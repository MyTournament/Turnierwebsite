(() => {
  const ready = (fn) => {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
      fn();
    }
  };

  const closestForm = (el) => {
    while (el && el !== document) {
      if (el.tagName && el.tagName.toLowerCase() === 'form') return el;
      el = el.parentNode;
    }
    return null;
  };

  const supportsAjax = () => {
    try {
      return typeof window.fetch === 'function' &&
             typeof window.FormData === 'function' &&
             typeof Promise === 'function';
    } catch (_) {
      return false;
    }
  };

  const setSubmitEnabled = (form, enabled) => {
    if (!form) return;
    try {
      form.querySelectorAll('button[type=submit],input[type=submit]').forEach(btn => {
        const isCheckBtn = btn.classList?.contains('cb-check-btn') || (btn.name === 'cb_action' && btn.value === 'check');
        if (isCheckBtn) return;
        btn.disabled = !enabled;
      });
    } catch (_) {}
  };

  const updateAttempts = (root, value) => {
    const el = root.querySelector('.cb-attempts');
    if (!el) return;
    const count = (typeof value === 'number' && value >= 0) ? value : 3;
    el.textContent = `${count} Versuche übrig`;
  };

  const showStatus = (root, msg, ok) => {
    const el = root.querySelector('.cb-status');
    if (!el) return;
    el.style.color = ok ? '#2ecc71' : '#c0392b';
    el.textContent = msg || '';
  };

  const lockTiles = (root) => {
    root.querySelectorAll('.cb-item').forEach(item => {
      item.style.pointerEvents = 'none';
      item.style.opacity = '0.8';
      const native = item.querySelector('.cb-native');
      if (native) {
        native.disabled = true;
        native.checked = false;
      }
    });
  };

  const ensureGlobalBox = () => {
    let box = document.querySelector('.cb-status-global');
    if (!box) {
      const anchor = document.querySelector('#anmelden') || document.body;
      box = document.createElement('div');
      box.className = 'cb-status-global';
      box.style.margin = '10px 0';
      box.style.padding = '10px';
      box.style.borderRadius = '6px';
      box.style.border = '1px solid #c0392b';
      box.style.background = '#ffeaea';
      box.style.color = '#c0392b';
      anchor.insertBefore(box, anchor.firstChild || null);
    }
    return box;
  };

  const updateGlobalStatus = (message, remaining, ok) => {
    const box = ensureGlobalBox();
    const color = ok ? '#27ae60' : '#c0392b';
    box.style.border = '1px solid ' + color;
    box.style.background = ok ? '#ecf9f0' : '#ffeaea';
    box.style.color = color;
    box.textContent = '';
    if (message) {
      const msgEl = document.createElement('div');
      msgEl.textContent = message;
      box.appendChild(msgEl);
    }
    const attemptsEl = document.createElement('div');
    attemptsEl.textContent = 'Verbleibende Versuche: ' + remaining;
    box.appendChild(attemptsEl);
  };

  const collectSelections = (root, target, tokenInput, formKeyInput, renderedAtInput, honeypotInput) => {
    target.append('cb_token', (tokenInput && tokenInput.value) || '');
    target.append('cb_formkey', (formKeyInput && formKeyInput.value) || '');
    if (renderedAtInput) { target.append('cb_rendered_at', renderedAtInput.value); }
    if (honeypotInput) { target.append('website', honeypotInput.value || ''); }
    root.querySelectorAll('input[name=cbsel[]]').forEach(c => {
      if (c.checked) { target.append('cbsel[]', c.value); }
    });
  };

  ready(() => {
    document.querySelectorAll('.captcha-blanki').forEach(root => {
      const form = closestForm(root);
      const btn = root.querySelector('.cb-check-btn');
      const passInput = root.querySelector('input[name=cb_pass]');
      const tokenInput = root.querySelector('input[name=cb_token]');
      const formKeyInput = root.querySelector('input[name=cb_formkey]');
      const renderedAtInput = root.querySelector('input[name=cb_rendered_at]');
      const honeypotInput = root.querySelector('input[name=website]');
      const initialAttempts = parseInt(root.getAttribute('data-initial-attempts') || '3', 10);
      const attemptsUsedAttr = parseInt(root.getAttribute('data-attempts-used') || '0', 10);
      const remainingInitial = Math.max(0, 3 - attemptsUsedAttr);

      updateAttempts(root, remainingInitial);

      if (passInput && passInput.value === '1') {
        setSubmitEnabled(form, true);
        if (btn) btn.disabled = true;
        showStatus(root, 'Captcha bestätigt. Du kannst jetzt absenden.', true);
        lockTiles(root);
      } else {
        setSubmitEnabled(form, false);
        if (btn) btn.disabled = false;
      }

      if (!btn) return;
      if (!supportsAjax()) return; // fall back to server submission

      btn.addEventListener('click', (ev) => {
        ev.preventDefault();
        const fd = new FormData();
        collectSelections(root, fd, tokenInput, formKeyInput, renderedAtInput, honeypotInput);

        btn.disabled = true;
        let origin = '';
        try { origin = window.location && (window.location.origin || ''); } catch (_) {}
        const url = origin + '/website_functionalities/captcha_blanki_check.php';

        const finish = (ok) => {
          if (!ok && !(passInput && passInput.value === '1')) {
            btn.disabled = false;
          }
        };

        fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(r => r.json())
          .then(res => {
            const ok = !!(res && res.ok);
            const remaining = (res && typeof res.remaining === 'number') ? res.remaining : 3;
            const used = Math.max(0, 3 - remaining);
            root.setAttribute('data-attempts-used', String(used));
            updateAttempts(root, remaining);

            if (ok) {
              const msg = 'Captcha bestätigt. Du kannst jetzt absenden.';
              showStatus(root, msg, true);
              updateGlobalStatus(msg, remaining, true);
              if (passInput) { passInput.value = '1'; }
              setSubmitEnabled(form, true);
              lockTiles(root);
              finish(true);
            } else {
              const msg = (res && res.message) ? res.message : 'Captcha falsch. Bitte erneut versuchen.';
              showStatus(root, msg, false);
              updateGlobalStatus(msg, remaining, false);
              if (res && res.reload) {
                window.location.reload();
                return;
              }
              finish(false);
            }
          })
          .catch(() => {
            const used = parseInt(root.getAttribute('data-attempts-used') || '0', 10);
            const remaining = Math.max(0, 3 - used);
            showStatus(root, 'Captcha-Prüfung fehlgeschlagen. Bitte später erneut probieren.', false);
            updateGlobalStatus('Captcha-Prüfung fehlgeschlagen. Bitte später erneut probieren.', remaining, false);
            finish(false);
          });
      });
    });
  });
})();
