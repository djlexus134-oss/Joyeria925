<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Directorio temporal escribible para mPDF (VPS / hosting compartido).
 */
function joyeria_mpdf_temp_dir(): string
{
    $candidatos = [
        __DIR__ . '/../../uploads/tmp/mpdf',
        __DIR__ . '/../../uploads/tmp',
        sys_get_temp_dir() . '/joyeria_mpdf',
    ];

    foreach ($candidatos as $dir) {
        $dir = str_replace('\\', '/', $dir);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            return $dir;
        }
    }

    $fallback = sys_get_temp_dir();
    if ($fallback !== '' && is_writable($fallback)) {
        return $fallback;
    }

    throw new RuntimeException('No hay directorio temporal escribible para generar PDF.');
}

/**
 * @param array<string, mixed> $opciones
 */
function joyeria_mpdf_crear(string $formato = 'A4-L', array $opciones = []): Mpdf
{
    if (!class_exists(Mpdf::class)) {
        throw new RuntimeException(
            'La libreria mPDF no esta instalada. En el servidor ejecuta: composer install'
        );
    }

    $config = array_merge([
        'mode' => 'utf-8',
        'format' => $formato,
        'tempDir' => joyeria_mpdf_temp_dir(),
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 12,
        'margin_bottom' => 12,
        'margin_header' => 6,
        'margin_footer' => 6,
    ], $opciones);

    return new Mpdf($config);
}

/**
 * Limpia buffers para evitar HTTP 500 al enviar el PDF.
 */
function joyeria_mpdf_limpiar_buffers(): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

/**
 * Genera y descarga un PDF a partir de HTML.
 */
function joyeria_mpdf_descargar_html(
    string $html,
    string $titulo,
    string $nombreArchivo,
    string $formato = 'A4-L'
): void {
    $memoriaPrev = ini_get('memory_limit');
    if ($memoriaPrev !== false && $memoriaPrev !== '-1') {
        @ini_set('memory_limit', '512M');
    }

    joyeria_mpdf_limpiar_buffers();

    try {
        $mpdf = joyeria_mpdf_crear($formato);
        $mpdf->SetAuthor('Sistema Joyeria');
        $mpdf->SetCreator('Sistema Joyeria');
        $mpdf->SetTitle($titulo);
        $mpdf->SetHTMLFooter(
            '<div style="text-align:right;font-size:8pt;color:#6b7280;">Pagina {PAGENO}</div>'
        );
        $mpdf->WriteHTML($html);

        if ($nombreArchivo === '') {
            $nombreArchivo = 'reporte_' . date('Ymd_His') . '.pdf';
        }
        if (!str_ends_with(strtolower($nombreArchivo), '.pdf')) {
            $nombreArchivo .= '.pdf';
        }

        joyeria_mpdf_limpiar_buffers();
        $mpdf->Output($nombreArchivo, Destination::DOWNLOAD);
        exit;
    } catch (Throwable $e) {
        error_log('joyeria_mpdf_descargar_html: ' . $e->getMessage());
        joyeria_mpdf_limpiar_buffers();
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'No se pudo generar el PDF. ';
        echo 'Revise que exista vendor/mpdf y que uploads/tmp sea escribible. ';
        echo 'Detalle: ' . $e->getMessage();
        exit;
    } finally {
        if ($memoriaPrev !== false) {
            @ini_set('memory_limit', (string) $memoriaPrev);
        }
    }
}
