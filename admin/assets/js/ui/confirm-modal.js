let confirmModalElement = null;
let confirmModalInstance = null;
let confirmModalCallback = null;

function ensureConfirmModal() {
  if (confirmModalElement) {
    return confirmModalElement;
  }
  const element = document.getElementById('adminConfirmModal');
  if (!element) {
    return null;
  }
  if (typeof bootstrap === 'undefined' || typeof bootstrap.Modal !== 'function') {
    return null;
  }
  confirmModalElement = element;
  confirmModalInstance = bootstrap.Modal.getOrCreateInstance(element);
  const confirmButton = element.querySelector('[data-confirm-modal-confirm]');
  const cancelButton = element.querySelector('[data-confirm-modal-cancel]');
  if (confirmButton && !confirmButton.dataset.defaultLabel) {
    confirmButton.dataset.defaultLabel = confirmButton.textContent || '';
  }
  if (cancelButton && !cancelButton.dataset.defaultLabel) {
    cancelButton.dataset.defaultLabel = cancelButton.textContent || '';
  }
  if (confirmButton && confirmButton.dataset.confirmModalBound !== '1') {
    confirmButton.dataset.confirmModalBound = '1';
    confirmButton.addEventListener('click', function () {
      if (confirmModalInstance) {
        confirmModalInstance.hide();
      }
      if (confirmModalCallback) {
        const callback = confirmModalCallback;
        confirmModalCallback = null;
        callback(true);
      }
    });
  }
  if (element.dataset.confirmModalHiddenBound !== '1') {
    element.dataset.confirmModalHiddenBound = '1';
    element.addEventListener('hidden.bs.modal', function () {
      if (confirmModalCallback) {
        const callback = confirmModalCallback;
        confirmModalCallback = null;
        callback(false);
      }
    });
  }
  return confirmModalElement;
}

function showConfirmModal(options) {
  const modalOptions = options || {};
  const modalElement = ensureConfirmModal();
  if (!modalElement || !confirmModalInstance) {
    const fallbackMessage = modalOptions.message || 'Opravdu chcete pokračovat?';
    if (window.confirm(fallbackMessage)) {
      if (typeof modalOptions.onConfirm === 'function') {
        modalOptions.onConfirm();
      }
    } else if (typeof modalOptions.onCancel === 'function') {
      modalOptions.onCancel();
    }
    return;
  }

  const titleEl = modalElement.querySelector('[data-confirm-modal-title]');
  if (titleEl) {
    const titleText = (typeof modalOptions.title === 'string' && modalOptions.title.trim() !== '')
      ? modalOptions.title
      : 'Potvrzení akce';
    titleEl.textContent = titleText;
  }

  const messageEl = modalElement.querySelector('[data-confirm-modal-message]');
  if (messageEl) {
    const messageText = (typeof modalOptions.message === 'string' && modalOptions.message.trim() !== '')
      ? modalOptions.message
      : 'Opravdu chcete pokračovat?';
    messageEl.textContent = messageText;
  }

  const confirmBtn = modalElement.querySelector('[data-confirm-modal-confirm]');
  if (confirmBtn) {
    const confirmLabel = (typeof modalOptions.confirmLabel === 'string' && modalOptions.confirmLabel.trim() !== '')
      ? modalOptions.confirmLabel
      : (confirmBtn.dataset.defaultLabel || confirmBtn.textContent || '');
    confirmBtn.textContent = confirmLabel;
  }

  const cancelBtn = modalElement.querySelector('[data-confirm-modal-cancel]');
  if (cancelBtn) {
    const cancelLabel = (typeof modalOptions.cancelLabel === 'string' && modalOptions.cancelLabel.trim() !== '')
      ? modalOptions.cancelLabel
      : (cancelBtn.dataset.defaultLabel || cancelBtn.textContent || '');
    cancelBtn.textContent = cancelLabel;
  }

  confirmModalCallback = function (confirmed) {
    if (confirmed) {
      if (typeof modalOptions.onConfirm === 'function') {
        modalOptions.onConfirm();
      }
    } else if (typeof modalOptions.onCancel === 'function') {
      modalOptions.onCancel();
    }
  };

  confirmModalInstance.show();
}

export function initConfirmModals(root) {
  const scope = root || document;
  const forms = [].slice.call(scope.querySelectorAll('form[data-confirm-modal]'));
  forms.forEach(function (form) {
    if (form.dataset.confirmModalBound === '1') {
      return;
    }
    form.dataset.confirmModalBound = '1';
    form.addEventListener('submit', function (event) {
      if (form.dataset.confirmModalHandled === '1') {
        delete form.dataset.confirmModalHandled;
        return;
      }
      event.preventDefault();
      const message = form.getAttribute('data-confirm-modal') || '';
      const title = form.getAttribute('data-confirm-modal-title') || '';
      const confirmLabel = form.getAttribute('data-confirm-modal-confirm-label') || '';
      const cancelLabel = form.getAttribute('data-confirm-modal-cancel-label') || '';
      showConfirmModal({
        message: message,
        title: title,
        confirmLabel: confirmLabel,
        cancelLabel: cancelLabel,
        onConfirm: function () {
          form.dataset.confirmModalHandled = '1';
          form.submit();
        }
      });
    });
  });
}

export { showConfirmModal };
