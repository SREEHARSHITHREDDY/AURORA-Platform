/* ============================================================
   AURORA — sales.js
   Stage 3: Live Sales Intelligence
   AJAX + Chart.js + Dynamic Tables
   ============================================================ */

// Global chart instance (so we can destroy/recreate on period change)
let salesChartInstance = null;

// ============================================================
// LOAD DASHBOARD STATS
// ============================================================
function loadDashboardStats() {
    Aurora.fetch('php/get_dashboard_stats.php').then(data => {
        if (data.status !== 'success') return;

        // Revenue
        const revEl = document.getElementById('statRevenue');
        if (revEl) {
            revEl.textContent = Aurora.formatCurrency(data.revenue);
        }

        // Revenue change badge
        const revChangeEl = document.getElementById('statRevenueChange');
        if (revChangeEl) {
            const isUp = data.revenue_change >= 0;
            revChangeEl.className = 'stat-change ' + (isUp ? 'up' : 'down');
            revChangeEl.textContent = (isUp ? '↑ ' : '↓ ') + Math.abs(data.revenue_change) + '% vs last month';
        }

        // Orders
        const ordEl = document.getElementById('statOrders');
        if (ordEl) ordEl.textContent = Aurora.formatNumber(data.orders);

        // Sentiment
        const sentEl = document.getElementById('statSentiment');
        if (sentEl) sentEl.textContent = data.sentiment + '%';

        // Sentiment change
        const sentChangeEl = document.getElementById('statSentimentChange');
        if (sentChangeEl) {
            const isGood = data.sentiment >= 75;
            sentChangeEl.className = 'stat-change ' + (isGood ? 'up' : 'down');
            sentChangeEl.textContent = isGood ? '↑ Good' : '↓ Needs attention';
        }

        // Stock
        const stockEl = document.getElementById('statStock');
        if (stockEl) stockEl.textContent = Aurora.formatNumber(data.stock);

        // Low stock badge
        const lowEl = document.getElementById('statLowStock');
        if (lowEl) lowEl.textContent = data.low_stock + ' low stock alerts';

        // Alert count
        const alertEl = document.getElementById('alertCount');
        if (alertEl) alertEl.textContent = data.alerts;

        // Sidebar badge
        const sideAlertEl = document.querySelector('.sidebar-badge');
        if (sideAlertEl && data.alerts > 0) sideAlertEl.textContent = data.alerts;

        // Alert strip
        const alertStrip = document.getElementById('alertStrip');
        if (alertStrip && data.alerts > 0) {
            alertStrip.style.display = 'flex';
            const alertMsg = document.getElementById('alertMsg');
            if (alertMsg) alertMsg.innerHTML = `<strong>${data.alerts} strategy alerts</strong> need your attention — products below target detected.`;
        }
    });
}

// ============================================================
// LOAD SALES CHART + TOP PRODUCTS + ACTIVITY
// ============================================================
function loadSalesData(period = 'week') {
    // Show loading in chart area
    const chartWrap = document.getElementById('chartWrap');
    if (chartWrap) chartWrap.style.opacity = '0.5';

    Aurora.fetch(`php/get_sales.php?period=${period}`).then(data => {
        if (data.status !== 'success') return;
        if (chartWrap) chartWrap.style.opacity = '1';

        // ---- RENDER CHART ----
        renderSalesChart(data.chart);

        // ---- RENDER TOP PRODUCTS ----
        renderTopProducts(data.top_products);

        // ---- RENDER RECENT ACTIVITY ----
        renderRecentActivity(data.recent_sales);

        // ---- RENDER UNDERPERFORMING ALERTS ----
        renderUnderperforming(data.underperforming);
    });
}

// ============================================================
// CHART.JS — SALES CHART
// ============================================================
function renderSalesChart(chartData) {
    const canvas = document.getElementById('salesChart');
    if (!canvas) return;

    // Destroy existing chart
    if (salesChartInstance) {
        salesChartInstance.destroy();
        salesChartInstance = null;
    }

    const ctx = canvas.getContext('2d');

    salesChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [
                {
                    label: 'Actual Sales',
                    data: chartData.actual,
                    backgroundColor: (context) => {
                        const chart = context.chart;
                        const { ctx: c, chartArea } = chart;
                        if (!chartArea) return 'rgba(79,139,255,0.7)';
                        const gradient = c.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                        gradient.addColorStop(0, 'rgba(79,139,255,0.85)');
                        gradient.addColorStop(1, 'rgba(79,139,255,0.3)');
                        return gradient;
                    },
                    borderRadius:   8,
                    borderSkipped:  false,
                    borderColor:    'rgba(79,139,255,0)',
                    borderWidth:    0,
                },
                {
                    label:       'Daily Target',
                    data:        chartData.target,
                    type:        'line',
                    borderColor: 'rgba(245,158,11,0.8)',
                    borderWidth: 2,
                    borderDash:  [6, 4],
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    pointHoverBackgroundColor: 'rgba(245,158,11,1)',
                    fill:        false,
                    tension:     0,
                }
            ]
        },
        options: {
            responsive:          true,
            maintainAspectRatio: false,
            interaction: {
                mode:      'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    display:  true,
                    position: 'top',
                    labels: {
                        color:    '#7a8aaa',
                        font:     { size: 11, family: 'DM Sans' },
                        boxWidth: 14,
                        padding:  16,
                    }
                },
                tooltip: {
                    backgroundColor: '#111827',
                    borderColor:     '#1e2740',
                    borderWidth:     1,
                    titleColor:      '#f0f4ff',
                    bodyColor:       '#94a3b8',
                    padding:         12,
                    callbacks: {
                        label: ctx => ' ' + Aurora.formatCurrencyFull(ctx.raw)
                    }
                }
            },
            scales: {
                x: {
                    grid:  { color: 'rgba(255,255,255,0.04)', drawBorder: false },
                    ticks: { color: '#64748b', font: { size: 11 } }
                },
                y: {
                    grid:  { color: 'rgba(255,255,255,0.04)', drawBorder: false },
                    ticks: {
                        color:    '#64748b',
                        font:     { size: 11 },
                        callback: v => '₹' + (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v)
                    }
                }
            },
            animation: {
                duration: 800,
                easing:   'easeInOutQuart'
            }
        }
    });
}

// ============================================================
// TOP PRODUCTS TABLE
// ============================================================
function renderTopProducts(products) {
    const container = document.getElementById('topProductsList');
    if (!container) return;

    if (!products || products.length === 0) {
        container.innerHTML = '<p class="text-muted text-sm" style="padding:20px 0">No sales data yet.</p>';
        return;
    }

    container.innerHTML = products.map((p, i) => `
        <div class="product-row">
            <div class="product-rank">#${i + 1}</div>
            <div class="product-info">
                <div class="product-name">${p.name}</div>
                <div class="product-units">${Aurora.formatNumber(p.units_sold)} units · ${p.category}</div>
            </div>
            <div class="product-bar-wrap">
                <div class="product-bar">
                    <div class="product-fill" style="width:${p.percentage}%;background:${
                        i === 0 ? 'var(--blue)' :
                        i === 1 ? 'var(--violet)' :
                        i === 2 ? 'var(--cyan)' : 'var(--muted)'
                    }"></div>
                </div>
            </div>
            <div class="product-val">${Aurora.formatCurrency(p.revenue)}</div>
        </div>
    `).join('');
}

// ============================================================
// RECENT ACTIVITY FEED
// ============================================================
function renderRecentActivity(sales) {
    const container = document.getElementById('activityList');
    if (!container) return;

    if (!sales || sales.length === 0) {
        container.innerHTML = '<p class="text-muted text-sm" style="padding:20px 0">No recent activity.</p>';
        return;
    }

    container.innerHTML = sales.map(s => `
        <div class="activity-item">
            <div class="activity-dot" style="background:var(--green)"></div>
            <div class="activity-body">
                <div class="activity-title">
                    Sale — ${s.product_name} × ${s.quantity} units
                </div>
                <div class="activity-time">${Aurora.timeAgo(s.sale_date)} · ${Aurora.formatCurrencyFull(s.amount)}</div>
            </div>
            <span class="badge badge-green">Sale</span>
        </div>
    `).join('');
}

// ============================================================
// UNDERPERFORMING PRODUCTS
// ============================================================
function renderUnderperforming(products) {
    const container = document.getElementById('underperformingList');
    if (!container) return;

    if (!products || products.length === 0) {
        container.innerHTML = `
            <div style="text-align:center;padding:24px 0">
                <div style="font-size:28px;margin-bottom:8px">✅</div>
                <div class="text-sm text-muted">All products meeting targets!</div>
            </div>`;
        return;
    }

    container.innerHTML = products.map(p => {
        const level    = p.achievement < 40 ? 'critical' : p.achievement < 60 ? 'medium' : 'low';
        const badgeClass = level === 'critical' ? 'badge-red' : level === 'medium' ? 'badge-amber' : 'badge-green';
        const label    = level === 'critical' ? 'Critical' : level === 'medium' ? 'Below Target' : 'Watch';
        return `
        <div class="strategy-card ${level}" style="margin-bottom:12px">
            <div class="strategy-header">
                <div>
                    <div class="strategy-title">${p.name}</div>
                    <div class="strategy-product">${p.category} · ${p.units_sold}/${p.target_sales} units</div>
                </div>
                <span class="badge ${badgeClass}">${label}</span>
            </div>
            <div class="progress-wrap" style="margin:10px 0 6px">
                <div class="progress-header">
                    <span class="progress-label">Achievement</span>
                    <span class="progress-value">${p.achievement}%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill ${level === 'critical' ? 'red' : level === 'medium' ? 'amber' : 'green'}" 
                         style="width:${p.achievement}%"></div>
                </div>
            </div>
        </div>`;
    }).join('');
}

// ============================================================
// STOCK STATUS BARS
// ============================================================
function loadStockStatus() {
    Aurora.fetch('php/get_dashboard_stats.php').then(data => {
        if (data.status !== 'success') return;

        const total    = parseInt(data.stock) + (data.low_stock * 10);
        const inStock  = data.low_stock > 0 ? Math.round((data.stock / (data.stock + data.low_stock)) * 100) : 100;
        const lowPct   = Math.round((data.low_stock / Math.max(1, data.stock)) * 100);

        const inEl  = document.getElementById('stockInPct');
        const lowEl = document.getElementById('stockLowPct');

        if (inEl)  { inEl.style.width  = Math.min(inStock, 100) + '%'; }
        if (lowEl) { lowEl.style.width = Math.min(lowPct,  100) + '%'; }
    });
}

// ============================================================
// PERIOD CHANGE HANDLER
// ============================================================
function changePeriod(period) {
    loadSalesData(period);

    // Update button styles
    document.querySelectorAll('.period-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.period === period);
    });
}

// ============================================================
// INIT — Run on page load
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    // Load all dashboard data
    loadDashboardStats();
    loadSalesData('week');
    loadStockStatus();

    // Period selector
    const periodSelect = document.getElementById('chartPeriod');
    if (periodSelect) {
        periodSelect.addEventListener('change', function () {
            const map = {
                'This Week':  'week',
                'Last Week':  'week',
                'This Month': 'month'
            };
            loadSalesData(map[this.value] || 'week');
        });
    }

    // Refresh button
    const refreshBtn = document.getElementById('refreshBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => {
            loadDashboardStats();
            loadSalesData('week');
            refreshBtn.textContent = '✅ Refreshed!';
            setTimeout(() => refreshBtn.textContent = '↻ Refresh', 2000);
        });
    }
});