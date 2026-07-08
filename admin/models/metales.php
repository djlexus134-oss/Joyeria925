<?php
require_once __DIR__ . "/../../sistema.class.php";
require_once __DIR__ . "/../includes/list_search.php";

class Metales extends Sistema
{
    const TABLE = 'metales';
    const MAX_NAME_LENGTH = 25;

    public function leer(?string $busqueda = null)
    {
        $pat = joyeria_like_pattern($busqueda);
        $sql = "SELECT * FROM " . self::TABLE . " WHERE activo = 1";
        if ($pat !== null) {
            $sql .= " AND nom_metal LIKE :busq";
        }
        $sql .= " ORDER BY nom_metal ASC";
        $stmt = $this->getDb()->prepare($sql);
        if ($pat !== null) {
            $stmt->bindValue(':busq', $pat, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function leerUno($id_metal)
    {
        $sql = "SELECT * FROM " . self::TABLE . " WHERE id_metal = :id_metal";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindParam(':id_metal', $id_metal, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function crear($data)
    {
        $nomMetal = $this->validarNombre($data);
        $precioTienda = $this->validarPrecioOpcional($data, 'precio_tienda', 'precio de tienda');
        $precioMercado = $this->validarPrecioOpcional($data, 'precio_mercado', 'precio de mercado');
        $descuentoMostrador = $this->validarPorcentajeOpcional($data, 'descuento_mostrador_pct', 'descuento en mostrador') ?? 0.0;
        $aplicaMayoreo = isset($data['aplica_mayoreo']) && (string) $data['aplica_mayoreo'] !== '' && (string) $data['aplica_mayoreo'] !== '0' ? 1 : 0;
        $umbralPiezas = $this->validarEnteroOpcional($data, 'umbral_piezas_descuento');
        $descuentoUmbral = $this->validarPorcentajeOpcional($data, 'descuento_umbral_pct', 'descuento por umbral de piezas');
        $this->validarParUmbralDescuento($umbralPiezas, $descuentoUmbral);

        $sql = "INSERT INTO " . self::TABLE . " (nom_metal, precio_tienda, precio_mercado, descuento_mostrador_pct, aplica_mayoreo, umbral_piezas_descuento, descuento_umbral_pct, activo)
                VALUES (:nom_metal, :precio_tienda, :precio_mercado, :desc_mostrador, :aplica_mayoreo, :umbral_piezas, :desc_umbral, 1)";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindParam(':nom_metal', $nomMetal, PDO::PARAM_STR);
        $stmt->bindValue(':precio_tienda', $precioTienda, $precioTienda === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':precio_mercado', $precioMercado, $precioMercado === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':desc_mostrador', number_format($descuentoMostrador, 2, '.', ''), PDO::PARAM_STR);
        $stmt->bindValue(':aplica_mayoreo', $aplicaMayoreo, PDO::PARAM_INT);
        $stmt->bindValue(':umbral_piezas', $umbralPiezas, $umbralPiezas === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':desc_umbral', $descuentoUmbral === null ? null : number_format($descuentoUmbral, 2, '.', ''), $descuentoUmbral === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();

        require_once __DIR__ . '/../includes/ReglasDescuentoService.php';
        ReglasDescuentoService::limpiarCacheMetales();

        return $stmt->rowCount();
    }

    public function actualizar($id_metal, $data)
    {
        $nomMetal = $this->validarNombre($data);
        $precioTienda = $this->validarPrecioOpcional($data, 'precio_tienda', 'precio de tienda');
        $precioMercado = $this->validarPrecioOpcional($data, 'precio_mercado', 'precio de mercado');
        $descuentoMostrador = $this->validarPorcentajeOpcional($data, 'descuento_mostrador_pct', 'descuento en mostrador') ?? 0.0;
        $aplicaMayoreo = isset($data['aplica_mayoreo']) && (string) $data['aplica_mayoreo'] !== '' && (string) $data['aplica_mayoreo'] !== '0' ? 1 : 0;
        $umbralPiezas = $this->validarEnteroOpcional($data, 'umbral_piezas_descuento');
        $descuentoUmbral = $this->validarPorcentajeOpcional($data, 'descuento_umbral_pct', 'descuento por umbral de piezas');
        $this->validarParUmbralDescuento($umbralPiezas, $descuentoUmbral);

        $sql = "UPDATE " . self::TABLE . " SET nom_metal = :nom_metal, precio_tienda = :precio_tienda, precio_mercado = :precio_mercado,
                descuento_mostrador_pct = :desc_mostrador, aplica_mayoreo = :aplica_mayoreo,
                umbral_piezas_descuento = :umbral_piezas, descuento_umbral_pct = :desc_umbral
                WHERE id_metal = :id_metal AND activo = 1";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindParam(':nom_metal', $nomMetal, PDO::PARAM_STR);
        $stmt->bindValue(':precio_tienda', $precioTienda, $precioTienda === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':precio_mercado', $precioMercado, $precioMercado === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':desc_mostrador', number_format($descuentoMostrador, 2, '.', ''), PDO::PARAM_STR);
        $stmt->bindValue(':aplica_mayoreo', $aplicaMayoreo, PDO::PARAM_INT);
        $stmt->bindValue(':umbral_piezas', $umbralPiezas, $umbralPiezas === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':desc_umbral', $descuentoUmbral === null ? null : number_format($descuentoUmbral, 2, '.', ''), $descuentoUmbral === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':id_metal', $id_metal, PDO::PARAM_INT);
        $stmt->execute();

        require_once __DIR__ . '/../includes/ReglasDescuentoService.php';
        ReglasDescuentoService::limpiarCacheMetales();

        return $stmt->rowCount();
    }

    public function borrar($id_metal, $id_usuario_baja = null)
    {
        $id_usuario_baja = isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : null;
        $sql = "UPDATE " . self::TABLE . " SET activo = 0, fecha_baja = NOW(), id_usuario_baja = :id_usuario_baja WHERE id_metal = :id_metal";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(':id_usuario_baja', $id_usuario_baja, $id_usuario_baja === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(':id_metal', $id_metal, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    private function validarNombre($data)
    {
        if (!isset($data['nom_metal'])) {
            throw new InvalidArgumentException('El nombre del metal es requerido.');
        }

        $nombre = trim(strip_tags((string) $data['nom_metal']));
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre del metal no puede estar vacio.');
        }

        if (mb_strlen($nombre) > self::MAX_NAME_LENGTH) {
            $nombre = mb_substr($nombre, 0, self::MAX_NAME_LENGTH);
        }

        return $nombre;
    }

    private function validarPrecioOpcional($data, $key, $label)
    {
        if (!isset($data[$key]) || trim((string) $data[$key]) === '') {
            return null;
        }

        if (!is_numeric($data[$key])) {
            throw new InvalidArgumentException('El ' . $label . ' debe ser numerico.');
        }

        $valor = (float) $data[$key];
        if ($valor < 0) {
            throw new InvalidArgumentException('El ' . $label . ' no puede ser negativo.');
        }

        return $valor;
    }

    private function validarPorcentajeOpcional($data, $key, $label)
    {
        if (!isset($data[$key]) || trim((string) $data[$key]) === '') {
            return null;
        }

        if (!is_numeric($data[$key])) {
            throw new InvalidArgumentException('El ' . $label . ' debe ser numerico.');
        }

        $valor = (float) $data[$key];
        if ($valor < 0 || $valor > 100) {
            throw new InvalidArgumentException('El ' . $label . ' debe estar entre 0 y 100.');
        }

        return $valor;
    }

    private function validarEnteroOpcional($data, $key)
    {
        if (!isset($data[$key]) || trim((string) $data[$key]) === '') {
            return null;
        }

        if (!is_numeric($data[$key]) || (int) $data[$key] <= 0) {
            throw new InvalidArgumentException('El umbral de piezas debe ser un entero mayor que cero.');
        }

        return (int) $data[$key];
    }

    private function validarParUmbralDescuento(?int $umbral, ?float $descuento): void
    {
        if ($umbral === null && $descuento === null) {
            return;
        }
        if ($umbral === null || $descuento === null) {
            throw new InvalidArgumentException('Debes indicar umbral de piezas y porcentaje de descuento, o dejar ambos vacios.');
        }
    }
}
