<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/joyeria_timezone.php';
joyeria_timezone_bootstrap();

class Sistema {
    private $db;
    private static $instance = null;

    public function __construct() {
        $this->conectar();
    }

    public function conectar() {
        try {
            $dsn = DBDRIVER . ':host=' . DBHOST . ';port=' . DBPORT . ';dbname=' . DBNAME . ';charset=utf8mb4';
            $options = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            );

            $this->db = new PDO(
                $dsn,
                DBUSER,
                DBPASSWORD,
                $options
            );
            joyeria_pdo_set_timezone($this->db);
        } catch (PDOException $e) {
            error_log('Error de conexión BD: ' . $e->getMessage());
            die('Error de conexión. Contacte al administrador.');
        }
    }

    public function getDb() {
        return $this->db;
    }

    public function generarNombreArchivoSeguro($nombreOriginal, $prefijo = 'archivo', $idEntidad = null)
    {
        $extension = strtolower((string) pathinfo((string) $nombreOriginal, PATHINFO_EXTENSION));
        $huella = hash('sha256', $prefijo . '|' . $nombreOriginal . '|' . microtime(true) . '|' . random_int(1000, 999999));

        $base = $prefijo;
        if ($idEntidad !== null) {
            $base .= '_' . (int) $idEntidad;
        }

        $base .= '_' . date('YmdHis') . '_' . substr($huella, 0, 20);
        return $extension !== '' ? ($base . '.' . $extension) : $base;
    }

    public function moverImagenSubida($archivo, $directorioDestino, $prefijo = 'img', $idEntidad = null)
    {
        if (!is_array($archivo) || !isset($archivo['error'], $archivo['name'], $archivo['tmp_name'])) {
            throw new InvalidArgumentException('Archivo no valido para carga.');
        }

        if ((int) $archivo['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Error al subir archivo. Codigo: ' . (int) $archivo['error']);
        }

        if (!is_uploaded_file($archivo['tmp_name'])) {
            throw new RuntimeException('No se detecto un archivo subido valido.');
        }

        // Validacion de tipo MIME real del archivo usando finfo
        $mimeReal = $this->obtenerMimeImagenSubida($archivo['tmp_name']);

        // Validacion de extension (inferir desde MIME si Android no envia extension)
        $extension = strtolower((string) pathinfo((string) $archivo['name'], PATHINFO_EXTENSION));
        $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if ($extension === '' || !in_array($extension, $extensionesPermitidas, true)) {
            $extension = $this->extensionDesdeMimeImagen($mimeReal);
        }
        if ($extension === '' || !in_array($extension, $extensionesPermitidas, true)) {
            throw new InvalidArgumentException('Formato de imagen no permitido. Usa JPG, PNG, WEBP o GIF.');
        }

        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }
        $archivo['name'] = $this->asegurarNombreConExtension((string) $archivo['name'], $extension);

        if (!is_dir($directorioDestino)) {
            if (!mkdir($directorioDestino, 0755, true) && !is_dir($directorioDestino)) {
                throw new RuntimeException('No se pudo crear el directorio destino de imagenes.');
            }
        }

        $nombreFinal = $this->generarNombreArchivoSeguro((string) $archivo['name'], $prefijo, $idEntidad);
        $rutaDestino = rtrim((string) $directorioDestino, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $nombreFinal;

        if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
            throw new RuntimeException('No se pudo mover la imagen al directorio destino.');
        }

        return $nombreFinal;
    }

    private function obtenerMimeImagenSubida($rutaArchivo): string
    {
        $mimesPermitidos = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ];

        if (!function_exists('finfo_file')) {
            throw new RuntimeException('La extension fileinfo no esta disponible en el servidor.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if (!$finfo) {
            throw new RuntimeException('No se pudo inicializar la validacion de tipo de archivo.');
        }

        $mimeReal = finfo_file($finfo, (string) $rutaArchivo);
        finfo_close($finfo);

        if ($mimeReal === false) {
            throw new RuntimeException('No se pudo determinar el tipo MIME del archivo subido.');
        }

        $mimeReal = (string) $mimeReal;
        if (!in_array($mimeReal, $mimesPermitidos, true)) {
            throw new InvalidArgumentException(
                'El archivo no es una imagen valida. Tipo MIME detectado: ' . htmlspecialchars($mimeReal) .
                '. Solo se aceptan imagenes (JPEG, PNG, WEBP, GIF).'
            );
        }

        return $mimeReal;
    }

    private function validarMimeImagenReal($rutaArchivo)
    {
        $this->obtenerMimeImagenSubida($rutaArchivo);
        return true;
    }

    private function extensionDesdeMimeImagen(string $mime): string
    {
        switch ($mime) {
            case 'image/jpeg':
                return 'jpg';
            case 'image/png':
                return 'png';
            case 'image/webp':
                return 'webp';
            case 'image/gif':
                return 'gif';
            default:
                return '';
        }
    }

    private function asegurarNombreConExtension(string $nombreOriginal, string $extension): string
    {
        $base = pathinfo($nombreOriginal, PATHINFO_FILENAME);
        if ($base === '' || $base === '.') {
            $base = 'imagen';
        }
        return $base . '.' . $extension;
    }
}
?>
