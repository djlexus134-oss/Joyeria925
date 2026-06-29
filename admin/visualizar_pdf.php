<?php
/**
 * Visualizador de PDFs de Contratos - Versión Corregida
 * Archivo seguro para visualizar PDFs generados
 */

require_once(__DIR__ . "/../sistema.class.php");
require_once(__DIR__ . "/includes/auth.php");

// Verificar autenticación
if (!auth_is_logged_in()) {
    die("Acceso denegado");
}

// Obtener parámetro de archivo (solo el nombre, sin ruta)
$archivo = isset($_GET['file']) ? basename($_GET['file']) : null;

if (!$archivo) {
    die("Archivo no especificado");
}

// CONSTRUCCIÓN SIMPLE DE LA RUTA USANDO dirname()
// dirname(__FILE__) = D:\PrograWEB\src\Joyeria\admin
// dirname(dirname(__FILE__)) = D:\PrograWEB\src\Joyeria
$adminPath = dirname(__FILE__);
$joyeriaPath = dirname($adminPath);
$uploadsPath = $joyeriaPath . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'contratos';

// Normalizar separadores a forward slashes
$uploadsPath = str_replace('\\', '/', $uploadsPath);
$rutaCompleta = $uploadsPath . '/' . $archivo;

error_log("=== VISUALIZAR PDF - Intento de acceso ===");
error_log("Archivo: $archivo");
error_log("Joyeria Path: $joyeriaPath");
error_log("Uploads Path: $uploadsPath");
error_log("Ruta Completa: $rutaCompleta");

// VALIDACIÓN DE SEGURIDAD: Path Traversal Protection
// El basename() ya limpia la ruta, pero hacemos verificación adicional
if (strpos($archivo, '..') !== false || strpos($archivo, '/') !== false || strpos($archivo, '\\') !== false) {
    error_log("SEGURIDAD: Intento de path traversal detectado");
    die("Acceso denegado: nombre de archivo inválido");
}

// VERIFICAR QUE EL ARCHIVO EXISTE
if (!file_exists($rutaCompleta)) {
    error_log("ERROR 404: Archivo no encontrado");
    error_log("Ruta esperada: $rutaCompleta");
    
    // Debug: listar archivos disponibles
    if (is_dir($uploadsPath)) {
        $archivos = @scandir($uploadsPath);
        error_log("Archivos disponibles: " . json_encode($archivos ?: []));
    } else {
        error_log("Directorio no existe: $uploadsPath");
    }
    
    die("Archivo no encontrado: " . htmlspecialchars($archivo));
}

// VERIFICAR QUE ES UN PDF
$ext = strtolower(pathinfo($rutaCompleta, PATHINFO_EXTENSION));
if ($ext !== 'pdf') {
    error_log("SEGURIDAD: Intento de acceso a archivo no-PDF (.$ext)");
    die("Tipo de archivo no permitido");
}

// VERIFICAR PERMISOS DE LECTURA
if (!is_readable($rutaCompleta)) {
    error_log("ERROR: Archivo no es legible");
    die("Permiso denegado: no se puede leer el archivo");
}

// Obtener información del archivo
$fileSize = filesize($rutaCompleta);
if ($fileSize === false || $fileSize === 0) {
    error_log("ERROR: Archivo vacío o corrupto - Tamaño: " . ($fileSize === false ? 'error' : $fileSize));
    die("Archivo corrupto o vacío");
}

error_log("OK: Sirviendo PDF - Tamaño: $fileSize bytes");

// SERVIR EL PDF
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($rutaCompleta) . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Accept-Ranges: bytes');

// Servir el archivo
readfile($rutaCompleta);
exit;
?>
