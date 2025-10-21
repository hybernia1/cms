import { notifier } from './notifier.js';

const ajaxConfig = {
  refreshDynamicUI: null,
  loadAdminPage: null
};

export function configureAjax(options) {
  if (!options || typeof options !== 'object') {
    return;
  }
  if (typeof options.refreshDynamicUI === 'function') {
    ajaxConfig.refreshDynamicUI = options.refreshDynamicUI;
  }
  if (typeof options.loadAdminPage === 'function') {
    ajaxConfig.loadAdminPage = options.loadAdminPage;
  }
}

function callRefreshDynamicUI(root) {
  if (root && typeof ajaxConfig.refreshDynamicUI === 'function') {
    ajaxConfig.refreshDynamicUI(root);
  }
}

function getLoadAdminPage() {
  return typeof ajaxConfig.loadAdminPage === 'function'
    ? ajaxConfig.loadAdminPage
    : null;
}

export function normalizeUrl(url) {
  try {
    return new URL(url, window.location.href).toString();
  } catch (e) {
    return url;
  }
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
    const parts = [];
    Object.keys(errors).forEach(function (key) {
      const value = errors[key];
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

function executeScripts(root) {
  if (!root) {
    return;
  }
  const scripts = [].slice.call(root.querySelectorAll('script'));
  scripts.forEach(function (oldScript) {
    if (!oldScript || !oldScript.parentNode) {
      return;
    }
    const newScript = document.createElement('script');
    for (let i = 0; i < oldScript.attributes.length; i += 1) {
      const attr = oldScript.attributes[i];
      newScript.setAttribute(attr.name, attr.value);
    }
    if (!oldScript.hasAttribute('src')) {
      newScript.textContent = oldScript.textContent;
    }
    oldScript.parentNode.replaceChild(newScript, oldScript);
  });
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
    const doc = document.implementation.createHTMLDocument('');
    try {
      doc.documentElement.innerHTML = html;
    } catch (e) {
      doc.body.innerHTML = html;
    }
    return doc;
  }
  return null;
}

function syncDocumentMeta(doc) {
  if (!doc) return;
  const newTitle = doc.querySelector('title');
  if (newTitle) {
    document.title = newTitle.textContent || document.title;
  }
  const incomingHtml = doc.documentElement;
  if (incomingHtml) {
    const lang = incomingHtml.getAttribute('lang');
    if (lang) {
      document.documentElement.setAttribute('lang', lang);
    }
    const theme = incomingHtml.getAttribute('data-bs-theme');
    if (theme) {
      document.documentElement.setAttribute('data-bs-theme', theme);
    }
  }
  const incomingBody = doc.body;
  if (incomingBody) {
    document.body.className = incomingBody.className || '';
  }
}

function replaceAdminShell(doc) {
  if (!doc) {
    return null;
  }
  const newWrapper = doc.querySelector('.admin-wrapper');
  const currentWrapper = document.querySelector('.admin-wrapper');
  if (!newWrapper || !currentWrapper) {
    return null;
  }
  currentWrapper.replaceWith(newWrapper);
  return newWrapper;
}

export function applyAdminHtml(html) {
  if (typeof html !== 'string' || html.trim() === '') {
    return null;
  }
  const doc = parseHtmlDocument(html);
  if (!doc) {
    return null;
  }
  const newWrapper = replaceAdminShell(doc);
  if (!newWrapper) {
    return null;
  }
  syncDocumentMeta(doc);
  executeScripts(newWrapper);
  callRefreshDynamicUI(newWrapper);
  return newWrapper;
}

function extractMessageFromData(data, fallback) {
  let message = fallback || '';
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

export function readResponsePayload(response) {
  return response.text().then(function (text) {
    const contentType = response.headers ? (response.headers.get('Content-Type') || '') : '';
    const isJson = contentType.toLowerCase().indexOf('application/json') !== -1;
    let data;
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

function applyPartialContent(target, html) {
  if (!target || typeof html !== 'string') {
    return null;
  }
  let element = null;
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
  callRefreshDynamicUI(element);
  return element;
}

function applyPartials(partials) {
  if (!partials) {
    return [];
  }
  const applied = [];
  if (Array.isArray(partials)) {
    partials.forEach(function (entry) {
      if (!entry || typeof entry !== 'object') {
        return;
      }
      const selector = entry.selector || entry.target || entry.key || entry.name;
      const html = entry.html || entry.content || entry.markup;
      const element = applyPartialContent(selector, html);
      if (element) {
        applied.push({ selector: selector, element: element });
      }
    });
    return applied;
  }
  if (typeof partials === 'object') {
    Object.keys(partials).forEach(function (selector) {
      const element = applyPartialContent(selector, partials[selector]);
      if (element) {
        applied.push({ selector: selector, element: element });
      }
    });
  }
  return applied;
}

const ajaxHandlers = Object.create(null);

export function registerAjaxHandler(name, handler) {
  if (typeof name !== 'string' || !name) {
    throw new Error('admin.ajax handler name must be a non-empty string');
  }
  if (typeof handler !== 'function') {
    throw new Error('admin.ajax handler for "' + name + '" must be a function');
  }
  ajaxHandlers[name] = handler;
}

export function unregisterAjaxHandler(name) {
  if (typeof name !== 'string' || !name) {
    return;
  }
  delete ajaxHandlers[name];
}

function executeAjaxHandlers(definitions, meta) {
  if (!definitions) {
    return;
  }
  let entries = [];
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
    const handlerName = entry.name || entry.module || entry.key;
    if (!handlerName || typeof handlerName !== 'string') {
      return;
    }
    const handler = ajaxHandlers[handlerName];
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
  const response = payload.response;
  const data = payload.data;
  let message = extractMessageFromData(data, 'Došlo k chybě (' + response.status + ')');
  if (response.status === 422 && data && data.errors) {
    const validationMessage = buildValidationMessage(data.errors);
    if (validationMessage) {
      message = validationMessage;
    }
  }
  notifier.danger(message, options.context || null);
  const error = new Error(message);
  error.status = response.status;
  error.data = data;
  if (data && data.errors) {
    error.validationErrors = data.errors;
  }
  error.__handled = true;
  throw error;
}

function handlePayload(url, payload, options) {
  const response = payload.response;
  if (!response.ok) {
    return handleHttpErrorPayload(payload, options || {});
  }

  const opts = options || {};
  const context = opts.context || null;
  const result = {
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
    const data = payload.data;
    if (data && typeof data === 'object') {
      const loadAdminPage = getLoadAdminPage();
      if (typeof data.redirect === 'string' && data.redirect !== '') {
        result.redirected = true;
        if (typeof opts.onRedirect === 'function') {
          opts.onRedirect(data.redirect, data, payload);
        } else if (loadAdminPage) {
          loadAdminPage(data.redirect, { pushState: true });
        }
        return result;
      }
      if (typeof data.reload === 'boolean' && data.reload) {
        result.reloaded = true;
        if (typeof opts.onReload === 'function') {
          opts.onReload(data, payload);
        } else if (loadAdminPage) {
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
        const htmlRoot = applyAdminHtml(data.html);
        if (!htmlRoot) {
          window.location.reload();
        } else {
          result.htmlRoot = htmlRoot;
          if (typeof opts.onHtml === 'function') {
            opts.onHtml(htmlRoot, data, payload);
          }
        }
      } else if (typeof data.raw === 'string' && data.raw.trim() !== '') {
        const raw = data.raw.trim();
        if (opts.expectHtml && raw.charAt(0) === '<') {
          const htmlRootRaw = applyAdminHtml(raw);
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
        const validationMessage = buildValidationMessage(data.errors);
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
    const text = payload.text ? payload.text.trim() : '';
    if (text) {
      if (opts.expectHtml && text.charAt(0) === '<') {
        const htmlRootText = applyAdminHtml(payload.text);
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

export function ajaxRequest(url, options) {
  const opts = options ? Object.assign({}, options) : {};
  const method = (opts.method || 'GET').toUpperCase();
  const headers = Object.assign({ 'X-Requested-With': 'XMLHttpRequest' }, opts.headers || {});
  const fetchOptions = {
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

  const requestUrl = normalizeUrl(url);
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
      const message = (error && error.message) ? error.message : 'Došlo k neočekávané chybě.';
      notifier.danger(message, opts.context || null);
    }
    throw error;
  });
}

export function ajaxGet(url, options) {
  const opts = options ? Object.assign({}, options) : {};
  opts.method = 'GET';
  return ajaxRequest(url, opts);
}

export function ajaxPost(url, body, options) {
  const opts = options ? Object.assign({}, options) : {};
  if (body !== undefined) {
    opts.body = body;
  }
  if (!opts.method) {
    opts.method = 'POST';
  }
  return ajaxRequest(url, opts);
}

export const adminAjax = {
  request: ajaxRequest,
  get: ajaxGet,
  post: ajaxPost,
  registerHandler: registerAjaxHandler,
  unregisterHandler: unregisterAjaxHandler,
  notify: notifier.notify
};
