<?php

class TicketEscPosBuilder
{
    private int $width;
    private int $marginDots;

    public function __construct(int $width = 38, int $marginDots = 40)
    {
        $this->width = max(28, min(48, $width));
        $this->marginDots = max(0, min(255, $marginDots));
    }

    public function build(array $ticket): string
    {
        $out = $this->cmdInit();
        $out .= $this->cmdSetLeftMargin($this->marginDots);

        $feedInicio = (int) ($ticket['feed_inicio_lineas'] ?? 1);
        if ($feedInicio > 0) {
            $out .= $this->cmdPrintAndFeed(min(10, $feedInicio));
        }

        $out .= $this->cmdAlignCenter();

        $nombre = trim((string) ($ticket['nombre_comercial'] ?? ''));
        if ($nombre !== '') {
            $out .= $this->cmdBold(true);
            foreach ($this->wrapText($nombre) as $parte) {
                $out .= $this->text($parte) . "\n";
            }
            $out .= $this->cmdBold(false);
        }

        $horario = trim((string) ($ticket['horario'] ?? ''));
        if ($horario !== '') {
            foreach ($this->wrapText($horario) as $parte) {
                $out .= $this->text($parte) . "\n";
            }
        }

        $out .= $this->text(str_repeat('-', $this->width)) . "\n";

        $docTit = trim((string) ($ticket['documento_titulo'] ?? ''));
        if ($docTit !== '') {
            $out .= $this->cmdAlignCenter();
            $out .= $this->cmdBold(true);
            $out .= $this->text($docTit) . "\n";
            $out .= $this->cmdBold(false);
            $out .= $this->text(str_repeat('-', $this->width)) . "\n";
        }

        $leyenda = trim((string) ($ticket['leyenda_folio'] ?? 'Folio'));
        $folio = (int) ($ticket['id_venta'] ?? 0);
        $out .= $this->text($leyenda) . "\n";
        $out .= $this->cmdBold(true);
        $out .= $this->text('#' . $folio) . "\n";
        $out .= $this->cmdBold(false);

        $fecha = trim((string) ($ticket['fecha_venta'] ?? ''));
        if ($fecha !== '') {
            $out .= $this->text($fecha) . "\n";
        }

        if (!empty($ticket['mostrar_empleado']) && !empty($ticket['empleado_numero'])) {
            $out .= $this->text('Atendio: ' . (string) $ticket['empleado_numero']) . "\n";
        }

        if (!empty($ticket['cliente_nombre'])) {
            $out .= $this->text('Cliente: ' . (string) $ticket['cliente_nombre']) . "\n";
        }

        $out .= $this->text(str_repeat('-', $this->width)) . "\n";
        $out .= $this->cmdAlignLeft();
        $out .= $this->lineItemColumns('Descripcion', 'Importe') . "\n";
        $out .= $this->text(str_repeat('-', $this->width)) . "\n";

        foreach ($ticket['lineas'] ?? [] as $linea) {
            if (!is_array($linea)) {
                continue;
            }
            $desc = trim((string) ($linea['descripcion'] ?? ''));
            $codigo = trim((string) ($linea['codigo'] ?? ''));
            $cantidad = (float) ($linea['cantidad'] ?? 1);
            $importe = (float) ($linea['subtotal'] ?? 0);
            $precio = (float) ($linea['precio_unitario'] ?? 0);

            if ($desc === '' && $codigo === '') {
                continue;
            }

            if ($codigo !== '') {
                foreach ($this->wrapText('Cod: ' . $codigo) as $parteCodigo) {
                    $out .= $this->text($parteCodigo) . "\n";
                }
            }

            foreach ($this->wrapText($desc) as $parte) {
                $out .= $this->text($parte) . "\n";
            }
            $detalle = sprintf('%s x $%s', $this->formatQty($cantidad), $this->formatMoney($precio));
            $out .= $this->lineItemColumns($detalle, '$' . $this->formatMoney($importe)) . "\n";
        }

        $out .= $this->text(str_repeat('-', $this->width)) . "\n";

        if (array_key_exists('conteo_piezas', $ticket)) {
            $piezas = (int) ($ticket['conteo_piezas'] ?? 0);
            $etiquetaPiezas = trim((string) ($ticket['leyenda_conteo_piezas'] ?? 'Piezas en esta compra'));
            if ($etiquetaPiezas === '') {
                $etiquetaPiezas = 'Piezas en esta compra';
            }
            $out .= $this->cmdBold(true);
            $out .= $this->lineItemColumns($etiquetaPiezas, (string) $piezas) . "\n";
            $out .= $this->cmdBold(false);
            $out .= $this->text(str_repeat('-', $this->width)) . "\n";
        }

        $subtotal = (float) ($ticket['subtotal'] ?? 0);
        $descuento = (float) ($ticket['descuento_monto'] ?? 0);
        $impuesto = (float) ($ticket['impuesto_monto'] ?? 0);
        $total = (float) ($ticket['total'] ?? 0);

        $out .= $this->lineItemColumns('Subtotal', '$' . $this->formatMoney($subtotal)) . "\n";
        if ($descuento > 0) {
            $out .= $this->lineItemColumns('Descuento', '-$' . $this->formatMoney($descuento)) . "\n";
        }
        $montoCanje = (float) ($ticket['monto_canje'] ?? 0);
        if ($montoCanje > 0) {
            $out .= $this->lineItemColumns('Descuento por canje', '-$' . $this->formatMoney($montoCanje)) . "\n";
        }
        if (!empty($ticket['mostrar_impuesto'])) {
            $pct = (float) ($ticket['impuesto_porcentaje'] ?? 0);
            $labelImp = $pct > 0.009
                ? 'Impuesto (' . $this->formatMoney($pct) . '%)'
                : 'Impuesto';
            $out .= $this->lineItemColumns($labelImp, '$' . $this->formatMoney($impuesto)) . "\n";
        }

        $out .= $this->cmdBold(true);
        $out .= $this->lineItemColumns('TOTAL', '$' . $this->formatMoney($total)) . "\n";
        $out .= $this->cmdBold(false);

        if (!empty($ticket['pagos']) && is_array($ticket['pagos'])) {
            $out .= $this->text(str_repeat('-', $this->width)) . "\n";
            $tituloPagos = trim((string) ($ticket['pagos_seccion_titulo'] ?? 'Formas de pago:'));
            $out .= $this->text($tituloPagos) . "\n";
            foreach ($ticket['pagos'] as $pago) {
                if (!is_array($pago)) {
                    continue;
                }
                $forma = trim((string) ($pago['forma_pago'] ?? 'Pago'));
                $monto = (float) ($pago['monto'] ?? 0);
                $out .= $this->lineItemColumns($forma, '$' . $this->formatMoney($monto)) . "\n";
            }
        }

        if (array_key_exists('saldo_pendiente', $ticket)) {
            $out .= $this->text(str_repeat('-', $this->width)) . "\n";
            $saldo = (float) ($ticket['saldo_pendiente'] ?? 0);
            $out .= $this->cmdBold(true);
            $out .= $this->lineItemColumns('Saldo pendiente', '$' . $this->formatMoney($saldo)) . "\n";
            $out .= $this->cmdBold(false);
        }

        $bannerApr = trim((string) ($ticket['apartado_estado_banner'] ?? ''));
        if ($bannerApr !== '') {
            $out .= $this->cmdAlignCenter();
            $out .= $this->cmdBold(true);
            $out .= $this->text($bannerApr) . "\n";
            $out .= $this->cmdBold(false);
        }

        $pie = trim((string) ($ticket['mensaje_pie'] ?? ''));
        if ($pie !== '') {
            $out .= $this->text(str_repeat('-', $this->width)) . "\n";
            $out .= $this->cmdAlignCenter();
            foreach ($this->wrapText($pie) as $parte) {
                $out .= $this->text($parte) . "\n";
            }
        }

        $out .= "\n\n\n";
        $out .= $this->cmdCutPartial();

        return $out;
    }

    public function toBase64(array $ticket): string
    {
        return base64_encode($this->build($ticket));
    }

    /** Avance de papel al inicio (ESC d). Evita que el encabezado quede en la zona de corte. */
    private function cmdPrintAndFeed(int $lines): string
    {
        $lines = max(0, min(255, $lines));

        return "\x1B\x64" . chr($lines);
    }

    private function cmdInit(): string
    {
        return "\x1B\x40";
    }

    private function cmdAlignLeft(): string
    {
        return "\x1B\x61\x00";
    }

    private function cmdAlignCenter(): string
    {
        return "\x1B\x61\x01";
    }

    private function cmdBold(bool $on): string
    {
        return $on ? "\x1B\x45\x01" : "\x1B\x45\x00";
    }

    private function cmdCutPartial(): string
    {
        return "\x1D\x56\x01";
    }

    /** Margen izquierdo en puntos (GS L). TM-T20 ~203 dpi: 40 pts ~ 5 mm */
    private function cmdSetLeftMargin(int $dots): string
    {
        $dots = max(0, min(65535, $dots));

        return "\x1D\x4C" . chr($dots & 0xFF) . chr(($dots >> 8) & 0xFF);
    }

    private function text(string $value): string
    {
        $value = $this->transliterate($value);
        // Solo ASCII: evita bytes Latin-1 con regex /u (PHP los trata como UTF-8 invalido y borra la linea).
        $value = preg_replace('/[^\x20-\x7E]/', '', $value) ?? '';

        return $value;
    }

    private function transliterate(string $value): string
    {
        $map = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'ñ' => 'n', 'Ñ' => 'N', 'ü' => 'u', 'Ü' => 'U',
        ];
        $value = strtr($value, $map);

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false) {
                return $converted;
            }
        }

        return $value;
    }

    private function lineItemColumns(string $left, string $right): string
    {
        $left = $this->text($left);
        $right = $this->text($right);
        $space = $this->width - mb_strlen($left) - mb_strlen($right);
        if ($space < 1) {
            $maxLeft = max(1, $this->width - mb_strlen($right) - 1);
            $left = mb_substr($left, 0, $maxLeft);
            $space = 1;
        }

        return $left . str_repeat(' ', $space) . $right;
    }

    private function wrapText(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        $words = preg_split('/\s+/', $text) ?: [];
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (mb_strlen($candidate) <= $this->width) {
                $current = $candidate;
                continue;
            }
            if ($current !== '') {
                $lines[] = $current;
            }
            $current = mb_strlen($word) > $this->width ? mb_substr($word, 0, $this->width) : $word;
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    private function formatMoney(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function formatQty(float $value): string
    {
        if (abs($value - round($value)) < 0.0001) {
            return (string) (int) round($value);
        }

        return number_format($value, 2, '.', '');
    }
}
