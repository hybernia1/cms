(function () {
  function initEditor(textarea) {
    if (!textarea || textarea.__contentEditorInit) {
      return;
    }
    textarea.__contentEditorInit = true;

    var placeholder = textarea.getAttribute('data-placeholder') || 'Napiš obsah…';
    textarea.classList.add('d-none');

    var wrapper = document.createElement('div');
    wrapper.className = 'content-editor';

    var toolbar = document.createElement('div');
    toolbar.className = 'content-editor-toolbar';

    var area = document.createElement('div');
    area.className = 'content-editor-area';
    area.contentEditable = 'true';
    area.setAttribute('data-placeholder', placeholder);

    var sourceWrapper = document.createElement('div');
    sourceWrapper.className = 'content-editor-source';
    var sourceTextarea = document.createElement('textarea');
    sourceWrapper.appendChild(sourceTextarea);

    var buttons = [
      { cmd: 'bold', label: 'B', title: 'Tučné', state: true },
      { cmd: 'italic', label: 'I', title: 'Kurzíva', state: true },
      { cmd: 'underline', label: 'U', title: 'Podtržení', state: true },
      { cmd: 'insertUnorderedList', label: '•', title: 'Seznam' },
      { cmd: 'insertOrderedList', label: '1.', title: 'Číslovaný seznam' },
      { cmd: 'formatBlock', label: 'H2', title: 'Nadpis', value: 'h2' },
      { cmd: 'formatBlock', label: 'Odstavec', title: 'Odstavec', value: 'p' },
      { cmd: 'createLink', label: 'Odkaz', title: 'Vložit odkaz', prompt: true },
      { cmd: 'removeFormat', label: 'Vymazat', title: 'Odstranit formátování' }
    ];

    buttons.forEach(function (buttonConfig) {
      var button = document.createElement('button');
      button.type = 'button';
      button.textContent = buttonConfig.label;
      button.title = buttonConfig.title;
      button.addEventListener('click', function () {
        if (wrapper.classList.contains('is-source')) {
          return;
        }
        area.focus();
        if (buttonConfig.cmd === 'createLink') {
          var url = window.prompt('Zadej adresu odkazu:');
          if (url) {
            document.execCommand('createLink', false, url);
          }
        } else if (buttonConfig.cmd === 'formatBlock') {
          document.execCommand('formatBlock', false, buttonConfig.value || 'p');
        } else {
          document.execCommand(buttonConfig.cmd, false, buttonConfig.value || null);
        }
        syncState();
        updateTextarea();
      });
      toolbar.appendChild(button);
      buttonConfig._el = button;
    });

    var sourceToggle = document.createElement('button');
    sourceToggle.type = 'button';
    sourceToggle.className = 'content-editor-source-toggle btn btn-sm btn-outline-secondary';
    sourceToggle.textContent = 'HTML';
    sourceToggle.addEventListener('click', function () {
      wrapper.classList.toggle('is-source');
      if (wrapper.classList.contains('is-source')) {
        sourceTextarea.value = textarea.value;
        sourceToggle.classList.add('active');
      } else {
        area.innerHTML = sourceTextarea.value;
        sourceToggle.classList.remove('active');
      }
      updateTextarea();
    });
    toolbar.appendChild(sourceToggle);

    wrapper.appendChild(toolbar);
    wrapper.appendChild(area);
    wrapper.appendChild(sourceWrapper);
    textarea.parentNode.insertBefore(wrapper, textarea.nextSibling);

    function updateTextarea() {
      if (wrapper.classList.contains('is-source')) {
        textarea.value = sourceTextarea.value;
      } else {
        textarea.value = area.innerHTML;
        sourceTextarea.value = textarea.value;
      }
    }

    function syncState() {
      if (wrapper.classList.contains('is-source')) {
        return;
      }
      buttons.forEach(function (buttonConfig) {
        if (!buttonConfig.state) {
          return;
        }
        var isActive = document.queryCommandState(buttonConfig.cmd);
        if (isActive) {
          buttonConfig._el.classList.add('active');
        } else {
          buttonConfig._el.classList.remove('active');
        }
      });
    }

    function restoreInitialContent() {
      var value = textarea.value || '';
      area.innerHTML = value;
      sourceTextarea.value = value;
      updateTextarea();
    }

    area.addEventListener('input', function () {
      updateTextarea();
      syncState();
    });

    area.addEventListener('keyup', syncState);
    area.addEventListener('mouseup', syncState);
    document.addEventListener('selectionchange', function () {
      if (document.activeElement === area) {
        syncState();
      }
    });

    sourceTextarea.addEventListener('input', updateTextarea);

    var form = textarea.closest('form');
    if (form) {
      form.addEventListener('submit', function () {
        updateTextarea();
      });
    }

    restoreInitialContent();
  }

  function scanEditors(root) {
    var scope = root && typeof root.querySelectorAll === 'function' ? root : document;
    var editors = scope.querySelectorAll ? scope.querySelectorAll('textarea[data-content-editor]') : [];
    editors.forEach(initEditor);
  }

  document.addEventListener('DOMContentLoaded', function () {
    scanEditors(document);
  });

  document.addEventListener('cms:admin:navigated', function (event) {
    var detail = event && event.detail ? event.detail : {};
    var root = detail.root && typeof detail.root.querySelectorAll === 'function' ? detail.root : document;
    scanEditors(root);
  });
})();
