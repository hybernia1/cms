import { adminAjax } from '../../core/ajax.js';
import { dispatchFormEvent } from '../../core/form-events.js';
import { HISTORY_STATE_KEY } from '../../core/navigation.js';
import { cssEscapeValue } from '../../utils/css-escape.js';

var termsListingControllers = new WeakMap();

var dependencies = {
  triggerBulkFormUpdate: function () {}
};

export function configureTermsListing(options) {
  if (!options || typeof options !== 'object') {
    return;
  }
  dependencies = Object.assign({}, dependencies, options);
}

function triggerBulkUpdate(container) {
  if (typeof dependencies.triggerBulkFormUpdate === 'function') {
    dependencies.triggerBulkFormUpdate(container);
  }
}

function getTermsTableElements(container) {
  var tableWrapper = container.querySelector('[data-terms-table]');
  if (!tableWrapper) {
    return null;
  }
  var tbody = tableWrapper.querySelector('[data-terms-tbody]');
  var template = tableWrapper.querySelector('template[data-terms-empty-template]');
  return {
    wrapper: tableWrapper,
    tbody: tbody,
    template: template
  };
}

function removeTermsEmptyRow(elements) {
  if (!elements || !elements.tbody) {
    return;
  }
  var emptyRow = elements.tbody.querySelector('[data-terms-empty-row]');
  if (emptyRow && emptyRow.parentNode) {
    emptyRow.parentNode.removeChild(emptyRow);
  }
}

function ensureTermsEmptyRow(elements) {
  if (!elements || !elements.tbody) {
    return;
  }
  var hasRows = !!elements.tbody.querySelector('[data-terms-row]');
  if (hasRows) {
    removeTermsEmptyRow(elements);
    return;
  }
  if (elements.tbody.querySelector('[data-terms-empty-row]')) {
    return;
  }
  var template = elements.template;
  if (template && template.content) {
    var clone = template.content.cloneNode(true);
    elements.tbody.appendChild(clone);
    return;
  }
  var row = document.createElement('tr');
  row.setAttribute('data-terms-empty-row', '1');
  row.innerHTML = '<td colspan="3" class="text-center text-secondary py-4"><i class="bi bi-inbox me-1"></i>Žádné termy</td>';
  elements.tbody.appendChild(row);
}

function removeTermRows(container, ids) {
  if (!Array.isArray(ids) || ids.length === 0) {
    return;
  }
  var elements = getTermsTableElements(container);
  if (!elements || !elements.tbody) {
    return;
  }
  removeTermsEmptyRow(elements);
  ids.forEach(function (rawId) {
    var id = String(rawId);
    var selector = '[data-terms-row][data-term-id="' + cssEscapeValue(id) + '"]';
    var row = elements.tbody.querySelector(selector);
    if (!row) {
      return;
    }
    var checkbox = row.querySelector('input[type="checkbox"][name="ids[]"]');
    if (checkbox) {
      checkbox.checked = false;
    }
    if (row.parentNode) {
      row.parentNode.removeChild(row);
    }
  });
  ensureTermsEmptyRow(elements);
  triggerBulkUpdate(container);
}

function createTermsListingController(container) {
  var state = {
    pending: null,
    searchForm: null,
    searchHandler: null,
    lastRequestedUrl: container.getAttribute('data-terms-url') || window.location.href
  };

  function setLoading(isLoading) {
    if (isLoading) {
      container.classList.add('is-loading');
      container.setAttribute('aria-busy', 'true');
    } else {
      container.classList.remove('is-loading');
      container.removeAttribute('aria-busy');
    }
  }

  function cleanUrl(url) {
    if (!url) {
      return url;
    }
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
      var typeValue = container.getAttribute('data-terms-type') || '';
      if (typeValue && !parsed.searchParams.get('type')) {
        parsed.searchParams.set('type', typeValue);
      }
      parsed.searchParams.set('format', 'json');
      return parsed.toString();
    } catch (err) {
      return url;
    }
  }

  function updateHistory(url) {
    if (!url || !window.history || typeof window.history.pushState !== 'function') {
      return;
    }
    try {
      var parsed = new URL(url, window.location.href);
      parsed.searchParams.delete('format');
      var stateObj = {};
      if (window.history.state && typeof window.history.state === 'object') {
        Object.keys(window.history.state).forEach(function (key) {
          stateObj[key] = window.history.state[key];
        });
      }
      stateObj[HISTORY_STATE_KEY] = true;
      window.history.pushState(stateObj, '', parsed.toString());
    } catch (err) {
      /* ignore history errors */
    }
  }

  function replaceSection(selector, html) {
    if (typeof html !== 'string') {
      return;
    }
    var target = container.querySelector(selector);
    if (!target) {
      return;
    }
    target.outerHTML = html;
  }

  function applyPartials(partials) {
    if (!partials || typeof partials !== 'object') {
      return;
    }
    if (partials.toolbar !== undefined) {
      replaceSection('[data-terms-toolbar]', partials.toolbar);
    }
    if (partials.table !== undefined) {
      replaceSection('[data-terms-table]', partials.table);
    }
    if (partials.pagination !== undefined) {
      replaceSection('[data-terms-pagination]', partials.pagination);
    }
  }

  function bindSearchForm() {
    var form = container.querySelector('[data-terms-toolbar] form[role="search"]');
    if (state.searchForm === form) {
      return;
    }
    if (state.searchForm && state.searchHandler) {
      state.searchForm.removeEventListener('submit', state.searchHandler, true);
    }
    state.searchForm = form;
    state.searchHandler = null;
    if (!form) {
      return;
    }
    var handler = function (event) {
      if (event) {
        event.preventDefault();
        if (event.stopPropagation) {
          event.stopPropagation();
        }
        if (event.stopImmediatePropagation) {
          event.stopImmediatePropagation();
        }
      }
      submitSearchForm(form, event && event.submitter ? event.submitter : null);
    };
    state.searchHandler = handler;
    form.addEventListener('submit', handler, true);
  }

  function finalize(data, sourceUrl) {
    if (!data || typeof data !== 'object') {
      return;
    }
    applyPartials(data.partials || {});
    bindSearchForm();
    triggerBulkUpdate(container);
    var cleanedUrl = cleanUrl(sourceUrl);
    if (cleanedUrl) {
      container.setAttribute('data-terms-url', cleanedUrl);
      updateHistory(cleanedUrl);
    }
    if (data.filters && typeof data.filters === 'object') {
      if (Object.prototype.hasOwnProperty.call(data.filters, 'type')) {
        container.setAttribute('data-terms-filter-type', String(data.filters.type || ''));
      }
      if (Object.prototype.hasOwnProperty.call(data.filters, 'q')) {
        container.setAttribute('data-terms-query', String(data.filters.q || ''));
      }
    }
    if (data.pagination && typeof data.pagination === 'object' && Object.prototype.hasOwnProperty.call(data.pagination, 'page')) {
      container.setAttribute('data-terms-page', String(data.pagination.page || ''));
    }
  }

  function handleRequest(url) {
    if (!url) {
      return Promise.resolve(null);
    }

    if (state.pending && typeof state.pending.abort === 'function') {
      state.pending.abort();
    }

    var controller = new AbortController();
    state.pending = controller;
    state.lastRequestedUrl = url;
    setLoading(true);

    return adminAjax.get(buildJsonUrl(url), {
      signal: controller.signal,
      headers: { 'Accept': 'application/json' },
      context: container
    }).then(function (result) {
      if (result && result.data) {
        finalize(result.data, url);
      }
      return result;
    }).catch(function (error) {
      if (error && error.name === 'AbortError') {
        return null;
      }
      throw error;
    }).finally(function () {
      if (state.pending === controller) {
        state.pending = null;
      }
      setLoading(false);
    });
  }

  function handleClick(event) {
    var anchor = event.target && event.target.closest ? event.target.closest('a') : null;
    if (!anchor || !container.contains(anchor)) {
      return;
    }
    if (!anchor.closest('[data-terms-toolbar]') && !anchor.closest('[data-terms-pagination]')) {
      return;
    }
    if (anchor.getAttribute('href')) {
      event.preventDefault();
      if (event.stopPropagation) {
        event.stopPropagation();
      }
      if (event.stopImmediatePropagation) {
        event.stopImmediatePropagation();
      }
      handleRequest(anchor.href);
    }
  }

  function submitSearchForm(form, submitter) {
    if (form.dataset.termsSubmitting === '1') {
      return;
    }
    form.dataset.termsSubmitting = '1';
    form.classList.add('is-submitting');

    var restoreDisabled = false;
    if (submitter && typeof submitter.disabled === 'boolean' && !submitter.disabled) {
      submitter.disabled = true;
      restoreDisabled = true;
    }

    var promise;
    var method = (form.getAttribute('method') || 'GET').toUpperCase();
    var action = form.getAttribute('action') || window.location.href;

    if (method === 'GET') {
      var params = new URLSearchParams();
      var formData = new FormData(form);
      formData.forEach(function (value, key) {
        params.append(key, value);
      });
      if (submitter && submitter.name) {
        params.append(submitter.name, submitter.value);
      }
      var urlObj;
      try {
        urlObj = new URL(action, window.location.href);
      } catch (err) {
        urlObj = new URL(window.location.href);
      }
      urlObj.search = params.toString();
      promise = handleRequest(urlObj.toString());
    } else {
      setLoading(true);
      var body = new FormData(form);
      if (submitter && submitter.name) {
        body.append(submitter.name, submitter.value);
      }
      promise = adminAjax.request(action, {
        method: method,
        body: body,
        headers: { 'Accept': 'application/json' },
        context: form
      }).then(function (result) {
        if (result && result.data) {
          finalize(result.data, action);
        }
        return result;
      });
    }

    promise.then(function (result) {
      dispatchFormEvent(form, 'cms:admin:form:success', { result: result || null });
    }).catch(function (error) {
      if (error && error.name === 'AbortError') {
        return;
      }
      dispatchFormEvent(form, 'cms:admin:form:error', { error: error });
    }).finally(function () {
      form.classList.remove('is-submitting');
      delete form.dataset.termsSubmitting;
      if (submitter && restoreDisabled) {
        submitter.disabled = false;
      }
      if (method !== 'GET') {
        setLoading(false);
      }
    });
  }

  function cleanup() {
    if (state.pending && typeof state.pending.abort === 'function') {
      state.pending.abort();
    }
    container.removeEventListener('click', handleClick, true);
    if (state.searchForm && state.searchHandler) {
      state.searchForm.removeEventListener('submit', state.searchHandler, true);
    }
    termsListingControllers.delete(container);
    document.removeEventListener('cms:admin:navigated', onNavigate);
  }

  function onNavigate() {
    if (!document.body.contains(container)) {
      cleanup();
    }
  }

  container.addEventListener('click', handleClick, true);
  bindSearchForm();
  document.addEventListener('cms:admin:navigated', onNavigate);

  return {
    finalize: finalize,
    removeIds: function (ids) {
      removeTermRows(container, ids);
    },
    reload: function () {
      return handleRequest(container.getAttribute('data-terms-url') || window.location.href);
    },
    dispose: cleanup
  };
}

export function initTermsListing(root) {
  var scope = root || document;
  var containers = [].slice.call(scope.querySelectorAll('[data-terms-listing]'));
  containers.forEach(function (container) {
    if (termsListingControllers.has(container)) {
      return;
    }
    var controller = createTermsListingController(container);
    termsListingControllers.set(container, controller);
  });
}

export function handleTermsFormSuccess(event) {
  var form = event.target;
  if (!(form instanceof HTMLFormElement)) {
    return;
  }
  if (!form.closest) {
    return;
  }
  var container = form.closest('[data-terms-listing]');
  if (!container) {
    return;
  }
  var controller = termsListingControllers.get(container);
  if (!controller) {
    return;
  }
  var detail = event.detail || {};
  var result = detail.result || null;
  var data = result && result.data ? result.data : null;
  if (!data || typeof data !== 'object') {
    return;
  }

  if (form.hasAttribute('data-terms-delete-form')) {
    if (Array.isArray(data.removedIds)) {
      controller.removeIds(data.removedIds);
    }
    return;
  }

  if (form.hasAttribute('data-bulk-form')) {
    if (Array.isArray(data.removedIds)) {
      controller.removeIds(data.removedIds);
    }
    if (data.partials) {
      controller.finalize(data, container.getAttribute('data-terms-url') || window.location.href);
    }
  }
}

export function getTermsListingController(container) {
  return termsListingControllers.get(container);
}
