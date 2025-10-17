(function () {
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

  function ajaxFormRequest(form, submitter) {
    var method = (form.getAttribute('method') || 'GET').toUpperCase();
    if (method === 'GET') {
      return Promise.resolve();
    }

    var action = form.getAttribute('action') || window.location.href;
    var formData = new FormData(form);
    if (submitter && submitter.name) {
      formData.append(submitter.name, submitter.value);
    }

    var restoreDisabled = false;
    if (submitter && typeof submitter.disabled === 'boolean' && !submitter.disabled) {
      submitter.disabled = true;
      restoreDisabled = true;
    }

    form.classList.add('is-submitting');

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

      if (data && typeof data.redirect === 'string' && data.redirect !== '') {
        window.location.href = data.redirect;
        return;
      }

      if (data && typeof data.reload === 'boolean' && data.reload) {
        window.location.reload();
        return;
      }

      if (!flash && data && data.message) {
        showFlashMessage(data.success === false ? 'danger' : 'info', data.message, form);
      }
    }).catch(function (error) {
      if (!error || !error.__handled) {
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

  function initTooltips() {
    if (typeof bootstrap === 'undefined' || typeof bootstrap.Tooltip !== 'function') {
      return;
    }
    var tooltipElements = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipElements.forEach(function (el) {
      new bootstrap.Tooltip(el);
    });
  }

  function initBulkForms() {
    var forms = [].slice.call(document.querySelectorAll('[data-bulk-form]'));
    forms.forEach(function (form) {
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

  document.addEventListener('DOMContentLoaded', function () {
    initTooltips();
    initBulkForms();
    initAjaxForms();
  });
})();
