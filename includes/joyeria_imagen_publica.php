<?php
declare(strict_types=1);

/**
 * URL pública de imagen solo si existe en disco (misma regla que la landing visitante).
 */
function joyeria_resolver_url_imagen(?string $urlRelativa): ?string
{
    $rel = trim((string) $urlRelativa);
    if ($rel === '') {
        return null;
    }

    if (preg_match('#^https?://#i', $rel) === 1) {
        return $rel;
    }

    $normalized = str_replace('\\', '/', $rel);
    $normalized = preg_replace('#^\./+#', '', $normalized);
    $normalized = ltrim((string) $normalized, '/');

    $root = dirname(__DIR__);
    $candidatas = [
        $normalized,
        'admin/' . $normalized,
    ];

    foreach ($candidatas as $rutaPublica) {
        $rutaPublica = preg_replace('#/+#', '/', (string) $rutaPublica);
        $rutaFisica = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rutaPublica);
        if (is_file($rutaFisica)) {
            return (string) $rutaPublica;
        }
    }

    return null;
}
