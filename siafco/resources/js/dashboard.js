const chartColors = ['#0b1f3a', '#d4af37', '#0f9f9a', '#64748b'];

const hasRenderableData = (series) => Array.isArray(series) && series.some((value) => Number(value) > 0);

const emptyChart = (element) => {
    element.innerHTML = `
        <div class="empty-state min-h-60">
            <span class="empty-state-icon" aria-hidden="true">--</span>
            <div>
                <p class="font-black text-siafco-primary-900">Sin datos</p>
                <p class="mt-1 text-sm">El grafico se completara cuando exista informacion suficiente.</p>
            </div>
        </div>
    `;
};

export async function initDashboardCharts() {
    const shell = document.querySelector('[data-dashboard-charts]');
    if (!shell) return;

    let charts = {};
    try {
        charts = JSON.parse(shell.dataset.dashboardCharts || '{}');
    } catch {
        return;
    }

    const { default: ApexCharts } = await import('apexcharts');

    document.querySelectorAll('[data-chart]').forEach((target) => {
        const key = target.dataset.chart;
        const data = charts[key];
        if (!data || !hasRenderableData(data.series)) {
            emptyChart(target);
            return;
        }

        target.innerHTML = '';
        const chart = new ApexCharts(target, {
            chart: {
                type: key === 'operations' ? 'bar' : 'donut',
                height: 240,
                toolbar: { show: false },
                animations: { enabled: true, speed: 200 },
                fontFamily: 'Instrument Sans, system-ui, sans-serif',
            },
            colors: chartColors,
            labels: data.labels,
            series: data.series.map((value) => Number(value)),
            legend: {
                position: 'bottom',
                fontSize: '12px',
                markers: { width: 8, height: 8, radius: 8 },
            },
            dataLabels: { enabled: false },
            stroke: { width: 0 },
            plotOptions: {
                bar: {
                    borderRadius: 6,
                    columnWidth: '45%',
                    distributed: true,
                },
                pie: {
                    donut: {
                        size: '68%',
                        labels: { show: true, total: { show: true, label: 'Total' } },
                    },
                },
            },
            xaxis: {
                categories: data.labels,
                labels: { style: { colors: '#64748b' } },
            },
            yaxis: {
                labels: { style: { colors: '#64748b' } },
            },
            grid: { borderColor: '#dbe3ef' },
            tooltip: { theme: 'light' },
        });

        chart.render();
    });
}
