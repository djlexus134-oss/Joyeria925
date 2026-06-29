# Contexto del proyecto — Platería El Ángel (Joyería)

Documento de referencia para asistentes de IA que trabajen en este repositorio. Describe arquitectura, convenciones, módulos y puntos críticos del sistema.

---

## Resumen ejecutivo

Sistema integral para una **joyería/platería** en México: inventario de piezas físicas (stock unitario con código de barras), punto de venta (POS), apartados, devoluciones, cierre de caja, tienda en línea, facturación CFDI 4.0, notificaciones (correo/WhatsApp), impresión de tickets y etiquetas, y panel administrativo con roles/permisos.

**Nombre comercial:** Platería El Ángel  
**Base de datos:** `joyeria` (MariaDB/MySQL)  
**Idioma de la UI:** español (México)  
**Zona horaria por defecto:** `America/Mexico_City`

---

## Stack tecnológico

| Capa | Tecnología |
|------|------------|
| Backend | PHP 8+ (sin framework; PDO directo) |
| Base de datos | MariaDB/MySQL (`utf8mb4`) |
| Frontend admin | PHP SSR + HTML/CSS/JS vanilla |
| KPI Dashboard | React + Vite (build → `admin/js/kpi-dashboard/`) |
| Tienda pública | PHP SSR + JS (`js/tienda-carrito.js`, GSAP en landing) |
| Dependencias PHP | Composer: PHPMailer, mPDF, php-dotenv, picqer/barcode |
| Impresión local | Agentes Node/Python en Windows (`print-agent`, `print-agent-etiquetas`) |
| Deploy | VPS Linux (Nginx + PHP-FPM) + PC caja Windows |

---

## Arquitectura de despliegue

```
Internet → VPS (Nginx + PHP + MariaDB)
              ├── Navegador admin / POS / tienda web
              └── cola_impresion (tabla BD)
                        ↓ poll HTTPS (X-Caja-Token)
              PC caja Windows
              ├── print-agent        → tickets Epson (ESC/POS)
              └── print-agent-etiquetas → etiquetas Argox (PPLA/ZPL)
```

Guía completa: `deploy/DEPLOY.md`

**Secretos (nunca en Git):** `config.php`, `.env`, `print-agent*/config.json`, tokens SMTP/WhatsApp/Facturama/MercadoPago/Turnstile.

---

## Estructura de directorios

```
Joyeria/
├── config.example.php          # Plantilla de config (copiar a config.php)
├── sistema.class.php           # Clase base: conexión PDO, uploads de imágenes
├── bootstrap.php               # Auth JSON para APIs raíz (sesión tienda/admin)
├── index.php                   # Landing + catálogo público embebido
├── catalogo.php                # Catálogo visitante
├── tienda_*_api.php            # APIs públicas: auth, carrito, checkout, pieza
├── login.php                   # Login/registro cliente (paneles unificados)
├── confirmacion_correo_pendiente.php  # Post-registro: revisar correo + reenvío
├── verificar_correo.php        # Confirmación de cuenta (enlace del correo)
├── includes/                   # Helpers compartidos (timezone, promos, imágenes, turnstile)
├── css/, js/                   # Estilos y scripts del sitio público
├── sql/                        # Migraciones incrementales (YYYY_MM_DD_*.sql)
├── deploy/                     # Scripts y guías de despliegue
├── print-agent/                # Agente tickets Windows
├── print-agent-etiquetas/      # Agente etiquetas Argox
├── user/                       # Área cliente logueado (carrito, compras, cuenta)
└── admin/
    ├── *.php                   # Controladores (un script por módulo)
    ├── login.php, logout.php
    ├── models/                 # Clases que extienden Sistema (acceso BD)
    ├── views/                  # Plantillas PHP por módulo + header/footer
    ├── includes/               # Auth, servicios, helpers, sesión
    ├── api/                    # Endpoints JSON (requieren sesión admin)
    ├── js/                     # Scripts admin (POS, autocomplete, etiquetas…)
    ├── services/               # Servicios especializados (ej. EtiquetaZplService)
    └── kpi-dashboard/          # Fuente React del panel KPI
```

---

## Patrón de código (convenciones)

### Controlador (`admin/modulo.php`)

1. `require_once` de `sistema.class.php`, modelos y helpers.
2. Instancia del modelo (`$app = new Pieza()`).
3. Lee `$_GET['accion']` y `$_GET['id']`.
4. `require_once views/header.php` (aplica guard de permisos).
5. `switch ($accion)` con casos: `leer`, `crear`, `actualizar`, `borrar`, etc.
6. Incluye vistas en `admin/views/modulo/`.
7. `require_once views/footer.php`.

Las acciones GET usan query string: `pieza.php?accion=leer`, `pieza.php?accion=crear`, `pieza.php?accion=actualizar&id=5`.

### Modelo (`admin/models/*.php`)

- Extiende `Sistema`.
- Acceso a BD vía `$this->getDb()` (PDO preparado).
- Métodos típicos: `leer()`, `leerUno()`, `crear()`, `actualizar()`, `borrar()`.
- Algunos modelos incluyen `auth.php` para auditoría (`auth_mysql_set_audit_user()`).

### Vistas (`admin/views/<modulo>/`)

- `index.php` — listado con búsqueda (`joyeria_list_search_normalize`, `?q=`).
- `formulario.php` — alta/edición.
- Partials reutilizables en `admin/views/partials/`.

### APIs JSON

- Admin: `admin/api/*.php` → incluyen `admin/api/bootstrap.php` (sesión admin).
- Públicas/tienda: `tienda_*_api.php` en raíz.
- Respuesta estándar: `{ "success": true/false, "error": "...", ... }`.

### Configuración clave-valor

Tabla `configuracion_general` (claves como `id_tienda_default`, `smtp_host`, datos de facturación).  
Modelo: `admin/models/configuracion_general.php`.  
Panel UI: `admin/configuracion_general.php` + `ConfiguracionGeneralPanelController.php`.  
Defaults en `admin/includes/configuracion_plantilla_defaults.php`.

Constantes sensibles en `config.php` (ver `config.example.php`): BD, SMTP, WhatsApp token, Facturama, Cloudflare Turnstile, URL pública, timezone, lifetime de sesión.

---

## Autenticación y permisos

### Dos sesiones independientes

| Sesión | Clave | Usuarios |
|--------|-------|----------|
| Admin | `joyeria_admin_auth` | Empleados con roles/permisos |
| Tienda | `joyeria_tienda_auth` | Clientes (`usuarios` + `clientes`) |

Archivos: `admin/includes/auth.php`, `admin/includes/tienda_auth.php`, `admin/includes/joyeria_session.php`.

### Sistema RBAC

- Tablas: `usuarios`, `roles`, `permisos`, `usuario_rol`, `rol_permiso`.
- Permisos nombrados: `{MODULO}_{ACCION}` — ej. `PIEZA_LEER`, `PIEZA_CREAR`, `VENTA_ACTUALIZAR`, `PIEZA_FOTO`.
- Acciones: `LEER`, `CREAR`, `ACTUALIZAR`, `BORRAR`, `FOTO`.
- Mapeo script → módulo en `auth_module_map()` dentro de `auth.php`.
- El menú lateral se filtra con `auth_visible_nav_groups()`.
- Capacidades expuestas al DOM: `data-can-create`, `data-can-update`, etc. en `<body>`.

**Al agregar un módulo nuevo:**

1. Crear `admin/modulo.php` + modelo + vistas.
2. Registrar en `auth_module_map()`.
3. Añadir entrada en `auth_nav_groups()`.
4. Crear permisos en BD (`MODULO_LEER`, etc.) y asignar a roles.
5. Opcional: migración SQL en `sql/`.

---

## Módulos principales

### Inventario y catálogo

- **Piezas** (`pieza.php`): catálogo maestro (familia, subfamilia, metal, costo directo o por gramo, markup, fotos).
- **Stock** (sub-vista en `pieza.php?accion=stock`; redirect desde `piezas_stock.php`): unidades físicas con código de barras, estado (`disponible`, `vendida`, `apartada`, `reservada_online`, `reservada_pos`, etc.), talla/color (variantes).
- **Insumos** (`insumos.php`, `insumos_etiquetas.php`): materiales consumibles con etiquetas propias.
- **Recuento** (`inventario_recuento.php`): conteo físico con permisos granulares.
- **Capital inventario** (`capital_inventario.php`): valor del inventario en tienda.
- **Sugerencia resurtido** (`piezas_vendidas.php`).

### Comercial / POS

- **Punto de venta** (`punto_venta.php`): escáner, carrito, descuentos, SPEI QR, stock alerts, **reserva temporal de stock** (`PosReservaStockService`, estado `reservada_pos`, TTL 2 h).
- **Ventas** (`ventas.php`): historial y detalle de ventas mostrador.
- **Ventas online** (`ventas_online.php`): pedidos web (MercadoPago, crédito cliente).
- **Apartados** (`apartados_alta.php`, `apartados_operaciones.php`, `apartados_cambio.php`).
- **Devoluciones** (`devoluciones.php`, `devoluciones_mostrador.php`, `devoluciones_credito.php`): canje, monedero cliente.
- **Clientes** (`cliente.php`): descuentos, crédito, monedero, datos fiscales.
- **Promociones** (`promociones.php`, `promociones_banner.php`).
- **Cierre de caja** / **Arqueo** (`cierre_caja.php`, `arqueo_caja.php`).
- **Gastos** (`gastos.php`, `gastos_categoria.php`).

### Taller

- **Órdenes de taller** (`ordenes_taller.php`): reparaciones/encargos con seguimiento, comprobante, fotos de pieza.

### Facturación

- **Facturas CFDI** (`facturas.php`): integración Facturama PAC.
- Servicios: `FacturamaClient.php`, `CfdiBuilder.php`, `FacturaEnvioService.php`, `factura_auto.php`.
- Migración: `sql/2026_06_06_facturacion_cfdi.sql`.

### RRHH

- **Empleados**, **Puestos**, **Contratos** (`empleado.php`, `puesto.php`, `contratos_empleados.php`).
- PDF contratos con mPDF (`joyeria_mpdf.php`, `PDFGenerator.php`).

### Proveedores

- **Proveedores** (`proveedores.php`): CRUD de proveedores + contactos unificados (`accion=contactos`, `contacto_crear`, etc.). `proveedor_contactos.php` redirige al módulo padre.

### Sistema

- **Configuración general** (negocio, mensajería, facturación, etiquetas, ticket POS).
- **Notificaciones** + **Enviar notificaciones** (correo masivo / plantillas).
- **Direcciones** (catálogo SEPOMEX: países, estados, municipios, colonias, CP).
- **KPI Dashboard** (`admin/index.php` + build React).

---

## Tienda pública y área cliente

### Sitio visitante (sin login)

- `index.php` — landing con vitrina y catálogo por familia; enlace a `login.php` para cuenta/carrito.
- `catalogo.php` — catálogo extendido con familias (estilo móvil) y selector de variantes (talla/color).
- `login.php` + `js/login-general.js` — login, registro y recuperación de contraseña.
- APIs: `tienda_pieza_api.php`, `tienda_carrito_api.php`, `tienda_checkout_api.php`, `tienda_auth_api.php`.
- Promociones: `includes/promociones_tienda_publica.php`, `includes/catalogo_banner_promos.php`.
- Variantes públicas: `includes/catalogo_variantes_publico.php`, `includes/variantes_stock_helpers.php`.
- Stock mínimo para compra online: `Pieza::MIN_STOCK_DISPONIBLE_CATALOGO_ONLINE = 2`.
- Nav pública: Catálogo (dropdown familias) + Promociones; sin enlace Contacto.

### Área cliente (`user/`)

- `index.php`, `carrito.php`, `compras.php`, `cuenta.php`, `checkout_resultado.php` (`apartados.php` existe pero sin enlace en nav).
- Requiere sesión tienda (`tienda_auth`).
- Nav cliente: Catálogo + Promociones + Compras (sin Apartados ni Contacto).

### Registro y verificación de correo (tienda)

Flujo post-registro (jun 2026):

1. `tienda_auth_api.php` → `register` crea cuenta, envía correo (`TiendaEmailVerificationService` + `MailService`) y redirige a `confirmacion_correo_pendiente.php`.
2. Sesión flash: `tienda_verificacion_pendiente_correo` (`tienda_set/get/clear_verificacion_pendiente` en `tienda_auth.php`).
3. Página pendiente: mensaje + reenvío tras 30 s (`js/confirmacion-correo-pendiente.js`).
4. Login con correo no verificado → redirect automático a la misma página (no hay reenvío en `login.php`).
5. Enlace del correo → `verificar_correo.php?token=...` → limpia sesión pendiente → login.

Migración: `sql/2026_06_10_verificacion_correo_tienda.sql`.

### Protección anti-bot (Cloudflare Turnstile)

- Helper: `includes/turnstile_helpers.php`; JS compartido: `js/turnstile-form.js`.
- Config: `JOYERIA_TURNSTILE_ENABLED`, `JOYERIA_TURNSTILE_SITE_KEY`, `JOYERIA_TURNSTILE_SECRET_KEY` en `config.php`.
- Validación server-side en `tienda_auth_api.php`: acciones `login`, `register`, `forgot_password`, `resend_verification`.
- Widget en `login.php` (cada pane) y en `confirmacion_correo_pendiente.php` (al mostrar reenvío).
- **No requiere** proxy DNS de Cloudflare; solo widget en dashboard Turnstile.

### Pagos

- MercadoPago: `admin/includes/MercadoPagoService.php`.
- SPEI depósito (QR): `SpeiDepositoPayloadBuilder.php`, config en panel.

---

## Impresión

### Cola en BD (`cola_impresion`)

Modelo: `admin/models/cola_impresion.php`.

| Tipo | Uso |
|------|-----|
| `venta`, `reimpresion`, `apartado_*` | Tickets ESC/POS |
| `etiqueta_stock`, `etiqueta_lote` | Etiquetas piezas |
| `etiqueta_insumo`, `etiqueta_insumo_lote` | Etiquetas insumos |

Servicios: `TicketService.php`, `TicketEscPosBuilder.php`, `ImpresionEtiquetaHelper.php`, `EtiquetaZplService.php` (modo IMAGEN: variante talla/color en pad central vía `joyeria_texto_etiqueta_variante()`).  
API poll: `admin/api/impresion.php` (autenticación `X-Caja-Token`).

Los agentes Windows hacen polling y envían RAW a impresoras USB.

---

## Integraciones externas

| Servicio | Archivo principal | Config |
|----------|-------------------|--------|
| SMTP (correo) | `MailService.php` | `config.php` o `configuracion_general` |
| WhatsApp Cloud API | `WhatsAppService.php` | Token en `config.php`; resto en panel |
| Facturama (CFDI 4.0) | `FacturamaClient.php` | `config.php` + panel facturación |
| MercadoPago | `MercadoPagoService.php` | `configuracion_general` |
| Códigos de barras | picqer + `joyeria-barcode-input.js` | Panel etiquetas |
| Cloudflare Turnstile | `includes/turnstile_helpers.php` | `config.php` (`JOYERIA_TURNSTILE_*`) |

---

## Base de datos y migraciones

- Migraciones incrementales en `sql/` con prefijo de fecha: `2026_06_05_*.sql`.
- Ejecutar en orden cronológico al desplegar.
- Vistas KPI: `sql/kpi_views.sql`.
- Migración datos legado (Gema): `sql/migracion_gema/` (no subir dumps al repo).
- Triggers de auditoría usan variable de sesión MySQL `@current_user_id` — resolver con `auth_mysql_resolve_audit_user_id()`.

### Entidades centrales (referencia)

- Catálogo: `familias`, `sub_familias`, `metales`, `piezas`, `piezas_stock`, `insumos`.
- Ventas: `ventas`, `venta_detalle`, `forma_pago`, `impuestos`.
- Clientes: `clientes`, `usuarios`, crédito/monedero.
- Apartados: tablas de gestión de apartados y abonos.
- Caja: `cierre_caja`, movimientos de arqueo.
- Online: `venta_online`, `carrito` (con snapshot de promoción).
- Verificación tienda: `token_verificacion_correo_tienda`, columna `usuarios.correo_verificado_en`.
- Facturación: tablas CFDI (ver migración `2026_06_06_facturacion_cfdi.sql`).
- Taller: `ordenes_taller` (ver `2026_06_05_ordenes_taller_modulo.sql`).

---

## Frontend y estilos

- CSS global: `css/main.css`.
- CSS admin: `css/admin.css`.
- Iconos: Bootstrap Icons + Font Awesome (CDN).
- Charts: Chart.js (CDN) en admin.
- JS admin notable: `fk-autocomplete.js`, `pos-spei-qr.js`, `pos-stock-alert.js`, `etiquetas-print.js`, `ordenes-taller-form.js`, `pieza-foto-capture.js`.
- JS tienda notable: `login-general.js`, `confirmacion-correo-pendiente.js`, `turnstile-form.js`, `tienda-carrito.js`, `catalog-carousel.js`.
- Formularios admin: patrón `form-group` + `form-input` (+ alias `.form-control` en `css/admin.css`); referencia POS y módulos apartados/devoluciones unificados.
- Búsqueda en listados: parámetro `?q=` + opcional `?campo=`.

---

## Desarrollo local

```powershell
# Config
copy config.example.php config.php
# Editar credenciales BD y servicios

# Dependencias PHP
composer install

# KPI dashboard (opcional)
cd admin\kpi-dashboard
npm ci
npm run build
```

**No commitear:** `config.php`, `.env`, `vendor/`, `node_modules/`, configs de agentes, uploads, logs.

---

## Principios al modificar código

1. **Cambios mínimos** — seguir el patrón existente del módulo; no introducir frameworks.
2. **PDO preparado** — nunca concatenar input de usuario en SQL.
3. **Permisos** — verificar con `auth_can_module_action()` o el guard del header; no omitir en APIs.
4. **Español** — mensajes de UI y errores en español mexicano.
5. **Migraciones** — cambios de esquema en `sql/` con nombre fechado; no alterar producción sin script.
6. **Secretos** — constantes sensibles solo en `config.php`, nunca en `configuracion_general` si superan límites o son tokens.
7. **Zona horaria** — usar helpers de `includes/joyeria_timezone.php` para fechas de negocio.
8. **Imágenes** — subir vía `Sistema::moverImagenSubida()` / `upload_helpers.php`; validar MIME real.

---

## Archivos clave para leer primero

| Archivo | Por qué |
|---------|---------|
| `sistema.class.php` | Conexión BD, uploads |
| `config.example.php` | Variables de entorno |
| `admin/includes/auth.php` | Permisos, menú, guards |
| `admin/views/header.php` | Layout admin, capabilities |
| `admin/models/configuracion_general.php` | Config del negocio |
| `admin/includes/tienda_auth.php` | Sesión cliente, verificación pendiente |
| `admin/includes/PosReservaStockService.php` | Reserva stock POS |
| `tienda_auth_api.php` | Auth JSON tienda + Turnstile |
| `includes/turnstile_helpers.php` | Verificación Cloudflare Turnstile |
| `deploy/DEPLOY.md` | Arquitectura producción |
| Un controlador + modelo + vista de referencia (ej. `pieza.php`) | Patrón CRUD |

---

## Funcionalidades recientes (junio 2026)

Implementado / reciente según el estado del repo:

- **Verificación correo tienda:** registro con confirmación por enlace, página `confirmacion_correo_pendiente.php`, reenvío diferido 30 s.
- **Cloudflare Turnstile:** anti-bot en login, registro, recuperación y reenvío de correo.
- **Reserva stock POS:** `reservada_pos` + `pos_reserva_token`, TTL 2 h; libera al quitar/cancelar ticket; cobro valida token (`sql/2026_06_10_reserva_pos.sql`).
- **Variantes catálogo online:** familias estilo móvil, selector talla/color en tienda pública (`sql/2026_06_10_variantes_catalogo.sql`).
- **Etiquetas con variante:** talla/color en pad central PNG (modo IMAGEN), sin alterar `desc_pieza`.
- **Nav tienda simplificada:** sin Apartados/Contacto en header; Catálogo centrado en nav.
- **Módulos unificados en menú:** Proveedores + contactos; Piezas + stock (patrón insumos/etiquetas).
- **Formularios admin unificados:** `form-group`/`form-input` en apartados y devoluciones.
- Facturación CFDI 4.0 (Facturama).
- Órdenes de taller.
- SPEI depósito en POS.
- WhatsApp Cloud API (un `phone_number_id` en config; número dedicado, no el personal).
- Promociones con snapshot en carrito.
- Etiquetas de insumos en cola de impresión.
- Inventario recuento sin borrar empleado.
- Descuentos decimales en cliente e insumos mostrador.
- Venta online con crédito cliente.
- Envío masivo de notificaciones.

Migraciones jun 2026: `sql/2026_06_10_verificacion_correo_tienda.sql`, `sql/2026_06_10_reserva_pos.sql`, `sql/2026_06_10_variantes_catalogo.sql` y resto de `sql/2026_06_*.sql`.

---

## Glosario del negocio

| Término | Significado |
|---------|-------------|
| Pieza | Producto de catálogo (modelo/descripción); no es una unidad física |
| Stock / piezas_stock | Unidad física individual con código de barras |
| Apartado | Reserva de pieza(s) con abonos parciales |
| reservada_pos | Stock bloqueado temporalmente en ticket POS sin cobrar |
| reservada_online | Stock reservado por carrito/checkout web |
| Mostrador | Venta presencial en tienda (POS) |
| Insumo | Material consumible (no joya terminada) |
| Monedero | Saldo a favor del cliente (devoluciones/crédito) |
| CFDI | Comprobante fiscal digital mexicano |
| Cierre de caja | Cuadre diario de efectivo y formas de pago |

---

*Última actualización: 10 junio 2026. Mantener este archivo al añadir módulos, integraciones o cambios arquitectónicos relevantes.*
