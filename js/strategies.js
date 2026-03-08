/* ============================================================
   AURORA — strategies.js
   Stage 5: AI Strategy Engine — Rule-based recommendations
   ============================================================ */

   document.addEventListener('DOMContentLoaded', () => {
    loadStrategies();
});

function loadStrategies() {
    const container = document.getElementById('strategiesContainer');
    if (!container) return;

    container.innerHTML = `
        <div style="text-align:center;padding:40px">
            <div class="spinner" style="margin:0 auto 12px"></div>
            <div class="text-muted text-sm">Generating AI strategies...</div>
        </div>`;

    Aurora.fetch('php/get_strategies.php').then(data => {
        if (data.status !== 'success') {
            container.innerHTML = `<div class="alert alert-error show"><span class="alert-icon">⚠️</span>Failed to load strategies.</div>`;
            return;
        }
        renderStrategies(data.strategies, data.summary);
    });
}

function renderStrategies(strategies, summary) {
    // Update summary stats
    if (summary) {
        const critEl = document.getElementById('criticalCount');
        const medEl  = document.getElementById('mediumCount');
        const lowEl  = document.getElementById('lowCount');
        if (critEl) critEl.textContent = summary.critical || 0;
        if (medEl)  medEl.textContent  = summary.medium   || 0;
        if (lowEl)  lowEl.textContent  = summary.low      || 0;
    }

    const container = document.getElementById('strategiesContainer');
    if (!container) return;

    if (!strategies || strategies.length === 0) {
        container.innerHTML = `
            <div style="text-align:center;padding:48px">
                <div style="font-size:40px;margin-bottom:12px">✅</div>
                <div style="font-family:var(--font-head);font-size:18px;font-weight:700;margin-bottom:8px">All products on track!</div>
                <div class="text-muted">No strategy interventions needed right now.</div>
            </div>`;
        return;
    }

    container.innerHTML = strategies.map(s => {
        const icons = {
            discount:    '💸',
            bundle:      '📦',
            reposition:  '🔄',
            promote:     '📣',
            quality:     '⭐',
            restock:     '🔁',
            clearance:   '🏷️'
        };
        const icon = icons[s.type] || '🧠';

        return `
        <div class="strategy-card ${s.priority}" style="margin-bottom:14px">
            <div class="strategy-header">
                <div style="display:flex;align-items:flex-start;gap:12px">
                    <div style="font-size:22px;flex-shrink:0">${icon}</div>
                    <div>
                        <div class="strategy-title">${s.title}</div>
                        <div class="strategy-product">${s.product_name} · ${s.category}</div>
                    </div>
                </div>
                <span class="badge ${s.priority === 'critical' ? 'badge-red' : s.priority === 'medium' ? 'badge-amber' : 'badge-green'}">
                    ${s.priority.charAt(0).toUpperCase() + s.priority.slice(1)}
                </span>
            </div>
            <div class="strategy-body">${s.description}</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                ${s.metrics ? `
                    <span class="badge badge-muted">📊 Sold: ${s.metrics.sold}/${s.metrics.target}</span>
                    <span class="badge badge-muted">📈 Achievement: ${s.metrics.achievement}%</span>
                    ${s.metrics.days_no_sale > 0 ? `<span class="badge badge-violet">🕐 No sale: ${s.metrics.days_no_sale} days</span>` : ''}
                ` : ''}
            </div>
        </div>`;
    }).join('');
}

function filterStrategies(priority) {
    document.querySelectorAll('.strategy-card').forEach(card => {
        card.style.display = (priority === 'all' || card.classList.contains(priority)) ? '' : 'none';
    });
    document.querySelectorAll('.strat-filter-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.priority === priority);
    });
}