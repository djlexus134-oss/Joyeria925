import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.tsx'
import { readKpiIsAdmin } from './kpiAdmin.ts'

const el = document.getElementById('kpi-dashboard-root')
if (el) {
  const canViewProfit = readKpiIsAdmin(el)
  createRoot(el).render(
    <StrictMode>
      <App canViewProfit={canViewProfit} />
    </StrictMode>,
  )
}
