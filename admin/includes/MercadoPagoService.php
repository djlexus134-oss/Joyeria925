<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

class MercadoPagoService
{
    private const API_BASE = 'https://api.mercadopago.com';

    public static function isConfigured(): bool
    {
        return defined('JOYERIA_MP_ACCESS_TOKEN') && trim((string) JOYERIA_MP_ACCESS_TOKEN) !== '';
    }

    /**
     * Crea una preferencia de pago.
     *
     * @param array $items items con keys: title, quantity, unit_price, currency_id
     * @param array $payer payer data con keys: name, email, phone
     * @param array $backUrls keys: success, failure, pending
     * @return array{ok:bool, error?:string, init_point?:string, sandbox_init_point?:string, id?:string}
     */
    public function crearPreference(int $idVenta, array $items, array $payer, array $backUrls, string $notificationUrl): array
    {
        if (!self::isConfigured()) {
            return ['ok' => false, 'error' => 'Mercado Pago no esta configurado (MP_ACCESS_TOKEN).'];
        }
        $body = [
            'items' => $items,
            'payer' => $payer,
            'back_urls' => $backUrls,
            'auto_return' => 'approved',
            'binary_mode' => false,
            'external_reference' => 'venta_' . $idVenta,
            'statement_descriptor' => 'PLATERIA ANGEL',
            'metadata' => [
                'id_venta' => $idVenta,
            ],
        ];
        if ($notificationUrl !== '') {
            $body['notification_url'] = $notificationUrl;
        }

        $resp = $this->httpJson('POST', '/checkout/preferences', $body);
        if (!$resp['ok']) {
            return ['ok' => false, 'error' => $resp['error'] ?? 'Error al crear preferencia.'];
        }
        $data = $resp['data'] ?? [];
        return [
            'ok' => true,
            'id' => (string) ($data['id'] ?? ''),
            'init_point' => (string) ($data['init_point'] ?? ''),
            'sandbox_init_point' => (string) ($data['sandbox_init_point'] ?? ''),
        ];
    }

    public function verificarPago(string $paymentId): array
    {
        if (trim($paymentId) === '') return ['ok' => false, 'error' => 'paymentId vacio'];
        $resp = $this->httpJson('GET', '/v1/payments/' . urlencode($paymentId));
        if (!$resp['ok']) return ['ok' => false, 'error' => $resp['error'] ?? 'Error consultando pago.'];
        return ['ok' => true, 'data' => $resp['data'] ?? []];
    }

    public function consultarMerchantOrder(string $merchantOrderId): array
    {
        if (trim($merchantOrderId) === '') return ['ok' => false, 'error' => 'merchantOrderId vacio'];
        $resp = $this->httpJson('GET', '/merchant_orders/' . urlencode($merchantOrderId));
        if (!$resp['ok']) return ['ok' => false, 'error' => $resp['error'] ?? 'Error consultando orden.'];
        return ['ok' => true, 'data' => $resp['data'] ?? []];
    }

    /**
     * Valida firma HMAC del webhook (cuando MP_WEBHOOK_SECRET esta configurado).
     * Si no hay secreto definido, devuelve true (modo permisivo).
     * Prueba con JOYERIA_MP_WEBHOOK_SECRET y, si existe, con
     * JOYERIA_MP_WEBHOOK_SECRET_PROD, porque MP usa secrets distintos por
     * pestana (test/prod) y a veces firma con uno u otro.
     */
    public function verificarFirmaWebhook(array $headers, string $dataIdFromQuery): bool
    {
        $secrets = [];
        if (defined('JOYERIA_MP_WEBHOOK_SECRET')) {
            $s = trim((string) JOYERIA_MP_WEBHOOK_SECRET);
            if ($s !== '') $secrets[] = $s;
        }
        if (defined('JOYERIA_MP_WEBHOOK_SECRET_PROD')) {
            $s = trim((string) JOYERIA_MP_WEBHOOK_SECRET_PROD);
            if ($s !== '' && !in_array($s, $secrets, true)) $secrets[] = $s;
        }
        if ($secrets === []) {
            return true;
        }

        $signatureHeader = '';
        $requestIdHeader = '';
        foreach ($headers as $k => $v) {
            $kl = strtolower((string) $k);
            if ($kl === 'x-signature') $signatureHeader = (string) $v;
            if ($kl === 'x-request-id') $requestIdHeader = (string) $v;
        }
        if ($signatureHeader === '' || $requestIdHeader === '') {
            return false;
        }

        $ts = '';
        $v1 = '';
        foreach (explode(',', $signatureHeader) as $part) {
            $part = trim($part);
            if (strpos($part, 'ts=') === 0) $ts = substr($part, 3);
            if (strpos($part, 'v1=') === 0) $v1 = substr($part, 3);
        }
        if ($ts === '' || $v1 === '') return false;

        $idCandidatos = [];
        $idCandidatos[] = $dataIdFromQuery;
        if ($dataIdFromQuery !== '' && !ctype_digit($dataIdFromQuery)) {
            $idCandidatos[] = strtolower($dataIdFromQuery);
        }

        foreach ($secrets as $secret) {
            foreach (array_unique($idCandidatos) as $idManifest) {
                $manifest = 'id:' . $idManifest . ';request-id:' . $requestIdHeader . ';ts:' . $ts . ';';
                $calc = hash_hmac('sha256', $manifest, $secret);
                if (hash_equals($calc, $v1)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Diagnostico extendido: devuelve toda la info usada para calcular la firma
     * (sin exponer el secret). Ideal para depurar firmas invalidas.
     *
     * @return array{
     *   ok:bool, ts:string, v1_recibido:string, request_id:string,
     *   data_id:string, manifests:array<int,string>, calculados:array<int,string>,
     *   secrets_probados:int, motivo:string
     * }
     */
    public function diagnosticarFirmaWebhook(array $headers, string $dataIdFromQuery): array
    {
        $secrets = [];
        if (defined('JOYERIA_MP_WEBHOOK_SECRET')) {
            $s = trim((string) JOYERIA_MP_WEBHOOK_SECRET);
            if ($s !== '') $secrets[] = $s;
        }
        if (defined('JOYERIA_MP_WEBHOOK_SECRET_PROD')) {
            $s = trim((string) JOYERIA_MP_WEBHOOK_SECRET_PROD);
            if ($s !== '' && !in_array($s, $secrets, true)) $secrets[] = $s;
        }

        $signatureHeader = '';
        $requestIdHeader = '';
        foreach ($headers as $k => $v) {
            $kl = strtolower((string) $k);
            if ($kl === 'x-signature') $signatureHeader = (string) $v;
            if ($kl === 'x-request-id') $requestIdHeader = (string) $v;
        }

        $ts = '';
        $v1 = '';
        foreach (explode(',', $signatureHeader) as $part) {
            $part = trim($part);
            if (strpos($part, 'ts=') === 0) $ts = substr($part, 3);
            if (strpos($part, 'v1=') === 0) $v1 = substr($part, 3);
        }

        $idCandidatos = [$dataIdFromQuery];
        if ($dataIdFromQuery !== '' && !ctype_digit($dataIdFromQuery)) {
            $idCandidatos[] = strtolower($dataIdFromQuery);
        }
        $idCandidatos = array_values(array_unique($idCandidatos));

        $manifests = [];
        $calculados = [];
        $ok = false;
        foreach ($secrets as $secret) {
            foreach ($idCandidatos as $idManifest) {
                $manifest = 'id:' . $idManifest . ';request-id:' . $requestIdHeader . ';ts:' . $ts . ';';
                $manifests[] = $manifest;
                $calc = hash_hmac('sha256', $manifest, $secret);
                $calculados[] = $calc;
                if ($v1 !== '' && hash_equals($calc, $v1)) {
                    $ok = true;
                }
            }
        }

        $motivo = 'ok';
        if ($secrets === []) {
            $motivo = 'sin_secret_configurado';
        } elseif ($signatureHeader === '') {
            $motivo = 'falta_header_x-signature';
        } elseif ($requestIdHeader === '') {
            $motivo = 'falta_header_x-request-id';
        } elseif ($ts === '' || $v1 === '') {
            $motivo = 'header_x-signature_mal_formado';
        } elseif (!$ok) {
            $motivo = 'hmac_no_coincide';
        }

        return [
            'ok' => $ok,
            'ts' => $ts,
            'v1_recibido' => $v1,
            'request_id' => $requestIdHeader,
            'data_id' => $dataIdFromQuery,
            'manifests' => $manifests,
            'calculados' => $calculados,
            'secrets_probados' => count($secrets),
            'motivo' => $motivo,
        ];
    }

    /**
     * @return array{ok:bool, data?:array, error?:string, http?:int}
     */
    private function httpJson(string $method, string $path, ?array $body = null): array
    {
        $url = self::API_BASE . $path;
        $token = (string) JOYERIA_MP_ACCESS_TOKEN;

        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Idempotency-Key: ' . bin2hex(random_bytes(16)),
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'error' => 'cURL: ' . $err, 'http' => 0];
        }
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'Respuesta MP no es JSON', 'http' => $http];
        }
        if ($http >= 200 && $http < 300) {
            return ['ok' => true, 'data' => $data, 'http' => $http];
        }
        return ['ok' => false, 'error' => 'MP ' . $http . ': ' . ($data['message'] ?? json_encode($data)), 'http' => $http];
    }
}
