import {
  cmsAdmin,
  refreshDynamicUI as baseRefreshDynamicUI,
  loadAdminPage,
  initAjaxForms,
  initAjaxLinks,
  initMediaUploadModals,
  bootHistory,
  dispatchNavigated
} from './admin.js';
import { ensureDefaultFormHelpers } from './core/form-helpers.js';
import { configureAjax } from './core/ajax.js';
import { initConfirmModals } from './ui/confirm-modal.js';
import { initPostAutosave } from './ui/autosave.js';

function refreshDynamicUI(root) {
  baseRefreshDynamicUI(root);
  initConfirmModals(root);
  initPostAutosave(root, { loadAdminPage });
}

function exposeAdminAPI() {
  cmsAdmin.refresh = refreshDynamicUI;
  window.cmsAdmin = cmsAdmin;
}

function startAdminUI() {
  const initialRoot = document.querySelector('.admin-wrapper') || document;
  ensureDefaultFormHelpers();
  initMediaUploadModals(document);
  refreshDynamicUI(initialRoot);
  initAjaxForms();
  initAjaxLinks();
  bootHistory();
  dispatchNavigated(window.location.href, {
    initial: true,
    source: 'initial',
    root: initialRoot
  });
}

configureAjax({ refreshDynamicUI });
exposeAdminAPI();

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', startAdminUI);
} else {
  startAdminUI();
}
