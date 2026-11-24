// Poll the extraction status while the queued job is running, then reload.
const poller = document.querySelector('[data-poll-url]');

if (poller) {
    const { pollUrl, pollStatus } = poller.dataset;

    const timer = setInterval(async () => {
        try {
            const res = await fetch(pollUrl, { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const { status } = await res.json();
            if (status !== pollStatus) {
                clearInterval(timer);
                window.location.reload();
            }
        } catch {
            // Network hiccup: try again on the next tick.
        }
    }, 3000);
}

// Slow submissions (<form data-loading>): show progress and allow only one submit,
// since every extra click would start another paid extraction.
document.querySelectorAll('form[data-loading]').forEach((form) => {
    const button = form.querySelector('[data-loading-text]');
    const panel = form.querySelector('[data-loading-panel]');
    const label = button.textContent;

    const setLoading = (loading) => {
        button.disabled = loading;
        button.textContent = loading ? button.dataset.loadingText : label;
        panel.hidden = !loading;
        form.setAttribute('aria-busy', String(loading));
    };

    form.addEventListener('submit', (event) => {
        if (button.disabled) {
            event.preventDefault();
            return;
        }
        setLoading(true);
    });

    // Coming back with the browser's Back button can restore the frozen loading state.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) setLoading(false);
    });
});

// "＋ 職歴を追加": clone the last (blank) row with a fresh index.
const addHistory = document.querySelector('[data-add-history]');

if (addHistory) {
    addHistory.addEventListener('click', () => {
        const container = document.querySelector('[data-history-rows]');
        const rows = container.querySelectorAll('[data-history-row]');
        const clone = rows[rows.length - 1].cloneNode(true);
        const index = rows.length;

        clone.querySelectorAll('input, textarea').forEach((el) => {
            el.name = el.name.replace(/work_histories\[\d+]/, `work_histories[${index}]`);
            el.value = '';
        });
        clone.querySelectorAll('.text-rose-600').forEach((el) => el.remove());

        container.appendChild(clone);
    });
}

// Candidate-pool histograms. Chart.js is loaded only on pages that have one.
const histograms = document.querySelectorAll('canvas[data-histogram]');

// The skill picker counts too: the first skill's chart may be withheld
// (sample too small) while others can still be drawn.
if (histograms.length > 0 || document.querySelector('[data-skill-select]')) {
    const { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip } = await import('chart.js');
    Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

    const ink = { primary: '#0f172a', secondary: '#475569', grid: '#e2e8f0' };
    const series = '#2a78d6';

    // Count at the tip of each bar (zero-height bars stay unlabelled).
    const valueLabels = {
        id: 'valueLabels',
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            ctx.save();
            ctx.fillStyle = ink.secondary;
            ctx.font = '12px ui-sans-serif, system-ui, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            chart.getDatasetMeta(0).data.forEach((bar, i) => {
                const value = chart.data.datasets[0].data[i];
                if (value > 0) ctx.fillText(`${value}名`, bar.x, bar.y - 4);
            });
            ctx.restore();
        },
    };

    // Charts in hidden panels are drawn the first time they are shown,
    // since a canvas inside a hidden element has no size to draw at.
    const drawHistogram = (canvas) => {
        if (canvas.dataset.drawn || canvas.closest('[hidden]')) return;
        canvas.dataset.drawn = 'true';

        const { labels, counts, axisLabel } = JSON.parse(canvas.dataset.histogram);

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    data: counts,
                    backgroundColor: series,
                    hoverBackgroundColor: '#1f63b8',
                    borderRadius: { topLeft: 4, topRight: 4 },
                    borderSkipped: 'start',
                    maxBarThickness: 24,
                }],
            },
            options: {
                maintainAspectRatio: false,
                layout: { padding: { top: 20 } },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        displayColors: false,
                        callbacks: {
                            title: (items) => `${axisLabel}: ${items[0].label}`,
                            label: (item) => `${item.raw}名`,
                        },
                    },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: ink.secondary } },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: ink.grid },
                        ticks: { color: ink.secondary, precision: 0 },
                        title: { display: true, text: '人数', color: ink.secondary },
                    },
                },
            },
            plugins: [valueLabels],
        });
    };

    histograms.forEach(drawHistogram);

    // Skill picker: swap to that skill's panel in place, leaving the other
    // charts alone, and keep ?skill= in the URL so a reload shows the same one.
    document.querySelectorAll('[data-skill-select]').forEach((select) => {
        select.addEventListener('change', () => {
            const panels = select.closest('[data-skill-panels]');
            const target = [...panels.querySelectorAll('[data-skill-panel]')]
                .find((panel) => panel.dataset.skillPanel === select.value);
            if (!target) return;

            panels.querySelectorAll('[data-skill-panel]').forEach((panel) => {
                panel.hidden = panel !== target;
            });
            target.querySelectorAll('canvas[data-histogram]').forEach(drawHistogram);

            // Each panel has its own picker; keep keyboard focus on the visible one.
            target.querySelector('[data-skill-select]')?.focus();

            const url = new URL(window.location.href);
            url.searchParams.set('skill', select.value);
            window.history.replaceState(null, '', url);
        });
    });
}

// Destructive actions: <form data-confirm="message"> asks before submitting.
document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});

// Stage changes that need a second look (e.g. reopening a rejected candidate).
document.querySelectorAll('form[data-confirm-stage]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const message = form.querySelector('select[name="stage"] option:checked')?.dataset.confirm;
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });
});
