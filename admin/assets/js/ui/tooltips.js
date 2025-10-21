export function initTooltips(root) {
  if (typeof bootstrap === 'undefined' || typeof bootstrap.Tooltip !== 'function') {
    return;
  }
  var scope = root || document;
  var tooltipElements = [].slice.call(scope.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipElements.forEach(function (el) {
    if (typeof bootstrap.Tooltip.getOrCreateInstance === 'function') {
      bootstrap.Tooltip.getOrCreateInstance(el);
    } else {
      new bootstrap.Tooltip(el);
    }
  });
}
