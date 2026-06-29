<?php
require_once __DIR__ . '/views/header.php';

$kpiUser = auth_user();
$kpiRoles = is_array($kpiUser['roles'] ?? null) ? $kpiUser['roles'] : [];
$kpiIsAdmin = auth_has_admin_role_in_array($kpiRoles) || auth_is_admin();
?>

<?php $kpiAssetVer = (int) @filemtime(__DIR__ . '/js/kpi-dashboard/index.js'); ?>
<link rel="stylesheet" href="js/kpi-dashboard/style.css?v=<?php echo $kpiAssetVer; ?>">
<script>window.JOYERIA_KPI_IS_ADMIN = <?php echo $kpiIsAdmin ? 'true' : 'false'; ?>;</script>

<header class="admin-header">
    <h2><i class="bi bi-graph-up-arrow"></i> KPIs</h2>
</header>

<div class="admin-main" style="padding-top: 12px;">
    <div id="kpi-dashboard-root" data-is-admin="<?php echo $kpiIsAdmin ? '1' : '0'; ?>"></div>
</div>

<script type="module" src="js/kpi-dashboard/index.js?v=<?php echo $kpiAssetVer; ?>"></script>

<?php require_once __DIR__ . '/views/footer.php'; ?>

