import { applyPostPayloadToForm, extractPostPayload } from './post-form.js';

const AUTOSAVE_INTERVAL = 30000;

function serializeAutosaveData(formData) {
  const pairs = [];
  if (!formData || typeof formData.forEach !== 'function') {
    return '';
  }
  formData.forEach(function (value, key) {
    if (value instanceof File) {
      return;
    }
    if (key === 'csrf' || key === 'thumbnail') {
      return;
    }
    const normalized = value === null || typeof value === 'undefined'
      ? ''
      : String(value);
    pairs.push(key + '=' + normalized);
  });
  pairs.sort();
  return pairs.join('&');
}

function hasMeaningfulAutosaveData(form, formData, postId) {
  if (postId) {
    return true;
  }
  if (!formData) {
    return false;
  }

  const title = formData.get ? String(formData.get('title') || '').trim() : '';
  if (title !== '') {
    return true;
  }

  const contentField = form ? form.querySelector('textarea[name="content"]') : null;
  const rawContent = contentField ? contentField.value : (formData.get ? String(formData.get('content') || '') : '');
  if (rawContent) {
    const normalizedContent = rawContent
      .replace(/<[^>]*>/g, ' ')
      .replace(/&nbsp;/gi, ' ')
      .replace(/\s+/g, ' ')
      .trim();
    if (normalizedContent !== '') {
      return true;
    }
  }

  const attachmentsValue = formData.get ? String(formData.get('attached_media') || '').trim() : '';
  if (attachmentsValue !== '' && attachmentsValue !== '[]') {
    return true;
  }

  const thumbnailId = formData.get ? String(formData.get('selected_thumbnail_id') || '').trim() : '';
  if (thumbnailId !== '') {
    return true;
  }

  let hasCategories = false;
  if (formData.getAll) {
    formData.getAll('categories[]').forEach(function (value) {
      if (String(value || '').trim() !== '') {
        hasCategories = true;
      }
    });
    if (hasCategories) {
      return true;
    }

    let hasTags = false;
    formData.getAll('tags[]').forEach(function (value) {
      if (String(value || '').trim() !== '') {
        hasTags = true;
      }
    });
    if (hasTags) {
      return true;
    }
  }

  const newCats = formData.get ? String(formData.get('new_categories') || '').trim() : '';
  if (newCats !== '') {
    return true;
  }

  const newTags = formData.get ? String(formData.get('new_tags') || '').trim() : '';
  if (newTags !== '') {
    return true;
  }

  const commentsInput = form ? form.querySelector('[name="comments_allowed"]') : null;
  if (commentsInput && commentsInput.checked === false) {
    return true;
  }

  const statusValue = formData.get ? String(formData.get('status') || '').trim() : '';
  if (statusValue !== '' && statusValue !== 'draft') {
    return true;
  }

  return false;
}

export function initPostAutosave(root, options) {
  const scope = root || document;
  const settings = options || {};
  const loadAdminPage = typeof settings.loadAdminPage === 'function'
    ? settings.loadAdminPage
    : null;
  const forms = [].slice.call(scope.querySelectorAll('form[data-autosave-form="1"]'));

  forms.forEach(function (form) {
    if (form.dataset.autosaveBound === '1') {
      return;
    }
    form.dataset.autosaveBound = '1';

    let autosaveUrl = form.getAttribute('data-autosave-url') || '';
    const statusEl = form.querySelector('[data-autosave-status]');
    const statusInput = form.querySelector('input[name="status"]');
    const statusLabelEl = form.querySelector('#status-current-label');
    const idDisplayEl = form.querySelector('[data-post-id-display]');
    const editorTextarea = form.querySelector('textarea[data-content-editor]');

    function updateStatus(message, isError) {
      if (!statusEl) {
        return;
      }
      statusEl.textContent = typeof message === 'string' ? message : '';
      if (isError) {
        statusEl.classList.add('text-danger');
        statusEl.classList.remove('text-secondary');
      } else {
        statusEl.classList.remove('text-danger');
        statusEl.classList.add('text-secondary');
      }
    }

    function applyEditorContent(formData) {
      if (!editorTextarea || !formData) {
        return;
      }
      if (typeof editorTextarea.editorGetContent === 'function') {
        formData.set('content', editorTextarea.editorGetContent());
      } else if (typeof editorTextarea.value !== 'undefined') {
        formData.set('content', editorTextarea.value);
      }
    }

    function collectFormData() {
      const formData = new FormData(form);
      formData.delete('thumbnail');
      applyEditorContent(formData);
      const currentPostId = getCurrentPostId();
      if (currentPostId) {
        formData.set('id', currentPostId);
      } else {
        formData.delete('id');
      }
      formData.set('autosave', '1');
      return formData;
    }

    if (!autosaveUrl) {
      const action = form.getAttribute('action') || '';
      if (action) {
        try {
          const actionUrl = new URL(action, window.location.href);
          actionUrl.searchParams.set('a', 'autosave');
          autosaveUrl = actionUrl.toString();
        } catch (err) {
          autosaveUrl = action;
        }
      }
    }

    let postId = String(form.getAttribute('data-post-id') || '').trim();
    if (postId !== '') {
      form.dataset.postId = postId;
    }

    function getCurrentPostId() {
      return postId !== '' ? postId : '';
    }

    function setCurrentPostId(value) {
      const parsed = parseInt(value, 10);
      if (isNaN(parsed) || parsed <= 0) {
        return false;
      }
      const normalized = String(parsed);
      if (postId === normalized) {
        return false;
      }
      postId = normalized;
      form.setAttribute('data-post-id', postId);
      form.dataset.postId = postId;
      if (idDisplayEl) {
        idDisplayEl.textContent = 'ID #' + postId;
        idDisplayEl.classList.remove('d-none');
      }
      if (editorTextarea) {
        editorTextarea.setAttribute('data-post-id', postId);
      }
      return true;
    }

    const initialData = new FormData(form);
    initialData.delete('thumbnail');
    if (postId !== '') {
      initialData.set('id', postId);
    } else {
      initialData.delete('id');
    }
    applyEditorContent(initialData);
    initialData.set('autosave', '1');

    let lastSavedSerialized = serializeAutosaveData(initialData);
    let lastSentSerialized = null;
    let intervalId = null;
    let debounceTimer = null;
    let inFlight = false;

    function startAutosaveInterval() {
      stopAutosaveInterval();
      intervalId = window.setInterval(attemptAutosave, AUTOSAVE_INTERVAL);
    }

    function stopAutosaveInterval() {
      if (intervalId !== null) {
        window.clearInterval(intervalId);
        intervalId = null;
      }
    }

    function cleanupTimers() {
      if (debounceTimer !== null) {
        window.clearTimeout(debounceTimer);
        debounceTimer = null;
      }
    }

    function attemptAutosave() {
      if (!document.body.contains(form)) {
        cleanup();
        return;
      }
      if (!autosaveUrl) {
        return;
      }
      if (form.classList.contains('is-submitting')) {
        return;
      }
      if (inFlight) {
        return;
      }

      const formData = collectFormData();
      const serialized = serializeAutosaveData(formData);
      const currentPostId = getCurrentPostId();

      if (!hasMeaningfulAutosaveData(form, formData, currentPostId)) {
        return;
      }

      if (serialized === lastSavedSerialized || serialized === lastSentSerialized) {
        return;
      }

      inFlight = true;
      lastSentSerialized = serialized;

      fetch(autosaveUrl, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      }).then(function (response) {
        const contentType = response.headers.get('Content-Type') || '';
        if (!response.ok) {
          if (contentType.indexOf('application/json') !== -1) {
            return response.json().then(function (json) {
              const message = json && typeof json.message === 'string' ? json.message : '';
              throw new Error(message || 'Automatické uložení selhalo.');
            });
          }
          return response.text().then(function (text) {
            throw new Error(text || 'Automatické uložení selhalo.');
          });
        }
        if (contentType.indexOf('application/json') !== -1) {
          return response.json();
        }
        return response.text().then(function (text) {
          try {
            return JSON.parse(text);
          } catch (err) {
            return { success: true, message: text };
          }
        });
      }).then(function (payload) {
        if (!payload || payload.success === false) {
          if (payload && payload.message === '') {
            lastSavedSerialized = serializeAutosaveData(collectFormData());
            lastSentSerialized = lastSavedSerialized;
            return;
          }
          const failMessage = payload && typeof payload.message === 'string' && payload.message !== ''
            ? payload.message
            : 'Automatické uložení selhalo.';
          throw new Error(failMessage);
        }

        const postPayload = extractPostPayload(payload);
        if (postPayload) {
          const hadIdBefore = postId !== '';
          const result = applyPostPayloadToForm(form, postPayload, {
            statusInput: statusInput,
            statusLabelEl: statusLabelEl
          });
          if (result.postId) {
            const changed = setCurrentPostId(result.postId);
            if (!hadIdBefore && changed && loadAdminPage) {
              const targetUrl = result.actionUrl || form.getAttribute('action') || '';
              if (targetUrl) {
                loadAdminPage(targetUrl, { replaceHistory: true });
                return;
              }
            }
          }
        }

        const successMessage = payload && typeof payload.message === 'string' && payload.message !== ''
          ? payload.message
          : 'Automaticky uloženo.';
        updateStatus(successMessage, false);

        lastSavedSerialized = serializeAutosaveData(collectFormData());
        lastSentSerialized = lastSavedSerialized;
      }).catch(function (error) {
        lastSentSerialized = null;
        const message = error && error.message ? error.message : 'Automatické uložení selhalo.';
        updateStatus(message, true);
      }).finally(function () {
        inFlight = false;
      });
    }

    function scheduleAutosaveSoon() {
      cleanupTimers();
      debounceTimer = window.setTimeout(function () {
        debounceTimer = null;
        attemptAutosave();
      }, 5000);
    }

    function cleanup() {
      stopAutosaveInterval();
      cleanupTimers();
    }

    startAutosaveInterval();

    form.addEventListener('input', function () {
      if (statusEl && statusEl.classList.contains('text-danger')) {
        updateStatus('', false);
      }
      scheduleAutosaveSoon();
    });

    form.addEventListener('change', scheduleAutosaveSoon);

    form.addEventListener('submit', function () {
      stopAutosaveInterval();
      cleanupTimers();
    });

    form.addEventListener('cms:admin:form:success', function (event) {
      const detail = event && event.detail ? event.detail : {};
      const result = detail.result || null;
      const data = result && result.data ? result.data : null;
      const postPayload = extractPostPayload(data);
      if (postPayload) {
        const hadIdBefore = postId !== '';
        const applyResult = applyPostPayloadToForm(form, postPayload, {
          statusInput: statusInput,
          statusLabelEl: statusLabelEl
        });
        if (applyResult.postId) {
          const changed = setCurrentPostId(applyResult.postId);
          if (!hadIdBefore && changed && loadAdminPage) {
            const targetUrl = applyResult.actionUrl || form.getAttribute('action') || '';
            if (targetUrl) {
              loadAdminPage(targetUrl, { replaceHistory: true });
              return;
            }
          }
        }
      }

      let message = '';
      if (result && result.data && typeof result.data.message === 'string') {
        message = result.data.message;
      }
      updateStatus(message, false);
      lastSavedSerialized = serializeAutosaveData(collectFormData());
      lastSentSerialized = lastSavedSerialized;
      startAutosaveInterval();
    });

    document.addEventListener('cms:admin:navigated', function handler() {
      if (!document.body.contains(form)) {
        cleanup();
        document.removeEventListener('cms:admin:navigated', handler);
      }
    });
  });
}

export {
  serializeAutosaveData,
  hasMeaningfulAutosaveData
};
