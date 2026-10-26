# Closer OS

CRM comercial completo com pipeline Kanban, ficha de lead, análise local de calls, propostas, contratos, pagamentos, comissões, relatórios, lembretes e administração. O frontend principal está em React e o backend em Laravel, com PostgreSQL e Nginx em Docker Compose.

## Stack

- Laravel 13 / PHP 8.3 / PostgreSQL 17
- React 19 / TypeScript / Vite
- Nginx com HTTPS self-signed em desenvolvimento
- Autenticação JWT HS256 própria, sem Sanctum/Passport/JWT externo
- Agente local restrito para o controle de Docker na barra superior

## Subir o ambiente

Pré-requisitos: Docker com Compose v2, Bash, curl e OpenSSL.

```bash
./dev.sh up
```

O comando é idempotente:

- reutiliza containers ativos e saudáveis;
- inicia containers parados sem recriá-los;
- cria somente serviços ausentes;
- aguarda PostgreSQL, API e frontend;
- aplica migrations não destrutivas;
- cria o seed inicial somente quando o banco não possui usuários;
- não executa `docker compose down`, não remove volumes e não recria toda a stack.

### URLs

- Produção: <https://tag-programming.com.br> (frontend e API no mesmo domínio)
- API/HTTPS: <https://tag-programming.com.br/api/v1/health>
- Nginx HTTP (redireciona para HTTPS): <http://localhost:8090>
- Nginx HTTPS direto: <https://localhost:8453/api/v1/health>
- PostgreSQL no host: `localhost:5434`

O nginx serve a SPA buildada e encaminha `/api` para o PHP-FPM na porta 9000.
Em produção o TLS é Let's Encrypt (`tag-programming.com.br` e `www`); o certificado
self-signed serve apenas para o acesso direto na porta 8453.

### Acesso

- E-mail: `admin@closer.local`
- Senha padrão: `Closer@2026!` (configurável por `DEMO_ADMIN_PASSWORD` antes do seed)

Em produção o banco contém apenas o admin: o `DemoDataSeeder` roda somente no
ambiente `local`, então leads, scripts, objeções, comissões e demais dados de
exemplo não são carregados. Os dados são inseridos manualmente.

## Comandos

```bash
./dev.sh up              # inicia/verifica o necessário
./dev.sh status          # estado real dos containers e migrations
./dev.sh logs php        # logs recentes por serviço
./dev.sh help            # ajuda

docker exec closer-frontend-1 npm run typecheck
docker exec closer-frontend-1 npm run build
docker exec closer-php-1 php artisan test
```

O frontend roda em Node 22 dentro do container. Use os comandos acima para evitar incompatibilidade com versões antigas do Node instaladas no host.

## Serviços e portas

| Serviço | Container | Porta no host | Observação |
|---|---|---|---|
| Nginx | `closer-nginx-1` | `8090` HTTP, `8453` HTTPS | serve a SPA e encaminha `/api` ao PHP-FPM |
| PHP-FPM | `closer-php-1` | interna `9000` | Laravel (API) |
| PostgreSQL | `postgres-closer` | `5434` | volume `closer_closer_pgdata` |
| Agente Docker | `closer-docker-control-1` | interna `8099` | allowlist e token |

O frontend é publicado apenas pelo nginx: não há container de dev do Vite na stack.

O Compose usa a rede `closer_app` e preserva os nomes de volume existentes.

## Produção na VPS

- Domínio: `tag-programming.com.br` (DNS `ns1.dns-parking.com` / `ns2.dns-parking.com`).
- As portas 80 e 443 da VPS são do ingress compartilhado; o nginx do Closer publica
  8090/8453 e o ingress encaminha `tag-programming.com.br` para `8453`.
- O `.env` do backend precisa ser legível pelo PHP-FPM (`chown 33:33` e `chmod 600`).
- Para publicar mudanças do frontend: build local e cópia dos assets.

```bash
cd frontend && npx vite build --outDir /tmp/closer-dist --emptyOutDir
scp -r /tmp/closer-dist/* ubuntu@<vps>:/tmp/closer-dist
scp -r /tmp/closer-dist/* ubuntu@<vps>:/opt/sources/closer/backend/public/
```

## Autenticação e segurança

- Access token JWT HS256 de curta duração mantido somente em memória no frontend.
- Refresh token opaco, armazenado apenas como hash SHA-256, com rotação e detecção de reutilização.
- Cookie `HttpOnly` restrito ao caminho de autenticação.
- Permissões validadas no backend em cada operação; o frontend apenas oculta ações indevidas.
- Bloqueio de origem e rate limiting nas rotas de login/refresh e no controle de Docker.
- O refresh no boot é single-flight para evitar corrida de rotação entre montagens do React StrictMode.

## Controle de Docker

O botão da barra superior consulta `GET /api/v1/system/docker` e solicita `POST /api/v1/system/docker/start`. A rota exige administrador, ambiente local e throttling. O navegador não executa comandos.

O agente `ops/docker-control/agent.py`:

- aceita somente `GET /status`, `POST /start` e `GET /health`;
- ignora qualquer nome de container enviado pelo cliente e usa uma allowlist fixa;
- inicia apenas containers existentes que estejam parados;
- nunca remove, recria, para ou executa comandos em containers;
- exige token Bearer e compara em tempo constante;
- fica apenas na rede interna, sem porta publicada.

**Limitação real:** se a stack inteira estiver parada, o frontend e a API também estarão indisponíveis, portanto o botão não pode ser acessado. Nesse caso use `./dev.sh up`. O botão recupera serviços parados quando a interface/API ainda estão no ar; containers ausentes exigem `./dev.sh up`.

O socket do Docker é montado somente no agente, nunca no frontend. Em produção, remova o serviço `docker-control` ou substitua-o por um mecanismo operacional fora do Compose.

## Variáveis de ambiente

- `backend/.env`: configuração do Laravel. O `dev.sh` cria um arquivo local se ele não existir e apenas adiciona chaves ausentes em arquivos existentes.
- `.env` na raiz (opcional): credenciais do PostgreSQL e `DOCKER_CONTROL_TOKEN` usados pelo Compose. Veja `.env.example`.
- Precedência do `dev.sh`: ambiente > `.env` da raiz > `backend/.env` > padrão local. Se o token do `backend/.env` divergir do token efetivo do Compose, o script avisa sem alterar o arquivo.
- Em qualquer ambiente compartilhado, troque `POSTGRES_PASSWORD` e `DOCKER_CONTROL_TOKEN` e mantenha o mesmo token no `backend/.env` e no Compose.
- Nunca versione `.env`, chaves JWT, tokens ou credenciais reais.

## Integrações externas

Google Agenda, WhatsApp Cloud, IA externa e SMTP aparecem com estado honesto de configuração. Sem credenciais reais elas permanecem “não configuradas”; nenhum envio ou sincronização é simulado. A análise de calls usa a heurística local por padrão.

## Testes e validações executadas

- Backend: suíte completa com 20 testes verdes e 86 asserções; autenticação JWT 5/5.
- Regressões cobertas: pagamentos carregando a proposta relacionada e biblioteca de scripts retornando o envelope `data`.
- Frontend: `npm run typecheck` sem erros, `npm run build` concluído e imagem Docker do frontend construída com `npm ci`.
- API real: login, refresh, dashboard, leads, kanban, calls, propostas, contratos, pagamentos, comissões, relatórios, usuários, times, nichos, configurações, auditoria, biblioteca e PDFs validados.
- Biblioteca real: 4 scripts, 1 playbook e 6 objeções carregados na interface; abertura de script validada.
- Chrome headless: login real pela interface, todas as 17 rotas autenticadas, reload completo em `/contracts`, modal de call concluída e modal de mudança de etapa sem erros de console, `.error-state` ou respostas de API com falha.
- Responsividade básica validada em viewport móvel de 390px.
- Botão Docker: status real, rejeição sem token/token inválido, rota desconhecida e operação idempotente validados; o caminho real de start foi testado parando o frontend e reiniciando-o pelo agente.

## Estrutura

```text
frontend/              React principal
frontend-vue/          referência visual preservada
backend/               Laravel/API
ops/docker-control/    agente Docker restrito
docker-compose.yml     stack completa
dev.sh                 inicialização idempotente
```
