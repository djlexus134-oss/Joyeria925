<?php
/**
 * Visualizador de PDFs de Contratos - Versión Robusta
 * Archivo seguro para visualizar PDFs generados
 * 
 * Este archivo fue creado como backup alternativo en caso de problemas con rutas relativas
 */

require_once(__DIR__ . "/../sistema.class.php");
require_once(__DIR__ . "/includes/auth.php");

// Verificar autenticación
if (!auth_is_logged_in()) {
    die("Acceso denegado - No autenticado");
}

// Obtener parámetro de archivo
$archivo = isset($_GET['file']) ? basename($_GET['file']) : null;

if (!$archivo) {
    die("Archivo no especificado");
}

// MÉTODO 1: Ruta absoluta directa
// En lugar de usar rutas relativas complicadas, usamos dirname() para obtener ancestros
$adminDir = __DIR__;
$joyeriaDir = dirname($adminDir);  // Sube desde admin a Joyeria
$uploadDir = $joyeriaDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'contratos';

// Normalizar separadores
$uploadDir = str_replace('\\', '/', $uploadDir);
$rutaArchivo = $uploadDir . '/' . $archivo;

error_log("=== PDF VIEWER ALTERNATIVE ===");
error_log("Admin Dir: $adminDir");
error_log("Joyeria Dir: $joyeriaDir");
error_log("Upload Dir: $uploadDir");
error_log("Archivo: $archivo");
error_log("Ruta Final: $rutaArchivo");

// Verificar que el archivo existe primero
if (!file_exists($rutaArchivo)) {
    error_log("Archivo no existe: $rutaArchivo");
    // Intentar diagnosticar por qué
    error_log("Upload Dir existe: " . (is_dir($uploadDir) ? 'YES' : 'NO'));
    error_log("Upload Dir contents: " . json_encode(scandir($uploadDir) ?: ['ERROR']));
    die("Archivo no encontrado: " . htmlspecialchars($archivo));
}

// Verificar que es un PDF
if (strtolower(pathinfo($rutaArchivo, PATHINFO_EXTENSION)) !== 'pdf') {
    error_log("Intento de acceso a no-PDF: $rutaArchivo");
    die("Tipo de archivo no permitido");
}

// Validación de seguridad: verificar que la ruta está dentro de uploads/contratos
// Normalizar ambas rutas para comparación
$rutaArchivoNorm = str_replace('\\', '/', realpath($rutaArchivo) ?: $rutaArchivo);
$uploadDirNorm = str_replace('\\', '/', realpath($uploadDir) ?: $uploadDir);
if (substr($uploadDirNorm, -1) !== '/') {
    $uploadDirNorm .= '/';
}

error_log("Ruta normalizada: $rutaArchivoNorm");
error_log("Upload dir normalizado: $uploadDirNorm");

if (strpos($rutaArchivoNorm, $uploadDirNorm) !== 0) {
    error_log("SEGURIDAD: Path traversal attempt - Ruta fuera de permisos");
    die("Acceso denegado: path inválido");
}

// Verificar permisos de lectura
if (!is_readable($rutaArchivo)) {
    die("Permiso denegado");
}

// Obtener tamaño
$fileSize = filesize($rutaArchivo);

// Servir el PDF
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($rutaArchivo) . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($rutaArchivo);
exit;
?>
