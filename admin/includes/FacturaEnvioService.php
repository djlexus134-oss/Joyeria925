<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/factura.php';
require_once __DIR__ . '/MailService.php';
require_once __DIR__ . '/WhatsAppService.php';
require_once __DIR__ . '/cliente_correo.php';

class FacturaEnvioService
{
    /**
     * @return array{correo: array, whatsapp: array}
     */
    public function enviarAlCliente(int $idFactura): array
    {
        $facturaModel = new Factura();
        $factura = $facturaModel->leerUno($idFactura);
        if (!$factura || ($factura['estado'] ?? '') !== 'emitida') {
            return [
                'correo' => ['ok' => false, 'error' => 'Factura no emitida.'],
                'whatsapp' => ['ok' => false, 'error' => 'Factura no emitida.'],
            ];
        }

        $contacto = $this->resolverContactoCliente((int) $factura['id_venta_FK']);
        $resultado = [
            'correo' => ['ok' => false, 'omitido' => true],
            'whatsapp' => ['ok' => false, 'omitido' => true],
        ];

        $serieFolio = trim((string) (($factura['serie'] ?? '') . '-' . ($factura['folio'] ?? '')));
        $uuid = (string) ($factura['uuid'] ?? '');

        if ($contacto['correo_entregable']) {
            $resultado['correo'] = $this->enviarCorreo($factura, $contacto['correo'], $serieFolio, $uuid);
            $this->actualizarEstadoEnvio($idFactura, 'correo', $resultado['correo']);
        } else {
            $this->actualizarEstadoEnvio($idFactura, 'correo', ['ok' => false, 'omitido' => true]);
        }

        if ($contacto['telefono_valido']) {
            $resultado['whatsapp'] = $this->enviarWhatsApp($factura, $contacto['telefono'], $serieFolio, $uuid);
            $this->actualizarEstadoEnvio($idFactura, 'whatsapp', $resultado['whatsapp']);
        } else {
            $this->actualizarEstadoEnvio($idFactura, 'whatsapp', ['ok' => false, 'omitido' => true]);
        }

        return $resultado;
    }

    /** @return array{correo: string, correo_entregable: bool, telefono: string, telefono_valido: bool} */
    private function resolverContactoCliente(int $idVenta): array
    {
        $db = (new Factura())->getDb();
        $st = $db->prepare(
            'SELECT u.correo, u.telefono
             FROM ventas v
             LEFT JOIN clientes c ON c.id_cliente = v.id_cliente_FK
             LEFT JOIN usuarios u ON u.id_usuario = c.id_usuario_FK
             WHERE v.id_venta = :id LIMIT 1'
        );
        $st->bindValue(':id', $idVenta, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        $correo = trim((string) ($row['correo'] ?? ''));
        $telefono = trim((string) ($row['telefono'] ?? ''));

        return [
            'correo' => $correo,
            'correo_entregable' => $correo !== '' && joyeria_cliente_correo_es_entregable($correo),
            'telefono' => $telefono,
            'telefono_valido' => $telefono !== '' && strlen(preg_replace('/\D/', '', $telefono) ?? '') >= 10,
        ];
    }

    private function enviarCorreo(array $factura, string $correo, string $serieFolio, string $uuid): array
    {
        $pdf = $factura['pdf'] ?? null;
        $xml = (string) ($factura['xml'] ?? '');
        $adjuntos = [];

        if (is_string($pdf) && $pdf !== '') {
            $adjuntos[] = ['bytes' => $pdf, 'filename' => 'factura-' . $serieFolio . '.pdf', 'mime' => 'application/pdf'];
        }
        if ($xml !== '') {
            $adjuntos[] = ['bytes' => $xml, 'filename' => 'factura-' . $serieFolio . '.xml', 'mime' => 'application/xml'];
        }

        $html = '<p>Hola,</p><p>Adjuntamos tu factura CFDI <strong>' . htmlspecialchars($serieFolio) . '</strong>';
        if ($uuid !== '') {
            $html .= ' (UUID: ' . htmlspecialchars($uuid) . ')';
        }
        $html .= '.</p><p>Gracias por tu compra.</p>';

        return MailService::enviarConAdjuntos(
            $correo,
            'Factura CFDI ' . $serieFolio,
            $html,
            'Adjuntamos tu factura CFDI ' . $serieFolio . '.',
            $adjuntos
        );
    }

    private function enviarWhatsApp(array $factura, string $telefono, string $serieFolio, string $uuid): array
    {
        $pdf = $factura['pdf'] ?? null;
        if (is_string($pdf) && $pdf !== '') {
            $doc = WhatsAppService::enviarDocumento(
                $telefono,
                $pdf,
                'factura-' . $serieFolio . '.pdf',
                'Tu factura ' . $serieFolio . ($uuid !== '' ? ' UUID: ' . $uuid : '')
            );
            if (!empty($doc['success'])) {
                return ['ok' => true];
            }
        }

        $mensaje = 'Tu factura CFDI ' . $serieFolio;
        if ($uuid !== '') {
            $mensaje .= ' (UUID: ' . $uuid . ')';
        }
        $mensaje .= ' fue emitida. Revisa tu correo para el PDF y XML.';
        $gen = WhatsAppService::enviarNotificacionGenerica($telefono, $mensaje);
        return ['ok' => !empty($gen['success']), 'error' => $gen['message'] ?? null];
    }

    private function actualizarEstadoEnvio(int $idFactura, string $canal, array $resultado): void
    {
        $estado = 'omitido';
        if (!empty($resultado['ok'])) {
            $estado = 'enviado';
        } elseif (empty($resultado['omitido'])) {
            $estado = 'error';
        }

        $colEstado = $canal === 'correo' ? 'envio_correo_estado' : 'envio_whatsapp_estado';
        $colFecha = $canal === 'correo' ? 'envio_correo_fecha' : 'envio_whatsapp_fecha';

        $db = (new Factura())->getDb();
        $st = $db->prepare(
            "UPDATE facturas SET {$colEstado} = :est, {$colFecha} = NOW() WHERE id_factura = :id"
        );
        $st->bindValue(':est', $estado, PDO::PARAM_STR);
        $st->bindValue(':id', $idFactura, PDO::PARAM_INT);
        $st->execute();
    }
}
