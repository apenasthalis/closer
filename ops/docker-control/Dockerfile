FROM python:3.12-alpine

WORKDIR /app

COPY agent.py ./

EXPOSE 8099

HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
  CMD python -c "import urllib.request; urllib.request.urlopen('http://127.0.0.1:8099/health', timeout=2)"

CMD ["python", "agent.py"]
