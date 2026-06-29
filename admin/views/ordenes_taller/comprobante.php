<?php
$nombreComercial = trim((string) ($configNegocio['ticket_nombre_comercial'] ?? 'Joyeria'));
$horario = trim((string) ($configNegocio['ticket_horario'] ?? ''));
$mensajePie = trim((string) ($configNegocio['ticket_mensaje_pie'] ?? ''));
$totalAbonado = 0.0;
foreach ($pagos as $p) {
    $totalAbonado += (float) ($p['monto'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden de Taller <?php echo htmlspecialchars((string) $orden['folio']); ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; max-width: 720px; margin: 24px auto; padding: 0 16px; color: #222; }
        h1 { font-size: 1.4rem; margin: 0 0 4px; }
        .sub { color: #666; margin-bottom: 20px; }
        .section { margin-bottom: 18px; }
        .section h2 { font-size: 1rem; border-bottom: 1px solid #ccc; padding-bottom: 4px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 6px 4px; border-bottom: 1px solid #eee; }
        .totales { margin-top: 12px; }
        .totales div { display: flex; justify-content: space-between; padding: 4px 0; }
        .totales .saldo { font-weight: bold; font-size: 1.1rem; }
        .no-print { margin: 16px 0; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" onclick="window.print();">Imprimir</button>
        <button type="button" onclick="window.close();">Cerrar</button>
    </div>

    <h1><?php echo htmlspecialchars($nombreComercial); ?></h1>
    <div class="sub">Orden de Taller — <?php echo htmlspecialchars((string) $orden['folio']); ?></div>
    <?php if ($horario !== ''): ?>
        <div class="sub"><?php echo htmlspecialchars($horario); ?></div>
    <?php endif; ?>

    <div class="section">
        <h2>Datos de la orden</h2>
        <p><strong>Fecha registro:</strong> <?php echo htmlspecialchars((string) $orden['fecha_registro']); ?></p>
        <p><strong>Estado:</strong> <?php echo htmlspecialchars($app->etiquetaEstado((string) $orden['estado'])); ?></p>
        <p><strong>Tipo:</strong> <?php echo htmlspecialchars(ucfirst((string) $orden['tipo'])); ?></p>
        <?php if (!empty($orden['fecha_compromiso'])): ?>
            <p><strong>Fecha compromiso:</strong> <?php echo htmlspecialchars((string) $orden['fecha_compromiso']); ?></p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>Pieza</h2>
        <p><strong>Descripcion:</strong> <?php echo htmlspecialchars((string) ($orden['pieza_descripcion'] ?? '')); ?></p>
        <?php if (!empty($orden['codigo_auxiliar']) || !empty($orden['codigo_barras'])): ?>
            <p><strong>Codigo:</strong> <?php echo htmlspecialchars((string) ($orden['codigo_auxiliar'] ?: $orden['codigo_barras'])); ?></p>
        <?php endif; ?>
        <p><strong>Origen:</strong> <?php echo (string) $orden['origen'] === 'inventario' ? 'Inventario' : 'Cliente'; ?></p>
    </div>

    <div class="section">
        <h2>Cliente y taller</h2>
        <p><strong>Cliente:</strong> <?php echo htmlspecialchars((string) ($orden['cliente_nombre'] ?? 'N/A')); ?>
            <?php if (!empty($orden['cliente_telefono'])): ?>
                — <?php echo htmlspecialchars((string) $orden['cliente_telefono']); ?>
            <?php endif; ?>
        </p>
        <p><strong>Taller:</strong> <?php echo htmlspecialchars((string) ($orden['taller_nombre'] ?? 'Sin asignar')); ?>
            <?php if (!empty($orden['taller_telefono'])): ?>
                — <?php echo htmlspecialchars((string) $orden['taller_telefono']); ?>
            <?php endif; ?>
        </p>
    </div>

    <div class="section">
        <h2>Trabajo solicitado</h2>
        <p><?php echo nl2br(htmlspecialchars((string) ($orden['descripcion_problema'] ?? ''))); ?></p>
        <?php if (!empty($orden['observaciones'])): ?>
            <p><strong>Observaciones:</strong> <?php echo htmlspecialchars((string) $orden['observaciones']); ?></p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>Cobros</h2>
        <?php if (!empty($pagos)): ?>
            <table>
                <thead>
                    <tr><th>Fecha</th><th>Forma pago</th><th>Monto</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($pagos as $pago): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) $pago['fecha_registro']); ?></td>
                            <td><?php echo htmlspecialchars((string) $pago['forma_pago']); ?></td>
                            <td>$<?php echo htmlspecialchars(number_format((float) $pago['monto'], 2)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Sin cobros registrados.</p>
        <?php endif; ?>

        <div class="totales">
            <div><span>Costo total:</span><span>$<?php echo htmlspecialchars(number_format((float) $orden['costo_total'], 2)); ?></span></div>
            <div><span>Total abonado:</span><span>$<?php echo htmlspecialchars(number_format($totalAbonado, 2)); ?></span></div>
            <div class="saldo"><span>Saldo pendiente:</span><span>$<?php echo htmlspecialchars(number_format((float) $orden['saldo_pendiente'], 2)); ?></span></div>
        </div>
    </div>

    <?php if ($mensajePie !== ''): ?>
        <div class="section" style="margin-top:24px;text-align:center;color:#666;">
            <p><?php echo htmlspecialchars($mensajePie); ?></p>
        </div>
    <?php endif; ?>
</body>
</html>
