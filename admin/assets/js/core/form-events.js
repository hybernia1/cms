export function dispatchFormEvent(form, name, detail) {
  if (!(form instanceof HTMLFormElement)) {
    return;
  }
  const eventDetail = detail || {};
  let evt;
  try {
    evt = new CustomEvent(name, { detail: eventDetail });
  } catch (err) {
    evt = document.createEvent('CustomEvent');
    evt.initCustomEvent(name, true, true, eventDetail);
  }
  form.dispatchEvent(evt);
}
