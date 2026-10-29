document.querySelectorAll('[data-map-consent]').forEach(panel => {
  const button = panel.querySelector('[data-load-map]');
  const frame = panel.parentElement.querySelector('iframe[data-map-src]');
  if (!button || !frame) return;
  const source = frame.dataset.mapSrc;
  function apply() {
    if (window.ZubrConsent?.get().functional === true) {
      if (frame.getAttribute('src') !== source) frame.src = source;
      frame.hidden = false;
      panel.hidden = true;
    } else {
      frame.hidden = true;
      frame.removeAttribute('src');
      panel.hidden = false;
    }
  }
  button.addEventListener('click', () => window.ZubrConsent?.open(button));
  window.addEventListener('zubr:consentchange', apply);
  window.addEventListener('zubr:consentready', apply);
  apply();
});
