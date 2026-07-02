<?php

class SpeiDepositoPayloadBuilder
{
    public static function normalizarClabe(string $clabe): string
    {
        return preg_replace('/\D/', '', trim($clabe)) ?? '';
    }

    public static function validarClabe(string $clabe): bool
    {
        $clabe = self::normalizarClabe($clabe);
        if (strlen($clabe) !== 18 || !ctype_digit($clabe)) {
            return false;
        }

        $pesos = [3, 7, 1];
        $suma = 0;
        for ($i = 0; $i < 17; $i++) {
            $suma += ((int) $clabe[$i] * $pesos[$i % 3]) % 10;
        }
        $control = (10 - ($suma % 10)) % 10;

        return (int) $clabe[17] === $control;
    }

    /**
     * @param array{beneficiario?:string,banco?:string,clabe?:string,instrucciones?:string,referencia_prefijo?:string} $config
     */
    public static function construirReferencia(array $config, ?DateTimeInterface $fecha = null): string
    {
        $prefijo = trim((string) ($config['referencia_prefijo'] ?? 'VENTA'));
        if ($prefijo === '') {
            $prefijo = 'VENTA';
        }
        $prefijo = preg_replace('/[^A-Za-z0-9_-]/', '', $prefijo) ?? 'VENTA';
        if ($prefijo === '') {
            $prefijo = 'VENTA';
        }

        $fecha = $fecha ?? new DateTimeImmutable('now');
        $sufijo = $fecha->format('Ymd-His');

        return $prefijo . '-' . $sufijo;
    }

    public static function formatearMonto(float $monto): string
    {
        return '$' . number_format(max(0, $monto), 2, '.', ',') . ' MXN';
    }

    /**
     * @param array{beneficiario?:string,banco?:string,clabe?:string,instrucciones?:string,referencia_prefijo?:string} $config
     */
    public static function construirTexto(array $config, float $monto, ?string $referencia = null): string
    {
        $beneficiario = trim((string) ($config['beneficiario'] ?? ''));
        $banco = trim((string) ($config['banco'] ?? ''));
        $clabe = self::normalizarClabe((string) ($config['clabe'] ?? ''));
        $instrucciones = trim((string) ($config['instrucciones'] ?? ''));
        $referencia = trim((string) ($referencia ?? ''));
        if ($referencia === '') {
            $referencia = self::construirReferencia($config);
        }

        $lineas = ['Transferencia SPEI'];
        if ($beneficiario !== '') {
            $lineas[] = 'Beneficiario: ' . $beneficiario;
        }
        if ($banco !== '') {
            $lineas[] = 'Banco: ' . $banco;
        }
        if ($clabe !== '') {
            $lineas[] = 'CLABE: ' . $clabe;
        }
        $lineas[] = 'Monto: ' . self::formatearMonto($monto);
        $lineas[] = 'Concepto: ' . $referencia;
        if ($instrucciones !== '') {
            $lineas[] = 'Instrucciones: ' . $instrucciones;
        }

        return implode("\n", $lineas);
    }

    /**
     * Raíz pública del sitio (sin /admin). Prefiere JOYERIA_APP_URL.
     */
    public static function resolverBaseUrlPublica(): string
    {
        if (defined('JOYERIA_APP_URL') && trim((string) JOYERIA_APP_URL) !== '') {
            return rtrim((string) JOYERIA_APP_URL, '/');
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
        $scheme = $https ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

        $scriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']) : '';
        $dir = $scriptName !== '' ? dirname($scriptName) : '';
        $path = '';
        if ($dir !== '' && $dir !== '.' && $dir !== '/') {
            $path = rtrim($dir, '/');
        }

        return $scheme . '://' . $host . $path;
    }

    public static function normalizarReferenciaUrl(string $referencia): string
    {
        $referencia = trim($referencia);
        if ($referencia === '') {
            return 'VENTA';
        }
        $referencia = preg_replace('/[^A-Za-z0-9_-]/', '', $referencia) ?? '';
        if ($referencia === '') {
            return 'VENTA';
        }

        return mb_substr($referencia, 0, 64);
    }

    /**
     * URL HTTPS para el QR (página móvil spei_deposito.php).
     */
    public static function construirUrlPaginaDeposito(
        string $baseUrl,
        float $monto,
        ?string $referencia = null
    ): string {
        $baseUrl = rtrim($baseUrl, '/');
        $monto = max(0, round($monto, 2));
        $referencia = self::normalizarReferenciaUrl((string) ($referencia ?? ''));

        $query = http_build_query([
            'm' => number_format($monto, 2, '.', ''),
            'r' => $referencia,
        ], '', '&', PHP_QUERY_RFC3986);

        return $baseUrl . '/spei_deposito.php?' . $query;
    }
}
