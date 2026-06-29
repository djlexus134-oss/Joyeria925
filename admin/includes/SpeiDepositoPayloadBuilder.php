<?php

class SpeiDepositoPayloadBuilder
{
    public static function normalizarClabe(string $clabe): string
    {
        return preg_replace('/\D/', '', trim($clabe)) ?? '';
    }

    public static function validarClabe(string $clabe): bool
    {
        $clabe = self::normalizarClabe($clabe);
        if (strlen($clabe) !== 18 || !ctype_digit($clabe)) {
            return false;
        }

        $pesos = [3, 7, 1];
        $suma = 0;
        for ($i = 0; $i < 17; $i++) {
            $suma += ((int) $clabe[$i] * $pesos[$i % 3]) % 10;
        }
        $control = (10 - ($suma % 10)) % 10;

        return (int) $clabe[17] === $control;
    }

    /**
     * @param array{beneficiario?:string,banco?:string,clabe?:string,instrucciones?:string,referencia_prefijo?:string} $config
     */
    public static function construirReferencia(array $config, ?DateTimeInterface $fecha = null): string
    {
        $prefijo = trim((string) ($config['referencia_prefijo'] ?? 'VENTA'));
        if ($prefijo === '') {
            $prefijo = 'VENTA';
        }
        $prefijo = preg_replace('/[^A-Za-z0-9_-]/', '', $prefijo) ?? 'VENTA';
        if ($prefijo === '') {
            $prefijo = 'VENTA';
        }

        $fecha = $fecha ?? new DateTimeImmutable('now');
        $sufijo = $fecha->format('Ymd-His');

        return $prefijo . '-' . $sufijo;
    }

    public static function formatearMonto(float $monto): string
    {
        return '$' . number_format(max(0, $monto), 2, '.', ',') . ' MXN';
    }

    /**
     * @param array{beneficiario?:string,banco?:string,clabe?:string,instrucciones?:string,referencia_prefijo?:string} $config
     */
    public static function construirTexto(array $config, float $monto, ?string $referencia = null): string
    {
        $beneficiario = trim((string) ($config['beneficiario'] ?? ''));
        $banco = trim((string) ($config['banco'] ?? ''));
        $clabe = self::normalizarClabe((string) ($config['clabe'] ?? ''));
        $instrucciones = trim((string) ($config['instrucciones'] ?? ''));
        $referencia = trim((string) ($referencia ?? ''));
        if ($referencia === '') {
            $referencia = self::construirReferencia($config);
        }

        $lineas = ['Transferencia SPEI'];
        if ($beneficiario !== '') {
            $lineas[] = 'Beneficiario: ' . $beneficiario;
        }
        if ($banco !== '') {
            $lineas[] = 'Banco: ' . $banco;
        }
        if ($clabe !== '') {
            $lineas[] = 'CLABE: ' . $clabe;
        }
        $lineas[] = 'Monto: ' . self::formatearMonto($monto);
        $lineas[] = 'Concepto: ' . $referencia;
        if ($instrucciones !== '') {
            $lineas[] = 'Instrucciones: ' . $instrucciones;
        }

        return implode("\n", $lineas);
    }
}
