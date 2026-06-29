<?php
require_once __DIR__ . '/../models/configuracion_general.php';
require_once __DIR__ . '/../models/tiendas.php';
require_once __DIR__ . '/../models/forma_pago.php';
require_once __DIR__ . '/../models/impuestos.php';
require_once __DIR__ . '/ConfiguracionTicketController.php';
require_once __DIR__ . '/ConfiguracionContratoController.php';
require_once __DIR__ . '/SpeiDepositoPayloadBuilder.php';

class ConfiguracionGeneralPanelController
{
    public const SECCIONES = ['negocio', 'ticket', 'etiquetas', 'contratos', 'mensajeria', 'facturacion'];

    private const FACTURACION_CLAVES = [
        'facturacion_habilitada',
        'facturama_api_url',
        'facturama_modo',
        'cfdi_rfc_emisor',
        'cfdi_nombre_emisor',
        'cfdi_regimen_fiscal',
        'cfdi_lugar_expedicion',
        'cfdi_serie',
        'cfdi_siguiente_folio',
        'cfdi_clave_unidad_default',
        'cfdi_clave_prod_serv_insumo_default',
        'cfdi_forma_pago_online_default',
        'whatsapp_template_factura',
    ];

    private const MENSAJERIA_CLAVES = [
        'whatsapp_habilitado',
        'whatsapp_phone_number_id',
        'whatsapp_api_version',
        'whatsapp_codigo_pais_default',
        'whatsapp_template_idioma',
        'whatsapp_template_bienvenida_cliente',
        'whatsapp_template_bienvenida_empleado',
        'whatsapp_template_notificacion',
    ];

    private ConfiguracionGeneral $config;
    private ConfiguracionTicketController $ticketCtrl;
    private ConfiguracionContratoController $contratoCtrl;

    public function __construct()
    {
        $this->config = new ConfiguracionGeneral();
        $this->ticketCtrl = new ConfiguracionTicketController();
        $this->contratoCtrl = new ConfiguracionContratoController();
    }

    public function seccionActiva(?string $seccion): string
    {
        $seccion = strtolower(trim((string) $seccion));
        if (in_array($seccion, self::SECCIONES, true)) {
            return $seccion;
        }

        return 'negocio';
    }

    public function catalogos(): array
    {
        $tiendas = new Tiendas();
        $formas = new FormaPago();
        $impuestos = new Impuestos();

        return [
            'tiendas' => $tiendas->leer(),
            'formas_pago' => $formas->leer(),
            'impuestos' => $impuestos->leer(),
        ];
    }

    public function valoresActuales(): array
    {
        $negocio = $this->config->leerConDefaults([
            'tipo_codigo_barras_default',
            'id_tienda_default',
            'markup_pct_default',
            'descuento_general_mostrador',
            'descuento_insumos_mostrador',
            'mayoreo_umbral_mxn',
            'mayoreo_descuento_pct',
            'id_forma_pago_default',
            'id_impuesto_default',
            'spei_deposito_habilitado',
            'spei_beneficiario',
            'spei_banco',
            'spei_clabe',
            'spei_instrucciones',
            'spei_referencia_prefijo',
        ]);
        $negocio['spei_deposito_habilitado'] = !empty($negocio['spei_deposito_habilitado']);

        $ticket = $this->ticketCtrl->valoresActuales();
        $contrato = $this->contratoCtrl->valoresActuales();
        $mensajeria = $this->valoresMensajeria();
        $facturacion = $this->valoresFacturacion();

        $valores = array_merge($negocio, $ticket, $mensajeria, $facturacion, [
            'contrato_ciudad' => $contrato['ciudad'],
            'contrato_domicilio_fuente_trabajo' => $contrato['domicilio_fuente_trabajo'],
            'contrato_nombre_patron' => $contrato['nombre_patron'],
            'contrato_tribunal_ciudad' => $contrato['tribunal_ciudad'],
            'contrato_jornada_horas_semanales' => $contrato['jornada_horas_semanales'],
            'contrato_nacionalidad_default' => $contrato['nacionalidad_default'],
        ]);

        return $this->resolverIdsCatalogoParaUi($valores, $this->catalogos());
    }

    public function guardar(array $post): void
    {
        $this->guardarNegocio($post);
        $this->ticketCtrl->guardar($post);
        $this->contratoCtrl->guardar($post);
        $this->guardarMensajeria($post);
        $this->guardarFacturacion($post);
    }

    private function valoresFacturacion(): array
    {
        $defaults = [
            'facturacion_habilitada' => false,
            'facturama_api_url' => 'https://apisandbox.facturama.mx',
            'facturama_modo' => 'sandbox',
            'cfdi_rfc_emisor' => '',
            'cfdi_nombre_emisor' => '',
            'cfdi_regimen_fiscal' => '601',
            'cfdi_lugar_expedicion' => '',
            'cfdi_serie' => 'A',
            'cfdi_siguiente_folio' => 1,
            'cfdi_forma_pago_online_default' => '03',
            'whatsapp_template_factura' => '',
        ];
        $map = $this->config->leerPorClaves(self::FACTURACION_CLAVES);
        foreach ($map as $clave => $valor) {
            if ($valor !== null && $valor !== '') {
                $defaults[$clave] = $valor;
            }
        }
        $defaults['facturacion_habilitada'] = !empty($map['facturacion_habilitada']);
        return $defaults;
    }

    private function guardarFacturacion(array $post): void
    {
        $habilitado = !empty($post['facturacion_habilitada']) ? '1' : '0';
        $this->config->guardarPorClave('facturacion_habilitada', $habilitado, 'BOOLEAN', 'Emite CFDI automaticamente');

        $textos = [
            'cfdi_rfc_emisor' => 'RFC emisor CFDI',
            'cfdi_nombre_emisor' => 'Nombre emisor CFDI',
            'cfdi_regimen_fiscal' => 'Regimen fiscal emisor',
            'cfdi_lugar_expedicion' => 'CP expedicion',
            'cfdi_serie' => 'Serie CFDI',
            'cfdi_forma_pago_online_default' => 'Forma pago SAT online',
            'whatsapp_template_factura' => 'Plantilla WhatsApp factura',
            'facturama_modo' => 'Modo Facturama sandbox/produccion',
        ];
        foreach ($textos as $clave => $desc) {
            if (!array_key_exists($clave, $post)) {
                continue;
            }
            $valor = trim((string) $post[$clave]);
            if ($valor === '') {
                continue;
            }
            $this->config->guardarPorClave($clave, mb_substr($valor, 0, 255), 'STRING', $desc);
        }

        if (isset($post['cfdi_siguiente_folio'])) {
            $folio = max(1, (int) $post['cfdi_siguiente_folio']);
            $this->config->guardarPorClave('cfdi_siguiente_folio', (string) $folio, 'INT', 'Proximo folio CFDI');
        }

        if (isset($post['facturama_modo'])) {
            $modo = strtolower(trim((string) $post['facturama_modo']));
            if (in_array($modo, ['sandbox', 'produccion'], true)) {
                $this->config->guardarPorClave('facturama_modo', $modo, 'STRING', 'Modo Facturama');
                $url = $modo === 'produccion' ? 'https://apis.facturama.mx' : 'https://apisandbox.facturama.mx';
                $this->config->guardarPorClave('facturama_api_url', $url, 'STRING', 'URL API Facturama');
            }
        }
    }

    private function valoresMensajeria(): array
    {
        $defaults = [
            'whatsapp_habilitado' => false,
            'whatsapp_phone_number_id' => '',
            'whatsapp_api_version' => 'v20.0',
            'whatsapp_codigo_pais_default' => '52',
            'whatsapp_template_idioma' => 'es_MX',
            'whatsapp_template_bienvenida_cliente' => '',
            'whatsapp_template_bienvenida_empleado' => '',
            'whatsapp_template_notificacion' => '',
        ];

        $map = $this->config->leerPorClaves(self::MENSAJERIA_CLAVES);
        foreach ($map as $clave => $valor) {
            if ($valor !== null && $valor !== '') {
                $defaults[$clave] = $valor;
            }
        }
        $defaults['whatsapp_habilitado'] = !empty($map['whatsapp_habilitado']);

        return $defaults;
    }

    private function guardarMensajeria(array $post): void
    {
        $habilitado = !empty($post['whatsapp_habilitado']) ? '1' : '0';
        $this->config->guardarPorClave(
            'whatsapp_habilitado',
            $habilitado,
            'BOOLEAN',
            'Activa el envio por WhatsApp (1=si, 0=no)'
        );

        $camposTexto = [
            'whatsapp_phone_number_id' => 'Phone Number ID de WhatsApp Cloud API (Meta)',
            'whatsapp_api_version' => 'Version de Graph API (ej. v20.0)',
            'whatsapp_codigo_pais_default' => 'Lada por defecto si el telefono no la trae',
            'whatsapp_template_idioma' => 'Codigo de idioma de las plantillas (ej. es_MX)',
            'whatsapp_template_bienvenida_cliente' => 'Nombre de la plantilla de bienvenida para clientes',
            'whatsapp_template_bienvenida_empleado' => 'Nombre de la plantilla de bienvenida para empleados',
            'whatsapp_template_notificacion' => 'Nombre de la plantilla para notificaciones especiales',
        ];

        foreach ($camposTexto as $clave => $descripcion) {
            if (!array_key_exists($clave, $post)) {
                continue;
            }
            $valor = trim((string) $post[$clave]);
            if ($valor === '') {
                // El modelo no permite valores vacios; se deja el valor previo.
                continue;
            }
            if (mb_strlen($valor) > 255) {
                $valor = mb_substr($valor, 0, 255);
            }
            $this->config->guardarPorClave($clave, $valor, 'STRING', $descripcion);
        }

        if (class_exists('WhatsAppService')) {
            WhatsAppService::resetConfig();
        }
    }

    public function vistaPreviaTicket(array $valores): string
    {
        return $this->ticketCtrl->vistaPrevia($valores);
    }

    public function opcionesTipoCodigoBarras(): array
    {
        return [
            'QR' => 'QR (recomendado)',
            'CODE128' => 'CODE128',
            'EAN13' => 'EAN13',
            'EAN8' => 'EAN8',
        ];
    }

    private function resolverIdsCatalogoParaUi(array $valores, array $catalogos): array
    {
        if ((int) ($valores['id_forma_pago_default'] ?? 0) === 0) {
            foreach ((array) ($catalogos['formas_pago'] ?? []) as $fp) {
                if (!empty($fp['activo'])) {
                    $valores['id_forma_pago_default'] = (int) $fp['id_forma_pago'];
                    break;
                }
            }
        }

        if ((int) ($valores['id_impuesto_default'] ?? 0) === 0) {
            foreach ((array) ($catalogos['impuestos'] ?? []) as $imp) {
                $id = (int) ($imp['id_impuesto'] ?? 0);
                if ($id > 0) {
                    $valores['id_impuesto_default'] = $id;
                    break;
                }
            }
        }

        return $valores;
    }

    private function guardarNegocio(array $post): void
    {
        if (isset($post['tipo_codigo_barras_default'])) {
            $tipo = strtoupper(trim((string) $post['tipo_codigo_barras_default']));
            $permitidos = array_keys($this->opcionesTipoCodigoBarras());
            if (!in_array($tipo, $permitidos, true)) {
                throw new InvalidArgumentException('Tipo de codigo de barras no valido.');
            }
            $this->config->guardarPorClave(
                'tipo_codigo_barras_default',
                $tipo,
                'STRING',
                'Tipo de codigo de barras por defecto para piezas_stock'
            );
        }

        if (isset($post['id_tienda_default'])) {
            $idTienda = max(0, (int) $post['id_tienda_default']);
            $this->config->guardarPorClave(
                'id_tienda_default',
                (string) $idTienda,
                'INT',
                'ID de la tienda por defecto para nuevas piezas'
            );
        }

        if (isset($post['markup_pct_default'])) {
            $markup = (float) str_replace(',', '.', (string) $post['markup_pct_default']);
            if ($markup < 0 || $markup > 9999) {
                throw new InvalidArgumentException('El margen debe estar entre 0 y 9999.');
            }
            $this->config->guardarPorClave(
                'markup_pct_default',
                number_format($markup, 2, '.', ''),
                'DECIMAL',
                'Porcentaje de margen al generar stock inicial de piezas'
            );
        }

        if (isset($post['descuento_general_mostrador'])) {
            $desc = (float) str_replace(',', '.', (string) $post['descuento_general_mostrador']);
            if ($desc < 0 || $desc > 100) {
                throw new InvalidArgumentException('El descuento general debe estar entre 0 y 100.');
            }
            $this->config->guardarPorClave(
                'descuento_general_mostrador',
                number_format($desc, 2, '.', ''),
                'DECIMAL',
                'Descuento general en punto de venta sin descuento de cliente'
            );
        }

        if (isset($post['descuento_insumos_mostrador'])) {
            $descIns = (float) str_replace(',', '.', (string) $post['descuento_insumos_mostrador']);
            if ($descIns < 0 || $descIns > 100) {
                throw new InvalidArgumentException('El descuento de insumos debe estar entre 0 y 100.');
            }
            $this->config->guardarPorClave(
                'descuento_insumos_mostrador',
                number_format($descIns, 2, '.', ''),
                'DECIMAL',
                'Descuento en POS para lineas insumo (no usa descuento del cliente)'
            );
        }

        if (isset($post['mayoreo_umbral_mxn'])) {
            $umbral = (float) str_replace(',', '.', (string) $post['mayoreo_umbral_mxn']);
            if ($umbral < 0) {
                throw new InvalidArgumentException('El umbral de mayoreo no puede ser negativo.');
            }
            $this->config->guardarPorClave(
                'mayoreo_umbral_mxn',
                number_format($umbral, 2, '.', ''),
                'DECIMAL',
                'Subtotal mínimo de joyas a precio lista para descuento mayoreo'
            );
        }

        if (isset($post['mayoreo_descuento_pct'])) {
            $pctMay = (float) str_replace(',', '.', (string) $post['mayoreo_descuento_pct']);
            if ($pctMay < 0 || $pctMay > 100) {
                throw new InvalidArgumentException('El descuento mayoreo debe estar entre 0 y 100.');
            }
            $this->config->guardarPorClave(
                'mayoreo_descuento_pct',
                number_format($pctMay, 2, '.', ''),
                'DECIMAL',
                'Porcentaje de descuento mayoreo en joyas'
            );
        }

        if (isset($post['mayoreo_umbral_mxn']) || isset($post['mayoreo_descuento_pct'])) {
            require_once __DIR__ . '/DescuentoTiendaService.php';
            DescuentoTiendaService::limpiarCacheConfig();
        }

        if (isset($post['id_forma_pago_default'])) {
            $idFp = (int) $post['id_forma_pago_default'];
            if ($idFp > 0) {
                $forma = new FormaPago();
                $rowFp = $forma->leerUno($idFp);
                if (!is_array($rowFp) || empty($rowFp['activo'])) {
                    throw new InvalidArgumentException('La forma de pago seleccionada no es valida.');
                }
            }
            $this->config->guardarPorClave(
                'id_forma_pago_default',
                (string) max(0, $idFp),
                'INT',
                'Forma de pago preseleccionada en formularios'
            );
        }

        if (isset($post['id_impuesto_default'])) {
            $idImp = (int) $post['id_impuesto_default'];
            if ($idImp > 0) {
                $imp = new Impuestos();
                $rowImp = $imp->leerUno($idImp);
                if (!is_array($rowImp)) {
                    throw new InvalidArgumentException('El impuesto seleccionado no es valido.');
                }
            }
            $this->config->guardarPorClave(
                'id_impuesto_default',
                (string) max(0, $idImp),
                'INT',
                'Impuesto preseleccionado en formularios'
            );
        }

        $this->guardarSpeiDeposito($post);
    }

    private function guardarSpeiDeposito(array $post): void
    {
        if (!array_key_exists('spei_deposito_habilitado', $post)
            && !array_key_exists('spei_beneficiario', $post)
            && !array_key_exists('spei_banco', $post)
            && !array_key_exists('spei_clabe', $post)
            && !array_key_exists('spei_instrucciones', $post)
            && !array_key_exists('spei_referencia_prefijo', $post)) {
            return;
        }

        $habilitado = !empty($post['spei_deposito_habilitado']) ? '1' : '0';
        $this->config->guardarPorClave(
            'spei_deposito_habilitado',
            $habilitado,
            'BOOLEAN',
            'Muestra QR de datos bancarios en punto de venta'
        );

        $camposTexto = [
            'spei_beneficiario' => 'Titular cuenta SPEI para depositos',
            'spei_banco' => 'Banco receptor SPEI',
            'spei_instrucciones' => 'Instrucciones opcionales para transferencia SPEI',
            'spei_referencia_prefijo' => 'Prefijo concepto/referencia SPEI en POS',
        ];
        foreach ($camposTexto as $clave => $descripcion) {
            if (!array_key_exists($clave, $post)) {
                continue;
            }
            $valor = trim((string) $post[$clave]);
            if ($valor === '') {
                continue;
            }
            $this->config->guardarPorClave($clave, mb_substr($valor, 0, 255), 'STRING', $descripcion);
        }

        if (array_key_exists('spei_clabe', $post)) {
            $clabe = SpeiDepositoPayloadBuilder::normalizarClabe((string) $post['spei_clabe']);
            if ($clabe === '') {
                return;
            }
            if (strlen($clabe) !== 18 || !ctype_digit($clabe)) {
                throw new InvalidArgumentException('La CLABE debe tener exactamente 18 digitos.');
            }
            if (!SpeiDepositoPayloadBuilder::validarClabe($clabe)) {
                throw new InvalidArgumentException('La CLABE no es valida (digito verificador incorrecto).');
            }
            if ($habilitado === '1' && trim((string) ($post['spei_beneficiario'] ?? '')) === '') {
                $map = $this->config->leerPorClaves(['spei_beneficiario']);
                if (trim((string) ($map['spei_beneficiario'] ?? '')) === '') {
                    throw new InvalidArgumentException('Indica el beneficiario para habilitar el QR de transferencia.');
                }
            }
            $this->config->guardarPorClave('spei_clabe', $clabe, 'STRING', 'CLABE interbancaria para depositos SPEI');
        } elseif ($habilitado === '1') {
            $map = $this->config->leerDatosDepositoSpei();
            if (empty($map['clabe']) || !SpeiDepositoPayloadBuilder::validarClabe((string) $map['clabe'])) {
                throw new InvalidArgumentException('Configura una CLABE valida para habilitar el QR de transferencia.');
            }
        }
    }
}
