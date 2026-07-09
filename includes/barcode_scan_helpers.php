<?php
declare(strict_types=1);

if (!function_exists('joyeria_normalizar_codigo_escaneo')) {
    /**
     * Normaliza lectura de pistola/teclado: trim, quita caracteres de control y
     * convierte codigo auxiliar ARTPIE-CODPIE → ARTPIE/CODPIE (guion por diagonal).
     */
    function joyeria_normalizar_codigo_escaneo(string $raw): string
    {
        $t = trim($raw);
        if ($t === '') {
            return '';
        }
        $t = preg_replace('/[\x00-\x1F\x7F]+/u', '', $t) ?? $t;
        $t = trim($t);
        if ($t !== '' && preg_match('/^\d+-\d+$/', $t) === 1) {
            $t = preg_replace('/-/', '/', $t, 1);
        }

        return $t;
    }
}
