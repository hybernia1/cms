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
  var formHelperRegistry = Object.create(null);
  var formHelperInstances = new WeakMap();
  var bulkFormStateUpdaters = new WeakMap();

  function isAjaxForm(el) {
    return el && el.hasAttribute && el.hasAttribute('data-ajax');
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

  var notifier = {
    notify: function (type, message, context) {
      if (!message) {
        return;
      }
      showFlashMessage(type, message, context);
    },
    danger: function (message, context) {
      notifier.notify('danger', message, context);
    },
    info: function (message, context) {
      notifier.notify('info', message, context);
    },
    success: function (message, context) {
      notifier.notify('success', message, context);
    },
    warning: function (message, context) {
      notifier.notify('warning', message, context);
    }
  };

  function registerFormHelper(name, factory) {
    var key = typeof name === 'string' ? name.trim() : '';
    if (!key) {
      throw new Error('form helper name must be a non-empty string');
    }
    if (typeof factory !== 'function') {
      throw new Error('form helper "' + key + '" must be a function');
    }
    formHelperRegistry[key] = factory;
  }

  function unregisterFormHelper(name) {
    if (typeof name !== 'string') {
      return;
    }
    var key = name.trim();
    if (key) {
      delete formHelperRegistry[key];
    }
  }

  function initFormHelpers(root) {
    var scope = root || document;
    if (!scope || typeof scope.querySelectorAll !== 'function') {
      return;
    }
    var forms = [].slice.call(scope.querySelectorAll('form[data-form-helper]'));
    forms.forEach(function (form) {
      var helperName = (form.getAttribute('data-form-helper') || '').trim();
      if (!helperName) {
        return;
      }
      var current = formHelperInstances.get(form);
      if (current && current.name === helperName) {
        return;
      }
      if (current && typeof current.cleanup === 'function') {
        try {
          current.cleanup();
        } catch (cleanupError) {
          console.error('form helper cleanup failed for "' + current.name + '"', cleanupError);
        }
      }
      var helper = formHelperRegistry[helperName];
      if (typeof helper !== 'function') {
        return;
      }
      var cleanup = null;
      try {
        cleanup = helper(form) || null;
      } catch (err) {
        console.error('form helper "' + helperName + '" failed to initialize', err);
        cleanup = null;
      }
      formHelperInstances.set(form, { name: helperName, cleanup: cleanup });
    });
  }

  function hideFeedbackElement(element) {
    if (!element) {
      return;
    }
    element.textContent = '';
    element.setAttribute('hidden', 'true');
    if (element.classList) {
      element.classList.remove('d-block');
    }
  }

  function getFormControls(form, field) {
    if (!form || !form.elements) {
      return [];
    }
    var controls = null;
    if (typeof form.elements.namedItem === 'function') {
      controls = form.elements.namedItem(field);
    }
    if (!controls && Object.prototype.hasOwnProperty.call(form.elements, field)) {
      controls = form.elements[field];
    }
    if (!controls) {
      return [];
    }
    if (typeof RadioNodeList !== 'undefined' && controls instanceof RadioNodeList) {
      return controls.length ? [].slice.call(controls) : [];
    }
    if (Array.isArray(controls)) {
      return controls;
    }
    if (typeof controls.length === 'number' && typeof controls.item === 'function' && !controls.tagName) {
      return controls.length ? [].slice.call(controls) : [];
    }
    return [controls];
  }

  function getFieldFeedbackElements(form, field) {
    if (!form || typeof form.querySelectorAll !== 'function') {
      return [];
    }
    var nodes = [].slice.call(form.querySelectorAll('[data-error-for]'));
    return nodes.filter(function (node) {
      if (!node.dataset) {
        return false;
      }
      return node.dataset.errorFor === field;
    });
  }

  function findGeneralFeedbackElement(form) {
    if (!form || typeof form.querySelectorAll !== 'function') {
      return null;
    }
    var nodes = [].slice.call(form.querySelectorAll('[data-error-for]'));
    for (var i = 0; i < nodes.length; i += 1) {
      var node = nodes[i];
      if (!node || !node.dataset) {
        continue;
      }
      var target = node.dataset.errorFor;
      if (target === 'form' || target === '*' || target === '') {
        return node;
      }
    }
    return null;
  }

  function resetControlValidation(control) {
    if (!control || !control.classList) {
      return;
    }
    control.classList.remove('is-invalid');
    if (typeof control.removeAttribute === 'function') {
      control.removeAttribute('aria-invalid');
      if (control.hasAttribute('data-prev-aria-describedby')) {
        var prev = control.getAttribute('data-prev-aria-describedby') || '';
        if (prev) {
          control.setAttribute('aria-describedby', prev);
        } else {
          control.removeAttribute('aria-describedby');
        }
        control.removeAttribute('data-prev-aria-describedby');
      }
    }
  }

  function clearFieldError(form, field) {
    var controls = getFormControls(form, field);
    controls.forEach(resetControlValidation);
    var feedbacks = getFieldFeedbackElements(form, field);
    feedbacks.forEach(hideFeedbackElement);
  }

  function clearFormValidation(form) {
    if (!form) {
      return;
    }
    var invalidControls = [].slice.call(form.querySelectorAll('.is-invalid'));
    invalidControls.forEach(resetControlValidation);
    var feedbacks = [].slice.call(form.querySelectorAll('[data-error-for]'));
    feedbacks.forEach(hideFeedbackElement);
  }

  function normalizeValidationMessages(value) {
    if (Array.isArray(value)) {
      var normalized = [];
      value.forEach(function (item) {
        if (item === null || item === undefined) {
          return;
        }
        var text = String(item).trim();
        if (text) {
          normalized.push(text);
        }
      });
      return normalized;
    }
    if (value === null || value === undefined) {
      return [];
    }
    var stringValue = String(value).trim();
    return stringValue ? [stringValue] : [];
  }

  function showGeneralFormError(form, messages) {
    if (!messages || !messages.length) {
      return;
    }
    var element = findGeneralFeedbackElement(form);
    if (!element) {
      return;
    }
    element.textContent = messages.join(' ');
    element.removeAttribute('hidden');
    if (element.classList) {
      element.classList.add('d-block');
    }
  }

  function applyFormValidationErrors(form, errors) {
    if (!form || !errors || typeof errors !== 'object') {
      return;
    }
    var focusTarget = null;
    Object.keys(errors).forEach(function (field) {
      if (!Object.prototype.hasOwnProperty.call(errors, field)) {
        return;
      }
      var messages = normalizeValidationMessages(errors[field]);
      if (!messages.length) {
        return;
      }
      if (field === 'form' || field === '*' || field === '') {
        showGeneralFormError(form, messages);
        return;
      }
      var controls = getFormControls(form, field);
      var feedbacks = getFieldFeedbackElements(form, field);
      var feedback = feedbacks.length ? feedbacks[0] : null;
      if (feedback) {
        feedback.textContent = messages.join(' ');
        feedback.removeAttribute('hidden');
        if (feedback.classList) {
          feedback.classList.add('d-block');
        }
      }
      controls.forEach(function (control) {
        if (!control || !control.classList) {
          return;
        }
        control.classList.add('is-invalid');
        control.setAttribute('aria-invalid', 'true');
        if (feedback && feedback.id) {
          if (!control.hasAttribute('data-prev-aria-describedby')) {
            control.setAttribute('data-prev-aria-describedby', control.getAttribute('aria-describedby') || '');
          }
          var describedBy = control.getAttribute('aria-describedby') || '';
          var tokens = describedBy.split(/\s+/).filter(Boolean);
          if (tokens.indexOf(feedback.id) === -1) {
            tokens.push(feedback.id);
            control.setAttribute('aria-describedby', tokens.join(' '));
          }
        }
        if (!focusTarget && typeof control.focus === 'function') {
          focusTarget = control;
        }
      });
    });
    if (focusTarget) {
      try {
        focusTarget.focus();
      } catch (focusError) {
        /* ignore focus errors */
      }
    }
  }

  registerFormHelper('validation', function (form) {
    function handleInput(event) {
      var target = event && event.target ? event.target : null;
      if (!target || !target.name) {
        return;
      }
      clearFieldError(form, target.name);
      var general = findGeneralFeedbackElement(form);
      if (general) {
        hideFeedbackElement(general);
      }
    }

    form.addEventListener('input', handleInput);
    form.addEventListener('change', handleInput);

    return function () {
      form.removeEventListener('input', handleInput);
      form.removeEventListener('change', handleInput);
    };
  });

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
        doc.documentElement.innerHTML = html;
      } catch (e) {
        doc.body.innerHTML = html;
      }
      return doc;
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

  function extractMessageFromData(data, fallback) {
    var message = fallback || '';
    if (!data || typeof data !== 'object') {
      return message;
    }
    if (typeof data.error === 'string' && data.error.trim() !== '') {
      return data.error.trim();
    }
    if (typeof data.message === 'string' && data.message.trim() !== '') {
      return data.message.trim();
    }
    if (typeof data.raw === 'string' && data.raw.trim() !== '') {
      return data.raw.trim();
    }
    return message;
  }

  function buildValidationMessage(errors) {
    if (!errors) {
      return '';
    }
    if (typeof errors === 'string') {
      return errors;
    }
    if (Array.isArray(errors)) {
      return errors.map(function (item) {
        return typeof item === 'string' ? item : '';
      }).filter(Boolean).join('\n');
    }
    if (typeof errors === 'object') {
      var parts = [];
      Object.keys(errors).forEach(function (key) {
        var value = errors[key];
        if (!value) {
          return;
        }
        if (Array.isArray(value)) {
          value.forEach(function (entry) {
            if (entry) {
              parts.push(entry);
            }
          });
        } else if (typeof value === 'string') {
          parts.push(value);
        }
      });
      return parts.join('\n');
    }
    return '';
  }

  function applyPartialContent(target, html) {
    if (!target || typeof html !== 'string') {
      return null;
    }
    var element = null;
    try {
      element = document.querySelector(target);
    } catch (err) {
      /* ignore invalid selector */
    }
    if (!element) {
      element = document.querySelector('[data-partial="' + target + '"]');
    }
    if (!element) {
      return null;
    }
    element.innerHTML = html;
    executeScripts(element);
    refreshDynamicUI(element);
    return element;
  }

  function applyPartials(partials) {
    if (!partials) {
      return [];
    }
    var applied = [];
    if (Array.isArray(partials)) {
      partials.forEach(function (entry) {
        if (!entry || typeof entry !== 'object') {
          return;
        }
        var selector = entry.selector || entry.target || entry.key || entry.name;
        var html = entry.html || entry.content || entry.markup;
        var element = applyPartialContent(selector, html);
        if (element) {
          applied.push({ selector: selector, element: element });
        }
      });
      return applied;
    }
    if (typeof partials === 'object') {
      Object.keys(partials).forEach(function (selector) {
        var element = applyPartialContent(selector, partials[selector]);
        if (element) {
          applied.push({ selector: selector, element: element });
        }
      });
    }
    return applied;
  }

  var ajaxHandlers = Object.create(null);

  function registerAjaxHandler(name, handler) {
    if (typeof name !== 'string' || !name) {
      throw new Error('admin.ajax handler name must be a non-empty string');
    }
    if (typeof handler !== 'function') {
      throw new Error('admin.ajax handler for "' + name + '" must be a function');
    }
    ajaxHandlers[name] = handler;
  }

  function unregisterAjaxHandler(name) {
    if (typeof name !== 'string' || !name) {
      return;
    }
    delete ajaxHandlers[name];
  }

  function executeAjaxHandlers(definitions, meta) {
    if (!definitions) {
      return;
    }
    var entries = [];
    if (Array.isArray(definitions)) {
      entries = definitions;
    } else if (typeof definitions === 'object') {
      Object.keys(definitions).forEach(function (key) {
        entries.push({ name: key, payload: definitions[key] });
      });
    } else if (typeof definitions === 'string') {
      entries = [{ name: definitions }];
    }
    entries.forEach(function (entry) {
      if (!entry) {
        return;
      }
      var handlerName = entry.name || entry.module || entry.key;
      if (!handlerName || typeof handlerName !== 'string') {
        return;
      }
      var handler = ajaxHandlers[handlerName];
      if (typeof handler !== 'function') {
        return;
      }
      try {
        handler(entry.payload !== undefined ? entry.payload : entry.data !== undefined ? entry.data : entry, meta || {});
      } catch (err) {
        console.error('admin.ajax handler "' + handlerName + '" failed', err);
      }
    });
  }

  function handleHttpErrorPayload(payload, options) {
    var response = payload.response;
    var data = payload.data;
    var message = extractMessageFromData(data, 'Došlo k chybě (' + response.status + ')');
    if (response.status === 422 && data && data.errors) {
      var validationMessage = buildValidationMessage(data.errors);
      if (validationMessage) {
        message = validationMessage;
      }
    }
    notifier.danger(message, options.context || null);
    var error = new Error(message);
    error.status = response.status;
    error.data = data;
    if (data && data.errors) {
      error.validationErrors = data.errors;
    }
    error.__handled = true;
    throw error;
  }

  function handlePayload(url, payload, options) {
    var response = payload.response;
    if (!response.ok) {
      return handleHttpErrorPayload(payload, options || {});
    }

    var opts = options || {};
    var context = opts.context || null;
    var result = {
      url: url,
      response: response,
      payload: payload,
      data: payload.isJson ? payload.data : null,
      htmlRoot: null,
      redirected: false,
      reloaded: false,
      partials: []
    };

    if (payload.isJson) {
      var data = payload.data;
      if (data && typeof data === 'object') {
        if (typeof data.redirect === 'string' && data.redirect !== '') {
          result.redirected = true;
          if (typeof opts.onRedirect === 'function') {
            opts.onRedirect(data.redirect, data, payload);
          } else {
            loadAdminPage(data.redirect, { pushState: true });
          }
          return result;
        }
        if (typeof data.reload === 'boolean' && data.reload) {
          result.reloaded = true;
          if (typeof opts.onReload === 'function') {
            opts.onReload(data, payload);
          } else {
            loadAdminPage(window.location.href, { replaceHistory: true });
          }
        }
        if (data.flash && typeof data.flash === 'object') {
          notifier.notify(data.flash.type || 'info', data.flash.msg, context);
        } else if (typeof data.message === 'string' && data.message) {
          notifier.notify(data.success === false ? 'danger' : 'info', data.message, context);
        }
        if (data.partials) {
          result.partials = applyPartials(data.partials);
          if (typeof opts.onPartials === 'function') {
            opts.onPartials(result.partials, data, payload);
          }
        }
        if (typeof data.html === 'string' && data.html.trim() !== '') {
          var htmlRoot = applyAdminHtml(data.html);
          if (!htmlRoot) {
            window.location.reload();
          } else {
            result.htmlRoot = htmlRoot;
            if (typeof opts.onHtml === 'function') {
              opts.onHtml(htmlRoot, data, payload);
            }
          }
        } else if (typeof data.raw === 'string' && data.raw.trim() !== '') {
          var raw = data.raw.trim();
          if (opts.expectHtml && raw.charAt(0) === '<') {
            var htmlRootRaw = applyAdminHtml(raw);
            if (!htmlRootRaw) {
              window.location.reload();
            } else {
              result.htmlRoot = htmlRootRaw;
              if (typeof opts.onHtml === 'function') {
                opts.onHtml(htmlRootRaw, data, payload);
              }
            }
          } else {
            notifier.info(raw, context);
          }
        }
        if (data.errors) {
          var validationMessage = buildValidationMessage(data.errors);
          if (validationMessage) {
            notifier.danger(validationMessage, context);
          }
          result.validationErrors = data.errors;
        }
        if (data.handlers || data.modules) {
          executeAjaxHandlers(data.handlers || data.modules, {
            context: context,
            data: data,
            response: response,
            request: { url: url, method: opts.method || 'GET', options: opts }
          });
        }
      }
    } else {
      var text = payload.text ? payload.text.trim() : '';
      if (text) {
        if (opts.expectHtml && text.charAt(0) === '<') {
          var htmlRootText = applyAdminHtml(payload.text);
          if (!htmlRootText) {
            window.location.reload();
          } else {
            result.htmlRoot = htmlRootText;
            if (typeof opts.onHtml === 'function') {
              opts.onHtml(htmlRootText, null, payload);
            }
          }
        } else {
          notifier.info(text, context);
        }
      }
    }

    return result;
  }

  function ajaxRequest(url, options) {
    var opts = options ? Object.assign({}, options) : {};
    var method = (opts.method || 'GET').toUpperCase();
    var headers = Object.assign({ 'X-Requested-With': 'XMLHttpRequest' }, opts.headers || {});
    var fetchOptions = {
      method: method,
      credentials: 'same-origin',
      headers: headers
    };
    if (opts.body !== undefined && method !== 'GET') {
      fetchOptions.body = opts.body;
    }
    if (opts.signal) {
      fetchOptions.signal = opts.signal;
    }
    if (opts.cache) {
      fetchOptions.cache = opts.cache;
    }

    var requestUrl = normalizeUrl(url);
    opts.method = method;
    opts.url = requestUrl;

    return fetch(requestUrl, fetchOptions).then(readResponsePayload).then(function (payload) {
      return handlePayload(requestUrl, payload, opts);
    }).then(function (result) {
      if (typeof opts.onSuccess === 'function') {
        opts.onSuccess(result);
      }
      return result;
    }).catch(function (error) {
      if (error && error.name === 'AbortError') {
        throw error;
      }
      if (typeof opts.onError === 'function') {
        try {
          opts.onError(error);
        } catch (err) {
          console.error('admin.ajax onError handler failed', err);
        }
      }
      if (!error || !error.__handled) {
        var message = (error && error.message) ? error.message : 'Došlo k neočekávané chybě.';
        notifier.danger(message, opts.context || null);
      }
      throw error;
    });
  }

  function ajaxGet(url, options) {
    var opts = options ? Object.assign({}, options) : {};
    opts.method = 'GET';
    return ajaxRequest(url, opts);
  }

  function ajaxPost(url, body, options) {
    var opts = options ? Object.assign({}, options) : {};
    if (body !== undefined) {
      opts.body = body;
    }
    if (!opts.method) {
      opts.method = 'POST';
    }
    return ajaxRequest(url, opts);
  }

  var adminAjax = {
    request: ajaxRequest,
    get: ajaxGet,
    post: ajaxPost,
    registerHandler: registerAjaxHandler,
    unregisterHandler: unregisterAjaxHandler,
    notify: notifier.notify
  };

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
          return;
        }
        postId = String(parsed);
        form.setAttribute('data-post-id', postId);
        form.dataset.postId = postId;
        if (idDisplayEl) {
          idDisplayEl.textContent = 'ID #' + postId;
          idDisplayEl.classList.remove('d-none');
        }
        if (editorTextarea) {
          editorTextarea.setAttribute('data-post-id', postId);
        }
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
          if (!payload || payload.success === false) {
            if (payload && payload.message === '') {
              lastSavedSerialized = serializeAutosaveData(collectFormData());
              lastSentSerialized = lastSavedSerialized;
              return;
            }
            var failMessage = payload && typeof payload.message === 'string' && payload.message !== ''
              ? payload.message
              : 'Automatické uložení selhalo.';
            throw new Error(failMessage);
          }

          if (payload.postId) {
            setCurrentPostId(payload.postId);
          }

          if (payload.actionUrl) {
            form.setAttribute('action', payload.actionUrl);
          }

          if (statusInput && payload.status) {
            statusInput.value = payload.status;
          }

          if (statusLabelEl && payload.statusLabel) {
            statusLabelEl.textContent = payload.statusLabel;
          }

          if (payload.slug) {
            var slugInput = form.querySelector('input[name="slug"]');
            if (slugInput) {
              slugInput.value = payload.slug;
            }
          }

          updateStatus(payload.message || 'Automaticky uloženo.', false);

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
    initFormHelpers(root);
    initTooltips(root);
    initBulkForms(root);
    initPostsListing(root);
    initQuickDraftWidget(root);
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

    var appliedRoot = null;

    return adminAjax.get(targetUrl, {
      context: document,
      expectHtml: true,
      signal: controller.signal,
      headers: {
        'Accept': 'text/html,application/xhtml+xml,application/json'
      },
      onHtml: function (root) {
        appliedRoot = root || appliedRoot;
      },
      onRedirect: function (redirectUrl) {
        appliedRoot = null;
        loadAdminPage(redirectUrl, { pushState: true });
      },
      onReload: function () {
        appliedRoot = null;
        loadAdminPage(targetUrl, { replaceHistory: true });
      }
    }).then(function (result) {
      if (result.redirected || result.reloaded) {
        return;
      }

      var root = result.htmlRoot || appliedRoot;
      if (!root) {
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

      dispatchNavigated(targetUrl, { source: options.fromPopstate ? 'history' : 'navigation', root: root });
    }).catch(function (error) {
      if (error && error.name === 'AbortError') {
        return;
      }
      console.error(error);
      if (!options.silent) {
        window.location.href = targetUrl;
      }
    }).finally(function () {
      if (activeNavigation && activeNavigation.controller === controller) {
        activeNavigation = null;
      }
      document.documentElement.classList.remove('is-admin-loading');
    });
  }

  function cssEscapeValue(value) {
    var str = String(value);
    if (window.CSS && typeof window.CSS.escape === 'function') {
      return window.CSS.escape(str);
    }
    return str.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
  }

  function findPostsListing(form) {
    if (!form || typeof form.closest !== 'function') {
      return null;
    }
    return form.closest('[data-posts-listing]');
  }

  function getPostsTableElements(listing) {
    if (!listing) {
      return null;
    }
    var container = listing.querySelector('[data-posts-table]');
    if (!container) {
      return null;
    }
    var wrapper = container.querySelector('[data-posts-table-wrapper]') || container;
    var tbody = wrapper.querySelector('[data-posts-tbody]');
    var template = wrapper.querySelector('template[data-posts-empty-template]') || container.querySelector('template[data-posts-empty-template]');
    return { listing: listing, container: container, wrapper: wrapper, tbody: tbody, template: template };
  }

  function removePostsEmptyRow(tbody) {
    if (!tbody) {
      return;
    }
    var empty = tbody.querySelector('[data-posts-empty-row]');
    if (empty && empty.parentNode) {
      empty.parentNode.removeChild(empty);
    }
  }

  function ensurePostsEmptyRow(elements) {
    if (!elements || !elements.tbody) {
      return;
    }
    var tbody = elements.tbody;
    var hasRow = tbody.querySelector('[data-post-row]');
    if (hasRow) {
      removePostsEmptyRow(tbody);
      return;
    }
    var existing = tbody.querySelector('[data-posts-empty-row]');
    if (existing) {
      return;
    }
    var clone = null;
    var template = elements.template;
    if (template && template.content && template.content.firstElementChild) {
      clone = template.content.firstElementChild.cloneNode(true);
    } else {
      clone = document.createElement('tr');
      clone.setAttribute('data-posts-empty-row', '1');
      var cell = document.createElement('td');
      cell.colSpan = 4;
      cell.className = 'text-center text-secondary py-4';
      cell.innerHTML = '<i class="bi bi-inbox me-1"></i>Žádné položky';
      clone.appendChild(cell);
    }
    if (clone) {
      tbody.appendChild(clone);
    }
  }

  function updatePostRowToggleState(row, state) {
    if (!row) {
      return;
    }
    row.setAttribute('data-post-status', state);
    var button = row.querySelector('[data-post-toggle-button]');
    if (!button) {
      return;
    }
    button.setAttribute('data-state', state);
    var label = state === 'publish'
      ? button.getAttribute('data-label-publish')
      : button.getAttribute('data-label-draft');
    var iconClass = state === 'publish'
      ? button.getAttribute('data-icon-publish')
      : button.getAttribute('data-icon-draft');
    if (label) {
      button.setAttribute('aria-label', label);
      button.setAttribute('data-bs-title', label);
      button.setAttribute('data-bs-original-title', label);
      button.setAttribute('title', label);
    }
    if (typeof bootstrap !== 'undefined' && typeof bootstrap.Tooltip === 'function') {
      var existingTooltip = bootstrap.Tooltip.getInstance(button);
      if (existingTooltip && typeof existingTooltip.dispose === 'function') {
        existingTooltip.dispose();
      }
    }
    var icon = button.querySelector('i');
    if (!icon) {
      icon = document.createElement('i');
      button.appendChild(icon);
    }
    icon.className = iconClass || '';
    initTooltips(row);
  }

  function triggerBulkFormUpdate(listing) {
    if (!listing) {
      return;
    }
    var form = listing.querySelector('form[data-bulk-form]');
    if (!form) {
      return;
    }
    var updateFn = bulkFormStateUpdaters.get(form);
    if (typeof updateFn === 'function') {
      updateFn();
    }
  }

  function handlePostsActionResponse(form, result) {
    if (!result || !result.data) {
      return;
    }
    var data = result.data;
    if (!data || typeof data !== 'object') {
      return;
    }
    if (!Array.isArray(data.affectedIds) || data.affectedIds.length === 0) {
      return;
    }
    var listing = findPostsListing(form);
    if (!listing) {
      return;
    }
    var elements = getPostsTableElements(listing);
    if (!elements || !elements.tbody) {
      return;
    }

    var ids = data.affectedIds.map(function (id) { return String(id); });
    var nextState = typeof data.nextState === 'string' ? data.nextState : '';

    removePostsEmptyRow(elements.tbody);

    ids.forEach(function (id) {
      var selector = '[data-post-row][data-post-id="' + cssEscapeValue(id) + '"]';
      var row = elements.tbody.querySelector(selector);
      if (!row) {
        return;
      }
      var checkbox = row.querySelector('input[type="checkbox"][name="ids[]"]');
      if (checkbox) {
        checkbox.checked = false;
      }
      if (nextState === 'deleted') {
        if (row.parentNode) {
          row.parentNode.removeChild(row);
        }
        return;
      }
      if (nextState === 'publish' || nextState === 'draft') {
        updatePostRowToggleState(row, nextState);
      }
    });

    ensurePostsEmptyRow(elements);
    triggerBulkFormUpdate(listing);
  }

  function ajaxFormRequest(form, submitter) {
    var method = (form.getAttribute('method') || 'GET').toUpperCase();
    var action = form.getAttribute('action') || window.location.href;
    var restoreDisabled = false;

    if (submitter && typeof submitter.disabled === 'boolean' && !submitter.disabled) {
      submitter.disabled = true;
      restoreDisabled = true;
    }

    clearFormValidation(form);
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

    return adminAjax.request(action, {
      method: method,
      body: formData,
      context: form,
      onHtml: function (root) {
        if (root) {
          window.history.replaceState(buildHistoryState(window.history.state), '', window.location.href);
          dispatchNavigated(window.location.href, { source: 'form', root: root });
        }
      }
    }).then(function (result) {
      if (result && result.validationErrors) {
        applyFormValidationErrors(form, result.validationErrors);
      }
      if (result.redirected || result.reloaded || result.htmlRoot) {
        return result;
      }
      handlePostsActionResponse(form, result);
      dispatchFormEvent(form, 'cms:admin:form:success', { result: result });
      return result;
    }).catch(function (error) {
      if (error && error.name === 'AbortError') {
        return;
      }
      if (error && error.validationErrors) {
        applyFormValidationErrors(form, error.validationErrors);
      }
      if (error && error.__handled) {
        dispatchFormEvent(form, 'cms:admin:form:error', { error: error });
        return;
      }
      var msg = (error && error.message) ? error.message : 'Došlo k neočekávané chybě.';
      notifier.danger(msg, form);
      dispatchFormEvent(form, 'cms:admin:form:error', { error: error, message: msg });
      throw error;
    }).finally(function () {
      form.classList.remove('is-submitting');
      if (submitter && restoreDisabled) {
        submitter.disabled = false;
      }
    });
  }

  function dispatchFormEvent(form, name, detail) {
    if (!(form instanceof HTMLFormElement)) {
      return;
    }
    var eventDetail = detail || {};
    var evt;
    try {
      evt = new CustomEvent(name, { detail: eventDetail });
    } catch (err) {
      evt = document.createEvent('CustomEvent');
      evt.initCustomEvent(name, true, true, eventDetail);
    }
    form.dispatchEvent(evt);
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
        checkboxes = checkboxes.filter(function (cb) { return cb && cb.isConnected; });
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
          checkboxes = checkboxes.filter(function (cb) { return cb && cb.isConnected; });
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

      bulkFormStateUpdaters.set(form, updateState);
      updateState();
    });
  }

  function initPostsListing(root) {
    var scope = root || document;
    var containers = [].slice.call(scope.querySelectorAll('[data-posts-listing]'));
    containers.forEach(function (container) {
      if (container.dataset.postsListingBound === '1') {
        return;
      }
      container.dataset.postsListingBound = '1';

      var pending = null;

      function setLoading(state) {
        if (state) {
          container.classList.add('is-loading');
          container.setAttribute('aria-busy', 'true');
        } else {
          container.classList.remove('is-loading');
          container.removeAttribute('aria-busy');
        }
      }

      function cleanUrl(url) {
        try {
          var parsed = new URL(url, window.location.href);
          parsed.searchParams.delete('format');
          return parsed.toString();
        } catch (err) {
          return url;
        }
      }

      function buildJsonUrl(url) {
        try {
          var parsed = new URL(url, window.location.href);
          var type = container.getAttribute('data-posts-type') || '';
          if (type && !parsed.searchParams.get('type')) {
            parsed.searchParams.set('type', type);
          }
          parsed.searchParams.set('format', 'json');
          return parsed.toString();
        } catch (err) {
          return url;
        }
      }

      function updateHistory(url) {
        if (!window.history || typeof window.history.pushState !== 'function') {
          return;
        }
        try {
          var parsed = new URL(url, window.location.href);
          parsed.searchParams.delete('format');
          var state = {};
          if (window.history.state && typeof window.history.state === 'object') {
            Object.keys(window.history.state).forEach(function (key) {
              state[key] = window.history.state[key];
            });
          }
          state[HISTORY_STATE_KEY] = true;
          window.history.pushState(state, '', parsed.toString());
        } catch (err) {
          /* ignore history errors */
        }
      }

      function applyPartials(partials) {
        if (!partials || typeof partials !== 'object') {
          return;
        }
        if (partials.toolbar) {
          var toolbar = container.querySelector('[data-posts-toolbar]');
          if (toolbar) {
            toolbar.innerHTML = partials.toolbar;
          }
        }
        if (partials.table) {
          var table = container.querySelector('[data-posts-table]');
          if (table) {
            table.innerHTML = partials.table;
          }
        }
        if (partials.pagination) {
          var pagination = container.querySelector('[data-posts-pagination]');
          if (pagination) {
            pagination.innerHTML = partials.pagination;
          }
        }
      }

      function dispatchListingUpdate(data, url) {
        var detail = { data: data || null, url: url || null };
        var eventName = 'cms:posts:listing:update';
        var evt;
        try {
          evt = new CustomEvent(eventName, { detail: detail });
        } catch (err) {
          evt = document.createEvent('CustomEvent');
          evt.initCustomEvent(eventName, true, true, detail);
        }
        container.dispatchEvent(evt);
      }

      function finalizeUpdate(data, sourceUrl) {
        if (!data || typeof data !== 'object') {
          return;
        }
        applyPartials(data.partials || {});
        refreshDynamicUI(container);
        var cleanedUrl = cleanUrl(sourceUrl);
        if (cleanedUrl) {
          container.dataset.postsUrl = cleanedUrl;
          updateHistory(cleanedUrl);
        }
        if (data.filters && typeof data.filters === 'object') {
          if (Object.prototype.hasOwnProperty.call(data.filters, 'status')) {
            container.dataset.postsStatus = String(data.filters.status || '');
          }
          if (Object.prototype.hasOwnProperty.call(data.filters, 'q')) {
            container.dataset.postsQuery = String(data.filters.q || '');
          }
        }
        if (data.pagination && typeof data.pagination === 'object' && Object.prototype.hasOwnProperty.call(data.pagination, 'page')) {
          container.dataset.postsPage = String(data.pagination.page || '');
        }
        dispatchListingUpdate(data, cleanedUrl || sourceUrl);
      }

      function handleRequest(url) {
        if (!url) {
          return;
        }

        var requestUrl = buildJsonUrl(url);
        if (!requestUrl) {
          return;
        }

        if (pending && pending.controller && typeof pending.controller.abort === 'function') {
          pending.controller.abort();
        }

        var token = { id: Date.now() + Math.random() };
        var controller = null;
        if (typeof AbortController === 'function') {
          controller = new AbortController();
          token.controller = controller;
        }
        pending = token;

        setLoading(true);

        var requestOptions = {
          headers: { 'Accept': 'application/json' },
          context: container
        };
        if (controller) {
          requestOptions.signal = controller.signal;
        }

        adminAjax.get(requestUrl, requestOptions).then(function (result) {
          if (pending !== token) {
            return;
          }
          pending = null;
          setLoading(false);
          if (!result || !result.data) {
            return;
          }
          if (result.data.redirect) {
            return;
          }
          finalizeUpdate(result.data, url);
        }).catch(function (error) {
          if (pending === token) {
            pending = null;
            setLoading(false);
          }
          if (error && error.name === 'AbortError') {
            return;
          }
        });
      }

      function shouldHandleAnchor(anchor) {
        if (!anchor || (anchor.target && anchor.target !== '_self')) {
          return false;
        }
        if (!container.contains(anchor)) {
          return false;
        }
        if (anchor.closest('[data-posts-pagination]')) {
          return true;
        }
        var toolbar = anchor.closest('[data-posts-toolbar]');
        if (!toolbar) {
          return false;
        }
        if (anchor.closest('nav')) {
          return true;
        }
        var form = anchor.closest('form');
        if (form && form.getAttribute('role') === 'search') {
          return true;
        }
        return false;
      }

      function handleClick(event) {
        var anchor = event.target && event.target.closest ? event.target.closest('a') : null;
        if (!shouldHandleAnchor(anchor)) {
          return;
        }
        event.preventDefault();
        if (event.stopPropagation) {
          event.stopPropagation();
        }
        if (event.stopImmediatePropagation) {
          event.stopImmediatePropagation();
        }
        handleRequest(anchor.href);
      }

      function handleSubmit(event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) {
          return;
        }
        if (!container.contains(form)) {
          return;
        }
        if (form.getAttribute('role') !== 'search') {
          return;
        }
        event.preventDefault();
        if (event.stopPropagation) {
          event.stopPropagation();
        }
        if (event.stopImmediatePropagation) {
          event.stopImmediatePropagation();
        }
        var method = (form.getAttribute('method') || 'GET').toUpperCase();
        var action = form.getAttribute('action') || window.location.href;
        if (method === 'GET') {
          var params = new URLSearchParams();
          var formData = new FormData(form);
          formData.forEach(function (value, key) {
            if (value instanceof File) {
              return;
            }
            params.set(key, value);
          });
          var urlObj;
          try {
            urlObj = new URL(action, window.location.href);
          } catch (err) {
            urlObj = new URL(window.location.href);
          }
          urlObj.search = params.toString();
          handleRequest(urlObj.toString());
          return;
        }

        var body = new FormData(form);
        adminAjax.request(action, {
          method: method,
          body: body,
          context: form,
          headers: { 'Accept': 'application/json' }
        }).then(function (result) {
          if (!result || !result.data) {
            return;
          }
          finalizeUpdate(result.data, action);
        }).catch(function (error) {
          if (error && error.name === 'AbortError') {
            return;
          }
        });
      }

      container.addEventListener('click', handleClick, true);
      container.addEventListener('submit', handleSubmit, true);

      document.addEventListener('cms:admin:navigated', function cleanup() {
        if (!document.body.contains(container)) {
          if (pending && pending.controller && typeof pending.controller.abort === 'function') {
            pending.controller.abort();
          }
          pending = null;
          setLoading(false);
          container.removeEventListener('click', handleClick, true);
          container.removeEventListener('submit', handleSubmit, true);
          document.removeEventListener('cms:admin:navigated', cleanup);
        }
      });
    });
  }

  function initQuickDraftWidget(root) {
    var scope = root || document;
    var forms = [].slice.call(scope.querySelectorAll('form[data-quick-draft-form]'));
    forms.forEach(function (form) {
      if (form.dataset.quickDraftBound === '1') {
        return;
      }
      form.dataset.quickDraftBound = '1';

      form.addEventListener('cms:admin:form:success', function (event) {
        var detail = event && event.detail ? event.detail : {};
        var result = detail.result || null;
        var data = result && result.data ? result.data : null;
        if (!data || !data.draft) {
          return;
        }

        if (window.cmsAdmin && window.cmsAdmin.forms && typeof window.cmsAdmin.forms.clearValidation === 'function') {
          window.cmsAdmin.forms.clearValidation(form);
        }

        form.reset();

        var titleInput = form.querySelector('[name="title"]');
        if (titleInput && typeof titleInput.focus === 'function') {
          titleInput.focus();
        }

        var widget = form.closest('[data-quick-draft-widget]');
        if (!widget) {
          return;
        }

        var listWrapper = widget.querySelector('[data-quick-draft-list-wrapper]');
        var list = widget.querySelector('[data-quick-draft-list]');
        var empty = widget.querySelector('[data-quick-draft-empty]');

        if (!list && listWrapper) {
          list = document.createElement('ul');
          list.className = 'list-unstyled mb-0 small';
          list.setAttribute('data-quick-draft-list', '1');
          listWrapper.appendChild(list);
        }

        if (empty && empty.parentNode) {
          empty.parentNode.removeChild(empty);
        }

        if (!list) {
          return;
        }

        var draft = data.draft;

        if (draft.id) {
          var existing = list.querySelector('[data-quick-draft-item-id="' + draft.id + '"]');
          if (existing && existing.parentNode) {
            existing.parentNode.removeChild(existing);
          }
        }

        var item = document.createElement('li');
        item.className = 'mb-1';
        if (draft.id) {
          item.setAttribute('data-quick-draft-item-id', String(draft.id));
        }

        var link = document.createElement('a');
        link.className = 'link-body-emphasis';
        if (draft.url) {
          link.setAttribute('href', draft.url);
          link.setAttribute('data-no-ajax', '');
        }
        var title = typeof draft.title === 'string' ? draft.title.trim() : '';
        link.textContent = title !== '' ? title : 'Bez názvu';
        item.appendChild(link);

        if (draft.created_at_display) {
          var meta = document.createElement('div');
          meta.className = 'text-secondary';
          meta.textContent = draft.created_at_display;
          item.appendChild(meta);
        }

        if (list.firstChild) {
          list.insertBefore(item, list.firstChild);
        } else {
          list.appendChild(item);
        }

        while (list.children.length > 5) {
          var last = list.lastElementChild || list.lastChild;
          if (!last) {
            break;
          }
          list.removeChild(last);
        }
      });
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
    refresh: refreshDynamicUI,
    ajax: adminAjax,
    notify: notifier.notify,
    forms: {
      registerHelper: registerFormHelper,
      unregisterHelper: unregisterFormHelper,
      applyHelpers: initFormHelpers,
      clearValidation: clearFormValidation,
      applyValidation: applyFormValidationErrors
    }
  };
})();
