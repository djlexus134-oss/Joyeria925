<?php
require_once __DIR__ . "/../../sistema.class.php";

class DireccionCatalogo extends Sistema
{
    private function fetchAllFromCall($sql, $bindings = [])
    {
        $stmt = $this->getDb()->prepare($sql);
        foreach ($bindings as $key => $value) {
            if (is_null($value)) {
                $stmt->bindValue($key, null, PDO::PARAM_NULL);
            } elseif (is_int($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
        }

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return $rows;
    }

    private function executeCall($sql, $bindings = [])
    {
        $stmt = $this->getDb()->prepare($sql);
        foreach ($bindings as $key => $value) {
            if (is_null($value)) {
                $stmt->bindValue($key, null, PDO::PARAM_NULL);
            } elseif (is_int($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
        }

        $stmt->execute();
        $stmt->closeCursor();
        return true;
    }

    public function leerPaises($id = null)
    {
        return $this->fetchAllFromCall(
            "CALL sp_crud_paises('READ', :id, NULL)",
            [':id' => $id]
        );
    }

    public function crearPais($nombre)
    {
        return $this->executeCall(
            "CALL sp_crud_paises('CREATE', NULL, :nombre)",
            [':nombre' => $nombre]
        );
    }

    public function actualizarPais($id, $nombre)
    {
        return $this->executeCall(
            "CALL sp_crud_paises('UPDATE', :id, :nombre)",
            [':id' => $id, ':nombre' => $nombre]
        );
    }

    public function borrarPais($id)
    {
        return $this->executeCall(
            "CALL sp_crud_paises('DELETE', :id, NULL)",
            [':id' => $id]
        );
    }

    public function leerEstados($id = null)
    {
        return $this->fetchAllFromCall(
            "CALL sp_crud_estados('READ', :id, NULL, NULL)",
            [':id' => $id]
        );
    }

    public function crearEstado($nombre, $idPais)
    {
        return $this->executeCall(
            "CALL sp_crud_estados('CREATE', NULL, :nombre, :id_pais)",
            [':nombre' => $nombre, ':id_pais' => $idPais]
        );
    }

    public function actualizarEstado($id, $nombre, $idPais)
    {
        return $this->executeCall(
            "CALL sp_crud_estados('UPDATE', :id, :nombre, :id_pais)",
            [':id' => $id, ':nombre' => $nombre, ':id_pais' => $idPais]
        );
    }

    public function borrarEstado($id)
    {
        return $this->executeCall(
            "CALL sp_crud_estados('DELETE', :id, NULL, NULL)",
            [':id' => $id]
        );
    }

    public function leerMunicipios($id = null)
    {
        return $this->fetchAllFromCall(
            "CALL sp_crud_municipios('READ', :id, NULL, NULL)",
            [':id' => $id]
        );
    }

    public function crearMunicipio($nombre, $idEstado)
    {
        return $this->executeCall(
            "CALL sp_crud_municipios('CREATE', NULL, :nombre, :id_estado)",
            [':nombre' => $nombre, ':id_estado' => $idEstado]
        );
    }

    public function actualizarMunicipio($id, $nombre, $idEstado)
    {
        return $this->executeCall(
            "CALL sp_crud_municipios('UPDATE', :id, :nombre, :id_estado)",
            [':id' => $id, ':nombre' => $nombre, ':id_estado' => $idEstado]
        );
    }

    public function borrarMunicipio($id)
    {
        return $this->executeCall(
            "CALL sp_crud_municipios('DELETE', :id, NULL, NULL)",
            [':id' => $id]
        );
    }

    public function leerLocalidades($id = null)
    {
        return $this->fetchAllFromCall(
            "CALL sp_crud_localidades('READ', :id, NULL, NULL)",
            [':id' => $id]
        );
    }

    public function crearLocalidad($nombre, $idMunicipio)
    {
        return $this->executeCall(
            "CALL sp_crud_localidades('CREATE', NULL, :nombre, :id_municipio)",
            [':nombre' => $nombre, ':id_municipio' => $idMunicipio]
        );
    }

    public function actualizarLocalidad($id, $nombre, $idMunicipio)
    {
        return $this->executeCall(
            "CALL sp_crud_localidades('UPDATE', :id, :nombre, :id_municipio)",
            [':id' => $id, ':nombre' => $nombre, ':id_municipio' => $idMunicipio]
        );
    }

    public function borrarLocalidad($id)
    {
        return $this->executeCall(
            "CALL sp_crud_localidades('DELETE', :id, NULL, NULL)",
            [':id' => $id]
        );
    }

    public function leerCodigosPostales($id = null)
    {
        return $this->fetchAllFromCall(
            "CALL sp_crud_codigos_postales('READ', :id, NULL)",
            [':id' => $id]
        );
    }

    public function crearCodigoPostal($codigo)
    {
        return $this->executeCall(
            "CALL sp_crud_codigos_postales('CREATE', NULL, :codigo)",
            [':codigo' => $codigo]
        );
    }

    public function actualizarCodigoPostal($id, $codigo)
    {
        return $this->executeCall(
            "CALL sp_crud_codigos_postales('UPDATE', :id, :codigo)",
            [':id' => $id, ':codigo' => $codigo]
        );
    }

    public function borrarCodigoPostal($id)
    {
        return $this->executeCall(
            "CALL sp_crud_codigos_postales('DELETE', :id, NULL)",
            [':id' => $id]
        );
    }

    public function leerColonias($id = null)
    {
        return $this->fetchAllFromCall(
            "CALL sp_crud_colonias('READ', :id, NULL, NULL, NULL)",
            [':id' => $id]
        );
    }

    public function crearColonia($nombre, $idLocalidad, $idCodigoPostal)
    {
        return $this->executeCall(
            "CALL sp_crud_colonias('CREATE', NULL, :nombre, :id_localidad, :id_codigo_postal)",
            [
                ':nombre' => $nombre,
                ':id_localidad' => $idLocalidad,
                ':id_codigo_postal' => $idCodigoPostal,
            ]
        );
    }

    public function actualizarColonia($id, $nombre, $idLocalidad, $idCodigoPostal)
    {
        return $this->executeCall(
            "CALL sp_crud_colonias('UPDATE', :id, :nombre, :id_localidad, :id_codigo_postal)",
            [
                ':id' => $id,
                ':nombre' => $nombre,
                ':id_localidad' => $idLocalidad,
                ':id_codigo_postal' => $idCodigoPostal,
            ]
        );
    }

    public function borrarColonia($id)
    {
        return $this->executeCall(
            "CALL sp_crud_colonias('DELETE', :id, NULL, NULL, NULL)",
            [':id' => $id]
        );
    }

    public function leerCalles($id = null)
    {
        return $this->fetchAllFromCall(
            "CALL sp_crud_calles('READ', :id, NULL, NULL)",
            [':id' => $id]
        );
    }

    public function crearCalle($nombre, $idColonia)
    {
        return $this->executeCall(
            "CALL sp_crud_calles('CREATE', NULL, :nombre, :id_colonia)",
            [':nombre' => $nombre, ':id_colonia' => $idColonia]
        );
    }

    public function actualizarCalle($id, $nombre, $idColonia)
    {
        return $this->executeCall(
            "CALL sp_crud_calles('UPDATE', :id, :nombre, :id_colonia)",
            [':id' => $id, ':nombre' => $nombre, ':id_colonia' => $idColonia]
        );
    }

    public function borrarCalle($id)
    {
        return $this->executeCall(
            "CALL sp_crud_calles('DELETE', :id, NULL, NULL)",
            [':id' => $id]
        );
    }

    public function leerDirecciones($id = null)
    {
        return $this->fetchAllFromCall(
            "CALL sp_crud_direcciones('READ', :id, NULL, NULL, NULL)",
            [':id' => $id]
        );
    }

    public function crearDireccion($numExterior, $numInterior, $idCalle)
    {
        return $this->executeCall(
            "CALL sp_crud_direcciones('CREATE', NULL, :num_exterior, :num_interior, :id_calle)",
            [':num_exterior' => $numExterior, ':num_interior' => $numInterior, ':id_calle' => $idCalle]
        );
    }

    public function actualizarDireccion($id, $numExterior, $numInterior, $idCalle)
    {
        return $this->executeCall(
            "CALL sp_crud_direcciones('UPDATE', :id, :num_exterior, :num_interior, :id_calle)",
            [
                ':id' => $id,
                ':num_exterior' => $numExterior,
                ':num_interior' => $numInterior,
                ':id_calle' => $idCalle,
            ]
        );
    }

    public function borrarDireccion($id)
    {
        return $this->executeCall(
            "CALL sp_crud_direcciones('DELETE', :id, NULL, NULL, NULL)",
            [':id' => $id]
        );
    }
}
