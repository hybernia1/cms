function createToolbar({
  area,
  wrapper,
  textarea,
  sourceTextarea,
  saveSelection,
  syncState,
  updateTextarea,
  showLinkDialog,
  showImageDialog
}) {
  const toolbar = document.createElement('div');
  toolbar.className = 'content-editor-toolbar';

  const buttonConfigs = [
    { cmd: 'bold', icon: 'bi-type-bold', title: 'Tučné', srLabel: 'Tučné', state: true },
    { cmd: 'italic', icon: 'bi-type-italic', title: 'Kurzíva', srLabel: 'Kurzíva', state: true },
    { cmd: 'underline', icon: 'bi-type-underline', title: 'Podtržení', srLabel: 'Podtržení', state: true },
    { cmd: 'insertUnorderedList', icon: 'bi-list-ul', title: 'Seznam', srLabel: 'Seznam' },
    { cmd: 'insertOrderedList', icon: 'bi-list-ol', title: 'Číslovaný seznam', srLabel: 'Číslovaný seznam' },
    { cmd: 'formatBlock', icon: 'bi-type-h2', title: 'Nadpis', srLabel: 'Nadpis', value: 'h2' },
    { cmd: 'formatBlock', icon: 'bi-text-paragraph', title: 'Odstavec', srLabel: 'Odstavec', value: 'p' },
    { cmd: 'createLink', icon: 'bi-link-45deg', title: 'Vložit odkaz', srLabel: 'Vložit odkaz', modal: 'link' },
    { cmd: 'insertImage', icon: 'bi-image', title: 'Vložit obrázek', srLabel: 'Vložit obrázek', modal: 'image' },
    { cmd: 'removeFormat', icon: 'bi-eraser', title: 'Odstranit formátování', srLabel: 'Odstranit formátování' }
  ];

  buttonConfigs.forEach((config) => {
    const button = document.createElement('button');
    button.type = 'button';
    const srLabel = config.srLabel || config.title || '';
    button.title = config.title || srLabel || '';
    if (srLabel) {
      button.setAttribute('aria-label', srLabel);
    }
    if (config.icon) {
      button.innerHTML =
        `<i class="bi ${config.icon}" aria-hidden="true"></i>` +
        `<span class="visually-hidden">${srLabel}</span>`;
    } else if (config.label) {
      button.textContent = config.label;
    } else if (srLabel) {
      button.textContent = srLabel;
    }
    button.addEventListener('click', (evt) => {
      evt.preventDefault();
      if (wrapper.classList.contains('is-source')) {
        return;
      }
      area.focus();
      saveSelection();
      if (config.modal === 'link') {
        showLinkDialog();
        return;
      }
      if (config.modal === 'image') {
        showImageDialog();
        return;
      }
      if (config.cmd === 'formatBlock') {
        document.execCommand('formatBlock', false, config.value || 'p');
      } else {
        document.execCommand(config.cmd, false, config.value || null);
      }
      syncState();
      updateTextarea();
      saveSelection();
    });
    toolbar.appendChild(button);
    config._el = button;
  });

  const sourceToggle = document.createElement('button');
  sourceToggle.type = 'button';
  sourceToggle.className = 'content-editor-source-toggle btn btn-sm btn-outline-secondary';
  sourceToggle.textContent = 'HTML';
  sourceToggle.addEventListener('click', () => {
    wrapper.classList.toggle('is-source');
    if (wrapper.classList.contains('is-source')) {
      sourceTextarea.value = textarea.value;
      sourceToggle.classList.add('active');
    } else {
      area.innerHTML = sourceTextarea.value;
      sourceToggle.classList.remove('active');
    }
    updateTextarea();
    saveSelection();
    syncState();
  });
  toolbar.appendChild(sourceToggle);

  return { toolbar, buttons: buttonConfigs, sourceToggle };
}

export { createToolbar };
