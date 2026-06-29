<?php
/**
 * Utilidades para envío de credenciales de cliente (crear / actualizar).
 */

require_once __DIR__ . '/MailService.php';

/** Sufijo de correos generados cuando el usuario no captura email (alta admin). */
const JOYERIA_CLIENTE_CORREO_SINTETICO_DOMINIO = '@sin-correo.local';

function joyeria_cliente_correo_normalizado(string $correo): string
{
    return strtolower(trim($correo));
}

function joyeria_cliente_correo_es_sintetico(string $correo): bool
{
    $norm = joyeria_cliente_correo_normalizado($correo);
    if ($norm === '') {
        return true;
    }

    return str_ends_with($norm, JOYERIA_CLIENTE_CORREO_SINTETICO_DOMINIO)
        || str_ends_with($norm, '@migracion.local');
}

function joyeria_cliente_correo_es_entregable(string $correo): bool
{
    $norm = joyeria_cliente_correo_normalizado($correo);

    return $norm !== '' && !joyeria_cliente_correo_es_sintetico($norm);
}

function joyeria_cliente_url_app(): string
{
    if (empty($_SERVER['HTTP_HOST'])) {
        return '';
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
}

function joyeria_cliente_generar_contrasena_temporal(): string
{
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $len = strlen($chars);
    $out = '';
    for ($i = 0; $i < 10; $i++) {
        $out .= $chars[random_int(0, $len - 1)];
    }

    return $out;
}

/**
 * Si cambió el correo y no se indicó contraseña, genera una temporal en $data.
 *
 * @return string|null Contraseña en texto plano que quedará vigente (nueva o la capturada).
 */
function joyeria_cliente_resolver_contrasena_para_correo(array $clienteAntes, array &$data): ?string
{
    $plain = isset($data['contrasena']) ? trim((string) $data['contrasena']) : '';
    if ($plain !== '') {
        return $plain;
    }

    $antes = joyeria_cliente_correo_normalizado((string) ($clienteAntes['correo'] ?? ''));
    $nuevoRaw = isset($data['correo']) ? trim((string) $data['correo']) : '';
    $nuevo = $nuevoRaw !== ''
        ? joyeria_cliente_correo_normalizado($nuevoRaw)
        : $antes;
    if ($antes !== $nuevo && joyeria_cliente_correo_es_entregable($nuevo)) {
        $plain = joyeria_cliente_generar_contrasena_temporal();
        $data['contrasena'] = $plain;

        return $plain;
    }

    return null;
}

function joyeria_cliente_correo_cambio(array $clienteAntes, array $data): bool
{
    $antes = joyeria_cliente_correo_normalizado((string) ($clienteAntes['correo'] ?? ''));
    $nuevoRaw = isset($data['correo']) ? trim((string) $data['correo']) : '';
    if ($nuevoRaw === '') {
        return false;
    }
    $nuevo = joyeria_cliente_correo_normalizado($nuevoRaw);

    return $antes !== $nuevo && joyeria_cliente_correo_es_entregable($nuevo);
}

/**
 * @param array{nombre?: string, primer_apellido?: string, correo?: string} $datosCliente
 */
function joyeria_cliente_enviar_credenciales_mail(array $datosCliente, string $contrasenaPlano, bool $esActualizacion): array
{
    $correo = trim((string) ($datosCliente['correo'] ?? ''));
    if (!joyeria_cliente_correo_es_entregable($correo)) {
        return [
            'success' => true,
            'message' => 'Sin correo de contacto; no se envio credenciales.',
            'skipped' => true,
        ];
    }

    return MailService::enviarCredencialesCliente(
        [
            'nombre' => trim((string) ($datosCliente['nombre'] ?? '')),
            'primer_apellido' => trim((string) ($datosCliente['primer_apellido'] ?? '')),
            'correo' => trim((string) ($datosCliente['correo'] ?? '')),
            'contrasena_temporal' => $contrasenaPlano,
        ],
        joyeria_cliente_url_app(),
        $esActualizacion
    );
}

/**
 * @param array<string, mixed> $clienteRegistro Resultado de Cliente::leerUno o datos del formulario.
 */
function joyeria_cliente_datos_para_correo(array $clienteRegistro): array
{
    return [
        'nombre' => trim((string) ($clienteRegistro['nombre'] ?? '')),
        'primer_apellido' => trim((string) ($clienteRegistro['primer_apellido'] ?? '')),
        'correo' => trim((string) ($clienteRegistro['correo'] ?? '')),
    ];
}

/**
 * @return array{correo_credenciales_enviado?: bool, correo_credenciales_mensaje?: string, contrasena_generada_por_cambio_correo?: bool}
 */
function joyeria_cliente_adjuntar_estado_correo_respuesta(array $payload, array $resultMail, bool $contrasenaGenerada = false): array
{
    if (!empty($resultMail['skipped'])) {
        $payload['correo_credenciales_enviado'] = false;
        $payload['correo_credenciales_omitido'] = true;

        return $payload;
    }

    $payload['correo_credenciales_enviado'] = !empty($resultMail['success']);
    if ($contrasenaGenerada) {
        $payload['contrasena_generada_por_cambio_correo'] = true;
    }
    if (empty($resultMail['success']) && isset($resultMail['message'])) {
        $payload['correo_credenciales_mensaje'] = $resultMail['message'];
    }

    return $payload;
}
