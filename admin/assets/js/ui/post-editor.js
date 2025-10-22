function parseJsonAttribute(element, attribute, fallback) {
  if (!element) {
    return fallback;
  }
  const value = element.getAttribute(attribute);
  if (!value || value.trim() === '') {
    return fallback;
  }
  try {
    return JSON.parse(value);
  } catch (err) {
    return fallback;
  }
}

function initStatusControls(form) {
  const statusInput = form.querySelector('[data-post-status-input]');
  const statusLabel = form.querySelector('[data-post-status-label]');
  const statusMap = statusLabel
    ? parseJsonAttribute(statusLabel, 'data-status-labels', {})
    : {};

  const buttons = [].slice.call(form.querySelectorAll('[data-status-value]'));
  buttons.forEach((button) => {
    if (!button) {
      return;
    }
    if (button.dataset.postStatusBound === '1') {
      return;
    }
    button.dataset.postStatusBound = '1';
    button.addEventListener('click', () => {
      const value = button.getAttribute('data-status-value') || '';
      if (statusInput) {
        statusInput.value = value;
      }
      if (statusLabel) {
        const label = statusMap && Object.prototype.hasOwnProperty.call(statusMap, value)
          ? statusMap[value]
          : value;
        if (label) {
          statusLabel.textContent = label;
        }
      }
    });
  });
}

function initThumbnailPicker(form) {
  const scope = form.closest('[data-post-editor-root]') || document;
  const previewWrapper = scope.querySelector('[data-thumbnail-preview]');
  const previewInner = scope.querySelector('[data-thumbnail-preview-inner]');
  const uploadInfo = scope.querySelector('[data-thumbnail-upload-info]');
  const fileInput = form.querySelector('[data-thumbnail-file-input]');
  const selectedInput = form.querySelector('[data-thumbnail-selected-input]');
  const removeFlagInput = form.querySelector('[data-thumbnail-remove-input]');
  const removeBtn = scope.querySelector('[data-thumbnail-remove]');
  const modalEl = scope.querySelector('[data-thumbnail-modal]');
  const dropzone = modalEl ? modalEl.querySelector('[data-thumbnail-dropzone]') : null;
  const modalFileInput = modalEl ? modalEl.querySelector('[data-thumbnail-modal-file-input]') : null;
  const libraryTab = modalEl ? modalEl.querySelector('[data-thumbnail-library-tab]') : null;
  const libraryGrid = modalEl ? modalEl.querySelector('[data-thumbnail-library-grid]') : null;
  const libraryLoading = modalEl ? modalEl.querySelector('[data-thumbnail-library-loading]') : null;
  const libraryError = modalEl ? modalEl.querySelector('[data-thumbnail-library-error]') : null;
  const libraryEmpty = modalEl ? modalEl.querySelector('[data-thumbnail-library-empty]') : null;
  const uploadPreview = modalEl ? modalEl.querySelector('[data-thumbnail-upload-preview]') : null;
  const applyBtn = modalEl ? modalEl.querySelector('[data-thumbnail-apply]') : null;
  const applyBtnLabel = applyBtn ? applyBtn.querySelector('[data-label]') : null;
  const defaultApplyLabel = applyBtn
    ? (applyBtn.getAttribute('data-default-label') || (applyBtnLabel ? applyBtnLabel.textContent : 'Použít'))
    : 'Použít';
  const libraryUrl = modalEl ? (modalEl.getAttribute('data-thumbnail-library-url') || '') : '';

  let pendingFile = null;
  let pendingLibraryItem = null;
  let libraryLoaded = false;

  function updateApplyButton(label, enabled) {
    if (!applyBtn) {
      return;
    }
    const text = label || defaultApplyLabel;
    if (applyBtnLabel) {
      applyBtnLabel.textContent = text;
    } else {
      applyBtn.textContent = text;
    }
    applyBtn.disabled = !enabled;
  }

  function clearLibrarySelection() {
    if (!libraryGrid) {
      return;
    }
    const activeButtons = [].slice.call(libraryGrid.querySelectorAll('button.active'));
    activeButtons.forEach((button) => {
      button.classList.remove('active');
      button.removeAttribute('aria-pressed');
    });
  }

  function resetPendingSelection() {
    pendingFile = null;
    pendingLibraryItem = null;
    updateApplyButton(defaultApplyLabel, false);
    clearLibrarySelection();
    if (uploadPreview) {
      uploadPreview.textContent = '';
      uploadPreview.classList.add('d-none');
    }
    if (modalFileInput) {
      try {
        modalFileInput.value = '';
      } catch (err) {
        // ignore
      }
    }
  }

  function setRemoveEnabled(enable) {
    if (!removeBtn) {
      return;
    }
    removeBtn.disabled = !enable;
    removeBtn.classList.toggle('disabled', !enable);
  }

  function setPreviewPlaceholder() {
    if (!previewInner) {
      return;
    }
    const emptyText = previewWrapper ? previewWrapper.getAttribute('data-empty-text') : '';
    const text = emptyText && emptyText.trim() !== '' ? emptyText : 'Žádný obrázek není vybrán.';
    previewInner.innerHTML = `<div class="text-secondary">${text}</div>`;
    if (uploadInfo) {
      uploadInfo.textContent = '';
      uploadInfo.classList.add('d-none');
    }
  }

  function setPreviewFromExisting(item) {
    if (!previewInner) {
      return;
    }
    const url = item && item.url ? item.url : '';
    const mime = item && item.mime ? item.mime : '';
    previewInner.innerHTML = '';
    if (mime.indexOf('image/') === 0 && url) {
      const img = document.createElement('img');
      img.src = url;
      img.alt = 'Vybraný obrázek';
      img.style.maxWidth = '220px';
      img.style.borderRadius = '.75rem';
      previewInner.appendChild(img);
    }
    const meta = document.createElement('div');
    meta.className = 'text-secondary small';
    meta.textContent = mime ? mime : (url || 'Obrázek');
    previewInner.appendChild(meta);
    if (uploadInfo) {
      uploadInfo.textContent = '';
      uploadInfo.classList.add('d-none');
    }
  }

  function setPreviewFromFile(file) {
    if (!previewInner) {
      return;
    }
    previewInner.innerHTML = '';
    const isImage = file && file.type && file.type.indexOf('image/') === 0;
    if (isImage && typeof FileReader !== 'undefined') {
      const reader = new FileReader();
      reader.onload = (event) => {
        previewInner.innerHTML = '';
        const img = document.createElement('img');
        img.src = event && event.target ? String(event.target.result || '') : '';
        img.alt = file.name || '';
        img.style.maxWidth = '220px';
        img.style.borderRadius = '.75rem';
        previewInner.appendChild(img);
        const meta = document.createElement('div');
        meta.className = 'text-secondary small';
        meta.textContent = file.name || '';
        previewInner.appendChild(meta);
      };
      reader.readAsDataURL(file);
    } else {
      const wrapper = document.createElement('div');
      wrapper.innerHTML = `<div class="fw-semibold">${file ? file.name || '' : ''}</div>` +
        `<div class="text-secondary small">${file && file.type ? file.type : 'Soubor'}</div>`;
      previewInner.appendChild(wrapper);
    }
    if (uploadInfo) {
      const message = file && file.name
        ? `Vybraný soubor bude nahrán po uložení: ${file.name}`
        : '';
      uploadInfo.textContent = message;
      uploadInfo.classList.toggle('d-none', !message);
    }
  }

  function clearFileInput() {
    if (fileInput) {
      try {
        fileInput.value = '';
      } catch (err) {
        // ignore
      }
    }
    if (modalFileInput) {
      try {
        modalFileInput.value = '';
      } catch (err) {
        // ignore
      }
    }
  }

  function closeModal() {
    if (!modalEl || typeof bootstrap === 'undefined' || typeof bootstrap.Modal !== 'function') {
      return;
    }
    const instance = bootstrap.Modal.getInstance(modalEl) || bootstrap.Modal.getOrCreateInstance(modalEl);
    if (instance && typeof instance.hide === 'function') {
      instance.hide();
    }
  }

  function applyFile(file) {
    if (!file) {
      return;
    }
    if (fileInput) {
      try {
        const dt = new DataTransfer();
        dt.items.add(file);
        fileInput.files = dt.files;
      } catch (err) {
        if (modalFileInput && modalFileInput.files) {
          try {
            fileInput.files = modalFileInput.files;
          } catch (fallbackErr) {
            // ignore
          }
        }
      }
    }
    if (selectedInput) {
      selectedInput.value = '';
    }
    if (removeFlagInput) {
      removeFlagInput.value = '0';
    }
    setPreviewFromFile(file);
    setRemoveEnabled(true);
    clearLibrarySelection();
    closeModal();
  }

  function selectExisting(item) {
    if (!item) {
      return;
    }
    clearFileInput();
    if (selectedInput) {
      selectedInput.value = item.id !== undefined && item.id !== null ? String(item.id) : '';
    }
    if (removeFlagInput) {
      removeFlagInput.value = '0';
    }
    setPreviewFromExisting(item);
    setRemoveEnabled(true);
    clearLibrarySelection();
    closeModal();
  }

  function prepareExistingSelection(item, button) {
    if (!item) {
      return;
    }
    pendingLibraryItem = item;
    pendingFile = null;
    clearLibrarySelection();
    if (button) {
      button.classList.add('active');
      button.setAttribute('aria-pressed', 'true');
    }
    if (uploadPreview) {
      uploadPreview.textContent = '';
      uploadPreview.classList.add('d-none');
    }
    updateApplyButton('Vybrat z knihovny', true);
  }

  function prepareFileSelection(file) {
    if (!file) {
      return;
    }
    pendingFile = file;
    pendingLibraryItem = null;
    clearLibrarySelection();
    if (modalFileInput) {
      try {
        modalFileInput.value = '';
      } catch (err) {
        // ignore
      }
    }
    if (uploadPreview) {
      const summaryParts = [];
      if (file.name) {
        summaryParts.push(file.name);
      }
      if (file.type) {
        summaryParts.push(`(${file.type})`);
      }
      uploadPreview.textContent = summaryParts.join(' ');
      uploadPreview.classList.remove('d-none');
    }
    updateApplyButton('Použít nahraný soubor', true);
  }

  function commitPendingSelection() {
    if (pendingFile) {
      applyFile(pendingFile);
      resetPendingSelection();
    } else if (pendingLibraryItem) {
      selectExisting(pendingLibraryItem);
      resetPendingSelection();
    }
  }

  function renderLibrary(items) {
    if (!libraryGrid) {
      return;
    }
    libraryGrid.innerHTML = '';
    if (!Array.isArray(items) || items.length === 0) {
      if (libraryEmpty) {
        libraryEmpty.classList.remove('d-none');
      }
      return;
    }
    if (libraryEmpty) {
      libraryEmpty.classList.add('d-none');
    }
    items.forEach((item) => {
      const normalized = item || {};
      const column = document.createElement('div');
      column.className = 'col-6 col-md-4 col-lg-3';

      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'btn btn-outline-secondary w-100 h-100 d-flex flex-column align-items-center justify-content-center gap-2 p-3';
      if (normalized.id !== undefined) {
        button.dataset.mediaId = String(normalized.id);
      }
      if (normalized.url) {
        button.dataset.mediaUrl = String(normalized.url);
      }
      if (normalized.mime) {
        button.dataset.mediaMime = String(normalized.mime);
      }

      if (normalized.mime && String(normalized.mime).indexOf('image/') === 0 && normalized.url) {
        const img = document.createElement('img');
        img.src = normalized.url;
        img.alt = normalized.name || 'Obrázek';
        img.style.maxHeight = '140px';
        img.style.maxWidth = '100%';
        img.style.objectFit = 'cover';
        button.appendChild(img);
      } else {
        const icon = document.createElement('i');
        icon.className = 'bi bi-file-earmark-text fs-2';
        button.appendChild(icon);
        const label = document.createElement('div');
        label.className = 'text-secondary small text-truncate w-100';
        label.textContent = normalized.name || normalized.url || 'Soubor';
        button.appendChild(label);
      }

      button.addEventListener('click', (event) => {
        event.preventDefault();
        prepareExistingSelection(normalized, button);
      });
      button.addEventListener('dblclick', (event) => {
        event.preventDefault();
        prepareExistingSelection(normalized, button);
        commitPendingSelection();
      });

      column.appendChild(button);
      libraryGrid.appendChild(column);
    });
  }

  function loadLibrary() {
    if (libraryLoaded) {
      return;
    }
    libraryLoaded = true;
    if (libraryLoading) {
      libraryLoading.classList.remove('d-none');
    }
    if (libraryError) {
      libraryError.classList.add('d-none');
    }

    const url = libraryUrl || 'admin.php?r=media&a=library&type=image&limit=60';
    fetch(url, {
      headers: { Accept: 'application/json' }
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error('Nepodařilo se načíst knihovnu médií.');
        }
        return response.json();
      })
      .then((data) => {
        if (libraryLoading) {
          libraryLoading.classList.add('d-none');
        }
        const items = data && Array.isArray(data.items) ? data.items : [];
        renderLibrary(items);
      })
      .catch((error) => {
        if (libraryLoading) {
          libraryLoading.classList.add('d-none');
        }
        if (libraryError) {
          libraryError.textContent = (error && error.message) ? error.message : 'Došlo k chybě při načítání médií.';
          libraryError.classList.remove('d-none');
        }
      });
  }

  if (removeBtn) {
    removeBtn.addEventListener('click', (event) => {
      event.preventDefault();
      clearFileInput();
      if (selectedInput) {
        selectedInput.value = '';
      }
      if (removeFlagInput) {
        removeFlagInput.value = '1';
      }
      setPreviewPlaceholder();
      setRemoveEnabled(false);
      resetPendingSelection();
    });
  }

  if (modalFileInput) {
    modalFileInput.addEventListener('change', () => {
      if (modalFileInput.files && modalFileInput.files[0]) {
        prepareFileSelection(modalFileInput.files[0]);
      }
    });
  }

  if (dropzone) {
    dropzone.addEventListener('dragover', (event) => {
      event.preventDefault();
      dropzone.classList.add('border-primary');
    });
    dropzone.addEventListener('dragleave', () => {
      dropzone.classList.remove('border-primary');
    });
    dropzone.addEventListener('dragend', () => {
      dropzone.classList.remove('border-primary');
    });
    dropzone.addEventListener('drop', (event) => {
      event.preventDefault();
      dropzone.classList.remove('border-primary');
      if (event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0]) {
        prepareFileSelection(event.dataTransfer.files[0]);
      }
    });
    dropzone.addEventListener('click', (event) => {
      if (!modalFileInput) {
        return;
      }
      if (event.target === modalFileInput) {
        return;
      }
      event.preventDefault();
      modalFileInput.click();
    });
  }

  if (libraryTab) {
    libraryTab.addEventListener('shown.bs.tab', loadLibrary);
  }

  if (applyBtn) {
    applyBtn.addEventListener('click', (event) => {
      event.preventDefault();
      commitPendingSelection();
    });
  }

  if (modalEl) {
    modalEl.addEventListener('show.bs.modal', () => {
      resetPendingSelection();
    });
    modalEl.addEventListener('hidden.bs.modal', () => {
      resetPendingSelection();
    });
  }

  const hasSelectedValue = selectedInput && String(selectedInput.value || '').trim() !== '';
  let hasInitial = hasSelectedValue;
  if (!hasInitial && previewWrapper) {
    const initialData = previewWrapper.getAttribute('data-initial');
    if (initialData && initialData !== 'null') {
      hasInitial = true;
    }
  }
  setRemoveEnabled(hasInitial);
  resetPendingSelection();
}

export function initPostEditor(root) {
  const scope = root || document;
  const forms = [].slice.call(scope.querySelectorAll('form[data-post-editor]'));
  forms.forEach((form) => {
    if (!form || form.dataset.postEditorBound === '1') {
      return;
    }
    form.dataset.postEditorBound = '1';
    initStatusControls(form);
    initThumbnailPicker(form);
  });
}
