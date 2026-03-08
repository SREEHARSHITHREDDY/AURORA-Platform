/* ============================================================
   AURORA — owner.js
   Stage 6: Owner Portal — multi-branch, risk alerts
   ============================================================ */

   document.addEventListener('DOMContentLoaded', () => {
    loadOwnerData();
});

function loadOwnerData() {
    Aurora.fetch('php/get_owner_stats.php').then(data => {
        if (data.status !== 'success') return;
        renderOwnerStats(data);
        renderBranchChart(data.branches);
        renderRiskAlerts(data.risks);
    });
}

function renderOwnerStats(data) {
    const els = {
        totalRevenue: document.getElementById('ownerTotalRevenue'),
        branches:     document.getElementById('ownerBranches'),
        vendors:      document.getElementById('ownerVendors'),
        alerts:       document.getElementById('ownerAlerts'),
    };
    if (els.totalRevenue) els.totalRevenue.textContent = Aurora.formatCurrency(data.total_revenue || 0);
    if (els.branches)     els.branches.textContent     = data.branch_count  || 0;
    if (els.vendors)      els.vendors.textContent      = data.vendor_count  || 0;
    if (els.alerts)       els.alerts.textContent       = data.total_alerts  || 0;
}

let branchChartInstance = null;

function renderBranchChart(branches) {
    if (!branches || !branches.length) return;
    const canvas = document.getElementById('branchChart');
    if (!canvas) return;

    if (branchChartInstance) {
        branchChartInstance.destroy();
    }

    branchChartInstance = new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: branches.map(b => b.name),
            datasets: [{
                label: 'Revenue (₹)',
                data:  branches.map(b => b.revenue),
                backgroundColor: [
                    'rgba(79,139,255,0.7)',
                    'rgba(139,92,246,0.7)',
                    'rgba(245,158,11,0.7)',
                    'rgba(34,211,238,0.7)'
                ],
                borderRadius:  8,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#111827',
                    borderColor: '#1e2740',
                    borderWidth: 1,
                    titleColor: '#f0f4ff',
                    bodyColor: '#94a3b8',
                    callbacks: { label: ctx => ' ' + Aurora.formatCurrencyFull(ctx.raw) }
                }
            },
            scales: {
                x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b', font: { size: 12 } } },
                y: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b', font: { size: 11 }, callback: v => '₹' + (v / 1000).toFixed(0) + 'k' } }
            }
        }
    });
}

function renderRiskAlerts(risks) {
    const container = document.getElementById('riskAlerts');
    if (!container || !risks) return;

    if (risks.length === 0) {
        container.innerHTML = `
            <div class="risk-item ok">
                <div class="risk-icon">✅</div>
                <div><div class="risk-title">All Systems Healthy</div>
                <div class="risk-desc">No critical issues detected across any branch.</div></div>
            </div>`;
        return;
    }

    container.innerHTML = risks.map(r => `
        <div class="risk-item ${r.level}">
            <div class="risk-icon">${r.level === 'critical' ? '🔴' : r.level === 'warning' ? '🟡' : '✅'}</div>
            <div style="flex:1">
                <div class="risk-title">${r.title}</div>
                <div class="risk-desc">${r.description}</div>
            </div>
            <span class="badge ${r.level === 'critical' ? 'badge-red' : r.level === 'warning' ? 'badge-amber' : 'badge-green'}" style="flex-shrink:0">
                ${r.level.charAt(0).toUpperCase() + r.level.slice(1)}
            </span>
        </div>`).join('');
}