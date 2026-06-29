<?php
require_once __DIR__ . '/../models/configuracion_general.php';
require_once __DIR__ . '/TicketService.php';

class ConfiguracionTicketController
{
    private ConfiguracionGeneral $config;
    private TicketService $tickets;

    public function __construct()
    {
        $this->config = new ConfiguracionGeneral();
        $this->tickets = new TicketService();
    }

    public function valoresActuales(): array
    {
        return $this->config->leerConDefaults($this->clavesPanel());
    }

    private function clavesPanel(): array
    {
        return array_merge($this->tickets->clavesConfig(), [
            'etiqueta_impresion_habilitada',
            'etiqueta_impresion_nombre_impresora',
            'etiqueta_impresion_token',
            'etiqueta_ancho_mm',
            'etiqueta_alto_mm',
            'etiqueta_gap_mm',
            'etiqueta_ala_mm',
            'etiqueta_media_mm',
            'etiqueta_cola_mm',
            'etiqueta_alto_cola_mm',
            'etiqueta_dpi',
            'etiqueta_offset_x',
            'etiqueta_offset_y',
            'etiqueta_lang',
            'etiqueta_img_shift_barcode_mm',
            'etiqueta_img_shift_precio_mm',
            'etiqueta_img_margen_izq_barcode_mm',
            'etiqueta_img_margen_der_barcode_mm',
            'etiqueta_img_gap_barcode_texto_mm',
            'etiqueta_img_margen_inferior_aux_mm',
            'etiqueta_img_alto_barcode_ratio',
            'etiqueta_img_tam_aux_pt',
            'etiqueta_img_tam_precio_pt',
            'etiqueta_img_precio_baseline_factor',
            'etiqueta_img_tam_variante_pt',
            'etiqueta_img_margen_inferior_variante_mm',
            'etiqueta_img_precio_con_variante_pt',
        ]);
    }

    public function guardar(array $post): void
    {
        $campos = [
            ['clave' => 'ticket_nombre_comercial', 'tipo' => 'STRING', 'desc' => 'Nombre comercial en ticket'],
            ['clave' => 'ticket_leyenda_folio', 'tipo' => 'STRING', 'desc' => 'Leyenda del folio'],
            ['clave' => 'ticket_horario', 'tipo' => 'STRING', 'desc' => 'Horario en ticket'],
            ['clave' => 'ticket_mensaje_pie', 'tipo' => 'STRING', 'desc' => 'Mensaje al pie'],
            ['clave' => 'ticket_ancho_columnas', 'tipo' => 'INT', 'desc' => 'Ancho en caracteres'],
            ['clave' => 'ticket_margen_izquierdo', 'tipo' => 'INT', 'desc' => 'Margen izquierdo en puntos ESC/POS'],
            ['clave' => 'impresion_nombre_impresora', 'tipo' => 'STRING', 'desc' => 'Nombre impresora Windows'],
            ['clave' => 'impresion_id_tienda_caja', 'tipo' => 'INT', 'desc' => 'ID tienda de esta caja'],
            ['clave' => 'etiqueta_impresion_nombre_impresora', 'tipo' => 'STRING', 'desc' => 'Nombre impresora Argox'],
            ['clave' => 'etiqueta_ancho_mm', 'tipo' => 'INT', 'desc' => 'Ancho etiqueta mm'],
            ['clave' => 'etiqueta_alto_mm', 'tipo' => 'INT', 'desc' => 'Alto etiqueta mm'],
            ['clave' => 'etiqueta_gap_mm', 'tipo' => 'STRING', 'desc' => 'Referencia gap driver mm (no se envia en RAW)'],
            ['clave' => 'etiqueta_ala_mm', 'tipo' => 'INT', 'desc' => 'Ancho cabeza izquierda mm'],
            ['clave' => 'etiqueta_media_mm', 'tipo' => 'INT', 'desc' => 'Zona media ancha mm'],
            ['clave' => 'etiqueta_cola_mm', 'tipo' => 'INT', 'desc' => 'Largo cola estrecha mm'],
            ['clave' => 'etiqueta_alto_cola_mm', 'tipo' => 'INT', 'desc' => 'Alto cola estrecha mm'],
            ['clave' => 'etiqueta_dpi', 'tipo' => 'INT', 'desc' => 'DPI etiquetas'],
            ['clave' => 'etiqueta_offset_x', 'tipo' => 'STRING', 'desc' => 'Offset X mm'],
            ['clave' => 'etiqueta_offset_y', 'tipo' => 'STRING', 'desc' => 'Offset Y mm'],
            ['clave' => 'etiqueta_lang', 'tipo' => 'STRING', 'desc' => 'Lenguaje ZPL o PPLA'],
            ['clave' => 'etiqueta_img_shift_barcode_mm', 'tipo' => 'STRING', 'desc' => 'PNG: desplazar barcode+aux mm'],
            ['clave' => 'etiqueta_img_shift_precio_mm', 'tipo' => 'STRING', 'desc' => 'PNG: desplazar precio mm'],
            ['clave' => 'etiqueta_img_margen_izq_barcode_mm', 'tipo' => 'STRING', 'desc' => 'PNG: margen izq barcode mm'],
            ['clave' => 'etiqueta_img_margen_der_barcode_mm', 'tipo' => 'STRING', 'desc' => 'PNG: margen der barcode mm'],
            ['clave' => 'etiqueta_img_gap_barcode_texto_mm', 'tipo' => 'STRING', 'desc' => 'PNG: gap barcode-texto mm'],
            ['clave' => 'etiqueta_img_margen_inferior_aux_mm', 'tipo' => 'STRING', 'desc' => 'PNG: margen inferior aux mm'],
            ['clave' => 'etiqueta_img_alto_barcode_ratio', 'tipo' => 'STRING', 'desc' => 'PNG: alto barcode / alto etiqueta'],
            ['clave' => 'etiqueta_img_tam_aux_pt', 'tipo' => 'INT', 'desc' => 'PNG: tamano texto aux pt'],
            ['clave' => 'etiqueta_img_tam_precio_pt', 'tipo' => 'INT', 'desc' => 'PNG: tamano precio pt'],
            ['clave' => 'etiqueta_img_precio_baseline_factor', 'tipo' => 'STRING', 'desc' => 'PNG: factor vertical precio'],
            ['clave' => 'etiqueta_img_tam_variante_pt', 'tipo' => 'INT', 'desc' => 'PNG: tamano variante pt'],
            ['clave' => 'etiqueta_img_margen_inferior_variante_mm', 'tipo' => 'STRING', 'desc' => 'PNG: margen inferior variante mm'],
            ['clave' => 'etiqueta_img_precio_con_variante_pt', 'tipo' => 'INT', 'desc' => 'PNG: tamano precio con variante pt'],
        ];

        foreach ($campos as $campo) {
            $clave = $campo['clave'];
            if (!array_key_exists($clave, $post)) {
                continue;
            }
            $valor = trim((string) $post[$clave]);
            $this->config->guardarPorClave($clave, $valor, $campo['tipo'], $campo['desc']);
        }

        $booleanos = [
            'ticket_mostrar_impuesto',
            'ticket_mostrar_empleado',
            'impresion_habilitada',
            'etiqueta_impresion_habilitada',
        ];
        foreach ($booleanos as $clave) {
            $valor = !empty($post[$clave]) ? '1' : '0';
            $this->config->guardarPorClave($clave, $valor, 'BOOLEAN', 'Opcion de ticket');
        }

        if (isset($post['impresion_caja_token'])) {
            $token = trim((string) $post['impresion_caja_token']);
            if ($token !== '') {
                $this->config->guardarPorClave(
                    'impresion_caja_token',
                    $token,
                    'STRING',
                    'Token del agente de impresion'
                );
            }
        }

        if (isset($post['etiqueta_impresion_token'])) {
            $tokenEtiqueta = trim((string) $post['etiqueta_impresion_token']);
            if ($tokenEtiqueta !== '') {
                $this->config->guardarPorClave(
                    'etiqueta_impresion_token',
                    $tokenEtiqueta,
                    'STRING',
                    'Token del agente de etiquetas'
                );
            }
        }
    }

    public function vistaPrevia(array $valores): string
    {
        $ticket = [
            'id_venta' => 123,
            'nombre_comercial' => (string) ($valores['ticket_nombre_comercial'] ?? ''),
            'leyenda_folio' => (string) ($valores['ticket_leyenda_folio'] ?? 'Folio'),
            'horario' => (string) ($valores['ticket_horario'] ?? ''),
            'mensaje_pie' => (string) ($valores['ticket_mensaje_pie'] ?? ''),
            'mostrar_impuesto' => !empty($valores['ticket_mostrar_impuesto']),
            'mostrar_empleado' => !empty($valores['ticket_mostrar_empleado']),
            'ancho_columnas' => (int) ($valores['ticket_ancho_columnas'] ?? 38),
            'margen_izquierdo' => (int) ($valores['ticket_margen_izquierdo'] ?? 40),
            'empleado_numero' => '#0001',
            'subtotal' => 1500.00,
            'impuesto_monto' => 240.00,
            'total' => 1740.00,
            'conteo_piezas' => 1,
            'lineas' => [
                [
                    'descripcion' => 'Anillo de plata demo',
                    'cantidad' => 1,
                    'precio_unitario' => 1500.00,
                    'subtotal' => 1500.00,
                ],
            ],
        ];

        return $this->tickets->renderVistaPreviaHtml($ticket);
    }
}
