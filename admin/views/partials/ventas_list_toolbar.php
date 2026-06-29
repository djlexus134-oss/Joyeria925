<?php
/**
 * Filtros y busqueda del listado de ventas.
 *
 * @var array $filtrosVentas
 * @var string $busqueda
 * @var array $catalogos
 */
require_once __DIR__ . '/../../includes/list_filters.php';

$filtrosVentas = isset($filtrosVentas) && is_array($filtrosVentas) ? $filtrosVentas : [];
$busqueda = isset($busqueda) ? joyeria_list_search_normalize((string) $busqueda) : '';
$catalogos = isset($catalogos) && is_array($catalogos) ? $catalogos : [];

$idClienteSel = array_key_exists('id_cliente', $filtrosVentas) && $filtrosVentas['id_cliente'] !== null
    ? (string) (int) $filtrosVentas['id_cliente']
    : '';
$idEmpleadoSel = !empty($filtrosVentas['id_empleado']) ? (string) (int) $filtrosVentas['id_empleado'] : '';
$estadoSel = !empty($filtrosVentas['estado']) ? (string) $filtrosVentas['estado'] : '';
$origenSel = !empty($filtrosVentas['origen']) ? (string) $filtrosVentas['origen'] : '';
$fechaDesde = !empty($filtrosVentas['fecha_desde']) ? (string) $filtrosVentas['fecha_desde'] : '';
$fechaHasta = !empty($filtrosVentas['fecha_hasta']) ? (string) $filtrosVentas['fecha_hasta'] : '';

$hayFiltros = joyeria_ventas_filtros_activos($filtrosVentas) || $busqueda !== '';
$clearHref = 'ventas.php?accion=leer';
?>
<section class="form-section ventas-list-filtros" style="width: 100%; max-width: 100%; margin-bottom: 1.5rem;">
    <h3>Filtros de ventas</h3>
    <form method="get" action="ventas.php" class="admin-form">
        <input type="hidden" name="accion" value="leer">

        <div class="form-row">
            <div class="form-group">
                <label for="vf_fecha_desde"><i class="bi bi-calendar-event"></i> Desde</label>
                <input type="date" id="vf_fecha_desde" name="fecha_desde" class="form-input"
                       value="<?php echo htmlspecialchars($fechaDesde, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label for="vf_fecha_hasta"><i class="bi bi-calendar-check"></i> Hasta</label>
                <input type="date" id="vf_fecha_hasta" name="fecha_hasta" class="form-input"
                       value="<?php echo htmlspecialchars($fechaHasta, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label for="vf_id_cliente"><i class="bi bi-person"></i> Cliente</label>
                <select id="vf_id_cliente" name="id_cliente" class="form-input">
                    <option value=""<?php echo $idClienteSel === '' ? ' selected' : ''; ?>>Todos</option>
                    <option value="0"<?php echo $idClienteSel === '0' ? ' selected' : ''; ?>>Público general</option>
                    <?php
                    $clientes = $catalogos['clientes'] ?? [];
                    $selectedId = $idClienteSel !== '' && $idClienteSel !== '0' ? $idClienteSel : '';
                    $includeEmpty = false;
                    require __DIR__ . '/cliente_select_options.php';
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="vf_id_empleado"><i class="bi bi-person-badge"></i> Empleado</label>
                <select id="vf_id_empleado" name="id_empleado" class="form-input">
                    <option value=""<?php echo $idEmpleadoSel === '' ? ' selected' : ''; ?>>Todos</option>
                    <?php foreach (($catalogos['empleados'] ?? []) as $emp): ?>
                        <option value="<?php echo (int) $emp['id_empleado']; ?>"<?php echo $idEmpleadoSel === (string) (int) $emp['id_empleado'] ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars((string) ($emp['nombre_completo'] ?? '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="vf_estado"><i class="bi bi-flag"></i> Estado</label>
                <select id="vf_estado" name="estado" class="form-input">
                    <option value=""<?php echo $estadoSel === '' ? ' selected' : ''; ?>>Todos</option>
                    <option value="completada"<?php echo $estadoSel === 'completada' ? ' selected' : ''; ?>>Completada</option>
                    <option value="cancelada"<?php echo $estadoSel === 'cancelada' ? ' selected' : ''; ?>>Cancelada</option>
                    <option value="devuelta"<?php echo $estadoSel === 'devuelta' ? ' selected' : ''; ?>>Devuelta</option>
                </select>
            </div>
            <div class="form-group">
                <label for="vf_origen"><i class="bi bi-diagram-3"></i> Origen</label>
                <select id="vf_origen" name="origen" class="form-input">
                    <option value=""<?php echo $origenSel === '' ? ' selected' : ''; ?>>Todos</option>
                    <option value="directa"<?php echo $origenSel === 'directa' ? ' selected' : ''; ?>>Venta directa</option>
                    <option value="liquidacion"<?php echo $origenSel === 'liquidacion' ? ' selected' : ''; ?>>Liquidación apartado</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group ventas-list-filtros-busqueda">
                <label for="vf_q"><i class="bi bi-search"></i> Buscar</label>
                <input type="search" id="vf_q" name="q" class="form-input"
                       value="<?php echo htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8'); ?>"
                       placeholder="ID venta, apartado, nombre, correo..."
                       autocomplete="off">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-action-primary">
                <i class="bi bi-funnel"></i> Aplicar filtros
            </button>
            <?php if ($hayFiltros): ?>
                <a href="<?php echo htmlspecialchars($clearHref, ENT_QUOTES, 'UTF-8'); ?>" class="btn-action-secondary">
                    <i class="bi bi-x-circle"></i> Limpiar
                </a>
            <?php endif; ?>
        </div>
    </form>
</section>
<script src="js/fk-autocomplete.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.JoyeriaFkAutocomplete) return;
    JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'vf_id_cliente', allowEmpty: true, placeholder: 'Nombre, apellido, correo o teléfono...' });
    JoyeriaFkAutocomplete.initSelectAutocomplete({ selectId: 'vf_id_empleado', allowEmpty: true, placeholder: 'Buscar empleado...' });
});
</script>
