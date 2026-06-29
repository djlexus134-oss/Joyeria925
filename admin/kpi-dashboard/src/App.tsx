import { useEffect, useMemo, useState } from 'react'
import ReactECharts from 'echarts-for-react'
import { readKpiIsAdmin } from './kpiAdmin.ts'

type Range = { from: string; to: string }

type Summary = {
  ventas: number
  monto: number
  empleados_activos: number
  ticket_promedio: number
}

type TrendPoint = { fecha: string; ventas: number; monto: number }

type PaymentRow = {
  id_forma_pago: number
  forma_pago: string
  num_transacciones: number
  monto_total: number
  porcentaje: number
}

type TopProductRow = {
  tipo_linea: 'joya' | 'insumo' | string
  nombre: string
  num_lineas: number
  cantidad_vendida: number
  monto_total: number
  precio_promedio: number
}

type RankingRow = {
  id_empleado: number
  empleado: string
  num_ventas: number
  total_ventas: number
  promedio_venta: number
}

type Filters = {
  empleados: { id_empleado: number; nombre: string }[]
  tiendas: { id_tienda: number; nombre: string }[]
}

type DevolucionEmpleadoRow = {
  id_empleado: number
  empleado: string
  total_ventas: number
  ventas_devueltas: number
  tasa_devolucion_pct: number
}

type ProfitSummary = {
  ingresos_lineas: number
  costo_vendido: number
  margen_bruto: number
  gastos: number
  devoluciones: number
  ganancia_neta: number
  margen_bruto_pct: number
}

async function fetchSection<T>(
  section: string,
  params: Record<string, string | number | undefined | null>,
): Promise<{ range: Range; data: T }> {
  const url = new URL('api/kpi_dashboard.php', window.location.href)
  url.searchParams.set('section', section)
  for (const [k, v] of Object.entries(params)) {
    if (v === undefined || v === null || v === '') continue
    url.searchParams.set(k, String(v))
  }

  const res = await fetch(url.toString(), { credentials: 'same-origin' })
  const json = (await res.json()) as { success: boolean; error?: string; range?: Range; data?: T }
  if (!res.ok || !json.success) {
    throw new Error(json.error || `Error cargando ${section}`)
  }
  return { range: json.range as Range, data: json.data as T }
}

function money(value: number): string {
  return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value || 0)
}

function num(value: number): string {
  return new Intl.NumberFormat('es-MX').format(value || 0)
}

function isoToday(): string {
  const d = new Date()
  const yyyy = d.getFullYear()
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const dd = String(d.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

function daysAgoIso(days: number): string {
  const d = new Date()
  d.setDate(d.getDate() - days)
  const yyyy = d.getFullYear()
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const dd = String(d.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

type AppProps = {
  canViewProfit?: boolean
}

function App({ canViewProfit = false }: AppProps) {
  const showProfit = canViewProfit || readKpiIsAdmin()
  const [from, setFrom] = useState(() => daysAgoIso(29))
  const [to, setTo] = useState(() => isoToday())
  const [idEmpleado, setIdEmpleado] = useState<number | ''>('')
  const [idTienda, setIdTienda] = useState<number | ''>('')

  const [filters, setFilters] = useState<Filters>({ empleados: [], tiendas: [] })
  const [profit, setProfit] = useState<ProfitSummary | null>(null)
  const [profitLoading, setProfitLoading] = useState(false)
  const [summary, setSummary] = useState<Summary | null>(null)
  const [trend, setTrend] = useState<TrendPoint[]>([])
  const [payments, setPayments] = useState<PaymentRow[]>([])
  const [topProducts, setTopProducts] = useState<TopProductRow[]>([])
  const [ranking, setRanking] = useState<RankingRow[]>([])
  const [alerts, setAlerts] = useState<{ devolucion_por_empleado: DevolucionEmpleadoRow[] } | null>(null)

  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const queryParams = useMemo(
    () => ({
      from,
      to,
      id_empleado: idEmpleado === '' ? undefined : idEmpleado,
      id_tienda: idTienda === '' ? undefined : idTienda,
    }),
    [from, to, idEmpleado, idTienda],
  )

  useEffect(() => {
    fetchSection<Filters>('filters', queryParams)
      .then(({ data }) => setFilters(data))
      .catch(() => {
        // Si falla filtros, no bloqueamos el dashboard.
        setFilters({ empleados: [], tiendas: [] })
      })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useEffect(() => {
    let alive = true
    setLoading(true)
    setError(null)

    Promise.all([
      fetchSection<Summary>('summary', queryParams),
      fetchSection<TrendPoint[]>('trend', queryParams),
      fetchSection<PaymentRow[]>('payments', queryParams),
      fetchSection<TopProductRow[]>('top_products', { ...queryParams, limit: 10 }),
      fetchSection<RankingRow[]>('ranking', { ...queryParams, limit: 10 }),
      fetchSection<{ devolucion_por_empleado: DevolucionEmpleadoRow[] }>('alerts', { ...queryParams, limit: 10 }),
    ])
      .then(([s, t, p, tp, r, a]) => {
        if (!alive) return
        setSummary(s.data)
        setTrend(t.data)
        setPayments(p.data)
        setTopProducts(tp.data)
        setRanking(r.data)
        setAlerts(a.data)
      })
      .catch((e: unknown) => {
        if (!alive) return
        setError(e instanceof Error ? e.message : 'Error cargando dashboard')
      })
      .finally(() => {
        if (!alive) return
        setLoading(false)
      })

    return () => {
      alive = false
    }
  }, [queryParams])

  useEffect(() => {
    if (!showProfit) {
      setProfit(null)
      return
    }

    let alive = true
    setProfitLoading(true)

    fetchSection<ProfitSummary>('profit', queryParams)
      .then(({ data }) => {
        if (!alive) return
        setProfit(data)
      })
      .catch(() => {
        if (!alive) return
        setProfit(null)
      })
      .finally(() => {
        if (!alive) return
        setProfitLoading(false)
      })

    return () => {
      alive = false
    }
  }, [showProfit, queryParams])

  useEffect(() => {
    if (!showProfit) return
    window.dispatchEvent(
      new CustomEvent('kpi-dashboard-filters', {
        detail: {
          from,
          to,
          id_empleado: idEmpleado === '' ? '' : idEmpleado,
        },
      }),
    )
  }, [showProfit, from, to, idEmpleado])

  const profitValueClass =
    profit && profit.ganancia_neta < 0 ? 'kpi-card-value--negative' : 'kpi-card-value--positive'

  const profitSub = profit
    ? `Margen ${profit.margen_bruto_pct.toFixed(1)}% · Gastos ${money(profit.gastos)} · Devoluciones ${money(profit.devoluciones)}`
    : 'Indicador operativo (no contable)'

  const trendOption = useMemo(() => {
    const labels = trend.map((x) => x.fecha)
    const montoSeries = trend.map((x) => Number(x.monto || 0))
    return {
      grid: { left: 10, right: 18, top: 28, bottom: 24, containLabel: true },
      tooltip: { trigger: 'axis' },
      xAxis: { type: 'category', data: labels, axisLabel: { hideOverlap: true } },
      yAxis: { type: 'value' },
      series: [
        {
          name: 'Monto',
          type: 'line',
          data: montoSeries,
          smooth: true,
          showSymbol: false,
          lineStyle: { width: 3 },
          areaStyle: { opacity: 0.12 },
        },
      ],
    }
  }, [trend])

  const paymentsOption = useMemo(() => {
    const data = payments.map((p) => ({ name: p.forma_pago, value: Number(p.monto_total || 0) }))
    return {
      tooltip: { trigger: 'item' },
      legend: { bottom: 0, left: 'center' },
      series: [
        {
          name: 'Formas de pago',
          type: 'pie',
          radius: ['55%', '78%'],
          avoidLabelOverlap: true,
          label: { show: false },
          emphasis: { label: { show: true, fontWeight: 'bold' } },
          labelLine: { show: false },
          data,
        },
      ],
    }
  }, [payments])

  const topProductsOption = useMemo(() => {
    const data = topProducts.slice(0, 10)
    const labels = data.map((x) => {
      const tag = x.tipo_linea === 'insumo' ? 'I' : 'J'
      return `[${tag}] ${x.nombre}`
    })
    const values = data.map((x) => Number(x.monto_total || 0))

    return {
      grid: { left: 10, right: 18, top: 18, bottom: 18, containLabel: true },
      tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
      xAxis: { type: 'value' },
      yAxis: { type: 'category', data: labels, axisLabel: { width: 220, overflow: 'truncate' } },
      series: [{ type: 'bar', data: values, barWidth: 16 }],
    }
  }, [topProducts])

  return (
    <div className="kpi-wrap">
      <div className="kpi-topbar">
        <div className="kpi-topbar-title">
          <div className="kpi-eyebrow">Dashboard</div>
          <div className="kpi-title">KPIs de ventas</div>
        </div>

        <div className="kpi-filters">
          <label className="kpi-field">
            <span>Desde</span>
            <input type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
          </label>
          <label className="kpi-field">
            <span>Hasta</span>
            <input type="date" value={to} onChange={(e) => setTo(e.target.value)} />
          </label>
          <label className="kpi-field">
            <span>Empleado</span>
            <select
              value={idEmpleado}
              onChange={(e) => setIdEmpleado(e.target.value ? Number(e.target.value) : '')}
            >
              <option value="">Todos</option>
              {filters.empleados.map((e) => (
                <option key={e.id_empleado} value={e.id_empleado}>
                  {e.nombre}
                </option>
              ))}
            </select>
          </label>
          <label className="kpi-field">
            <span>Tienda</span>
            <select value={idTienda} onChange={(e) => setIdTienda(e.target.value ? Number(e.target.value) : '')}>
              <option value="">Todas</option>
              {filters.tiendas.map((t) => (
                <option key={t.id_tienda} value={t.id_tienda}>
                  {t.nombre}
                </option>
              ))}
            </select>
          </label>
        </div>
      </div>

      {error ? (
        <div className="kpi-error">{error}</div>
      ) : null}

      <div className={`kpi-cards${showProfit ? ' kpi-cards-admin' : ''}`}>
        {showProfit ? (
          <div className="kpi-card kpi-card--profit" title="Costo según catálogo actual; ingresos por línea de venta.">
            <div className="kpi-card-label">Ganancia neta</div>
            <div className={`kpi-card-value ${profitValueClass}`}>
              {profitLoading || loading ? '—' : money(profit?.ganancia_neta ?? 0)}
            </div>
            <div className="kpi-card-sub">{profitLoading ? 'Calculando…' : profitSub}</div>
          </div>
        ) : null}
        <div className="kpi-card">
          <div className="kpi-card-label">Ventas</div>
          <div className="kpi-card-value">{loading ? '—' : num(summary?.ventas ?? 0)}</div>
          <div className="kpi-card-sub">En el rango seleccionado</div>
        </div>
        <div className="kpi-card">
          <div className="kpi-card-label">Monto</div>
          <div className="kpi-card-value">{loading ? '—' : money(summary?.monto ?? 0)}</div>
          <div className="kpi-card-sub">Total vendido</div>
        </div>
        <div className="kpi-card">
          <div className="kpi-card-label">Ticket promedio</div>
          <div className="kpi-card-value">{loading ? '—' : money(summary?.ticket_promedio ?? 0)}</div>
          <div className="kpi-card-sub">Promedio por venta</div>
        </div>
        <div className="kpi-card">
          <div className="kpi-card-label">Empleados activos</div>
          <div className="kpi-card-value">{loading ? '—' : num(summary?.empleados_activos ?? 0)}</div>
          <div className="kpi-card-sub">Con ventas en el rango</div>
        </div>
      </div>

      <div className="kpi-grid">
        <section className="kpi-panel kpi-panel-span2">
          <div className="kpi-panel-head">
            <div>
              <div className="kpi-panel-title">Tendencia</div>
              <div className="kpi-panel-sub">Monto por día</div>
            </div>
          </div>
          <div className="kpi-panel-body">
            <ReactECharts option={trendOption} style={{ height: 320 }} notMerge lazyUpdate />
          </div>
        </section>

        <section className="kpi-panel">
          <div className="kpi-panel-head">
            <div>
              <div className="kpi-panel-title">Formas de pago</div>
              <div className="kpi-panel-sub">Distribución por monto</div>
            </div>
          </div>
          <div className="kpi-panel-body">
            <ReactECharts option={paymentsOption} style={{ height: 320 }} notMerge lazyUpdate />
          </div>
        </section>

        <section className="kpi-panel">
          <div className="kpi-panel-head">
            <div>
              <div className="kpi-panel-title">Top productos</div>
              <div className="kpi-panel-sub">Por monto (Top 10)</div>
            </div>
          </div>
          <div className="kpi-panel-body">
            <ReactECharts option={topProductsOption} style={{ height: 320 }} notMerge lazyUpdate />
          </div>
        </section>

        <section className="kpi-panel kpi-panel-span2">
          <div className="kpi-panel-head">
            <div>
              <div className="kpi-panel-title">Ranking empleados</div>
              <div className="kpi-panel-sub">Top 10 por monto</div>
            </div>
          </div>
          <div className="kpi-panel-body">
            <div className="kpi-table-wrap">
              <table className="kpi-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Empleado</th>
                    <th>Ventas</th>
                    <th>Monto</th>
                    <th>Promedio</th>
                  </tr>
                </thead>
                <tbody>
                  {(ranking || []).map((r, idx) => (
                    <tr key={r.id_empleado || idx}>
                      <td>{idx + 1}</td>
                      <td className="kpi-td-main">{r.empleado}</td>
                      <td>{num(Number(r.num_ventas || 0))}</td>
                      <td>{money(Number(r.total_ventas || 0))}</td>
                      <td>{money(Number(r.promedio_venta || 0))}</td>
                    </tr>
                  ))}
                  {loading ? (
                    <tr>
                      <td colSpan={5} className="kpi-table-empty">
                        Cargando…
                      </td>
                    </tr>
                  ) : ranking.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="kpi-table-empty">
                        Sin datos para el rango seleccionado.
                      </td>
                    </tr>
                  ) : null}
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <section className="kpi-panel">
          <div className="kpi-panel-head">
            <div>
              <div className="kpi-panel-title">Alertas</div>
              <div className="kpi-panel-sub">Devoluciones por empleado</div>
            </div>
          </div>
          <div className="kpi-panel-body">
            <div className="kpi-table-wrap">
              <table className="kpi-table">
                <thead>
                  <tr>
                    <th>Empleado</th>
                    <th>Ventas</th>
                    <th>Devueltas</th>
                    <th>Tasa</th>
                  </tr>
                </thead>
                <tbody>
                  {(alerts?.devolucion_por_empleado || []).slice(0, 10).map((a) => (
                    <tr key={a.id_empleado}>
                      <td className="kpi-td-main">{a.empleado}</td>
                      <td>{num(Number(a.total_ventas || 0))}</td>
                      <td>{num(Number(a.ventas_devueltas || 0))}</td>
                      <td>{`${Number(a.tasa_devolucion_pct || 0).toFixed(2)}%`}</td>
                    </tr>
                  ))}
                  {loading ? (
                    <tr>
                      <td colSpan={4} className="kpi-table-empty">
                        Cargando…
                      </td>
                    </tr>
                  ) : (alerts?.devolucion_por_empleado || []).length === 0 ? (
                    <tr>
                      <td colSpan={4} className="kpi-table-empty">
                        Sin devoluciones en el rango seleccionado.
                      </td>
                    </tr>
                  ) : null}
                </tbody>
              </table>
            </div>
          </div>
        </section>
      </div>
    </div>
  )
}

export default App
