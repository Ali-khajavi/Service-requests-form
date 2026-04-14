/* Calculator helper triggering AJAX to calculate quotes */
(function () {
    const currency = tpq.currencySymbol || '$';

    function getForm() {
        return document.querySelector('.tpq-shortcode-form');
    }

    function collectQuoteInputs() {
        const form = getForm();
        if (!form) {
            return {};
        }

        const payload = {};
        new FormData(form).forEach((value, key) => {
            payload[key] = value;
        });

        return payload;
    }

    function requestQuoteCalculation(payload) {
        const form = getForm();
        if (!form) {
            return Promise.resolve();
        }

        setCalculationLoading(true);

        payload.action = 'tpq_calculate_quote';
        payload.nonce = tpq.nonces && tpq.nonces.calculate ? tpq.nonces.calculate : '';

        return fetch(tpq.ajaxUrl, {
            method: 'POST',
            body: new URLSearchParams(payload),
        })
            .then((res) => res.json())
            .then((data) => {
                if (!data.success) {
                    throw new Error((data.data && data.data.message) || 'Quote error');
                }

                renderQuoteBreakdown(data.data);
            })
            .catch((error) => {
                renderQuoteError(error.message);
            })
            .finally(() => setCalculationLoading(false));
    }

    function renderQuoteBreakdown(result) {
        const breakdown = document.querySelector('.tpq-quote-breakdown');
        const total = document.querySelector('.tpq-total-value');

        if (!breakdown || !total) {
            return;
        }

        breakdown.innerHTML = '';

        if (!result || !result.breakdown) {
            breakdown.textContent = 'No data yet';
            total.textContent = '—';
            return;
        }

        Object.entries(result.breakdown).forEach(([label, value]) => {
            const row = document.createElement('div');
            row.className = 'tpq-quote-result__row';
            row.innerHTML = `<span>${label}</span><strong>${formatCurrency(value)}</strong>`;
            breakdown.appendChild(row);
        });

        total.textContent = formatCurrency(result.total || 0);
    }

    function renderQuoteError(message) {
        const breakdown = document.querySelector('.tpq-quote-breakdown');
        const total = document.querySelector('.tpq-total-value');

        if (breakdown) {
            breakdown.textContent = message;
        }

        if (total) {
            total.textContent = '—';
        }
    }

    function setCalculationLoading(state) {
        const loading = document.querySelector('.tpq-calculation-loading');
        if (loading) {
            loading.classList.toggle('is-visible', !!state);
        }
    }

    function formatCurrency(value) {
        return `${currency}${Number(value || 0).toFixed(2)}`;
    }

    const debouncedCalculate = debounce(() => requestQuoteCalculation(collectQuoteInputs()), 300);

    document.addEventListener('DOMContentLoaded', () => {
        const form = getForm();
        if (!form) {
            return;
        }

        form.addEventListener('input', debouncedCalculate);
        requestQuoteCalculation(collectQuoteInputs());

        document.addEventListener('tpqModelUploaded', () => {
            requestQuoteCalculation(collectQuoteInputs());
        });
    });

    function debounce(fn, delay) {
        let timeout;

        return function () {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn.apply(this, arguments), delay);
        };
    }
})();