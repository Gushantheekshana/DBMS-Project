(() => {
  'use strict';

  const body = document.body;
  const toggle = document.querySelector('[data-nav-toggle]');
  const closeButton = document.querySelector('[data-nav-close]');
  const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const milliseconds = (value) => {
    const duration = Number.parseFloat(value);
    return value.trim().endsWith('ms') ? duration : duration * 1000;
  };

  const motionDuration = (element) => {
    const styles = window.getComputedStyle(element);
    const transitionDurations = styles.transitionDuration.split(',').map(milliseconds);
    const transitionDelays = styles.transitionDelay.split(',').map(milliseconds);
    const animationDurations = styles.animationDuration.split(',').map(milliseconds);
    const animationDelays = styles.animationDelay.split(',').map(milliseconds);
    const longest = (durations, delays) => durations.reduce((maximum, duration, index) => (
      Math.max(maximum, duration + (delays[index] ?? delays[0] ?? 0))
    ), 0);

    return Math.max(longest(transitionDurations, transitionDelays), longest(animationDurations, animationDelays));
  };

  const afterMotion = (element, callback) => {
    if (reducedMotion()) {
      callback();
      return;
    }

    const duration = motionDuration(element);
    if (duration <= 0) {
      callback();
      return;
    }

    let complete = false;
    const finish = (event) => {
      if (event && event.target !== element) return;
      if (complete) return;
      complete = true;
      element.removeEventListener('transitionend', finish);
      element.removeEventListener('animationend', finish);
      callback();
    };

    element.addEventListener('transitionend', finish);
    element.addEventListener('animationend', finish);
    window.setTimeout(() => finish(), duration + 50);
  };

  const setNavigation = (open) => {
    body.classList.toggle('nav-open', open);
    toggle?.setAttribute('aria-expanded', String(open));
    if (open) {
      document.querySelector('.sidebar a')?.focus();
    } else if (document.activeElement?.closest('.sidebar')) {
      toggle?.focus();
    }
  };

  toggle?.addEventListener('click', () => setNavigation(!body.classList.contains('nav-open')));
  closeButton?.addEventListener('click', () => setNavigation(false));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && body.classList.contains('nav-open')) setNavigation(false);
  });

  document.querySelectorAll('[data-dismiss]').forEach((button) => {
    button.addEventListener('click', () => {
      const alert = button.closest('[data-alert], .alert');
      if (!alert) return;
      alert.classList.add('is-leaving');
      afterMotion(alert, () => alert.remove());
    });
  });

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

  document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.dataset.passwordToggle);
      if (!(input instanceof HTMLInputElement)) return;
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      button.textContent = show ? 'Hide' : 'Show';
      button.setAttribute('aria-label', `${show ? 'Hide' : 'Show'} password`);
    });
  });

  document.querySelectorAll('input[type="file"]').forEach((input) => {
    input.addEventListener('change', () => {
      const hint = input.closest('.file-field')?.querySelector('[data-file-name]');
      if (hint) hint.textContent = input.files?.[0]?.name || 'No file selected';
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
      if (reducedMotion()) {
        dialog.close();
        return;
      }

      closing = true;
      dialog.classList.add('is-closing');
      afterMotion(dialog, () => {
        if (dialog.open) dialog.close();
      });
    };

    document.querySelectorAll('[data-confirm-open]').forEach((button) => {
      if (button.dataset.confirmOpen !== dialog.id) return;

      button.addEventListener('click', () => {
        opener = button;
        if (recordInput instanceof HTMLInputElement) recordInput.value = button.dataset.recordId || '';
        if (decisionInput instanceof HTMLInputElement) decisionInput.value = button.dataset.decision || '';
        if (title) title.textContent = button.dataset.confirmTitle || 'Confirm action';
        if (description) description.textContent = button.dataset.confirmDescription || 'Please confirm that you want to continue.';

        const showDetail = button.dataset.detailVisible === 'true';
        if (detailGroup instanceof HTMLElement) detailGroup.hidden = !showDetail;
        if (detailInput instanceof HTMLTextAreaElement || detailInput instanceof HTMLInputElement) {
          detailInput.value = '';
          detailInput.required = showDetail && button.dataset.detailRequired === 'true';
        }
        if (detailLabel) detailLabel.textContent = button.dataset.detailLabel || 'Additional information';
        if (detailHint) detailHint.textContent = button.dataset.detailHint || '';

        if (submitButton instanceof HTMLButtonElement) {
          submitButton.textContent = button.dataset.confirmLabel || 'Confirm';
          submitButton.classList.toggle('btn-success', button.dataset.confirmTone === 'success');
          submitButton.classList.toggle('btn-danger', button.dataset.confirmTone === 'danger');
        }

        dialog.showModal();
        cancelButton?.focus();
      });
    });

    cancelButton?.addEventListener('click', closeDialog);
    dialog.addEventListener('cancel', (event) => {
      if (reducedMotion()) return;
      event.preventDefault();
      closeDialog();
    });
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) closeDialog();
    });
    dialog.addEventListener('close', () => {
      dialog.classList.remove('is-closing');
      closing = false;
      form?.reset();
      form?.removeAttribute('aria-busy');
      if (form instanceof HTMLElement) delete form.dataset.submitting;
      if (opener instanceof HTMLElement) opener.focus();
      opener = null;
    });
  });
})();
