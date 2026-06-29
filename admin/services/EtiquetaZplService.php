<?php
require_once __DIR__ . '/../models/configuracion_general.php';
require_once __DIR__ . '/../models/piezas_stock.php';

$autoload = __DIR__ . '/../../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

use Picqer\Barcode\BarcodeGeneratorPNG;

class EtiquetaZplService
{
    private ConfiguracionGeneral $config;
    private PiezasStock $stock;

    public function __construct()
    {
        $this->config = new ConfiguracionGeneral();
        $this->stock = new PiezasStock();
    }

    public function impresionHabilitada(): bool
    {
        $map = $this->config->leerPorClaves(['etiqueta_impresion_habilitada']);

        return !empty($map['etiqueta_impresion_habilitada']);
    }

    public function leerLayout(): array
    {
        $map = $this->config->leerPorClaves([
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
            'etiqueta_impresion_nombre_impresora',
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

        $dpi = max(150, (int) ($map['etiqueta_dpi'] ?? 203));

        return [
            // Deben coincidir con el driver Argox (Material / Preparar pagina).
            'ancho_mm' => max(20, (int) ($map['etiqueta_ancho_mm'] ?? 60)),
            'alto_mm' => max(8, (int) ($map['etiqueta_alto_mm'] ?? 10)),
            'gap_mm' => max(0, $this->leerMmDecimal($map['etiqueta_gap_mm'] ?? '3')),
            'ala_mm' => max(10, (int) ($map['etiqueta_ala_mm'] ?? 17)),
            'media_mm' => max(0, (int) ($map['etiqueta_media_mm'] ?? 17)),
            'cola_mm' => max(10, (int) ($map['etiqueta_cola_mm'] ?? 26)),
            'alto_cola_mm' => max(3, (int) ($map['etiqueta_alto_cola_mm'] ?? 10)),
            'dpi' => $dpi,
            'offset_x_mm' => (float) ($map['etiqueta_offset_x'] ?? 0),
            'offset_y_mm' => (float) ($map['etiqueta_offset_y'] ?? 0),
            'lang' => strtoupper((string) ($map['etiqueta_lang'] ?? 'IMAGEN')),
            'impresora' => (string) ($map['etiqueta_impresion_nombre_impresora'] ?? ''),
            // Acomodo PNG (modo IMAGEN); valores por defecto = ultimo layout estable.
            'img_shift_bc_mm' => $this->leerConfigFloat($map['etiqueta_img_shift_barcode_mm'] ?? null, 4.0, 0.0, 15.0),
            'img_shift_precio_mm' => $this->leerConfigFloat($map['etiqueta_img_shift_precio_mm'] ?? null, 6.0, -5.0, 15.0),
            'img_margen_izq_barcode_mm' => $this->leerConfigFloat($map['etiqueta_img_margen_izq_barcode_mm'] ?? null, 2.5, 0.0, 8.0),
            'img_margen_der_barcode_mm' => $this->leerConfigFloat($map['etiqueta_img_margen_der_barcode_mm'] ?? null, 1.0, 0.0, 5.0),
            'img_gap_barcode_texto_mm' => $this->leerConfigFloat($map['etiqueta_img_gap_barcode_texto_mm'] ?? null, 0.3, 0.0, 3.0),
            'img_margen_inferior_aux_mm' => $this->leerConfigFloat($map['etiqueta_img_margen_inferior_aux_mm'] ?? null, 1.5, 0.0, 4.0),
            'img_alto_barcode_ratio' => $this->leerConfigFloat($map['etiqueta_img_alto_barcode_ratio'] ?? null, 0.72, 0.35, 0.92),
            'img_tam_aux_pt' => $this->leerConfigInt($map['etiqueta_img_tam_aux_pt'] ?? null, 11, 6, 22),
            'img_tam_precio_pt' => $this->leerConfigInt($map['etiqueta_img_tam_precio_pt'] ?? null, 24, 10, 56),
            'img_precio_baseline_factor' => $this->leerConfigFloat($map['etiqueta_img_precio_baseline_factor'] ?? null, 0.30, 0.10, 0.55),
            'img_tam_variante_pt' => $this->leerConfigInt($map['etiqueta_img_tam_variante_pt'] ?? null, 8, 6, 16),
            'img_margen_inferior_variante_mm' => $this->leerConfigFloat($map['etiqueta_img_margen_inferior_variante_mm'] ?? null, 1.2, 0.5, 3.0),
            'img_precio_con_variante_pt' => $this->leerConfigInt($map['etiqueta_img_precio_con_variante_pt'] ?? null, 20, 10, 40),
        ];
    }

    private function leerConfigFloat(mixed $valor, float $default, float $min, float $max): float
    {
        $texto = str_replace(',', '.', trim((string) ($valor ?? '')));
        if ($texto === '' || !is_numeric($texto)) {
            return $default;
        }
        $v = (float) $texto;

        return min($max, max($min, $v));
    }

    private function leerConfigInt(mixed $valor, int $default, int $min, int $max): int
    {
        if ($valor === null || $valor === '') {
            return $default;
        }
        if (!is_numeric($valor)) {
            return $default;
        }
        $v = (int) round((float) $valor);

        return min($max, max($min, $v));
    }

    private function leerMmDecimal(mixed $valor): float
    {
        $texto = str_replace(',', '.', trim((string) $valor));

        return is_numeric($texto) ? (float) $texto : 0.0;
    }

    public function usaPpla(array $layout): bool
    {
        $lang = strtoupper((string) ($layout['lang'] ?? 'PPLA'));

        return in_array($lang, ['PPLA', 'DPL'], true);
    }

    public function usaImagen(array $layout): bool
    {
        $lang = strtoupper((string) ($layout['lang'] ?? 'PPLA'));

        return in_array($lang, ['IMAGEN', 'IMAGE', 'PNG', 'GDI'], true);
    }

    public function resolverIdsDesdePayload(array $payload): array
    {
        if (!empty($payload['ids_pieza_stock']) && is_array($payload['ids_pieza_stock'])) {
            $ids = [];
            foreach ($payload['ids_pieza_stock'] as $id) {
                $n = (int) $id;
                if ($n > 0) {
                    $ids[] = $n;
                }
            }

            return array_values(array_unique($ids));
        }

        $idPieza = (int) ($payload['id_pieza'] ?? 0);
        $desde = (int) ($payload['desde'] ?? 0);
        $hasta = (int) ($payload['hasta'] ?? 0);
        if ($idPieza <= 0 || $desde <= 0 || $hasta <= 0) {
            return [];
        }

        return $this->stock->resolverIdsRango($idPieza, $desde, $hasta, !empty($payload['solo_disponibles']));
    }

    public function obtenerDatosEtiqueta(int $idPiezaStock): ?array
    {
        require_once __DIR__ . '/../../includes/variantes_stock_helpers.php';
        $usaCatalogo = joyeria_tiene_columnas_variante_catalogo($this->stock->getDb());
        $colsMatriz = $this->stock->tieneColumnasVarianteMatriz()
            ? "ps.variante_talla,
                    ps.variante_color,"
            : '';
        $joinCatalogo = $usaCatalogo ? joyeria_sql_join_variantes_stock('ps') : '';
        $selectCatalogo = $usaCatalogo ? joyeria_sql_select_variantes_stock() . ',' : '';
        $stmt = $this->stock->getDb()->prepare(
            'SELECT ps.id_pieza_stock,
                    ps.codigo_auxiliar,
                    ps.codigo_barras,
                    ps.precio_venta,
                    ps.tipo_codigo,
                    ps.estado,
                    ps.variante_tipo,
                    ps.variante_valor,
                    ' . $colsMatriz . '
                    ' . $selectCatalogo . '
                    p.desc_pieza,
                    p.peso_gr,
                    m.nom_metal
             FROM piezas_stock ps
             INNER JOIN piezas p ON p.id_pieza = ps.id_pieza_FK
             INNER JOIN metales m ON m.id_metal = p.id_metal_FK
             ' . $joinCatalogo . '
             WHERE ps.id_pieza_stock = :id
               AND ps.activo = 1'
        );
        $stmt->bindValue(':id', $idPiezaStock, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        require_once __DIR__ . '/../../includes/variantes_stock_helpers.php';
        $row['texto_variante_etiqueta'] = joyeria_texto_etiqueta_variante($row);

        return $row;
    }

    public function resolverIdsInsumoDesdePayload(array $payload): array
    {
        if (empty($payload['ids_insumo']) || !is_array($payload['ids_insumo'])) {
            return [];
        }

        $ids = [];
        foreach ($payload['ids_insumo'] as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $ids[] = $n;
            }
        }

        return $ids;
    }

    public function payloadEsEtiquetaInsumo(array $payload): bool
    {
        return $this->resolverIdsInsumoDesdePayload($payload) !== [];
    }

    public function obtenerDatosEtiquetaInsumo(int $idInsumo): ?array
    {
        $stmt = $this->stock->getDb()->prepare(
            'SELECT i.id_insumo,
                    i.nombre,
                    i.sku_codigo,
                    i.precio_venta_sugerido,
                    c.nombre AS categoria_nombre
             FROM insumos i
             LEFT JOIN insumo_categorias c ON c.id_categoria = i.id_categoria_FK
             WHERE i.id_insumo = :id
               AND i.activo = 1
             LIMIT 1'
        );
        $stmt->bindValue(':id', $idInsumo, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Adapta fila de insumo al formato usado por generadores PPLA/ZPL de piezas.
     */
    public function mapearInsumoADatosEtiqueta(array $row): array
    {
        $sku = trim((string) ($row['sku_codigo'] ?? ''));
        $pvp = isset($row['precio_venta_sugerido']) ? (float) $row['precio_venta_sugerido'] : 0.0;
        if ($pvp <= 0) {
            $pvp = 0.01;
        }

        return [
            'desc_pieza' => (string) ($row['nombre'] ?? ''),
            'nom_metal' => (string) ($row['categoria_nombre'] ?? ''),
            'precio_venta' => $pvp,
            'codigo_auxiliar' => $sku,
            'codigo_barras' => $sku,
            'tipo_codigo' => 'CODE128',
        ];
    }

    /** Genera comandos RAW para etiquetas de insumos (mismo layout que piezas). */
    public function generarZplLoteInsumos(array $idsInsumo): string
    {
        $layout = $this->leerLayout();
        $cuerpos = [];
        $zplPartes = [];
        $pngBase64s = [];

        foreach ($idsInsumo as $id) {
            $row = $this->obtenerDatosEtiquetaInsumo((int) $id);
            if ($row === null) {
                continue;
            }
            $sku = trim((string) ($row['sku_codigo'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $datos = $this->mapearInsumoADatosEtiqueta($row);

            if ($this->usaImagen($layout)) {
                $png = $this->generarImagenEtiqueta($datos, $layout);
                if ($png !== '') {
                    $pngBase64s[] = base64_encode($png);
                }
            } elseif ($this->usaPpla($layout)) {
                $cuerpos[] = (int) $layout['alto_mm'] <= 15
                    ? $this->lineasContenidoMariposa($datos, $layout)
                    : $this->lineasContenidoPplaGrande($datos, $layout);
            } else {
                $zplPartes[] = $this->generarZplUna($datos, $layout);
            }
        }

        if ($pngBase64s !== []) {
            return json_encode([
                'tipo' => 'imagen',
                'formato' => 'png',
                'ancho_mm' => (float) $layout['ancho_mm'],
                'alto_mm' => (float) $layout['alto_mm'],
                'dpi' => (int) $layout['dpi'],
                'etiquetas' => $pngBase64s,
            ], JSON_UNESCAPED_SLASHES);
        }

        if ($cuerpos !== []) {
            return $this->ensamblarLotePpla($cuerpos);
        }

        return $zplPartes === [] ? '' : implode("\n", $zplPartes);
    }

    /** Genera comandos RAW segun etiqueta_lang (PPLA/DPL, ZPL o IMAGEN). */
    public function generarZplLote(array $idsPiezaStock): string
    {
        $layout = $this->leerLayout();
        $cuerpos = [];
        $zplPartes = [];
        $pngBase64s = [];

        foreach ($idsPiezaStock as $id) {
            $datos = $this->obtenerDatosEtiqueta((int) $id);
            if ($datos === null) {
                continue;
            }
            if ($this->usaImagen($layout)) {
                $png = $this->generarImagenEtiqueta($datos, $layout);
                if ($png !== '') {
                    $pngBase64s[] = base64_encode($png);
                }
            } elseif ($this->usaPpla($layout)) {
                $cuerpos[] = (int) $layout['alto_mm'] <= 15
                    ? $this->lineasContenidoMariposa($datos, $layout)
                    : $this->lineasContenidoPplaGrande($datos, $layout);
            } else {
                $zplPartes[] = $this->generarZplUna($datos, $layout);
            }
        }

        if ($pngBase64s !== []) {
            // Empaquetamos las imagenes en un JSON. El agente las detecta por
            // el byte mágico '{' al inicio y las envía al driver una por una.
            return json_encode([
                'tipo' => 'imagen',
                'formato' => 'png',
                'ancho_mm' => (float) $layout['ancho_mm'],
                'alto_mm' => (float) $layout['alto_mm'],
                'dpi' => (int) $layout['dpi'],
                'etiquetas' => $pngBase64s,
            ], JSON_UNESCAPED_SLASHES);
        }

        if ($cuerpos !== []) {
            return $this->ensamblarLotePpla($cuerpos);
        }

        return $zplPartes === [] ? '' : implode("\n", $zplPartes);
    }

    /**
     * Texto PPLA modo interno Argox (mismo formato que test.py / test.php).
     * Prefijo 15 digitos: orientacion, fuente, mult H/V, subfuente, Y(4), X(4).
     */
    private function lineaTextoPpla1211(
        int $x,
        int $y,
        string $texto,
        int $orient = 1,
        int $font = 2,
        int $hMult = 1,
        int $vMult = 1
    ): string {
        $texto = $this->pplaEscape($texto);

        return sprintf(
            '%d%d%d%d%03d%04d%04d%s',
            $orient,
            $font,
            $hMult,
            $vMult,
            0,
            max(0, min(9999, $y)),
            max(0, min(9999, $x)),
            $texto
        );
    }

    public function generarPplaUna(array $datos, ?array $layout = null): string
    {
        $layout = $layout ?? $this->leerLayout();
        if ((int) $layout['alto_mm'] <= 15) {
            return $this->generarPplaMariposa($datos, $layout);
        }

        return $this->ensamblarLotePpla([$this->lineasContenidoPplaGrande($datos, $layout)]);
    }

    /**
     * Driver: 60 x 10 mm, gap 3 mm. Cuerpo = 17 + 17 + 26 mm (solo posiciones X/Y en RAW).
     */
    private function geometriaMariposa(array $layout): array
    {
        $dpi = (int) $layout['dpi'];
        $altoMm = (float) $layout['alto_mm'];
        $alaMm = (float) ($layout['ala_mm'] ?? 17);
        $mediaMm = (float) ($layout['media_mm'] ?? 17);
        $ox = (float) ($layout['offset_x_mm'] ?? 0);
        $oy = (float) ($layout['offset_y_mm'] ?? 0);

        // Layout horizontal: pad izq 0-17mm | pad der 17-34mm | cola 34-60mm.
        $xCabezaMm = 1.5 + $ox;
        // Argox PPLA aplica un offset fisico extra (~4-6 mm) en la X del comando
        // de barras vs. la X del texto. Restamos margen para que termine quedando
        // alineado al inicio del segundo pad (~17 mm fisicos).
        $xBarcodeMm = max(0.5, $alaMm - 4.0) + $ox;
        $xColaMm = $alaMm + $mediaMm + 0.5 + $ox;
        // Precio en font 1 = ~2 mm alto, centrado en los 10 mm.
        $yTextoMm = max(0.5, ($altoMm - 2.2) / 2) + $oy;
        $alturaBarcodeMm = 4.0;
        $yBarcodeMm = max(0.5, ($altoMm - $alturaBarcodeMm) / 2) + $oy;

        // QR centrado en el pad medio. QR v1 (cell=3): ~7.8 mm cuadrados.
        // Compensamos el mismo offset fisico que el barcode (~4 mm).
        $qrLadoMm = 8.0;
        $xQrMm = max(0.5, $alaMm + ($mediaMm - $qrLadoMm) / 2 - 4.0) + $ox;
        $yQrMm = max(0.5, ($altoMm - $qrLadoMm) / 2) + $oy;

        return [
            'xPrecio' => $this->mmADots($xCabezaMm, $dpi),
            'yPrecio' => $this->mmADots($yTextoMm, $dpi),
            'xBarcode' => $this->mmADots($xBarcodeMm, $dpi),
            'yBarcode' => $this->mmADots($yBarcodeMm, $dpi),
            'xQr' => $this->mmADots($xQrMm, $dpi),
            'yQr' => $this->mmADots($yQrMm, $dpi),
            'xAux' => $this->mmADots($xColaMm, $dpi),
            'yAux' => $this->mmADots($yTextoMm, $dpi),
            'barcodeAlto' => $this->mmADots($alturaBarcodeMm, $dpi),
            'anchoBarcodeMm' => max(8, $mediaMm - 1.5),
        ];
    }

    /** Solo lineas de contenido (sin q/Q ni STX); el lote las ensambla. */
    private function lineasContenidoMariposa(array $datos, array $layout): array
    {
        $geo = $this->geometriaMariposa($layout);

        $precio = $this->pplaEscape('$' . number_format((float) ($datos['precio_venta'] ?? 0), 2, '.', ''));
        $aux = $this->pplaEscape(mb_substr((string) ($datos['codigo_auxiliar'] ?? ''), 0, 8));
        $codigoCrudo = trim((string) ($datos['codigo_barras'] ?? ''));
        $tipo = strtoupper((string) ($datos['tipo_codigo'] ?? 'CODE128'));

        $lineas = [
            $this->lineaTextoPpla1211($geo['xPrecio'], $geo['yPrecio'], $precio, 1, 1, 1, 1),
        ];

        if ($codigoCrudo !== '') {
            $codigoBarcode = $this->ajustarCodigoBarrasMariposa($codigoCrudo);
            $lineas[] = $this->lineaCodigoBarrasPpla(
                'CODE128',
                $codigoBarcode,
                $geo['xBarcode'],
                $geo['yBarcode'],
                $geo['barcodeAlto'],
                false,
                1,
                2
            );
        }

        if ($aux !== '') {
            $lineas[] = $this->lineaTextoPpla1211(
                $geo['xAux'],
                $geo['yAux'],
                $aux,
                1,
                1,
                1,
                1
            );
        }

        return $lineas;
    }

    /**
     * Comando QR Code PPLA / DPL.
     *   Formato: <orient>W1d<c><d><eee><ffff><gggg><data>
     *     - orient: 1=0, 2=90, 3=180, 4=270
     *     - W1   : identificador fijo (QR)
     *     - d    : auto-format (data directo, sin separadores)
     *     - c, d : module size horizontal/vertical (1-9). Deben ser iguales (cell cuadrada).
     *     - eee  : 3 digitos sin efecto, requeridos
     *     - ffff : Y (4 digitos)
     *     - gggg : X (4 digitos)
     *     - data : codigo completo
     *
     *   Cell size en dots a 203 dpi:
     *     1=0.125mm  2=0.25mm  3=0.37mm  4=0.5mm  5=0.62mm
     *   QR v1 (21 cells) con cell=3: 7.8 mm cuadrados -> cabe en pad 17x10 mm.
     */
    private function lineaQrPpla(string $codigo, int $x, int $y, int $cellSize = 3): string
    {
        $codigo = $this->pplaEscape(trim($codigo));
        if ($codigo === '') {
            return '';
        }
        $cellSize = max(1, min(9, $cellSize));

        return sprintf(
            '1W1d%d%d000%04d%04d%s',
            $cellSize,
            $cellSize,
            max(0, min(9999, $y)),
            max(0, min(9999, $x)),
            $codigo
        );
    }

    /**
     * Recorta el codigo para que el CODE128 quepa en los 17 mm del pad derecho.
     * Aun con narrow=1, Argox OS-2140 redondea el modulo a ~2 dots fisicos.
     * Con 4 chars CODE128: 4*11 + 13 = 57 modulos x 2 dots ~= 14 mm. Cabe.
     * Toma los ultimos 4 caracteres (los mas identificadores del codigo real).
     */
    private function ajustarCodigoBarrasMariposa(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return '';
        }

        $soloDigitos = preg_replace('/\D+/', '', $codigo) ?? '';
        if ($soloDigitos !== '' && strlen($soloDigitos) >= mb_strlen($codigo) - 1) {
            return substr($soloDigitos, -4);
        }

        return mb_substr($codigo, -4);
    }

    private function lineasContenidoPplaGrande(array $datos, array $layout): array
    {
        $dpi = (int) $layout['dpi'];
        $ox = $this->mmADots((float) $layout['offset_x_mm'], $dpi);
        $oy = $this->mmADots((float) $layout['offset_y_mm'], $dpi);

        $desc = $this->pplaEscape($this->truncar((string) ($datos['desc_pieza'] ?? ''), 28));
        $metal = $this->pplaEscape($this->truncar((string) ($datos['nom_metal'] ?? ''), 24));
        $aux = $this->pplaEscape((string) ($datos['codigo_auxiliar'] ?? ''));
        $precio = $this->pplaEscape('$' . number_format((float) ($datos['precio_venta'] ?? 0), 2, '.', ''));
        $codigo = $this->pplaEscape((string) ($datos['codigo_barras'] ?? ''));
        $tipo = strtoupper((string) ($datos['tipo_codigo'] ?? 'EAN13'));

        $x = 30 + $ox;
        $lineas = [
            $this->lineaTextoPpla1211($x, 20 + $oy, $desc),
            $this->lineaTextoPpla1211($x, 48 + $oy, $metal),
            $this->lineaTextoPpla1211($x, 72 + $oy, $precio, 1, 2, 1, 2),
        ];

        if ($codigo !== '') {
            $lineas[] = $this->lineaCodigoBarrasPpla($tipo, $codigo, $x, 105 + $oy, 65, false);
        }

        if ($aux !== '') {
            $lineas[] = $this->lineaTextoPpla1211($x, 185 + $oy, $aux, 1, 1, 1, 1);
        }

        return $lineas;
    }

    /** Layout mariposa joyeria (driver 60 x 10 mm). */
    private function generarPplaMariposa(array $datos, array $layout): string
    {
        return $this->ensamblarLotePpla([$this->lineasContenidoMariposa($datos, $layout)]);
    }

    /**
     * Cabecera de sistema PPLA minima (1 vez por lote).
     * Solo activa el sensor de espacios (gap-mode); el driver Argox controla
     * el resto (tamano, gap, densidad) desde Windows.
     */
    private function cabeceraSistemaPpla(array $layout): string
    {
        return "\x02e\r";
    }

    /**
     * Lote PPLA: cabecera (1 vez) + bloque L...Q0001 E por etiqueta.
     * Cada \x02L...E\r imprime exactamente una etiqueta y el firmware
     * autoavanza al siguiente gap usando su sensor.
     */
    private function ensamblarLotePpla(array $listaContenidos): string
    {
        $layout = $this->leerLayout();
        $resultado = $this->cabeceraSistemaPpla($layout);

        foreach ($listaContenidos as $lineas) {
            $lineas = array_values(array_filter($lineas, static fn ($l) => $l !== ''));
            if ($lineas === []) {
                continue;
            }

            $bloque = "\x02L\r";
            $bloque .= "D22\r";
            $bloque .= implode("\r", $lineas) . "\r";
            $bloque .= "Q0001\r";
            $bloque .= "E\r";

            $resultado .= $bloque;
        }

        return $resultado;
    }

    public function generarZplUna(array $datos, ?array $layout = null): string
    {
        $layout = $layout ?? $this->leerLayout();
        $dotsPerMm = $layout['dpi'] / 25.4;
        $ox = (int) round($layout['offset_x_mm'] * $dotsPerMm);
        $oy = (int) round($layout['offset_y_mm'] * $dotsPerMm);
        $pw = (int) round($layout['ancho_mm'] * $dotsPerMm);
        $ll = (int) round($layout['alto_mm'] * $dotsPerMm);

        $desc = $this->zplEscape($this->truncar((string) ($datos['desc_pieza'] ?? ''), 28));
        $metal = $this->zplEscape($this->truncar((string) ($datos['nom_metal'] ?? ''), 24));
        $aux = $this->zplEscape((string) ($datos['codigo_auxiliar'] ?? ''));
        $precio = '$' . number_format((float) ($datos['precio_venta'] ?? 0), 2, '.', '');
        $codigo = $this->zplEscape((string) ($datos['codigo_barras'] ?? ''));
        $tipo = strtoupper((string) ($datos['tipo_codigo'] ?? 'EAN13'));

        $barcodeBlock = $this->bloqueCodigoBarrasZpl($tipo, $codigo, 50 + $ox, 130 + $oy);

        return "^XA\n"
            . '^PW' . $pw . "\n"
            . '^LL' . $ll . "\n"
            . '^FO' . (50 + $ox) . ',' . (20 + $oy) . "^A0N,24,24^FD{$desc}^FS\n"
            . '^FO' . (50 + $ox) . ',' . (55 + $oy) . "^A0N,20,20^FD{$metal}^FS\n"
            . '^FO' . (50 + $ox) . ',' . (85 + $oy) . "^A0N,28,28^FD{$precio}^FS\n"
            . $barcodeBlock
            . '^FO' . (50 + $ox) . ',' . (215 + $oy) . "^A0N,18,18^FD{$aux}^FS\n"
            . "^XZ";
    }

    /**
     * Codigo de barras PPLA / DPL.
     * Formato oficial DPL: <orient><type><WIDE><NARROW><height(3)><Y(4)><X(4)>data
     *   - c (WIDE)   = wide-bar width / multiplicador horizontal (1-9, A-Z, a-z)
     *   - d (NARROW) = narrow-bar width / ratio denominator    (1-9, A-Z, a-z)
     *   tipo: F=EAN13 (con HR), f=EAN13 (sin HR), G/g=EAN8, E/e=CODE128, A/a=CODE39
     *
     * BUG previo: enviabamos narrow ANTES de wide, asi que el firmware tomaba
     * wide=2 como narrow=2 y duplicaba el ancho del barcode.
     */
    private function lineaCodigoBarrasPpla(
        string $tipo,
        string $codigo,
        int $x,
        int $y,
        int $alturaDots = 40,
        bool $textoHumano = false,
        int $narrow = 1,
        int $wide = 2
    ): string {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return '';
        }

        $tipo = strtoupper($tipo);
        if ($tipo === 'EAN13' && !preg_match('/^\d{12,13}$/', $codigo)) {
            $tipo = 'CODE128';
        }
        if ($tipo === 'EAN8' && !preg_match('/^\d{7,8}$/', $codigo)) {
            $tipo = 'CODE128';
        }

        $tipoChar = $this->tipoBarrasPpla($tipo, $textoHumano);

        $narrow = max(1, min(24, $narrow));
        $wide = max(2, min(24, $wide));
        // wide debe ser >= narrow para un ratio valido (1:2, 1:3, 2:3, etc.).
        if ($wide < $narrow) {
            $wide = $narrow + 1;
        }

        $narrowChar = $narrow <= 9 ? (string) $narrow : chr(ord('A') + ($narrow - 10));
        $wideChar = $wide <= 9 ? (string) $wide : chr(ord('A') + ($wide - 10));

        // Orden correcto DPL: WIDE primero, NARROW despues.
        return sprintf(
            '1%s%s%s%03d%04d%04d%s',
            $tipoChar,
            $wideChar,
            $narrowChar,
            max(10, min(999, $alturaDots)),
            max(0, min(9999, $y)),
            max(0, min(9999, $x)),
            $this->pplaEscape($codigo)
        );
    }

    private function tipoBarrasPpla(string $tipo, bool $textoHumano): string
    {
        return match (strtoupper($tipo)) {
            'EAN13' => $textoHumano ? 'F' : 'f',
            'EAN8' => $textoHumano ? 'G' : 'g',
            'CODE128' => $textoHumano ? 'E' : 'e',
            'CODE39' => $textoHumano ? 'A' : 'a',
            default => $textoHumano ? 'E' : 'e',
        };
    }

    private function bloqueCodigoBarrasZpl(string $tipo, string $codigo, int $x, int $y): string
    {
        if ($codigo === '') {
            return '';
        }

        if ($tipo === 'QR') {
            return '^FO' . $x . ',' . $y . "^BQN,2,4^FDQA,{$codigo}^FS\n";
        }

        if ($tipo === 'CODE128') {
            return '^FO' . $x . ',' . $y . "^BY2,2,70^BCN,70,Y,N,N^FD{$codigo}^FS\n";
        }

        return '^FO' . $x . ',' . $y . "^BY2,2,70^BEN,70,Y,N^FD{$codigo}^FS\n";
    }

    private function mmADots(float $mm, int $dpi): int
    {
        return (int) round($mm * $dpi / 25.4);
    }

    private function dotsAMm(int $dots, int $dpi): float
    {
        if ($dpi <= 0) {
            return 0.0;
        }
        return ($dots * 25.4) / $dpi;
    }

    // =================================================================
    //  GENERACION POR IMAGEN (estilo Gemarun)
    //  Renderiza la etiqueta como PNG. El agente la imprime con GDI y
    //  el driver Argox se encarga de la conversion a PPLA + calibracion.
    // =================================================================

    /**
     * Genera un PNG con el contenido de UNA etiqueta a tamano real (en dots).
     * Devuelve el binario PNG listo para base64.
     *
     * IMPORTANTE: la cinta esta troquelada en 3 etiquetas:
     *   [PAD IZQ 17mm] [PAD MEDIO 17mm] [COLA 26mm]
     * La cola es la parte adhesiva que se enrolla y NO SE VE en la pieza.
     * Todo el contenido visible debe ir en los 2 pads. La cola queda vacia.
     *
     * Layout (referencia imagen joyeria):
     *   +-----------------+-----------------+--------------------+
     *   | [BARCODE]       |                 |                    |
     *   | 6597/002        |    $ 1,080      |    (vacio)         |
     *   +-----------------+-----------------+--------------------+
     *      pad izq (17mm)    pad medio (17mm)    cola (26mm)
     */
    public function generarImagenEtiqueta(array $datos, array $layout): string
    {
        $dpi = (int) ($layout['dpi'] ?? 203);
        $widthPx = $this->mmADots((float) $layout['ancho_mm'], $dpi);
        $heightPx = $this->mmADots((float) $layout['alto_mm'], $dpi);
        $alaMm = (float) ($layout['ala_mm'] ?? 17);
        $mediaMm = (float) ($layout['media_mm'] ?? 17);
        $ox = (float) ($layout['offset_x_mm'] ?? 0);
        $oy = (float) ($layout['offset_y_mm'] ?? 0);
        $altoMm = (float) $layout['alto_mm'];

        $img = imagecreatetruecolor($widthPx, $heightPx);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $widthPx, $heightPx, $white);

        $precio = '$ ' . number_format((float) ($datos['precio_venta'] ?? 0), 0, '.', ',');
        $aux = trim((string) ($datos['codigo_auxiliar'] ?? ''));
        $codigoBarras = trim((string) ($datos['codigo_barras'] ?? ''));
        // El barcode usa codigo_auxiliar (mas corto y unico). Si por alguna
        // razon no hay codigo_auxiliar, caemos a codigo_barras como respaldo.
        $codigo = $aux !== '' ? $aux : $codigoBarras;

        $fuente = $this->fuenteTtfDisponible();

        // ------------------ PAD IZQUIERDO: barcode + codigo ------------------
        $shiftBcMm = (float) ($layout['img_shift_bc_mm'] ?? 4.0);
        $shiftPrecioMm = (float) ($layout['img_shift_precio_mm'] ?? 6.0);
        $margenIzqBaseMm = (float) ($layout['img_margen_izq_barcode_mm'] ?? 2.5);
        $margenDerMm = (float) ($layout['img_margen_der_barcode_mm'] ?? 1.0);
        $gapMm = (float) ($layout['img_gap_barcode_texto_mm'] ?? 0.3);
        $margenInferiorMm = (float) ($layout['img_margen_inferior_aux_mm'] ?? 1.5);
        $bcAltoRatio = (float) ($layout['img_alto_barcode_ratio'] ?? 0.72);
        $sizeCod = (int) ($layout['img_tam_aux_pt'] ?? 11);

        if ($codigo !== '') {
            $margenIzqMm = $margenIzqBaseMm + $shiftBcMm;

            list(, $codAltoPx) = $this->medirTexto('0', $sizeCod, $fuente);
            $codAltoMm = $this->dotsAMm($codAltoPx, $dpi);

            // Anclamos primero el TEXTO al fondo y construimos el barcode
            // hacia arriba: asi todo el bloque "baja" al pie del pad izq.
            $textoBaselineMm = $altoMm - $margenInferiorMm + $oy;
            $textoTopMm = $textoBaselineMm - $codAltoMm;

            $bcAltoMm = $altoMm * $bcAltoRatio;
            $bcEndMm = $textoTopMm - $gapMm;
            $bcYMm = max(0.2, $bcEndMm - $bcAltoMm);
            // Ajustar el alto efectivo si el max(0.2,...) recorto la base.
            $bcAltoMm = $bcEndMm - $bcYMm;
            $bcXMm = $margenIzqMm + $ox;
            $bcAnchoMm = $alaMm - $margenIzqMm - $margenDerMm + $shiftBcMm;

            $bcXPx = $this->mmADots($bcXMm, $dpi);
            $bcYPx = $this->mmADots($bcYMm, $dpi);
            $bcAnchoPx = $this->mmADots($bcAnchoMm, $dpi);
            $bcAltoPx = $this->mmADots($bcAltoMm, $dpi);

            $this->dibujarBarcodeCode128($img, $codigo, $bcXPx, $bcYPx, $bcAnchoPx, $bcAltoPx);

            // Texto humano-legible: el mismo codigo_auxiliar que va en el barcode,
            // para que coincida con lo que escaneara la camara.
            $textoCodigo = $codigo !== '' ? $codigo : '';
            if ($textoCodigo !== '') {
                list($codAnchoPx, ) = $this->medirTexto($textoCodigo, $sizeCod, $fuente);
                $padIzqCentroMm = $alaMm / 2.0 + $shiftBcMm;
                $xCodPx = $this->mmADots($padIzqCentroMm + $ox, $dpi) - (int) round($codAnchoPx / 2);
                $yCodPx = $this->mmADots($textoBaselineMm, $dpi);
                $this->dibujarTexto($img, $textoCodigo, $xCodPx, $yCodPx, $sizeCod, $fuente, $black);
            }
        }

        // ------------------ PAD MEDIO: precio (+ variante talla/color si aplica) ------------------
        $textoVariante = trim((string) ($datos['texto_variante_etiqueta'] ?? ''));
        $padMedioInicioMm = $alaMm;
        $padMedioCentroMm = $padMedioInicioMm + ($mediaMm / 2.0) + $shiftPrecioMm;

        if ($textoVariante === '') {
            $sizePrecio = (int) ($layout['img_tam_precio_pt'] ?? 24);
            $precioBaselineFactor = (float) ($layout['img_precio_baseline_factor'] ?? 0.30);
            list($txtAnchoPx, $txtAltoPx) = $this->medirTexto($precio, $sizePrecio, $fuente);
            $xPrecioPx = $this->mmADots($padMedioCentroMm + $ox, $dpi) - (int) round($txtAnchoPx / 2);
            $yPrecioPx = $this->mmADots($altoMm / 2 + $oy, $dpi) + (int) round($txtAltoPx * $precioBaselineFactor);
            $this->dibujarTexto($img, $precio, $xPrecioPx, $yPrecioPx, $sizePrecio, $fuente, $black);
        } else {
            $sizePrecio = (int) ($layout['img_precio_con_variante_pt'] ?? 20);
            $sizeVariante = (int) ($layout['img_tam_variante_pt'] ?? 8);
            $margenInferiorVarianteMm = (float) ($layout['img_margen_inferior_variante_mm'] ?? 1.2);
            $fuenteRegular = $this->fuenteTtfRegular();

            list($precioAnchoPx, $precioAltoPx) = $this->medirTexto($precio, $sizePrecio, $fuente);
            $xPrecioPx = $this->mmADots($padMedioCentroMm + $ox, $dpi) - (int) round($precioAnchoPx / 2);
            $yPrecioBaselineMm = max(0.8, $altoMm * 0.35) + $oy;
            $yPrecioPx = $this->mmADots($yPrecioBaselineMm, $dpi) + (int) round($precioAltoPx * 0.35);
            $this->dibujarTexto($img, $precio, $xPrecioPx, $yPrecioPx, $sizePrecio, $fuente, $black);

            list($varAnchoPx, ) = $this->medirTexto($textoVariante, $sizeVariante, $fuenteRegular);
            $xVarPx = $this->mmADots($padMedioCentroMm + $ox, $dpi) - (int) round($varAnchoPx / 2);
            $yVarBaselineMm = $altoMm - $margenInferiorVarianteMm + $oy;
            $yVarPx = $this->mmADots($yVarBaselineMm, $dpi);
            $this->dibujarTexto($img, $textoVariante, $xVarPx, $yVarPx, $sizeVariante, $fuenteRegular, $black);
        }

        // ------------------ COLA: vacia a proposito ------------------

        ob_start();
        imagepng($img, null, 9);
        $bin = (string) ob_get_clean();
        imagedestroy($img);

        return $bin;
    }

    /**
     * Mide el ancho y alto en pixeles de un texto. Si hay TTF disponible usa
     * imagettfbbox; si no, calcula con bitmap GD (5px x 8px por char aprox).
     *
     * @return array{0:int,1:int} [ancho, alto]
     */
    private function medirTexto(string $texto, int $size, ?string $ttf): array
    {
        if ($ttf !== null && function_exists('imagettfbbox')) {
            $bbox = imagettfbbox($size, 0, $ttf, $texto);
            if (is_array($bbox)) {
                $ancho = abs($bbox[2] - $bbox[0]);
                $alto = abs($bbox[7] - $bbox[1]);
                return [(int) $ancho, (int) $alto];
            }
        }
        $ancho = strlen($texto) * imagefontwidth(5);
        $alto = imagefontheight(5);
        return [$ancho, $alto];
    }

    /**
     * Da formato corto al codigo de barras (ultimos N chars con "/" si es largo).
     * Ejemplo: "JOYERIA0006597002" -> "6597/002"
     */
    private function formatearCodigoCorto(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return '';
        }
        $soloDigitos = preg_replace('/\D+/', '', $codigo) ?? '';
        if (strlen($soloDigitos) >= 7) {
            $ult = substr($soloDigitos, -7);
            return substr($ult, 0, 4) . '/' . substr($ult, 4);
        }
        return mb_substr($codigo, -8);
    }

    private function fuenteTtfDisponible(): ?string
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache === '' ? null : $cache;
        }
        $candidatos = [
            // Linux / Docker (Debian/Ubuntu) - prioridad alta porque el
            // backend PHP corre dentro del contenedor.
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            // macOS
            '/Library/Fonts/Arial Bold.ttf',
            '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
            // Windows (cuando PHP corre nativo).
            'C:/Windows/Fonts/arialbd.ttf',
            'C:/Windows/Fonts/arial.ttf',
            'C:/Windows/Fonts/consolab.ttf',
            // Fuente local empacada con el proyecto (override manual).
            __DIR__ . '/../../assets/fonts/arialbd.ttf',
            __DIR__ . '/../../assets/fonts/DejaVuSans-Bold.ttf',
        ];
        foreach ($candidatos as $f) {
            if (is_file($f)) {
                $cache = $f;
                return $f;
            }
        }
        $cache = '';
        return null;
    }

    private function fuenteTtfRegular(): ?string
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache === '' ? null : $cache;
        }
        $candidatos = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/Library/Fonts/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            'C:/Windows/Fonts/arial.ttf',
            __DIR__ . '/../../assets/fonts/DejaVuSans.ttf',
            __DIR__ . '/../../assets/fonts/arial.ttf',
        ];
        foreach ($candidatos as $f) {
            if (is_file($f)) {
                $cache = $f;
                return $f;
            }
        }
        $cache = '';
        return $this->fuenteTtfDisponible();
    }

    private function dibujarTexto($img, string $texto, int $x, int $y, int $size, ?string $ttf, int $color): void
    {
        if ($ttf !== null && function_exists('imagettftext')) {
            imagettftext($img, $size, 0, $x, $y, $color, $ttf, $texto);
            return;
        }
        // Fallback fuente bitmap GD (max=5).
        imagestring($img, 5, $x, max(0, $y - 16), $texto, $color);
    }

    /**
     * Dibuja CODE128 sin escalar (preservando nitidez de las barras).
     * Calcula el widthFactor entero maximo (1..5) que hace que el barcode
     * COMPLETO quepa en el pad.
     *
     * IMPORTANTE: el codigo nunca se trunca. Si ni siquiera con widthFactor=1
     * cabe, se imprime con factor=1 dejando que sobresalga ligeramente del
     * pad: es preferible un barcode que se sale antes que generar un codigo
     * falso que no se podra escanear contra la BD (codigo_barras).
     */
    private function dibujarBarcodeCode128($img, string $codigo, int $padXPx, int $padYPx, int $padAnchoPx, int $padAltoPx): void
    {
        if (!class_exists(BarcodeGeneratorPNG::class) || $padAnchoPx <= 10 || $padAltoPx <= 5 || $codigo === '') {
            return;
        }

        $gen = new BarcodeGeneratorPNG();
        $maxAlto = max(20, $padAltoPx);

        $barcodePng = null;
        $bcW = 0;
        $bcH = 0;

        foreach ([3, 2, 1] as $factor) {
            try {
                $png = $gen->getBarcode($codigo, $gen::TYPE_CODE_128, $factor, $maxAlto);
            } catch (\Throwable $e) {
                return;
            }
            $tmp = @imagecreatefromstring($png);
            if ($tmp === false) {
                continue;
            }
            $w = imagesx($tmp);
            $h = imagesy($tmp);

            // Aceptamos el mas grande que quepa en el pad.
            if ($w > 0 && $w <= $padAnchoPx) {
                if ($barcodePng !== null) {
                    imagedestroy($barcodePng);
                }
                $barcodePng = $tmp;
                $bcW = $w;
                $bcH = $h;
                break;
            }

            // No cabe con este factor; lo guardamos como respaldo por si
            // ninguno cabe (preferimos factor=1 que es el mas chico).
            if ($factor === 1 && $barcodePng === null) {
                $barcodePng = $tmp;
                $bcW = $w;
                $bcH = $h;
            } else {
                imagedestroy($tmp);
            }
        }

        if ($barcodePng === null || $bcW <= 0) {
            return;
        }

        // Centrar dentro del pad. Si bcW > padAnchoPx (codigo larguisimo),
        // se sale simetricamente para ambos lados pero el codigo queda intacto.
        $offX = $padXPx + (int) floor(($padAnchoPx - $bcW) / 2);
        $offY = $padYPx + (int) floor(($padAltoPx - $bcH) / 2);
        imagecopy($img, $barcodePng, $offX, $offY, 0, 0, $bcW, $bcH);
        imagedestroy($barcodePng);
    }

    private function pplaEscape(string $texto): string
    {
        return str_replace(['"', "\r", "\n"], ['', ' ', ' '], $texto);
    }

    private function zplEscape(string $texto): string
    {
        return str_replace(['^', '~', '\\'], [' ', ' ', '/'], $texto);
    }

    private function truncar(string $texto, int $max): string
    {
        if (mb_strlen($texto) <= $max) {
            return $texto;
        }

        return mb_substr($texto, 0, $max - 1) . '…';
    }
}
