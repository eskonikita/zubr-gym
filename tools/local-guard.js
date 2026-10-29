/* Development only. Kept outside the downloaded reference files. */
(() => {
  window.__ZUBR_LOCAL__ = true;
  const sourceOrigin = 'https://bronxgym.by';
  const nativeFetch = window.fetch.bind(window);
  const message = 'Локальная копия: отправка заявок и оплата будут подключены после интеграции с MODX.';
  const inform = () => {
    let notice = document.getElementById('zubr-local-notice');
    if (!notice) {
      notice = document.createElement('div'); notice.id = 'zubr-local-notice'; notice.setAttribute('role', 'status');
      notice.style.cssText = 'position:fixed;top:16px;left:50%;transform:translateX(-50%);z-index:99999;max-width:90vw;width:520px;padding:16px 20px;border:1px solid #444;border-radius:12px;background:#141414;color:white;font:14px/1.5 sans-serif;box-shadow:0 10px 40px #0008';
      document.body.appendChild(notice);
    }
    notice.textContent = message; clearTimeout(window.__zubrNoticeTimer);
    window.__zubrNoticeTimer = setTimeout(() => notice.remove(), 6500);
  };
  window.fetch = (input, options = {}) => {
    const original = typeof input === 'string' || input instanceof URL ? String(input) : input.url;
    const url = new URL(original, location.href);
    const method = (options.method || (input instanceof Request ? input.method : 'GET')).toUpperCase();
    if (!['GET', 'HEAD'].includes(method)) {
      return Promise.resolve(new Response(JSON.stringify({ error: message, message }), { status: 503, headers: { 'Content-Type': 'application/json' } }));
    }
    if (url.origin === sourceOrigin) {
      url.protocol = location.protocol; url.host = location.host;
      input = input instanceof Request ? new Request(url, input) : url.href;
    }
    if (url.origin !== location.origin) return Promise.reject(new Error('External request disabled in local development'));
    return nativeFetch(input, options);
  };
  document.addEventListener('submit', event => { event.preventDefault(); event.stopImmediatePropagation(); inform(); }, true);
  document.addEventListener('click', event => {
    const anchor = event.target.closest?.('a');
    if (anchor && /^(?:\/(?:member|account|admin|app|login|register)|https:\/\/bronxgym\.by\/(?:member|account|admin|app|login|register))\b/.test(anchor.getAttribute('href') || '')) {
      event.preventDefault(); event.stopImmediatePropagation(); inform();
    }
  }, true);
  const originalBeacon = navigator.sendBeacon?.bind(navigator);
  if (originalBeacon) navigator.sendBeacon = () => false;
})();
