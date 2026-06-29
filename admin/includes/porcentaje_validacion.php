<?php

/**
 * Normaliza un porcentaje entre 0 y 100 con hasta 2 decimales.
 *
 * @param mixed $valor
 * @param bool $permitirNull Si true, vacio/null devuelve null
 * @param string $etiquetaError Etiqueta para mensajes de excepcion
 * @return string|null Valor con formato "12.34" o null
 */
function joyeria_normalizar_porcentaje_0_100($valor, bool $permitirNull = true, string $etiquetaError = 'El porcentaje'): ?string
{
    if ($valor === null || trim((string) $valor) === '') {
        if ($permitirNull) {
            return null;
        }
        throw new InvalidArgumentException($etiquetaError . ' es requerido.');
    }

    $texto = trim((string) $valor);
    $texto = str_replace(',', '.', $texto);

    if (!is_numeric($texto)) {
        throw new InvalidArgumentException($etiquetaError . ' debe ser numerico.');
    }

    $numero = (float) $texto;
    if ($numero < 0 || $numero > 100) {
        throw new InvalidArgumentException($etiquetaError . ' debe estar entre 0 y 100.');
    }

    $epsilon = 0.0005;
    $ajustado = $numero + ($numero >= 0 ? $epsilon : -$epsilon);

    return number_format(round($ajustado, 2), 2, '.', '');
}
