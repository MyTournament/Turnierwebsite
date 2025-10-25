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
    } catch (e) {
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
    } catch (e) {}
  };

  const updateAttempts = (root, value) => {
    const el = root.querySelector('.cb-attempts');
    if (!el) return;
    const count = (typeof value === 'number' && value >= 0) ? value : 3;
    el.textContent = `${count} Versuche \u00fcbrig`;
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

      updateAttempts(root, initialAttempts);

      if (passInput && passInput.value === '1') {
        setSubmitEnabled(form, true);
        if (btn) btn.disabled = true;
        showStatus(root, 'Captcha best\u00e4tigt. Du kannst jetzt absenden.', true);
        lockTiles(root);
      } else {
        setSubmitEnabled(form, false);
        if (btn) btn.disabled = false;
      }

      if (!btn) return;
      if (!supportsAjax()) return; // fall back to server; leave default submission

      btn.addEventListener('click', (ev) => {
        ev.preventDefault();
        const fd = new FormData();
        fd.append('cb_token', (tokenInput && tokenInput.value) || '');
        fd.append('cb_formkey', (formKeyInput && formKeyInput.value) || '');
        if (renderedAtInput) fd.append('cb_rendered_at', renderedAtInput.value);
        if (honeypotInput) fd.append('website', honeypotInput.value || '');
        root.querySelectorAll('input[name=cbsel[]]').forEach(c => {
          if (c.checked) fd.append('cbsel[]', c.value);
        });

        btn.disabled = true;
        const url = `${(window.location && (window.location.origin || '')) || ''}/website_functionalities/captcha_blanki_check.php`;

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
            updateAttempts(root, remaining);
            if (ok) {
              showStatus(root, 'Captcha best\u00e4tigt. Du kannst jetzt absenden.', true);
              if (passInput) passInput.value = '1';
              setSubmitEnabled(form, true);
              lockTiles(root);
              finish(true);
            } else {
              const message = (res && res.message) ? res.message : 'Captcha falsch. Bitte erneut versuchen.';
              showStatus(root, message, false);
              if (res && res.reload) {
                window.location.reload();
                return;
              }
              finish(false);
            }
          })
          .catch(() => {
            showStatus(root, 'Captcha-Pr\u00fcfung fehlgeschlagen. Bitte sp\u00e4ter erneut probieren.', false);
            finish(false);
          });
      });
    });
  });
})();
