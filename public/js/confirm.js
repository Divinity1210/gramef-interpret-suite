/*
 A to Z Unified Confirmation Modal
 - Replaces native window.confirm prompts for consistent UI
 - Accessible (ARIA attributes, focus management, keyboard support)
 - Smooth 300ms ease-in-out transitions
*/
(function(){
  let backdrop, dialog, titleEl, descEl, confirmBtn, cancelBtn, lastFocusedEl, resolveFn;

  function ensureDOM(){
    if (backdrop) return;
    backdrop = document.createElement('div');
    backdrop.className = 'az-confirm__backdrop';
    backdrop.setAttribute('aria-hidden', 'true');

    dialog = document.createElement('div');
    dialog.className = 'az-confirm';
    dialog.setAttribute('role', 'dialog');
    dialog.setAttribute('aria-modal', 'true');
    const titleId = 'azConfirmTitle';
    const descId = 'azConfirmDesc';
    dialog.setAttribute('aria-labelledby', titleId);
    dialog.setAttribute('aria-describedby', descId);

    const body = document.createElement('div');
    body.className = 'az-confirm__body';

    titleEl = document.createElement('h2');
    titleEl.className = 'az-confirm__title';
    titleEl.id = titleId;
    titleEl.textContent = 'Please Confirm';

    descEl = document.createElement('p');
    descEl.className = 'az-confirm__desc';
    descEl.id = descId;

    const actions = document.createElement('div');
    actions.className = 'az-confirm__actions';

    confirmBtn = document.createElement('button');
    confirmBtn.type = 'button';
    confirmBtn.className = 'az-confirm__btn az-confirm__btn--primary';
    confirmBtn.textContent = 'Confirm';

    cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.className = 'az-confirm__btn az-confirm__btn--secondary';
    cancelBtn.textContent = 'Cancel';

    actions.appendChild(cancelBtn);
    actions.appendChild(confirmBtn);
    body.appendChild(titleEl);
    body.appendChild(descEl);
    dialog.appendChild(body);
    dialog.appendChild(actions);
    backdrop.appendChild(dialog);
    document.body.appendChild(backdrop);

    // Event handlers
    cancelBtn.addEventListener('click', () => close(false));
    confirmBtn.addEventListener('click', () => close(true));

    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop) close(false);
    });

    document.addEventListener('keydown', (e) => {
      if (!backdrop.classList.contains('is-open')) return;
      if (e.key === 'Escape') { e.preventDefault(); close(false); }
      if (e.key === 'Enter') { e.preventDefault(); close(true); }
      if (e.key === 'Tab') {
        // simple focus trap among dialog and buttons
        const focusables = [cancelBtn, confirmBtn];
        const idx = focusables.indexOf(document.activeElement);
        if (e.shiftKey) {
          if (idx <= 0) { focusables[focusables.length - 1].focus(); e.preventDefault(); }
        } else {
          if (idx === focusables.length - 1) { focusables[0].focus(); e.preventDefault(); }
        }
      }
    }, true);
  }

  function open(message){
    ensureDOM();
    descEl.textContent = message || 'Are you sure?';
    lastFocusedEl = document.activeElement;

    backdrop.classList.add('is-open');
    backdrop.setAttribute('aria-hidden', 'false');
    requestAnimationFrame(() => {
      backdrop.classList.add('is-visible');
      dialog.classList.add('is-visible');
      confirmBtn.focus();
    });
  }

  function close(confirmed){
    backdrop.classList.remove('is-visible');
    dialog.classList.remove('is-visible');
    setTimeout(() => {
      backdrop.classList.remove('is-open');
      backdrop.setAttribute('aria-hidden', 'true');
      if (lastFocusedEl && typeof lastFocusedEl.focus === 'function') lastFocusedEl.focus();
      try { if (resolveFn) resolveFn(confirmed); } finally { resolveFn = null; }
    }, 300); // match CSS transition
  }

  function azConfirm(message){
    open(message);
    return new Promise((resolve) => { resolveFn = resolve; });
  }

  // Global exposure
  window.azConfirm = azConfirm;

  // Interception utility: parse inline confirm() message from attribute
  function extractConfirmMessage(str){
    if (!str) return null;
    const match = str.match(/confirm\((['\"])(.*?)\1\)/);
    return match ? match[2] : null;
  }

  // Helper: programmatically proceed default action
  function proceedDefault(target){
    if (!target) return;
    const form = target.closest('form');
    const tag = target.tagName.toLowerCase();
    const type = target.getAttribute('type');
    if (tag === 'a' && target.href) {
      window.location.href = target.href;
      return;
    }
    if (form) {
      form.submit();
      return;
    }
    // Fallback: click again without interception
    target.removeAttribute('data-az-intercepted');
    target.click();
  }

  // Intercept click events before inline handlers run
  document.addEventListener('click', async function(e){
    const path = e.composedPath ? e.composedPath() : [e.target];
    let target = null;
    for (const el of path) {
      if (!el || !el.getAttribute) continue;
      const onclick = el.getAttribute('onclick');
      const hasJsConfirm = el.classList && el.classList.contains('js-confirm');
      const msgAttr = el.getAttribute('data-confirm-message');
      if ((onclick && onclick.includes('confirm(')) || hasJsConfirm) {
        target = el;
        const msg = hasJsConfirm ? (msgAttr || 'Are you sure?') : extractConfirmMessage(onclick) || 'Are you sure?';
        e.preventDefault();
        e.stopImmediatePropagation();
        el.setAttribute('data-az-intercepted', '1');
        const confirmed = await azConfirm(msg);
        if (confirmed) proceedDefault(el);
        return;
      }
    }
  }, true);

  // Intercept form submit with inline onsubmit="return confirm('...')"
  document.addEventListener('submit', async function(e){
    const form = e.target;
    const onsubmit = form.getAttribute && form.getAttribute('onsubmit');
    if (onsubmit && onsubmit.includes('confirm(')) {
      const msg = extractConfirmMessage(onsubmit) || 'Are you sure?';
      e.preventDefault();
      const confirmed = await azConfirm(msg);
      if (confirmed) form.submit();
    }
  }, true);
})();