const list = document.querySelector('[data-blog-list]');
if (list) {
  const cards = [...list.querySelectorAll('.blog-card')];
  const size = Math.max(1, Number(list.dataset.pageSize) || 6);
  const total = Math.max(1, Math.ceil(cards.length / size));
  const bar = document.querySelector('[data-blog-pagination-bar]');
  const nav = document.querySelector('[data-blog-pagination]');
  const status = document.querySelector('[data-blog-page-status]');
  const urlFor = page => {
    const url = new URL(location.href);
    url.hash = '';
    if (page === 1) url.searchParams.delete('page');
    else url.searchParams.set('page', page);
    return url.pathname + url.search;
  };
  const control = (page, label, content, disabled, current = false) => {
    const element = document.createElement(disabled || current ? 'span' : 'a');
    element.className = 'blog-page-link';
    element.textContent = content;
    element.setAttribute('aria-label', label);
    if (current) element.setAttribute('aria-current', 'page');
    else if (disabled) element.setAttribute('aria-disabled', 'true');
    else {
      element.href = urlFor(page);
      element.dataset.blogPage = page;
    }
    return element;
  };
  function render(scroll = false) {
    const value = new URL(location.href).searchParams.get('page');
    const requested = /^\d+$/.test(value || '') ? Number(value) : 1;
    const page = Number.isSafeInteger(requested) ? Math.max(1, Math.min(total, requested)) : 1;
    // Keep deep links and the browser history consistent with the page shown.
    const cleanUrl = urlFor(page);
    if (location.pathname + location.search !== cleanUrl) history.replaceState(null, '', cleanUrl);
    cards.forEach((card, index) => { card.hidden = index < (page - 1) * size || index >= page * size; });
    nav.replaceChildren(control(page - 1, 'Предыдущая страница', '←', page === 1));
    const visiblePages = new Set([1, total, page - 1, page, page + 1]);
    if (page <= 3) [2, 3, 4].forEach(n => visiblePages.add(n));
    if (page >= total - 2) [total - 3, total - 2, total - 1].forEach(n => visiblePages.add(n));
    let previous = 0;
    [...visiblePages].filter(n => n >= 1 && n <= total).sort((a, b) => a - b).forEach(n => {
      if (previous && n - previous > 1) {
        const gap = document.createElement('span');
        gap.className = 'blog-page-gap';
        gap.textContent = '…';
        gap.setAttribute('aria-hidden', 'true');
        nav.append(gap);
      }
      nav.append(control(n, `Страница ${n}`, String(n), false, n === page));
      previous = n;
    });
    nav.append(control(page + 1, 'Следующая страница', '→', page === total));
    status.textContent = cards.length ? `Показано ${(page - 1) * size + 1}–${Math.min(page * size, cards.length)} из ${cards.length}` : 'Пока нет материалов';
    bar.hidden = cards.length === 0;
    if (scroll) {
      list.focus({ preventScroll: true });
      list.scrollIntoView({ block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
    }
  }
  nav.addEventListener('click', event => {
    const link = event.target.closest('a[data-blog-page]');
    if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    history.pushState(null, '', link.getAttribute('href'));
    render(true);
  });
  addEventListener('popstate', () => render());
  render();
}
