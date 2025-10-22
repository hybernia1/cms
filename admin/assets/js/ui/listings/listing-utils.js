export function setListingLoadingState(container, isLoading) {
  if (!container) {
    return;
  }
  if (isLoading) {
    container.classList.add('is-loading');
    container.setAttribute('aria-busy', 'true');
  } else {
    container.classList.remove('is-loading');
    container.removeAttribute('aria-busy');
  }
}

export function cleanListingUrl(url) {
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

export function buildListingJsonUrl(url, params = {}, options = {}) {
  try {
    const parsed = new URL(url, window.location.href);
    const skipExisting = Array.isArray(options.skipExisting) ? options.skipExisting : [];
    if (params && typeof params === 'object') {
      Object.keys(params).forEach(function (key) {
        const value = params[key];
        if (value === undefined || value === null || value === '') {
          return;
        }
        if (skipExisting.indexOf(key) !== -1 && parsed.searchParams.has(key)) {
          return;
        }
        parsed.searchParams.set(key, String(value));
      });
    }
    parsed.searchParams.set('format', 'json');
    return parsed.toString();
  } catch (err) {
    return url;
  }
}

export function pushListingHistory(url, transformState) {
  if (!url || !window.history || typeof window.history.pushState !== 'function') {
    return;
  }
  try {
    const parsed = new URL(url, window.location.href);
    parsed.searchParams.delete('format');
    const currentState = window.history.state;
    let nextState = null;
    if (typeof transformState === 'function') {
      nextState = transformState(currentState);
    }
    if (!nextState || typeof nextState !== 'object') {
      if (currentState && typeof currentState === 'object') {
        nextState = Object.assign({}, currentState);
      } else {
        nextState = {};
      }
    }
    window.history.pushState(nextState, '', parsed.toString());
  } catch (err) {
    /* ignore history errors */
  }
}
