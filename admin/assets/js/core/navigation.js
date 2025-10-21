import { adminAjax, configureAjax, normalizeUrl } from './ajax.js';

export const HISTORY_STATE_KEY = 'cmsAdminAjax';

let activeNavigation = null;

export function buildHistoryState(extra) {
  const state = {};
  state[HISTORY_STATE_KEY] = true;
  if (extra && typeof extra === 'object') {
    Object.keys(extra).forEach(function (key) {
      state[key] = extra[key];
    });
  }
  return state;
}

export function dispatchNavigated(url, options) {
  const detail = {
    url: url,
    root: options && options.root ? options.root : document,
    initial: !!(options && options.initial),
    source: options && options.source ? options.source : 'navigation'
  };
  try {
    document.dispatchEvent(new CustomEvent('cms:admin:navigated', { detail: detail }));
  } catch (err) {
    try {
      const legacyEvent = document.createEvent('CustomEvent');
      legacyEvent.initCustomEvent('cms:admin:navigated', true, true, detail);
      document.dispatchEvent(legacyEvent);
    } catch (legacyError) {
      try {
        const fallback = document.createEvent('Event');
        fallback.initEvent('cms:admin:navigated', true, true);
        fallback.detail = detail;
        document.dispatchEvent(fallback);
      } catch (ignored) {
        /* noop */
      }
    }
  }
}

export function loadAdminPage(url, options) {
  options = options || {};
  const targetUrl = normalizeUrl(url);
  if (!targetUrl) {
    return Promise.resolve();
  }

  if (activeNavigation && activeNavigation.controller) {
    activeNavigation.controller.abort();
  }

  const controller = new AbortController();
  activeNavigation = { url: targetUrl, controller: controller };

  document.documentElement.classList.add('is-admin-loading');

  let appliedRoot = null;

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

    const root = result.htmlRoot || appliedRoot;
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

export function bootHistory() {
  if (!window.history.state || !window.history.state[HISTORY_STATE_KEY]) {
    window.history.replaceState(buildHistoryState(), '', window.location.href);
  }
  window.addEventListener('popstate', function (event) {
    if (event.state && event.state[HISTORY_STATE_KEY]) {
      loadAdminPage(window.location.href, { replaceHistory: true, fromPopstate: true });
    }
  });
}

configureAjax({ loadAdminPage });
