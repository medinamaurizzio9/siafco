const chartColors = ['#0b1f3a', '#d4af37', '#0f9f9a', '#64748b'];

const hasRenderableData = (series) => Array.isArray(series) && series.some((value) => Number(value) > 0);

const emptyChart = (element, title = 'Sin datos') => {
    element.innerHTML = `
        <div class="empty-state min-h-60">
            <span class="empty-state-icon" aria-hidden="true">--</span>
            <div>
                <p class="font-black text-siafco-primary-900">${title}</p>
                <p class="mt-1 text-sm">El grafico se completara cuando exista informacion suficiente.</p>
            </div>
        </div>
    `;
};

const unavailableCharts = () => {
    document.querySelectorAll('[data-chart]').forEach((target) => emptyChart(target, 'Grafico no disponible'));
    document.querySelectorAll('[data-sparkline]').forEach((target) => target.remove());
};

const baseOptions = {
    chart: {
        height: 250,
        toolbar: { show: false },
        animations: { enabled: true, speed: 200 },
        fontFamily: 'Instrument Sans, system-ui, sans-serif',
    },
    colors: chartColors,
    dataLabels: { enabled: false },
    legend: {
        position: 'bottom',
        fontSize: '12px',
        markers: { width: 8, height: 8, radius: 8 },
    },
    grid: { borderColor: '#dbe3ef' },
    tooltip: { theme: 'light' },
};

const areaOptions = (data) => ({
    ...baseOptions,
    chart: { ...baseOptions.chart, type: 'area' },
    series: [{ name: data.name || 'Serie', data: data.series.map((value) => Number(value)) }],
    stroke: { width: 3, curve: 'smooth' },
    fill: {
        type: 'gradient',
        gradient: { opacityFrom: 0.28, opacityTo: 0.04 },
    },
    xaxis: {
        categories: data.labels,
        labels: { style: { colors: '#64748b' } },
    },
    yaxis: {
        labels: {
            style: { colors: '#64748b' },
            formatter: (value) => data.currency ? `${Number(value).toFixed(0)} ${data.currency}` : Number(value).toFixed(0),
        },
    },
    tooltip: {
        theme: 'light',
        y: {
            formatter: (value) => data.currency ? `${Number(value).toFixed(2)} ${data.currency}` : Number(value).toString(),
        },
    },
});

const donutOptions = (data) => ({
    ...baseOptions,
    chart: { ...baseOptions.chart, type: 'donut' },
    labels: data.labels,
    series: data.series.map((value) => Number(value)),
    stroke: { width: 0 },
    plotOptions: {
        pie: {
            donut: {
                size: '68%',
                labels: { show: true, total: { show: true, label: 'Total' } },
            },
        },
    },
});

const sparklineOptions = (data) => ({
    chart: {
        type: 'area',
        height: 44,
        sparkline: { enabled: true },
        animations: { enabled: true, speed: 180 },
        fontFamily: 'Instrument Sans, system-ui, sans-serif',
    },
    colors: [data.color || '#0b1f3a'],
    series: [{ data: data.series.map((value) => Number(value)) }],
    stroke: { width: 2.5, curve: 'smooth' },
    fill: {
        type: 'gradient',
        gradient: { opacityFrom: 0.22, opacityTo: 0 },
    },
    tooltip: { enabled: false },
});

const initFullscreen = () => {
    const shell = document.querySelector('[data-dashboard-shell]');
    const button = document.querySelector('[data-dashboard-fullscreen]');
    if (!shell || !button || !document.fullscreenEnabled) {
        button?.classList.add('hidden');
        return;
    }

    const updateState = () => {
        const active = document.fullscreenElement === shell;
        shell.classList.toggle('is-fullscreen', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
        button.setAttribute('aria-label', active ? 'Salir de pantalla completa' : 'Activar pantalla completa');
        button.querySelector('span').textContent = active ? 'Salir' : 'Pantalla completa';
    };

    button.addEventListener('click', async () => {
        try {
            if (document.fullscreenElement === shell) {
                await document.exitFullscreen();
            } else {
                await shell.requestFullscreen();
            }
        } catch {
            button.classList.add('hidden');
        }
    });

    document.addEventListener('fullscreenchange', updateState);
    updateState();
};

export async function initDashboardCharts() {
    const shell = document.querySelector('[data-dashboard-charts]');
    if (!shell) return;

    let charts = {};
    let sparklines = {};
    try {
        charts = JSON.parse(shell.dataset.dashboardCharts || '{}');
        sparklines = JSON.parse(shell.dataset.dashboardSparklines || '{}');
    } catch {
        return;
    }

    initFullscreen();

    let ApexCharts;
    try {
        ({ default: ApexCharts } = await import('apexcharts'));
    } catch {
        unavailableCharts();
        return;
    }

    document.querySelectorAll('[data-chart]').forEach((target) => {
        const key = target.dataset.chart;
        const data = charts[key];
        if (!data || !hasRenderableData(data.series)) {
            emptyChart(target);
            return;
        }

        target.innerHTML = '';
        const chart = new ApexCharts(target, data.type === 'donut' ? donutOptions(data) : areaOptions(data));

        chart.render();
    });

    document.querySelectorAll('[data-sparkline]').forEach((target) => {
        const key = target.dataset.sparkline;
        const data = sparklines[key];
        if (!data || !hasRenderableData(data.series)) {
            target.remove();
            return;
        }

        target.innerHTML = '';
        const chart = new ApexCharts(target, sparklineOptions(data));
        chart.render().catch(() => target.remove());
    });

}
