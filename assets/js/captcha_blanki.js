(() => {
  const onReady = (fn) => {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
      fn();
    }
  };

  const closestForm = (el) => {
    let current = el;
    while (current && current !== document) {
      if (current.tagName && current.tagName.toLowerCase() === 'form') {
        return current;
      }
      current = current.parentNode;
    }
    return null;
  };

  const supportsAjax = () => {
    try {
      return (
        typeof window.fetch === 'function'
        && typeof window.FormData === 'function'
        && typeof Promise === 'function'
      );
    } catch (_) {
      return false;
    }
  };

  const setSubmitEnabled = (form, enabled) => {
    if (!form) return;
    try {
      form.querySelectorAll('button[type=submit],input[type=submit]').forEach((btn) => {
        const isCheckBtn = btn.classList?.contains('cb-check-btn')
          || (btn.name === 'cb_action' && btn.value === 'check');
        if (isCheckBtn) return;
        btn.disabled = !enabled;
      });
    } catch (_) {
      /* ignore */
    }
  };

  const attemptLabel = (count) => (count === 1 ? 'Versuch' : 'Versuche');

  const updateAttempts = (root, value) => {
    const counter = root.querySelector('.cb-attempts');
    if (!counter) return;
    const count = Number.isFinite(value) && value >= 0 ? value : 3;
    counter.textContent = `${count} ${attemptLabel(count)} übrig`;
  };

  const showStatus = (root, msg, ok) => {
    const box = root.querySelector('.cb-status');
    if (!box) return;
    box.style.color = ok ? '#2ecc71' : '#c0392b';
    box.textContent = msg || '';
  };

  const lockTiles = (root) => {
    root.querySelectorAll('.cb-item').forEach((item) => {
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
    const shouldShow = !!ok || (Number.isFinite(remaining) && remaining <= 2);
    const existing = document.querySelector('.cb-status-global');
    if (!shouldShow) {
      if (existing) existing.remove();
      return;
    }
    const box = existing || ensureGlobalBox();
    const color = ok ? '#27ae60' : '#c0392b';
    box.style.border = `1px solid ${color}`;
    box.style.background = ok ? '#ecf9f0' : '#ffeaea';
    box.style.color = color;

    box.textContent = '';
    if (message) {
      const msg = document.createElement('div');
      msg.textContent = message;
      box.appendChild(msg);
    }
    const hasCountInMessage = typeof message === 'string' && /Verbleibende\s+Versuche/i.test(message);
    if (!hasCountInMessage && Number.isFinite(remaining)) {
      const info = document.createElement('div');
      info.textContent = `Verbleibende ${attemptLabel(remaining)}: ${remaining}`;
      box.appendChild(info);
    }
  };

  const collectSelections = (root, fd, tokenInput, formKeyInput, renderedAtInput, honeypotInput) => {
    fd.append('cb_token', (tokenInput && tokenInput.value) || '');
    fd.append('cb_formkey', (formKeyInput && formKeyInput.value) || '');
    if (renderedAtInput) fd.append('cb_rendered_at', renderedAtInput.value);
    if (honeypotInput) fd.append('website', honeypotInput.value || '');
    root.querySelectorAll('input[name="cbsel[]"]').forEach((c) => {
      if (c.checked) fd.append('cbsel[]', c.value);
    });
  };

  onReady(() => {
    document.querySelectorAll('.captcha-blanki').forEach((root) => {
      const form = closestForm(root);
      const btn = root.querySelector('.cb-check-btn');
      const passInput = root.querySelector('input[name=cb_pass]');
      const tokenInput = root.querySelector('input[name=cb_token]');
      const formKeyInput = root.querySelector('input[name=cb_formkey]');
      const renderedAtInput = root.querySelector('input[name=cb_rendered_at]');
      const honeypotInput = root.querySelector('input[name=website]');
      const initialRemaining = Number(root.getAttribute('data-initial-attempts') || '3');

      root.dataset.remaining = String(initialRemaining);
      root.dataset.attemptsUsed = String(Math.max(0, 3 - initialRemaining));
      updateAttempts(root, initialRemaining);

      if (passInput && passInput.value === '1') {
        setSubmitEnabled(form, true);
        if (btn) btn.disabled = true;
        showStatus(root, 'Captcha bestätigt. Du kannst jetzt absenden.', true);
        lockTiles(root);
      } else {
        setSubmitEnabled(form, false);
        if (btn) btn.disabled = false;
      }

      if (!btn || !supportsAjax()) return;

      btn.addEventListener('click', (ev) => {
        ev.preventDefault();

        const fd = new FormData();
        collectSelections(root, fd, tokenInput, formKeyInput, renderedAtInput, honeypotInput);

        btn.disabled = true;
        const origin = (window.location && (window.location.origin || '')) || '';
        const url = `${origin}/website_functionalities/captcha_blanki_check.php`;
        const previousRemaining = Number(root.dataset.remaining || '3');

        const finish = (ok) => {
          if (!ok && !(passInput && passInput.value === '1')) {
            btn.disabled = false;
          }
        };

        fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
          .then((r) => r.json())
          .then((res) => {
            const ok = !!(res && res.ok);
            let remaining = Number(res && res.remaining);
            const attempts = Number(res && res.attempts);
            if (!Number.isFinite(remaining) || remaining < 0 || remaining > 3) {
              if (Number.isFinite(attempts) && attempts >= 0 && attempts <= 3) {
                remaining = Math.max(0, 3 - attempts);
              } else {
                remaining = previousRemaining;
              }
            }
            const stale = !ok && remaining === previousRemaining && previousRemaining > 0;
            if (stale) {
              remaining = Math.max(0, previousRemaining - 1);
            }
            root.dataset.remaining = String(remaining);
            root.dataset.attemptsUsed = String(Math.max(0, 3 - remaining));
            updateAttempts(root, remaining);

            let message;
            if (ok) {
              message = 'Captcha bestätigt. Du kannst jetzt absenden.';
              showStatus(root, message, true);
              updateGlobalStatus(message, remaining, true);
              if (passInput) passInput.value = '1';
              setSubmitEnabled(form, true);
              lockTiles(root);
              finish(true);
            } else {
              if (remaining > 0) {
                message = `Captcha falsch. Verbleibende ${attemptLabel(remaining)}: ${remaining}`;
              } else if (res && res.reload) {
                message = 'Captcha fehlgeschlagen. Die Seite wird neu geladen.';
              } else {
                message = (res && res.message) ? res.message : 'Captcha falsch. Bitte erneut versuchen.';
              }
              showStatus(root, message, false);
              updateGlobalStatus(message, remaining, false);
              if (res && res.reload) {
                window.location.reload();
                return;
              }
              finish(false);
            }
          })
          .catch(() => {
            const fallback = Math.max(0, previousRemaining - 1);
            root.dataset.remaining = String(fallback);
            root.dataset.attemptsUsed = String(Math.max(0, 3 - fallback));
            updateAttempts(root, fallback);
            const msg = 'Captcha-Prüfung fehlgeschlagen. Bitte später erneut probieren.';
            showStatus(root, msg, false);
            updateGlobalStatus(msg, fallback, false);
            finish(false);
          });

        return false;
      });
    });
  });
})();
