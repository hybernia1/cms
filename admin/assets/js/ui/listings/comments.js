import { adminAjax } from '../../core/ajax.js';
import { dispatchFormEvent } from '../../core/form-events.js';
import { buildHistoryState, loadAdminPage } from '../../core/navigation.js';
import {
  buildListingJsonUrl,
  cleanListingUrl,
  pushListingHistory,
  setListingLoadingState
} from './listing-utils.js';

var commentsListingControllers = new WeakMap();

var dependencies = {
  triggerBulkFormUpdate: function () {}
};

export function configureCommentsListing(options) {
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

function createCommentsListingController(container) {
  var state = {
    pending: null,
    lastUrl: container.getAttribute('data-comments-url') || window.location.href,
    searchForm: null,
    searchHandler: null
  };

  function setLoading(isLoading) {
    setListingLoadingState(container, isLoading);
  }

  function cleanUrl(url) {
    return cleanListingUrl(url);
  }

  function buildJsonUrl(url) {
    return buildListingJsonUrl(url);
  }

  function updateHistory(url) {
    pushListingHistory(url, function (state) {
      return buildHistoryState(state);
    });
  }

  function updateDataset(data, sourceUrl) {
    if (data && data.filters && typeof data.filters === 'object') {
      if (Object.prototype.hasOwnProperty.call(data.filters, 'status')) {
        container.setAttribute('data-comments-status', String(data.filters.status || ''));
      }
      if (Object.prototype.hasOwnProperty.call(data.filters, 'q')) {
        container.setAttribute('data-comments-query', String(data.filters.q || ''));
      }
      if (Object.prototype.hasOwnProperty.call(data.filters, 'post')) {
        container.setAttribute('data-comments-post', String(data.filters.post || ''));
      }
    }
    if (data && data.pagination && typeof data.pagination === 'object' && Object.prototype.hasOwnProperty.call(data.pagination, 'page')) {
      container.setAttribute('data-comments-page', String(data.pagination.page || ''));
    }

    var nextUrl = sourceUrl || state.lastUrl;
    if (data && data.listing && typeof data.listing === 'object' && data.listing.url) {
      nextUrl = data.listing.url;
    }
    if (nextUrl) {
      var cleaned = cleanUrl(nextUrl);
      if (cleaned) {
        container.setAttribute('data-comments-url', cleaned);
        state.lastUrl = cleaned;
        updateHistory(cleaned);
      }
    }
  }

  function finalize(data, sourceUrl) {
    if (!data || typeof data !== 'object') {
      return;
    }
    bindSearchForm();
    triggerBulkUpdate(container);
    updateDataset(data, sourceUrl);
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
    var cleanedRequested = cleanUrl(url);
    if (cleanedRequested) {
      state.lastUrl = cleanedRequested;
    }
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

  function submitGetForm(form, submitter) {
    if (form.dataset.commentsSubmitting === '1') {
      return;
    }
    form.dataset.commentsSubmitting = '1';
    form.classList.add('is-submitting');

    var restoreDisabled = false;
    if (submitter && typeof submitter.disabled === 'boolean' && !submitter.disabled) {
      submitter.disabled = true;
      restoreDisabled = true;
    }

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
      urlObj = new URL(form.getAttribute('action') || window.location.href, window.location.href);
    } catch (err) {
      urlObj = new URL(window.location.href);
    }
    urlObj.search = params.toString();

    handleRequest(urlObj.toString()).finally(function () {
      form.classList.remove('is-submitting');
      delete form.dataset.commentsSubmitting;
      if (submitter && restoreDisabled) {
        submitter.disabled = false;
      }
    });
  }

  function submitPostForm(form, submitter) {
    if (form.dataset.commentsSubmitting === '1') {
      return;
    }
    form.dataset.commentsSubmitting = '1';
    form.classList.add('is-submitting');

    var restoreDisabled = false;
    if (submitter && typeof submitter.disabled === 'boolean' && !submitter.disabled) {
      submitter.disabled = true;
      restoreDisabled = true;
    }

    var body = new FormData(form);
    if (submitter && submitter.name) {
      body.append(submitter.name, submitter.value);
    }

    adminAjax.request(form.getAttribute('action') || window.location.href, {
      method: (form.getAttribute('method') || 'POST').toUpperCase(),
      body: body,
      headers: { 'Accept': 'application/json' },
      context: form
    }).then(function (result) {
      if (result && result.data) {
        finalize(result.data, form.getAttribute('action') || window.location.href);
      }
      dispatchFormEvent(form, 'cms:admin:form:success', { result: result || null });
    }).catch(function (error) {
      if (error && error.name === 'AbortError') {
        return;
      }
      dispatchFormEvent(form, 'cms:admin:form:error', { error: error });
    }).finally(function () {
      form.classList.remove('is-submitting');
      delete form.dataset.commentsSubmitting;
      if (submitter && restoreDisabled) {
        submitter.disabled = false;
      }
    });
  }

  function handleSubmit(event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement)) {
      return;
    }
    if (!container.contains(form)) {
      return;
    }
    if (!form.getAttribute('data-comments-form')) {
      return;
    }
    event.preventDefault();
    if (event.stopPropagation) {
      event.stopPropagation();
    }
    if (event.stopImmediatePropagation) {
      event.stopImmediatePropagation();
    }
    var method = (form.getAttribute('method') || 'POST').toUpperCase();
    if (method === 'GET') {
      submitGetForm(form, event.submitter || null);
    } else {
      submitPostForm(form, event.submitter || null);
    }
  }

  function handleClick(event) {
    var anchor = event.target && event.target.closest ? event.target.closest('a') : null;
    if (!anchor || !container.contains(anchor)) {
      return;
    }
    if (anchor.closest('[data-comments-pagination]')) {
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

  function bindSearchForm() {
    var form = container.querySelector('[data-comments-toolbar] form[role="search"]');
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
      submitGetForm(form, event && event.submitter ? event.submitter : null);
    };
    state.searchHandler = handler;
    form.addEventListener('submit', handler, true);
  }

  function cleanup() {
    if (state.pending && typeof state.pending.abort === 'function') {
      state.pending.abort();
    }
    container.removeEventListener('submit', handleSubmit, true);
    container.removeEventListener('click', handleClick, true);
    if (state.searchForm && state.searchHandler) {
      state.searchForm.removeEventListener('submit', state.searchHandler, true);
    }
    commentsListingControllers.delete(container);
  }

  container.addEventListener('submit', handleSubmit, true);
  container.addEventListener('click', handleClick, true);
  bindSearchForm();

  document.addEventListener('cms:admin:navigated', function onNavigate() {
    if (!document.body.contains(container)) {
      cleanup();
      document.removeEventListener('cms:admin:navigated', onNavigate);
    }
  });

  return {
    reload: function () {
      var target = container.getAttribute('data-comments-url') || state.lastUrl;
      return handleRequest(target);
    },
    dispose: cleanup
  };
}

export function initCommentsListing(root) {
  var scope = root || document;
  var containers = [].slice.call(scope.querySelectorAll('[data-comments-listing]'));
  containers.forEach(function (container) {
    if (commentsListingControllers.has(container)) {
      return;
    }
    var controller = createCommentsListingController(container);
    commentsListingControllers.set(container, controller);
  });
}

export function handleCommentsListingFormSuccess(event) {
  var form = event.target;
  if (!(form instanceof HTMLFormElement) || !form.closest) {
    return;
  }
  var container = form.closest('[data-comments-listing]');
  if (!container) {
    return;
  }
  if (form.getAttribute('data-comments-form')) {
    return;
  }
  if (form.getAttribute('role') === 'search' && form.closest('[data-comments-toolbar]')) {
    return;
  }
  var controller = commentsListingControllers.get(container);
  if (!controller) {
    return;
  }
  var detail = event.detail || {};
  var result = detail.result || null;
  var data = result && result.data ? result.data : null;
  if (data && data.success === false) {
    return;
  }
  controller.reload();
}

function updateCommentStatusBadge(comment) {
  if (!comment) {
    return;
  }
  var badge = document.querySelector('[data-comment-status-badge]');
  if (!badge) {
    return;
  }
  var status = typeof comment.status === 'string' ? comment.status : '';
  var variants = { published: 'success', spam: 'danger' };
  var variant = variants[status] || 'secondary';
  badge.textContent = status || '';
  ['text-bg-success', 'text-bg-danger', 'text-bg-secondary'].forEach(function (cls) {
    badge.classList.remove(cls);
  });
  badge.classList.add('text-bg-' + variant);
}

export function handleCommentActionFormSuccess(event) {
  var form = event.target;
  if (!(form instanceof HTMLFormElement)) {
    return;
  }
  var actionType = form.getAttribute('data-comments-action');
  if (!actionType) {
    return;
  }
  var detail = event.detail || {};
  var result = detail.result || null;
  var data = result && result.data ? result.data : null;

  if (actionType === 'status') {
    if (!form.closest('[data-comments-listing]') && data && data.comment) {
      updateCommentStatusBadge(data.comment);
    }
    return;
  }

  if (actionType === 'reply') {
    if (typeof form.reset === 'function') {
      form.reset();
    } else {
      var textInput = form.querySelector('textarea[name="content"]');
      if (textInput) {
        textInput.value = '';
      }
    }
    var csrfInput = form.querySelector('input[name="csrf"]');
    if (csrfInput && !csrfInput.value) {
      var defaultCsrf = csrfInput.getAttribute('value');
      if (defaultCsrf) {
        csrfInput.value = defaultCsrf;
      }
    }
    var parentInput = form.querySelector('input[name="parent_id"]');
    if (parentInput && !parentInput.value) {
      var defaultParent = parentInput.getAttribute('value');
      if (defaultParent) {
        parentInput.value = defaultParent;
      }
    }
    var textarea = form.querySelector('textarea[name="content"]');
    if (textarea && typeof textarea.focus === 'function') {
      textarea.focus();
    }
    return;
  }

  if (actionType === 'delete') {
    if (form.closest('[data-comments-listing]')) {
      return;
    }
    var backInput = form.querySelector('input[name="_back"]');
    var redirectUrl = backInput && backInput.value ? backInput.value : 'admin.php?r=comments';
    loadAdminPage(redirectUrl, { pushState: true });
  }
}

export function getCommentsListingController(container) {
  return commentsListingControllers.get(container);
}
