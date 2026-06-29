<?php
declare(strict_types=1);

class FacturamaClient
{
    private string $baseUrl;
    private string $usuario;
    private string $password;

    public function __construct(?array $config = null)
    {
        $config = $config ?? self::resolverConfig();
        $this->baseUrl = rtrim((string) ($config['api_url'] ?? 'https://apisandbox.facturama.mx'), '/');
        $this->usuario = (string) ($config['usuario'] ?? '');
        $this->password = (string) ($config['password'] ?? '');
    }

    public static function resolverConfig(): array
    {
        $defaults = [
            'api_url' => 'https://apisandbox.facturama.mx',
            'usuario' => '',
            'password' => '',
        ];

        try {
            require_once __DIR__ . '/../models/configuracion_general.php';
            $map = (new ConfiguracionGeneral())->leerPorClaves(['facturama_api_url', 'facturama_modo']);
            if (!empty($map['facturama_api_url'])) {
                $defaults['api_url'] = (string) $map['facturama_api_url'];
            }
            if (!empty($map['facturama_modo']) && strtolower((string) $map['facturama_modo']) === 'produccion') {
                $defaults['api_url'] = 'https://apis.facturama.mx';
            }
        } catch (Throwable $e) {
            error_log('FacturamaClient::resolverConfig: ' . $e->getMessage());
        }

        if (defined('JOYERIA_FACTURAMA_USUARIO')) {
            $defaults['usuario'] = trim((string) JOYERIA_FACTURAMA_USUARIO);
        }
        if (defined('JOYERIA_FACTURAMA_PASSWORD')) {
            $defaults['password'] = trim((string) JOYERIA_FACTURAMA_PASSWORD);
        }
        if (defined('JOYERIA_FACTURAMA_SANDBOX')) {
            $defaults['api_url'] = JOYERIA_FACTURAMA_SANDBOX
                ? 'https://apisandbox.facturama.mx'
                : 'https://apis.facturama.mx';
        }

        return $defaults;
    }

    public function credencialesConfiguradas(): bool
    {
        return $this->usuario !== '' && $this->password !== '';
    }

    /** @return array{ok:bool, data?:array, error?:string, http_code?:int} */
    public function timbrarCfdi(array $payload): array
    {
        return $this->request('POST', '/api-lite/3/cfdis', $payload);
    }

    /** @return array{ok:bool, body?:string, error?:string} */
    public function descargarPdf(string $idFacturama): array
    {
        $res = $this->requestRaw('GET', '/cfdi/pdf/issued/' . rawurlencode($idFacturama));
        return $res['ok'] ? ['ok' => true, 'body' => $res['body'] ?? ''] : $res;
    }

    /** @return array{ok:bool, body?:string, error?:string} */
    public function descargarXml(string $idFacturama): array
    {
        $res = $this->requestRaw('GET', '/cfdi/xml/issued/' . rawurlencode($idFacturama));
        return $res['ok'] ? ['ok' => true, 'body' => $res['body'] ?? ''] : $res;
    }

    /** @return array{ok:bool, data?:array, error?:string} */
    public function cancelarCfdi(string $idFacturama, string $motivo = '02'): array
    {
        return $this->request('DELETE', '/api-lite/cfdis/' . rawurlencode($idFacturama) . '?motive=' . rawurlencode($motivo));
    }

    /** @return array{ok:bool, data?:array, error?:string, http_code?:int} */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $raw = $this->requestRaw($method, $path, $body);
        if (!$raw['ok']) {
            return $raw;
        }
        $decoded = json_decode((string) ($raw['body'] ?? ''), true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'error' => 'Respuesta JSON invalida de Facturama.', 'http_code' => $raw['http_code'] ?? 0];
        }
        return ['ok' => true, 'data' => $decoded, 'http_code' => $raw['http_code'] ?? 200];
    }

    /** @return array{ok:bool, body?:string, error?:string, http_code?:int} */
    private function requestRaw(string $method, string $path, ?array $body = null): array
    {
        if (!$this->credencialesConfiguradas()) {
            return ['ok' => false, 'error' => 'Credenciales Facturama no configuradas.'];
        }

        $ch = curl_init($this->baseUrl . $path);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'No se pudo iniciar cURL.'];
        }

        $headers = [
            'Authorization: Basic ' . base64_encode($this->usuario . ':' . $this->password),
            'Accept: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'error' => 'Error de red: ' . $curlErr, 'http_code' => $httpCode];
        }
        if ($httpCode >= 400) {
            return ['ok' => false, 'error' => $this->extraerMensajeError((string) $response), 'http_code' => $httpCode, 'body' => (string) $response];
        }

        return ['ok' => true, 'body' => (string) $response, 'http_code' => $httpCode];
    }

    private function extraerMensajeError(string $body): string
    {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            if (!empty($decoded['Message'])) {
                return (string) $decoded['Message'];
            }
            if (!empty($decoded['message'])) {
                return (string) $decoded['message'];
            }
            if (!empty($decoded['ModelState']) && is_array($decoded['ModelState'])) {
                $parts = [];
                foreach ($decoded['ModelState'] as $field => $msgs) {
                    if (is_array($msgs)) {
                        $parts[] = $field . ': ' . implode('; ', array_map('strval', $msgs));
                    }
                }
                if ($parts !== []) {
                    return implode(' | ', $parts);
                }
            }
        }
        $trim = trim($body);
        return mb_strlen($trim) > 500 ? mb_substr($trim, 0, 500) . '...' : ($trim !== '' ? $trim : 'Error Facturama.');
    }
}
