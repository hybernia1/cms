// Core notifier utilities for admin UI flash messages.
const FLASH_DISMISS_DELAY = 5000;
const FLASH_DISMISS_ANIMATION = 260;
let flashActiveElement = null;
let flashDismissTimeout = null;
let flashRemoveTimeout = null;

function findFlashContainer(form) {
  if (form && typeof form.closest === 'function') {
    const closest = form.closest('[data-flash-container]');
    if (closest) {
      return closest;
    }
  }
  const selector = form ? form.getAttribute('data-flash-target') : null;
  if (selector) {
    try {
      const explicit = document.querySelector(selector);
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
    const target = event.target;
    const dismissTrigger = target && typeof target.closest === 'function'
      ? target.closest('[data-flash-dismiss]')
      : null;
    if (!dismissTrigger) {
      return;
    }
    event.preventDefault();
    const alert = dismissTrigger.closest('.admin-flash');
    if (alert) {
      hideFlashMessage(alert);
    }
  });
}

export function initFlashMessages(root) {
  let container = null;
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
  const alerts = [].slice.call(container.querySelectorAll('.admin-flash'));
  alerts.forEach(function (alert) {
    scheduleFlashDismiss(alert);
  });
}

function showFlashMessage(type, message, form) {
  const container = findFlashContainer(form);
  if (!container) {
    return;
  }

  const existing = container.querySelector('.admin-flash');
  if (existing) {
    removeFlashElement(existing);
  }

  if (!message) {
    return;
  }

  const allowed = ['success', 'danger', 'warning', 'info'];
  const normalized = typeof type === 'string' && allowed.indexOf(type) !== -1 ? type : 'info';
  const alert = document.createElement('div');
  alert.className = 'alert alert-' + normalized + ' admin-flash';
  alert.setAttribute('role', 'alert');
  const body = document.createElement('div');
  body.className = 'admin-flash-body';
  const text = document.createElement('span');
  text.className = 'admin-flash-message';
  text.textContent = message;
  const close = document.createElement('button');
  close.type = 'button';
  close.className = 'btn-close admin-flash-close';
  close.setAttribute('aria-label', 'Zavřít upozornění');
  close.setAttribute('data-flash-dismiss', '');
  body.appendChild(text);
  body.appendChild(close);
  alert.appendChild(body);
  if (container.firstChild) {
    container.insertBefore(alert, container.firstChild);
  } else {
    container.appendChild(alert);
  }
  scheduleFlashDismiss(alert);
}

export const notifier = {
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

export default notifier;
