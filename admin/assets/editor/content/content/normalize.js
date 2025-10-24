import { ALLOWED_BLOCK_TAGS, createEmptyParagraph, wrapNodeInParagraph, ensureBlockPlaceholder } from './blocks.js';
import { isEmbedElement, ensureEmbedElementAttributes, convertBlocksToEmbeds } from '../embeds/index.js';

function createContentNormalizer({ area, wrapper, withCaretPreserved }) {
  return function normalizeEditorContent() {
    if (!area || !withCaretPreserved || wrapper.classList.contains('is-source')) {
      return;
    }
    withCaretPreserved(() => {
      const children = Array.prototype.slice.call(area.childNodes);
      children.forEach((child) => {
        if (child.nodeType === Node.TEXT_NODE) {
          const textValue = child.textContent || '';
          if (textValue.trim() === '') {
            area.removeChild(child);
          } else {
            const paragraph = document.createElement('p');
            paragraph.textContent = textValue;
            area.replaceChild(paragraph, child);
            ensureBlockPlaceholder(paragraph);
          }
          return;
        }
        if (isEmbedElement(child)) {
          ensureEmbedElementAttributes(child);
          return;
        }
        if (child.nodeType !== Node.ELEMENT_NODE) {
          return;
        }
        if (child.hasAttribute && child.hasAttribute('data-ce-marker')) {
          return;
        }
        const tag = child.tagName;
        if (tag === 'DIV') {
          const replacement = document.createElement('p');
          while (child.firstChild) {
            replacement.appendChild(child.firstChild);
          }
          if (replacement.innerHTML.trim() === '') {
            replacement.innerHTML = '<br>';
          }
          area.replaceChild(replacement, child);
          ensureBlockPlaceholder(replacement);
          return;
        }
        if (tag === 'BR') {
          const newParagraph = createEmptyParagraph();
          area.replaceChild(newParagraph, child);
          return;
        }
        if (ALLOWED_BLOCK_TAGS[tag]) {
          ensureBlockPlaceholder(child);
          return;
        }
        const wrapped = wrapNodeInParagraph(area, child);
        ensureBlockPlaceholder(wrapped);
      });
      const hasMeaningfulChild = Array.prototype.some.call(area.childNodes, (node) => {
        return !(node.nodeType === Node.ELEMENT_NODE && node.hasAttribute && node.hasAttribute('data-ce-marker'));
      });
      if (!hasMeaningfulChild) {
        area.appendChild(createEmptyParagraph());
      }
      convertBlocksToEmbeds(area, { markerSelector: '[data-ce-marker]' });
    });
  };
}

export { createContentNormalizer };
