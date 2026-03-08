/* ============================================================
   AURORA — inventory.js
   Stage 4: Inventory tracking, dead stock, low stock alerts
   ============================================================ */

   document.addEventListener('DOMContentLoaded', () => {
    loadInventoryData();
});

function loadInventoryData() {
    const container = document.getElementById('inventoryTable');
    if (!container) return;

    Aurora.fetch('php/get_inventory.php').then(data => {
        if (data.status !== 'success') {
            Aurora.showError('#inventoryTable', 'Failed to load inventory data.');
            return;
        }
        renderInventoryTable(data.inventory);
        renderInventoryStats(data.stats);
    });
}

function renderInventoryStats(stats) {
    if (!stats) return;
    const inEl   = document.getElementById('invTotal');
    const lowEl  = document.getElementById('invLow');
    const outEl  = document.getElementById('invOut');
    const deadEl = document.getElementById('invDead');
    if (inEl)   inEl.textContent   = Aurora.formatNumber(stats.total_stock);
    if (lowEl)  lowEl.textContent  = stats.low_stock;
    if (outEl)  outEl.textContent  = stats.out_of_stock;
    if (deadEl) deadEl.textContent = stats.dead_stock;
}

function renderInventoryTable(items) {
    const tbody = document.getElementById('inventoryTableBody');
    if (!tbody) return;

    if (!items || items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--muted)">No inventory data found.</td></tr>';
        return;
    }

    tbody.innerHTML = items.map(item => {
        let statusBadge = '';
        let statusClass = '';
        if (item.stock_qty === 0) {
            statusBadge = '<span class="badge badge-red">Out of Stock</span>';
            statusClass = 'out';
        } else if (item.stock_qty <= item.min_stock) {
            statusBadge = '<span class="badge badge-amber">Low Stock</span>';
            statusClass = 'low';
        } else if (item.days_no_sale > 14) {
            statusBadge = '<span class="badge badge-violet">Dead Stock</span>';
            statusClass = 'dead';
        } else {
            statusBadge = '<span class="badge badge-green">In Stock</span>';
            statusClass = 'ok';
        }

        return `
        <tr class="${statusClass}">
            <td class="td-main">${item.product_name}</td>
            <td>${item.category}</td>
            <td>
                <div style="display:flex;align-items:center;gap:10px">
                    <div style="font-weight:600;color:${item.stock_qty <= item.min_stock ? 'var(--red)' : 'var(--text)'}">${item.stock_qty}</div>
                    <div class="product-bar" style="width:60px">
                        <div class="product-fill" style="width:${Math.min(100, (item.stock_qty / Math.max(item.min_stock * 5, 1)) * 100)}%;
                            background:${item.stock_qty <= item.min_stock ? 'var(--red)' : 'var(--green)'}"></div>
                    </div>
                </div>
            </td>
            <td style="color:var(--muted)">${item.min_stock}</td>
            <td>${statusBadge}</td>
            <td style="color:var(--muted)">${item.days_no_sale > 0 ? item.days_no_sale + ' days ago' : 'Today'}</td>
            <td>
                <button class="btn btn-ghost btn-sm" onclick="updateStock(${item.product_id}, ${item.stock_qty})">Edit</button>
            </td>
        </tr>`;
    }).join('');
}

function updateStock(productId, currentStock) {
    const newStock = prompt(`Update stock quantity (current: ${currentStock}):`, currentStock);
    if (newStock === null || isNaN(newStock)) return;

    fetch('php/update_inventory.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId, stock_qty: parseInt(newStock) }),
        credentials: 'same-origin'
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            loadInventoryData();
        }
    });
}

function filterInventory(status) {
    const rows = document.querySelectorAll('#inventoryTableBody tr');
    rows.forEach(row => {
        if (status === 'all') {
            row.style.display = '';
        } else {
            row.style.display = row.classList.contains(status) ? '' : 'none';
        }
    });

    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.filter === status);
    });
}