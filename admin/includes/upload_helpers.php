<?php
/**
 * Utilidades para diagnosticar errores de subida de archivos (multipart).
 */

function joyeria_describir_error_subida(int $codigo): string
{
    switch ($codigo) {
        case UPLOAD_ERR_OK:
            return '';
        case UPLOAD_ERR_INI_SIZE:
            return 'La imagen supera el limite upload_max_filesize del servidor.';
        case UPLOAD_ERR_FORM_SIZE:
            return 'La imagen supera el tamano maximo permitido por el formulario.';
        case UPLOAD_ERR_PARTIAL:
            return 'La imagen se subio solo parcialmente. Intenta de nuevo.';
        case UPLOAD_ERR_NO_FILE:
            return 'No se selecciono ningun archivo de imagen.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'El servidor no tiene carpeta temporal para subidas.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'El servidor no pudo escribir la imagen en disco.';
        case UPLOAD_ERR_EXTENSION:
            return 'Una extension de PHP bloqueo la subida del archivo.';
        default:
            return 'Error desconocido al subir la imagen (codigo ' . $codigo . ').';
    }
}

function joyeria_archivo_subida_listo(?array $archivo): bool
{
    if (!is_array($archivo) || !isset($archivo['error'], $archivo['tmp_name'])) {
        return false;
    }
    if ((int) $archivo['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    $tmp = (string) ($archivo['tmp_name'] ?? '');
    return $tmp !== '' && is_uploaded_file($tmp);
}

function joyeria_mensaje_error_archivo_subida(?array $archivo, string $campoLabel = 'imagen'): ?string
{
    if (!is_array($archivo) || !isset($archivo['error'])) {
        return null;
    }
    $error = (int) $archivo['error'];
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error === UPLOAD_ERR_OK) {
        $tmp = (string) ($archivo['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return 'No se recibio la ' . $campoLabel . ' en el servidor. Si usaste la camara, intenta de nuevo o reduce el tamano de la foto.';
        }
        return null;
    }
    $detalle = joyeria_describir_error_subida($error);
    return 'Error al subir la ' . $campoLabel . ': ' . $detalle;
}

function joyeria_mensaje_post_sin_archivos(): ?string
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method !== 'POST') {
        return null;
    }
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength <= 0) {
        return null;
    }
    $files = $_FILES ?? [];
    if ($files !== []) {
        return null;
    }
    return 'El servidor no recibio archivos. La foto puede ser demasiado grande para post_max_size o upload_max_filesize. Contacta al administrador o usa una foto mas pequena.';
}

function joyeria_extraer_archivo_principal_listo(): ?array
{
    if (!isset($_FILES['imagen_principal']) || !is_array($_FILES['imagen_principal'])) {
        return null;
    }
    $archivo = $_FILES['imagen_principal'];
    if (!joyeria_archivo_subida_listo($archivo)) {
        return null;
    }
    return $archivo;
}

function joyeria_resumen_errores_imagenes_subida(): array
{
    $errores = [];
    $postVacio = joyeria_mensaje_post_sin_archivos();
    if ($postVacio !== null) {
        $errores[] = $postVacio;
    }
    if (isset($_FILES['imagen_principal'])) {
        $msg = joyeria_mensaje_error_archivo_subida($_FILES['imagen_principal'], 'imagen principal');
        if ($msg !== null) {
            $errores[] = $msg;
        }
    }
    if (isset($_FILES['imagenes_adicionales']) && is_array($_FILES['imagenes_adicionales']['error'] ?? null)) {
        $total = count($_FILES['imagenes_adicionales']['error']);
        for ($i = 0; $i < $total; $i++) {
            $err = (int) ($_FILES['imagenes_adicionales']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($err === UPLOAD_ERR_NO_FILE || $err === UPLOAD_ERR_OK) {
                continue;
            }
            $errores[] = 'Imagen adicional ' . ($i + 1) . ': ' . joyeria_describir_error_subida($err);
        }
    }
    return $errores;
}
