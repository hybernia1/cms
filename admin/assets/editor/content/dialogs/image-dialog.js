const bootstrap = window.bootstrap;

function fallbackImageDialog(opts) {
  const url = window.prompt('Zadej adresu obrázku:');
  if (url && opts && typeof opts.onInsert === 'function') {
    opts.onInsert({ id: null, url, mime: 'image/*' }, '');
  }
}

function setupImageDialog(modalEl) {
  const modal = new bootstrap.Modal(modalEl);
  const confirmBtn =
    modalEl.querySelector('[data-media-picker-apply]') || modalEl.querySelector('[data-image-confirm]');
  const altInput = modalEl.querySelector('[data-image-alt]');
  const selectedInfo = modalEl.querySelector('[data-image-selected-info]');
  const errorEl = modalEl.querySelector('[data-image-error]');
  const picker = modalEl._mediaPicker || null;
  const defaultLabel = confirmBtn ? getConfirmLabel() : 'Vložit';
  let lastSelectionLabel = defaultLabel;
  let currentSelection = null;
  let currentOpts = null;

  function getConfirmLabel() {
    if (!confirmBtn) {
      return '';
    }
    const labelEl = confirmBtn.querySelector('[data-label]');
    if (labelEl) {
      return labelEl.textContent || '';
    }
    return confirmBtn.textContent || '';
  }

  function setConfirmLabel(text) {
    if (!confirmBtn) {
      return;
    }
    const labelEl = confirmBtn.querySelector('[data-label]');
    if (labelEl) {
      labelEl.textContent = text || '';
    } else {
      confirmBtn.textContent = text || '';
    }
  }

  function setConfirmEnabled(enabled) {
    if (confirmBtn) {
      confirmBtn.disabled = !enabled;
    }
  }

  function setSelectedInfo(text) {
    if (selectedInfo) {
      selectedInfo.textContent = text || '';
    }
  }

  function setError(message) {
    if (!errorEl) {
      return;
    }
    if (message) {
      errorEl.textContent = message;
      errorEl.classList.remove('d-none');
    } else {
      errorEl.textContent = '';
      errorEl.classList.add('d-none');
    }
  }

  function describeFile(file) {
    if (!file) {
      return 'Soubor';
    }
    const parts = [];
    if (file.name) {
      parts.push(file.name);
    }
    if (file.type) {
      parts.push(`(${file.type})`);
    }
    return parts.join(' ') || 'Soubor';
  }

  function resetState() {
    currentSelection = null;
    lastSelectionLabel = defaultLabel;
    currentOpts = null;
    setSelectedInfo('');
    setError('');
    setConfirmLabel(defaultLabel);
    setConfirmEnabled(false);
    if (altInput) {
      altInput.value = '';
    }
  }

  function handleSelectionChange(selection) {
    currentSelection = selection;
    if (!selection) {
      setSelectedInfo('');
      setError('');
      lastSelectionLabel = defaultLabel;
      setConfirmLabel(defaultLabel);
      setConfirmEnabled(false);
      return;
    }
    if (selection.type === 'file' && selection.file) {
      setSelectedInfo(`Vybrán nový soubor: ${describeFile(selection.file)}`);
      if (altInput && (!altInput.value || altInput.value.trim() === '')) {
        altInput.value = selection.file.name || '';
      }
      setError('');
      setConfirmEnabled(true);
      lastSelectionLabel = getConfirmLabel();
    } else if (selection.type === 'item' && selection.item) {
      const item = selection.item;
      const summary = item.name || item.url || 'Soubor';
      setSelectedInfo(`Vybráno z knihovny: ${summary}`);
      if (altInput && item.name && (!altInput.value || altInput.value.trim() === '')) {
        altInput.value = item.name;
      }
      setError('');
      setConfirmEnabled(true);
      lastSelectionLabel = getConfirmLabel();
    } else {
      setSelectedInfo('');
      setConfirmEnabled(false);
      lastSelectionLabel = getConfirmLabel();
    }
  }

  function uploadSelectedFile(file) {
    if (!file || !currentOpts || typeof currentOpts.onInsert !== 'function') {
      return;
    }
    if (!currentOpts.csrf) {
      setError('Chybí CSRF token.');
      return;
    }
    const previousLabel = lastSelectionLabel || defaultLabel;
    setError('');
    setConfirmLabel('Nahrávání…');
    setConfirmEnabled(false);

    const formData = new FormData();
    formData.append('csrf', currentOpts.csrf);
    formData.append('file', file);
    if (currentOpts.postId) {
      formData.append('post_id', String(currentOpts.postId));
    }

    fetch('admin.php?r=media&a=upload-editor', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF': currentOpts.csrf
      },
      body: formData
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error('Nahrávání se nezdařilo.');
        }
        return response.json();
      })
      .then((data) => {
        if (!data || data.success !== true || !data.item) {
          const message = data && data.error ? data.error : 'Nahrávání se nezdařilo.';
          throw new Error(message);
        }
        if (picker && typeof picker.addItem === 'function') {
          picker.addItem(data.item);
        }
        currentOpts.onInsert(data.item, altInput ? altInput.value : '');
        modal.hide();
      })
      .catch((error) => {
        setError(error && error.message ? error.message : 'Nahrávání se nezdařilo.');
        setConfirmLabel(previousLabel || defaultLabel);
        setConfirmEnabled(true);
      });
  }

  modalEl.addEventListener('cms:media-picker:selection-change', (event) => {
    const detail = event && event.detail ? event.detail : {};
    handleSelectionChange(detail.selection || null);
  });

  modalEl.addEventListener('cms:media-picker:apply', (event) => {
    const detail = event && event.detail ? event.detail : {};
    const selection = detail.selection || null;
    if (!selection) {
      return;
    }
    if (selection.type === 'file' && selection.file) {
      event.preventDefault();
      uploadSelectedFile(selection.file);
    } else if (selection.type === 'item' && selection.item) {
      if (currentOpts && typeof currentOpts.onInsert === 'function') {
        currentOpts.onInsert(selection.item, altInput ? altInput.value : '');
      }
    }
  });

  modalEl.addEventListener('hidden.bs.modal', () => {
    resetState();
  });

  modalEl.addEventListener('shown.bs.modal', () => {
    if (altInput) {
      altInput.focus();
      altInput.select();
    }
    if (picker && typeof picker.loadLibrary === 'function') {
      const libraryTab = modalEl.querySelector('[data-media-picker-library-tab]');
      if (libraryTab && libraryTab.classList.contains('active')) {
        picker.loadLibrary(false);
      }
    }
  });

  resetState();

  return {
    open(opts) {
      currentOpts = opts || {};
      if (picker && typeof picker.reset === 'function') {
        picker.reset();
      }
      handleSelectionChange(null);
      setConfirmLabel(defaultLabel);
      setConfirmEnabled(false);
      if (altInput) {
        altInput.value = currentOpts.defaultAlt || '';
      }
      modal.show();
    }
  };
}

function openImageDialog(opts) {
  if (!bootstrap || !bootstrap.Modal) {
    fallbackImageDialog(opts);
    return;
  }
  const modalEl = document.getElementById('contentEditorImageModal');
  if (!modalEl) {
    fallbackImageDialog(opts);
    return;
  }
  if (!modalEl.__contentEditorImageDialog) {
    modalEl.__contentEditorImageDialog = setupImageDialog(modalEl);
  }
  modalEl.__contentEditorImageDialog.open(opts);
}

export { openImageDialog };
