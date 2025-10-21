export function cssEscapeValue(value) {
  var str = String(value);
  if (window.CSS && typeof window.CSS.escape === 'function') {
    return window.CSS.escape(str);
  }
  return str.replace(/\\\\/g, '\\\\\\').replace(/"/g, '\\"');
}
