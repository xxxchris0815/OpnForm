# AppFlowy Cloud hinter Caddy

Self-Hosted [AppFlowy Cloud](https://github.com/AppFlowy-IO/AppFlowy-Cloud) mit Docker Compose. TLS macht dein bestehendes Caddy; intern routet nginx die AppFlowy-Pfade.

Image-Stand (Docker Hub, 2026-09-09):

| Dienst | Image | Tag |
| --- | --- | --- |
| Cloud API | `appflowyinc/appflowy_cloud` | `0.18.6` |
| Auth (GoTrue) | `appflowyinc/gotrue` | `0.18.6` |
| Worker | `appflowyinc/appflowy_worker` | `0.18.6` |
| Web | `appflowyinc/appflowy_web` | `0.18.2` |
| Search | `appflowyinc/appflowy_search` | `0.18.4` |
| Admin | `appflowyinc/admin_frontend` | `0.17.13` |
| AI | `appflowyinc/appflowy_ai` | `0.17.7` |

## Enthaltene Dienste

- PostgreSQL 16 mit pgvector
- Redis 7
- MinIO (S3 für Uploads)
- GoTrue (Login / JWT)
- AppFlowy Cloud, Worker, Search, AI, Web, Admin-Konsole
- internes nginx (HTTP, ohne TLS)

## 1. Konfigurieren

```bash
cd appflowy-selfhost
cp .env.example .env
```

In `.env` mindestens setzen:

- `FQDN` – deine Domain, z. B. `appflowy.example.com`
- `POSTGRES_PASSWORD`, `GOTRUE_JWT_SECRET`, `GOTRUE_ADMIN_PASSWORD`
- `AWS_ACCESS_KEY` / `AWS_SECRET` (MinIO-Zugangsdaten)

Geheimnisse erzeugen:

```bash
openssl rand -base64 32
```

## 2. Stack starten

```bash
docker compose up -d
docker compose ps
```

AppFlowy lauscht danach auf `127.0.0.1:8080`.

## 3. Caddy

Snippet: `Caddyfile` in diesem Ordner. Domain anpassen und in deine bestehende Caddy-Config übernehmen.

### Variante A – Caddy auf demselben Host (Standard)

Caddy proxied auf den Host-Port:

```caddy
appflowy.example.com {
	encode gzip zstd
	request_body {
		max_size 2GB
	}
	reverse_proxy 127.0.0.1:8080 {
		header_up Host {host}
		header_up X-Real-IP {remote_host}
		header_up X-Forwarded-For {remote_host}
		header_up X-Forwarded-Proto {scheme}
		flush_interval -1
		transport http {
			read_timeout 24h
			write_timeout 10m
		}
	}
}
```

Läuft Caddy selbst in Docker **ohne** Host-Netzwerk, braucht der Caddy-Container Zugriff auf den Host:

```yaml
extra_hosts:
  - "host.docker.internal:host-gateway"
```

Dann in der Caddyfile `host.docker.internal:8080` statt `127.0.0.1:8080`.

Läuft Caddy mit `network_mode: host`, reicht `127.0.0.1:8080`.

### Variante B – gemeinsames Docker-Netz

```bash
docker network ls | grep -i caddy
# PROXY_NETWORK in .env auf den Netz-Namen setzen, falls er nicht `caddy` heißt

docker compose -f docker-compose.yml -f docker-compose.proxy.yml up -d
```

Caddyfile dann:

```caddy
reverse_proxy appflowy-nginx:80
```

Caddy muss demselben Netz (`PROXY_NETWORK`) beitreten.

Caddy auf einem **anderen Host**: in `.env` `APPFLOWY_BIND_ADDRESS=0.0.0.0` setzen und in Caddy die IP dieses Servers plus Port `8080` eintragen. Port `8080` nicht öffentlich ins Internet legen, nur zur Caddy-Maschine.

## 4. Erstes Login

- Web: `https://deine-domain/`
- Admin-Konsole: `https://deine-domain/console`
- MinIO-UI: `https://deine-domain/minio`

Desktop-App: Cloud-Server auf `https://deine-domain` stellen.

Admin-Login kommt aus `GOTRUE_ADMIN_EMAIL` / `GOTRUE_ADMIN_PASSWORD`.

## 5. Aktualisieren

Neue Tags in `.env` eintragen (oder auf Docker Hub prüfen), dann:

```bash
docker compose pull
docker compose up -d
```

## Hinweise

- Passwörter in URLs mit Sonderzeichen URL-encoden (`@` → `%40`).
- SMTP ist optional. Ohne SMTP bleibt `GOTRUE_MAILER_AUTOCONFIRM=true`.
- AI-Features brauchen `AI_OPENAI_API_KEY`. Ohne Key startet der Stack trotzdem.
- Postgres, Redis, MinIO und der Keyword-Index liegen in Docker-Volumes.
