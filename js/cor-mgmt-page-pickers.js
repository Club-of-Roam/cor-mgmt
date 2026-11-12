/* Filter native event/page selects without requests or automatic selection. */
(() => {
    const normalize = text => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    document.querySelectorAll('[data-search-picker]').forEach(form => {
        const search = form.querySelector('input[type="search"]');
        const select = form.querySelector('select');
        const status = form.querySelector('[data-picker-matches]');
        const options = Array.from(select.options).filter(option => option.value !== '');
        form.querySelector('[data-picker-search]').hidden = false;
        search.disabled = options.length === 0;
        search.addEventListener('input', () => {
            const query = normalize(search.value.trim());
            let matches = 0;
            options.forEach(option => {
                option.hidden = !normalize(option.textContent).includes(query);
                if (!option.hidden) matches++;
                if (option.hidden && option.selected) select.value = '';
            });
            status.textContent = form.dataset.resultText.replace('%d', String(matches));
        });
    });
})();
