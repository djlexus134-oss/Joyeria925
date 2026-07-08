<?php
declare(strict_types=1);

/**
 * Evita que warnings/notices PHP (HTML) contaminen respuestas JSON en APIs y AJAX.
 */
function joyeria_json_guard_begin(): void
{
    static $started = false;
    if ($started) {
        return;
    }
    $started = true;

    if (ob_get_level() === 0) {
        ob_start();
    }

    ini_set('display_errors', '0');
    ini_set('html_errors', '0');

    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        error_log("PHP [$severity] $message in $file:$line");

        return true;
    });

    register_shutdown_function(static function (): void {
        $err = error_get_last();
        if ($err === null) {
            return;
        }
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($err['type'], $fatalTypes, true)) {
            return;
        }

        joyeria_json_clean_buffer();
        if (headers_sent()) {
            return;
        }

        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'ok' => false,
            'error' => 'Error interno del servidor.',
            'mensaje' => 'Error interno del servidor.',
        ], JSON_UNESCAPED_UNICODE);
    });
}

function joyeria_json_clean_buffer(): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}
