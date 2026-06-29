<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/configuracion_general.php';
require_once __DIR__ . '/../models/ventas.php';

class CfdiBuilder
{
    private ConfiguracionGeneral $config;
    private Ventas $ventas;

    public function __construct(?ConfiguracionGeneral $config = null, ?Ventas $ventas = null)
    {
        $this->config = $config ?? new ConfiguracionGeneral();
        $this->ventas = $ventas ?? new Ventas();
    }

    public function leerConfigFiscal(): array
    {
        return $this->config->leerConDefaults([
            'cfdi_rfc_emisor',
            'cfdi_nombre_emisor',
            'cfdi_regimen_fiscal',
            'cfdi_lugar_expedicion',
            'cfdi_serie',
            'cfdi_siguiente_folio',
            'cfdi_clave_unidad_default',
            'cfdi_clave_prod_serv_insumo_default',
            'cfdi_forma_pago_online_default',
        ]);
    }

    /**
     * @return array{payload: array, meta: array}
     */
    public function construirDesdeVenta(int $idVenta): array
    {
        $db = $this->ventas->getDb();
        $venta = $this->ventas->leerUno($idVenta);
        if (!is_array($venta)) {
            throw new InvalidArgumentException('Venta no encontrada.');
        }

        $cfg = $this->leerConfigFiscal();
        $rfcEmisor = strtoupper(trim((string) ($cfg['cfdi_rfc_emisor'] ?? '')));
        $nombreEmisor = trim((string) ($cfg['cfdi_nombre_emisor'] ?? ''));
        $regimenEmisor = trim((string) ($cfg['cfdi_regimen_fiscal'] ?? '601'));
        $cpExp = trim((string) ($cfg['cfdi_lugar_expedicion'] ?? ''));

        if ($rfcEmisor === '' || $nombreEmisor === '' || $cpExp === '') {
            throw new RuntimeException('Configura RFC, nombre y CP de expedicion en Facturacion.');
        }

        $serie = trim((string) ($cfg['cfdi_serie'] ?? 'A'));
        $folio = (int) ($cfg['cfdi_siguiente_folio'] ?? 1);
        $claveUnidadDefault = trim((string) ($cfg['cfdi_clave_unidad_default'] ?? 'H87')) ?: 'H87';
        $claveInsumoDefault = trim((string) ($cfg['cfdi_clave_prod_serv_insumo_default'] ?? '53131600')) ?: '53131600';

        $receptor = $this->resolverReceptor($db, $venta, $cpExp);
        $pagos = $this->resolverPagos($db, $idVenta, $cfg);
        $tasaIva = (float) ($venta['impuesto_porcentaje'] ?? 0);
        $rateIva = $tasaIva > 0 ? round($tasaIva / 100, 4) : 0.0;

        $detalle = isset($venta['detalle']) && is_array($venta['detalle']) ? $venta['detalle'] : [];
        if ($detalle === []) {
            throw new InvalidArgumentException('La venta no tiene lineas para facturar.');
        }

        $items = [];
        $lineasMeta = [];
        $subtotalAcum = 0.0;

        foreach ($detalle as $ln) {
            if (!is_array($ln)) {
                continue;
            }
            $cantidad = (float) ($ln['cantidad'] ?? 1);
            if ($cantidad <= 0) {
                $cantidad = 1.0;
            }
            $subtotalLinea = (float) ($ln['subtotal'] ?? 0);
            $descuento = (float) ($ln['descuento_aplicado'] ?? 0);
            $baseGravable = max(0.0, $subtotalLinea - $descuento);
            $subtotalAcum += $baseGravable;

            $descripcion = $this->armarDescripcionLinea($ln);
            $claveProd = $this->resolverClaveProdServ($db, $ln, $claveInsumoDefault);

            $importeIva = $rateIva > 0 ? round($baseGravable * $rateIva, 2) : 0.0;
            $precioUnitario = $cantidad > 0 ? round($baseGravable / $cantidad, 2) : $baseGravable;

            $item = [
                'ProductCode' => $claveProd,
                'Description' => mb_substr($descripcion, 0, 250),
                'UnitCode' => $claveUnidadDefault,
                'Quantity' => round($cantidad, 2),
                'UnitPrice' => $precioUnitario,
                'Subtotal' => round($baseGravable, 2),
                'TaxObject' => $rateIva > 0 ? '02' : '01',
            ];
            if ($descuento > 0.009) {
                $item['Discount'] = round($descuento, 2);
            }
            if ($rateIva > 0) {
                $item['Taxes'] = [[
                    'Total' => $importeIva,
                    'Name' => 'IVA',
                    'Base' => round($baseGravable, 2),
                    'Rate' => $rateIva,
                    'IsRetention' => false,
                ]];
                $item['Total'] = round($baseGravable + $importeIva, 2);
            } else {
                $item['Total'] = round($baseGravable, 2);
            }

            $items[] = $item;
            $lineasMeta[] = [
                'descripcion' => $descripcion,
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
                'importe' => round($baseGravable, 2),
                'clave_prod_serv' => $claveProd,
                'clave_unidad' => $claveUnidadDefault,
                'objeto_imp' => $rateIva > 0 ? '02' : '01',
                'tasa_iva' => $tasaIva,
                'base_iva' => round($baseGravable, 2),
                'importe_iva' => $importeIva,
            ];
        }

        $totalVenta = (float) ($venta['total'] ?? 0);
        $impuestoMonto = (float) ($venta['impuesto_monto'] ?? 0);

        $payload = [
            'CfdiType' => 'I',
            'PaymentForm' => $pagos['payment_form'],
            'PaymentMethod' => 'PUE',
            'ExpeditionPlace' => $cpExp,
            'Date' => date('Y-m-d H:i:s'),
            'Serie' => $serie,
            'Folio' => (string) $folio,
            'Issuer' => [
                'Rfc' => $rfcEmisor,
                'Name' => $nombreEmisor,
                'FiscalRegime' => $regimenEmisor,
            ],
            'Receiver' => $receptor['receiver'],
            'Items' => $items,
        ];

        return [
            'payload' => $payload,
            'meta' => [
                'serie' => $serie,
                'folio' => (string) $folio,
                'subtotal' => round($subtotalAcum, 2),
                'total' => round($totalVenta, 2),
                'impuesto_monto' => round($impuestoMonto, 2),
                'rfc_emisor' => $rfcEmisor,
                'rfc_receptor' => $receptor['rfc'],
                'uso_cfdi' => $receptor['uso_cfdi'],
                'metodo_pago' => 'PUE',
                'id_forma_pago_FK' => $pagos['id_forma_pago_FK'],
                'factura_pagos' => $pagos['factura_pagos'],
                'lineas' => $lineasMeta,
                'id_apartado_FK' => isset($venta['id_apartado_FK']) ? (int) $venta['id_apartado_FK'] : null,
            ],
        ];
    }

    private function armarDescripcionLinea(array $ln): string
    {
        $nombre = trim((string) ($ln['nombre_item'] ?? 'Articulo'));
        $tipo = (string) ($ln['tipo_linea'] ?? 'joya');
        if ($tipo === 'insumo') {
            $cod = trim((string) ($ln['insumo_codigo'] ?? ''));
        } else {
            $cod = trim((string) ($ln['pieza_codigo_auxiliar'] ?? $ln['pieza_codigo_barras'] ?? ''));
        }
        if ($cod !== '') {
            return $cod . ' - ' . $nombre;
        }
        return $nombre;
    }

    private function resolverClaveProdServ(PDO $db, array $ln, string $defaultInsumo): string
    {
        $tipo = (string) ($ln['tipo_linea'] ?? 'joya');
        if ($tipo === 'insumo') {
            $idInsumo = (int) ($ln['id_insumo_FK'] ?? 0);
            if ($idInsumo > 0) {
                $st = $db->prepare('SELECT clave_prod_serv FROM insumos WHERE id_insumo = :id LIMIT 1');
                $st->bindValue(':id', $idInsumo, PDO::PARAM_INT);
                $st->execute();
                $clave = trim((string) ($st->fetchColumn() ?: ''));
                if ($clave !== '') {
                    return $clave;
                }
            }
            return $defaultInsumo;
        }

        $idPs = (int) ($ln['id_pieza_stock_FK'] ?? 0);
        if ($idPs > 0) {
            $st = $db->prepare(
                'SELECT f.clave_prod_serv
                 FROM piezas_stock ps
                 INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
                 INNER JOIN sub_familia sf ON sf.id_sub_familia = p.id_sub_familia_FK
                 INNER JOIN familias f ON f.id_familia = sf.id_familia_FK
                 WHERE ps.id_pieza_stock = :id LIMIT 1'
            );
            $st->bindValue(':id', $idPs, PDO::PARAM_INT);
            $st->execute();
            $clave = trim((string) ($st->fetchColumn() ?: ''));
            if ($clave !== '') {
                return $clave;
            }
        }
        return '42181500';
    }

    /** @return array{receiver: array, rfc: string, uso_cfdi: string} */
    private function resolverReceptor(PDO $db, array $venta, string $cpExpedicionDefault): array
    {
        $idCliente = isset($venta['id_cliente_FK']) ? (int) $venta['id_cliente_FK'] : 0;

        if ($idCliente <= 0) {
            return [
                'receiver' => [
                    'Rfc' => 'XAXX010101000',
                    'Name' => 'PUBLICO EN GENERAL',
                    'CfdiUse' => 'S01',
                    'FiscalRegime' => '616',
                    'TaxZipCode' => $cpExpedicionDefault,
                ],
                'rfc' => 'XAXX010101000',
                'uso_cfdi' => 'S01',
            ];
        }

        $st = $db->prepare(
            'SELECT c.rfc, c.razon_social, c.regimen_fiscal, c.uso_cfdi, c.codigo_postal_fiscal,
                    u.nombre, u.primer_apellido, u.segundo_apellido,
                    cp.codigo_postal
             FROM clientes c
             INNER JOIN usuarios u ON u.id_usuario = c.id_usuario_FK
             LEFT JOIN direcciones d ON d.id_direccion = u.id_direccion_FK
             LEFT JOIN calles ca ON ca.id_calle = d.id_calle_FK
             LEFT JOIN colonias co ON co.id_colonia = ca.id_colonia_FK
             LEFT JOIN codigos_postales cp ON cp.id_codigo_postal = co.id_codigo_postal_FK
             WHERE c.id_cliente = :id LIMIT 1'
        );
        $st->bindValue(':id', $idCliente, PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new InvalidArgumentException('Cliente de la venta no encontrado.');
        }

        $rfc = strtoupper(trim((string) ($row['rfc'] ?? '')));
        $razon = trim((string) ($row['razon_social'] ?? ''));
        if ($razon === '') {
            $razon = trim(
                (string) ($row['nombre'] ?? '') . ' '
                . (string) ($row['primer_apellido'] ?? '') . ' '
                . (string) ($row['segundo_apellido'] ?? '')
            );
        }
        $razon = preg_replace('/\s+/', ' ', $razon) ?? $razon;

        $cpFiscal = trim((string) ($row['codigo_postal_fiscal'] ?? ''));
        if ($cpFiscal === '') {
            $cpFiscal = trim((string) ($row['codigo_postal'] ?? ''));
        }
        if ($cpFiscal === '') {
            $cpFiscal = $cpExpedicionDefault;
        }

        if ($rfc === '' || strlen($rfc) < 12) {
            return [
                'receiver' => [
                    'Rfc' => 'XAXX010101000',
                    'Name' => 'PUBLICO EN GENERAL',
                    'CfdiUse' => 'S01',
                    'FiscalRegime' => '616',
                    'TaxZipCode' => $cpFiscal,
                ],
                'rfc' => 'XAXX010101000',
                'uso_cfdi' => 'S01',
            ];
        }

        $regimen = trim((string) ($row['regimen_fiscal'] ?? '616')) ?: '616';
        $uso = trim((string) ($row['uso_cfdi'] ?? 'G03')) ?: 'G03';

        return [
            'receiver' => [
                'Rfc' => $rfc,
                'Name' => mb_substr($razon, 0, 254),
                'CfdiUse' => $uso,
                'FiscalRegime' => $regimen,
                'TaxZipCode' => $cpFiscal,
            ],
            'rfc' => $rfc,
            'uso_cfdi' => $uso,
        ];
    }

    /** @return array{payment_form: string, id_forma_pago_FK: int, factura_pagos: array} */
    private function resolverPagos(PDO $db, int $idVenta, array $cfg): array
    {
        $st = $db->prepare(
            'SELECT vp.id_forma_pago_FK, vp.monto, fp.clave_sat, fp.forma_pago
             FROM venta_pagos vp
             INNER JOIN forma_pago fp ON fp.id_forma_pago = vp.id_forma_pago_FK
             WHERE vp.id_venta_FK = :id
             ORDER BY vp.monto DESC'
        );
        $st->bindValue(':id', $idVenta, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $onlineDefault = trim((string) ($cfg['cfdi_forma_pago_online_default'] ?? '03')) ?: '03';

        if ($rows === []) {
            $idFp = $this->config->resolverIdFormaPagoDefault() ?? 0;
            if ($idFp <= 0) {
                $idFp = (int) ($db->query('SELECT id_forma_pago FROM forma_pago WHERE activo = 1 ORDER BY id_forma_pago ASC LIMIT 1')->fetchColumn() ?: 0);
            }
            return [
                'payment_form' => $onlineDefault,
                'id_forma_pago_FK' => $idFp > 0 ? $idFp : 1,
                'factura_pagos' => [],
            ];
        }

        $facturaPagos = [];
        $claves = [];
        foreach ($rows as $r) {
            $clave = trim((string) ($r['clave_sat'] ?? '99')) ?: '99';
            $claves[$clave] = true;
            $facturaPagos[] = [
                'id_forma_pago_FK' => (int) $r['id_forma_pago_FK'],
                'monto' => (float) $r['monto'],
                'clave_sat' => $clave,
            ];
        }

        $paymentForm = count($claves) > 1 ? '99' : array_key_first($claves);

        return [
            'payment_form' => $paymentForm,
            'id_forma_pago_FK' => (int) $rows[0]['id_forma_pago_FK'],
            'factura_pagos' => $facturaPagos,
        ];
    }
}
