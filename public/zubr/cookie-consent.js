(() => {
  'use strict';
  const key = 'zubr_cookie_consent';
  const version = 2;
  const lifetime = 180 * 24 * 60 * 60 * 1000;
  const banner = document.querySelector('[data-cookie-banner]');
  const dialog = document.querySelector('[data-cookie-dialog]');
  let opener;
  let memory = null;
  function read() {
    try {
      const part = document.cookie.split('; ').find(item => item.startsWith(key + '='));
      if (!part) return null;
      const data = JSON.parse(decodeURIComponent(part.slice(key.length + 1)));
      if (data.version !== version || data.necessary !== true || typeof data.functional !== 'boolean' || typeof data.analytics !== 'boolean'
          || !Number.isFinite(data.savedAt) || !Number.isFinite(data.expiresAt)
          || data.savedAt <= 0 || data.savedAt > Date.now() + 60000 || data.expiresAt <= Date.now() || data.expiresAt <= data.savedAt
          || data.expiresAt - data.savedAt > lifetime + 1000) return null;
      return data;
    } catch { return null; }
  }
  memory = read();
  function current() {
    if (memory && memory.expiresAt <= Date.now()) memory = null;
    return memory ? {...memory} : {necessary: true, functional: false, analytics: false};
  }
  function hydrate() {
    document.querySelectorAll('[data-cookie-functional]').forEach(input => { input.checked = current().functional; });
    document.querySelectorAll('[data-cookie-analytics]').forEach(input => { input.checked = current().analytics; });
  }
  function syncModal() { document.body.classList.toggle('modal-open', !!document.querySelector('dialog[open]')); }
  function close() { if (dialog?.open) dialog.close(); }
  function open(trigger) {
    if (!dialog || dialog.open) return;
    opener = trigger || document.activeElement;
    hydrate();
    dialog.showModal();
    syncModal();
  }
  function save(choice) {
    const savedAt = Date.now();
    const functional = typeof choice === 'object' ? choice.functional === true : choice === true;
    const analytics = typeof choice === 'object' ? choice.analytics === true : choice === true;
    memory = {version, necessary: true, functional, analytics, savedAt, expiresAt: savedAt + lifetime};
    try {
      document.cookie = key + '=' + encodeURIComponent(JSON.stringify(memory))
        + '; Path=/; Max-Age=' + lifetime / 1000 + '; Expires=' + new Date(memory.expiresAt).toUTCString()
        + '; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    } catch { /* Without browser storage the choice applies only to this page. */ }
    const stored = read();
    const persisted = stored?.savedAt === memory.savedAt && stored.functional === memory.functional && stored.analytics === memory.analytics;
    hydrate();
    if (banner) banner.hidden = true;
    document.querySelectorAll('[data-cookie-status]').forEach(node => {
      node.textContent = persisted ? 'Настройки сохранены на 180 дней.'
        : 'Выбор применён. Браузер не разрешил сохранить его для следующих посещений.';
    });
    window.dispatchEvent(new CustomEvent('zubr:consentchange', {detail: current()}));
    close();
    return persisted;
  }
  document.querySelectorAll('[data-cookie-open]').forEach(button => button.addEventListener('click', () => open(button)));
  document.querySelectorAll('[data-cookie-close]').forEach(button => button.addEventListener('click', close));
  document.querySelectorAll('[data-cookie-accept]').forEach(button => button.addEventListener('click', () => save(true)));
  document.querySelectorAll('[data-cookie-reject]').forEach(button => button.addEventListener('click', () => save(false)));
  document.querySelectorAll('[data-cookie-save]').forEach(button => button.addEventListener('click', () => {
    const form = button.closest('[data-cookie-form]');
    save({functional: form.querySelector('[data-cookie-functional]').checked, analytics: form.querySelector('[data-cookie-analytics]').checked});
  }));
  dialog?.addEventListener('close', () => { hydrate(); syncModal(); opener?.focus(); });
  dialog?.addEventListener('click', event => {
    const rect = dialog.getBoundingClientRect();
    if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right
        || event.clientY < rect.top || event.clientY > rect.bottom)) close();
  });
  window.ZubrConsent = Object.freeze({get: current, save, open});
  function refresh() {
    const saved = read();
    if (JSON.stringify(saved) === JSON.stringify(memory)) return;
    memory = saved;
    hydrate();
    if (banner) banner.hidden = !!memory;
    window.dispatchEvent(new CustomEvent('zubr:consentchange', {detail: current()}));
  }
  window.addEventListener('focus', refresh);
  window.addEventListener('pageshow', refresh);
  hydrate();
  if (banner) banner.hidden = !!memory;
  window.dispatchEvent(new CustomEvent('zubr:consentready', {detail: current()}));
})();
