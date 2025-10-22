const DEFAULT_LIBRARY_URL = 'admin.php?r=media&a=library&type=image&limit=60';

function getBootstrapModal(modalEl) {
  const bootstrap = window.bootstrap;
  if (!modalEl || !bootstrap || typeof bootstrap.Modal !== 'function') {
    return null;
  }
  return bootstrap.Modal.getInstance(modalEl) || bootstrap.Modal.getOrCreateInstance(modalEl);
}

function closeModal(modalEl) {
  const instance = getBootstrapModal(modalEl);
  if (instance && typeof instance.hide === 'function') {
    instance.hide();
  }
}

function formatFileSummary(file) {
  if (!file) {
    return '';
  }
  const parts = [];
  if (file.name) {
    parts.push(file.name);
  }
  if (file.type) {
    parts.push(`(${file.type})`);
  }
  return parts.join(' ');
}

function normalizeLibraryItem(item) {
  const data = item && typeof item === 'object' ? item : {};
  const id = data.id !== undefined && data.id !== null ? data.id : null;
  const url = typeof data.url === 'string' ? data.url : '';
  const displayUrlRaw = typeof data.display_url === 'string' && data.display_url
    ? data.display_url
    : (typeof data.displayUrl === 'string' && data.displayUrl ? data.displayUrl : '');
  const displayUrl = displayUrlRaw || url;
  return {
    id: id,
    url,
    displayUrl,
    mime: data.mime || '',
    name: data.name || '',
    type: data.type || ''
  };
}

export function initMediaPickerModals(root) {
  const scope = root || document;
  const modals = [].slice.call(scope.querySelectorAll('[data-media-picker-modal]'));
  modals.forEach((modalEl) => {
    if (!modalEl || modalEl.dataset.mediaPickerBound === '1') {
      return;
    }
    modalEl.dataset.mediaPickerBound = '1';

    const dropzone = modalEl.querySelector('[data-media-picker-dropzone]');
    const fileInput = modalEl.querySelector('[data-media-picker-file-input]');
    const uploadSummary = modalEl.querySelector('[data-media-picker-upload-summary]');
    const libraryTab = modalEl.querySelector('[data-media-picker-library-tab]');
    const libraryGrid = modalEl.querySelector('[data-media-picker-library-grid]');
    const libraryLoading = modalEl.querySelector('[data-media-picker-library-loading]');
    const libraryError = modalEl.querySelector('[data-media-picker-library-error]');
    const libraryEmpty = modalEl.querySelector('[data-media-picker-library-empty]');
    const applyBtn = modalEl.querySelector('[data-media-picker-apply]');
    const applyLabel = applyBtn ? applyBtn.querySelector('[data-label]') : null;
    const defaultApplyLabel = applyBtn
      ? (applyBtn.getAttribute('data-default-label') || (applyLabel ? applyLabel.textContent : applyBtn.textContent || 'Použít'))
      : 'Použít';
    const uploadApplyLabel = applyBtn ? (applyBtn.getAttribute('data-media-picker-upload-label') || '') : '';
    const libraryApplyLabel = applyBtn ? (applyBtn.getAttribute('data-media-picker-library-label') || '') : '';
    const context = modalEl.getAttribute('data-media-picker-context') || '';
    const libraryUrl = modalEl.getAttribute('data-media-picker-library-url') || DEFAULT_LIBRARY_URL;

    const state = {
      selection: null,
      libraryLoaded: false,
      itemsMap: new Map(),
      activeLibraryButton: null
    };

    function setApplyEnabled(enabled) {
      if (!applyBtn) {
        return;
      }
      applyBtn.disabled = !enabled;
    }

    function setApplyLabel(mode) {
      if (!applyBtn) {
        return;
      }
      let text = defaultApplyLabel;
      if (mode === 'upload' && uploadApplyLabel) {
        text = uploadApplyLabel;
      } else if (mode === 'library' && libraryApplyLabel) {
        text = libraryApplyLabel;
      }
      if (applyLabel) {
        applyLabel.textContent = text;
      } else {
        applyBtn.textContent = text;
      }
    }

    function setUploadSummary(text) {
      if (!uploadSummary) {
        return;
      }
      const value = text || '';
      uploadSummary.textContent = value;
      if (value) {
        uploadSummary.classList.remove('d-none');
      } else {
        uploadSummary.classList.add('d-none');
      }
    }

    function clearLibrarySelection() {
      if (state.activeLibraryButton) {
        state.activeLibraryButton.classList.remove('active');
        state.activeLibraryButton.removeAttribute('aria-pressed');
        state.activeLibraryButton = null;
      }
    }

    function dispatchSelectionChange(selection) {
      const detail = {
        modal: modalEl,
        context: context,
        selection: selection
          ? (selection.type === 'file'
            ? { type: 'file', file: selection.file }
            : { type: 'item', item: selection.item })
          : null
      };
      modalEl.dispatchEvent(new CustomEvent('cms:media-picker:selection-change', {
        bubbles: true,
        detail
      }));
    }

    function resetSelection() {
      state.selection = null;
      setUploadSummary('');
      setApplyEnabled(false);
      setApplyLabel('default');
      clearLibrarySelection();
      dispatchSelectionChange(null);
    }

    function prepareFileSelection(file) {
      if (!file) {
        return;
      }
      state.selection = { type: 'file', file };
      clearLibrarySelection();
      setUploadSummary(formatFileSummary(file));
      setApplyLabel('upload');
      setApplyEnabled(true);
      dispatchSelectionChange(state.selection);
    }

    function prepareLibrarySelection(item, button) {
      const normalized = normalizeLibraryItem(item);
      state.selection = { type: 'item', item: normalized };
      setUploadSummary('');
      if (button) {
        clearLibrarySelection();
        state.activeLibraryButton = button;
        button.classList.add('active');
        button.setAttribute('aria-pressed', 'true');
      } else {
        clearLibrarySelection();
      }
      setApplyLabel('library');
      setApplyEnabled(true);
      dispatchSelectionChange(state.selection);
    }

    function buildLibraryButton(item) {
      const normalized = normalizeLibraryItem(item);
      const column = document.createElement('div');
      column.className = 'col-6 col-md-4 col-lg-3';

      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'btn btn-outline-secondary w-100 h-100 d-flex flex-column align-items-center justify-content-center gap-2 p-3';
      if (normalized.id !== null) {
        button.dataset.mediaId = String(normalized.id);
      }
      if (normalized.url) {
        button.dataset.mediaUrl = normalized.url;
      }
      if (normalized.displayUrl && normalized.displayUrl !== normalized.url) {
        button.dataset.mediaDisplayUrl = normalized.displayUrl;
      }
      if (normalized.mime) {
        button.dataset.mediaMime = normalized.mime;
      }
      button.__mediaItem = normalized;

      const previewUrl = normalized.displayUrl || normalized.url;
      if (normalized.mime && normalized.mime.indexOf('image/') === 0 && previewUrl) {
        const img = document.createElement('img');
        img.src = previewUrl;
        img.alt = normalized.name || 'Obrázek';
        img.style.maxHeight = '140px';
        img.style.maxWidth = '100%';
        img.style.objectFit = 'cover';
        button.appendChild(img);
      } else {
        const icon = document.createElement('i');
        icon.className = 'bi bi-file-earmark-image fs-2';
        button.appendChild(icon);
        const label = document.createElement('div');
        label.className = 'text-secondary small text-truncate w-100';
        label.textContent = normalized.name || normalized.url || 'Soubor';
        button.appendChild(label);
      }

      button.addEventListener('click', (event) => {
        event.preventDefault();
        prepareLibrarySelection(normalized, button);
      });
      button.addEventListener('dblclick', (event) => {
        event.preventDefault();
        prepareLibrarySelection(normalized, button);
        commitSelection('dblclick');
      });

      column.appendChild(button);
      return column;
    }

    function renderLibrary(items) {
      if (!libraryGrid) {
        return;
      }
      libraryGrid.innerHTML = '';
      state.itemsMap.clear();
      clearLibrarySelection();

      const list = Array.isArray(items) ? items : [];
      if (list.length === 0) {
        if (libraryEmpty) {
          libraryEmpty.classList.remove('d-none');
        }
        return;
      }

      if (libraryEmpty) {
        libraryEmpty.classList.add('d-none');
      }

      list.forEach((item) => {
        const normalized = normalizeLibraryItem(item);
        if (normalized.id !== null) {
          state.itemsMap.set(String(normalized.id), normalized);
        }
        const column = buildLibraryButton(normalized);
        libraryGrid.appendChild(column);
      });
    }

    function addItemToLibrary(item) {
      if (!libraryGrid || !item) {
        return;
      }
      const normalized = normalizeLibraryItem(item);
      if (normalized.id !== null && state.itemsMap.has(String(normalized.id))) {
        return;
      }
      if (libraryEmpty) {
        libraryEmpty.classList.add('d-none');
      }
      const column = buildLibraryButton(normalized);
      if (libraryGrid.firstChild) {
        libraryGrid.insertBefore(column, libraryGrid.firstChild);
      } else {
        libraryGrid.appendChild(column);
      }
      if (normalized.id !== null) {
        state.itemsMap.set(String(normalized.id), normalized);
      }
    }

    function showLibraryError(message) {
      if (libraryError) {
        libraryError.textContent = message || 'Došlo k chybě při načítání médií.';
        libraryError.classList.remove('d-none');
      }
    }

    function hideLibraryError() {
      if (libraryError) {
        libraryError.textContent = '';
        libraryError.classList.add('d-none');
      }
    }

    function loadLibrary(force) {
      if (state.libraryLoaded && !force) {
        return;
      }
      state.libraryLoaded = true;
      if (libraryLoading) {
        libraryLoading.classList.remove('d-none');
      }
      hideLibraryError();

      fetch(libraryUrl, { headers: { Accept: 'application/json' } })
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
          state.libraryLoaded = false;
          showLibraryError(error && error.message ? error.message : 'Došlo k chybě při načítání médií.');
        });
    }

    function commitSelection(trigger) {
      if (!state.selection) {
        return;
      }
      const detail = {
        modal: modalEl,
        context,
        selection: state.selection,
        trigger
      };
      const event = new CustomEvent('cms:media-picker:apply', {
        bubbles: true,
        cancelable: true,
        detail
      });
      const prevented = !modalEl.dispatchEvent(event);
      if (!prevented) {
        closeModal(modalEl);
        resetSelection();
      }
    }

    if (fileInput) {
      fileInput.addEventListener('change', () => {
        if (fileInput.files && fileInput.files[0]) {
          prepareFileSelection(fileInput.files[0]);
        } else {
          resetSelection();
        }
      });
    }

    if (dropzone) {
      dropzone.addEventListener('dragover', (event) => {
        event.preventDefault();
        dropzone.classList.add('border-primary');
      });
      dropzone.addEventListener('dragenter', (event) => {
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
        if (!fileInput) {
          return;
        }
        if (event.target === fileInput) {
          return;
        }
        event.preventDefault();
        fileInput.click();
      });
    }

    if (libraryTab) {
      libraryTab.addEventListener('shown.bs.tab', () => {
        loadLibrary(false);
      });
    }

    if (applyBtn) {
      applyBtn.addEventListener('click', (event) => {
        event.preventDefault();
        commitSelection('button');
      });
    }

    modalEl.addEventListener('show.bs.modal', () => {
      resetSelection();
      if (libraryLoading) {
        libraryLoading.classList.add('d-none');
      }
      hideLibraryError();
    });

    modalEl.addEventListener('hidden.bs.modal', () => {
      resetSelection();
    });

    modalEl.addEventListener('cms:media-picker:add-item', (event) => {
      const detail = event && event.detail ? event.detail : {};
      if (detail && detail.item) {
        addItemToLibrary(detail.item);
      }
    });

    resetSelection();

    modalEl._mediaPicker = {
      reset: resetSelection,
      addItem: addItemToLibrary,
      loadLibrary,
      getSelection: () => state.selection,
      getFileInputFiles: () => (fileInput && fileInput.files ? fileInput.files : null)
    };
  });
}
