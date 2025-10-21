import { initFlashMessages, notifier } from './core/notifier.js';
import { adminAjax, configureAjax, normalizeUrl } from './core/ajax.js';
import { initConfirmModals } from './ui/confirm-modal.js';
import { initTooltips } from './ui/tooltips.js';
import {
  registerFormHelper,
  unregisterFormHelper,
  initFormHelpers,
  clearFormValidation,
  applyFormValidationErrors,
  ensureDefaultFormHelpers
} from './core/form-helpers.js';
import { dispatchFormEvent } from './core/form-events.js';
import {
  HISTORY_STATE_KEY,
  buildHistoryState,
  dispatchNavigated,
  loadAdminPage,
  bootHistory
} from './core/navigation.js';
import { initThemesPage } from './ui/themes-page.js';
import { initNavigationUI } from './ui/navigation/index.js';

  var adminMenuMediaQuery = null;
  var bulkFormStateUpdaters = new WeakMap();
  var commentsListingControllers = new WeakMap();

  function isAjaxForm(el) {
    return el && el.hasAttribute && el.hasAttribute('data-ajax');
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

  function refreshDynamicUI(root) {
    initFlashMessages(root);
    initFormHelpers(root);
    initTooltips(root);
    initBulkForms(root);
    initCommentsListing(root);
    initTermsListing(root);
    initTermsForm(root);
    initPostsListing(root);
    initMediaLibrary(root);
    initQuickDraftWidget(root);
    initAdminMenuToggle(root);
    initNavigationUI(root);
    initThemesPage(root);
  }

  configureAjax({ refreshDynamicUI: refreshDynamicUI });

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

  function updateUsersListingState(container, listing) {
    if (!container || !listing || typeof listing !== 'object') {
      return;
    }
    if (listing.url) {
      container.setAttribute('data-users-url', String(listing.url));
    }
    if (listing.page !== undefined && listing.page !== null) {
      container.setAttribute('data-users-page', String(listing.page));
    }
    if (listing.searchQuery !== undefined && listing.searchQuery !== null) {
      container.setAttribute('data-users-query', String(listing.searchQuery));
    }
    var bulkForm = container.querySelector('form[data-bulk-form]');
    if (bulkForm) {
      var pageInput = bulkForm.querySelector('input[name="page"]');
      if (pageInput) {
        var nextPage = listing.page !== undefined && listing.page !== null ? listing.page : (container.getAttribute('data-users-page') || '1');
        pageInput.value = String(nextPage);
      }
      var queryInput = bulkForm.querySelector('input[name="q"]');
      if (queryInput) {
        queryInput.value = listing.searchQuery !== undefined && listing.searchQuery !== null ? String(listing.searchQuery) : '';
      }
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
        window.history.pushState(buildHistoryState(window.history.state), '', parsed.toString());
      } catch (err) {
        /* ignore history errors */
      }
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
      triggerBulkFormUpdate(container);
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
        if (value instanceof File) {
          return;
        }
        params.set(key, value);
      });
      if (submitter && submitter.name) {
        params.set(submitter.name, submitter.value);
      }

      var action = form.getAttribute('action') || window.location.href;
      var urlObj;
      try {
        urlObj = new URL(action, window.location.href);
      } catch (err) {
        urlObj = new URL(window.location.href);
      }
      urlObj.search = params.toString();

      return handleRequest(urlObj.toString()).then(function (result) {
        dispatchFormEvent(form, 'cms:admin:form:success', { result: result || null });
        return result;
      }).catch(function (error) {
        if (error && error.name === 'AbortError') {
          return null;
        }
        dispatchFormEvent(form, 'cms:admin:form:error', { error: error });
        throw error;
      }).finally(function () {
        form.classList.remove('is-submitting');
        delete form.dataset.commentsSubmitting;
        if (restoreDisabled) {
          submitter.disabled = false;
        }
      });
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

    function handleSubmit(event) {
      var form = event.target;
      if (!(form instanceof HTMLFormElement)) {
        return;
      }
      if (!container.contains(form)) {
        return;
      }
      var formType = form.getAttribute('data-comments-form');
      var isSearch = form.getAttribute('role') === 'search' && form.closest('[data-comments-toolbar]');
      if (!formType && !isSearch) {
        return;
      }
      event.preventDefault();
      if (event.stopPropagation) {
        event.stopPropagation();
      }
      if (event.stopImmediatePropagation) {
        event.stopImmediatePropagation();
      }
      submitGetForm(form, event.submitter || null);
    }

    function shouldHandleAnchor(anchor) {
      if (!anchor || !container.contains(anchor)) {
        return false;
      }
      if (anchor.target && anchor.target !== '_self') {
        return false;
      }
      if (anchor.classList && anchor.classList.contains('disabled')) {
        return false;
      }
      var ariaDisabled = anchor.getAttribute('aria-disabled');
      if (ariaDisabled && ariaDisabled.toLowerCase() === 'true') {
        return false;
      }
      if (anchor.closest('[data-comments-pagination]')) {
        return true;
      }
      if (anchor.closest('[data-comments-toolbar]')) {
        return true;
      }
      if (anchor.closest('[data-comments-filters]')) {
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
      var href = anchor.getAttribute('href');
      if (!href) {
        return;
      }
      handleRequest(href);
    }

    function cleanup() {
      if (state.pending && typeof state.pending.abort === 'function') {
        state.pending.abort();
      }
      container.removeEventListener('click', handleClick, true);
      container.removeEventListener('submit', handleSubmit, true);
      if (state.searchForm && state.searchHandler) {
        state.searchForm.removeEventListener('submit', state.searchHandler, true);
      }
      commentsListingControllers.delete(container);
      document.removeEventListener('cms:admin:navigated', onNavigate);
    }

    function onNavigate() {
      if (!document.body.contains(container)) {
        cleanup();
      }
    }

    container.addEventListener('click', handleClick, true);
    container.addEventListener('submit', handleSubmit, true);
    bindSearchForm();
    document.addEventListener('cms:admin:navigated', onNavigate);

    return {
      reload: function () {
        var target = container.getAttribute('data-comments-url') || state.lastUrl;
        return handleRequest(target);
      },
      dispose: cleanup
    };
  }

  function initCommentsListing(root) {
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

  var termsListingControllers = new WeakMap();

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
    triggerBulkFormUpdate(container);
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

    function finalize(data, sourceUrl) {
      if (!data || typeof data !== 'object') {
        return;
      }

      applyPartials(data.partials || null);
      bindSearchForm();
      refreshDynamicUI(container);

      if (data.filters && typeof data.filters === 'object' && Object.prototype.hasOwnProperty.call(data.filters, 'q')) {
        container.dataset.termsQuery = String(data.filters.q || '');
      }
      if (data.pagination && typeof data.pagination === 'object' && Object.prototype.hasOwnProperty.call(data.pagination, 'page')) {
        container.dataset.termsPage = String(data.pagination.page || '');
      }
      if (data.type) {
        container.dataset.termsType = String(data.type);
      }
      if (data.csrf) {
        container.dataset.termsCsrf = String(data.csrf);
      }

      var nextUrl = sourceUrl || state.lastRequestedUrl || container.getAttribute('data-terms-url') || window.location.href;
      var cleaned = cleanUrl(nextUrl);
      if (cleaned) {
        container.dataset.termsUrl = cleaned;
        updateHistory(cleaned);
      }
    }

    function shouldHandleAnchor(anchor) {
      if (!anchor || !container.contains(anchor)) {
        return false;
      }
      if (anchor.target && anchor.target !== '_self') {
        return false;
      }
      if (anchor.classList && anchor.classList.contains('disabled')) {
        return false;
      }
      var ariaDisabled = anchor.getAttribute('aria-disabled');
      if (ariaDisabled && ariaDisabled.toLowerCase() === 'true') {
        return false;
      }
      if (anchor.closest('[data-terms-pagination]')) {
        return true;
      }
      var searchForm = anchor.closest('form[role="search"]');
      return !!(searchForm && container.contains(searchForm));
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
      var href = anchor.getAttribute('href');
      if (!href) {
        return;
      }
      handleRequest(href);
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

      var method = (form.getAttribute('method') || 'GET').toUpperCase();
      var action = form.getAttribute('action') || window.location.href;

      var promise;
      if (method === 'GET') {
        var params = new URLSearchParams();
        var formData = new FormData(form);
        formData.forEach(function (value, key) {
          if (value instanceof File) {
            return;
          }
          params.set(key, value);
        });
        if (submitter && submitter.name) {
          params.set(submitter.name, submitter.value);
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

  function initTermsListing(root) {
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

  function initTermsForm(root) {
    var scope = root || document;
    var forms = [].slice.call(scope.querySelectorAll('[data-term-form]'));
    forms.forEach(function (form) {
      if (form.dataset.termFormBound === '1') {
        return;
      }
      form.dataset.termFormBound = '1';
      form.addEventListener('cms:admin:form:success', function (event) {
        var detail = event && event.detail ? event.detail : {};
        var result = detail.result || null;
        var data = result && result.data ? result.data : null;
        if (!data || typeof data !== 'object' || !data.term) {
          return;
        }
        var term = data.term;
        var slugInput = form.querySelector('[name="slug"]');
        if (slugInput && typeof slugInput.value !== 'undefined' && term.slug !== undefined) {
          slugInput.value = String(term.slug);
        }
        var nameInput = form.querySelector('[name="name"]');
        if (nameInput && typeof nameInput.value !== 'undefined' && term.name !== undefined) {
          nameInput.value = String(term.name);
        }
      });
    });
  }

  document.addEventListener('cms:admin:form:success', function (event) {
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
  });

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

  document.addEventListener('cms:admin:form:success', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.closest) {
      return;
    }
    var container = form.closest('[data-users-listing]');
    if (!container) {
      return;
    }
    var detail = event.detail || {};
    var result = detail.result || null;
    var data = result && result.data ? result.data : null;
    if (data && typeof data === 'object' && data.listing) {
      updateUsersListingState(container, data.listing);
    }
    triggerBulkFormUpdate(container);
  });

  document.addEventListener('cms:admin:form:success', function (event) {
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
  });

  document.addEventListener('cms:admin:form:success', function (event) {
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
  });

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

  function initMediaLibrary(root) {
    var scope = root || document;
    var containers = [].slice.call(scope.querySelectorAll('[data-media-library]'));
    containers.forEach(function (container) {
      if (container.dataset.mediaLibraryBound === '1') {
        updateContextInputs(container);
        refreshMediaThumbnails(container);
        refreshMediaCopyButtons(container);
        refreshMediaActionForms(container);
        return;
      }
      container.dataset.mediaLibraryBound = '1';

      var state = {
        overlay: container.querySelector('[data-media-overlay]') || null,
        pending: null,
        modal: null,
        upload: null
      };

      function getContext() {
        return {
          filters: {
            type: container.getAttribute('data-media-type') || '',
            q: container.getAttribute('data-media-query') || ''
          },
          pagination: {
            page: parseInt(container.getAttribute('data-media-page') || '1', 10) || 1,
            per_page: parseInt(container.getAttribute('data-media-per-page') || '30', 10) || 30
          }
        };
      }

      function serializeContext() {
        try {
          return JSON.stringify(getContext());
        } catch (err) {
          return '{}';
        }
      }

      function updateContextInputs(scopeElement) {
        var target = scopeElement || container;
        var value = serializeContext();
        var inputs = [].slice.call(target.querySelectorAll('[data-media-context-input]'));
        inputs.forEach(function (input) {
          if (input instanceof HTMLInputElement) {
            input.value = value;
          }
        });
      }

      function setLoading(isLoading) {
        if (state.overlay) {
          if (isLoading) {
            state.overlay.classList.remove('d-none');
          } else {
            state.overlay.classList.add('d-none');
          }
        }
        if (isLoading) {
          container.classList.add('is-loading');
          container.setAttribute('aria-busy', 'true');
        } else {
          container.classList.remove('is-loading');
          container.removeAttribute('aria-busy');
        }
      }

      function ensureMediaModal() {
        if (state.modal) {
          return state.modal;
        }
        var modalEl = document.getElementById('mediaDetailModal');
        if (!modalEl) {
          return null;
        }
        if (modalEl._mediaModalState) {
          state.modal = modalEl._mediaModalState;
          return state.modal;
        }

        var preview = modalEl.querySelector('[data-role="preview"]');
        var fields = {
          id: modalEl.querySelector('[data-field="id"]'),
          type: modalEl.querySelector('[data-field="type"]'),
          mime: modalEl.querySelector('[data-field="mime"]'),
          dimensions: modalEl.querySelector('[data-field="dimensions"]'),
          size: modalEl.querySelector('[data-field="size"]'),
          created: modalEl.querySelector('[data-field="created"]'),
          author: modalEl.querySelector('[data-field="author"]')
        };
        var links = {
          original: modalEl.querySelector('[data-link="original"]'),
          webp: modalEl.querySelector('[data-link="webp"]')
        };
        var usageSection = modalEl.querySelector('[data-role="usage"]');
        var usageField = usageSection ? usageSection.querySelector('[data-field="usage"]') : null;
        var defaultUsageText = 'Médium zatím není připojeno k žádnému příspěvku.';
        var modalInstance = (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal === 'function')
          ? bootstrap.Modal.getOrCreateInstance(modalEl)
          : null;

        function updateField(key, value) {
          if (!fields[key]) {
            return;
          }
          fields[key].textContent = value && value !== '' ? value : '—';
        }

        function updateLink(key, url, hiddenClass) {
          var el = links[key];
          if (!el) {
            return;
          }
          if (url) {
            el.href = url;
            el.removeAttribute('aria-disabled');
            if (hiddenClass) {
              el.classList.remove(hiddenClass);
            }
          } else {
            el.removeAttribute('href');
            el.setAttribute('aria-disabled', 'true');
            if (hiddenClass) {
              el.classList.add(hiddenClass);
            }
          }
        }

        function renderUsage(references) {
          if (!usageField) {
            return;
          }
          usageField.innerHTML = '';
          var thumbs = Array.isArray(references && references.thumbnails) ? references.thumbnails : [];
          var content = Array.isArray(references && references.content) ? references.content : [];
          if (!thumbs.length && !content.length) {
            usageField.textContent = defaultUsageText;
            return;
          }
          function appendList(title, items, includeRole) {
            if (!items.length) {
              return;
            }
            var heading = document.createElement('div');
            heading.className = 'fw-semibold mb-1';
            heading.textContent = title;
            usageField.appendChild(heading);

            var list = document.createElement('ul');
            list.className = 'list-unstyled mb-2';
            items.forEach(function (item) {
              var li = document.createElement('li');
              li.className = 'mb-1';
              var titleText = (item && typeof item.title === 'string' && item.title.trim() !== '') ? item.title : 'Bez názvu';
              var prefix = item && item.id ? '#' + item.id + ' – ' : '';
              if (item && item.editUrl) {
                var link = document.createElement('a');
                link.href = item.editUrl;
                link.className = 'text-decoration-none';
                link.textContent = prefix + titleText;
                link.setAttribute('data-no-ajax', 'true');
                li.appendChild(link);
              } else {
                li.textContent = prefix + titleText;
              }
              var metaParts = [];
              if (item && item.typeLabel) metaParts.push(item.typeLabel);
              if (item && item.statusLabel) metaParts.push(item.statusLabel);
              if (includeRole && item && item.roleLabel) metaParts.push(item.roleLabel);
              if (metaParts.length) {
                var meta = document.createElement('div');
                meta.className = 'text-secondary small';
                meta.textContent = metaParts.join(' • ');
                li.appendChild(meta);
              }
              list.appendChild(li);
            });
            usageField.appendChild(list);
          }
          appendList('Náhled příspěvku', thumbs, false);
          appendList('V obsahu', content, true);
          if (usageField.lastElementChild && usageField.lastElementChild.tagName === 'UL') {
            usageField.lastElementChild.classList.remove('mb-2');
            usageField.lastElementChild.classList.add('mb-0');
          }
        }

        function open(data) {
          if (!data) {
            return;
          }
          if (preview) {
            preview.innerHTML = '';
            if (data.displayUrl) {
              var img = document.createElement('img');
              img.src = data.displayUrl;
              img.alt = 'media detail';
              img.style.maxWidth = '100%';
              img.style.maxHeight = '320px';
              img.style.objectFit = 'contain';
              preview.appendChild(img);
            } else {
              var placeholder = document.createElement('div');
              placeholder.className = 'text-secondary';
              placeholder.textContent = 'Náhled není k dispozici.';
              preview.appendChild(placeholder);
            }
          }
          var dimensions = data.width && data.height ? data.width + ' × ' + data.height + 'px' : '';
          var authorParts = [];
          if (data.authorName) authorParts.push(data.authorName);
          if (data.authorEmail) authorParts.push('(' + data.authorEmail + ')');
          updateField('id', data.id ? '#' + data.id : '');
          updateField('type', data.typeLabel || data.type || '');
          updateField('mime', data.mime || '');
          updateField('dimensions', dimensions);
          updateField('size', data.sizeHuman || (data.sizeBytes ? data.sizeBytes + ' B' : ''));
          updateField('created', data.created || '');
          updateField('author', authorParts.length ? authorParts.join(' ') : '');
          updateLink('original', data.url || '', 'disabled');
          updateLink('webp', data.webpUrl || '', 'd-none');
          renderUsage(data.references || null);
          if (modalInstance) {
            modalInstance.show();
          }
        }

        var api = { open: open };
        modalEl._mediaModalState = api;
        modalEl.dataset.mediaModalBound = '1';
        state.modal = api;
        return api;
      }

      function initUploadModal() {
        if (state.upload) {
          return state.upload;
        }
        var modalEl = document.getElementById('mediaUploadModal');
        var form = document.getElementById('media-upload-form');
        var fileInput = document.getElementById('media-upload-input');
        var dropzone = document.getElementById('media-upload-dropzone');
        var browseBtn = document.getElementById('media-upload-browse');
        var summary = document.getElementById('media-upload-summary');
        var submitBtn = document.getElementById('media-upload-submit');

        if (!modalEl || !form || !fileInput || !summary || !submitBtn) {
          return null;
        }
        if (modalEl.dataset.mediaUploadBound === '1') {
          state.upload = modalEl._mediaUploadState || null;
          return state.upload;
        }

        function formatFileSize(bytes) {
          if (!Number.isFinite(bytes) || bytes <= 0) {
            return '';
          }
          var units = ['B', 'KB', 'MB', 'GB', 'TB'];
          var value = bytes;
          var index = 0;
          while (value >= 1024 && index < units.length - 1) {
            value /= 1024;
            index += 1;
          }
          return index === 0 ? Math.round(value) + ' ' + units[index] : value.toFixed(1) + ' ' + units[index];
        }

        function renderSummary() {
          var files = fileInput.files;
          summary.innerHTML = '';
          if (!files || files.length === 0) {
            summary.classList.add('d-none');
            submitBtn.disabled = true;
            return;
          }
          var list = document.createElement('ul');
          list.className = 'list-unstyled mb-2';
          Array.from(files).forEach(function (file) {
            var li = document.createElement('li');
            li.className = 'mb-1';
            var sizeText = typeof file.size === 'number' ? formatFileSize(file.size) : '';
            li.textContent = file.name + (sizeText ? ' (' + sizeText + ')' : '');
            list.appendChild(li);
          });
          summary.appendChild(list);
          summary.classList.remove('d-none');
          submitBtn.disabled = false;
        }

        function resetUpload() {
          try { fileInput.value = ''; } catch (err) {}
          summary.innerHTML = '';
          summary.classList.add('d-none');
          submitBtn.disabled = true;
        }

        fileInput.addEventListener('change', renderSummary);

        if (browseBtn) {
          browseBtn.addEventListener('click', function (evt) {
            evt.preventDefault();
            fileInput.click();
          });
        }

        if (dropzone) {
          dropzone.addEventListener('dragover', function (evt) {
            evt.preventDefault();
            dropzone.classList.add('is-dragover');
          });
          dropzone.addEventListener('dragleave', function () {
            dropzone.classList.remove('is-dragover');
          });
          dropzone.addEventListener('dragend', function () {
            dropzone.classList.remove('is-dragover');
          });
          dropzone.addEventListener('drop', function (evt) {
            evt.preventDefault();
            dropzone.classList.remove('is-dragover');
            if (!evt.dataTransfer || !evt.dataTransfer.files || evt.dataTransfer.files.length === 0) {
              return;
            }
            var files = evt.dataTransfer.files;
            try {
              var dt = new DataTransfer();
              Array.from(files).forEach(function (file) { dt.items.add(file); });
              fileInput.files = dt.files;
            } catch (err) {
              try { fileInput.files = files; } catch (e) {}
            }
            renderSummary();
          });
          dropzone.addEventListener('click', function (evt) {
            if (browseBtn && (evt.target === browseBtn || browseBtn.contains(evt.target))) {
              return;
            }
            fileInput.click();
          });
        }

        modalEl.addEventListener('show.bs.modal', resetUpload);
        modalEl.addEventListener('hidden.bs.modal', resetUpload);

        resetUpload();

        var api = {
          reset: resetUpload,
          renderSummary: renderSummary,
          modal: modalEl,
          form: form
        };
        modalEl.dataset.mediaUploadBound = '1';
        modalEl._mediaUploadState = api;
        state.upload = api;
        return api;
      }

      function refreshMediaThumbnails(scopeElement) {
        var target = scopeElement || container;
        var buttons = [].slice.call(target.querySelectorAll('.media-thumb'));
        buttons.forEach(function (btn) {
          if (btn.dataset.mediaThumbBound === '1') {
            return;
          }
          btn.dataset.mediaThumbBound = '1';
          btn.addEventListener('click', function () {
            var modalState = ensureMediaModal();
            if (!modalState || typeof modalState.open !== 'function') {
              return;
            }
            var data = {};
            try {
              data = JSON.parse(btn.getAttribute('data-media') || '{}');
            } catch (err) {
              data = {};
            }
            modalState.open(data);
          });
        });
      }

      function copyToClipboard(text, button) {
        if (!text) {
          return;
        }
        var restore = null;
        if (button && typeof button.textContent === 'string') {
          restore = button.textContent;
        }
        function setCopied() {
          if (button && restore !== null) {
            button.textContent = 'Zkopírováno';
            window.setTimeout(function () {
              button.textContent = restore;
            }, 2000);
          }
        }
        function fallback() {
          var input = document.createElement('textarea');
          input.value = text;
          input.setAttribute('readonly', 'true');
          input.style.position = 'fixed';
          input.style.opacity = '0';
          document.body.appendChild(input);
          input.select();
          try { document.execCommand('copy'); } catch (err) {}
          document.body.removeChild(input);
          setCopied();
        }
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
          navigator.clipboard.writeText(text).then(setCopied).catch(function () {
            fallback();
          });
          return;
        }
        fallback();
      }

      function refreshMediaCopyButtons(scopeElement) {
        var target = scopeElement || container;
        var buttons = [].slice.call(target.querySelectorAll('[data-media-copy]'));
        buttons.forEach(function (btn) {
          if (btn.dataset.mediaCopyBound === '1') {
            return;
          }
          btn.dataset.mediaCopyBound = '1';
          btn.addEventListener('click', function () {
            var url = btn.getAttribute('data-media-copy') || '';
            copyToClipboard(url, btn);
          });
        });
      }

      function removeMediaItem(id) {
        var selector = '[data-media-item][data-media-item-id="' + cssEscapeValue(String(id)) + '"]';
        var element = container.querySelector(selector);
        if (element && element.parentNode) {
          element.parentNode.removeChild(element);
        }
        var grid = container.querySelector('[data-media-grid]');
        if (!grid) {
          return;
        }
        var itemsWrapper = grid.querySelector('[data-media-grid-items]');
        if (itemsWrapper && itemsWrapper.children.length > 0) {
          return;
        }
        grid.innerHTML = '<div class="text-secondary" data-media-empty><i class="bi bi-inbox me-1"></i>Žádná média.</div>';
      }

      function insertMediaCards(htmlMap, mode) {
        if (!htmlMap || typeof htmlMap !== 'object') {
          return;
        }
        var grid = container.querySelector('[data-media-grid]');
        if (!grid) {
          return;
        }
        var itemsWrapper = grid.querySelector('[data-media-grid-items]');
        if (!itemsWrapper) {
          grid.innerHTML = '';
          itemsWrapper = document.createElement('div');
          itemsWrapper.className = 'row g-3';
          itemsWrapper.setAttribute('data-media-grid-items', '');
          grid.appendChild(itemsWrapper);
        }
        var empty = grid.querySelector('[data-media-empty]');
        if (empty && empty.parentNode) {
          empty.parentNode.removeChild(empty);
        }
        Object.keys(htmlMap).forEach(function (key) {
          var html = htmlMap[key];
          if (typeof html !== 'string') {
            return;
          }
          var wrapper = document.createElement('div');
          wrapper.innerHTML = html.trim();
          var element = wrapper.firstElementChild;
          if (!element) {
            return;
          }
          if (mode === 'append') {
            itemsWrapper.appendChild(element);
          } else {
            itemsWrapper.insertBefore(element, itemsWrapper.firstChild);
          }
          refreshDynamicUI(element);
          refreshMediaActionForms(element);
          refreshMediaThumbnails(element);
          refreshMediaCopyButtons(element);
        });
      }

      function replaceMediaCard(id, html) {
        var selector = '[data-media-item][data-media-item-id="' + cssEscapeValue(String(id)) + '"]';
        var element = container.querySelector(selector);
        if (!element || !element.parentNode) {
          if (html && typeof html === 'string') {
            var map = {};
            map[String(id)] = html;
            insertMediaCards(map, 'prepend');
          }
          return;
        }
        if (!html || typeof html !== 'string') {
          return;
        }
        var wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        var newElement = wrapper.firstElementChild;
        if (!newElement) {
          return;
        }
        element.replaceWith(newElement);
        refreshDynamicUI(newElement);
        refreshMediaActionForms(newElement);
        refreshMediaThumbnails(newElement);
        refreshMediaCopyButtons(newElement);
      }

      function applyContext(context) {
        if (!context || typeof context !== 'object') {
          return;
        }
        if (context.filters && typeof context.filters === 'object') {
          if (Object.prototype.hasOwnProperty.call(context.filters, 'type')) {
            container.setAttribute('data-media-type', String(context.filters.type || ''));
          }
          if (Object.prototype.hasOwnProperty.call(context.filters, 'q')) {
            container.setAttribute('data-media-query', String(context.filters.q || ''));
          }
        }
        if (context.pagination && typeof context.pagination === 'object') {
          if (Object.prototype.hasOwnProperty.call(context.pagination, 'page')) {
            container.setAttribute('data-media-page', String(context.pagination.page || '1'));
          }
          if (Object.prototype.hasOwnProperty.call(context.pagination, 'per_page')) {
            container.setAttribute('data-media-per-page', String(context.pagination.per_page || '30'));
          }
        }
        updateContextInputs(container);
      }

      function handleActionSuccess(form, data) {
        if (!data || typeof data !== 'object') {
          data = {};
        }
        var action = (form.getAttribute('data-media-action') || '').toLowerCase();
        if (action === 'upload') {
          if (typeof form.reset === 'function') {
            form.reset();
          }
          if (data.html && data.html.items) {
            insertMediaCards(data.html.items, 'prepend');
          }
          var uploadState = state.upload || initUploadModal();
          if (uploadState) {
            uploadState.reset();
            if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal === 'function') {
              bootstrap.Modal.getOrCreateInstance(uploadState.modal).hide();
            }
          }
        } else if (action === 'delete') {
          if (Object.prototype.hasOwnProperty.call(data, 'removedId')) {
            removeMediaItem(data.removedId);
          }
        } else if (action === 'optimize') {
          if (data.html && data.html.items) {
            Object.keys(data.html.items).forEach(function (key) {
              replaceMediaCard(key, data.html.items[key]);
            });
          }
        }
        if (data.context) {
          applyContext(data.context);
        } else {
          updateContextInputs(container);
        }
      }

      function refreshMediaActionForms(scopeElement) {
        var target = scopeElement || container;
        var forms = [].slice.call(target.querySelectorAll('form[data-media-action]'));
        forms.forEach(function (form) {
          if (form.dataset.mediaActionBound === '1') {
            return;
          }
          form.dataset.mediaActionBound = '1';
          form.addEventListener('submit', function () {
            updateContextInputs(form);
            setLoading(true);
          }, true);
          form.addEventListener('cms:admin:form:success', function (event) {
            setLoading(false);
            var detail = event && event.detail ? event.detail : {};
            var result = detail.result || null;
            var responseData = result && result.data ? result.data : null;
            handleActionSuccess(form, responseData || {});
          });
          form.addEventListener('cms:admin:form:error', function () {
            setLoading(false);
          });
        });
      }

      function applyListingResponse(data, sourceUrl, pushState) {
        if (!data || typeof data !== 'object') {
          return;
        }
        if (data.html && typeof data.html === 'object') {
          var grid = container.querySelector('[data-media-grid]');
          if (grid && typeof data.html.grid === 'string') {
            grid.innerHTML = data.html.grid;
          }
          var paginationWrapper = container.querySelector('[data-media-pagination]');
          if (paginationWrapper && typeof data.html.pagination === 'string') {
            paginationWrapper.innerHTML = data.html.pagination;
          }
        }
        if (data.filters || data.pagination) {
          applyContext({ filters: data.filters || null, pagination: data.pagination || null });
        } else {
          updateContextInputs(container);
        }
        if (sourceUrl) {
          container.setAttribute('data-media-url', sourceUrl);
          if (pushState) {
            try {
              var parsed = new URL(sourceUrl, window.location.href);
              var stateObj = window.history && typeof window.history.state === 'object' ? Object.assign({}, window.history.state) : {};
              stateObj[HISTORY_STATE_KEY] = true;
              if (window.history && typeof window.history.pushState === 'function') {
                window.history.pushState(stateObj, '', parsed.toString());
              }
            } catch (err) {
              /* ignore */
            }
          }
        }
        refreshDynamicUI(container);
        refreshMediaActionForms(container);
        refreshMediaThumbnails(container);
        refreshMediaCopyButtons(container);
      }

      function requestListing(url, options) {
        options = options || {};
        if (!url) {
          return Promise.resolve();
        }
        if (state.pending && state.pending.controller && typeof state.pending.controller.abort === 'function') {
          state.pending.controller.abort();
        }
        var controller = typeof AbortController === 'function' ? new AbortController() : null;
        state.pending = controller ? { controller: controller } : { controller: null };
        setLoading(true);
        return adminAjax.get(url, {
          headers: { 'Accept': 'application/json' },
          context: container,
          signal: controller ? controller.signal : undefined
        }).then(function (result) {
          state.pending = null;
          setLoading(false);
          if (!result || !result.data) {
            return;
          }
          if (result.data.redirect) {
            return;
          }
          applyListingResponse(result.data, url, options.pushState !== false);
        }).catch(function (error) {
          if (state.pending && state.pending.controller === controller) {
            state.pending = null;
          }
          setLoading(false);
          if (error && error.name === 'AbortError') {
            return;
          }
        });
      }

      function handleFilterSubmit(event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) {
          return;
        }
        event.preventDefault();
        var method = (form.getAttribute('method') || 'GET').toUpperCase();
        if (method !== 'GET') {
          return;
        }
        var action = form.getAttribute('action') || window.location.href;
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
        params.forEach(function (value, key) {
          urlObj.searchParams.set(key, value);
        });
        requestListing(urlObj.toString(), { pushState: true });
      }

      function handlePaginationClick(event) {
        var anchor = event.target && event.target.closest ? event.target.closest('a[data-media-page-link]') : null;
        if (!anchor || !container.contains(anchor)) {
          return;
        }
        event.preventDefault();
        if (event.stopPropagation) {
          event.stopPropagation();
        }
        if (event.stopImmediatePropagation) {
          event.stopImmediatePropagation();
        }
        requestListing(anchor.href, { pushState: true });
      }

      updateContextInputs(container);
      refreshMediaThumbnails(container);
      refreshMediaCopyButtons(container);
      refreshMediaActionForms(container);
      initUploadModal();

      var filterForm = container.querySelector('[data-media-filter-form]');
      if (filterForm && filterForm.dataset.mediaFilterBound !== '1') {
        filterForm.dataset.mediaFilterBound = '1';
        filterForm.addEventListener('submit', handleFilterSubmit);
      }

      container.addEventListener('click', handlePaginationClick, true);

      document.addEventListener('cms:admin:navigated', function cleanup() {
        if (!document.body.contains(container)) {
          if (state.pending && state.pending.controller && typeof state.pending.controller.abort === 'function') {
            state.pending.controller.abort();
          }
          state.pending = null;
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

        clearFormValidation(form);

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



  export const cmsAdmin = {
    load: loadAdminPage,
    refresh: refreshDynamicUI,
    ajax: adminAjax,
    notify: notifier.notify,
    forms: {
      registerHelper: registerFormHelper,
      unregisterHelper: unregisterFormHelper,
      applyHelpers: initFormHelpers,
      clearValidation: clearFormValidation,
      applyValidation: applyFormValidationErrors,
      ensureDefaults: ensureDefaultFormHelpers
    }
  };

  export {
    refreshDynamicUI,
    loadAdminPage,
    initAjaxForms,
    initAjaxLinks,
    bootHistory,
    dispatchNavigated,
    adminAjax,
    notifier,
    registerFormHelper,
    unregisterFormHelper,
    initFormHelpers,
    clearFormValidation,
    applyFormValidationErrors,
    ensureDefaultFormHelpers
  };
