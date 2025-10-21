import {
  cmsAdmin,
  refreshDynamicUI,
  loadAdminPage,
  initAjaxForms,
  initAjaxLinks,
  bootHistory,
  dispatchNavigated
} from './admin.js';

function exposeAdminAPI() {
  window.cmsAdmin = cmsAdmin;
}

function startAdminUI() {
  const initialRoot = document.querySelector('.admin-wrapper') || document;
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

exposeAdminAPI();

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', startAdminUI);
} else {
  startAdminUI();
}
