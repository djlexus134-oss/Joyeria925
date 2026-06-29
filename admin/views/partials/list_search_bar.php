<?php
require_once __DIR__ . '/../../includes/list_search.php';
/**
 * Barra de búsqueda para listados CRUD (GET q).
 *
 * Variables esperadas:
 * - $listSearchAction (string) nombre del script, ej. 'familia.php'
 * - $listSearchHidden (array<string, scalar>) campos hidden además de q (accion, entidad, id_pieza, etc.)
 * - $busqueda (string) valor actual de q
 * - $listSearchPlaceholder (string, opcional)
 */
$__lsAction = isset($listSearchAction) ? (string) $listSearchAction : '';
$__lsHidden = isset($listSearchHidden) && is_array($listSearchHidden) ? $listSearchHidden : [];
$__lsValue = isset($busqueda) ? joyeria_list_search_normalize((string) $busqueda) : '';
$__lsPlaceholder = isset($listSearchPlaceholder) ? (string) $listSearchPlaceholder : 'Buscar...';

$__lsClearQuery = http_build_query($__lsHidden);
$__lsClearHref = htmlspecialchars($__lsAction . ($__lsClearQuery !== '' ? '?' . $__lsClearQuery : ''), ENT_QUOTES, 'UTF-8');
?>
<div class="module-search-bar">
    <form class="module-search-form" method="get" action="<?php echo htmlspecialchars($__lsAction, ENT_QUOTES, 'UTF-8'); ?>">
        <?php foreach ($__lsHidden as $hk => $hv): ?>
            <input type="hidden" name="<?php echo htmlspecialchars((string) $hk, ENT_QUOTES, 'UTF-8'); ?>"
                   value="<?php echo htmlspecialchars((string) $hv, ENT_QUOTES, 'UTF-8'); ?>">
        <?php endforeach; ?>
        <label class="module-search-label" for="list-search-q">
            <span class="sr-only">Buscar</span>
            <input type="search" id="list-search-q" name="q" class="module-search-input"
                   value="<?php echo htmlspecialchars($__lsValue, ENT_QUOTES, 'UTF-8'); ?>"
                   placeholder="<?php echo htmlspecialchars($__lsPlaceholder, ENT_QUOTES, 'UTF-8'); ?>"
                   autocomplete="off">
        </label>
        <button type="submit" class="btn-action-secondary module-search-submit">
            <i class="bi bi-search"></i> Buscar
        </button>
        <?php if ($__lsValue !== ''): ?>
            <a href="<?php echo $__lsClearHref; ?>" class="btn-action-secondary module-search-clear">Limpiar</a>
        <?php endif; ?>
    </form>
</div>
