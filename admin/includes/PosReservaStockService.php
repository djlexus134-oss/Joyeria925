<?php
declare(strict_types=1);

/**
 * Reserva de piezas_stock para tickets de punto de venta (estado reservada_pos).
 */
class PosReservaStockService
{
    public const RESERVA_TTL_MINUTOS = 120;

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public static function generarToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * @return array{ok:bool, error?:string}
     */
    public function reservar(int $idPiezaStock, string $token, int $ttlMinutos = self::RESERVA_TTL_MINUTOS): array
    {
        if ($idPiezaStock <= 0) {
            return ['ok' => false, 'error' => 'Pieza de stock invalida.'];
        }
        $token = trim($token);
        if ($token === '') {
            return ['ok' => false, 'error' => 'Token de reserva POS invalido.'];
        }
        if ($ttlMinutos < 1) {
            $ttlMinutos = self::RESERVA_TTL_MINUTOS;
        }

        $upd = $this->db->prepare(
            "UPDATE piezas_stock
             SET estado = 'reservada_pos',
                 reservada_hasta = DATE_ADD(NOW(), INTERVAL :ttl MINUTE),
                 pos_reserva_token = :token,
                 id_carrito_owner = NULL
             WHERE id_pieza_stock = :id
               AND estado = 'disponible'
               AND activo = 1"
        );
        $upd->bindValue(':ttl', $ttlMinutos, PDO::PARAM_INT);
        $upd->bindValue(':token', $token, PDO::PARAM_STR);
        $upd->bindValue(':id', $idPiezaStock, PDO::PARAM_INT);
        $upd->execute();

        if ($upd->rowCount() !== 1) {
            return [
                'ok' => false,
                'error' => 'La pieza ya no esta disponible (puede estar en otro ticket o en linea).',
            ];
        }

        return ['ok' => true];
    }

    public function liberar(int $idPiezaStock, string $token): bool
    {
        if ($idPiezaStock <= 0) {
            return false;
        }
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        $upd = $this->db->prepare(
            "UPDATE piezas_stock
             SET estado = 'disponible',
                 reservada_hasta = NULL,
                 pos_reserva_token = NULL
             WHERE id_pieza_stock = :id
               AND estado = 'reservada_pos'
               AND pos_reserva_token = :token"
        );
        $upd->bindValue(':id', $idPiezaStock, PDO::PARAM_INT);
        $upd->bindValue(':token', $token, PDO::PARAM_STR);
        $upd->execute();

        return $upd->rowCount() === 1;
    }

    public function liberarPorToken(string $token): int
    {
        $token = trim($token);
        if ($token === '') {
            return 0;
        }

        $upd = $this->db->prepare(
            "UPDATE piezas_stock
             SET estado = 'disponible',
                 reservada_hasta = NULL,
                 pos_reserva_token = NULL
             WHERE estado = 'reservada_pos'
               AND pos_reserva_token = :token"
        );
        $upd->bindValue(':token', $token, PDO::PARAM_STR);
        $upd->execute();

        return $upd->rowCount();
    }

    public function liberarExpiradasPos(): int
    {
        $upd = $this->db->prepare(
            "UPDATE piezas_stock
             SET estado = 'disponible',
                 reservada_hasta = NULL,
                 pos_reserva_token = NULL
             WHERE estado = 'reservada_pos'
               AND reservada_hasta IS NOT NULL
               AND reservada_hasta < NOW()"
        );
        $upd->execute();

        return $upd->rowCount();
    }

    public function extenderToken(string $token, int $ttlMinutos = self::RESERVA_TTL_MINUTOS): void
    {
        $token = trim($token);
        if ($token === '' || $ttlMinutos < 1) {
            return;
        }

        $upd = $this->db->prepare(
            "UPDATE piezas_stock
             SET reservada_hasta = DATE_ADD(NOW(), INTERVAL :ttl MINUTE)
             WHERE estado = 'reservada_pos'
               AND pos_reserva_token = :token"
        );
        $upd->bindValue(':ttl', $ttlMinutos, PDO::PARAM_INT);
        $upd->bindValue(':token', $token, PDO::PARAM_STR);
        $upd->execute();
    }

    /**
     * Filtra lineas joya cuya reserva POS ya no es valida (expirada u otro token).
     *
     * @param array<int, array<string, mixed>> $detalles
     * @return array{detalles: array<int, array<string, mixed>>, advertencias: list<string>}
     */
    public function filtrarDetallesConReservaValida(array $detalles, string $token): array
    {
        $token = trim($token);
        $advertencias = [];
        $validos = [];

        foreach ($detalles as $linea) {
            if (!is_array($linea)) {
                continue;
            }
            if (($linea['tipo_linea'] ?? '') !== 'joya') {
                $validos[] = $linea;
                continue;
            }
            $idPs = (int) ($linea['id_pieza_stock_FK'] ?? 0);
            if ($idPs <= 0) {
                $validos[] = $linea;
                continue;
            }

            $st = $this->db->prepare(
                "SELECT estado, pos_reserva_token
                 FROM piezas_stock
                 WHERE id_pieza_stock = :id
                 LIMIT 1"
            );
            $st->bindValue(':id', $idPs, PDO::PARAM_INT);
            $st->execute();
            $row = $st->fetch(PDO::FETCH_ASSOC);

            $estado = trim((string) ($row['estado'] ?? ''));
            $tokenDb = trim((string) ($row['pos_reserva_token'] ?? ''));

            if ($estado === 'reservada_pos' && $token !== '' && $tokenDb === $token) {
                $validos[] = $linea;
                continue;
            }

            $codigo = trim((string) ($linea['codigo'] ?? ''));
            $desc = trim((string) ($linea['descripcion'] ?? ''));
            $etiq = $desc !== '' ? $desc : ($codigo !== '' ? $codigo : ('stock #' . $idPs));
            $advertencias[] = 'Se quito del ticket: «' . $etiq . '» (ya no esta reservada para esta venta).';
        }

        return ['detalles' => $validos, 'advertencias' => $advertencias];
    }
}
