# Dominio Hostinger → VPS externo

Dominio registrado en **Hostinger**; aplicación en un **VPS de otro proveedor**. Hostinger solo gestiona el **DNS**; el sitio corre en la IP del VPS.

## Paso 1 — IP del VPS

En el panel de tu proveedor (Hetzner, DigitalOcean, etc.) copia la **IPv4 pública**.

En `/etc/joyeria/env` del VPS:

```bash
JOYERIA_DOMAIN=tudominio.com
JOYERIA_VPS_IP=203.0.113.50
```

## Paso 2 — DNS en hPanel (Hostinger)

1. [hPanel](https://hpanel.hostinger.com/) → **Dominios** → tu dominio.
2. **DNS / Zona DNS** (Administrar DNS).
3. Si el dominio está en “parking” o enlazado al hosting web de Hostinger, desvincúlalo para editar registros manualmente (tu app no está en ese hosting).

| Tipo | Nombre | Contenido / Apunta a | TTL |
|------|--------|----------------------|-----|
| **A** | `@` | IP del VPS | 300 |
| **A** | `www` | misma IP del VPS | 300 |

Alternativa para `www`: **CNAME** `www` → `tudominio.com`.

No crees **AAAA** salvo que tu VPS tenga IPv6 configurado en Nginx.

Guarda y espera propagación (suele ser 5–60 minutos).

## Paso 3 — Verificar DNS (en el VPS)

```bash
bash /var/www/joyeria/deploy/scripts/verify-dns.sh
```

Desde tu PC (PowerShell con WSL o web): [dnschecker.org](https://dnschecker.org)

## Paso 4 — Nginx + HTTPS (en el VPS)

```bash
bash /var/www/joyeria/deploy/scripts/setup-domain.sh
bash /var/www/joyeria/deploy/scripts/setup-ssl.sh
bash /var/www/joyeria/deploy/scripts/validate-domain.sh
```

Define en env antes de SSL (opcional):

```bash
JOYERIA_SSL_EMAIL=tu@correo.com
```

## Paso 5 — Agentes Windows (PC caja)

En `print-agent/config.json` y `print-agent-etiquetas/config.json`:

```json
"serverUrl": "https://tudominio.com/admin"
```

Token igual que `impresion_caja_token` en el admin.

## Errores frecuentes

| Síntoma | Solución |
|---------|----------|
| Página de Hostinger / “próximamente” | Quita enlace al hosting Hostinger; solo registros **A** al VPS |
| Certbot falla | Puerto 80 abierto; usa `setup-domain.sh` (HTTP sin redirect) antes de `setup-ssl.sh` |
| `dig` no muestra tu IP | Revisa registro `@`; espera TTL |

Guía completa de deploy: [DEPLOY.md](DEPLOY.md)
