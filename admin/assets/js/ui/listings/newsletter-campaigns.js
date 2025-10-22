import { adminAjax } from '../../core/ajax.js';
import { HISTORY_STATE_KEY } from '../../core/navigation.js';

const controllers = new WeakMap();

let dependencies = {
  refreshDynamicUI: function () {}
};

export function configureNewsletterCampaignsListing(options) {
  if (!options || typeof options !== 'object') {
    return;
  }
  dependencies = Object.assign({}, dependencies, options);
}

function refreshUI(root) {
  if (typeof dependencies.refreshDynamicUI === 'function') {
    dependencies.refreshDynamicUI(root);
  }
}

function replaceSection(container, selector, html) {
  if (typeof html !== 'string') {
    return;
  }
  const target = container.querySelector(selector);
  if (!target) {
    return;
  }
  target.outerHTML = html;
}

function cleanUrl(url) {
  if (!url) {
    return url;
  }
  try {
    const parsed = new URL(url, window.location.href);
    parsed.searchParams.delete('format');
    return parsed.toString();
  } catch (err) {
    return url;
  }
}

function buildJsonUrl(url, container) {
  try {
    const parsed = new URL(url, window.location.href);
    parsed.searchParams.set('format', 'json');
    const q = container.getAttribute('data-newsletter-campaigns-filter-q') || '';
    if (q && !parsed.searchParams.has('q')) {
      parsed.searchParams.set('q', q);
    }
    const author = container.getAttribute('data-newsletter-campaigns-filter-author');
    if (author && !parsed.searchParams.has('author')) {
      parsed.searchParams.set('author', author);
    }
    const page = container.getAttribute('data-newsletter-campaigns-page');
    if (page && !parsed.searchParams.has('page')) {
      parsed.searchParams.set('page', page);
    }
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
    const parsed = new URL(url, window.location.href);
    parsed.searchParams.delete('format');
    const state = Object.assign({}, window.history.state || {});
    state[HISTORY_STATE_KEY] = true;
    window.history.pushState(state, '', parsed.toString());
  } catch (err) {
    /* ignore history errors */
  }
}

function parseCampaignData(container, id) {
  const selector = '[data-newsletter-campaign-data][data-campaign-id="' + id + '"]';
  const script = container.querySelector(selector);
  if (!script) {
    return null;
  }
  const text = script.textContent || '';
  if (!text) {
    return null;
  }
  try {
    const data = JSON.parse(text);
    if (data && typeof data === 'object') {
      return data;
    }
  } catch (err) {
    /* ignore parse error */
  }
  return null;
}

function syncModalContext(container) {
  const modal = document.getElementById('newsletterCampaignEditModal');
  if (!modal) {
    return;
  }
  const form = modal.querySelector('[data-newsletter-campaigns-edit-form]');
  if (!(form instanceof HTMLFormElement)) {
    return;
  }
  const csrfInput = form.querySelector('input[name="csrf"]');
  if (csrfInput) {
    csrfInput.value = container.getAttribute('data-newsletter-campaigns-csrf') || '';
  }
  const qInput = form.querySelector('input[name="q"]');
  if (qInput) {
    qInput.value = container.getAttribute('data-newsletter-campaigns-filter-q') || '';
  }
  const authorInput = form.querySelector('input[name="author"]');
  if (authorInput) {
    authorInput.value = container.getAttribute('data-newsletter-campaigns-filter-author') || '0';
  }
  const pageInput = form.querySelector('input[name="page"]');
  if (pageInput) {
    pageInput.value = container.getAttribute('data-newsletter-campaigns-page') || '1';
  }
}

function openEditModal(container, id) {
  const data = parseCampaignData(container, id);
  if (!data) {
    return;
  }
  const modal = document.getElementById('newsletterCampaignEditModal');
  if (!modal) {
    return;
  }
  const form = modal.querySelector('[data-newsletter-campaigns-edit-form]');
  if (!(form instanceof HTMLFormElement)) {
    return;
  }
  const subjectInput = form.querySelector('#newsletter-campaign-edit-subject');
  const bodyInput = form.querySelector('#newsletter-campaign-edit-body');
  const idInput = form.querySelector('input[name="id"]');
  if (subjectInput instanceof HTMLInputElement) {
    subjectInput.value = typeof data.subject === 'string' ? data.subject : '';
  }
  if (bodyInput instanceof HTMLTextAreaElement) {
    bodyInput.value = typeof data.body === 'string' ? data.body : '';
  }
  if (idInput instanceof HTMLInputElement) {
    idInput.value = String(id);
  }
  syncModalContext(container);
  if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined') {
    const instance = bootstrap.Modal.getOrCreateInstance(modal);
    instance.show();
  }
}

function createController(container) {
  const state = {
    pending: null,
    lastUrl: cleanUrl(container.getAttribute('data-newsletter-campaigns-url') || window.location.href)
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

  function applyPartials(partials) {
    if (!partials || typeof partials !== 'object') {
      return;
    }
    if (Object.prototype.hasOwnProperty.call(partials, 'toolbar')) {
      replaceSection(container, '[data-newsletter-campaigns-toolbar]', partials.toolbar);
    }
    if (Object.prototype.hasOwnProperty.call(partials, 'table')) {
      replaceSection(container, '[data-newsletter-campaigns-table]', partials.table);
    }
    if (Object.prototype.hasOwnProperty.call(partials, 'pagination')) {
      replaceSection(container, '[data-newsletter-campaigns-pagination]', partials.pagination);
    }
  }

  function bindFilterForm() {
    const form = container.querySelector('[data-newsletter-campaigns-filter-form]');
    if (!(form instanceof HTMLFormElement)) {
      return;
    }
    if (form.dataset.newsletterCampaignsBound === '1') {
      return;
    }
    form.dataset.newsletterCampaignsBound = '1';
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      const submitter = event.submitter || null;
      submitFilterForm(form, submitter);
    }, true);
  }

  function bindEditButtons() {
    const buttons = [].slice.call(container.querySelectorAll('[data-newsletter-campaign-edit-trigger]'));
    buttons.forEach(function (button) {
      if (!(button instanceof HTMLElement)) {
        return;
      }
      if (button.dataset.newsletterCampaignsEditBound === '1') {
        return;
      }
      button.dataset.newsletterCampaignsEditBound = '1';
      button.addEventListener('click', function (event) {
        event.preventDefault();
        const id = button.getAttribute('data-campaign-id');
        if (!id) {
          return;
        }
        openEditModal(container, id);
      });
    });
  }

  function submitFilterForm(form, submitter) {
    const method = (form.getAttribute('method') || 'GET').toUpperCase();
    const action = form.getAttribute('action') || window.location.href;
    const formData = new FormData(form);
    if (submitter && submitter.name) {
      formData.append(submitter.name, submitter.value);
    }
    if (method !== 'GET') {
      return;
    }
    const params = new URLSearchParams();
    formData.forEach(function (value, key) {
      params.append(key, value);
    });
    try {
      const urlObj = new URL(action, window.location.href);
      urlObj.search = params.toString();
      handleRequest(urlObj.toString());
    } catch (err) {
      handleRequest(action);
    }
  }

  function finalize(data, requestedUrl) {
    if (!data || typeof data !== 'object') {
      return;
    }
    if (data.partials) {
      applyPartials(data.partials);
      refreshUI(container);
      bindFilterForm();
      bindEditButtons();
    }
    if (data.filters && typeof data.filters === 'object') {
      if (Object.prototype.hasOwnProperty.call(data.filters, 'q')) {
        container.setAttribute('data-newsletter-campaigns-filter-q', String(data.filters.q || ''));
      }
      if (Object.prototype.hasOwnProperty.call(data.filters, 'author')) {
        container.setAttribute('data-newsletter-campaigns-filter-author', String(data.filters.author || '0'));
      }
    }
    if (data.pagination && typeof data.pagination === 'object' && Object.prototype.hasOwnProperty.call(data.pagination, 'page')) {
      container.setAttribute('data-newsletter-campaigns-page', String(data.pagination.page || '1'));
    }
    if (Object.prototype.hasOwnProperty.call(data, 'csrf')) {
      container.setAttribute('data-newsletter-campaigns-csrf', String(data.csrf || ''));
    }
    if (requestedUrl) {
      const cleaned = cleanUrl(requestedUrl);
      container.setAttribute('data-newsletter-campaigns-url', cleaned);
      state.lastUrl = cleaned;
      updateHistory(cleaned);
    }
    syncModalContext(container);
  }

  function handleRequest(url) {
    if (!url) {
      return Promise.resolve(null);
    }
    if (state.pending && typeof state.pending.abort === 'function') {
      state.pending.abort();
    }
    const controller = new AbortController();
    state.pending = controller;
    setLoading(true);
    const requestUrl = buildJsonUrl(url, container);

    return adminAjax.get(requestUrl, {
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
    const anchor = event.target && event.target.closest ? event.target.closest('a') : null;
    if (!anchor || !container.contains(anchor)) {
      return;
    }
    if (!anchor.closest('[data-newsletter-campaigns-toolbar]') && !anchor.closest('[data-newsletter-campaigns-pagination]')) {
      return;
    }
    if (!anchor.getAttribute('href')) {
      return;
    }
    event.preventDefault();
    handleRequest(anchor.href);
  }

  container.addEventListener('click', handleClick, true);
  bindFilterForm();
  bindEditButtons();
  syncModalContext(container);

  return {
    reload: function (url) {
      const target = url || state.lastUrl || window.location.href;
      return handleRequest(target);
    }
  };
}

export function initNewsletterCampaignsListing(root) {
  const scope = root || document;
  const containers = [].slice.call(scope.querySelectorAll('[data-newsletter-campaigns-listing]'));
  containers.forEach(function (container) {
    if (controllers.has(container)) {
      return;
    }
    const controller = createController(container);
    controllers.set(container, controller);
  });
}

export function handleNewsletterCampaignsFormSuccess(event) {
  const form = event.target;
  if (!(form instanceof HTMLFormElement) || !form.closest) {
    return;
  }
  const container = form.closest('[data-newsletter-campaigns-listing]');
  if (!container) {
    return;
  }
  const controller = controllers.get(container);
  if (!controller) {
    return;
  }
  const detail = event.detail || {};
  const result = detail.result || null;
  const data = result && result.data ? result.data : null;
  if (!data || typeof data !== 'object') {
    return;
  }
  if (form.hasAttribute('data-newsletter-campaigns-edit-form')) {
    const modal = document.getElementById('newsletterCampaignEditModal');
    if (modal && typeof bootstrap !== 'undefined' && typeof bootstrap.Modal !== 'undefined') {
      const instance = bootstrap.Modal.getInstance(modal);
      if (instance) {
        instance.hide();
      }
    }
  }
  if (data.csrf) {
    container.setAttribute('data-newsletter-campaigns-csrf', String(data.csrf));
  }
  if (data.refreshUrl) {
    controller.reload(data.refreshUrl);
    return;
  }
  if (data.redirect) {
    window.location.assign(data.redirect);
  }
}

export function getNewsletterCampaignsController(container) {
  return controllers.get(container);
}
