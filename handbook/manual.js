(() => {
    const articles = [...document.querySelectorAll('[data-article]')];
    const nav = [...document.querySelectorAll('[data-topic]')];
    const search = document.querySelector('#manual-search');
    const normalize = (s) =>
        s
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLocaleLowerCase();
    const texts = new Map(articles.map((a) => [a.id, normalize(a.textContent)]));
    const select = (focus = false) => {
        let fragment;
        try {
            fragment = decodeURIComponent(location.hash.slice(1));
        } catch {
            fragment = '';
        }
        const target = document.getElementById(fragment);
        const article = target?.closest('[data-article]') || articles[0];
        articles.forEach((a) => {
            a.hidden = a !== article;
        });
        nav.forEach((a) => {
            if (a.dataset.topic === article.id) a.setAttribute('aria-current', 'page');
            else a.removeAttribute('aria-current');
        });
        document.querySelector('[data-language]').hash = fragment && target ? fragment : article.id;
        document.title = article.querySelector('h1').textContent + ' | OfferWeave';
        if (focus) {
            article.focus({ preventScroll: true });
            (target || article).scrollIntoView({ block: 'start' });
        }
    };
    document.querySelector('[data-search-area]').hidden = false;
    const filter = () => {
        const terms = normalize(search.value.trim()).split(/\s+/).filter(Boolean);
        let count = 0;
        nav.forEach((a) => {
            a.hidden = !terms.every((term) => texts.get(a.dataset.topic).includes(term));
            if (!a.hidden) count++;
        });
        document.querySelector('[data-empty]').hidden = count !== 0;
        document.querySelector('[data-search-status]').textContent = terms.length
            ? `${count} ${document.body.dataset.hitLabel}`
            : '';
    };
    search.addEventListener('input', filter);
    document.querySelector('[data-clear]').addEventListener('click', () => {
        search.value = '';
        filter();
        search.focus();
    });
    document.querySelectorAll('[data-print],[data-print-all]').forEach((b) => {
        b.hidden = false;
        b.addEventListener('click', () => {
            document.body.classList.toggle('owm-print-all', b.hasAttribute('data-print-all'));
            window.print();
        });
    });
    window.addEventListener('afterprint', () => document.body.classList.remove('owm-print-all'));
    window.addEventListener('hashchange', () => select(true));
    select();
})();
