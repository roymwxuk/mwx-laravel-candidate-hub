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
