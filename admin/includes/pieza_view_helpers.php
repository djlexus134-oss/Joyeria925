<?php
/**
 * Helpers de navegacion entre vista completa de pieza y gestion solo de foto.
 */

function joyeria_pieza_origen_es_gestion_foto(): bool
{
    $candidatos = [
        $_GET['origen'] ?? null,
        $_POST['origen'] ?? null,
    ];

    foreach ($candidatos as $valor) {
        if (is_string($valor) && strtolower(trim($valor)) === 'foto') {
            return true;
        }
    }

    return false;
}

/**
 * Carga foto.php o formulario.php segun origen=foto (pantalla Editar foto).
 */
function joyeria_pieza_cargar_vista_tras_galeria(Pieza $app, ?int $idPieza, ?string $mensaje): void
{
    if ($idPieza === null || $idPieza <= 0) {
        global $busqueda, $campoBusquedaPieza;
        $piezas = $app->leer(
            isset($busqueda) ? $busqueda : null,
            isset($campoBusquedaPieza) ? (string) $campoBusquedaPieza : 'global'
        );
        require_once __DIR__ . '/../views/pieza/index.php';
        return;
    }

    $pieza = $app->leerUno($idPieza);
    $imagenesPieza = $app->leerImagenes($idPieza);

    if (joyeria_pieza_origen_es_gestion_foto()) {
        require_once __DIR__ . '/../views/pieza/foto.php';
        return;
    }

    require_once __DIR__ . '/../views/pieza/formulario.php';
}
