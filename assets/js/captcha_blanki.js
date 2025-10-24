(() => {
  function onReady(fn){ if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', fn, {once:true}); } else { fn(); } }
  function closestForm(el){ while (el && el !== document) { if (el.tagName && el.tagName.toLowerCase() === 'form') return el; el = el.parentNode; } return null; }
  function setSubmitEnabled(form, enabled){
    if (!form) return;
    try {
      form.querySelectorAll('button[type=submit], input[type=submit]').forEach(b => {
        // Never disable the dedicated captcha-check button
        const isCheckBtn = b.classList?.contains('cb-check-btn') || (b.name === 'cb_action' && b.value === 'check');
        if (isCheckBtn) return;
        b.disabled = !enabled;
      });
    } catch(e) {}
  }
  function updateAttempts(root, rem){ const el = root.querySelector('.cb-attempts'); if (el) el.textContent = ((typeof rem==='number' && rem>=0)? rem: 3) + ' Versuche bis die Website neu geladen werden muss'; }
  function showStatus(root, msg, ok){ const el = root.querySelector('.cb-status'); if (el){ el.style.color = ok? '#2ecc71':'#c0392b'; el.textContent = msg || ''; } }
  async function sendCheck(fd){ const url = (window.location ? (window.location.origin || '') : '') + '/website_functionalities/captcha_blanki_check.php'; if (window.fetch){ const r = await fetch(url, {method:'POST', body: fd, credentials:'same-origin'}); return await r.json(); } else { return new Promise(resolve => { const xhr = new XMLHttpRequest(); xhr.open('POST', url, true); xhr.onreadystatechange = () => { if (xhr.readyState===4){ try { resolve(JSON.parse(xhr.responseText)); } catch(e){ resolve({ok:false,remaining:0}); } } }; xhr.send(fd); }); } }
  onReady(() => {
    document.querySelectorAll('.captcha-blanki').forEach(root => {
      const form = closestForm(root);
      setSubmitEnabled(form, false);
      // Ensure the check button is enabled on load
      try { root.querySelectorAll('.cb-check-btn').forEach(b => b.disabled = false); } catch(e) {}
      root.addEventListener('click', async ev => {
        const t = ev.target;
        if (!t) return;
        if (t.classList && t.classList.contains('cb-check-btn')){
          // Allow server to handle the check; bypass HTML5 validation
          const f = form || closestForm(t);
          if (f){ try { f.setAttribute('novalidate','novalidate'); } catch(e){} }
          // Do not prevent default; let the form submit
        }
      });
    });
  });
})();
