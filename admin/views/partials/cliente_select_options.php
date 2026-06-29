<?php
/**
 * Opciones HTML para selects de clientes (con data-search para autocompletado).
 *
 * @var array<int, array<string, mixed>> $clientes
 * @var int|string|null $selectedId
 * @var string|null $emptyLabel
 * @var string|null $emptyValue
 * @var bool $includeEmpty
 * @var string|null $emptyDataDescuento atributo data-descuento en opcion vacia (apartados)
 */
require_once __DIR__ . '/../../includes/cliente_select.php';

$clientes = isset($clientes) && is_array($clientes) ? $clientes : [];
$includeEmpty = !isset($includeEmpty) || $includeEmpty;
$emptyLabel = isset($emptyLabel) ? (string) $emptyLabel : '';
$emptyValue = isset($emptyValue) ? (string) $emptyValue : '';
if (isset($selectedId) && $selectedId !== '' && $selectedId !== null) {
    $selectedId = (string) (int) $selectedId;
} else {
    $selectedId = '';
}
$emptyDataDescuento = isset($emptyDataDescuento) ? (string) $emptyDataDescuento : null;

if ($includeEmpty && $emptyLabel !== ''):
    $emptyExtra = $emptyDataDescuento !== null
        ? ' data-descuento="' . htmlspecialchars($emptyDataDescuento, ENT_QUOTES, 'UTF-8') . '"'
        : '';
    ?>
    <option value="<?php echo htmlspecialchars($emptyValue, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $emptyExtra; ?><?php echo $selectedId === $emptyValue ? ' selected' : ''; ?>>
        <?php echo htmlspecialchars($emptyLabel, ENT_QUOTES, 'UTF-8'); ?>
    </option>
<?php endif;

foreach ($clientes as $cli):
    if (!is_array($cli)) {
        continue;
    }
    $idCli = (int) ($cli['id_cliente'] ?? 0);
    if ($idCli <= 0) {
        continue;
    }
    $label = joyeria_cliente_option_label($cli);
    $search = joyeria_cliente_option_search_haystack($cli);
    $isSelected = $selectedId !== '' && $selectedId === (string) $idCli;
    ?>
    <?php
    $dataDescuento = array_key_exists('descuento_porcentaje', $cli) && $cli['descuento_porcentaje'] !== null && $cli['descuento_porcentaje'] !== ''
        ? ' data-descuento="' . htmlspecialchars((string) $cli['descuento_porcentaje'], ENT_QUOTES, 'UTF-8') . '"'
        : '';
    ?>
    <option value="<?php echo $idCli; ?>"
            data-search="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $dataDescuento; ?>
            <?php echo $isSelected ? ' selected' : ''; ?>>
        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
    </option>
<?php endforeach; ?>
