<?php
/**
 * Configuración del patrón para PDFs de contratos (tabla configuracion_general).
 */
require_once __DIR__ . '/../models/configuracion_general.php';

function joyeria_config_contrato_laboral(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    try {
        $config = new ConfiguracionGeneral();
        $cache = $config->leerConfigContratoLaboral();
    } catch (Throwable $e) {
        error_log('contrato_laboral_config: ' . $e->getMessage());
        $cache = [
            'ciudad' => 'Ciudad, Estado',
            'domicilio_fuente_trabajo' => 'Domicilio de la fuente de trabajo',
            'nombre_patron' => 'Nombre del patrón',
            'tribunal_ciudad' => 'Ciudad de los tribunales',
            'jornada_horas_semanales' => 48,
            'nacionalidad_default' => 'Mexicana',
        ];
    }

    return $cache;
}

return joyeria_config_contrato_laboral();
