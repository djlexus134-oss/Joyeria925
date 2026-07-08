<?php
require_once __DIR__ . '/bootstrap.php';

function api_json_body(): array
{
    $raw = joyeria_request_raw_body();
    if ($raw === '' || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        api_fail('JSON invalido.', 400);
    }

    return $decoded;
}

function api_ok(array $data = [], int $status = 200): void
{
    joyeria_json_clean_buffer();
    http_response_code($status);
    echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

function api_fail(string $message, int $status = 400, array $extra = []): void
{
    joyeria_json_clean_buffer();
    http_response_code($status);
    echo json_encode(array_merge(['success' => false, 'error' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function api_require_method(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper($method)) {
        api_fail('Metodo no permitido.', 405);
    }
}

function api_int(?array $data, string $key, bool $required = true): ?int
{
    if ($data === null || !array_key_exists($key, $data) || $data[$key] === '' || $data[$key] === null) {
        if ($required) {
            api_fail('Falta el campo requerido: ' . $key, 422);
        }
        return null;
    }

    return (int) $data[$key];
}

function api_string(?array $data, string $key, bool $required = true): ?string
{
    if ($data === null || !array_key_exists($key, $data)) {
        if ($required) {
            api_fail('Falta el campo requerido: ' . $key, 422);
        }
        return null;
    }

    $value = trim((string) $data[$key]);
    if ($value === '') {
        if ($required) {
            api_fail('Falta el campo requerido: ' . $key, 422);
        }
        return null;
    }

    return $value;
}

function api_bcrypt(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}
