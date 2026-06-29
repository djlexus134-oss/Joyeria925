<?php
require_once __DIR__ . '/includes/auth.php';

if (auth_is_logged_in() && !auth_can_read_panel()) {
    $first = auth_first_readable_admin_script();
    if ($first !== null) {
        header('Location: ' . $first);
        exit;
    }
}

require_once __DIR__ . '/views/header.php';

$kpiUser = auth_user();
$kpiRoles = is_array($kpiUser['roles'] ?? null) ? $kpiUser['roles'] : [];
$kpiIsAdmin = auth_has_admin_role_in_array($kpiRoles) || auth_is_admin();
?>

<header class="admin-header">
    <h2><i class="bi bi-speedometer2"></i> Panel de Control</h2>
</header>

<!-- KPI DASHBOARD (SPA) -->
<?php $kpiAssetVer = (int) @filemtime(__DIR__ . '/js/kpi-dashboard/index.js'); ?>
<link rel="stylesheet" href="js/kpi-dashboard/style.css?v=<?php echo $kpiAssetVer; ?>">
<script>
window.JOYERIA_KPI_IS_ADMIN = <?php echo $kpiIsAdmin ? 'true' : 'false'; ?>;
document.body.setAttribute('data-kpi-is-admin', '<?php echo $kpiIsAdmin ? '1' : '0'; ?>');
</script>
<div class="admin-main" style="margin-bottom: 10px; padding-top: 0; padding-bottom: 20px;">
    <div id="kpi-dashboard-root" data-is-admin="<?php echo $kpiIsAdmin ? '1' : '0'; ?>"></div>
</div>
<script type="module" src="js/kpi-dashboard/index.js?v=<?php echo $kpiAssetVer; ?>"></script>

<?php require_once __DIR__ . '/views/footer.php'; ?>
