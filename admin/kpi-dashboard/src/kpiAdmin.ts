declare global {
  interface Window {
    JOYERIA_KPI_IS_ADMIN?: boolean
  }
}

/** Admin flag set by PHP (index.php) before the SPA module loads. */
export function readKpiIsAdmin(mountEl?: HTMLElement | null): boolean {
  if (window.JOYERIA_KPI_IS_ADMIN === true) {
    return true
  }
  if (document.body?.getAttribute('data-kpi-is-admin') === '1') {
    return true
  }
  const root = mountEl ?? document.getElementById('kpi-dashboard-root')
  if (root?.getAttribute('data-is-admin') === '1') {
    return true
  }
  return root?.dataset.isAdmin === '1'
}
