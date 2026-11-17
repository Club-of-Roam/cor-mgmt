/* Event template selection with protection for unsaved editor changes. */
(function () {
  'use strict';
  const page = document.querySelector('.cor-response-page');
  const selector = page?.querySelector('.cor-response-selector');
  const editor = page?.querySelector('.cor-response-editor');
  if (!selector || !editor) return;

  const controls = Array.from(selector.querySelectorAll('select'));
  const initialSelection = controls.map(control => Array.from(control.options).find(option => option.defaultSelected)?.value || control.options[0]?.value);
  const fields = Array.from(editor.querySelectorAll('[name="enabled"], [name="subject"], [name="message"]'));
  const snapshot = initial => JSON.stringify(fields.map(field => field.type === 'checkbox'
    ? (initial ? field.defaultChecked : field.checked)
    : (initial ? field.defaultValue : field.value)));
  const original = snapshot(true);
  let leaving = false;
  let busy = false;
  const progress = page.querySelector('[data-response-loading]');
  if (!progress) return;
  const forms = Array.from(page.querySelectorAll('form'));
  const setBusy = active => {
    busy = active;
    progress.hidden = !active;
    selector.setAttribute('aria-busy', String(active));
    editor.setAttribute('aria-busy', String(active));
    // Inert prevents more input without omitting values from native form submission.
    forms.forEach(form => { form.inert = active; });
    page.style.cursor = active ? 'progress' : '';
  };
  window.addEventListener('pageshow', event => {
    if (event.persisted) { leaving = false; setBusy(false); }
  });
  const dirty = () => snapshot(false) !== original;
  const confirmDiscard = () => !dirty() || window.confirm(page.dataset.unsavedWarning);
  const restoreSelection = () => controls.forEach((control, index) => { control.value = initialSelection[index]; });

  const openButton = selector.querySelector('[data-response-open]');
  openButton.hidden = true;
  // WordPress button styles can override the browser's default [hidden] display.
  openButton.style.display = 'none';
  controls.forEach(control => control.addEventListener('change', () => {
    if (busy) return;
    if (!confirmDiscard()) {
      restoreSelection();
      control.focus();
      return;
    }
    leaving = true;
    selector.requestSubmit();
  }));

  page.addEventListener('submit', event => {
    if (busy) { event.preventDefault(); return; }
    if (!leaving && event.target !== editor && !confirmDiscard()) {
      event.preventDefault();
      restoreSelection();
      return;
    }
    leaving = true;
    setBusy(true);
  });
  page.addEventListener('click', event => {
    const link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.target === '_blank') return;
    if (busy || !confirmDiscard()) {
      event.preventDefault();
      return;
    }
    leaving = true;
    setBusy(true);
  });
  window.addEventListener('beforeunload', event => {
    if (!leaving && dirty()) {
      event.preventDefault();
      event.returnValue = '';
    }
  });
}());
