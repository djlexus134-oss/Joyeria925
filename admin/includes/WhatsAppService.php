<?php
/**
 * WhatsAppService.php - Servicio centralizado para envio de mensajes por WhatsApp
 *
 * Usa la WhatsApp Cloud API oficial de Meta (graph.facebook.com).
 *
 * Importante: los mensajes iniciados por el negocio (bienvenida, notificaciones)
 * requieren PLANTILLAS pre-aprobadas en Meta Business Manager. El texto libre
 * solo se permite dentro de la ventana de 24h tras un mensaje del cliente.
 *
 * Resolucion de configuracion (orden de prioridad):
 *   defaults -> configuracion_general -> constantes JOYERIA_WHATSAPP_* en config.php
 *
 * El token de acceso se recomienda SOLO en config.php (es secreto y puede
 * superar el limite de 255 caracteres de configuracion_general.valor).
 */

require_once __DIR__ . '/../../sistema.class.php';

if (is_file(__DIR__ . '/../../config.php')) {
    require_once __DIR__ . '/../../config.php';
}

class WhatsAppService
{
    /**
     * Valores por defecto (versionables, sin secretos).
     */
    private const DEFAULT_CONFIG = [
        'habilitado'        => false,
        'token'             => '',
        'phone_number_id'   => '',
        'api_version'       => 'v20.0',
        'codigo_pais'       => '52',
        'idioma'            => 'es_MX',
        'tpl_bienvenida_cliente'  => '',
        'tpl_bienvenida_empleado' => '',
        'tpl_notificacion'        => '',
    ];

    /** Claves en configuracion_general que alimentan cada campo. */
    private const CONFIG_KEYS = [
        'habilitado'              => 'whatsapp_habilitado',
        'token'                   => 'whatsapp_token',
        'phone_number_id'         => 'whatsapp_phone_number_id',
        'api_version'             => 'whatsapp_api_version',
        'codigo_pais'             => 'whatsapp_codigo_pais_default',
        'idioma'                  => 'whatsapp_template_idioma',
        'tpl_bienvenida_cliente'  => 'whatsapp_template_bienvenida_cliente',
        'tpl_bienvenida_empleado' => 'whatsapp_template_bienvenida_empleado',
        'tpl_notificacion'        => 'whatsapp_template_notificacion',
    ];

    private static ?array $resolvedConfig = null;

    public static function resetConfig(): void
    {
        self::$resolvedConfig = null;
    }

    private static function resolveConfig(): array
    {
        if (is_array(self::$resolvedConfig)) {
            return self::$resolvedConfig;
        }

        $c = self::DEFAULT_CONFIG;
        $c = self::mergeSystemConfig($c);

        if (defined('JOYERIA_WHATSAPP_TOKEN') && trim((string) JOYERIA_WHATSAPP_TOKEN) !== '') {
            $c['token'] = trim((string) JOYERIA_WHATSAPP_TOKEN);
        }
        if (defined('JOYERIA_WHATSAPP_PHONE_NUMBER_ID') && trim((string) JOYERIA_WHATSAPP_PHONE_NUMBER_ID) !== '') {
            $c['phone_number_id'] = trim((string) JOYERIA_WHATSAPP_PHONE_NUMBER_ID);
        }
        if (defined('JOYERIA_WHATSAPP_API_VERSION') && trim((string) JOYERIA_WHATSAPP_API_VERSION) !== '') {
            $c['api_version'] = trim((string) JOYERIA_WHATSAPP_API_VERSION);
        }

        self::$resolvedConfig = $c;
        return self::$resolvedConfig;
    }

    private static function mergeSystemConfig(array $base): array
    {
        try {
            $keys = array_values(self::CONFIG_KEYS);
            $sys = new Sistema();
            $db = $sys->getDb();
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $stmt = $db->prepare(
                "SELECT clave, valor FROM configuracion_general WHERE clave IN ($placeholders)"
            );
            foreach ($keys as $idx => $key) {
                $stmt->bindValue($idx + 1, $key, PDO::PARAM_STR);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $rawMap = [];
            foreach ($rows as $row) {
                $k = trim((string) ($row['clave'] ?? ''));
                if ($k === '') {
                    continue;
                }
                $rawMap[$k] = trim((string) ($row['valor'] ?? ''));
            }

            foreach (self::CONFIG_KEYS as $target => $clave) {
                if (!isset($rawMap[$clave]) || $rawMap[$clave] === '') {
                    continue;
                }
                $value = $rawMap[$clave];
                if ($target === 'habilitado') {
                    $base[$target] = in_array(strtolower($value), ['1', 'true', 'si', 'yes', 'on'], true);
                } else {
                    $base[$target] = $value;
                }
            }
        } catch (Throwable $e) {
            error_log('WhatsAppService::mergeSystemConfig: ' . $e->getMessage());
        }

        return $base;
    }

    public static function estaHabilitado(): bool
    {
        $cfg = self::resolveConfig();
        return !empty($cfg['habilitado']);
    }

    /**
     * @return string|null Mensaje de error si la configuracion esta incompleta; null si puede enviarse.
     */
    private static function configurationError(): ?string
    {
        $cfg = self::resolveConfig();
        if (empty($cfg['habilitado'])) {
            return 'WhatsApp deshabilitado en la configuracion.';
        }
        $faltan = [];
        if (trim((string) $cfg['token']) === '') {
            $faltan[] = 'token de acceso (JOYERIA_WHATSAPP_TOKEN)';
        }
        if (trim((string) $cfg['phone_number_id']) === '') {
            $faltan[] = 'phone_number_id';
        }
        if ($faltan !== []) {
            return 'WhatsApp no configurado: falta ' . implode(', ', $faltan) . '.';
        }
        return null;
    }

    /**
     * Normaliza un telefono a formato E.164 sin el signo +.
     * Si no trae lada, antepone el codigo de pais por defecto.
     */
    public static function normalizarTelefono(string $telefono, ?string $codigoPaisDefault = null): string
    {
        $cfg = self::resolveConfig();
        $cp = $codigoPaisDefault !== null && $codigoPaisDefault !== ''
            ? preg_replace('/[^0-9]/', '', $codigoPaisDefault)
            : preg_replace('/[^0-9]/', '', (string) $cfg['codigo_pais']);
        if ($cp === '') {
            $cp = '52';
        }

        $tieneMas = strpos(trim($telefono), '+') === 0;
        $digitos = preg_replace('/[^0-9]/', '', $telefono) ?? '';
        if ($digitos === '') {
            return '';
        }

        // Si ya viene con + lo respetamos (ya es internacional).
        if ($tieneMas) {
            return $digitos;
        }

        // Numero nacional tipico (10 digitos en MX) -> anteponer lada.
        if (strlen($digitos) <= 10) {
            return $cp . $digitos;
        }

        // Ya parece traer lada incluida.
        return $digitos;
    }

    /**
     * Envia un mensaje basado en plantilla aprobada.
     *
     * @param string[] $bodyParams Valores para las variables {{1}}, {{2}}... del cuerpo.
     * @return array{success: bool, message: string, telefono?: string}
     */
    public static function enviarPlantilla(string $telefono, string $template, string $idioma, array $bodyParams = []): array
    {
        $cfgErr = self::configurationError();
        if ($cfgErr !== null) {
            return ['success' => false, 'message' => $cfgErr];
        }

        $template = trim($template);
        if ($template === '') {
            return ['success' => false, 'message' => 'No se configuro el nombre de la plantilla de WhatsApp.'];
        }

        $cfg = self::resolveConfig();
        $destino = self::normalizarTelefono($telefono);
        if ($destino === '') {
            return ['success' => false, 'message' => 'Telefono vacio o invalido.'];
        }

        $idioma = trim($idioma) !== '' ? trim($idioma) : (string) $cfg['idioma'];

        $components = [];
        if ($bodyParams !== []) {
            $parameters = [];
            foreach ($bodyParams as $valor) {
                $parameters[] = ['type' => 'text', 'text' => (string) $valor];
            }
            $components[] = ['type' => 'body', 'parameters' => $parameters];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $destino,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $idioma],
            ],
        ];
        if ($components !== []) {
            $payload['template']['components'] = $components;
        }

        return self::enviarPayload($cfg, $payload, $destino);
    }

    /**
     * Envia un mensaje de texto libre (solo valido dentro de la ventana de 24h).
     *
     * @return array{success: bool, message: string, telefono?: string}
     */
    public static function enviarTexto(string $telefono, string $texto): array
    {
        $cfgErr = self::configurationError();
        if ($cfgErr !== null) {
            return ['success' => false, 'message' => $cfgErr];
        }

        $cfg = self::resolveConfig();
        $destino = self::normalizarTelefono($telefono);
        if ($destino === '') {
            return ['success' => false, 'message' => 'Telefono vacio o invalido.'];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $destino,
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $texto],
        ];

        return self::enviarPayload($cfg, $payload, $destino);
    }

    /**
     * @return array{success: bool, message: string, telefono?: string}
     */
    private static function enviarPayload(array $cfg, array $payload, string $destino): array
    {
        $url = 'https://graph.facebook.com/' . rawurlencode((string) $cfg['api_version'])
            . '/' . rawurlencode((string) $cfg['phone_number_id']) . '/messages';

        if (!function_exists('curl_init')) {
            return ['success' => false, 'message' => 'La extension cURL de PHP no esta disponible.'];
        }

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $cfg['token'],
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_TIMEOUT => 30,
            ]);

            $respuesta = curl_exec($ch);
            if ($respuesta === false) {
                $err = curl_error($ch);
                curl_close($ch);
                error_log('WhatsAppService cURL: ' . $err);
                return ['success' => false, 'message' => 'Error de red al enviar WhatsApp: ' . $err];
            }

            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $data = json_decode((string) $respuesta, true);
            if ($httpCode >= 200 && $httpCode < 300) {
                return [
                    'success' => true,
                    'message' => 'Mensaje de WhatsApp enviado.',
                    'telefono' => $destino,
                ];
            }

            $detalle = '';
            if (is_array($data) && isset($data['error']['message'])) {
                $detalle = (string) $data['error']['message'];
            } else {
                $detalle = 'HTTP ' . $httpCode;
            }
            error_log('WhatsAppService API error (' . $httpCode . '): ' . (string) $respuesta);
            return ['success' => false, 'message' => 'WhatsApp rechazado: ' . $detalle];
        } catch (Throwable $e) {
            error_log('WhatsAppService::enviarPayload: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error al enviar WhatsApp: ' . $e->getMessage()];
        }
    }

    /**
     * Bienvenida automatica para un nuevo cliente.
     * @return array{success: bool, message: string, skipped?: bool}
     */
    public static function enviarBienvenidaCliente(string $telefono, string $nombre): array
    {
        if (!self::estaHabilitado()) {
            return ['success' => true, 'skipped' => true, 'message' => 'WhatsApp deshabilitado.'];
        }
        if (trim($telefono) === '') {
            return ['success' => true, 'skipped' => true, 'message' => 'Cliente sin telefono; no se envio WhatsApp.'];
        }
        $cfg = self::resolveConfig();
        return self::enviarPlantilla(
            $telefono,
            (string) $cfg['tpl_bienvenida_cliente'],
            (string) $cfg['idioma'],
            [self::primerNombre($nombre)]
        );
    }

    /**
     * Bienvenida automatica para un nuevo empleado.
     * @return array{success: bool, message: string, skipped?: bool}
     */
    public static function enviarBienvenidaEmpleado(string $telefono, string $nombre): array
    {
        if (!self::estaHabilitado()) {
            return ['success' => true, 'skipped' => true, 'message' => 'WhatsApp deshabilitado.'];
        }
        if (trim($telefono) === '') {
            return ['success' => true, 'skipped' => true, 'message' => 'Empleado sin telefono; no se envio WhatsApp.'];
        }
        $cfg = self::resolveConfig();
        return self::enviarPlantilla(
            $telefono,
            (string) $cfg['tpl_bienvenida_empleado'],
            (string) $cfg['idioma'],
            [self::primerNombre($nombre)]
        );
    }

    /**
     * Notificacion especial generica (1 variable de cuerpo: el mensaje).
     * @return array{success: bool, message: string}
     */
    public static function enviarNotificacionGenerica(string $telefono, string $mensaje): array
    {
        $cfg = self::resolveConfig();
        return self::enviarPlantilla(
            $telefono,
            (string) $cfg['tpl_notificacion'],
            (string) $cfg['idioma'],
            [trim($mensaje)]
        );
    }

    /**
     * Envia un documento PDF por WhatsApp (sube media y luego mensaje document).
     *
     * @return array{success: bool, message: string}
     */
    public static function enviarDocumento(string $telefono, string $bytes, string $filename, string $caption = ''): array
    {
        if (!self::estaHabilitado()) {
            return ['success' => true, 'skipped' => true, 'message' => 'WhatsApp deshabilitado.'];
        }
        $cfgErr = self::configurationError();
        if ($cfgErr !== null) {
            return ['success' => false, 'message' => $cfgErr];
        }
        $cfg = self::resolveConfig();
        $destino = self::normalizarTelefono($telefono);
        if ($destino === '' || $bytes === '') {
            return ['success' => false, 'message' => 'Telefono o documento invalido.'];
        }

        $mediaId = self::subirMedia($cfg, $bytes, $filename, 'application/pdf');
        if ($mediaId === '') {
            return ['success' => false, 'message' => 'No se pudo subir el documento a WhatsApp.'];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $destino,
            'type' => 'document',
            'document' => [
                'id' => $mediaId,
                'filename' => $filename,
            ],
        ];
        if (trim($caption) !== '') {
            $payload['document']['caption'] = mb_substr(trim($caption), 0, 1024);
        }

        return self::enviarPayload($cfg, $payload, $destino);
    }

    private static function subirMedia(array $cfg, string $bytes, string $filename, string $mime): string
    {
        if (!function_exists('curl_init')) {
            return '';
        }
        $url = 'https://graph.facebook.com/' . rawurlencode((string) $cfg['api_version'])
            . '/' . rawurlencode((string) $cfg['phone_number_id']) . '/media';

        $tmp = tempnam(sys_get_temp_dir(), 'wa_doc_');
        if ($tmp === false) {
            return '';
        }
        file_put_contents($tmp, $bytes);

        $ch = curl_init($url);
        $post = [
            'messaging_product' => 'whatsapp',
            'file' => new CURLFile($tmp, $mime, $filename),
        ];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $cfg['token']],
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_TIMEOUT => 60,
        ]);
        $respuesta = curl_exec($ch);
        curl_close($ch);
        @unlink($tmp);

        if ($respuesta === false) {
            return '';
        }
        $data = json_decode((string) $respuesta, true);
        return is_array($data) ? trim((string) ($data['id'] ?? '')) : '';
    }

    private static function primerNombre(string $nombreCompleto): string
    {
        $nombre = trim($nombreCompleto);
        if ($nombre === '') {
            return 'cliente';
        }
        $partes = preg_split('/\s+/', $nombre);
        return $partes[0] ?? $nombre;
    }
}
