<?php

declare(strict_types=1);

/**
 * Cloudflare Turnstile — verificacion server-side.
 * Claves en config.php: JOYERIA_TURNSTILE_*.
 */

/**
 * Interpreta un valor de config como booleano, tolerando tanto el booleano
 * real (true) como cadenas ('true', '1', 'on', 'yes'). Asi la config funciona
 * tanto si se escribe define(..., true) como define(..., 'true').
 */
function joyeria_config_es_verdadero($valor): bool
{
    if (is_bool($valor)) {
        return $valor;
    }
    return filter_var($valor, FILTER_VALIDATE_BOOLEAN);
}

function joyeria_turnstile_enabled(): bool
{
    if (!defined('JOYERIA_TURNSTILE_ENABLED') || !joyeria_config_es_verdadero(JOYERIA_TURNSTILE_ENABLED)) {
        return false;
    }
    if (!defined('JOYERIA_TURNSTILE_SECRET_KEY')) {
        return false;
    }
    $secret = trim((string) JOYERIA_TURNSTILE_SECRET_KEY);
    if ($secret === '' || $secret === 'cambiar_secret_key_turnstile') {
        return false;
    }

    return true;
}

/**
 * En produccion (JOYERIA_TURNSTILE_REQUERIDO=true) Turnstile es obligatorio:
 * si no esta correctamente configurado, las peticiones se rechazan en lugar
 * de dejarlas pasar (evita quedar sin proteccion anti-bot por un error de
 * configuracion).
 */
function joyeria_turnstile_requerido(): bool
{
    return defined('JOYERIA_TURNSTILE_REQUERIDO') && joyeria_config_es_verdadero(JOYERIA_TURNSTILE_REQUERIDO);
}

function joyeria_turnstile_site_key(): string
{
    if (!joyeria_turnstile_enabled()) {
        return '';
    }
    if (!defined('JOYERIA_TURNSTILE_SITE_KEY')) {
        return '';
    }
    $site = trim((string) JOYERIA_TURNSTILE_SITE_KEY);
    if ($site === '' || $site === 'cambiar_site_key_turnstile') {
        return '';
    }

    return $site;
}

/**
 * @return array{success: bool, error_codes?: array<int, string>}
 */
function joyeria_turnstile_siteverify(string $token): array
{
    $token = trim($token);
    if ($token === '') {
        return ['success' => false, 'error_codes' => ['missing-input-response']];
    }

    $secret = (string) JOYERIA_TURNSTILE_SECRET_KEY;
    $postFields = http_build_query([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '',
    ]);

    $responseBody = false;
    if (function_exists('curl_init')) {
        $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        if ($ch !== false) {
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postFields,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            ]);
            $responseBody = curl_exec($ch);
            curl_close($ch);
        }
    }

    if ($responseBody === false) {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $postFields,
                'timeout' => 10,
            ],
        ]);
        $responseBody = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $ctx);
    }

    if ($responseBody === false || $responseBody === '') {
        error_log('joyeria_turnstile_siteverify: sin respuesta de Cloudflare');
        return ['success' => false, 'error_codes' => ['internal-error']];
    }

    $decoded = json_decode((string) $responseBody, true);
    if (!is_array($decoded)) {
        return ['success' => false, 'error_codes' => ['invalid-json']];
    }

    return [
        'success' => ($decoded['success'] ?? false) === true,
        'error_codes' => isset($decoded['error-codes']) && is_array($decoded['error-codes'])
            ? $decoded['error-codes']
            : [],
    ];
}

function joyeria_turnstile_verificar(string $token): bool
{
    if (!joyeria_turnstile_enabled()) {
        return true;
    }

    return joyeria_turnstile_siteverify($token)['success'];
}

function joyeria_turnstile_rechazar_si_invalido(array $input): bool
{
    if (!joyeria_turnstile_enabled()) {
        // En produccion exigimos Turnstile: si esta marcado como requerido pero
        // no se pudo habilitar (claves placeholder o ENABLED=false), NO hacemos
        // fail-open; rechazamos y dejamos rastro para detectar la mala config.
        if (joyeria_turnstile_requerido()) {
            error_log('joyeria_turnstile: REQUERIDO pero no configurado; rechazando solicitud.');
            return false;
        }
        return true;
    }

    $token = isset($input['turnstile_token']) ? (string) $input['turnstile_token'] : '';
    if (joyeria_turnstile_verificar($token)) {
        return true;
    }

    return false;
}

function joyeria_turnstile_error_mensaje(): string
{
    return 'No pudimos verificar que eres una persona. Completa la verificacion e intenta de nuevo.';
}
