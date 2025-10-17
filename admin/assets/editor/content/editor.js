(function () {
  var bootstrap = window.bootstrap;

  function fallbackLinkDialog(opts) {
    var defaultValue = opts && opts.defaultValue ? String(opts.defaultValue) : '';
    var url = window.prompt('Zadej adresu odkazu:', defaultValue);
    if (url && opts && typeof opts.onSubmit === 'function') {
      opts.onSubmit(url, !!opts.openInNewTab);
    }
  }

  function fallbackImageDialog(opts) {
    var url = window.prompt('Zadej adresu obrázku:');
    if (url && opts && typeof opts.onInsert === 'function') {
      opts.onInsert({ id: null, url: url, mime: 'image/*' }, '');
    }
  }

  function setupLinkDialog(modalEl) {
    var modal = new bootstrap.Modal(modalEl);
    var urlInput = modalEl.querySelector('[data-link-url]');
    var targetCheckbox = modalEl.querySelector('[data-link-target]');
    var confirmBtn = modalEl.querySelector('[data-link-confirm]');
    var errorEl = modalEl.querySelector('[data-link-error]');
    var currentOpts = null;

    function showError(message) {
      if (!errorEl) { return; }
      errorEl.textContent = message || '';
      errorEl.classList.remove('d-none');
    }

    function clearError() {
      if (!errorEl) { return; }
      errorEl.textContent = '';
      errorEl.classList.add('d-none');
    }

    if (confirmBtn) {
      confirmBtn.addEventListener('click', function (evt) {
        evt.preventDefault();
        if (!urlInput) {
          modal.hide();
          return;
        }
        var url = urlInput.value.trim();
        if (!url) {
          showError('Zadej URL adresu.');
          urlInput.focus();
          return;
        }
        clearError();
        var openInNewTab = targetCheckbox ? !!targetCheckbox.checked : false;
        modal.hide();
        if (currentOpts && typeof currentOpts.onSubmit === 'function') {
          currentOpts.onSubmit(url, openInNewTab);
        }
      });
    }

    modalEl.addEventListener('hidden.bs.modal', function () {
      currentOpts = null;
      if (urlInput) {
        urlInput.value = '';
      }
      if (targetCheckbox) {
        targetCheckbox.checked = false;
      }
      clearError();
    });

    modalEl.addEventListener('shown.bs.modal', function () {
      if (urlInput) {
        urlInput.focus();
        urlInput.select();
      }
    });

    return {
      open: function (opts) {
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

  function setupImageDialog(modalEl) {
    var modal = new bootstrap.Modal(modalEl);
    var confirmBtn = modalEl.querySelector('[data-image-confirm]');
    var altInput = modalEl.querySelector('[data-image-alt]');
    var fileInput = modalEl.querySelector('#content-image-file');
    var dropzone = modalEl.querySelector('#content-image-dropzone');
    var uploadInfo = modalEl.querySelector('#content-image-upload-info');
    var errorEl = modalEl.querySelector('[data-image-error]');
    var selectedInfo = modalEl.querySelector('#content-image-selected-info');
    var libraryTab = modalEl.querySelector('#content-image-library-tab');
    var libraryGrid = modalEl.querySelector('#content-image-library-grid');
    var libraryLoading = modalEl.querySelector('#content-image-library-loading');
    var libraryEmpty = modalEl.querySelector('#content-image-library-empty');
    var libraryError = modalEl.querySelector('#content-image-library-error');
    var defaultLabel = confirmBtn ? confirmBtn.textContent : 'Vložit';
    var pendingFile = null;
    var pendingLibraryItem = null;
    var activeLibraryButton = null;
    var currentOpts = null;
    var libraryLoaded = false;
    var libraryItemsMap = new Map();

    function setSelectedInfo(text) {
      if (selectedInfo) {
        selectedInfo.textContent = text || '';
      }
    }

    function setUploadInfo(text) {
      if (!uploadInfo) { return; }
      if (text) {
        uploadInfo.textContent = text;
        uploadInfo.classList.remove('d-none');
      } else {
        uploadInfo.textContent = '';
        uploadInfo.classList.add('d-none');
      }
    }

    function setError(message) {
      if (!errorEl) { return; }
      if (message) {
        errorEl.textContent = message;
        errorEl.classList.remove('d-none');
      } else {
        errorEl.textContent = '';
        errorEl.classList.add('d-none');
      }
    }

    function updateConfirm(label, enabled) {
      if (!confirmBtn) { return; }
      confirmBtn.textContent = label || defaultLabel;
      confirmBtn.disabled = !enabled;
    }

    function clearLibrarySelection() {
      if (activeLibraryButton) {
        activeLibraryButton.classList.remove('active');
        activeLibraryButton.removeAttribute('aria-pressed');
        activeLibraryButton = null;
      }
    }

    function resetState() {
      pendingFile = null;
      pendingLibraryItem = null;
      clearLibrarySelection();
      if (fileInput) {
        fileInput.value = '';
      }
      if (dropzone) {
        dropzone.classList.remove('border-primary');
      }
      if (altInput) {
        altInput.value = '';
      }
      setUploadInfo('');
      setSelectedInfo('');
      setError('');
      updateConfirm(defaultLabel, false);
    }

    modalEl.addEventListener('hidden.bs.modal', resetState);
    modalEl.addEventListener('shown.bs.modal', function () {
      if (altInput) {
        altInput.focus();
        altInput.select();
      }
    });

    function selectFile(file) {
      if (!file) { return; }
      pendingFile = file;
      pendingLibraryItem = null;
      clearLibrarySelection();
      var summary = file.name || 'Soubor';
      if (file.type) {
        summary += ' (' + file.type + ')';
      }
      setUploadInfo(summary);
      setSelectedInfo('Vybrán nový soubor: ' + summary);
      if (altInput && !altInput.value) {
        altInput.value = file.name || '';
      }
      updateConfirm('Nahrát a vložit', true);
      setError('');
    }

    if (fileInput) {
      fileInput.addEventListener('change', function () {
        if (fileInput.files && fileInput.files[0]) {
          selectFile(fileInput.files[0]);
        }
      });
    }

    if (dropzone) {
      dropzone.addEventListener('click', function (evt) {
        evt.preventDefault();
        if (fileInput) {
          fileInput.click();
        }
      });
      dropzone.addEventListener('dragover', function (evt) {
        evt.preventDefault();
        dropzone.classList.add('border-primary');
      });
      dropzone.addEventListener('dragenter', function (evt) {
        evt.preventDefault();
        dropzone.classList.add('border-primary');
      });
      dropzone.addEventListener('dragleave', function () {
        dropzone.classList.remove('border-primary');
      });
      dropzone.addEventListener('drop', function (evt) {
        evt.preventDefault();
        dropzone.classList.remove('border-primary');
        if (evt.dataTransfer && evt.dataTransfer.files && evt.dataTransfer.files[0]) {
          selectFile(evt.dataTransfer.files[0]);
        }
      });
    }

    function buildLibraryButton(item) {
      var col = document.createElement('div');
      col.className = 'col-6 col-md-4 col-lg-3';
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-outline-secondary w-100 h-100 d-flex flex-column align-items-center justify-content-center gap-2 p-3';
      btn.dataset.mediaId = item && item.id ? String(item.id) : '';
      btn.dataset.mediaUrl = item && item.url ? item.url : '';
      btn.dataset.mediaMime = item && item.mime ? item.mime : '';

      if (item && item.mime && item.mime.indexOf('image/') === 0 && item.url) {
        var img = document.createElement('img');
        img.src = item.url;
        img.alt = item.name || 'Obrázek';
        img.style.maxHeight = '140px';
        img.style.maxWidth = '100%';
        img.style.objectFit = 'cover';
        btn.appendChild(img);
      } else {
        var icon = document.createElement('i');
        icon.className = 'bi bi-file-earmark-image fs-2';
        btn.appendChild(icon);
        var label = document.createElement('div');
        label.className = 'text-secondary small text-truncate w-100';
        label.textContent = item && item.name ? item.name : (item && item.url ? item.url : 'Soubor');
        btn.appendChild(label);
      }

      btn.addEventListener('click', function (evt) {
        evt.preventDefault();
        selectLibraryItem(item, btn);
      });
      btn.addEventListener('dblclick', function (evt) {
        evt.preventDefault();
        selectLibraryItem(item, btn);
        commitSelection();
      });

      col.appendChild(btn);
      return col;
    }

    function renderLibrary(items) {
      if (!libraryGrid) { return; }
      libraryGrid.innerHTML = '';
      libraryItemsMap.clear();
      clearLibrarySelection();

      if (!items || !items.length) {
        if (libraryEmpty) {
          libraryEmpty.classList.remove('d-none');
        }
        return;
      }

      if (libraryEmpty) {
        libraryEmpty.classList.add('d-none');
      }

      items.forEach(function (item) {
        libraryItemsMap.set(item.id, item);
        var col = buildLibraryButton(item);
        libraryGrid.appendChild(col);
      });
    }

    function addItemToLibrary(item) {
      if (!libraryGrid || !item || !item.id) { return; }
      if (libraryItemsMap.has(item.id)) { return; }
      libraryItemsMap.set(item.id, item);
      if (libraryEmpty) {
        libraryEmpty.classList.add('d-none');
      }
      var col = buildLibraryButton(item);
      libraryGrid.insertBefore(col, libraryGrid.firstChild);
    }

    function selectLibraryItem(item, button) {
      pendingLibraryItem = item;
      pendingFile = null;
      clearLibrarySelection();
      if (button) {
        activeLibraryButton = button;
        button.classList.add('active');
        button.setAttribute('aria-pressed', 'true');
      }
      setUploadInfo('');
      var summary = item && item.name ? item.name : (item && item.url ? item.url : 'Soubor');
      setSelectedInfo('Vybráno z knihovny: ' + summary);
      if (altInput && item && item.name) {
        altInput.value = item.name;
      }
      updateConfirm('Vložit z knihovny', true);
      setError('');
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

      fetch('admin.php?r=media&a=library&type=image&limit=60', {
        headers: { 'Accept': 'application/json' }
      })
        .then(function (response) {
          if (!response.ok) {
            throw new Error('Nepodařilo se načíst knihovnu médií.');
          }
          return response.json();
        })
        .then(function (data) {
          if (libraryLoading) {
            libraryLoading.classList.add('d-none');
          }
          renderLibrary(data && data.items ? data.items : []);
        })
        .catch(function (error) {
          if (libraryLoading) {
            libraryLoading.classList.add('d-none');
          }
          if (libraryError) {
            libraryError.textContent = (error && error.message) ? error.message : 'Došlo k chybě při načítání médií.';
            libraryError.classList.remove('d-none');
          }
        });
    }

    if (libraryTab) {
      libraryTab.addEventListener('shown.bs.tab', loadLibrary);
    }

    function commitSelection() {
      if (pendingFile) {
        uploadSelectedFile();
        return;
      }
      if (pendingLibraryItem) {
        insertSelectedLibraryItem();
      }
    }

    function uploadSelectedFile() {
      if (!pendingFile || !currentOpts || typeof currentOpts.onInsert !== 'function') {
        return;
      }
      if (!currentOpts.csrf) {
        setError('Chybí CSRF token.');
        return;
      }

      var formData = new FormData();
      formData.append('csrf', currentOpts.csrf);
      formData.append('file', pendingFile);
      if (currentOpts.postId) {
        formData.append('post_id', String(currentOpts.postId));
      }

      setError('');
      updateConfirm('Nahrávání…', false);

      fetch('admin.php?r=media&a=upload-editor', {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF': currentOpts.csrf
        },
        body: formData
      })
        .then(function (response) {
          if (!response.ok) {
            throw new Error('Nahrávání se nezdařilo.');
          }
          return response.json();
        })
        .then(function (data) {
          if (!data || data.success !== true || !data.item) {
            var message = data && data.error ? data.error : 'Nahrávání se nezdařilo.';
            throw new Error(message);
          }
          pendingFile = null;
          updateConfirm(defaultLabel, true);
          addItemToLibrary(data.item);
          currentOpts.onInsert(data.item, altInput ? altInput.value : '');
          modal.hide();
        })
        .catch(function (error) {
          updateConfirm('Nahrát a vložit', true);
          setError(error && error.message ? error.message : 'Nahrávání se nezdařilo.');
        });
    }

    function insertSelectedLibraryItem() {
      if (!pendingLibraryItem || !currentOpts || typeof currentOpts.onInsert !== 'function') {
        return;
      }
      currentOpts.onInsert(pendingLibraryItem, altInput ? altInput.value : '');
      modal.hide();
    }

    if (confirmBtn) {
      confirmBtn.addEventListener('click', function (evt) {
        evt.preventDefault();
        commitSelection();
      });
    }

    return {
      open: function (opts) {
        currentOpts = opts || {};
        resetState();
        updateConfirm(defaultLabel, false);
        if (currentOpts.defaultAlt && altInput) {
          altInput.value = currentOpts.defaultAlt;
        }
        if (libraryTab && libraryTab.classList.contains('active')) {
          loadLibrary();
        }
        modal.show();
      }
    };
  }

  function openLinkDialogWithFallback(opts) {
    if (!bootstrap || !bootstrap.Modal) {
      fallbackLinkDialog(opts);
      return;
    }
    var modalEl = document.getElementById('contentEditorLinkModal');
    if (!modalEl) {
      fallbackLinkDialog(opts);
      return;
    }
    if (!modalEl.__contentEditorLinkDialog) {
      modalEl.__contentEditorLinkDialog = setupLinkDialog(modalEl);
    }
    modalEl.__contentEditorLinkDialog.open(opts);
  }

  function openImageDialogWithFallback(opts) {
    if (!bootstrap || !bootstrap.Modal) {
      fallbackImageDialog(opts);
      return;
    }
    var modalEl = document.getElementById('contentEditorImageModal');
    if (!modalEl) {
      fallbackImageDialog(opts);
      return;
    }
    if (!modalEl.__contentEditorImageDialog) {
      modalEl.__contentEditorImageDialog = setupImageDialog(modalEl);
    }
    modalEl.__contentEditorImageDialog.open(opts);
  }

  function initEditor(textarea) {
    if (!textarea || textarea.__contentEditorInit) {
      return;
    }
    textarea.__contentEditorInit = true;

    var placeholder = textarea.getAttribute('data-placeholder') || 'Napiš obsah…';
    textarea.classList.add('d-none');

    var wrapper = document.createElement('div');
    wrapper.className = 'content-editor';

    var toolbar = document.createElement('div');
    toolbar.className = 'content-editor-toolbar';

    var area = document.createElement('div');
    area.className = 'content-editor-area';
    area.contentEditable = 'true';
    area.setAttribute('data-placeholder', placeholder);

    var sourceWrapper = document.createElement('div');
    sourceWrapper.className = 'content-editor-source';
    var sourceTextarea = document.createElement('textarea');
    sourceWrapper.appendChild(sourceTextarea);

    var attachmentsInput = null;
    var attachmentsSelector = textarea.getAttribute('data-attachments-input');
    if (attachmentsSelector) {
      attachmentsInput = document.querySelector(attachmentsSelector);
    }
    var attachments = new Set();

    function parseMediaIds(html) {
      if (!html) {
        return [];
      }
      var container = document.createElement('div');
      container.innerHTML = html;
      var imgs = container.querySelectorAll ? container.querySelectorAll('img[data-media-id]') : [];
      var collected = [];
      Array.prototype.forEach.call(imgs, function (img) {
        var raw = img.getAttribute('data-media-id');
        if (!raw) {
          return;
        }
        var id = parseInt(raw, 10);
        if (!isNaN(id) && id > 0) {
          collected.push(id);
        }
      });
      return Array.from(new Set(collected));
    }

    function refreshAttachmentsFromHtml(html) {
      var ids = parseMediaIds(html || '');
      attachments = new Set(ids);
      if (attachmentsInput) {
        attachmentsInput.value = ids.length ? JSON.stringify(ids) : '';
      }
    }

    var form = textarea.closest('form');
    var csrfToken = '';
    if (form) {
      var csrfInput = form.querySelector('input[name="csrf"]');
      if (csrfInput) {
        csrfToken = csrfInput.value || '';
      }
    }
    var postIdAttr = textarea.getAttribute('data-post-id');
    var postId = parseInt(postIdAttr || '', 10);
    if (isNaN(postId) || postId <= 0) {
      postId = null;
    }

    var buttons = [
      { cmd: 'bold', label: 'B', title: 'Tučné', state: true },
      { cmd: 'italic', label: 'I', title: 'Kurzíva', state: true },
      { cmd: 'underline', label: 'U', title: 'Podtržení', state: true },
      { cmd: 'insertUnorderedList', label: '•', title: 'Seznam' },
      { cmd: 'insertOrderedList', label: '1.', title: 'Číslovaný seznam' },
      { cmd: 'formatBlock', label: 'H2', title: 'Nadpis', value: 'h2' },
      { cmd: 'formatBlock', label: 'Odstavec', title: 'Odstavec', value: 'p' },
      { cmd: 'createLink', label: 'Odkaz', title: 'Vložit odkaz', modal: 'link' },
      { cmd: 'insertImage', label: 'Obrázek', title: 'Vložit obrázek', modal: 'image' },
      { cmd: 'removeFormat', label: 'Vymazat', title: 'Odstranit formátování' }
    ];

    buttons.forEach(function (buttonConfig) {
      var button = document.createElement('button');
      button.type = 'button';
      button.textContent = buttonConfig.label;
      button.title = buttonConfig.title;
      button.addEventListener('click', function (evt) {
        evt.preventDefault();
        if (wrapper.classList.contains('is-source')) {
          return;
        }
        area.focus();
        saveSelection();
        if (buttonConfig.modal === 'link') {
          showLinkDialog();
          return;
        }
        if (buttonConfig.modal === 'image') {
          showImageDialog();
          return;
        }
        if (buttonConfig.cmd === 'formatBlock') {
          document.execCommand('formatBlock', false, buttonConfig.value || 'p');
        } else {
          document.execCommand(buttonConfig.cmd, false, buttonConfig.value || null);
        }
        syncState();
        updateTextarea();
        saveSelection();
      });
      toolbar.appendChild(button);
      buttonConfig._el = button;
    });

    var sourceToggle = document.createElement('button');
    sourceToggle.type = 'button';
    sourceToggle.className = 'content-editor-source-toggle btn btn-sm btn-outline-secondary';
    sourceToggle.textContent = 'HTML';
    sourceToggle.addEventListener('click', function () {
      wrapper.classList.toggle('is-source');
      if (wrapper.classList.contains('is-source')) {
        sourceTextarea.value = textarea.value;
        sourceToggle.classList.add('active');
      } else {
        area.innerHTML = sourceTextarea.value;
        sourceToggle.classList.remove('active');
      }
      updateTextarea();
      saveSelection();
      syncState();
    });
    toolbar.appendChild(sourceToggle);

    wrapper.appendChild(toolbar);
    wrapper.appendChild(area);
    wrapper.appendChild(sourceWrapper);
    if (textarea.parentNode) {
      textarea.parentNode.insertBefore(wrapper, textarea.nextSibling);
    }

    var savedRange = null;

    function selectionInsideArea(range) {
      if (!range) {
        return false;
      }
      var container = range.commonAncestorContainer;
      if (container === area) {
        return true;
      }
      if (container && container.nodeType === Node.TEXT_NODE) {
        container = container.parentElement;
      }
      return !!(container && area.contains(container));
    }

    function saveSelection() {
      var selection = window.getSelection();
      if (!selection || selection.rangeCount === 0) {
        return;
      }
      var range = selection.getRangeAt(0);
      if (!selectionInsideArea(range)) {
        return;
      }
      savedRange = range.cloneRange();
    }

    function restoreSelection() {
      if (!savedRange) {
        area.focus();
        return false;
      }
      var selection = window.getSelection();
      if (!selection) {
        return false;
      }
      selection.removeAllRanges();
      selection.addRange(savedRange);
      area.focus();
      return true;
    }

    function findAnchorAtSelection() {
      var range = savedRange;
      if (!range) {
        return null;
      }
      var container = range.commonAncestorContainer;
      if (container && container.nodeType === Node.TEXT_NODE) {
        container = container.parentElement;
      }
      if (container && container.closest) {
        return container.closest('a');
      }
      return null;
    }

    function insertLink(url, openInNewTab) {
      if (!url) {
        return;
      }
      if (!restoreSelection()) {
        var tempRange = document.createRange();
        tempRange.selectNodeContents(area);
        tempRange.collapse(false);
        var tempSel = window.getSelection();
        if (tempSel) {
          tempSel.removeAllRanges();
          tempSel.addRange(tempRange);
        }
      }
      var selection = window.getSelection();
      if (!selection || selection.rangeCount === 0) {
        return;
      }
      var range = selection.getRangeAt(0);
      if (selection.isCollapsed) {
        var anchor = document.createElement('a');
        anchor.href = url;
        anchor.textContent = url;
        if (openInNewTab) {
          anchor.setAttribute('target', '_blank');
          anchor.setAttribute('rel', 'noopener');
        }
        range.insertNode(anchor);
        range.setStartAfter(anchor);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
      } else {
        document.execCommand('createLink', false, url);
        var createdAnchor = findAnchorAtSelection();
        if (createdAnchor) {
          if (openInNewTab) {
            createdAnchor.setAttribute('target', '_blank');
            createdAnchor.setAttribute('rel', 'noopener');
          } else {
            createdAnchor.removeAttribute('target');
            createdAnchor.removeAttribute('rel');
          }
        }
      }
      saveSelection();
      updateTextarea();
      syncState();
    }

    function insertImage(item, altText) {
      if (!item || !item.url) {
        return;
      }
      if (!restoreSelection()) {
        var tempRange = document.createRange();
        tempRange.selectNodeContents(area);
        tempRange.collapse(false);
        var tempSel = window.getSelection();
        if (tempSel) {
          tempSel.removeAllRanges();
          tempSel.addRange(tempRange);
        }
      }
      var selection = window.getSelection();
      if (!selection || selection.rangeCount === 0) {
        return;
      }
      var range = selection.getRangeAt(0);
      range.deleteContents();
      var fragment = document.createDocumentFragment();
      var img = document.createElement('img');
      img.src = item.url;
      img.alt = altText || '';
      img.setAttribute('loading', 'lazy');
      if (item.id) {
        img.setAttribute('data-media-id', String(item.id));
      }
      fragment.appendChild(img);
      var spacer = document.createElement('br');
      fragment.appendChild(spacer);
      range.insertNode(fragment);
      range.setStartAfter(spacer);
      range.collapse(true);
      selection.removeAllRanges();
      selection.addRange(range);
      savedRange = range.cloneRange();
      updateTextarea();
      syncState();
    }

    function showLinkDialog() {
      var anchor = findAnchorAtSelection();
      openLinkDialogWithFallback({
        defaultValue: anchor ? anchor.getAttribute('href') || '' : '',
        openInNewTab: anchor ? anchor.getAttribute('target') === '_blank' : false,
        onSubmit: function (url, openInNewTab) {
          insertLink(url, openInNewTab);
        }
      });
    }

    function showImageDialog() {
      openImageDialogWithFallback({
        csrf: csrfToken,
        postId: postId,
        onInsert: function (item, altText) {
          insertImage(item, altText);
        }
      });
    }

    function updateTextarea() {
      var currentHtml;
      if (wrapper.classList.contains('is-source')) {
        textarea.value = sourceTextarea.value;
        currentHtml = sourceTextarea.value;
      } else {
        textarea.value = area.innerHTML;
        sourceTextarea.value = textarea.value;
        currentHtml = textarea.value;
      }
      refreshAttachmentsFromHtml(currentHtml);
    }

    function syncState() {
      if (wrapper.classList.contains('is-source')) {
        return;
      }
      buttons.forEach(function (buttonConfig) {
        if (!buttonConfig.state) {
          return;
        }
        var isActive = document.queryCommandState(buttonConfig.cmd);
        if (isActive) {
          buttonConfig._el.classList.add('active');
        } else {
          buttonConfig._el.classList.remove('active');
        }
      });
    }

    function restoreInitialContent() {
      var value = textarea.value || '';
      area.innerHTML = value;
      sourceTextarea.value = value;
      updateTextarea();
      syncState();
      saveSelection();
    }

    area.addEventListener('input', function () {
      updateTextarea();
      syncState();
      saveSelection();
    });

    area.addEventListener('keyup', function () {
      syncState();
      saveSelection();
    });

    area.addEventListener('mouseup', saveSelection);
    area.addEventListener('focus', saveSelection);

    sourceTextarea.addEventListener('input', updateTextarea);

    document.addEventListener('selectionchange', function () {
      var selection = window.getSelection();
      if (!selection || selection.rangeCount === 0) {
        return;
      }
      var range = selection.getRangeAt(0);
      if (selectionInsideArea(range) && document.activeElement === area) {
        savedRange = range.cloneRange();
        syncState();
      }
    });

    if (form) {
      form.addEventListener('submit', function () {
        updateTextarea();
      });
    }

    restoreInitialContent();
  }

  function scanEditors(root) {
    var scope = root && typeof root.querySelectorAll === 'function' ? root : document;
    var editors = scope.querySelectorAll ? scope.querySelectorAll('textarea[data-content-editor]') : [];
    editors.forEach(initEditor);
  }

  document.addEventListener('DOMContentLoaded', function () {
    scanEditors(document);
  });

  document.addEventListener('cms:admin:navigated', function (event) {
    var detail = event && event.detail ? event.detail : {};
    var root = detail.root && typeof detail.root.querySelectorAll === 'function' ? detail.root : document;
    scanEditors(root);
  });
})();
