<?php
require_once __DIR__ . '/../../sistema.class.php';
require_once __DIR__ . '/joyeria_session.php';

joyeria_session_start();

const JOYERIA_AUTH_SESSION_KEY = 'joyeria_admin_auth';
const JOYERIA_AUTH_FLASH_KEY = 'joyeria_admin_flash';

class AdminAuthService extends Sistema
{
  /**
   * Indica si el usuario puede entrar al panel admin (tiene rol o permisos).
   */
    public function canAccessAdminPanel(int $idUsuario): bool
    {
        $accesos = $this->getRolesAndPermissions($idUsuario);
        if (auth_has_admin_role_in_array($accesos['roles'])) {
            return true;
        }

        return !empty($accesos['permissions']);
    }

    public function findUserByEmail(string $correo): ?array
    {
        $correo = mb_strtolower(trim($correo));
        $sql = "SELECT id_usuario,
                       nombre,
                       primer_apellido,
                       segundo_apellido,
                       correo,
                       contrasena,
                       activo
                FROM usuarios
                WHERE LOWER(TRIM(correo)) = :correo
                LIMIT 1";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':correo', $correo, PDO::PARAM_STR);
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        return $usuario ?: null;
    }

    public function getRolesAndPermissions(int $idUsuario): array
    {
        $sql = "SELECT DISTINCT r.nombre_rol,
                               p.nombre_permiso
                FROM usuario_rol ur
                INNER JOIN roles r ON r.id_rol = ur.id_rol_FK AND r.activo = 1
                LEFT JOIN rol_permiso rp ON rp.id_rol_FK = r.id_rol
                LEFT JOIN permisos p ON p.id_permiso = rp.id_permiso_FK AND p.activo = 1
                WHERE ur.id_usuario_FK = :id_usuario";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();

        $roles = [];
        $permisos = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['nombre_rol'])) {
                $roles[] = mb_strtoupper(trim((string) $row['nombre_rol']));
            }
            if (!empty($row['nombre_permiso'])) {
                $permisos[] = mb_strtoupper(trim((string) $row['nombre_permiso']));
            }
        }

        $roles = array_values(array_unique($roles));
        $permisos = array_values(array_unique($permisos));

        return [
            'roles' => $roles,
            'permissions' => $permisos,
        ];
    }

    /**
     * Explica por que un usuario no puede entrar al panel admin.
     *
     * @return array{allowed: bool, code: string, message: string, hints: string[]}
     */
    public function diagnosePanelAccess(int $idUsuario): array
    {
        $accesos = $this->getRolesAndPermissions($idUsuario);
        if (auth_has_admin_role_in_array($accesos['roles'])) {
            return [
                'allowed' => true,
                'code' => 'OK',
                'message' => '',
                'hints' => [],
            ];
        }
        if (!empty($accesos['permissions'])) {
            return [
                'allowed' => true,
                'code' => 'OK',
                'message' => '',
                'hints' => [],
            ];
        }

        $stmt = $this->getDb()->prepare(
            "SELECT COUNT(*) FROM usuario_rol WHERE id_usuario_FK = :id"
        );
        $stmt->bindValue(':id', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();
        $asignaciones = (int) $stmt->fetchColumn();

        if ($asignaciones === 0) {
            return [
                'allowed' => false,
                'code' => 'ADMIN_NO_ROLE',
                'message' => 'Tu usuario existe pero no tiene ningun rol asignado en Seguridad > Usuario Rol.',
                'hints' => [
                    'Un administrador debe asignarte un rol (por ejemplo ADMINISTRADOR).',
                    'El puesto "Empleado" en RH no es lo mismo que el rol de seguridad.',
                ],
            ];
        }

        $stmt = $this->getDb()->prepare(
            "SELECT GROUP_CONCAT(r.nombre_rol ORDER BY r.nombre_rol SEPARATOR ', ') AS roles
             FROM usuario_rol ur
             INNER JOIN roles r ON r.id_rol = ur.id_rol_FK
             WHERE ur.id_usuario_FK = :id AND COALESCE(r.activo, 1) = 0"
        );
        $stmt->bindValue(':id', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();
        $rolesInactivos = trim((string) ($stmt->fetchColumn() ?: ''));

        if ($rolesInactivos !== '') {
            return [
                'allowed' => false,
                'code' => 'ADMIN_ROLE_INACTIVE',
                'message' => 'Tienes rol(es) asignado(s) pero estan inactivos: ' . $rolesInactivos . '.',
                'hints' => [
                    'En Seguridad > Roles activa el rol o asigna otro rol vigente.',
                ],
            ];
        }

        $stmt = $this->getDb()->prepare(
            "SELECT GROUP_CONCAT(DISTINCT r.nombre_rol ORDER BY r.nombre_rol SEPARATOR ', ') AS roles
             FROM usuario_rol ur
             INNER JOIN roles r ON r.id_rol = ur.id_rol_FK AND COALESCE(r.activo, 1) = 1
             WHERE ur.id_usuario_FK = :id"
        );
        $stmt->bindValue(':id', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();
        $rolesActivos = trim((string) ($stmt->fetchColumn() ?: ''));

        return [
            'allowed' => false,
            'code' => 'ADMIN_NO_PERMISSIONS',
            'message' => 'Tienes rol(es) (' . ($rolesActivos !== '' ? $rolesActivos : '?') . ') pero ningun permiso activo en Rol Permiso.',
            'hints' => [
                'Revisa Seguridad > Rol Permiso y asigna permisos al rol.',
                'O asigna el rol ADMINISTRADOR en Usuario Rol.',
            ],
        ];
    }

    public function logBitacora(?int $idUsuario, string $tipoEvento, string $observaciones = ''): void
    {
        try {
            $sql = "INSERT INTO bitacora_movimientos
                        (tabla_afectada, id_registro, tipo_evento, id_usuario_FK, ip_origen, observaciones)
                    VALUES
                        (:tabla_afectada, :id_registro, :tipo_evento, :id_usuario_fk, :ip_origen, :observaciones)";

            $stmt = $this->getDb()->prepare($sql);
            $stmt->bindValue(':tabla_afectada', 'usuarios', PDO::PARAM_STR);
            $stmt->bindValue(':id_registro', (int) ($idUsuario ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':tipo_evento', $tipoEvento, PDO::PARAM_STR);
            if ($idUsuario === null) {
                $stmt->bindValue(':id_usuario_fk', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':id_usuario_fk', $idUsuario, PDO::PARAM_INT);
            }
            $stmt->bindValue(':ip_origen', (string) ($_SERVER['REMOTE_ADDR'] ?? ''), PDO::PARAM_STR);
            $stmt->bindValue(':observaciones', $observaciones, PDO::PARAM_STR);
            $stmt->execute();
        } catch (Throwable $e) {
            // La bitacora no debe bloquear el flujo de autenticacion.
        }
    }
}

function auth_service(): AdminAuthService
{
    static $service = null;
    if ($service === null) {
        $service = new AdminAuthService();
    }
    return $service;
}

function auth_module_map(): array
{
    return [
        'index' => 'PANEL',
        'kpi_dashboard' => 'PANEL',
        'familia' => 'FAMILIA',
        'sub_familia' => 'SUB_FAMILIA',
        'rol' => 'ROL',
        'permiso' => 'PERMISO',
        'puesto' => 'PUESTO',
        'metales' => 'METALES',
        'forma_pago' => 'FORMA_PAGO',
        'talleres' => 'TALLER',
        'ordenes_taller' => 'ORDEN_TALLER',
        'proveedores' => 'PROVEEDOR',
        'proveedor_contactos' => 'PROVEEDOR_CONTACTO',
        'tiendas' => 'TIENDA',
        'cliente' => 'CLIENTE',
        'pieza' => 'PIEZA',
        'piezas_stock' => 'PIEZA_STOCK',
        'inventario_recuento' => 'INVENTARIO_RECUENTO',
        'piezas_vendidas' => 'PIEZAS_VENDIDAS',
        'capital_inventario' => 'CAPITAL_INVENTARIO',
        'insumos' => 'INSUMO',
        'insumos_etiquetas' => 'INSUMO',
        'catalogo_compra' => 'CATALOGO_COMPRA',
        'promociones' => 'PROMOCION',
        'promociones_banner' => 'PROMOCION',
        'gastos_categoria' => 'GASTO_CATEGORIA',
        'variantes' => 'VARIANTE',
        'impuestos' => 'IMPUESTO',
        'impuestos_historico' => 'IMPUESTO_HISTORICO',
        'notificaciones' => 'NOTIFICACION',
        'enviar_notificaciones' => 'NOTIFICACION',
        'configuracion_general' => 'CONFIGURACION_GENERAL',
        'configuracion_ticket' => 'CONFIGURACION_GENERAL',
        'configuracion_contratos' => 'CONFIGURACION_GENERAL',
        'usuario' => 'USUARIO',
        'empleado' => 'EMPLEADO',
        'gastos' => 'GASTO',
        'cierre_caja' => 'CIERRE_CAJA',
        'arqueo_caja' => 'ARQUEO_CAJA',
        'ventas' => 'VENTA',
        'facturas' => 'VENTA',
        'ventas_online' => 'VENTA_ONLINE',
        'apartados_alta' => 'APARTADO_GESTION',
        'apartados_consulta' => 'APARTADO_GESTION',
        'apartados_operaciones' => 'APARTADO_GESTION',
        'apartados_cambio' => 'APARTADO_CAMBIO',
        'punto_venta' => 'PUNTO_VENTA',
        'devoluciones' => 'DEVOLUCION',
        'devoluciones_mostrador' => 'DEVOLUCION',
        'devoluciones_credito' => 'DEVOLUCION',
        'usuario_rol' => 'USUARIO_ROL',
        'rol_permiso' => 'ROL_PERMISO',
        'usuario_notificacion' => 'USUARIO_NOTIFICACION',
        'direccion' => 'DIRECCION',
        'contratos_empleados' => 'CONTRATOS_EMPLEADOS',
    ];
}

function auth_nav_items(): array
{
    $items = [];
    foreach (auth_nav_groups() as $group) {
        if (!isset($group['items']) || !is_array($group['items'])) {
            continue;
        }
        foreach ($group['items'] as $item) {
            $items[] = $item;
        }
    }
    return $items;
}

function auth_nav_groups(): array
{
    return [
        [
            'label' => 'Inicio',
            'icon' => 'bi-house',
            'items' => [
                ['script' => 'index.php', 'label' => 'Panel', 'icon' => 'bi-speedometer2'],
            ],
        ],
        [
            'label' => 'Catalogos',
            'icon' => 'bi-grid-3x3-gap',
            'items' => [
                ['script' => 'familia.php?accion=leer', 'label' => 'Familias', 'icon' => 'bi-collection'],
                ['script' => 'sub_familia.php?accion=leer', 'label' => 'Subfamilias', 'icon' => 'bi-diagram-3'],
                ['script' => 'metales.php?accion=leer', 'label' => 'Metales', 'icon' => 'bi-gem'],
                ['script' => 'forma_pago.php?accion=leer', 'label' => 'Formas de Pago', 'icon' => 'bi-credit-card'],
                ['script' => 'talleres.php?accion=leer', 'label' => 'Talleres', 'icon' => 'bi-tools'],
                ['script' => 'impuestos.php?accion=leer', 'label' => 'Impuestos', 'icon' => 'bi-percent'],
                ['script' => 'gastos_categoria.php?accion=leer', 'label' => 'Gastos Categoria', 'icon' => 'bi-wallet2'],
                ['script' => 'variantes.php?accion=leer', 'label' => 'Variantes', 'icon' => 'bi-sliders2'],
            ],
        ],
        [
            'label' => 'Inventario y compras',
            'icon' => 'bi-box-seam',
            'items' => [
                ['script' => 'pieza.php?accion=leer', 'label' => 'Piezas', 'icon' => 'bi-gem'],
                ['script' => 'piezas_vendidas.php?accion=leer', 'label' => 'Sugerencia de resurtido', 'icon' => 'bi-cart-plus'],
                ['script' => 'inventario_recuento.php?accion=leer', 'label' => 'Recuento inventario', 'icon' => 'bi-clipboard-check'],
                ['script' => 'capital_inventario.php?accion=leer', 'label' => 'Capital en tienda', 'icon' => 'bi-cash-stack'],
                ['script' => 'insumos.php?accion=leer', 'label' => 'Insumos', 'icon' => 'bi-droplet-half'],
                ['script' => 'catalogo_compra.php?accion=leer', 'label' => 'Catalogo Compra', 'icon' => 'bi-list-check'],
                ['script' => 'proveedores.php?accion=leer', 'label' => 'Proveedores', 'icon' => 'bi-truck'],
            ],
        ],
        [
            'label' => 'Taller',
            'icon' => 'bi-wrench-adjustable',
            'items' => [
                ['script' => 'ordenes_taller.php?accion=leer', 'label' => 'Ordenes de Taller', 'icon' => 'bi-clipboard-check'],
            ],
        ],
        [
            'label' => 'Comercial',
            'icon' => 'bi-shop',
            'items' => [
                ['script' => 'cliente.php?accion=leer', 'label' => 'Clientes', 'icon' => 'bi-people'],
                ['script' => 'ventas.php?accion=leer', 'label' => 'Ventas', 'icon' => 'bi-cart-check'],
                ['script' => 'facturas.php?accion=leer', 'label' => 'Facturas CFDI', 'icon' => 'bi-receipt-cutoff'],
                ['script' => 'ventas_online.php?accion=leer', 'label' => 'Ventas en linea', 'icon' => 'bi-globe2'],
                ['script' => 'devoluciones.php?accion=leer', 'label' => 'Devoluciones', 'icon' => 'bi-arrow-return-left'],
                ['script' => 'apartados_alta.php?accion=leer', 'label' => 'Apartados alta', 'icon' => 'bi-plus-circle'],
                ['script' => 'apartados_operaciones.php?accion=leer', 'label' => 'Apartados activos (abonos y cambio)', 'icon' => 'bi-grid-3x2-gap'],
                ['script' => 'punto_venta.php?accion=leer', 'label' => 'Punto de Venta', 'icon' => 'bi-upc-scan'],
                ['script' => 'promociones.php?accion=leer', 'label' => 'Promociones descuento', 'icon' => 'bi-tag'],
                ['script' => 'promociones_banner.php?accion=leer', 'label' => 'Banners catalogo web', 'icon' => 'bi-megaphone'],
                ['script' => 'tiendas.php?accion=leer', 'label' => 'Tiendas', 'icon' => 'bi-shop-window'],
                ['script' => 'gastos.php?accion=leer', 'label' => 'Gastos', 'icon' => 'bi-cash-coin'],
                ['script' => 'cierre_caja.php?accion=leer', 'label' => 'Cierre de caja', 'icon' => 'bi-safe'],
                ['script' => 'arqueo_caja.php', 'label' => 'Arqueo / descuadre', 'icon' => 'bi-calculator'],
            ],
        ],
        [
            'label' => 'Recursos humanos',
            'icon' => 'bi-person-workspace',
            'items' => [
                ['script' => 'empleado.php?accion=leer', 'label' => 'Empleados', 'icon' => 'bi-person-badge'],
                ['script' => 'puesto.php?accion=leer', 'label' => 'Puestos', 'icon' => 'bi-diagram-3'],
                ['script' => 'contratos_empleados.php?accion=listar', 'label' => 'Contratos', 'icon' => 'bi-file-earmark-text'],
            ],
        ],
        [
            'label' => 'Seguridad y accesos',
            'icon' => 'bi-shield-lock',
            'items' => [
                ['script' => 'usuario.php?accion=leer', 'label' => 'Usuarios', 'icon' => 'bi-people'],
                ['script' => 'rol.php?accion=leer', 'label' => 'Roles', 'icon' => 'bi-diagram-3'],
                ['script' => 'permiso.php?accion=leer', 'label' => 'Permisos', 'icon' => 'bi-diagram-3'],
                ['script' => 'usuario_rol.php?accion=leer', 'label' => 'Usuario Rol', 'icon' => 'bi-person-check'],
                ['script' => 'rol_permiso.php?accion=leer', 'label' => 'Rol Permiso', 'icon' => 'bi-shield-check'],
            ],
        ],
        [
            'label' => 'Sistema',
            'icon' => 'bi-gear',
            'items' => [
                ['script' => 'direccion.php?accion=leer&entidad=paises', 'label' => 'Direcciones', 'icon' => 'bi-geo-alt'],
                ['script' => 'notificaciones.php?accion=leer', 'label' => 'Notificaciones', 'icon' => 'bi-bell'],
                ['script' => 'enviar_notificaciones.php', 'label' => 'Enviar notificaciones', 'icon' => 'bi-send'],
                ['script' => 'configuracion_general.php', 'label' => 'Configuracion del sistema', 'icon' => 'bi-sliders'],
            ],
        ],
    ];
}

function auth_normalize_action(?string $action): string
{
    $value = mb_strtolower(trim((string) ($action ?? 'leer')));

    if ($value === 'stock' || $value === 'contactos') {
        return 'LEER';
    }
    if (str_starts_with($value, 'stock_')) {
        return auth_normalize_action(substr($value, 6));
    }
    if (str_starts_with($value, 'contacto_')) {
        return auth_normalize_action(substr($value, 9));
    }

    switch ($value) {
        case 'crear':
        case 'asignar':
            return 'CREAR';
        case 'actualizar':
        case 'guardar':
        case 'estado':
        case 'abono':
        case 'finalizar':
        case 'cancelar':
            return 'ACTUALIZAR';
        case 'imprimir':
            return 'LEER';
        case 'gestionar_foto':
        case 'subir_foto':
        case 'establecer_principal_imagen':
        case 'eliminar_imagen':
            return 'FOTO';
        case 'borrar':
        case 'revocar':
        case 'desvincular':
            return 'BORRAR';
        case 'ver':
        case 'leer':
        default:
            return 'LEER';
    }
}

function auth_script_to_module(string $script): string
{
    $base = pathinfo($script, PATHINFO_FILENAME);
    return $base !== '' ? $base : 'index';
}

function auth_resolve_module(string $script, ?string $accion = null): string
{
    $base = auth_script_to_module($script);
    $accionNorm = mb_strtolower(trim((string) ($accion ?? '')));

    if ($base === 'pieza' && ($accionNorm === 'stock' || str_starts_with($accionNorm, 'stock_'))) {
        return 'piezas_stock';
    }
    if ($base === 'proveedores' && ($accionNorm === 'contactos' || str_starts_with($accionNorm, 'contacto_'))) {
        return 'proveedor_contactos';
    }

    return $base;
}

function auth_permission_name(string $module, string $action): ?string
{
    $map = auth_module_map();
    $key = $module;

    if (!isset($map[$key])) {
        return null;
    }

    return $map[$key] . '_' . $action;
}

function auth_user(): ?array
{
    if (!isset($_SESSION[JOYERIA_AUTH_SESSION_KEY]) || !is_array($_SESSION[JOYERIA_AUTH_SESSION_KEY])) {
        return null;
    }

    return $_SESSION[JOYERIA_AUTH_SESSION_KEY];
}   

function auth_is_logged_in(): bool
{
    return auth_user() !== null;
}

function auth_set_flash(string $message, string $type = 'error'): void
{
    $_SESSION[JOYERIA_AUTH_FLASH_KEY] = [
        'message' => $message,
        'type' => $type,
    ];
}

function auth_pull_flash(): ?array
{
    if (!isset($_SESSION[JOYERIA_AUTH_FLASH_KEY]) || !is_array($_SESSION[JOYERIA_AUTH_FLASH_KEY])) {
        return null;
    }

    $flash = $_SESSION[JOYERIA_AUTH_FLASH_KEY];
    unset($_SESSION[JOYERIA_AUTH_FLASH_KEY]);

    return $flash;
}

function auth_has_role(string $roleName): bool
{
    $usuario = auth_user();
    if ($usuario === null || !isset($usuario['roles']) || !is_array($usuario['roles'])) {
        return false;
    }

    return in_array(mb_strtoupper($roleName), $usuario['roles'], true);
}

function auth_is_admin(): bool
{
    return auth_has_role('ADMINISTRADOR');
}

function auth_has_permission(string $permiso): bool
{
    if (auth_is_admin()) {
        return true;
    }

    $usuario = auth_user();
    if ($usuario === null || !isset($usuario['permissions']) || !is_array($usuario['permissions'])) {
        return false;
    }

    return in_array(mb_strtoupper($permiso), $usuario['permissions'], true);
}

/**
 * Panel de control / KPIs (admin/index.php): solo administradores o PANEL_LEER.
 */
function auth_can_read_panel(): bool
{
    return auth_is_admin() || auth_has_permission('PANEL_LEER');
}

/**
 * Usuario con al menos un permiso activo en sesion (empleados sin rol ADMIN).
 */
function auth_user_has_any_panel_permission(): bool
{
    if (auth_is_admin()) {
        return true;
    }

    $usuario = auth_user();
    if ($usuario === null || !isset($usuario['permissions']) || !is_array($usuario['permissions'])) {
        return false;
    }

    return count($usuario['permissions']) > 0;
}

function auth_can_module_action(string $module, string $action): bool
{
    if ($module === 'apartados_operaciones' && $action === 'LEER') {
        if (auth_is_admin()) {
            return true;
        }

        return auth_has_permission('APARTADO_GESTION_LEER');
    }

    if ($module === 'index' && $action === 'LEER') {
        return auth_can_read_panel();
    }

    if ($action === 'FOTO') {
        $permFoto = auth_permission_name($module, 'FOTO');
        if ($permFoto !== null && auth_has_permission($permFoto)) {
            return true;
        }
        $permUpd = auth_permission_name($module, 'ACTUALIZAR');
        if ($permUpd !== null && auth_has_permission($permUpd)) {
            return true;
        }
        return false;
    }

    $permiso = auth_permission_name($module, $action);
    if ($permiso === null) {
        return true;
    }

    return auth_has_permission($permiso);
}

function auth_can_read_script(string $script): bool
{
    $module = auth_script_to_module($script);
    return auth_can_module_action($module, 'LEER');
}

/**
 * Primera ruta del menu lateral que el usuario puede leer (relativa a admin/).
 */
function auth_first_readable_admin_script(): ?string
{
    foreach (auth_visible_nav_items() as $item) {
        $script = trim((string) ($item['script'] ?? ''));
        if ($script === '') {
            continue;
        }
        $base = basename(explode('?', $script)[0]);
        if ($base === 'index.php' && !auth_can_read_panel()) {
            continue;
        }
        return $script;
    }

    return null;
}

/**
 * Ruta relativa a la raiz del sitio tras login admin exitoso.
 */
function auth_default_admin_redirect(): string
{
    if (auth_can_read_panel()) {
        return 'admin/index.php';
    }

    $first = auth_first_readable_admin_script();
    if ($first !== null) {
        return 'admin/' . ltrim($first, '/');
    }

    return 'admin/index.php';
}

/**
 * Enlace relativo a admin/ para pantalla de acceso denegado.
 */
function auth_access_denied_fallback_href(): ?string
{
    return auth_first_readable_admin_script();
}

/**
 * @return array{ok: bool, code: string, message: string, hints: string[]}
 */
function auth_verify_password_for_login(string $contrasenaPlano, string $hashGuardado): array
{
    $hash = trim($hashGuardado);
    if ($hash === '') {
        return [
            'ok' => false,
            'code' => 'PASSWORD_NOT_SET',
            'message' => 'El usuario no tiene contrasena configurada en la base de datos.',
            'hints' => ['Restablece la contrasena desde Empleados o con recuperacion de contrasena.'],
        ];
    }

    if (password_verify($contrasenaPlano, $hash)) {
        return ['ok' => true, 'code' => 'OK', 'message' => '', 'hints' => []];
    }

    if (strlen($hash) === 32 && ctype_xdigit($hash)) {
        return [
            'ok' => false,
            'code' => 'PASSWORD_LEGACY_HASH',
            'message' => 'La contrasena en base de datos es antigua (no bcrypt). Debe restablecerse.',
            'hints' => [
                'En Empleados edita al usuario y guarda una contrasena nueva.',
                'O usa "Olvidaste tu contrasena" si esta habilitado.',
            ],
        ];
    }

    if ($hash[0] !== '$') {
        return [
            'ok' => false,
            'code' => 'PASSWORD_INVALID_FORMAT',
            'message' => 'El formato de contrasena almacenada no es valido para este sistema.',
            'hints' => ['Restablece la contrasena del usuario desde el panel.'],
        ];
    }

    return [
        'ok' => false,
        'code' => 'PASSWORD_WRONG',
        'message' => 'La contrasena no coincide con la registrada para ese correo.',
        'hints' => ['Verifica mayusculas y espacios. Si la cambiaron, usa la contrasena nueva.'],
    ];
}

function auth_login(string $correo, string $contrasena, ?string &$error = null, ?string &$code = null): bool
{
    $correo = mb_strtolower(trim($correo));
    if ($correo === '' || $contrasena === '') {
        $code = 'MISSING_FIELDS';
        $error = 'Correo y contrasena son obligatorios.';
        return false;
    }

    try {
        $service = auth_service();
        $usuario = $service->findUserByEmail($correo);

        if ($usuario === null) {
            $code = 'USER_NOT_FOUND';
            $error = 'No existe ningun usuario con ese correo en el sistema.';
            return false;
        }

        if ((int) ($usuario['activo'] ?? 0) !== 1) {
            $code = 'USER_INACTIVE';
            $error = 'El usuario existe pero la cuenta esta desactivada (activo = 0).';
            return false;
        }

        $passCheck = auth_verify_password_for_login($contrasena, (string) $usuario['contrasena']);
        if (!$passCheck['ok']) {
            $code = $passCheck['code'];
            $error = $passCheck['message'];
            return false;
        }

        $accesos = $service->getRolesAndPermissions((int) $usuario['id_usuario']);
        $diag = $service->diagnosePanelAccess((int) $usuario['id_usuario']);
        if (!$diag['allowed']) {
            $code = $diag['code'];
            $error = $diag['message'];
            return false;
        }

        $_SESSION[JOYERIA_AUTH_SESSION_KEY] = [
            'id_usuario' => (int) $usuario['id_usuario'],
            'nombre' => trim((string) $usuario['nombre']),
            'nombre_completo' => trim((string) $usuario['nombre'] . ' ' . $usuario['primer_apellido'] . ' ' . ($usuario['segundo_apellido'] ?? '')),
            'correo' => (string) $usuario['correo'],
            'roles' => $accesos['roles'],
            'permissions' => $accesos['permissions'],
        ];

        session_regenerate_id(true);

        $service->logBitacora((int) $usuario['id_usuario'], 'LOGIN', 'Inicio de sesion exitoso en panel admin');
        $code = 'OK';
        return true;
    } catch (Throwable $e) {
        error_log('auth_login: ' . $e->getMessage());
        $code = 'SERVER_ERROR';
        $error = 'Error interno al iniciar sesion. Intenta de nuevo en unos minutos.';
        return false;
    }
}

/**
 * Arma respuesta JSON cuando falla el login (panel admin).
 * Siempre devuelve un mensaje generico al cliente; el diagnostico detallado
 * se registra en el log del servidor para evitar enumeracion de usuarios.
 *
 * @return array{ok: false, login_code: string, error: string}
 */
function auth_build_login_failure(string $correo, ?string $errAdmin, ?string $errCliente): array
{
    $correoNorm = mb_strtolower(trim($correo));
    $service = auth_service();
    $usuario = $service->findUserByEmail($correoNorm);

    // Diagnostico detallado: solo al log del servidor
    $diagCode = 'UNKNOWN';
    $diagDetail = '';

    if ($usuario === null) {
        $diagCode = 'USER_NOT_FOUND';
        $diagDetail = 'No existe usuario con correo: ' . $correoNorm;
    } elseif ($errAdmin !== null && $errAdmin !== '') {
        $diag = $service->diagnosePanelAccess((int) $usuario['id_usuario']);
        if (!$diag['allowed']) {
            $diagCode = $diag['code'];
            $diagDetail = 'Panel: ' . implode('; ', (array) ($diag['hints'] ?? []));
        } else {
            $hash = trim((string) ($usuario['contrasena'] ?? ''));
            if ($hash === '') {
                $diagCode = 'PASSWORD_NOT_SET';
            } elseif (strlen($hash) === 32 && ctype_xdigit($hash)) {
                $diagCode = 'PASSWORD_LEGACY_HASH';
            } elseif ($hash !== '' && $hash[0] !== '$') {
                $diagCode = 'PASSWORD_INVALID_FORMAT';
            } else {
                $diagCode = 'PASSWORD_WRONG';
            }
            $diagDetail = $errAdmin;
        }
    } elseif ($errCliente !== null && $errCliente !== '') {
        $diagCode = 'CLIENT_AREA_ONLY';
        $diagDetail = $errCliente;
    }

    error_log('auth_login_failure correo=' . $correoNorm . ' code=' . $diagCode . ($diagDetail !== '' ? ' detail=' . $diagDetail : ''));

    // Respuesta generica identica para cualquier motivo de fallo
    return [
        'ok' => false,
        'login_code' => 'LOGIN_FAILED',
        'error' => 'Correo o contrasena incorrectos.',
    ];
}

function auth_logout(): void
{
    $usuario = auth_user();
    if ($usuario !== null) {
        auth_service()->logBitacora((int) $usuario['id_usuario'], 'LOGOUT', 'Cierre de sesion en panel admin');
    }

    unset($_SESSION[JOYERIA_AUTH_SESSION_KEY]);
    session_regenerate_id(true);
}

function auth_has_admin_role_in_array(array $roles): bool
{
    foreach ($roles as $role) {
        $r = mb_strtoupper(trim((string) $role));
        if ($r === 'ADMINISTRADOR' || $r === 'ADMIN') {
            return true;
        }
    }

    return false;
}

function auth_current_access_guard(): array
{
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    $accionRaw = isset($_GET['accion']) ? (string) $_GET['accion'] : 'leer';
    $module = auth_resolve_module($script, $accionRaw);
    $accion = auth_normalize_action($accionRaw);

    if (!auth_is_logged_in()) {
        return [
            'allowed' => false,
            'redirect' => 'login.php',
            'message' => 'Debes iniciar sesion para continuar.',
        ];
    }

    if (!auth_can_module_action($module, $accion)) {
        $redirect = null;
        if ($module !== 'index') {
            $redirect = auth_first_readable_admin_script();
        }

        return [
            'allowed' => false,
            'redirect' => $redirect,
            'message' => 'No tienes permiso para realizar esta accion.',
            'module' => $module,
            'action' => $accion,
        ];
    }

    return [
        'allowed' => true,
        'module' => $module,
        'action' => $accion,
    ];
}

function auth_current_capabilities(): array
{
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    $accionRaw = isset($_GET['accion']) ? (string) $_GET['accion'] : 'leer';
    $module = auth_resolve_module($script, $accionRaw);

    return [
        'module' => $module,
        'canRead' => auth_can_module_action($module, 'LEER'),
        'canCreate' => auth_can_module_action($module, 'CREAR'),
        'canUpdate' => auth_can_module_action($module, 'ACTUALIZAR'),
        'canDelete' => auth_can_module_action($module, 'BORRAR'),
        'canPhoto' => auth_can_module_action($module, 'FOTO'),
    ];
}

function auth_visible_nav_items(): array
{
    $items = auth_nav_items();
    $visible = [];

    foreach ($items as $item) {
        $script = (string) $item['script'];
        $base = basename(explode('?', $script)[0]);
        if (auth_can_read_script($base)) {
            $visible[] = $item;
        }
    }

    return $visible;
}

function auth_visible_nav_groups(): array
{
    $groups = auth_nav_groups();
    $visibleGroups = [];

    foreach ($groups as $group) {
        $groupItems = [];
        if (isset($group['items']) && is_array($group['items'])) {
            foreach ($group['items'] as $item) {
                $script = (string) ($item['script'] ?? '');
                if ($script === '') {
                    continue;
                }
                $base = basename(explode('?', $script)[0]);
                if (auth_can_read_script($base)) {
                    $groupItems[] = $item;
                }
            }
        }

        if (!empty($groupItems)) {
            $visibleGroups[] = [
                'label' => (string) ($group['label'] ?? ''),
                'icon' => (string) ($group['icon'] ?? 'bi-folder2-open'),
                'items' => $groupItems,
            ];
        }
    }

    return $visibleGroups;
}

function auth_readable_routes(): array
{
    $routes = [];
    foreach (auth_module_map() as $module => $prefix) {
        if (auth_can_module_action($module, 'LEER')) {
            $routes[] = $module . '.php';
        }
    }
    return $routes;
}

/**
 * Resuelve el id_usuario para auditoría en MySQL (triggers que leen @current_user_id).
 * Orden: override explícito → sesión panel admin → sesión tienda (cliente).
 */
function auth_mysql_resolve_audit_user_id(?int $override = null): ?int
{
    if ($override !== null) {
        return $override > 0 ? $override : null;
    }

    $usuario = auth_user();
    if ($usuario !== null && isset($usuario['id_usuario'])) {
        $id = (int) $usuario['id_usuario'];
        if ($id > 0) {
            return $id;
        }
    }

    $tiendaPath = __DIR__ . '/tienda_auth.php';
    if (!function_exists('tienda_auth_user') && is_file($tiendaPath)) {
        require_once $tiendaPath;
    }
    if (function_exists('tienda_auth_user')) {
        $tienda = tienda_auth_user();
        if ($tienda !== null && isset($tienda['id_usuario'])) {
            $id = (int) $tienda['id_usuario'];
            if ($id > 0) {
                return $id;
            }
        }
    }

    return null;
}

/**
 * Variables de sesión MySQL esperadas por triggers de auditoría (equivalente a
 * SET @current_user_id / SET @current_ip antes del INSERT/UPDATE/DELETE).
 */
function auth_mysql_set_audit_vars(PDO $pdo, ?int $idUsuarioOverride = null): void
{
    $idUsuario = auth_mysql_resolve_audit_user_id($idUsuarioOverride);
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    $stmt = $pdo->prepare('SET @current_user_id = ?');
    $stmt->bindValue(1, $idUsuario, $idUsuario === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->execute();

    $stmtIp = $pdo->prepare('SET @current_ip = ?');
    $stmtIp->bindValue(1, $ip, PDO::PARAM_STR);
    $stmtIp->execute();
}
