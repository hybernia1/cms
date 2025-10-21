import { adminAjax } from '../../core/ajax.js';
import { HISTORY_STATE_KEY } from '../../core/navigation.js';
import { initTooltips } from '../tooltips.js';
import { cssEscapeValue } from '../../utils/css-escape.js';

var dependencies = {
  refreshDynamicUI: function () {},
  triggerBulkFormUpdate: function () {}
};

export function configurePostsListing(options) {
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

function refreshUI(root) {
  if (typeof dependencies.refreshDynamicUI === 'function') {
    dependencies.refreshDynamicUI(root);
  }
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

function dispatchListingUpdate(container, data, url) {
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

export function handlePostsActionResponse(form, result) {
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
  triggerBulkUpdate(listing);
}

export function initPostsListing(root) {
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

    function finalizeUpdate(data, sourceUrl) {
      if (!data || typeof data !== 'object') {
        return;
      }
      applyPartials(data.partials || {});
      refreshUI(container);
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
      dispatchListingUpdate(container, data, cleanedUrl || sourceUrl);
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

