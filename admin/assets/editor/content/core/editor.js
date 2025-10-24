import { openLinkDialog } from '../dialogs/link-dialog.js';
import { openImageDialog } from '../dialogs/image-dialog.js';
import { createToolbar } from '../toolbar/index.js';
import { createCaretManager } from '../selection/caret.js';
import { createAttachmentsManager } from '../media/attachments.js';
import { createContentNormalizer } from '../content/normalize.js';

function initEditor(textarea) {
  if (!textarea || textarea.__contentEditorInit) {
    return;
  }
  textarea.__contentEditorInit = true;

  const placeholder = textarea.getAttribute('data-placeholder') || 'Napiš obsah…';
  textarea.classList.add('d-none');

  try {
    if (document && typeof document.execCommand === 'function') {
      document.execCommand('defaultParagraphSeparator', false, 'p');
    }
  } catch (err) {
    // Ignore unsupported command errors.
  }

  const wrapper = document.createElement('div');
  wrapper.className = 'content-editor';

  const area = document.createElement('div');
  area.className = 'content-editor-area';
  area.contentEditable = 'true';
  area.setAttribute('data-placeholder', placeholder);

  const sourceWrapper = document.createElement('div');
  sourceWrapper.className = 'content-editor-source';
  const sourceTextarea = document.createElement('textarea');
  sourceWrapper.appendChild(sourceTextarea);

  let attachmentsInput = null;
  const attachmentsSelector = textarea.getAttribute('data-attachments-input');
  if (attachmentsSelector) {
    attachmentsInput = document.querySelector(attachmentsSelector);
  }
  const attachmentsManager = createAttachmentsManager({ input: attachmentsInput });

  const form = textarea.closest('form');
  let csrfToken = '';
  if (form) {
    const csrfInput = form.querySelector('input[name="csrf"]');
    if (csrfInput) {
      csrfToken = csrfInput.value || '';
    }
  }
  const postIdAttr = textarea.getAttribute('data-post-id');
  let postId = parseInt(postIdAttr || '', 10);
  if (Number.isNaN(postId) || postId <= 0) {
    postId = null;
  }

  const caret = createCaretManager(area);
  const normalizeEditorContent = createContentNormalizer({
    area,
    wrapper,
    withCaretPreserved: caret.withCaretPreserved
  });

  let buttonConfigs = [];

  function syncState() {
    if (wrapper.classList.contains('is-source')) {
      return;
    }
    buttonConfigs.forEach((buttonConfig) => {
      if (!buttonConfig.state || !buttonConfig._el) {
        return;
      }
      const isActive = document.queryCommandState(buttonConfig.cmd);
      if (isActive) {
        buttonConfig._el.classList.add('active');
      } else {
        buttonConfig._el.classList.remove('active');
      }
    });
  }

  function refreshAttachmentsFromHtml(html) {
    attachmentsManager.refreshFromHtml(html || '');
  }

  function updateTextarea() {
    let currentHtml;
    if (wrapper.classList.contains('is-source')) {
      textarea.value = sourceTextarea.value;
      currentHtml = sourceTextarea.value;
    } else {
      normalizeEditorContent();
      textarea.value = area.innerHTML;
      sourceTextarea.value = textarea.value;
      currentHtml = textarea.value;
    }
    refreshAttachmentsFromHtml(currentHtml);
  }

  function restoreSelectionOrMoveToEnd() {
    if (caret.restoreSelection()) {
      return;
    }
    const tempRange = document.createRange();
    tempRange.selectNodeContents(area);
    tempRange.collapse(false);
    const tempSel = window.getSelection();
    if (tempSel) {
      tempSel.removeAllRanges();
      tempSel.addRange(tempRange);
    }
  }

  function insertLink(url, openInNewTab) {
    if (!url) {
      return;
    }
    restoreSelectionOrMoveToEnd();
    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) {
      return;
    }
    const range = selection.getRangeAt(0);
    if (selection.isCollapsed) {
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.textContent = url;
      if (openInNewTab) {
        anchor.setAttribute('target', '_blank');
        anchor.setAttribute('rel', 'noopener');
      }
      range.insertNode(anchor);
      range.setStartAfter(anchor);
      range.collapse(true);
      selection.removeAllRanges();
      selection.addRange(range);
    } else {
      document.execCommand('createLink', false, url);
      const createdAnchor = caret.findAnchorAtSelection();
      if (createdAnchor) {
        if (openInNewTab) {
          createdAnchor.setAttribute('target', '_blank');
          createdAnchor.setAttribute('rel', 'noopener');
        } else {
          createdAnchor.removeAttribute('target');
          createdAnchor.removeAttribute('rel');
        }
      }
    }
    caret.saveSelection();
    updateTextarea();
    syncState();
  }

  function insertImage(item, altText) {
    if (!item || !item.url) {
      return;
    }
    restoreSelectionOrMoveToEnd();
    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) {
      return;
    }
    const range = selection.getRangeAt(0);
    range.deleteContents();
    const fragment = document.createDocumentFragment();
    const img = document.createElement('img');
    img.src = item.url;
    img.alt = altText || '';
    img.setAttribute('loading', 'lazy');
    if (item.id) {
      img.setAttribute('data-media-id', String(item.id));
    }
    fragment.appendChild(img);
    const spacer = document.createElement('br');
    fragment.appendChild(spacer);
    range.insertNode(fragment);
    range.setStartAfter(spacer);
    range.collapse(true);
    selection.removeAllRanges();
    selection.addRange(range);
    caret.saveSelection();
    updateTextarea();
    syncState();
  }

  function showLinkDialog() {
    const anchor = caret.findAnchorAtSelection();
    openLinkDialog({
      defaultValue: anchor ? anchor.getAttribute('href') || '' : '',
      openInNewTab: anchor ? anchor.getAttribute('target') === '_blank' : false,
      onSubmit: (url, openInNewTab) => {
        insertLink(url, openInNewTab);
      }
    });
  }

  function showImageDialog() {
    openImageDialog({
      csrf: csrfToken,
      postId,
      onInsert: (item, altText) => {
        insertImage(item, altText);
      }
    });
  }

  const { toolbar, buttons } = createToolbar({
    area,
    wrapper,
    textarea,
    sourceTextarea,
    saveSelection: caret.saveSelection,
    syncState,
    updateTextarea,
    showLinkDialog,
    showImageDialog
  });
  buttonConfigs = buttons;

  wrapper.appendChild(toolbar);
  wrapper.appendChild(area);
  wrapper.appendChild(sourceWrapper);
  if (textarea.parentNode) {
    textarea.parentNode.insertBefore(wrapper, textarea.nextSibling);
  }

  function restoreInitialContent() {
    const value = textarea.value || '';
    area.innerHTML = value;
    sourceTextarea.value = value;
    updateTextarea();
    syncState();
    caret.saveSelection();
  }

  area.addEventListener('input', () => {
    updateTextarea();
    syncState();
    caret.saveSelection();
  });

  area.addEventListener('keyup', () => {
    syncState();
    caret.saveSelection();
  });

  area.addEventListener('mouseup', caret.saveSelection);
  area.addEventListener('focus', caret.saveSelection);

  sourceTextarea.addEventListener('input', updateTextarea);

  document.addEventListener('selectionchange', () => {
    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) {
      return;
    }
    const range = selection.getRangeAt(0);
    if (caret.selectionInsideArea(range) && document.activeElement === area) {
      caret.setSavedRange(range);
      syncState();
    }
  });

  if (form) {
    form.addEventListener('submit', () => {
      updateTextarea();
    });
  }

  restoreInitialContent();
}

function scanEditors(root) {
  const scope = root && typeof root.querySelectorAll === 'function' ? root : document;
  const editors = scope.querySelectorAll ? scope.querySelectorAll('textarea[data-content-editor]') : [];
  editors.forEach(initEditor);
}

export { initEditor, scanEditors };
