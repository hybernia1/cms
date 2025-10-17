(function () {
  var HISTORY_STATE_KEY = 'cmsAdminAjax';
  var activeNavigation = null;

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

  function showFlashMessage(type, message, form) {
    var container = findFlashContainer(form);
    if (!container) {
      return;
    }

    var existing = container.querySelector('.admin-flash');
    if (existing && existing.parentNode) {
      existing.parentNode.removeChild(existing);
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
    if (typeof alert.scrollIntoView === 'function') {
      try {
        alert.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } catch (e) {
        /* noop */
      }
    }
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

  function dispatchNavigated(url, options) {
    var detail = {
      url: url,
      root: document,
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
      return false;
    }
    var newWrapper = doc.querySelector('.admin-wrapper');
    var currentWrapper = document.querySelector('.admin-wrapper');
    if (!newWrapper || !currentWrapper) {
      return false;
    }
    currentWrapper.replaceWith(newWrapper);
    return true;
  }

  function refreshDynamicUI(root) {
    initTooltips(root);
    initBulkForms(root);
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
        'Accept': 'text/html,application/xhtml+xml'
      },
      signal: controller.signal
    }).then(function (response) {
      if (!response.ok) {
        var error = new Error('Nepodařilo se načíst stránku (' + response.status + ').');
        error.status = response.status;
        throw error;
      }
      return response.text();
    }).then(function (html) {
      var parser = new DOMParser();
      var doc = parser.parseFromString(html, 'text/html');
      if (!replaceAdminShell(doc)) {
        window.location.href = targetUrl;
        return;
      }
      syncDocumentMeta(doc);
      refreshDynamicUI(document);

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

      dispatchNavigated(targetUrl, { source: options.fromPopstate ? 'history' : 'navigation' });
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
    var restoreDisabled = false;

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
    }).then(function (response) {
      return response.text().then(function (text) {
        var data = {};
        if (text) {
          try {
            data = JSON.parse(text);
          } catch (err) {
            data = { raw: text };
          }
        }

        if (!response.ok) {
          var message = 'Došlo k chybě (' + response.status + ')';
          if (data && typeof data === 'object') {
            if (data.error) {
              message = data.error;
            } else if (data.message) {
              message = data.message;
            } else if (data.raw) {
              message = String(data.raw).trim() || message;
            }
          }
          var error = new Error(message);
          error.__handled = true;
          showFlashMessage('danger', message, form);
          throw error;
        }

        return data;
      });
    }).then(function (data) {
      var flash = data && typeof data === 'object' ? data.flash : null;
      if (flash && typeof flash === 'object') {
        showFlashMessage(flash.type, flash.msg, form);
      }

      var followUp = Promise.resolve();

      if (data && typeof data.redirect === 'string' && data.redirect !== '') {
        followUp = loadAdminPage(data.redirect, { pushState: true });
      } else if (data && typeof data.reload === 'boolean' && data.reload) {
        followUp = loadAdminPage(window.location.href, { replaceHistory: true });
      } else if (!flash && data && data.message) {
        showFlashMessage(data.success === false ? 'danger' : 'info', data.message, form);
      }

      return followUp;
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
    refreshDynamicUI(document);
    initAjaxForms();
    initAjaxLinks();
    bootHistory();
    dispatchNavigated(window.location.href, { initial: true, source: 'initial' });
  });

  window.cmsAdmin = {
    load: loadAdminPage,
    refresh: refreshDynamicUI
  };
})();
