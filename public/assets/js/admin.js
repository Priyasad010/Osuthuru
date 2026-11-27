/** Dashboard charts. The same figures are listed in tables and reports. */
(function () {
    if (!window.Chart) {
        return;
    }

    const sales = document.getElementById('salesChart');
    if (sales) {
        const d = JSON.parse(sales.dataset.chart);
        const line = (label, data, color, extra) => Object.assign({
            label: label, data: data, borderColor: color, backgroundColor: color, pointRadius: 0, borderWidth: 2
        }, extra);

        new window.Chart(sales, {
            type: 'line',
            data: {
                labels: d.labels,
                datasets: [
                    line(d.names[0], d.actual, '#198754'),
                    line(d.names[1], d.forecast, '#d9822b', { borderDash: [6, 4] }),
                    line(d.names[2], d.lower, 'rgba(217,130,43,0.35)', { borderWidth: 1 }),
                    line(d.names[3], d.upper, 'rgba(217,130,43,0.35)', { borderWidth: 1, fill: '-1', backgroundColor: 'rgba(217,130,43,0.12)' })
                ]
            },
            options: {
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: { y: { beginAtZero: true }, x: { ticks: { maxTicksLimit: 10 } } }
            }
        });
    }

    const categories = document.getElementById('categoryChart');
    if (categories) {
        const d = JSON.parse(categories.dataset.chart);
        new window.Chart(categories, {
            type: 'bar',
            data: { labels: d.labels, datasets: [{ data: d.values, backgroundColor: '#2d6a4f' }] },
            options: { maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } } }
        });
    }
})();
