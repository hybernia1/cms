import { scanEditors } from './core/editor.js';

function bootEditors(root = document) {
  scanEditors(root);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    bootEditors(document);
  });
} else {
  bootEditors(document);
}

document.addEventListener('cms:admin:navigated', (event) => {
  const detail = event && event.detail ? event.detail : {};
  const root = detail.root && typeof detail.root.querySelectorAll === 'function' ? detail.root : document;
  bootEditors(root);
});
