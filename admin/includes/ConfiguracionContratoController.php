<?php
require_once __DIR__ . '/../models/configuracion_general.php';

class ConfiguracionContratoController
{
    private ConfiguracionGeneral $config;

    public function __construct()
    {
        $this->config = new ConfiguracionGeneral();
    }

    public function valoresActuales(): array
    {
        return $this->config->leerConfigContratoLaboral();
    }

    public function guardar(array $post): void
    {
        $campos = [
            ['clave' => 'contrato_ciudad', 'post' => 'contrato_ciudad', 'tipo' => 'STRING', 'desc' => 'Ciudad de firma del contrato'],
            ['clave' => 'contrato_domicilio_fuente_trabajo', 'post' => 'contrato_domicilio_fuente_trabajo', 'tipo' => 'STRING', 'desc' => 'Domicilio de la fuente de trabajo'],
            ['clave' => 'contrato_nombre_patron', 'post' => 'contrato_nombre_patron', 'tipo' => 'STRING', 'desc' => 'Nombre del patrón'],
            ['clave' => 'contrato_tribunal_ciudad', 'post' => 'contrato_tribunal_ciudad', 'tipo' => 'STRING', 'desc' => 'Ciudad de tribunales laborales'],
            ['clave' => 'contrato_nacionalidad_default', 'post' => 'contrato_nacionalidad_default', 'tipo' => 'STRING', 'desc' => 'Nacionalidad por defecto en contratos'],
            ['clave' => 'contrato_jornada_horas_semanales', 'post' => 'contrato_jornada_horas_semanales', 'tipo' => 'INT', 'desc' => 'Jornada semanal en horas'],
        ];

        foreach ($campos as $campo) {
            $key = $campo['post'];
            if (!array_key_exists($key, $post)) {
                continue;
            }
            $valor = trim((string) $post[$key]);
            if ($valor === '') {
                throw new InvalidArgumentException('Todos los campos son obligatorios.');
            }
            if ($campo['tipo'] === 'INT') {
                $horas = (int) $valor;
                if ($horas < 1 || $horas > 72) {
                    throw new InvalidArgumentException('La jornada semanal debe estar entre 1 y 72 horas.');
                }
                $valor = (string) $horas;
            }
            $this->config->guardarPorClave($campo['clave'], $valor, $campo['tipo'], $campo['desc']);
        }
    }
}
