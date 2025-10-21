import { notifier } from '../core/notifier.js';
import { dispatchFormEvent } from '../core/form-events.js';

export function initThemesPage(root) {
  var scope = root || document;
  var containers = [].slice.call(scope.querySelectorAll('[data-themes-page]'));
  if (containers.length === 0) {
    return;
  }

  containers.forEach(function (container) {
    if (!(container instanceof HTMLElement)) {
      return;
    }
    if (container.dataset.themesInitialized === '1') {
      return;
    }
    container.dataset.themesInitialized = '1';

    var list = container.querySelector('[data-themes-list]');
    if (!(list instanceof HTMLElement)) {
      return;
    }

    var uploadForm = container.querySelector('[data-theme-upload-form]');

    function currentCsrf() {
      var token = container.getAttribute('data-csrf');
      if (token && token.trim() !== '') {
        return token;
      }
      var hidden = container.querySelector('[data-theme-upload-form] input[name="csrf"]');
      if (hidden && hidden.value) {
        container.setAttribute('data-csrf', hidden.value);
        return hidden.value;
      }
      return '';
    }

    function setCsrf(token) {
      if (typeof token !== 'string' || token === '') {
        return;
      }
      container.setAttribute('data-csrf', token);
      if (uploadForm instanceof HTMLFormElement) {
        var uploadCsrf = uploadForm.querySelector('input[name="csrf"]');
        if (uploadCsrf) {
          uploadCsrf.value = token;
          uploadCsrf.setAttribute('value', token);
        }
      }
      var activateInputs = [].slice.call(list.querySelectorAll('form[data-theme-activate-form] input[name="csrf"]'));
      activateInputs.forEach(function (input) {
        input.value = token;
        input.setAttribute('value', token);
      });
    }

    function createThemeCard(theme, activeSlug, csrfToken) {
      var slug = theme && typeof theme.slug === 'string' ? theme.slug : '';
      var name = theme && typeof theme.name === 'string' ? theme.name : slug;
      var version = theme && typeof theme.version === 'string' ? theme.version : '';
      var author = theme && typeof theme.author === 'string' ? theme.author : '';
      var screenshot = theme && typeof theme.screenshot === 'string' && theme.screenshot !== '' ? theme.screenshot : null;
      var hasTemplates = !!(theme && theme.hasTemplates);
      var cardCol = document.createElement('div');
      cardCol.className = 'col-md-6';
      cardCol.setAttribute('data-theme-card', '1');
      cardCol.setAttribute('data-theme-slug', slug);

      var card = document.createElement('div');
      card.className = 'card h-100 shadow-sm';
      cardCol.appendChild(card);

      if (screenshot) {
        var img = document.createElement('img');
        img.className = 'card-img-top';
        img.setAttribute('alt', 'screenshot');
        img.style.objectFit = 'cover';
        img.style.maxHeight = '180px';
        img.src = screenshot;
        card.appendChild(img);
      }

      var body = document.createElement('div');
      body.className = 'card-body';
      card.appendChild(body);

      var title = document.createElement('h5');
      title.className = 'card-title mb-1';
      title.textContent = name;
      body.appendChild(title);

      var slugRow = document.createElement('div');
      slugRow.className = 'small text-secondary mb-2';
      slugRow.appendChild(document.createTextNode('slug: '));
      var slugCode = document.createElement('code');
      slugCode.textContent = slug;
      slugRow.appendChild(slugCode);
      if (version) {
        slugRow.appendChild(document.createTextNode(' • v' + version));
      }
      body.appendChild(slugRow);

      var authorRow = document.createElement('div');
      authorRow.className = 'small text-secondary mb-2';
      authorRow.textContent = author ? 'Autor: ' + author : '';
      body.appendChild(authorRow);

      var badge = document.createElement('span');
      if (hasTemplates) {
        badge.className = 'badge text-bg-success-subtle text-success-emphasis border border-success-subtle';
        badge.textContent = 'templates/ OK';
      } else {
        badge.className = 'badge text-bg-warning-subtle text-warning-emphasis border border-warning-subtle';
        badge.textContent = 'chybí templates/';
      }
      body.appendChild(badge);

      var footer = document.createElement('div');
      footer.className = 'card-footer d-flex align-items-center gap-2';
      card.appendChild(footer);

      var activeBadge = document.createElement('span');
      activeBadge.className = 'badge text-bg-primary-subtle text-primary-emphasis border border-primary-subtle';
      activeBadge.setAttribute('data-theme-active-badge', '1');
      activeBadge.textContent = 'Aktivní';
      if (slug !== activeSlug) {
        activeBadge.classList.add('d-none');
      }
      footer.appendChild(activeBadge);

      var form = document.createElement('form');
      form.method = 'post';
      form.action = 'admin.php?r=themes&a=activate';
      form.className = 'ms-auto';
      form.setAttribute('data-ajax', '');
      form.setAttribute('data-theme-activate-form', '1');
      if (slug === activeSlug) {
        form.classList.add('d-none');
      }

      var csrfInput = document.createElement('input');
      csrfInput.type = 'hidden';
      csrfInput.name = 'csrf';
      csrfInput.value = csrfToken || currentCsrf();
      csrfInput.setAttribute('value', csrfInput.value);
      form.appendChild(csrfInput);

      var slugInput = document.createElement('input');
      slugInput.type = 'hidden';
      slugInput.name = 'slug';
      slugInput.value = slug;
      slugInput.setAttribute('value', slug);
      form.appendChild(slugInput);

      var button = document.createElement('button');
      button.type = 'submit';
      button.className = 'btn btn-light btn-sm border';
      button.textContent = 'Aktivovat';
      form.appendChild(button);

      footer.appendChild(form);

      return cardCol;
    }

    function renderThemes(themes, activeSlug, csrfToken) {
      while (list.firstChild) {
        list.removeChild(list.firstChild);
      }
      if (!Array.isArray(themes) || themes.length === 0) {
        var emptyCol = document.createElement('div');
        emptyCol.className = 'col-12';
        emptyCol.setAttribute('data-theme-empty', '1');
        var alert = document.createElement('div');
        alert.className = 'alert alert-info';
        alert.innerHTML = 'Zatím žádné šablony v <code>/themes</code>.';
        emptyCol.appendChild(alert);
        list.appendChild(emptyCol);
      } else {
        var fragment = document.createDocumentFragment();
        themes.forEach(function (theme) {
          fragment.appendChild(createThemeCard(theme, activeSlug, csrfToken));
        });
        list.appendChild(fragment);
      }

      setCsrf(csrfToken || currentCsrf());

      var forms = [].slice.call(list.querySelectorAll('form[data-theme-activate-form]'));
      forms.forEach(bindActivateForm);
    }

    function setActiveSlug(activeSlug) {
      container.setAttribute('data-active-slug', activeSlug || '');
      var cards = [].slice.call(list.querySelectorAll('[data-theme-card]'));
      cards.forEach(function (card) {
        var slug = card.getAttribute('data-theme-slug') || '';
        var badge = card.querySelector('[data-theme-active-badge]');
        var form = card.querySelector('form[data-theme-activate-form]');
        if (slug === activeSlug) {
          if (badge) {
            badge.classList.remove('d-none');
          }
          if (form) {
            form.classList.add('d-none');
          }
        } else {
          if (badge) {
            badge.classList.add('d-none');
          }
          if (form) {
            form.classList.remove('d-none');
          }
        }
        if (form) {
          var slugInput = form.querySelector('input[name="slug"]');
          if (slugInput) {
            slugInput.value = slug;
            slugInput.setAttribute('value', slug);
          }
        }
      });
    }

    function applyThemesData(data) {
      if (!data || typeof data !== 'object') {
        return;
      }
      if (typeof data.csrf === 'string' && data.csrf !== '') {
        setCsrf(data.csrf);
      }
      if (Array.isArray(data.themes)) {
        renderThemes(
          data.themes,
          typeof data.activeSlug === 'string' ? data.activeSlug : '',
          typeof data.csrf === 'string' ? data.csrf : currentCsrf()
        );
      } else if (typeof data.activeSlug === 'string') {
        setActiveSlug(data.activeSlug);
      }
    }

    function bindActivateForm(form) {
      if (!(form instanceof HTMLFormElement)) {
        return;
      }
      if (form.dataset.themeActivateBound === '1') {
        return;
      }
      form.dataset.themeActivateBound = '1';
      form.addEventListener('cms:admin:form:success', function (event) {
        var detail = event && event.detail ? event.detail : {};
        var result = detail.result || null;
        var data = result && result.data ? result.data : null;
        if (data && data.success === false) {
          return;
        }
        applyThemesData(data || {});
      });
    }

    function handleUploadSubmit(form, submitter) {
      if (!(form instanceof HTMLFormElement)) {
        return;
      }
      if (form.dataset.themeUploadSubmitting === '1') {
        return;
      }

      var method = (form.getAttribute('method') || 'POST').toUpperCase();
      var action = form.getAttribute('action') || window.location.href;
      var formData = new FormData(form);
      if (submitter && submitter.name) {
        formData.append(submitter.name, submitter.value);
      }

      var progressWrapper = form.querySelector('[data-theme-upload-progress]');
      var progressBar = form.querySelector('[data-theme-upload-progress-bar]');

      function updateProgress(value) {
        var normalized = Math.max(0, Math.min(100, Math.round(value)));
        if (progressBar instanceof HTMLElement) {
          progressBar.style.width = normalized + '%';
          progressBar.setAttribute('aria-valuenow', String(normalized));
          progressBar.textContent = normalized + '%';
        }
      }

      function showProgress() {
        if (progressWrapper instanceof HTMLElement) {
          progressWrapper.classList.remove('d-none');
        }
        updateProgress(0);
      }

      function hideProgress() {
        if (progressWrapper instanceof HTMLElement) {
          progressWrapper.classList.add('d-none');
        }
        updateProgress(0);
      }

      var disableSubmit = submitter && typeof submitter.disabled === 'boolean' && !submitter.disabled;
      if (disableSubmit) {
        submitter.disabled = true;
      }

      form.dataset.themeUploadSubmitting = '1';
      form.classList.add('is-submitting');
      showProgress();

      var xhr = new XMLHttpRequest();
      xhr.open(method, action, true);
      xhr.withCredentials = true;
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.setRequestHeader('Accept', 'application/json');

      xhr.upload.addEventListener('progress', function (event) {
        if (!event || !event.lengthComputable) {
          return;
        }
        var percent = event.total > 0 ? (event.loaded / event.total) * 100 : 0;
        updateProgress(percent);
      });

      function finalize() {
        form.classList.remove('is-submitting');
        form.dataset.themeUploadSubmitting = '';
        delete form.dataset.themeUploadSubmitting;
        if (disableSubmit) {
          submitter.disabled = false;
        }
        window.setTimeout(function () {
          hideProgress();
        }, 400);
      }

      xhr.addEventListener('load', function () {
        var status = xhr.status;
        var responseText = xhr.responseText || '';
        var contentType = xhr.getResponseHeader('Content-Type') || '';
        var isJson = contentType.toLowerCase().indexOf('application/json') !== -1;
        var data = null;
        if (isJson && responseText) {
          try {
            data = JSON.parse(responseText);
          } catch (err) {
            data = null;
          }
        }
        updateProgress(100);
        finalize();

        if (status >= 200 && status < 300) {
          if (data && data.flash && typeof data.flash === 'object') {
            notifier.notify(data.flash.type || 'success', data.flash.msg, form);
          } else {
            notifier.success('Šablona byla nahrána.', form);
          }
          applyThemesData(data || {});
          dispatchFormEvent(form, 'cms:admin:form:success', {
            result: {
              data: data && typeof data === 'object' ? data : {},
              url: action,
              response: null
            }
          });
          try {
            form.reset();
          } catch (err) {
            /* ignore */
          }
        } else {
          var message = '';
          var type = 'danger';
          if (data && data.flash && typeof data.flash === 'object') {
            message = data.flash.msg || '';
            type = data.flash.type || 'danger';
          } else if (data && typeof data.message === 'string' && data.message) {
            message = data.message;
          } else if (responseText) {
            message = responseText;
          } else {
            message = 'Došlo k neočekávané chybě.';
          }
          notifier.notify(type, message, form);
          dispatchFormEvent(form, 'cms:admin:form:error', {
            error: {
              message: message,
              data: data || null,
              status: status
            }
          });
        }
      });

      xhr.addEventListener('error', function () {
        finalize();
        notifier.danger('Nahrávání se nezdařilo.', form);
        dispatchFormEvent(form, 'cms:admin:form:error', {
          error: {
            message: 'Nahrávání se nezdařilo.',
            data: null
          }
        });
      });

      xhr.addEventListener('abort', function () {
        finalize();
        notifier.info('Nahrávání bylo přerušeno.', form);
      });

      xhr.send(formData);
    }

    function bindUploadForm(form) {
      if (!(form instanceof HTMLFormElement)) {
        return;
      }
      if (form.dataset.themeUploadBound === '1') {
        return;
      }
      form.dataset.themeUploadBound = '1';
      form.addEventListener(
        'submit',
        function (event) {
          if (event.defaultPrevented) {
            return;
          }
          event.preventDefault();
          if (typeof event.stopPropagation === 'function') {
            event.stopPropagation();
          }
          if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
          }
          var submitter = event.submitter || null;
          handleUploadSubmit(form, submitter);
        },
        true
      );
    }

    var activateForms = [].slice.call(list.querySelectorAll('form[data-theme-activate-form]'));
    activateForms.forEach(bindActivateForm);

    if (uploadForm instanceof HTMLFormElement) {
      bindUploadForm(uploadForm);
    }
  });
}
