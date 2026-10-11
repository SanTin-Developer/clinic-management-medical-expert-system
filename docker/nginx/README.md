# TLS / reverse proxy (production)

The Laravel API is the only component that should be exposed to clients. In
production, terminate TLS at a reverse proxy and forward to `backend:8000` over
the private Docker network. The Python expert system must stay unpublished.

Example configuration: [`clinic.conf.example`](clinic.conf.example).

## 1. Provide certificates

Use a real certificate (e.g. Let's Encrypt) and mount it read-only into the
proxy at `/etc/nginx/certs/`. For **local testing only**, generate a self-signed
pair:

```sh
mkdir -p docker/nginx/certs
openssl req -x509 -nodes -newkey rsa:2048 -days 365 \
  -keyout docker/nginx/certs/privkey.pem \
  -out docker/nginx/certs/fullchain.pem \
  -subj "/CN=api.example.com"
```

## 2. Run an edge proxy

A minimal proxy service (add to a `docker-compose.prod.yml`, not the base file):

```yaml
services:
  proxy:
    image: nginx:1.27-alpine
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx/clinic.conf.example:/etc/nginx/conf.d/clinic.conf:ro
      - ./docker/nginx/certs:/etc/nginx/certs:ro
    depends_on:
      - backend
    networks:
      - clinic
```

Then deploy **without** `docker-compose.override.yml` so the expert system has no
published port:

```sh
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

## 3. Checklist

- [ ] Real `EXPERT_SYSTEM_API_KEY` set (see the root `.env`).
- [ ] `EXPERT_SYSTEM_URL` left as `http://expert-system:8001` (internal only).
- [ ] `docker-compose.override.yml` NOT deployed.
- [ ] `APP_ENV=production`, `APP_DEBUG=false` on the backend.
- [ ] Certificates mounted read-only; auto-renewal configured.
- [ ] Firewall: only 80/443 reachable; 8000/8001/5432 not public.
