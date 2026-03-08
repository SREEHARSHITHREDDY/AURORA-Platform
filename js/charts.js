/* ============================================================
   AURORA — charts.js
   Reusable Chart.js configurations
   ============================================================ */

   const AuroraCharts = {

    // Default dark theme options
    defaultOptions: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'top',
                labels: {
                    color: '#7a8aaa',
                    font: { size: 11, family: 'DM Sans' },
                    boxWidth: 14,
                    padding: 16
                }
            },
            tooltip: {
                backgroundColor: '#111827',
                borderColor: '#1e2740',
                borderWidth: 1,
                titleColor: '#f0f4ff',
                bodyColor: '#94a3b8',
                padding: 12
            }
        },
        scales: {
            x: {
                grid: { color: 'rgba(255,255,255,0.04)', drawBorder: false },
                ticks: { color: '#64748b', font: { size: 11 } }
            },
            y: {
                grid: { color: 'rgba(255,255,255,0.04)', drawBorder: false },
                ticks: { color: '#64748b', font: { size: 11 } }
            }
        },
        animation: { duration: 800, easing: 'easeInOutQuart' }
    },

    // Bar chart
    createBar(canvasId, labels, datasets, yFormatter) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return null;
        const opts = JSON.parse(JSON.stringify(this.defaultOptions));
        if (yFormatter) {
            opts.scales.y.ticks.callback = yFormatter;
        }
        return new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: { labels, datasets },
            options: opts
        });
    },

    // Line chart
    createLine(canvasId, labels, datasets, yFormatter) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return null;
        const opts = JSON.parse(JSON.stringify(this.defaultOptions));
        if (yFormatter) {
            opts.scales.y.ticks.callback = yFormatter;
        }
        return new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: { labels, datasets },
            options: opts
        });
    },

    // Doughnut chart
    createDoughnut(canvasId, labels, data, colors) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return null;
        return new Chart(canvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data,
                    backgroundColor: colors || ['rgba(79,139,255,0.8)', 'rgba(139,92,246,0.8)', 'rgba(34,211,238,0.8)', 'rgba(245,158,11,0.8)'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { color: '#7a8aaa', font: { size: 11 }, padding: 14, boxWidth: 12 }
                    },
                    tooltip: {
                        backgroundColor: '#111827',
                        borderColor: '#1e2740',
                        borderWidth: 1,
                        titleColor: '#f0f4ff',
                        bodyColor: '#94a3b8',
                    }
                }
            }
        });
    },

    // Currency Y-axis formatter
    currencyFormatter(v) {
        return '₹' + (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v);
    },

    // Destroy chart safely
    destroy(chartInstance) {
        if (chartInstance) {
            chartInstance.destroy();
        }
        return null;
    }
};