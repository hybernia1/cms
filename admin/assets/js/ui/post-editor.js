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
  const toggles = [].slice.call(form.querySelectorAll('[data-status-toggle]'));

  const labelForValue = (value) => {
    if (!value) {
      return '';
    }
    if (statusMap && Object.prototype.hasOwnProperty.call(statusMap, value)) {
      return statusMap[value];
    }
    return value;
  };

  const updateToggleLabel = (toggle, value) => {
    if (!toggle) {
      return;
    }
    const wrapper = toggle.closest('[data-status-toggle-wrapper]');
    if (!wrapper) {
      return;
    }
    const labelEl = wrapper.querySelector('[data-status-toggle-label]');
    if (!labelEl) {
      return;
    }
    const draftValue = toggle.getAttribute('data-status-toggle-draft') || 'draft';
    const publishValue = toggle.getAttribute('data-status-toggle-publish') || 'publish';
    const draftLabel = labelEl.getAttribute('data-label-draft') || '';
    const publishLabel = labelEl.getAttribute('data-label-publish') || '';
    const isPublish = value === publishValue;
    const text = isPublish
      ? (publishLabel || labelForValue(publishValue))
      : (draftLabel || labelForValue(draftValue));
    labelEl.textContent = text;
  };

  const applyStatus = (value) => {
    if (!value) {
      return;
    }
    if (statusInput) {
      statusInput.value = value;
    }
    if (statusLabel) {
      const label = labelForValue(value);
      if (label) {
        statusLabel.textContent = label;
      }
    }
    toggles.forEach((toggle) => {
      if (!toggle) {
        return;
      }
      const publishValue = toggle.getAttribute('data-status-toggle-publish') || 'publish';
      toggle.checked = value === publishValue;
      updateToggleLabel(toggle, value);
    });
  };

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
      applyStatus(value);
    });
  });

  toggles.forEach((toggle) => {
    if (!toggle) {
      return;
    }
    if (toggle.dataset.statusToggleBound === '1') {
      return;
    }
    toggle.dataset.statusToggleBound = '1';
    const draftValue = toggle.getAttribute('data-status-toggle-draft') || 'draft';
    const publishValue = toggle.getAttribute('data-status-toggle-publish') || 'publish';
    toggle.addEventListener('change', () => {
      const nextValue = toggle.checked ? publishValue : draftValue;
      applyStatus(nextValue);
    });
  });

  const initialValue = statusInput ? statusInput.value : '';
  if (initialValue) {
    applyStatus(initialValue);
  } else if (toggles.length > 0) {
    const firstToggle = toggles[0];
    const publishValue = firstToggle.getAttribute('data-status-toggle-publish') || 'publish';
    const draftValue = firstToggle.getAttribute('data-status-toggle-draft') || 'draft';
    const derivedValue = firstToggle.checked ? publishValue : draftValue;
    applyStatus(derivedValue);
  }
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
  const modalEl = scope.querySelector('[data-media-picker-modal][data-media-picker-context="thumbnail"]');

  function clearFileInput() {
    if (fileInput) {
      try {
        fileInput.value = '';
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

  function applyFile(file) {
    if (!file) {
      return;
    }
    if (fileInput) {
      try {
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        fileInput.files = dataTransfer.files;
      } catch (err) {
        const picker = modalEl && modalEl._mediaPicker ? modalEl._mediaPicker : null;
        if (picker && typeof picker.getFileInputFiles === 'function') {
          const files = picker.getFileInputFiles();
          if (files) {
            try {
              fileInput.files = files;
            } catch (fallbackErr) {
              // ignore
            }
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
  }

  function selectExisting(item) {
    if (!item) {
      return;
    }
    clearFileInput();
    if (selectedInput) {
      const id = item.id !== undefined && item.id !== null ? item.id : '';
      selectedInput.value = id === '' ? '' : String(id);
    }
    if (removeFlagInput) {
      removeFlagInput.value = '0';
    }
    setPreviewFromExisting(item);
    setRemoveEnabled(true);
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
    });
  }

  if (modalEl) {
    modalEl.addEventListener('cms:media-picker:apply', (event) => {
      const detail = event && event.detail ? event.detail : {};
      const selection = detail.selection || null;
      if (!selection) {
        return;
      }
      if (selection.type === 'file' && selection.file) {
        applyFile(selection.file);
      } else if (selection.type === 'item' && selection.item) {
        selectExisting(selection.item);
      }
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
  if (!hasInitial) {
    setPreviewPlaceholder();
  }
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
