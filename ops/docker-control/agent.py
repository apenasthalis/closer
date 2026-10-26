#!/usr/bin/env python3
"""Restricted local Docker agent for Closer OS.

Only exposes status for, and start of, an explicit allowlist of existing
containers. It never accepts arbitrary container names or shell commands.
"""

from __future__ import annotations

import hmac
import http.client
import json
import os
import socket
import sys
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

DOCKER_SOCKET = os.environ.get("DOCKER_SOCKET", "/var/run/docker.sock")
TOKEN = os.environ.get("DOCKER_CONTROL_TOKEN", "")
PORT = int(os.environ.get("DOCKER_CONTROL_PORT", "8099"))
WAIT_SECONDS = int(os.environ.get("DOCKER_START_WAIT_SECONDS", "25"))

ALLOWED = [
    "postgres-closer",
    "closer-php-1",
    "closer-nginx-1",
    "closer-frontend-1",
]


class DockerError(RuntimeError):
    pass


class UnixHTTPConnection(http.client.HTTPConnection):
    def __init__(self, socket_path: str, timeout: float = 5.0) -> None:
        super().__init__("localhost", timeout=timeout)
        self.socket_path = socket_path

    def connect(self) -> None:
        self.sock = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
        self.sock.settimeout(self.timeout)
        self.sock.connect(self.socket_path)


def docker_request(method: str, path: str, timeout: float = 5.0) -> tuple[int, bytes]:
    connection = UnixHTTPConnection(DOCKER_SOCKET, timeout=timeout)
    try:
        connection.request(method, path, headers={"Host": "docker", "Content-Type": "application/json"})
        response = connection.getresponse()
        return response.status, response.read()
    except (OSError, http.client.HTTPException) as exc:
        raise DockerError(f"Docker socket unavailable: {exc}") from exc
    finally:
        connection.close()


def docker_json(method: str, path: str, timeout: float = 5.0) -> tuple[int, dict]:
    status, body = docker_request(method, path, timeout)
    try:
        payload = json.loads(body.decode("utf-8")) if body else {}
    except (ValueError, UnicodeDecodeError):
        payload = {}
    return status, payload


def inspect_container(name: str) -> dict | None:
    status, payload = docker_json("GET", f"/containers/{name}/json")
    if status == 404:
        return None
    if status >= 400:
        raise DockerError(payload.get("message") or f"Docker respondeu HTTP {status}.")
    return payload


def describe(name: str, payload: dict | None) -> dict:
    if payload is None:
        return {
            "name": name,
            "state": "missing",
            "status": "not_created",
            "health": None,
            "running": False,
        }

    state = payload.get("State") or {}
    health = (state.get("Health") or {}).get("Status")
    running = bool(state.get("Running"))
    return {
        "name": name,
        "state": "running" if running else str(state.get("Status") or "stopped"),
        "status": str(state.get("Status") or "unknown"),
        "health": health,
        "running": running,
        "started_at": state.get("StartedAt"),
    }


def daemon_available() -> bool:
    try:
        status, _ = docker_request("GET", "/_ping", timeout=2)
        return status == 200
    except DockerError:
        return False


def status_payload() -> dict:
    if not daemon_available():
        return {
            "agent": "closer-docker-control",
            "available": False,
            "reason": "Daemon Docker inacessível pelo agente.",
            "services": [],
        }

    services = [describe(name, inspect_container(name)) for name in ALLOWED]
    missing = [service["name"] for service in services if service["state"] == "missing"]

    return {
        "agent": "closer-docker-control",
        "available": True,
        "reason": f"Containers ausentes: {', '.join(missing)}. Use ./dev.sh up." if missing else None,
        "services": services,
    }


def wait_until_running(names: list[str], deadline: float) -> None:
    pending = set(names)
    while pending and time.monotonic() < deadline:
        for name in list(pending):
            payload = inspect_container(name)
            if payload and (payload.get("State") or {}).get("Running"):
                pending.discard(name)
        if pending:
            time.sleep(1)
    if pending:
        raise DockerError(f"Tempo esgotado aguardando: {', '.join(sorted(pending))}.")


def start_payload() -> tuple[int, dict]:
    if not daemon_available():
        return 503, {"ok": False, "error": "Daemon Docker inacessível pelo agente."}

    inspected = {name: inspect_container(name) for name in ALLOWED}
    missing = [name for name, payload in inspected.items() if payload is None]
    if missing:
        return 409, {
            "ok": False,
            "error": f"Containers ainda não criados: {', '.join(missing)}. Execute ./dev.sh up.",
            "services": [describe(name, inspected[name]) for name in ALLOWED],
        }

    started: list[str] = []
    try:
        for name in ALLOWED:
            payload = inspected[name]
            state = payload.get("State") or {}
            if state.get("Running"):
                continue
            status, response = docker_json("POST", f"/containers/{name}/start", timeout=15)
            if status not in (204, 304):
                raise DockerError(response.get("message") or f"Falha ao iniciar {name} (HTTP {status}).")
            started.append(name)
            wait_until_running([name], time.monotonic() + WAIT_SECONDS)
    except DockerError as exc:
        return 502, {
            "ok": False,
            "error": str(exc),
            "started": started,
            "services": [describe(name, inspect_container(name)) for name in ALLOWED],
        }

    return 200, {
        "ok": True,
        "started": started,
        "reused": [name for name in ALLOWED if name not in started],
        "services": [describe(name, inspect_container(name)) for name in ALLOWED],
    }


class Handler(BaseHTTPRequestHandler):
    server_version = "CloserDockerControl/1.0"

    def log_message(self, fmt: str, *args) -> None:
        sys.stdout.write("%s - %s\n" % (self.address_string(), fmt % args))
        sys.stdout.flush()

    def send_json(self, status: int, payload: dict) -> None:
        body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(body)

    def authorized(self) -> bool:
        header = self.headers.get("Authorization", "")
        if not header.startswith("Bearer "):
            return False
        return hmac.compare_digest(header[7:], TOKEN)

    def route(self, method: str) -> None:
        path = self.path.split("?", 1)[0].rstrip("/") or "/"

        if method == "GET" and path == "/health":
            self.send_json(200, {"ok": True, "agent": "closer-docker-control"})
            return

        if not TOKEN or not self.authorized():
            self.send_json(401, {"ok": False, "error": "Não autorizado."})
            return

        try:
            if method == "GET" and path == "/status":
                self.send_json(200, status_payload())
                return
            if method == "POST" and path == "/start":
                code, payload = start_payload()
                self.send_json(code, payload)
                return
            self.send_json(404, {"ok": False, "error": "Operação não permitida."})
        except DockerError as exc:
            self.send_json(503, {"ok": False, "error": str(exc)})
        except Exception:
            self.log_error("erro interno no agente")
            self.send_json(500, {"ok": False, "error": "Erro interno no agente."})

    def do_GET(self) -> None:
        self.route("GET")

    def do_POST(self) -> None:
        length = int(self.headers.get("Content-Length") or 0)
        if length > 1024:
            self.send_json(413, {"ok": False, "error": "Corpo muito grande."})
            return
        if length:
            self.rfile.read(length)
        self.route("POST")

    def do_PUT(self) -> None:
        self.send_json(405, {"ok": False, "error": "Método não permitido."})

    def do_DELETE(self) -> None:
        self.send_json(405, {"ok": False, "error": "Método não permitido."})


def main() -> None:
    if not TOKEN:
        print("DOCKER_CONTROL_TOKEN não configurado; agente não iniciado.", file=sys.stderr)
        sys.exit(1)
    server = ThreadingHTTPServer(("0.0.0.0", PORT), Handler)
    print(f"closer-docker-control ouvindo em 0.0.0.0:{PORT}", flush=True)
    server.serve_forever()


if __name__ == "__main__":
    main()
