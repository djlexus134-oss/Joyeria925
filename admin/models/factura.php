<?php
declare(strict_types=1);

require_once __DIR__ . '/../../sistema.class.php';
require_once __DIR__ . '/configuracion_general.php';
require_once __DIR__ . '/../includes/FacturamaClient.php';
require_once __DIR__ . '/../includes/CfdiBuilder.php';

class Factura extends Sistema
{
    public function facturacionHabilitada(): bool
    {
        try {
            $map = (new ConfiguracionGeneral())->leerPorClaves(['facturacion_habilitada']);
            $v = $map['facturacion_habilitada'] ?? false;
            return $v === true || $v === 1 || $v === '1';
        } catch (Throwable $e) {
            return false;
        }
    }

    public function leerPorVenta(int $idVenta): ?array
    {
        if ($idVenta <= 0) {
            return null;
        }
        $st = $this->getDb()->prepare(
            'SELECT * FROM facturas WHERE id_venta_FK = :id ORDER BY id_factura DESC LIMIT 1'
        );
        $st->bindValue(':id', $idVenta, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function leerUno(int $idFactura): ?array
    {
        $st = $this->getDb()->prepare('SELECT * FROM facturas WHERE id_factura = :id LIMIT 1');
        $st->bindValue(':id', $idFactura, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $stD = $this->getDb()->prepare('SELECT * FROM factura_detalle WHERE id_factura_FK = :id ORDER BY id_factura_detalle ASC');
        $stD->bindValue(':id', $idFactura, PDO::PARAM_INT);
        $stD->execute();
        $row['detalle'] = $stD->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $row;
    }

    public function leer(int $limite = 100, ?string $estado = null): array
    {
        $lim = max(1, min(500, $limite));
        $sql = 'SELECT f.*, v.fecha_venta,
                       COALESCE(CONCAT(uc.nombre, \' \', uc.primer_apellido), \'Publico general\') AS cliente_nombre
                FROM facturas f
                INNER JOIN ventas v ON v.id_venta = f.id_venta_FK
                LEFT JOIN clientes c ON c.id_cliente = v.id_cliente_FK
                LEFT JOIN usuarios uc ON uc.id_usuario = c.id_usuario_FK
                WHERE 1=1';
        if ($estado !== null && $estado !== '') {
            $sql .= ' AND f.estado = :estado';
        }
        $sql .= ' ORDER BY f.fecha_emision DESC, f.id_factura DESC LIMIT ' . $lim;
        $st = $this->getDb()->prepare($sql);
        if ($estado !== null && $estado !== '') {
            $st->bindValue(':estado', $estado, PDO::PARAM_STR);
        }
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Emite CFDI para una venta y opcionalmente envia al cliente.
     *
     * @return array{ok:bool, id_factura?:int, error?:string, ya_existia?:bool}
     */
    public function emitirYEnviarParaVenta(int $idVenta, bool $enviar = true): array
    {
        if (!$this->facturacionHabilitada()) {
            return ['ok' => false, 'error' => 'Facturacion deshabilitada.'];
        }

        $existente = $this->leerPorVenta($idVenta);
        if ($existente && ($existente['estado'] ?? '') === 'emitida') {
            if ($enviar) {
                $this->enviarAlCliente((int) $existente['id_factura']);
            }
            return ['ok' => true, 'id_factura' => (int) $existente['id_factura'], 'ya_existia' => true];
        }

        $emit = $this->emitirParaVenta($idVenta);
        if (!$emit['ok']) {
            return $emit;
        }
        if ($enviar && !empty($emit['id_factura'])) {
            $this->enviarAlCliente((int) $emit['id_factura']);
        }
        return $emit;
    }

    /** @return array{ok:bool, id_factura?:int, error?:string} */
    public function emitirParaVenta(int $idVenta): array
    {
        if ($idVenta <= 0) {
            return ['ok' => false, 'error' => 'ID de venta invalido.'];
        }

        $existente = $this->leerPorVenta($idVenta);
        if ($existente && ($existente['estado'] ?? '') === 'emitida') {
            return ['ok' => true, 'id_factura' => (int) $existente['id_factura']];
        }

        $db = $this->getDb();
        $builder = new CfdiBuilder();
        $client = new FacturamaClient();

        if (!$client->credencialesConfiguradas()) {
            return ['ok' => false, 'error' => 'Credenciales Facturama no configuradas.'];
        }

        try {
            $built = $builder->construirDesdeVenta($idVenta);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $meta = $built['meta'];
        $idFactura = 0;

        if ($existente) {
            $idFactura = (int) $existente['id_factura'];
            $this->actualizarPendiente($idFactura, $meta);
        } else {
            $idFactura = $this->insertarPendiente($idVenta, $meta);
        }

        $res = $client->timbrarCfdi($built['payload']);
        if (!$res['ok']) {
            $this->marcarError($idFactura, (string) ($res['error'] ?? 'Error timbrado'), $res['body'] ?? null);
            return ['ok' => false, 'error' => (string) ($res['error'] ?? 'Error timbrado'), 'id_factura' => $idFactura];
        }

        $data = $res['data'] ?? [];
        $idFacturama = (string) ($data['Id'] ?? $data['id'] ?? '');
        $uuid = (string) ($data['Uuid'] ?? $data['uuid'] ?? '');
        $serie = (string) ($data['Serie'] ?? $meta['serie'] ?? '');
        $folio = (string) ($data['Folio'] ?? $meta['folio'] ?? '');

        $xml = '';
        $pdfBytes = null;
        if ($idFacturama !== '') {
            $xmlRes = $client->descargarXml($idFacturama);
            if ($xmlRes['ok']) {
                $xml = (string) ($xmlRes['body'] ?? '');
            }
            $pdfRes = $client->descargarPdf($idFacturama);
            if ($pdfRes['ok'] && ($pdfRes['body'] ?? '') !== '') {
                $pdfBytes = $pdfRes['body'];
            }
        }

        $db->beginTransaction();
        try {
            $this->completarEmitida($idFactura, [
                'uuid' => $uuid,
                'serie' => $serie,
                'folio' => $folio,
                'xml' => $xml,
                'pdf' => $pdfBytes,
                'id_facturama' => $idFacturama,
                'respuesta_pac' => $data,
            ]);
            $this->guardarDetalle($idFactura, $meta['lineas'] ?? []);
            $this->guardarPagos($idFactura, $meta['factura_pagos'] ?? []);
            $this->incrementarFolio();
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->marcarError($idFactura, $e->getMessage(), $data);
            return ['ok' => false, 'error' => $e->getMessage(), 'id_factura' => $idFactura];
        }

        return ['ok' => true, 'id_factura' => $idFactura];
    }

    /** @return array{correo?:array, whatsapp?:array} */
    public function enviarAlCliente(int $idFactura): array
    {
        require_once __DIR__ . '/../includes/FacturaEnvioService.php';
        return (new FacturaEnvioService())->enviarAlCliente($idFactura);
    }

    /** @return array{ok:bool, error?:string} */
    public function cancelar(int $idFactura, string $motivo = '02'): array
    {
        $factura = $this->leerUno($idFactura);
        if (!$factura || ($factura['estado'] ?? '') !== 'emitida') {
            return ['ok' => false, 'error' => 'Factura no encontrada o no emitida.'];
        }
        $idFm = trim((string) ($factura['id_facturama'] ?? ''));
        if ($idFm === '') {
            return ['ok' => false, 'error' => 'Sin ID Facturama para cancelar.'];
        }
        $res = (new FacturamaClient())->cancelarCfdi($idFm, $motivo);
        if (!$res['ok']) {
            return ['ok' => false, 'error' => (string) ($res['error'] ?? 'Error cancelacion')];
        }
        $st = $this->getDb()->prepare("UPDATE facturas SET estado = 'cancelada' WHERE id_factura = :id");
        $st->bindValue(':id', $idFactura, PDO::PARAM_INT);
        $st->execute();
        return ['ok' => true];
    }

    private function insertarPendiente(int $idVenta, array $meta): int
    {
        $db = $this->getDb();
        $st = $db->prepare(
            "INSERT INTO facturas
                (id_venta_FK, id_apartado_FK, subtotal, total, estado, rfc_emisor, rfc_receptor,
                 uso_cfdi, metodo_pago, id_forma_pago_FK, tipo_comprobante, xml)
             VALUES
                (:venta, :apartado, :sub, :tot, 'pendiente', :rfc_e, :rfc_r,
                 :uso, :metodo, :fp, 'I', NULL)"
        );
        $st->bindValue(':venta', $idVenta, PDO::PARAM_INT);
        $ap = $meta['id_apartado_FK'] ?? null;
        $st->bindValue(':apartado', $ap, $ap === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $st->bindValue(':sub', (string) $meta['subtotal'], PDO::PARAM_STR);
        $st->bindValue(':tot', (string) $meta['total'], PDO::PARAM_STR);
        $st->bindValue(':rfc_e', (string) $meta['rfc_emisor'], PDO::PARAM_STR);
        $st->bindValue(':rfc_r', (string) $meta['rfc_receptor'], PDO::PARAM_STR);
        $st->bindValue(':uso', (string) $meta['uso_cfdi'], PDO::PARAM_STR);
        $st->bindValue(':metodo', (string) $meta['metodo_pago'], PDO::PARAM_STR);
        $st->bindValue(':fp', (int) $meta['id_forma_pago_FK'], PDO::PARAM_INT);
        $st->execute();
        return (int) $db->lastInsertId();
    }

    private function actualizarPendiente(int $idFactura, array $meta): void
    {
        $st = $this->getDb()->prepare(
            "UPDATE facturas SET subtotal = :sub, total = :tot, rfc_emisor = :rfc_e, rfc_receptor = :rfc_r,
             uso_cfdi = :uso, metodo_pago = :metodo, id_forma_pago_FK = :fp, estado = 'pendiente',
             error_timbrado = NULL, id_apartado_FK = :apartado
             WHERE id_factura = :id"
        );
        $ap = $meta['id_apartado_FK'] ?? null;
        $st->bindValue(':sub', (string) $meta['subtotal'], PDO::PARAM_STR);
        $st->bindValue(':tot', (string) $meta['total'], PDO::PARAM_STR);
        $st->bindValue(':rfc_e', (string) $meta['rfc_emisor'], PDO::PARAM_STR);
        $st->bindValue(':rfc_r', (string) $meta['rfc_receptor'], PDO::PARAM_STR);
        $st->bindValue(':uso', (string) $meta['uso_cfdi'], PDO::PARAM_STR);
        $st->bindValue(':metodo', (string) $meta['metodo_pago'], PDO::PARAM_STR);
        $st->bindValue(':fp', (int) $meta['id_forma_pago_FK'], PDO::PARAM_INT);
        $st->bindValue(':apartado', $ap, $ap === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $st->bindValue(':id', $idFactura, PDO::PARAM_INT);
        $st->execute();
    }

    private function completarEmitida(int $idFactura, array $data): void
    {
        $st = $this->getDb()->prepare(
            "UPDATE facturas SET uuid = :uuid, serie = :serie, folio = :folio, estado = 'emitida',
             xml = :xml, pdf = :pdf, id_facturama = :id_fm, respuesta_pac = :resp,
             error_timbrado = NULL, fecha_emision = NOW()
             WHERE id_factura = :id"
        );
        $st->bindValue(':uuid', $data['uuid'] !== '' ? $data['uuid'] : null, $data['uuid'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':serie', $data['serie'], PDO::PARAM_STR);
        $st->bindValue(':folio', $data['folio'], PDO::PARAM_STR);
        $st->bindValue(':xml', $data['xml'] !== '' ? $data['xml'] : null, $data['xml'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':pdf', $data['pdf'], $data['pdf'] !== null ? PDO::PARAM_LOB : PDO::PARAM_NULL);
        $st->bindValue(':id_fm', $data['id_facturama'] !== '' ? $data['id_facturama'] : null, PDO::PARAM_STR);
        $json = json_encode($data['respuesta_pac'] ?? [], JSON_UNESCAPED_UNICODE);
        $st->bindValue(':resp', $json, PDO::PARAM_STR);
        $st->bindValue(':id', $idFactura, PDO::PARAM_INT);
        $st->execute();
    }

    private function marcarError(int $idFactura, string $error, $respuesta = null): void
    {
        $json = is_array($respuesta) ? json_encode($respuesta, JSON_UNESCAPED_UNICODE) : (is_string($respuesta) ? $respuesta : null);
        $st = $this->getDb()->prepare(
            "UPDATE facturas SET estado = 'error', error_timbrado = :err, respuesta_pac = :resp WHERE id_factura = :id"
        );
        $st->bindValue(':err', mb_substr($error, 0, 5000), PDO::PARAM_STR);
        $st->bindValue(':resp', $json, $json !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $st->bindValue(':id', $idFactura, PDO::PARAM_INT);
        $st->execute();
    }

    private function guardarDetalle(int $idFactura, array $lineas): void
    {
        $db = $this->getDb();
        $db->prepare('DELETE FROM factura_detalle WHERE id_factura_FK = :id')->execute([':id' => $idFactura]);

        $st = $db->prepare(
            'INSERT INTO factura_detalle
                (id_factura_FK, clave_prod_serv, clave_unidad, objeto_imp, descripcion, cantidad,
                 precio_unitario, importe, tasa_iva, base_iva, importe_iva)
             VALUES
                (:f, :cps, :cu, :oi, :desc, :cant, :pu, :imp, :tasa, :base, :iva)'
        );
        foreach ($lineas as $ln) {
            if (!is_array($ln)) {
                continue;
            }
            $st->bindValue(':f', $idFactura, PDO::PARAM_INT);
            $st->bindValue(':cps', (string) ($ln['clave_prod_serv'] ?? '42181500'), PDO::PARAM_STR);
            $st->bindValue(':cu', (string) ($ln['clave_unidad'] ?? 'H87'), PDO::PARAM_STR);
            $st->bindValue(':oi', (string) ($ln['objeto_imp'] ?? '02'), PDO::PARAM_STR);
            $st->bindValue(':desc', mb_substr((string) ($ln['descripcion'] ?? ''), 0, 255), PDO::PARAM_STR);
            $st->bindValue(':cant', (string) ($ln['cantidad'] ?? 1), PDO::PARAM_STR);
            $st->bindValue(':pu', (string) ($ln['precio_unitario'] ?? 0), PDO::PARAM_STR);
            $st->bindValue(':imp', (string) ($ln['importe'] ?? 0), PDO::PARAM_STR);
            $st->bindValue(':tasa', $ln['tasa_iva'] ?? null, $ln['tasa_iva'] !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $st->bindValue(':base', $ln['base_iva'] ?? null, $ln['base_iva'] !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $st->bindValue(':iva', $ln['importe_iva'] ?? null, $ln['importe_iva'] !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $st->execute();
        }
    }

    private function guardarPagos(int $idFactura, array $pagos): void
    {
        $db = $this->getDb();
        try {
            $db->prepare('DELETE FROM factura_pagos WHERE id_factura_FK = :id')->execute([':id' => $idFactura]);
        } catch (Throwable $e) {
            return;
        }
        if ($pagos === []) {
            return;
        }
        $st = $db->prepare(
            'INSERT INTO factura_pagos (id_factura_FK, id_forma_pago_FK, monto, clave_sat)
             VALUES (:f, :fp, :m, :c)'
        );
        foreach ($pagos as $p) {
            if (!is_array($p)) {
                continue;
            }
            $st->bindValue(':f', $idFactura, PDO::PARAM_INT);
            $st->bindValue(':fp', (int) ($p['id_forma_pago_FK'] ?? 0), PDO::PARAM_INT);
            $st->bindValue(':m', (string) ($p['monto'] ?? 0), PDO::PARAM_STR);
            $st->bindValue(':c', (string) ($p['clave_sat'] ?? '99'), PDO::PARAM_STR);
            $st->execute();
        }
    }

    private function incrementarFolio(): void
    {
        $cfg = new ConfiguracionGeneral();
        $map = $cfg->leerPorClaves(['cfdi_siguiente_folio']);
        $folio = (int) ($map['cfdi_siguiente_folio'] ?? 1);
        $nuevo = $folio + 1;
        $db = $this->getDb();
        $st = $db->prepare(
            'UPDATE configuracion_general SET valor = :v, fecha_actualizacion = NOW() WHERE clave = :c'
        );
        $st->bindValue(':v', (string) $nuevo, PDO::PARAM_STR);
        $st->bindValue(':c', 'cfdi_siguiente_folio', PDO::PARAM_STR);
        $st->execute();
        if ($st->rowCount() === 0) {
            $ins = $db->prepare(
                'INSERT INTO configuracion_general (clave, valor, tipo, descripcion, fecha_actualizacion)
                 VALUES (:c, :v, \'INT\', \'Proximo folio CFDI\', NOW())'
            );
            $ins->bindValue(':c', 'cfdi_siguiente_folio', PDO::PARAM_STR);
            $ins->bindValue(':v', (string) $nuevo, PDO::PARAM_STR);
            $ins->execute();
        }
    }
}
