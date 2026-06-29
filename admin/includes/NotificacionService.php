<?php
declare(strict_types=1);

require_once __DIR__ . '/../../sistema.class.php';
require_once __DIR__ . '/MailService.php';
require_once __DIR__ . '/../../includes/joyeria_branding.php';

class NotificacionService
{
    public const TIPO_VENTA_NUEVA = 'venta_online_nueva';
    public const TIPO_VENTA_LISTA = 'venta_online_lista_recoger';
    public const TIPO_VENTA_ENTREGADA = 'venta_online_entregada';
    public const TIPO_STOCK_PERDIDO = 'venta_online_stock_perdido';

    private function getDb(): PDO
    {
        static $sistema = null;
        if ($sistema === null) {
            $sistema = new Sistema();
        }
        return $sistema->getDb();
    }

    public function notificarVentaOnline(int $idVenta): void
    {
        if ($idVenta <= 0) return;
        $db = $this->getDb();
        $venta = $this->obtenerVentaConDetalle($db, $idVenta);
        if (!$venta) return;

        $idTienda = (int) ($venta['id_tienda_FK'] ?? 0);
        $mensaje = 'Nueva venta en linea #' . $idVenta
            . ' por $' . number_format((float) $venta['total'], 2, '.', ',')
            . ' para sucursal ' . (string) ($venta['nom_tienda'] ?? '—')
            . '. Aparta las piezas del anaquel.';

        $this->insertarNotificacion($db, $mensaje, self::TIPO_VENTA_NUEVA, $idVenta, $idTienda ?: null, null);

        $tiendaInfo = $this->obtenerTiendaInfo($db, $idTienda);

        try { $this->enviarCorreoAEmpleadosDeTienda($db, $venta, $tiendaInfo); } catch (Throwable $e) { error_log('notif employees: ' . $e->getMessage()); }
        try { $this->enviarCorreoConfirmacionCliente($venta, $tiendaInfo); } catch (Throwable $e) { error_log('notif customer: ' . $e->getMessage()); }
    }

    /**
     * Notifica al admin que un pago de MP fue aprobado pero el stock no estaba
     * disponible (caso de reserva expirada + venta presencial en paralelo).
     * Requiere reembolso manual desde MP.
     */
    public function notificarStockPerdidoTrasPago(int $idVenta, int $faltantes = 0): void
    {
        if ($idVenta <= 0) return;
        $db = $this->getDb();
        $venta = $this->obtenerVentaConDetalle($db, $idVenta);
        if (!$venta) return;

        $idTienda = (int) ($venta['id_tienda_FK'] ?? 0);
        $totalFmt = '$' . number_format((float) ($venta['total'] ?? 0), 2, '.', ',');
        $mensaje = 'ATENCION: Pedido #' . $idVenta . ' cobrado en MP (' . $totalFmt
            . ') pero ' . ($faltantes > 0 ? ($faltantes . ' pieza(s)') : 'al menos una pieza')
            . ' ya no estaban disponibles. Reembolsa al cliente desde Mercado Pago.';

        $this->insertarNotificacion($db, $mensaje, self::TIPO_STOCK_PERDIDO, $idVenta, $idTienda ?: null, null);
    }

    public function notificarListaParaRecoger(int $idVenta): void
    {
        if ($idVenta <= 0) return;
        $db = $this->getDb();
        $venta = $this->obtenerVentaConDetalle($db, $idVenta);
        if (!$venta) return;

        $idTienda = (int) ($venta['id_tienda_FK'] ?? 0);
        $mensaje = 'Venta #' . $idVenta . ' marcada como lista para recoger en '
            . (string) ($venta['nom_tienda'] ?? '—');
        $this->insertarNotificacion($db, $mensaje, self::TIPO_VENTA_LISTA, $idVenta, $idTienda ?: null, null);

        $tiendaInfo = $this->obtenerTiendaInfo($db, $idTienda);
        try { $this->enviarCorreoListaParaRecoger($venta, $tiendaInfo); } catch (Throwable $e) { error_log('notif lista: ' . $e->getMessage()); }
    }

    /**
     * Devuelve mapa columna->true de columnas presentes en `notificaciones`.
     * Permite que la campana funcione aunque la migracion 2026_05_20
     * no se haya aplicado en el VPS.
     */
    private function columnasNotificaciones(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        try {
            foreach ($this->getDb()->query('SHOW COLUMNS FROM notificaciones')->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $f = isset($r['Field']) ? trim((string) $r['Field']) : '';
                if ($f !== '') $cache[$f] = true;
            }
        } catch (Throwable $e) {
            error_log('columnasNotificaciones: ' . $e->getMessage());
        }
        return $cache;
    }

    /**
     * Lista notificaciones para el usuario logueado del panel (admin/empleado).
     * Solo filtra por columnas que existen en la tabla; si la migracion no se
     * aplico, devuelve igualmente todas las filas mas recientes.
     */
    public function listarParaUsuario(int $idUsuario, int $idTiendaUsuario, int $limite = 15): array
    {
        $db = $this->getDb();
        $cols = $this->columnasNotificaciones();

        $select = ['id_notificacion', 'mensaje', 'fecha_envio'];
        if (isset($cols['tipo']))          $select[] = 'tipo';
        if (isset($cols['id_referencia'])) $select[] = 'id_referencia';
        if (isset($cols['id_tienda_FK']))  $select[] = 'id_tienda_FK';
        $select[] = isset($cols['leida']) ? 'COALESCE(leida, 0) AS leida' : '0 AS leida';

        $where = ['1 = 1'];
        $params = [];
        if (isset($cols['id_destino_usuario_FK'])) {
            $where[] = '(id_destino_usuario_FK IS NULL OR id_destino_usuario_FK = :uid)';
            $params[':uid'] = $idUsuario;
        }
        if (isset($cols['id_tienda_FK']) && $idTiendaUsuario > 0) {
            $where[] = '(id_tienda_FK IS NULL OR id_tienda_FK = :tnd)';
            $params[':tnd'] = $idTiendaUsuario;
        }

        $sql = 'SELECT ' . implode(', ', $select)
            . ' FROM notificaciones WHERE ' . implode(' AND ', $where)
            . ' ORDER BY fecha_envio DESC, id_notificacion DESC LIMIT :lim';

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_INT);
        }
        $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarNoLeidas(int $idUsuario, int $idTiendaUsuario): int
    {
        $db = $this->getDb();
        $cols = $this->columnasNotificaciones();
        if (!isset($cols['leida'])) {
            return 0;
        }
        $where = ['COALESCE(leida, 0) = 0'];
        $params = [];
        if (isset($cols['id_destino_usuario_FK'])) {
            $where[] = '(id_destino_usuario_FK IS NULL OR id_destino_usuario_FK = :uid)';
            $params[':uid'] = $idUsuario;
        }
        if (isset($cols['id_tienda_FK']) && $idTiendaUsuario > 0) {
            $where[] = '(id_tienda_FK IS NULL OR id_tienda_FK = :tnd)';
            $params[':tnd'] = $idTiendaUsuario;
        }
        $sql = 'SELECT COUNT(*) FROM notificaciones WHERE ' . implode(' AND ', $where);
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function marcarTodasLeidas(int $idUsuario, int $idTiendaUsuario): void
    {
        $db = $this->getDb();
        $cols = $this->columnasNotificaciones();
        if (!isset($cols['leida'])) {
            return;
        }
        $where = ['COALESCE(leida, 0) = 0'];
        $params = [];
        if (isset($cols['id_destino_usuario_FK'])) {
            $where[] = '(id_destino_usuario_FK IS NULL OR id_destino_usuario_FK = :uid)';
            $params[':uid'] = $idUsuario;
        }
        if (isset($cols['id_tienda_FK']) && $idTiendaUsuario > 0) {
            $where[] = '(id_tienda_FK IS NULL OR id_tienda_FK = :tnd)';
            $params[':tnd'] = $idTiendaUsuario;
        }
        $sql = 'UPDATE notificaciones SET leida = 1 WHERE ' . implode(' AND ', $where);
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_INT);
        }
        $stmt->execute();
    }

    /**
     * Crea una notificacion interna (campana) dirigida a un usuario del panel.
     * Reutiliza la deteccion de columnas para compatibilidad de esquema.
     *
     * @return bool true si se inserto correctamente.
     */
    public function crearNotificacionInterna(int $idUsuario, string $mensaje, string $tipo = 'aviso'): bool
    {
        $mensaje = trim($mensaje);
        if ($idUsuario <= 0 || $mensaje === '') {
            return false;
        }
        try {
            $this->insertarNotificacion($this->getDb(), $mensaje, $tipo, 0, null, $idUsuario);
            return true;
        } catch (Throwable $e) {
            error_log('crearNotificacionInterna: ' . $e->getMessage());
            return false;
        }
    }

    public function marcarUnaLeida(int $idNotificacion): void
    {
        $stmt = $this->getDb()->prepare("UPDATE notificaciones SET leida = 1 WHERE id_notificacion = :id");
        $stmt->bindValue(':id', $idNotificacion, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function insertarNotificacion(PDO $db, string $mensaje, string $tipo, int $idReferencia, ?int $idTienda, ?int $idDestinoUsuario): void
    {
        try {
            // Detectar columnas para compatibilidad con esquemas previos.
            $cols = [];
            foreach ($db->query("SHOW COLUMNS FROM notificaciones")->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $f = isset($r['Field']) ? trim((string) $r['Field']) : '';
                if ($f !== '') $cols[$f] = true;
            }

            $insertCols = ['mensaje'];
            $insertVals = [':mensaje'];
            $bind = [':mensaje' => [$mensaje, PDO::PARAM_STR]];

            if (isset($cols['tipo']))                  { $insertCols[] = 'tipo';                  $insertVals[] = ':tipo';   $bind[':tipo']   = [$tipo, PDO::PARAM_STR]; }
            if (isset($cols['id_referencia']))         { $insertCols[] = 'id_referencia';         $insertVals[] = ':ref';    $bind[':ref']    = [$idReferencia, PDO::PARAM_INT]; }
            if (isset($cols['id_tienda_FK']))          { $insertCols[] = 'id_tienda_FK';          $insertVals[] = ':tnd';
                if ($idTienda === null) { $bind[':tnd'] = [null, PDO::PARAM_NULL]; } else { $bind[':tnd'] = [$idTienda, PDO::PARAM_INT]; }
            }
            if (isset($cols['id_destino_usuario_FK'])) { $insertCols[] = 'id_destino_usuario_FK'; $insertVals[] = ':uid';
                if ($idDestinoUsuario === null) { $bind[':uid'] = [null, PDO::PARAM_NULL]; } else { $bind[':uid'] = [$idDestinoUsuario, PDO::PARAM_INT]; }
            }
            if (isset($cols['leida']))                 { $insertCols[] = 'leida';                 $insertVals[] = '0'; }
            if (isset($cols['fecha_envio']))           { $insertCols[] = 'fecha_envio';           $insertVals[] = 'NOW()'; }

            $sql = 'INSERT INTO notificaciones (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $insertVals) . ')';
            $stmt = $db->prepare($sql);
            foreach ($bind as $k => [$v, $t]) {
                $stmt->bindValue($k, $v, $t);
            }
            $stmt->execute();
        } catch (Throwable $e) {
            error_log('insertarNotificacion: ' . $e->getMessage());
        }
    }

    private function obtenerVentaConDetalle(PDO $db, int $idVenta): ?array
    {
        $sql = "SELECT v.id_venta, v.id_cliente_FK, v.total, v.fecha_venta, v.id_tienda_FK,
                       v.estado_pago, v.estado_entrega, v.id_pago_externo,
                       COALESCE(CONCAT(uc.nombre, ' ', uc.primer_apellido, COALESCE(CONCAT(' ', uc.segundo_apellido), '')), 'Cliente') AS cliente_nombre,
                       uc.correo AS cliente_correo,
                       uc.telefono AS cliente_telefono,
                       t.nom_tienda
                FROM ventas v
                LEFT JOIN clientes c ON c.id_cliente = v.id_cliente_FK
                LEFT JOIN usuarios uc ON uc.id_usuario = c.id_usuario_FK
                LEFT JOIN tiendas t ON t.id_tienda = v.id_tienda_FK
                WHERE v.id_venta = :id";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id', $idVenta, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $stmtDet = $db->prepare(
            "SELECT vd.precio_unitario, ps.codigo_auxiliar, p.desc_pieza, m.nom_metal, t.nom_tienda
             FROM venta_detalle vd
             INNER JOIN piezas_stock ps ON ps.id_pieza_stock = vd.id_pieza_stock_FK
             INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
             INNER JOIN metales m ON m.id_metal = p.id_metal_FK
             INNER JOIN tiendas t ON t.id_tienda = p.id_tienda_FK
             WHERE vd.id_venta_FK = :id
             ORDER BY vd.id_venta_detalle ASC"
        );
        $stmtDet->bindValue(':id', $idVenta, PDO::PARAM_INT);
        $stmtDet->execute();
        $row['detalle'] = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
        return $row;
    }

    private function obtenerTiendaInfo(PDO $db, int $idTienda): array
    {
        if ($idTienda <= 0) {
            return ['nom_tienda' => '—'];
        }
        $stmt = $db->prepare("SELECT nom_tienda FROM tiendas WHERE id_tienda = :id LIMIT 1");
        $stmt->bindValue(':id', $idTienda, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: ['nom_tienda' => '—'];
    }

    private function enviarCorreoAEmpleadosDeTienda(PDO $db, array $venta, array $tiendaInfo): void
    {
        $idTienda = (int) ($venta['id_tienda_FK'] ?? 0);
        if ($idTienda <= 0) return;
        $stmt = $db->prepare(
            "SELECT u.correo FROM empleados e
             INNER JOIN usuarios u ON u.id_usuario = e.id_usuario_FK
             WHERE e.id_tienda_FK = :t AND e.activo = 1 AND u.activo = 1 AND u.correo IS NOT NULL AND u.correo <> ''"
        );
        $stmt->bindValue(':t', $idTienda, PDO::PARAM_INT);
        $stmt->execute();
        $correos = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'correo');

        if ($correos === []) return;
        $asunto = 'Nueva venta en línea #' . $venta['id_venta'] . ' - Aparta piezas';
        $html = $this->plantillaCorreoEmpleado($venta, $tiendaInfo);
        foreach ($correos as $c) {
            MailService::enviarNotificacion((string) $c, $asunto, $html);
        }
    }

    private function enviarCorreoConfirmacionCliente(array $venta, array $tiendaInfo): void
    {
        $correo = (string) ($venta['cliente_correo'] ?? '');
        if ($correo === '') return;
        $asunto = 'Confirmación de tu compra en línea #' . $venta['id_venta'] . ' - ' . joyeria_marca_nombre();
        $html = $this->plantillaCorreoClienteConfirmacion($venta, $tiendaInfo);
        MailService::enviarNotificacion($correo, $asunto, $html);
    }

    private function enviarCorreoListaParaRecoger(array $venta, array $tiendaInfo): void
    {
        $correo = (string) ($venta['cliente_correo'] ?? '');
        if ($correo === '') return;
        $asunto = 'Tu compra #' . $venta['id_venta'] . ' está lista para recoger en tienda';
        $html = $this->plantillaCorreoClienteListo($venta, $tiendaInfo);
        MailService::enviarNotificacion($correo, $asunto, $html);
    }

    private function plantillaCorreoEmpleado(array $venta, array $tiendaInfo): string
    {
        $idV = (int) $venta['id_venta'];
        $tienda = htmlspecialchars((string) ($tiendaInfo['nom_tienda'] ?? '—'));
        $cliente = htmlspecialchars((string) ($venta['cliente_nombre'] ?? 'Cliente'));
        $total = number_format((float) ($venta['total'] ?? 0), 2, '.', ',');

        $filas = '';
        foreach (($venta['detalle'] ?? []) as $d) {
            $filas .= '<tr>'
                . '<td style="padding:6px;border-bottom:1px solid #eee;"><code>' . htmlspecialchars((string) ($d['codigo_auxiliar'] ?? '')) . '</code></td>'
                . '<td style="padding:6px;border-bottom:1px solid #eee;">' . htmlspecialchars((string) ($d['desc_pieza'] ?? '')) . '</td>'
                . '<td style="padding:6px;border-bottom:1px solid #eee;">' . htmlspecialchars((string) ($d['nom_metal'] ?? '')) . '</td>'
                . '<td style="padding:6px;border-bottom:1px solid #eee;">' . htmlspecialchars((string) ($d['nom_tienda'] ?? '')) . '</td>'
                . '<td style="padding:6px;border-bottom:1px solid #eee;text-align:right;">$' . number_format((float) ($d['precio_unitario'] ?? 0), 2, '.', ',') . '</td>'
                . '</tr>';
        }

        return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;color:#333;">
<div style="max-width:700px;margin:0 auto;padding:20px;">
    <h2 style="color:#1a1a1a;">Nueva venta en línea #{$idV}</h2>
    <p>Hola equipo de <strong>{$tienda}</strong>,</p>
    <p>Se acaba de confirmar el pago de la siguiente venta en línea. Por favor, aparten las piezas del anaquel
       y marquen el pedido como <strong>"Lista para recoger"</strong> desde el panel administrativo.</p>

    <table style="width:100%;border-collapse:collapse;margin-top:14px;">
        <tr style="background:#1a1a1a;color:#f4d03f;"><th style="padding:6px;text-align:left;">Código</th>
            <th style="padding:6px;text-align:left;">Pieza</th>
            <th style="padding:6px;text-align:left;">Metal</th>
            <th style="padding:6px;text-align:left;">Sucursal</th>
            <th style="padding:6px;text-align:right;">Precio</th>
        </tr>
        {$filas}
    </table>

    <p style="margin-top:14px;"><strong>Cliente:</strong> {$cliente}<br><strong>Total:</strong> \${$total} MXN</p>
    <p style="background:#fffbe6;padding:10px;border-left:4px solid #f4d03f;">
        <strong>Entrega exclusivamente en tienda.</strong> El cliente recogerá con identificación oficial y número de orden.
    </p>
</div></body></html>
HTML;
    }

    private function plantillaCorreoClienteConfirmacion(array $venta, array $tiendaInfo): string
    {
        $idV = (int) $venta['id_venta'];
        $tienda = htmlspecialchars((string) ($tiendaInfo['nom_tienda'] ?? '—'));
        $cliente = htmlspecialchars((string) ($venta['cliente_nombre'] ?? 'Cliente'));
        $total = number_format((float) ($venta['total'] ?? 0), 2, '.', ',');

        $filas = '';
        foreach (($venta['detalle'] ?? []) as $d) {
            $filas .= '<li>' . htmlspecialchars((string) ($d['desc_pieza'] ?? ''))
                . ' (' . htmlspecialchars((string) ($d['nom_metal'] ?? '')) . ') - $'
                . number_format((float) ($d['precio_unitario'] ?? 0), 2, '.', ',') . '</li>';
        }

        return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;color:#333;">
<div style="max-width:700px;margin:0 auto;padding:20px;">
    <h2 style="color:#1a1a1a;">Gracias por tu compra, {$cliente}</h2>
    <p>Recibimos tu pago. Tu número de orden es <strong>#{$idV}</strong>.</p>
    <ul>{$filas}</ul>
    <p><strong>Total:</strong> \${$total} MXN</p>

    <div style="background:#fffbe6;padding:14px;border-left:4px solid #f4d03f;margin-top:14px;">
        <strong>Entrega exclusivamente en tienda.</strong><br>
        Tu pieza queda apartada en la sucursal <strong>{$tienda}</strong>. Te enviaremos un nuevo correo
        cuando esté lista para recoger. Para recogerla deberás presentar:
        <ol>
            <li>Identificación oficial con fotografía.</li>
            <li>Tu número de orden <strong>#{$idV}</strong>.</li>
        </ol>
        No realizamos envíos a domicilio.
    </div>
</div></body></html>
HTML;
    }

    private function plantillaCorreoClienteListo(array $venta, array $tiendaInfo): string
    {
        $idV = (int) $venta['id_venta'];
        $tienda = htmlspecialchars((string) ($tiendaInfo['nom_tienda'] ?? '—'));
        $cliente = htmlspecialchars((string) ($venta['cliente_nombre'] ?? 'Cliente'));
        $marca = htmlspecialchars(joyeria_marca_nombre(), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;color:#333;">
<div style="max-width:700px;margin:0 auto;padding:20px;">
    <h2 style="color:#1a1a1a;">Tu compra está lista, {$cliente}</h2>
    <p>Tu orden <strong>#{$idV}</strong> ya está apartada y lista para recoger en la sucursal
       <strong>{$tienda}</strong>.</p>
    <div style="background:#fffbe6;padding:14px;border-left:4px solid #f4d03f;">
        Para recogerla, presenta:
        <ol>
            <li><strong>Identificación oficial</strong> con fotografía.</li>
            <li>Tu número de orden <strong>#{$idV}</strong>.</li>
        </ol>
        Recuerda que no realizamos envíos a domicilio.
    </div>
    <p style="margin-top:14px;">Te esperamos. Gracias por tu compra en {$marca}.</p>
</div></body></html>
HTML;
    }
}
