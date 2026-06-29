<?php
/**
 * Clase ContratoEmpleado - Modelo para gestión de contratos
 * Maneja CRUD completo con soft delete y generación de PDFs
 */

require_once __DIR__ . '/../includes/list_search.php';

class ContratoEmpleado extends Sistema {
    
    private $tabla = 'contratos_empleados';
    const TYPES = ['Indeterminado', 'Tiempo Determinado', 'Obra Determinada', 'Periodo de Prueba', 'Capacitacion Inicial'];
    
    /**
     * Listado de contratos activos con datos del empleado (filtro opcional por texto).
     */
    public function leer(?string $busqueda = null) {
        try {
            $pat = joyeria_like_pattern($busqueda);
            $sql = "
                SELECT 
                    c.id_contrato,
                    c.id_empleado_FK,
                    c.tipo_contrato,
                    c.fecha_inicio,
                    c.fecha_fin,
                    c.observaciones,
                    c.ruta_archivo,
                    c.activo,
                    c.fecha_registro,
                    c.fecha_baja,
                    c.id_usuario_baja,
                    e.id_empleado,
                    u.nombre AS empleado_nombre,
                    u.primer_apellido AS empleado_primer_apellido,
                    u.segundo_apellido AS empleado_segundo_apellido,
                    u.correo AS empleado_correo,
                    u.telefono AS empleado_telefono,
                    p.nombre_puesto AS empleado_puesto,
                    ub.nombre AS nombre_usuario_baja,
                    ub.primer_apellido AS apellido_usuario_baja
                FROM $this->tabla c
                INNER JOIN empleados e ON c.id_empleado_FK = e.id_empleado
                INNER JOIN usuarios u ON e.id_usuario_FK = u.id_usuario
                INNER JOIN puestos p ON e.id_puesto_FK = p.id_puesto
                LEFT JOIN usuarios ub ON c.id_usuario_baja = ub.id_usuario
                WHERE c.activo = 1
            ";
            if ($pat !== null) {
                $sql .= " AND (
                    u.nombre LIKE :busq OR u.primer_apellido LIKE :busq2 OR IFNULL(u.segundo_apellido, '') LIKE :busq3
                    OR u.correo LIKE :busq4 OR u.telefono LIKE :busq5 OR p.nombre_puesto LIKE :busq6
                    OR c.tipo_contrato LIKE :busq7 OR IFNULL(c.observaciones, '') LIKE :busq8
                    OR CAST(c.id_contrato AS CHAR) LIKE :busq9
                )";
            }
            $sql .= " ORDER BY c.fecha_registro DESC";

            $stmt = $this->getDb()->prepare($sql);
            if ($pat !== null) {
                $stmt->bindValue(':busq', $pat, PDO::PARAM_STR);
                $stmt->bindValue(':busq2', $pat, PDO::PARAM_STR);
                $stmt->bindValue(':busq3', $pat, PDO::PARAM_STR);
                $stmt->bindValue(':busq4', $pat, PDO::PARAM_STR);
                $stmt->bindValue(':busq5', $pat, PDO::PARAM_STR);
                $stmt->bindValue(':busq6', $pat, PDO::PARAM_STR);
                $stmt->bindValue(':busq7', $pat, PDO::PARAM_STR);
                $stmt->bindValue(':busq8', $pat, PDO::PARAM_STR);
                $stmt->bindValue(':busq9', $pat, PDO::PARAM_STR);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en ContratoEmpleado::leer() - " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * CREAR nuevo contrato
     */
    public function crear($datos) {
        try {
            if (empty($datos['id_empleado_FK']) || empty($datos['tipo_contrato']) || empty($datos['fecha_inicio'])) {
                return [
                    'success' => false,
                    'id' => null,
                    'message' => 'Faltan datos requeridos'
                ];
            }
            
            $sqlCheck = "SELECT id_empleado FROM empleados WHERE id_empleado = ? AND activo = 1";
            $stmtCheck = $this->getDb()->prepare($sqlCheck);
            $stmtCheck->execute([$datos['id_empleado_FK']]);
            
            if (!$stmtCheck->fetch()) {
                return [
                    'success' => false,
                    'id' => null,
                    'message' => 'El empleado no existe o está inactivo'
                ];
            }
            
            $sql = "
                INSERT INTO $this->tabla 
                (id_empleado_FK, tipo_contrato, fecha_inicio, fecha_fin, observaciones, ruta_archivo, activo, fecha_registro)
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
            ";
            
            $stmt = $this->getDb()->prepare($sql);
            $result = $stmt->execute([
                $datos['id_empleado_FK'],
                $datos['tipo_contrato'],
                $datos['fecha_inicio'],
                $datos['fecha_fin'] ?? null,
                $datos['observaciones'] ?? null,
                $datos['ruta_archivo'] ?? null
            ]);
            
            if (!$result) {
                return [
                    'success' => false,
                    'id' => null,
                    'message' => 'Error al insertar'
                ];
            }
            
            $id = $this->getDb()->lastInsertId();
            
            return [
                'success' => true,
                'id' => $id,
                'message' => 'Contrato creado exitosamente'
            ];
            
        } catch (PDOException $e) {
            error_log("Error en ContratoEmpleado::crear() - " . $e->getMessage());
            return [
                'success' => false,
                'id' => null,
                'message' => 'Error BD: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ACTUALIZAR contrato existente
     */
    public function actualizar($id, $datos) {
        try {
            $sqlCheck = "SELECT id_contrato FROM $this->tabla WHERE id_contrato = ? AND activo = 1";
            $stmtCheck = $this->getDb()->prepare($sqlCheck);
            $stmtCheck->execute([$id]);
            
            if (!$stmtCheck->fetch()) {
                return [
                    'success' => false,
                    'message' => 'El contrato no existe o está inactivo'
                ];
            }
            
            $campos = [];
            $valores = [];
            $permitidos = ['tipo_contrato', 'fecha_inicio', 'fecha_fin', 'observaciones', 'ruta_archivo'];
            
            foreach ($permitidos as $campo) {
                if (isset($datos[$campo])) {
                    $campos[] = "$campo = ?";
                    $valores[] = $datos[$campo];
                }
            }
            
            if (empty($campos)) {
                return [
                    'success' => false,
                    'message' => 'No hay datos para actualizar'
                ];
            }
            
            $valores[] = $id;
            
            $sql = "UPDATE $this->tabla SET " . implode(', ', $campos) . " WHERE id_contrato = ?";
            
            $stmt = $this->getDb()->prepare($sql);
            $result = $stmt->execute($valores);
            
            return [
                'success' => $result,
                'message' => $result ? 'Actualizado' : 'Error al actualizar'
            ];
            
        } catch (PDOException $e) {
            error_log("Error en ContratoEmpleado::actualizar() - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error BD: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ELIMINAR (baja lógica)
     */
    public function eliminar($id, $id_usuario_baja = null) {
        try {
            $id_usuario_baja = isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : null;
            $sqlCheck = "SELECT id_contrato FROM $this->tabla WHERE id_contrato = ? AND activo = 1";
            $stmtCheck = $this->getDb()->prepare($sqlCheck);
            $stmtCheck->execute([$id]);
            
            if (!$stmtCheck->fetch()) {
                return [
                    'success' => false,
                    'message' => 'El contrato no existe o ya está inactivo'
                ];
            }
            
            $sql = "
                UPDATE $this->tabla 
                SET activo = 0, fecha_baja = NOW(), id_usuario_baja = ?
                WHERE id_contrato = ?
            ";
            
            $stmt = $this->getDb()->prepare($sql);
            $result = $stmt->execute([$id_usuario_baja, $id]);
            
            return [
                'success' => $result,
                'message' => $result ? 'Dado de baja' : 'Error'
            ];
            
        } catch (PDOException $e) {
            error_log("Error en ContratoEmpleado::eliminar() - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error BD: ' . $e->getMessage()
            ];
        }
    }
    
    public function leerUno($id) {
        try {
            $sql = "
                SELECT 
                    c.id_contrato,
                    c.id_empleado_FK,
                    c.tipo_contrato,
                    c.fecha_inicio,
                    c.fecha_fin,
                    c.observaciones,
                    c.ruta_archivo,
                    c.activo,
                    c.fecha_registro,
                    c.fecha_baja,
                    c.id_usuario_baja,
                    e.id_empleado,
                    u.nombre AS empleado_nombre,
                    u.primer_apellido AS empleado_primer_apellido,
                    u.segundo_apellido AS empleado_segundo_apellido,
                    u.correo AS empleado_correo,
                    u.telefono AS empleado_telefono,
                    p.nombre_puesto AS empleado_puesto,
                    ub.nombre AS nombre_usuario_baja,
                    ub.primer_apellido AS apellido_usuario_baja
                FROM $this->tabla c
                INNER JOIN empleados e ON c.id_empleado_FK = e.id_empleado
                INNER JOIN usuarios u ON e.id_usuario_FK = u.id_usuario
                INNER JOIN puestos p ON e.id_puesto_FK = p.id_puesto
                LEFT JOIN usuarios ub ON c.id_usuario_baja = ub.id_usuario
                WHERE c.activo = 1 AND c.id_contrato = ?
            ";
            $stmt = $this->getDb()->prepare($sql);
            $stmt->execute([(int) $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en ContratoEmpleado::leerUno() - " . $e->getMessage());
            throw $e;
        }
    }
    
    public function borrar($id, $id_usuario_baja = null) {
        return $this->eliminar($id, $id_usuario_baja);
    }
    
    public function obtenerPorId($id) {
        $resultado = $this->leerUno($id);
        if (!$resultado) {
            throw new Exception("Contrato con ID $id no encontrado");
        }
        return $resultado;
    }
    
    /**
     * ACTUALIZAR RUTA PDF
     */
    public function actualizarRutaPDF($id, $ruta) {
        try {
            $sql = "UPDATE $this->tabla SET ruta_archivo = ? WHERE id_contrato = ?";
            $stmt = $this->getDb()->prepare($sql);
            $result = $stmt->execute([$ruta, $id]);
            
            return [
                'success' => $result,
                'message' => $result ? 'PDF actualizado' : 'Error'
            ];
            
        } catch (PDOException $e) {
            error_log("Error en ContratoEmpleado::actualizarRutaPDF() - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error BD: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * LEER contratos de un empleado
     */
    public function obtenerPorEmpleado($id_empleado) {
        try {
            $sql = "
                SELECT 
                    c.*,
                    u.nombre,
                    u.primer_apellido,
                    u.segundo_apellido,
                    p.nombre_puesto
                FROM $this->tabla c
                INNER JOIN empleados e ON c.id_empleado_FK = e.id_empleado
                INNER JOIN usuarios u ON e.id_usuario_FK = u.id_usuario
                INNER JOIN puestos p ON e.id_puesto_FK = p.id_puesto
                WHERE c.id_empleado_FK = ? AND c.activo = 1
                ORDER BY c.fecha_registro DESC
            ";
            
            $stmt = $this->getDb()->prepare($sql);
            $stmt->execute([$id_empleado]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error en ContratoEmpleado::obtenerPorEmpleado() - " . $e->getMessage());
            throw $e;
        }
    }
    
    public function leerPorEmpleado($id_empleado) {
        return $this->obtenerPorEmpleado($id_empleado);
    }
}
?>
