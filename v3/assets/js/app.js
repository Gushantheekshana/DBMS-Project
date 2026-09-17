(() => {
  const root = document.documentElement;
  const themeKey = 'gympro-v3-theme';
  const colorMedia = window.matchMedia('(prefers-color-scheme: dark)');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const desktopMedia = window.matchMedia('(min-width: 1024px)');
  const themes = ['light', 'dark', 'system'];

  const storedTheme = () => {
    try {
      return localStorage.getItem(themeKey);
    } catch {
      return null;
    }
  };

  const preference = () => {
    const stored = storedTheme();
    return themes.includes(stored) ? stored : 'system';
  };

  const persistTheme = (value) => {
    try {
      localStorage.setItem(themeKey, value);
    } catch {
      // The preference still applies for this page when storage is unavailable.
    }
  };

  const applyTheme = (value, persist = true) => {
    const selected = themes.includes(value) ? value : 'system';
    const resolved = selected === 'system' ? (colorMedia.matches ? 'dark' : 'light') : selected;
    if (persist) persistTheme(selected);
    root.dataset.theme = resolved;
    root.dataset.themePreference = selected;
    root.classList.toggle('dark', resolved === 'dark');
    root.style.colorScheme = resolved;
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.content = resolved === 'dark' ? '#020617' : '#f8fafc';
    document.querySelectorAll('[data-theme-value]').forEach((button) => {
      button.setAttribute('aria-checked', String(button.dataset.themeValue === selected));
    });
    document.querySelectorAll('[data-theme-label]').forEach((label) => {
      label.textContent = selected.charAt(0).toUpperCase() + selected.slice(1);
    });
  };

  const afterMotion = (element, callback, timeout = 240) => {
    if (reducedMotion.matches) {
      callback();
      return;
    }
    let completed = false;
    const finish = () => {
      if (completed) return;
      completed = true;
      element.removeEventListener('animationend', finish);
      callback();
    };
    element.addEventListener('animationend', finish, { once: true });
    window.setTimeout(finish, timeout);
  };

  applyTheme(preference(), false);
  colorMedia.addEventListener('change', () => {
    if (preference() === 'system') applyTheme('system', false);
  });

  document.querySelectorAll('[data-theme-value]').forEach((button) => {
    button.addEventListener('click', () => applyTheme(button.dataset.themeValue || 'system'));
  });

  document.querySelectorAll('[data-theme-cycle]').forEach((button) => {
    button.addEventListener('click', () => {
      const current = preference();
      applyTheme(themes[(themes.indexOf(current) + 1) % themes.length]);
    });
  });

  const navToggle = document.querySelector('[data-nav-toggle]');
  const navClose = document.querySelector('[data-nav-close]');
  const sidebar = document.getElementById('primary-navigation');
  const setNavigation = (open, restoreFocus = true) => {
    document.body.classList.toggle('nav-open', open);
    navToggle?.setAttribute('aria-expanded', String(open));
    if (open) sidebar?.querySelector('a')?.focus();
    else if (restoreFocus) navToggle?.focus();
  };
  navToggle?.addEventListener('click', () => setNavigation(!document.body.classList.contains('nav-open')));
  navClose?.addEventListener('click', () => setNavigation(false));
  desktopMedia.addEventListener('change', (event) => {
    if (event.matches && document.body.classList.contains('nav-open')) setNavigation(false, false);
  });
  window.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && document.body.classList.contains('nav-open')) setNavigation(false);
  });

  document.querySelectorAll('[data-dismiss]').forEach((button) => {
    button.addEventListener('click', () => {
      const alert = button.closest('[data-alert]');
      if (!alert || alert.classList.contains('is-leaving')) return;
      alert.classList.add('is-leaving');
      afterMotion(alert, () => alert.remove(), 220);
    });
  });

  document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.dataset.passwordToggle || '');
      if (!(input instanceof HTMLInputElement)) return;
      const visible = input.type === 'text';
      input.type = visible ? 'password' : 'text';
      button.textContent = visible ? 'Show' : 'Hide';
      button.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
    });
  });

  const resetSubmitState = () => {
    document.querySelectorAll('form[data-submitting="true"]').forEach((form) => {
      delete form.dataset.submitting;
      form.removeAttribute('aria-busy');
    });
    document.querySelectorAll('.btn.is-loading').forEach((button) => {
      button.classList.remove('is-loading');
      button.removeAttribute('aria-disabled');
    });
  };

  window.addEventListener('pageshow', resetSubmitState);
  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (form.dataset.submitting === 'true') {
        event.preventDefault();
        return;
      }
      const submitter = event.submitter;
      if (!(submitter instanceof HTMLElement) || !form.checkValidity()) return;
      form.dataset.submitting = 'true';
      form.setAttribute('aria-busy', 'true');
      submitter.classList.add('is-loading');
      submitter.setAttribute('aria-disabled', 'true');
    });
  });

  document.querySelectorAll('[data-confirm-dialog]').forEach((dialog) => {
    if (!(dialog instanceof HTMLDialogElement) || !dialog.id) return;
    const form = dialog.querySelector('[data-confirm-form]');
    const title = dialog.querySelector('[data-confirm-title]');
    const description = dialog.querySelector('[data-confirm-description]');
    const recordInput = dialog.querySelector('[data-confirm-record]');
    const decisionInput = dialog.querySelector('[data-confirm-decision]');
    const detailGroup = dialog.querySelector('[data-confirm-detail-group]');
    const detailLabel = dialog.querySelector('[data-confirm-detail-label]');
    const detailHint = dialog.querySelector('[data-confirm-detail-hint]');
    const detailInput = dialog.querySelector('[data-confirm-detail]');
    const submitButton = dialog.querySelector('[data-confirm-submit]');
    const cancelButton = dialog.querySelector('[data-confirm-cancel]');
    let opener = null;
    let closing = false;

    const closeDialog = () => {
      if (!dialog.open || closing) return;
      closing = true;
      dialog.classList.add('is-closing');
      afterMotion(dialog, () => {
        dialog.classList.remove('is-closing');
        closing = false;
        dialog.close();
      }, 200);
    };

    document.querySelectorAll(`[data-confirm-open="${CSS.escape(dialog.id)}"]`).forEach((button) => {
      button.addEventListener('click', () => {
        opener = button;
        form?.reset();
        if (title) title.textContent = button.dataset.confirmTitle || 'Confirm action';
        if (description) description.textContent = button.dataset.confirmDescription || '';
        if (recordInput) recordInput.value = button.dataset.recordId || '';
        if (decisionInput) decisionInput.value = button.dataset.decision || '';
        const showDetail = button.dataset.detailVisible === 'true';
        if (detailGroup) detailGroup.hidden = !showDetail;
        if (detailInput) detailInput.required = showDetail && button.dataset.detailRequired === 'true';
        if (detailLabel && button.dataset.detailLabel) detailLabel.textContent = button.dataset.detailLabel;
        if (detailHint && button.dataset.detailHint) detailHint.textContent = button.dataset.detailHint;
        if (submitButton) {
          submitButton.textContent = button.dataset.confirmLabel || 'Confirm';
          submitButton.className = `btn btn-${button.dataset.confirmTone === 'danger' ? 'danger' : button.dataset.confirmTone === 'success' ? 'success' : 'primary'}`;
        }
        dialog.classList.remove('is-closing');
        dialog.showModal();
        cancelButton?.focus();
      });
    });

    cancelButton?.addEventListener('click', closeDialog);
    dialog.addEventListener('cancel', (event) => {
      event.preventDefault();
      closeDialog();
    });
    dialog.addEventListener('click', (event) => {
      const rect = dialog.getBoundingClientRect();
      if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) closeDialog();
    });
    dialog.addEventListener('close', () => {
      form?.removeAttribute('aria-busy');
      if (form) delete form.dataset.submitting;
      submitButton?.classList.remove('is-loading');
      submitButton?.removeAttribute('aria-disabled');
      opener?.focus();
    });
  });
})();
