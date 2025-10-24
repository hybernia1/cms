const bootstrap = window.bootstrap;

function fallbackLinkDialog(opts) {
  const defaultValue = opts && opts.defaultValue ? String(opts.defaultValue) : '';
  const url = window.prompt('Zadej adresu odkazu:', defaultValue);
  if (url && opts && typeof opts.onSubmit === 'function') {
    opts.onSubmit(url, !!opts.openInNewTab);
  }
}

function setupLinkDialog(modalEl) {
  const modal = new bootstrap.Modal(modalEl);
  const urlInput = modalEl.querySelector('[data-link-url]');
  const targetCheckbox = modalEl.querySelector('[data-link-target]');
  const confirmBtn = modalEl.querySelector('[data-link-confirm]');
  const errorEl = modalEl.querySelector('[data-link-error]');
  let currentOpts = null;

  function showError(message) {
    if (!errorEl) {
      return;
    }
    errorEl.textContent = message || '';
    errorEl.classList.remove('d-none');
  }

  function clearError() {
    if (!errorEl) {
      return;
    }
    errorEl.textContent = '';
    errorEl.classList.add('d-none');
  }

  if (confirmBtn) {
    confirmBtn.addEventListener('click', (evt) => {
      evt.preventDefault();
      if (!urlInput) {
        modal.hide();
        return;
      }
      const url = urlInput.value.trim();
      if (!url) {
        showError('Zadej URL adresu.');
        urlInput.focus();
        return;
      }
      clearError();
      const openInNewTab = targetCheckbox ? !!targetCheckbox.checked : false;
      modal.hide();
      if (currentOpts && typeof currentOpts.onSubmit === 'function') {
        currentOpts.onSubmit(url, openInNewTab);
      }
    });
  }

  modalEl.addEventListener('hidden.bs.modal', () => {
    currentOpts = null;
    if (urlInput) {
      urlInput.value = '';
    }
    if (targetCheckbox) {
      targetCheckbox.checked = false;
    }
    clearError();
  });

  modalEl.addEventListener('shown.bs.modal', () => {
    if (urlInput) {
      urlInput.focus();
      urlInput.select();
    }
  });

  return {
    open(opts) {
      currentOpts = opts || {};
      if (urlInput) {
        urlInput.value = currentOpts.defaultValue || '';
      }
      if (targetCheckbox) {
        targetCheckbox.checked = !!currentOpts.openInNewTab;
      }
      clearError();
      modal.show();
    }
  };
}

function openLinkDialog(opts) {
  if (!bootstrap || !bootstrap.Modal) {
    fallbackLinkDialog(opts);
    return;
  }
  const modalEl = document.getElementById('contentEditorLinkModal');
  if (!modalEl) {
    fallbackLinkDialog(opts);
    return;
  }
  if (!modalEl.__contentEditorLinkDialog) {
    modalEl.__contentEditorLinkDialog = setupLinkDialog(modalEl);
  }
  modalEl.__contentEditorLinkDialog.open(opts);
}

export { openLinkDialog };
