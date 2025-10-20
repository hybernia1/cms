(function () {
  var HISTORY_STATE_KEY = 'cmsAdminAjax';
  var AUTOSAVE_INTERVAL = 30000;
  var activeNavigation = null;
  var adminMenuMediaQuery = null;
  var confirmModalElement = null;
  var confirmModalInstance = null;
  var confirmModalCallback = null;
  var FLASH_DISMISS_DELAY = 5000;
  var FLASH_DISMISS_ANIMATION = 260;
  var flashActiveElement = null;
  var flashDismissTimeout = null;
  var flashRemoveTimeout = null;

  function isAjaxForm(el) {
    return el && el.hasAttribute && el.hasAttribute('data-ajax');
  }

  function shouldResetFormOnSuccess(form) {
    if (!form || !form.hasAttribute || !form.hasAttribute('data-reset-on-success')) {
      return false;
    }
    var attr = form.getAttribute('data-reset-on-success');
    if (attr === null || attr === '') {
      return true;
    }
    var normalized = String(attr).toLowerCase();
    return normalized !== 'false' && normalized !== '0';
  }

  function findFlashContainer(form) {
    if (form && typeof form.closest === 'function') {
      var closest = form.closest('[data-flash-container]');
      if (closest) {
        return closest;
      }
    }
    var selector = form ? form.getAttribute('data-flash-target') : null;
    if (selector) {
      try {
        var explicit = document.querySelector(selector);
        if (explicit) {
          return explicit;
        }
      } catch (e) {
        /* ignore invalid selectors */
      }
    }
    return document.querySelector('[data-flash-container]') || document.body;
  }

  function removeFlashElement(element) {
    if (!element) {
      return;
    }
    if (flashActiveElement === element) {
      if (flashDismissTimeout) {
        window.clearTimeout(flashDismissTimeout);
        flashDismissTimeout = null;
      }
      if (flashRemoveTimeout) {
        window.clearTimeout(flashRemoveTimeout);
        flashRemoveTimeout = null;
      }
      flashActiveElement = null;
    }
    if (element.parentNode) {
      element.parentNode.removeChild(element);
    }
  }

  function hideFlashMessage(element) {
    if (!element) {
      return;
    }
    if (flashDismissTimeout) {
      window.clearTimeout(flashDismissTimeout);
      flashDismissTimeout = null;
    }
    if (element.classList.contains('is-dismissing')) {
      return;
    }
    element.classList.add('is-dismissing');
    if (flashRemoveTimeout) {
      window.clearTimeout(flashRemoveTimeout);
    }
    flashRemoveTimeout = window.setTimeout(function () {
      removeFlashElement(element);
      flashRemoveTimeout = null;
    }, FLASH_DISMISS_ANIMATION);
  }

  function scheduleFlashDismiss(element) {
    if (!element) {
      return;
    }
    flashActiveElement = element;
    if (flashDismissTimeout) {
      window.clearTimeout(flashDismissTimeout);
    }
    if (flashRemoveTimeout) {
      window.clearTimeout(flashRemoveTimeout);
      flashRemoveTimeout = null;
    }
    element.classList.remove('is-dismissing');
    flashDismissTimeout = window.setTimeout(function () {
      hideFlashMessage(element);
    }, FLASH_DISMISS_DELAY);
  }

  function bindFlashContainerInteractions(container) {
    if (!container || container.dataset.flashBound === '1') {
      return;
    }
    container.dataset.flashBound = '1';
    container.addEventListener('click', function (event) {
      var alert = event.target && event.target.closest ? event.target.closest('.admin-flash') : null;
      if (alert) {
        hideFlashMessage(alert);
      }
    });
  }

  function initFlashMessages(root) {
    var container = null;
    if (root && typeof root.querySelector === 'function') {
      container = root.querySelector('[data-flash-container]');
    }
    if (!container) {
      container = document.querySelector('[data-flash-container]');
    }
    if (!container) {
      return;
    }
    bindFlashContainerInteractions(container);
    var alerts = [].slice.call(container.querySelectorAll('.admin-flash'));
    alerts.forEach(function (alert) {
      scheduleFlashDismiss(alert);
    });
  }

  function showFlashMessage(type, message, form) {
    var container = findFlashContainer(form);
    if (!container) {
      return;
    }

    var existing = container.querySelector('.admin-flash');
    if (existing) {
      removeFlashElement(existing);
    }

    if (!message) {
      return;
    }

    var allowed = ['success', 'danger', 'warning', 'info'];
    var normalized = typeof type === 'string' && allowed.indexOf(type) !== -1 ? type : 'info';
    var alert = document.createElement('div');
    alert.className = 'alert alert-' + normalized + ' admin-flash';
    alert.setAttribute('role', 'alert');
    alert.textContent = message;
    if (container.firstChild) {
      container.insertBefore(alert, container.firstChild);
    } else {
      container.appendChild(alert);
    }
    scheduleFlashDismiss(alert);
  }

  function normalizeUrl(url) {
    try {
      return new URL(url, window.location.href).toString();
    } catch (e) {
      return url;
    }
  }

  function buildHistoryState(extra) {
    var state = {};
    state[HISTORY_STATE_KEY] = true;
    if (extra && typeof extra === 'object') {
      Object.keys(extra).forEach(function (key) {
        state[key] = extra[key];
      });
    }
    return state;
  }

  function parseHtmlDocument(html) {
    if (typeof html !== 'string') {
      return null;
    }
    try {
      if (typeof DOMParser === 'function') {
        return new DOMParser().parseFromString(html, 'text/html');
      }
    } catch (err) {
      /* ignore */
    }
    if (document.implementation && document.implementation.createHTMLDocument) {
      var doc = document.implementation.createHTMLDocument('');
      try {
        doc.open();
        doc.write(html);
        doc.close();
        return doc;
      } catch (e) {
        return null;
      }
    }
    return null;
  }

  function executeScripts(root) {
    if (!root) {
      return;
    }
    var scripts = [].slice.call(root.querySelectorAll('script'));
    scripts.forEach(function (oldScript) {
      if (!oldScript || !oldScript.parentNode) {
        return;
      }
      var newScript = document.createElement('script');
      for (var i = 0; i < oldScript.attributes.length; i++) {
        var attr = oldScript.attributes[i];
        newScript.setAttribute(attr.name, attr.value);
      }
      if (!oldScript.hasAttribute('src')) {
        newScript.textContent = oldScript.textContent;
      }
      oldScript.parentNode.replaceChild(newScript, oldScript);
    });
  }

  function applyAdminHtml(html) {
    if (typeof html !== 'string' || html.trim() === '') {
      return null;
    }
    var doc = parseHtmlDocument(html);
    if (!doc) {
      return null;
    }
    var newWrapper = replaceAdminShell(doc);
    if (!newWrapper) {
      return null;
    }
    syncDocumentMeta(doc);
    executeScripts(newWrapper);
    refreshDynamicUI(newWrapper);
    return newWrapper;
  }

  function readResponsePayload(response) {
    return response.text().then(function (text) {
      var contentType = response.headers ? (response.headers.get('Content-Type') || '') : '';
      var isJson = contentType.toLowerCase().indexOf('application/json') !== -1;
      var data;
      if (isJson) {
        if (text) {
          try {
            data = JSON.parse(text);
          } catch (err) {
            data = { raw: text };
          }
        } else {
          data = {};
        }
      } else {
        data = { raw: text };
      }
      return { response: response, text: text, data: data, isJson: isJson };
    });
  }

  function normalizeAjaxPayload(raw) {
    var success = true;
    var data = {};
    var message = '';
    var errors = [];

    if (!raw || typeof raw !== 'object') {
      return { success: success, data: data, message: message, errors: errors };
    }

    if (typeof raw.success === 'boolean') {
      success = raw.success;
    }

    if (Array.isArray(raw.errors)) {
      raw.errors.forEach(function (value) {
        if (typeof value === 'string' && value.trim() !== '') {
          errors.push(value.trim());
        }
      });
    } else if (typeof raw.errors === 'string' && raw.errors.trim() !== '') {
      errors.push(raw.errors.trim());
    }

    if (raw.data && typeof raw.data === 'object') {
      data = raw.data;
    } else if (success) {
      data = raw;
    }

    if (typeof raw.message === 'string' && raw.message.trim() !== '') {
      message = raw.message.trim();
    } else if (data && typeof data.message === 'string' && data.message.trim() !== '') {
      message = data.message.trim();
    } else if (!success && errors.length > 0) {
      message = errors.join(' ');
    }

    return { success: success, data: data, message: message, errors: errors };
  }

  function extractMessageFromData(data, fallback) {
    var message = fallback || '';
    if (!data || typeof data !== 'object') {
      return message;
    }
    if (typeof data.success === 'boolean') {
      var normalized = normalizeAjaxPayload(data);
      if (!normalized.success) {
        return normalized.message || (normalized.errors.length ? normalized.errors.join(' ') : message);
      }
      if (normalized.message) {
        return normalized.message;
      }
      data = normalized.data || {};
    }
    if (typeof data.error === 'string' && data.error.trim() !== '') {
      return data.error.trim();
    }
    if (Array.isArray(data.errors) && data.errors.length > 0) {
      return data.errors.map(function (value) {
        return typeof value === 'string' ? value.trim() : String(value || '');
      }).join(' ').trim() || message;
    }
    if (typeof data.errors === 'string' && data.errors.trim() !== '') {
      return data.errors.trim();
    }
    if (typeof data.message === 'string' && data.message.trim() !== '') {
      return data.message.trim();
    }
    if (typeof data.raw === 'string' && data.raw.trim() !== '') {
      return data.raw.trim();
    }
    return message;
  }

  function fragmentFromHtml(html) {
    var template = document.createElement('template');
    template.innerHTML = typeof html === 'string' ? html : '';
    return template.content;
  }

  function transferFragment(parent, referenceNode, fragment) {
    var inserted = [];
    if (!parent || !fragment) {
      return inserted;
    }
    var node;
    while ((node = fragment.firstChild)) {
      fragment.removeChild(node);
      parent.insertBefore(node, referenceNode);
      if (node.nodeType === Node.ELEMENT_NODE) {
        inserted.push(node);
      }
    }
    return inserted;
  }

  function applyAjaxFragments(fragments) {
    if (!Array.isArray(fragments) || fragments.length === 0) {
      return [];
    }

    var touchedRoots = [];

    fragments.forEach(function (fragment) {
      if (!fragment || typeof fragment.selector !== 'string' || fragment.selector.trim() === '') {
        return;
      }

      var selector = fragment.selector.trim();
      var mode = typeof fragment.mode === 'string' ? fragment.mode : 'replace';
      var html = fragment.html;
      var targets = Array.prototype.slice.call(document.querySelectorAll(selector));

      targets.forEach(function (target) {
        if (!target) {
          return;
        }

        var inserted = [];

        switch (mode) {
          case 'replace':
            if (!target.parentNode) {
              return;
            }
            inserted = transferFragment(target.parentNode, target, fragmentFromHtml(html));
            target.parentNode.removeChild(target);
            break;
          case 'replaceChildren':
            target.innerHTML = typeof html === 'string' ? html : '';
            inserted = [target];
            break;
          case 'append':
            inserted = transferFragment(target, null, fragmentFromHtml(html));
            if (!inserted.length) {
              inserted = [target];
            }
            break;
          case 'prepend':
            inserted = transferFragment(target, target.firstChild, fragmentFromHtml(html));
            if (!inserted.length) {
              inserted = [target];
            }
            break;
          case 'remove':
            if (target.parentNode) {
              target.parentNode.removeChild(target);
            }
            inserted = [];
            break;
          case 'text':
            target.textContent = typeof html === 'string' ? html : '';
            inserted = [target];
            break;
          default:
            target.innerHTML = typeof html === 'string' ? html : '';
            inserted = [target];
            break;
        }

        inserted.forEach(function (node) {
          if (node && node.nodeType === Node.ELEMENT_NODE && touchedRoots.indexOf(node) === -1) {
            touchedRoots.push(node);
          }
        });
      });
    });

    return touchedRoots;
  }

  function dispatchNavigated(url, options) {
    var detail = {
      url: url,
      root: options && options.root ? options.root : document,
      initial: !!(options && options.initial),
      source: options && options.source ? options.source : 'navigation'
    };
    try {
      document.dispatchEvent(new CustomEvent('cms:admin:navigated', { detail: detail }));
    } catch (err) {
      try {
        var legacyEvent = document.createEvent('CustomEvent');
        legacyEvent.initCustomEvent('cms:admin:navigated', true, true, detail);
        document.dispatchEvent(legacyEvent);
      } catch (legacyError) {
        try {
          var fallback = document.createEvent('Event');
          fallback.initEvent('cms:admin:navigated', true, true);
          fallback.detail = detail;
          document.dispatchEvent(fallback);
        } catch (ignored) {
          /* noop */
        }
      }
    }
  }

  function syncDocumentMeta(doc) {
    if (!doc) return;
    var newTitle = doc.querySelector('title');
    if (newTitle) {
      document.title = newTitle.textContent || document.title;
    }
    var incomingHtml = doc.documentElement;
    if (incomingHtml) {
      var lang = incomingHtml.getAttribute('lang');
      if (lang) {
        document.documentElement.setAttribute('lang', lang);
      }
      var theme = incomingHtml.getAttribute('data-bs-theme');
      if (theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
      }
    }
    var incomingBody = doc.body;
    if (incomingBody) {
      document.body.className = incomingBody.className || '';
    }
  }

  function replaceAdminShell(doc) {
    if (!doc) {
      return null;
    }
    var newWrapper = doc.querySelector('.admin-wrapper');
    var currentWrapper = document.querySelector('.admin-wrapper');
    if (!newWrapper || !currentWrapper) {
      return null;
    }
    currentWrapper.replaceWith(newWrapper);
    return newWrapper;
  }

  function setSidebarOpen(open) {
    if (open) {
      document.body.classList.add('admin-sidebar-open');
    } else {
      document.body.classList.remove('admin-sidebar-open');
    }
    var toggles = [].slice.call(document.querySelectorAll('[data-admin-menu-toggle]'));
    toggles.forEach(function (btn) {
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  function bindSidebarMediaQuery() {
    if (adminMenuMediaQuery) {
      return;
    }
    if (typeof window.matchMedia !== 'function') {
      return;
    }
    var mq = window.matchMedia('(min-width: 993px)');
    var handler = function (event) {
      if (event && event.matches) {
        setSidebarOpen(false);
      }
    };
    if (typeof mq.addEventListener === 'function') {
      mq.addEventListener('change', handler);
    } else if (typeof mq.addListener === 'function') {
      mq.addListener(handler);
    }
    adminMenuMediaQuery = { mq: mq, handler: handler };
  }

  function initAdminMenuToggle(root) {
    var scope = root || document;
    var toggles = [].slice.call(scope.querySelectorAll('[data-admin-menu-toggle]'));
    if (toggles.length) {
      bindSidebarMediaQuery();
    }
    toggles.forEach(function (btn) {
      if (btn.dataset.adminMenuToggle === '1') {
        return;
      }
      btn.dataset.adminMenuToggle = '1';
      btn.addEventListener('click', function (event) {
        event.preventDefault();
        var isOpen = document.body.classList.contains('admin-sidebar-open');
        setSidebarOpen(!isOpen);
      });
    });

    var backdrop = document.querySelector('[data-admin-menu-backdrop]');
    if (backdrop && backdrop.dataset.adminMenuBackdrop !== '1') {
      backdrop.dataset.adminMenuBackdrop = '1';
      backdrop.addEventListener('click', function () {
        setSidebarOpen(false);
      });
    }

    var sidebar = document.querySelector('.admin-sidebar');
    if (sidebar && sidebar.dataset.adminMenuSidebar !== '1') {
      sidebar.dataset.adminMenuSidebar = '1';
      sidebar.addEventListener('click', function (evt) {
        var link = evt.target && evt.target.closest ? evt.target.closest('a') : null;
        if (link) {
          setSidebarOpen(false);
        }
      });
    }

    if (!document.documentElement.dataset.adminMenuGlobalBound) {
      document.documentElement.dataset.adminMenuGlobalBound = '1';
      document.addEventListener('keydown', function (evt) {
        if (evt.key === 'Escape') {
          setSidebarOpen(false);
        }
      });
      document.addEventListener('cms:admin:navigated', function () {
        setSidebarOpen(false);
      });
    }

    var sectionButtons = [].slice.call(scope.querySelectorAll('.admin-menu-item.has-children > .admin-menu-link[data-admin-menu-section]'));
    sectionButtons.forEach(function (btn) {
      if (btn.dataset.adminMenuSectionBound === '1') {
        return;
      }
      btn.dataset.adminMenuSectionBound = '1';
      var parent = btn.closest('.admin-menu-item');
      if (parent) {
        btn.setAttribute('aria-expanded', parent.classList.contains('is-expanded') ? 'true' : 'false');
      }
      btn.addEventListener('click', function (event) {
        event.preventDefault();
        var item = btn.closest('.admin-menu-item');
        if (!item) {
          return;
        }
        var expanded = item.classList.toggle('is-expanded');
        btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      });
    });

    setSidebarOpen(document.body.classList.contains('admin-sidebar-open'));
  }

  function ensureConfirmModal() {
    if (confirmModalElement) {
      return confirmModalElement;
    }
    var element = document.getElementById('adminConfirmModal');
    if (!element) {
      return null;
    }
    if (typeof bootstrap === 'undefined' || typeof bootstrap.Modal !== 'function') {
      return null;
    }
    confirmModalElement = element;
    confirmModalInstance = bootstrap.Modal.getOrCreateInstance(element);
    var confirmButton = element.querySelector('[data-confirm-modal-confirm]');
    var cancelButton = element.querySelector('[data-confirm-modal-cancel]');
    if (confirmButton && !confirmButton.dataset.defaultLabel) {
      confirmButton.dataset.defaultLabel = confirmButton.textContent || '';
    }
    if (cancelButton && !cancelButton.dataset.defaultLabel) {
      cancelButton.dataset.defaultLabel = cancelButton.textContent || '';
    }
    if (confirmButton && confirmButton.dataset.confirmModalBound !== '1') {
      confirmButton.dataset.confirmModalBound = '1';
      confirmButton.addEventListener('click', function () {
        if (confirmModalInstance) {
          confirmModalInstance.hide();
        }
        if (confirmModalCallback) {
          var callback = confirmModalCallback;
          confirmModalCallback = null;
          callback(true);
        }
      });
    }
    if (element.dataset.confirmModalHiddenBound !== '1') {
      element.dataset.confirmModalHiddenBound = '1';
      element.addEventListener('hidden.bs.modal', function () {
        if (confirmModalCallback) {
          var callback = confirmModalCallback;
          confirmModalCallback = null;
          callback(false);
        }
      });
    }
    return confirmModalElement;
  }

  function showConfirmModal(options) {
    options = options || {};
    var modalElement = ensureConfirmModal();
    if (!modalElement || !confirmModalInstance) {
      var fallbackMessage = options.message || 'Opravdu chcete pokračovat?';
      if (window.confirm(fallbackMessage)) {
        if (typeof options.onConfirm === 'function') {
          options.onConfirm();
        }
      } else if (typeof options.onCancel === 'function') {
        options.onCancel();
      }
      return;
    }

    var titleEl = modalElement.querySelector('[data-confirm-modal-title]');
    if (titleEl) {
      var titleText = (typeof options.title === 'string' && options.title.trim() !== '')
        ? options.title
        : 'Potvrzení akce';
      titleEl.textContent = titleText;
    }

    var messageEl = modalElement.querySelector('[data-confirm-modal-message]');
    if (messageEl) {
      var messageText = (typeof options.message === 'string' && options.message.trim() !== '')
        ? options.message
        : 'Opravdu chcete pokračovat?';
      messageEl.textContent = messageText;
    }

    var confirmBtn = modalElement.querySelector('[data-confirm-modal-confirm]');
    if (confirmBtn) {
      var confirmLabel = (typeof options.confirmLabel === 'string' && options.confirmLabel.trim() !== '')
        ? options.confirmLabel
        : (confirmBtn.dataset.defaultLabel || confirmBtn.textContent || '');
      confirmBtn.textContent = confirmLabel;
    }

    var cancelBtn = modalElement.querySelector('[data-confirm-modal-cancel]');
    if (cancelBtn) {
      var cancelLabel = (typeof options.cancelLabel === 'string' && options.cancelLabel.trim() !== '')
        ? options.cancelLabel
        : (cancelBtn.dataset.defaultLabel || cancelBtn.textContent || '');
      cancelBtn.textContent = cancelLabel;
    }

    confirmModalCallback = function (confirmed) {
      if (confirmed) {
        if (typeof options.onConfirm === 'function') {
          options.onConfirm();
        }
      } else if (typeof options.onCancel === 'function') {
        options.onCancel();
      }
    };

    confirmModalInstance.show();
  }

  function initConfirmModals(root) {
    var scope = root || document;
    var forms = [].slice.call(scope.querySelectorAll('form[data-confirm-modal]'));
    forms.forEach(function (form) {
      if (form.dataset.confirmModalBound === '1') {
        return;
      }
      form.dataset.confirmModalBound = '1';
      form.addEventListener('submit', function (event) {
        if (form.dataset.confirmModalHandled === '1') {
          delete form.dataset.confirmModalHandled;
          return;
        }
        event.preventDefault();
        var message = form.getAttribute('data-confirm-modal') || '';
        var title = form.getAttribute('data-confirm-modal-title') || '';
        var confirmLabel = form.getAttribute('data-confirm-modal-confirm-label') || '';
        var cancelLabel = form.getAttribute('data-confirm-modal-cancel-label') || '';
        showConfirmModal({
          message: message,
          title: title,
          confirmLabel: confirmLabel,
          cancelLabel: cancelLabel,
          onConfirm: function () {
            form.dataset.confirmModalHandled = '1';
            form.submit();
          }
        });
      });
    });
  }

  function serializeAutosaveData(formData) {
    var pairs = [];
    if (!formData || typeof formData.forEach !== 'function') {
      return '';
    }
    formData.forEach(function (value, key) {
      if (value instanceof File) {
        return;
      }
      if (key === 'csrf' || key === 'thumbnail') {
        return;
      }
      var normalized = value === null || typeof value === 'undefined'
        ? ''
        : String(value);
      pairs.push(key + '=' + normalized);
    });
    pairs.sort();
    return pairs.join('&');
  }

  function hasMeaningfulAutosaveData(form, formData, postId) {
    if (postId) {
      return true;
    }
    if (!formData) {
      return false;
    }

    var title = formData.get ? String(formData.get('title') || '').trim() : '';
    if (title !== '') {
      return true;
    }

    var contentField = form ? form.querySelector('textarea[name="content"]') : null;
    var rawContent = contentField ? contentField.value : (formData.get ? String(formData.get('content') || '') : '');
    if (rawContent) {
      var normalizedContent = rawContent
        .replace(/<[^>]*>/g, ' ')
        .replace(/&nbsp;/gi, ' ')
        .replace(/\s+/g, ' ')
        .trim();
      if (normalizedContent !== '') {
        return true;
      }
    }

    var attachmentsValue = formData.get ? String(formData.get('attached_media') || '').trim() : '';
    if (attachmentsValue !== '' && attachmentsValue !== '[]') {
      return true;
    }

    var thumbnailId = formData.get ? String(formData.get('selected_thumbnail_id') || '').trim() : '';
    if (thumbnailId !== '') {
      return true;
    }

    var hasCategories = false;
    if (formData.getAll) {
      formData.getAll('categories[]').forEach(function (value) {
        if (String(value || '').trim() !== '') {
          hasCategories = true;
        }
      });
      if (hasCategories) {
        return true;
      }

      var hasTags = false;
      formData.getAll('tags[]').forEach(function (value) {
        if (String(value || '').trim() !== '') {
          hasTags = true;
        }
      });
      if (hasTags) {
        return true;
      }
    }

    var newCats = formData.get ? String(formData.get('new_categories') || '').trim() : '';
    if (newCats !== '') {
      return true;
    }

    var newTags = formData.get ? String(formData.get('new_tags') || '').trim() : '';
    if (newTags !== '') {
      return true;
    }

    var commentsInput = form ? form.querySelector('[name="comments_allowed"]') : null;
    if (commentsInput && commentsInput.checked === false) {
      return true;
    }

    var statusValue = formData.get ? String(formData.get('status') || '').trim() : '';
    if (statusValue !== '' && statusValue !== 'draft') {
      return true;
    }

    return false;
  }

  function initPostAutosave(root) {
    var scope = root || document;
    var forms = [].slice.call(scope.querySelectorAll('form[data-autosave-form="1"]'));
    forms.forEach(function (form) {
      if (form.dataset.autosaveBound === '1') {
        return;
      }
      form.dataset.autosaveBound = '1';

      var autosaveUrl = form.getAttribute('data-autosave-url') || '';
      var statusEl = form.querySelector('[data-autosave-status]');
      var statusInput = form.querySelector('input[name="status"]');
      var statusLabelEl = form.querySelector('#status-current-label');
      var idDisplayEl = form.querySelector('[data-post-id-display]');
      var editorTextarea = form.querySelector('textarea[data-content-editor]');

      if (!autosaveUrl) {
        var action = form.getAttribute('action') || '';
        if (action) {
          try {
            var actionUrl = new URL(action, window.location.href);
            actionUrl.searchParams.set('a', 'autosave');
            autosaveUrl = actionUrl.toString();
          } catch (err) {
            autosaveUrl = action;
          }
        }
      }

      var postId = String(form.getAttribute('data-post-id') || '').trim();
      if (postId !== '') {
        form.dataset.postId = postId;
      }

      function getCurrentPostId() {
        return postId !== '' ? postId : '';
      }

      function setCurrentPostId(value) {
        var parsed = parseInt(value, 10);
        if (isNaN(parsed) || parsed <= 0) {
          return false;
        }
        var normalized = String(parsed);
        if (postId === normalized) {
          return false;
        }
        postId = normalized;
        form.setAttribute('data-post-id', postId);
        form.dataset.postId = postId;
        if (idDisplayEl) {
          idDisplayEl.textContent = 'ID #' + postId;
          idDisplayEl.classList.remove('d-none');
        }
        if (editorTextarea) {
          editorTextarea.setAttribute('data-post-id', postId);
        }
        return true;
      }

      function updateStatus(message, isError) {
        if (!statusEl) {
          return;
        }
        statusEl.textContent = message || '';
        if (isError) {
          statusEl.classList.add('text-danger');
          statusEl.classList.remove('text-secondary');
        } else {
          statusEl.classList.remove('text-danger');
          statusEl.classList.add('text-secondary');
        }
      }

      var lastSavedSerialized = serializeAutosaveData((function () {
        var initialData = new FormData(form);
        initialData.delete('thumbnail');
        if (postId !== '') {
          initialData.set('id', postId);
        }
        initialData.set('autosave', '1');
        return initialData;
      })());
      var lastSentSerialized = null;
      var inFlight = false;
      var debounceTimer = null;

      function collectFormData() {
        var formData = new FormData(form);
        formData.delete('thumbnail');
        var currentPostId = getCurrentPostId();
        if (currentPostId) {
          formData.set('id', currentPostId);
        } else {
          formData.delete('id');
        }
        formData.set('autosave', '1');
        return formData;
      }

      function cleanupTimers() {
        if (debounceTimer) {
          window.clearTimeout(debounceTimer);
          debounceTimer = null;
        }
      }

      function attemptAutosave() {
        if (!document.body.contains(form)) {
          cleanup();
          return;
        }
        if (!autosaveUrl) {
          return;
        }
        if (form.classList.contains('is-submitting')) {
          return;
        }
        if (inFlight) {
          return;
        }

        var formData = collectFormData();
        var serialized = serializeAutosaveData(formData);
        var currentPostId = getCurrentPostId();
        var hadPostIdBefore = currentPostId !== '';

        if (!hasMeaningfulAutosaveData(form, formData, currentPostId)) {
          return;
        }

        if (serialized === lastSavedSerialized || serialized === lastSentSerialized) {
          return;
        }

        inFlight = true;
        lastSentSerialized = serialized;

        fetch(autosaveUrl, {
          method: 'POST',
          body: formData,
          credentials: 'same-origin',
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        }).then(function (response) {
          var contentType = response.headers.get('Content-Type') || '';
          if (!response.ok) {
            if (contentType.indexOf('application/json') !== -1) {
              return response.json().then(function (json) {
                var message = json && typeof json.message === 'string' ? json.message : '';
                throw new Error(message || 'Automatické uložení selhalo.');
              });
            }
            return response.text().then(function (text) {
              throw new Error(text || 'Automatické uložení selhalo.');
            });
          }
          if (contentType.indexOf('application/json') !== -1) {
            return response.json();
          }
          return response.text().then(function (text) {
            try {
              return JSON.parse(text);
            } catch (err) {
              return { success: true, message: text };
            }
          });
        }).then(function (payload) {
          var normalized = normalizeAjaxPayload(payload || {});
          var data = normalized.data && typeof normalized.data === 'object' ? normalized.data : {};

          if (!normalized.success) {
            if (data && data.created === false) {
              lastSavedSerialized = serializeAutosaveData(collectFormData());
              lastSentSerialized = lastSavedSerialized;
              return;
            }
            var failMessage = normalized.message || (normalized.errors.length ? normalized.errors.join(' ') : 'Automatické uložení selhalo.');
            throw new Error(failMessage);
          }

          if (data.created === false) {
            lastSavedSerialized = serializeAutosaveData(collectFormData());
            lastSentSerialized = lastSavedSerialized;
            return;
          }

          var assignedPostId = false;
          if (data.postId) {
            assignedPostId = setCurrentPostId(data.postId) || assignedPostId;
          }

          if (!hadPostIdBefore && assignedPostId) {
            var targetUrl = (typeof data.actionUrl === 'string' && data.actionUrl)
              ? data.actionUrl
              : (form.getAttribute('action') || window.location.href);
            return loadAdminPage(targetUrl, { replaceHistory: true });
          }

          if (data.actionUrl) {
            form.setAttribute('action', data.actionUrl);
          }

          if (statusInput && data.status) {
            statusInput.value = data.status;
          }

          if (statusLabelEl && data.statusLabel) {
            statusLabelEl.textContent = data.statusLabel;
          }

          if (data.slug) {
            var slugInput = form.querySelector('input[name="slug"]');
            if (slugInput) {
              slugInput.value = data.slug;
            }
          }

          updateStatus(normalized.message || data.message || 'Automaticky uloženo.', false);

          lastSavedSerialized = serializeAutosaveData(collectFormData());
          lastSentSerialized = lastSavedSerialized;
        }).catch(function (error) {
          lastSentSerialized = null;
          var message = error && error.message ? error.message : 'Automatické uložení selhalo.';
          updateStatus(message, true);
        }).finally(function () {
          inFlight = false;
        });
      }

      function scheduleAutosaveSoon() {
        cleanupTimers();
        debounceTimer = window.setTimeout(function () {
          debounceTimer = null;
          attemptAutosave();
        }, 5000);
      }

      function cleanup() {
        if (intervalHandle) {
          window.clearInterval(intervalHandle);
          intervalHandle = null;
        }
        cleanupTimers();
      }

      var intervalHandle = window.setInterval(attemptAutosave, AUTOSAVE_INTERVAL);

      form.addEventListener('input', function () {
        if (statusEl && statusEl.classList.contains('text-danger')) {
          updateStatus('', false);
        }
        scheduleAutosaveSoon();
      });

      form.addEventListener('change', scheduleAutosaveSoon);

      form.addEventListener('submit', function () {
        cleanup();
      });

      document.addEventListener('cms:admin:navigated', function handler() {
        if (!document.body.contains(form)) {
          cleanup();
          document.removeEventListener('cms:admin:navigated', handler);
        }
      });
    });
  }

  function refreshDynamicUI(root) {
    initFlashMessages(root);
    initTooltips(root);
    initBulkForms(root);
    initConfirmModals(root);
    initPostAutosave(root);
    initAdminMenuToggle(root);
    initNavigationQuickAdd(root);
  }

  function initNavigationQuickAdd(root) {
    var modal = document.getElementById('navigationContentModal');
    if (!modal) {
      return;
    }

    var defaultForm = document.getElementById('navigation-item-form');
    if (defaultForm) {
      initNavigationLinkSourceControls(defaultForm);
    }

    var fillButtons = [].slice.call(modal.querySelectorAll('[data-nav-fill]'));
    fillButtons.forEach(function (btn) {
      if (btn.dataset.navFillBound === '1') {
        return;
      }
      btn.dataset.navFillBound = '1';
      btn.addEventListener('click', function () {
        var targetSelector = btn.getAttribute('data-nav-target') || '#navigation-item-form';
        var form;
        try {
          form = document.querySelector(targetSelector);
        } catch (err) {
          form = document.getElementById('navigation-item-form');
        }
        if (!form) {
          return;
        }

        initNavigationLinkSourceControls(form);

        var titleInput = form.querySelector('[name="title"]');
        var urlInput = form.querySelector('[name="url"]');
        var typeInput = form.querySelector('[name="link_type"]');
        var referenceInput = form.querySelector('[name="link_reference"]');
        var badge = form.querySelector('[data-nav-link-badge]');
        var badgeLabel = form.querySelector('[data-nav-link-type-label]');
        var metaInfo = form.querySelector('[data-nav-link-meta]');
        var warningInfo = form.querySelector('[data-nav-link-warning]');
        var titleValue = btn.getAttribute('data-nav-title') || '';
        var urlValue = btn.getAttribute('data-nav-url') || '';
        var typeValue = btn.getAttribute('data-nav-type') || 'custom';
        var referenceValue = btn.getAttribute('data-nav-reference') || '';

        if (titleInput && typeof titleInput.value !== 'undefined') {
          titleInput.value = titleValue;
        }

        if (urlInput && typeof urlInput.value !== 'undefined') {
          urlInput.value = urlValue;
        }

        if (typeInput && typeof typeInput.value !== 'undefined') {
          typeInput.value = typeValue;
        }

        if (referenceInput && typeof referenceInput.value !== 'undefined') {
          referenceInput.value = referenceValue;
        }

        if (badge && badge.classList) {
          if (typeValue === 'custom') {
            badge.classList.add('d-none');
          } else {
            badge.classList.remove('d-none');
          }
        }

        if (badgeLabel && typeof badgeLabel.textContent !== 'undefined') {
          badgeLabel.textContent = btn.getAttribute('data-nav-type-label') || typeValue;
        }

        if (metaInfo && typeof metaInfo.textContent !== 'undefined') {
          var meta = btn.getAttribute('data-nav-meta') || '';
          if (meta === '' && metaInfo.classList) {
            metaInfo.classList.add('d-none');
          } else {
            metaInfo.textContent = meta;
            if (metaInfo.classList) {
              metaInfo.classList.remove('d-none');
            }
          }
        }

        if (warningInfo && warningInfo.classList) {
          warningInfo.textContent = '';
          warningInfo.classList.add('d-none');
        }

        var clearButtons = [].slice.call(form.querySelectorAll('[data-nav-clear-source]'));
        clearButtons.forEach(function (clearBtn) {
          clearBtn.textContent = typeValue === 'custom' ? 'Přepnout na vlastní URL' : 'Zrušit napojení';
        });

        if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal === 'function') {
          bootstrap.Modal.getOrCreateInstance(modal).hide();
        }

        var focusTarget = titleInput || urlInput;
        if (focusTarget && typeof focusTarget.focus === 'function') {
          try {
            focusTarget.focus();
            if (typeof focusTarget.select === 'function' && focusTarget.value) {
              focusTarget.select();
            }
          } catch (err) {
            /* ignore focus issues */
          }
        }

        var highlightTarget = form;
        if (form && typeof form.closest === 'function') {
          var closestCard = form.closest('.card');
          if (closestCard) {
            highlightTarget = closestCard;
          }
        }

        if (highlightTarget && highlightTarget.classList) {
          highlightTarget.classList.add('navigation-item-filled');
          window.setTimeout(function () {
            highlightTarget.classList.remove('navigation-item-filled');
          }, 1500);
        }
      });
    });
  }

  function initNavigationLinkSourceControls(form) {
    if (!form || form.dataset.navLinkBound === '1') {
      return;
    }
    form.dataset.navLinkBound = '1';

    var typeInput = form.querySelector('[name="link_type"]');
    var referenceInput = form.querySelector('[name="link_reference"]');
    var urlInput = form.querySelector('[name="url"]');
    var badge = form.querySelector('[data-nav-link-badge]');
    var badgeLabel = form.querySelector('[data-nav-link-type-label]');
    var metaInfo = form.querySelector('[data-nav-link-meta]');
    var warningInfo = form.querySelector('[data-nav-link-warning]');

    var clearButtons = [].slice.call(form.querySelectorAll('[data-nav-clear-source]'));
    clearButtons.forEach(function (btn) {
      btn.addEventListener('click', function (event) {
        event.preventDefault();
        if (typeInput && typeof typeInput.value !== 'undefined') {
          typeInput.value = 'custom';
        }
        if (referenceInput && typeof referenceInput.value !== 'undefined') {
          referenceInput.value = '';
        }
        if (badge && badge.classList) {
          badge.classList.add('d-none');
        }
        if (badgeLabel && typeof badgeLabel.textContent !== 'undefined') {
          badgeLabel.textContent = '';
        }
        if (metaInfo && metaInfo.classList) {
          metaInfo.classList.add('d-none');
          metaInfo.textContent = '';
        }
        if (warningInfo && warningInfo.classList) {
          warningInfo.classList.add('d-none');
          warningInfo.textContent = '';
        }
        btn.textContent = 'Přepnout na vlastní URL';
      });
    });

    if (urlInput) {
      urlInput.addEventListener('input', function () {
        if (typeInput && typeof typeInput.value !== 'undefined') {
          typeInput.value = 'custom';
        }
        if (referenceInput && typeof referenceInput.value !== 'undefined') {
          referenceInput.value = '';
        }
        if (badge && badge.classList) {
          badge.classList.add('d-none');
        }
        if (badgeLabel && typeof badgeLabel.textContent !== 'undefined') {
          badgeLabel.textContent = '';
        }
        if (metaInfo && metaInfo.classList) {
          metaInfo.classList.add('d-none');
          metaInfo.textContent = '';
        }
        if (warningInfo && warningInfo.classList) {
          warningInfo.classList.add('d-none');
          warningInfo.textContent = '';
        }
        clearButtons.forEach(function (btn) {
          btn.textContent = 'Přepnout na vlastní URL';
        });
      });
    }
  }

  function loadAdminPage(url, options) {
    options = options || {};
    var targetUrl = normalizeUrl(url);
    if (!targetUrl) {
      return Promise.resolve();
    }

    if (activeNavigation && activeNavigation.controller) {
      activeNavigation.controller.abort();
    }

    var controller = new AbortController();
    activeNavigation = { url: targetUrl, controller: controller };

    document.documentElement.classList.add('is-admin-loading');

    return fetch(targetUrl, {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'text/html,application/xhtml+xml,application/json'
      },
      signal: controller.signal
    }).then(readResponsePayload).then(function (payload) {
      var response = payload.response;
      var data = payload.data;

      if (!response.ok) {
        var message = extractMessageFromData(data, 'Nepodařilo se načíst stránku (' + response.status + ').');
        var error = new Error(message);
        error.status = response.status;
        throw error;
      }

      var contentType = response.headers ? (response.headers.get('Content-Type') || '') : '';
      var isHtmlResponse = contentType.toLowerCase().indexOf('text/html') !== -1;

      if (payload.isJson && data && typeof data === 'object') {
        if (typeof data.redirect === 'string' && data.redirect !== '') {
          return loadAdminPage(data.redirect, { pushState: true });
        }
        if (typeof data.reload === 'boolean' && data.reload) {
          return loadAdminPage(targetUrl, { replaceHistory: true });
        }
      }

      var html = null;
      if (isHtmlResponse && typeof payload.text === 'string') {
        html = payload.text;
      } else if (!payload.isJson && typeof payload.text === 'string') {
        html = payload.text;
      } else if (payload.isJson && data && typeof data.html === 'string') {
        html = data.html;
      } else if (payload.isJson && data && typeof data.raw === 'string') {
        html = data.raw;
      }

      var appliedRoot = applyAdminHtml(typeof html === 'string' ? html : '');
      if (!appliedRoot) {
        window.location.href = targetUrl;
        return;
      }

      if (options.replaceHistory) {
        window.history.replaceState(buildHistoryState(), '', targetUrl);
      } else if (options.pushState && targetUrl !== window.location.href) {
        window.history.pushState(buildHistoryState(), '', targetUrl);
      } else if (!window.history.state || !window.history.state[HISTORY_STATE_KEY]) {
        window.history.replaceState(buildHistoryState(), '', targetUrl);
      } else {
        window.history.replaceState(buildHistoryState(window.history.state), '', targetUrl);
      }

      if (options.scroll !== false) {
        try {
          window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
        } catch (err) {
          window.scrollTo(0, 0);
        }
      }

      dispatchNavigated(targetUrl, { source: options.fromPopstate ? 'history' : 'navigation', root: appliedRoot });
    }).catch(function (error) {
      if (error && error.name === 'AbortError') {
        return;
      }
      console.error(error);
      window.location.href = targetUrl;
    }).finally(function () {
      if (activeNavigation && activeNavigation.controller === controller) {
        activeNavigation = null;
      }
      document.documentElement.classList.remove('is-admin-loading');
    });
  }

  function ajaxFormRequest(form, submitter) {
    var method = (form.getAttribute('method') || 'GET').toUpperCase();
    var action = form.getAttribute('action') || window.location.href;
    var ajaxActionName = form.getAttribute('data-action');
    if (ajaxActionName) {
      var endpointAttr = form.getAttribute('data-endpoint') || action;
      if (!endpointAttr || endpointAttr.indexOf('admin-ajax.php') === -1) {
        endpointAttr = 'admin-ajax.php';
      }
      var normalizedEndpoint = normalizeUrl(endpointAttr);
      var ajaxUrl;
      try {
        ajaxUrl = new URL(normalizedEndpoint);
      } catch (err) {
        ajaxUrl = new URL(normalizedEndpoint, window.location.href);
      }
      ajaxUrl.searchParams.set('action', ajaxActionName);
      action = ajaxUrl.toString();
    }
    var restoreDisabled = false;
    var shouldResetOnSuccess = shouldResetFormOnSuccess(form);
    var formShouldReset = false;

    if (submitter && typeof submitter.disabled === 'boolean' && !submitter.disabled) {
      submitter.disabled = true;
      restoreDisabled = true;
    }

    form.classList.add('is-submitting');

    if (method === 'GET') {
      var params = new URLSearchParams();
      var formData = new FormData(form);
      formData.forEach(function (value, key) {
        params.append(key, value);
      });
      if (submitter && submitter.name) {
        params.append(submitter.name, submitter.value);
      }
      var url = normalizeUrl(action);
      var urlObj;
      try {
        urlObj = new URL(url);
      } catch (err) {
        urlObj = new URL(window.location.href);
      }
      urlObj.search = params.toString();
      return loadAdminPage(urlObj.toString(), { pushState: true }).finally(function () {
        form.classList.remove('is-submitting');
        if (submitter && restoreDisabled) {
          submitter.disabled = false;
        }
      });
    }

    var formData = new FormData(form);
    if (submitter && submitter.name) {
      formData.append(submitter.name, submitter.value);
    }

    return fetch(action, {
      method: method,
      body: formData,
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    }).then(readResponsePayload).then(function (payload) {
      var response = payload.response;
      var data = payload.data;
      var normalizedResponse = null;
      var normalizedMessageShown = false;
      var fragmentRoots = [];

      if (!response.ok) {
        var message = extractMessageFromData(data, 'Došlo k chybě (' + response.status + ')');
        var error = new Error(message);
        error.__handled = true;
        showFlashMessage('danger', message, form);
        throw error;
      }

      var contentType = response.headers ? (response.headers.get('Content-Type') || '') : '';
      var isHtmlResponse = contentType.toLowerCase().indexOf('text/html') !== -1;

      if (payload.isJson && data && typeof data === 'object' && typeof data.success === 'boolean') {
        normalizedResponse = normalizeAjaxPayload(data);
        if (!normalizedResponse.success) {
          var normalizedErrorMessage = normalizedResponse.message || (normalizedResponse.errors.length ? normalizedResponse.errors.join(' ') : 'Došlo k chybě.');
          var normalizedError = new Error(normalizedErrorMessage);
          normalizedError.__handled = true;
          showFlashMessage('danger', normalizedErrorMessage, form);
          throw normalizedError;
        }
        if (normalizedResponse.message) {
          showFlashMessage('success', normalizedResponse.message, form);
          normalizedMessageShown = true;
        }
        data = normalizedResponse.data && typeof normalizedResponse.data === 'object'
          ? normalizedResponse.data
          : {};
      }

      if (isHtmlResponse && typeof payload.text === 'string') {
        var formAppliedRoot = applyAdminHtml(payload.text);
        if (!formAppliedRoot) {
          window.location.reload();
          return Promise.resolve();
        }
        window.history.replaceState(buildHistoryState(window.history.state), '', window.location.href);
        dispatchNavigated(window.location.href, { source: 'form', root: formAppliedRoot });
        return Promise.resolve();
      }

      if (payload.isJson && data && typeof data === 'object') {
        if (typeof data.html === 'string' && data.html !== '') {
          var formAppliedRoot = applyAdminHtml(data.html);
          if (!formAppliedRoot) {
            window.location.reload();
            return Promise.resolve();
          }
          window.history.replaceState(buildHistoryState(window.history.state), '', window.location.href);
          dispatchNavigated(window.location.href, { source: 'form', root: formAppliedRoot });
          return Promise.resolve();
        }
        if (Array.isArray(data.fragments) && data.fragments.length) {
          fragmentRoots = applyAjaxFragments(data.fragments);
        }
      } else if (typeof payload.text === 'string' && payload.text.trim() !== '') {
        var trimmed = payload.text.trim();
        if (trimmed.charAt(0) === '<') {
          var htmlAppliedRoot = applyAdminHtml(payload.text);
          if (htmlAppliedRoot) {
            window.history.replaceState(buildHistoryState(window.history.state), '', window.location.href);
            dispatchNavigated(window.location.href, { source: 'form', root: htmlAppliedRoot });
            return Promise.resolve();
          }
        }
      }

      var flash = data && typeof data === 'object' ? data.flash : null;
      if (flash && typeof flash === 'object') {
        showFlashMessage(flash.type, flash.msg, form);
      }

      if (payload.isJson && data && typeof data === 'object') {
        if (typeof data.redirect === 'string' && data.redirect !== '') {
          return loadAdminPage(data.redirect, { pushState: true });
        }
        if (typeof data.reload === 'boolean' && data.reload) {
          return loadAdminPage(window.location.href, { replaceHistory: true });
        }
        if (!flash && !normalizedMessageShown && typeof data.message === 'string' && data.message) {
          showFlashMessage(data.success === false ? 'danger' : 'info', data.message, form);
        }
        if (fragmentRoots.length) {
          var fragmentRoot = fragmentRoots.length === 1 ? fragmentRoots[0] : document;
          dispatchNavigated(window.location.href, { source: 'fragment', root: fragmentRoot });
        } else if (Array.isArray(data.fragments) && data.fragments.length) {
          dispatchNavigated(window.location.href, { source: 'fragment', root: document });
        }
      } else if (!flash && typeof payload.text === 'string' && payload.text.trim() !== '') {
        showFlashMessage('info', payload.text.trim(), form);
      }

      if (shouldResetOnSuccess) {
        formShouldReset = true;
      }

      return Promise.resolve();
    }).then(function (result) {
      if (formShouldReset && typeof form.reset === 'function') {
        try {
          form.reset();
        } catch (err) {
          /* ignore reset errors */
        }
      }
      return result;
    }).catch(function (error) {
      if (!error || !error.__handled) {
        if (error && error.name === 'AbortError') {
          return;
        }
        var msg = (error && error.message) ? error.message : 'Došlo k neočekávané chybě.';
        showFlashMessage('danger', msg, form);
      }
    }).finally(function () {
      form.classList.remove('is-submitting');
      if (submitter && restoreDisabled) {
        submitter.disabled = false;
      }
    });
  }

  function initAjaxForms() {
    document.addEventListener('submit', function (event) {
      var form = event.target;
      if (!(form instanceof HTMLFormElement)) {
        return;
      }
      if (!isAjaxForm(form)) {
        return;
      }
      if (event.defaultPrevented) {
        return;
      }

      event.preventDefault();
      ajaxFormRequest(form, event.submitter || null);
    });
  }

  function initTooltips(root) {
    if (typeof bootstrap === 'undefined' || typeof bootstrap.Tooltip !== 'function') {
      return;
    }
    var scope = root || document;
    var tooltipElements = [].slice.call(scope.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipElements.forEach(function (el) {
      if (typeof bootstrap.Tooltip.getOrCreateInstance === 'function') {
        bootstrap.Tooltip.getOrCreateInstance(el);
      } else {
        new bootstrap.Tooltip(el);
      }
    });
  }

  function initBulkForms(root) {
    var scope = root || document;
    var forms = [].slice.call(scope.querySelectorAll('[data-bulk-form]'));
    forms.forEach(function (form) {
      if (form.dataset.bulkInitialized === '1') {
        return;
      }
      form.dataset.bulkInitialized = '1';

      var selectAllSelector = form.getAttribute('data-select-all');
      var rowSelector = form.getAttribute('data-row-checkbox');
      var applySelector = form.getAttribute('data-apply-button');
      var actionSelector = form.getAttribute('data-action-select');
      var counterSelector = form.getAttribute('data-counter');

      var selectAll = selectAllSelector ? document.querySelector(selectAllSelector) : null;
      var checkboxes = rowSelector ? [].slice.call(document.querySelectorAll(rowSelector)) : [];
      var applyButton = applySelector ? document.querySelector(applySelector) : null;
      var actionSelect = actionSelector ? document.querySelector(actionSelector) : null;
      var counter = counterSelector ? document.querySelector(counterSelector) : null;

      function updateState() {
        var checked = checkboxes.filter(function (cb) { return cb.checked; });
        var count = checked.length;
        if (selectAll) {
          selectAll.checked = count > 0 && count === checkboxes.length;
          selectAll.indeterminate = count > 0 && count < checkboxes.length;
        }
        if (applyButton) {
          applyButton.disabled = count === 0 || (actionSelect && actionSelect.value === '');
        }
        if (counter) {
          counter.textContent = count > 0 ? ('Vybráno ' + count + ' položek') : '';
        }
      }

      if (selectAll) {
        selectAll.addEventListener('change', function () {
          var isChecked = selectAll.checked;
          checkboxes.forEach(function (cb) { cb.checked = isChecked; });
          updateState();
        });
      }

      checkboxes.forEach(function (cb) {
        cb.addEventListener('change', updateState);
      });

      if (actionSelect) {
        actionSelect.addEventListener('change', updateState);
      }

      form.addEventListener('submit', function (evt) {
        if (applyButton && applyButton.disabled) {
          evt.preventDefault();
        }
      });

      updateState();
    });
  }

  function shouldHandleLink(event, link) {
    if (!link || typeof link.getAttribute !== 'function') {
      return false;
    }
    if (link.hasAttribute('data-no-ajax')) {
      return false;
    }
    if (link.target && link.target !== '_self') {
      return false;
    }
    if (link.hasAttribute('download')) {
      return false;
    }
    var href = link.getAttribute('href');
    if (!href || href.trim() === '' || href.trim().charAt(0) === '#') {
      return false;
    }
    if (link.getAttribute('rel') && link.getAttribute('rel').toLowerCase().indexOf('external') !== -1) {
      return false;
    }
    var url;
    try {
      url = new URL(link.href, window.location.href);
    } catch (e) {
      return false;
    }
    if (url.origin !== window.location.origin) {
      return false;
    }
    if (url.pathname !== window.location.pathname) {
      return false;
    }
    if (link.dataset && link.dataset.noAjax === 'true') {
      return false;
    }
    return true;
  }

  function initAjaxLinks() {
    document.addEventListener('click', function (event) {
      if (event.defaultPrevented) {
        return;
      }
      if (event.button !== 0) {
        return;
      }
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
      }

      var link = event.target && event.target.closest ? event.target.closest('a') : null;
      if (!shouldHandleLink(event, link)) {
        return;
      }

      event.preventDefault();
      loadAdminPage(link.href, { pushState: true });
    });
  }

  function bootHistory() {
    if (!window.history.state || !window.history.state[HISTORY_STATE_KEY]) {
      window.history.replaceState(buildHistoryState(), '', window.location.href);
    }
    window.addEventListener('popstate', function (event) {
      if (event.state && event.state[HISTORY_STATE_KEY]) {
        loadAdminPage(window.location.href, { replaceHistory: true, fromPopstate: true });
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var initialRoot = document.querySelector('.admin-wrapper') || document;
    refreshDynamicUI(initialRoot);
    initAjaxForms();
    initAjaxLinks();
    bootHistory();
    dispatchNavigated(window.location.href, { initial: true, source: 'initial', root: initialRoot });
  });

  window.cmsAdmin = {
    load: loadAdminPage,
    refresh: refreshDynamicUI
  };
})();
