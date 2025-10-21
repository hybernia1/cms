import { formHelperRegistry, formHelperInstances } from './state.js';

function registerFormHelper(name, factory) {
  const key = typeof name === 'string' ? name.trim() : '';
  if (!key) {
    throw new Error('form helper name must be a non-empty string');
  }
  if (typeof factory !== 'function') {
    throw new Error('form helper "' + key + '" must be a function');
  }
  formHelperRegistry[key] = factory;
}

function unregisterFormHelper(name) {
  if (typeof name !== 'string') {
    return;
  }
  const key = name.trim();
  if (key) {
    delete formHelperRegistry[key];
  }
}

function hideFeedbackElement(element) {
  if (!element) {
    return;
  }
  element.textContent = '';
  element.setAttribute('hidden', 'true');
  if (element.classList) {
    element.classList.remove('d-block');
  }
}

function getFormControls(form, field) {
  if (!form || !form.elements) {
    return [];
  }
  let controls = null;
  if (typeof form.elements.namedItem === 'function') {
    controls = form.elements.namedItem(field);
  }
  if (!controls && Object.prototype.hasOwnProperty.call(form.elements, field)) {
    controls = form.elements[field];
  }
  if (!controls) {
    return [];
  }
  if (typeof RadioNodeList !== 'undefined' && controls instanceof RadioNodeList) {
    return controls.length ? [].slice.call(controls) : [];
  }
  if (Array.isArray(controls)) {
    return controls;
  }
  if (typeof controls.length === 'number' && typeof controls.item === 'function' && !controls.tagName) {
    return controls.length ? [].slice.call(controls) : [];
  }
  return [controls];
}

function getFieldFeedbackElements(form, field) {
  if (!form || typeof form.querySelectorAll !== 'function') {
    return [];
  }
  const nodes = [].slice.call(form.querySelectorAll('[data-error-for]'));
  return nodes.filter(function (node) {
    if (!node.dataset) {
      return false;
    }
    return node.dataset.errorFor === field;
  });
}

function findGeneralFeedbackElement(form) {
  if (!form || typeof form.querySelectorAll !== 'function') {
    return null;
  }
  const nodes = [].slice.call(form.querySelectorAll('[data-error-for]'));
  for (let i = 0; i < nodes.length; i += 1) {
    const node = nodes[i];
    if (!node || !node.dataset) {
      continue;
    }
    const target = node.dataset.errorFor;
    if (target === 'form' || target === '*' || target === '') {
      return node;
    }
  }
  return null;
}

function resetControlValidation(control) {
  if (!control || !control.classList) {
    return;
  }
  control.classList.remove('is-invalid');
  if (typeof control.removeAttribute === 'function') {
    control.removeAttribute('aria-invalid');
    if (control.hasAttribute('data-prev-aria-describedby')) {
      const prev = control.getAttribute('data-prev-aria-describedby') || '';
      if (prev) {
        control.setAttribute('aria-describedby', prev);
      } else {
        control.removeAttribute('aria-describedby');
      }
      control.removeAttribute('data-prev-aria-describedby');
    }
  }
}

function clearFieldError(form, field) {
  const controls = getFormControls(form, field);
  controls.forEach(resetControlValidation);
  const feedbacks = getFieldFeedbackElements(form, field);
  feedbacks.forEach(hideFeedbackElement);
}

function clearFormValidation(form) {
  if (!form) {
    return;
  }
  const invalidControls = [].slice.call(form.querySelectorAll('.is-invalid'));
  invalidControls.forEach(resetControlValidation);
  const feedbacks = [].slice.call(form.querySelectorAll('[data-error-for]'));
  feedbacks.forEach(hideFeedbackElement);
}

function normalizeValidationMessages(value) {
  if (Array.isArray(value)) {
    const normalized = [];
    value.forEach(function (item) {
      if (item === null || item === undefined) {
        return;
      }
      const text = String(item).trim();
      if (text) {
        normalized.push(text);
      }
    });
    return normalized;
  }
  if (value === null || value === undefined) {
    return [];
  }
  const stringValue = String(value).trim();
  return stringValue ? [stringValue] : [];
}

function showGeneralFormError(form, messages) {
  if (!messages || !messages.length) {
    return;
  }
  const element = findGeneralFeedbackElement(form);
  if (!element) {
    return;
  }
  element.textContent = messages.join(' ');
  element.removeAttribute('hidden');
  if (element.classList) {
    element.classList.add('d-block');
  }
}

function applyFormValidationErrors(form, errors) {
  if (!form || !errors || typeof errors !== 'object') {
    return;
  }
  let focusTarget = null;
  Object.keys(errors).forEach(function (field) {
    if (!Object.prototype.hasOwnProperty.call(errors, field)) {
      return;
    }
    const messages = normalizeValidationMessages(errors[field]);
    if (!messages.length) {
      return;
    }
    if (field === 'form' || field === '*' || field === '') {
      showGeneralFormError(form, messages);
      return;
    }
    const controls = getFormControls(form, field);
    const feedbacks = getFieldFeedbackElements(form, field);
    const feedback = feedbacks.length ? feedbacks[0] : null;
    if (feedback) {
      feedback.textContent = messages.join(' ');
      feedback.removeAttribute('hidden');
      if (feedback.classList) {
        feedback.classList.add('d-block');
      }
    }
    controls.forEach(function (control) {
      if (!control || !control.classList) {
        return;
      }
      control.classList.add('is-invalid');
      control.setAttribute('aria-invalid', 'true');
      if (feedback && feedback.id) {
        if (!control.hasAttribute('data-prev-aria-describedby')) {
          control.setAttribute('data-prev-aria-describedby', control.getAttribute('aria-describedby') || '');
        }
        const describedBy = control.getAttribute('aria-describedby') || '';
        const tokens = describedBy.split(/\s+/).filter(Boolean);
        if (tokens.indexOf(feedback.id) === -1) {
          tokens.push(feedback.id);
          control.setAttribute('aria-describedby', tokens.join(' '));
        }
      }
      if (!focusTarget && typeof control.focus === 'function') {
        focusTarget = control;
      }
    });
  });
  if (focusTarget) {
    try {
      focusTarget.focus();
    } catch (focusError) {
      /* ignore focus errors */
    }
  }
}

function initFormHelpers(root) {
  ensureDefaultFormHelpers();

  const scope = root || document;
  if (!scope || typeof scope.querySelectorAll !== 'function') {
    return;
  }
  const forms = [].slice.call(scope.querySelectorAll('form[data-form-helper]'));
  forms.forEach(function (form) {
    const helperName = (form.getAttribute('data-form-helper') || '').trim();
    if (!helperName) {
      return;
    }
    const current = formHelperInstances.get(form);
    if (current && current.name === helperName) {
      return;
    }
    if (current && typeof current.cleanup === 'function') {
      try {
        current.cleanup();
      } catch (cleanupError) {
        console.error('form helper cleanup failed for "' + current.name + '"', cleanupError);
      }
    }
    const helper = formHelperRegistry[helperName];
    if (typeof helper !== 'function') {
      return;
    }
    let cleanup = null;
    try {
      cleanup = helper(form) || null;
    } catch (err) {
      console.error('form helper "' + helperName + '" failed to initialize', err);
      cleanup = null;
    }
    formHelperInstances.set(form, { name: helperName, cleanup: cleanup });
  });
}

let defaultHelpersInitialized = false;

function ensureDefaultFormHelpers() {
  if (defaultHelpersInitialized) {
    return;
  }
  registerFormHelper('validation', function (form) {
    function handleInput(event) {
      const target = event && event.target ? event.target : null;
      if (!target || !target.name) {
        return;
      }
      clearFieldError(form, target.name);
      const general = findGeneralFeedbackElement(form);
      if (general) {
        hideFeedbackElement(general);
      }
    }

    form.addEventListener('input', handleInput);
    form.addEventListener('change', handleInput);

    return function () {
      form.removeEventListener('input', handleInput);
      form.removeEventListener('change', handleInput);
    };
  });
  defaultHelpersInitialized = true;
}

export {
  registerFormHelper,
  unregisterFormHelper,
  initFormHelpers,
  clearFormValidation,
  applyFormValidationErrors,
  ensureDefaultFormHelpers
};
