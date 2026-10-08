<script nonce="{{ $cspNonce ?? '' }}">
(function () {
  // Theme: remembered per browser; follows the OS until chosen.
  var root = document.documentElement;
  var stored = null;
  try { stored = localStorage.getItem('nis-admin-theme'); } catch (e) {}
  var dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
  if (dark) root.setAttribute('data-theme', 'dark');
  document.querySelectorAll('[data-theme-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var on = root.getAttribute('data-theme') !== 'dark';
      if (on) root.setAttribute('data-theme', 'dark'); else root.removeAttribute('data-theme');
      try { localStorage.setItem('nis-admin-theme', on ? 'dark' : 'light'); } catch (e) {}
    });
  });

  // Mobile sidebar.
  var sidebar = document.getElementById('sidebar');
  var backdrop = document.querySelector('.backdrop');
  function setSidebar(open) {
    if (!sidebar) return;
    sidebar.classList.toggle('open', open);
    backdrop && backdrop.classList.toggle('open', open);
  }
  document.querySelectorAll('[data-open-sidebar]').forEach(function (b) { b.addEventListener('click', function () { setSidebar(true); }); });
  document.querySelectorAll('[data-close-sidebar]').forEach(function (b) { b.addEventListener('click', function () { setSidebar(false); }); });

  // Close the user menu when clicking elsewhere.
  document.addEventListener('click', function (e) {
    document.querySelectorAll('details.usermenu[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); });
  });

  // Flash messages fade after a while.
  document.querySelectorAll('[data-autodismiss]').forEach(function (el) {
    setTimeout(function () { el.style.transition = 'opacity .4s'; el.style.opacity = '0'; setTimeout(function () { el.remove(); }, 400); }, 6000);
  });

  // Confirmation for destructive actions:
  // <form data-confirm="Suspend this officer?" data-confirm-body="..." data-confirm-reason> ...
  var dialog = document.getElementById('confirm-dialog');
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (form.dataset.confirmed === '1' || !dialog || !dialog.showModal) return;
      e.preventDefault();
      dialog.querySelector('[data-confirm-title]').textContent = form.dataset.confirm;
      dialog.querySelector('[data-confirm-body]').textContent = form.dataset.confirmBody || '';
      var wantsReason = form.hasAttribute('data-confirm-reason');
      var wrap = dialog.querySelector('[data-confirm-reason-wrap]');
      var reason = dialog.querySelector('[data-confirm-reason]');
      wrap.hidden = !wantsReason; reason.value = ''; reason.required = wantsReason;
      dialog.querySelector('[data-confirm-ok]').textContent = form.dataset.confirmOk || 'Confirm';
      dialog.showModal();
      dialog.addEventListener('close', function handler() {
        dialog.removeEventListener('close', handler);
        if (dialog.returnValue !== 'ok') return;
        if (wantsReason) {
          var input = form.querySelector('input[name="reason"]') || document.createElement('input');
          input.type = 'hidden'; input.name = 'reason'; input.value = reason.value;
          form.appendChild(input);
        }
        form.dataset.confirmed = '1';
        form.requestSubmit ? form.requestSubmit() : form.submit();
      });
    });
  });

  // Select-all checkboxes: <input type="checkbox" data-check-all="perm-group-x">
  document.querySelectorAll('[data-check-all]').forEach(function (master) {
    master.addEventListener('change', function () {
      document.querySelectorAll('[data-check-group="' + master.dataset.checkAll + '"]').forEach(function (c) { if (!c.disabled) c.checked = master.checked; });
    });
  });

  // Auto-submit filter selects: <select data-autosubmit>
  document.querySelectorAll('[data-autosubmit]').forEach(function (s) {
    s.addEventListener('change', function () { s.form && s.form.submit(); });
  });
})();
</script>
