(function () {
  function parseJson(str) {
    if (!str) {
      return [];
    }
    try {
      var parsed = JSON.parse(str);
      return Array.isArray(parsed) ? parsed : [];
    } catch (err) {
      return [];
    }
  }

  function normalizeItem(raw) {
    if (!raw || typeof raw !== 'object') {
      return null;
    }
    var id = 0;
    if (typeof raw.id === 'number') {
      id = raw.id;
    } else if (typeof raw.id === 'string' && raw.id.trim() !== '') {
      id = parseInt(raw.id, 10);
    }
    if (!Number.isFinite(id) || id < 0) {
      id = 0;
    }
    var label = '';
    if (typeof raw.value === 'string' && raw.value.trim() !== '') {
      label = raw.value.trim();
    } else if (typeof raw.name === 'string' && raw.name.trim() !== '') {
      label = raw.name.trim();
    } else if (typeof raw.slug === 'string' && raw.slug.trim() !== '') {
      label = raw.slug.trim();
    } else if (id > 0) {
      label = 'ID ' + id;
    }
    if (label === '') {
      return null;
    }
    return {
      id: id,
      label: label,
      slug: typeof raw.slug === 'string' ? raw.slug : '',
    };
  }

  function dedupe(items) {
    var seenIds = new Set();
    var seenLabels = new Set();
    var out = [];
    items.forEach(function (item) {
      if (!item) {
        return;
      }
      var id = item.id > 0 ? item.id : 0;
      var labelKey = item.label.toLowerCase();
      if (id > 0) {
        if (seenIds.has(id)) {
          return;
        }
        seenIds.add(id);
      } else {
        if (seenLabels.has(labelKey)) {
          return;
        }
        seenLabels.add(labelKey);
      }
      out.push(item);
    });
    return out;
  }

  function createSuggestionList(items, query, selectedIds) {
    var normalizedQuery = query.trim().toLowerCase();
    var filtered = items.filter(function (item) {
      if (!item) return false;
      if (selectedIds.has(item.id)) return false;
      if (normalizedQuery === '') return true;
      return item.label.toLowerCase().indexOf(normalizedQuery) !== -1;
    });
    return filtered.slice(0, 20);
  }

  function initTagField(el) {
    if (!el) return;
    if (el.__tagFieldInit) return;
    el.__tagFieldInit = true;

    var whitelist = dedupe(parseJson(el.getAttribute('data-whitelist')).map(normalizeItem));
    var selectedRaw = parseJson(el.getAttribute('data-selected')).map(normalizeItem);
    var selected = dedupe(selectedRaw);

    var existingName = el.getAttribute('data-existing-name') || '';
    var newName = el.getAttribute('data-new-name') || '';
    var placeholder = el.getAttribute('data-placeholder') || 'Přidej položku';
    var helperText = el.getAttribute('data-helper') || '';
    var emptyText = el.getAttribute('data-empty') || 'Žádné položky nejsou vybrány.';

    el.classList.add('tag-field');

    var hiddenContainer = el.querySelector('[data-tag-hidden]');
    if (!hiddenContainer) {
      hiddenContainer = document.createElement('div');
      hiddenContainer.setAttribute('data-tag-hidden', '');
      el.appendChild(hiddenContainer);
    }

    var chips = document.createElement('div');
    chips.className = 'tag-field-chips';
    el.appendChild(chips);

    var emptyLabel = document.createElement('div');
    emptyLabel.className = 'tag-field-empty';
    emptyLabel.textContent = emptyText;
    chips.appendChild(emptyLabel);

    var inputWrapper = document.createElement('div');
    inputWrapper.className = 'tag-field-input-wrapper';

    var input = document.createElement('input');
    input.type = 'text';
    input.className = 'form-control tag-field-input';
    input.placeholder = placeholder;
    input.autocomplete = 'off';

    var suggestionBox = document.createElement('div');
    suggestionBox.className = 'tag-suggestions';

    inputWrapper.appendChild(input);
    inputWrapper.appendChild(suggestionBox);
    el.appendChild(inputWrapper);

    if (helperText) {
      var helper = document.createElement('div');
      helper.className = 'tag-field-helper';
      helper.textContent = helperText;
      el.appendChild(helper);
    }

    var state = selected.slice();

    function updateHiddenInputs() {
      while (hiddenContainer.firstChild) {
        hiddenContainer.removeChild(hiddenContainer.firstChild);
      }
      var newValues = [];
      state.forEach(function (item) {
        if (item.id > 0) {
          if (existingName) {
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = existingName;
            hidden.value = String(item.id);
            hiddenContainer.appendChild(hidden);
          }
        } else if (item.label.trim() !== '') {
          newValues.push(item.label.trim());
        }
      });
      if (newName) {
        var newInput = document.createElement('input');
        newInput.type = 'hidden';
        newInput.name = newName;
        newInput.value = newValues.join(', ');
        hiddenContainer.appendChild(newInput);
      }
    }

    function renderChips() {
      chips.innerHTML = '';
      if (!state.length) {
        var emptyState = document.createElement('div');
        emptyState.className = 'tag-field-empty';
        emptyState.textContent = emptyText;
        chips.appendChild(emptyState);
        return;
      }
      state.forEach(function (item, index) {
        var chip = document.createElement('span');
        chip.className = 'tag-chip';
        chip.textContent = item.label;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.setAttribute('aria-label', 'Odebrat');
        btn.innerHTML = '&times;';
        btn.addEventListener('click', function () {
          state.splice(index, 1);
          renderChips();
          updateHiddenInputs();
        });
        chip.appendChild(btn);
        chips.appendChild(chip);
      });
    }

    function hasLabel(label) {
      var target = label.trim().toLowerCase();
      return state.some(function (item) {
        if (item.id > 0) {
          return false;
        }
        return item.label.trim().toLowerCase() === target;
      });
    }

    function hasId(id) {
      return state.some(function (item) { return item.id === id && id > 0; });
    }

    function addItem(item) {
      if (!item) return;
      if (item.id > 0 && hasId(item.id)) {
        return;
      }
      if (item.id === 0 && (item.label.trim() === '' || hasLabel(item.label))) {
        return;
      }
      state.push(item);
      renderChips();
      updateHiddenInputs();
    }

    function closeSuggestions() {
      suggestionBox.innerHTML = '';
      suggestionBox.classList.remove('show');
      suggestionBox.dataset.activeIndex = '';
    }

    function openSuggestions(list) {
      suggestionBox.innerHTML = '';
      if (!list.length) {
        closeSuggestions();
        return;
      }
      list.forEach(function (item, idx) {
        var option = document.createElement('div');
        option.className = 'tag-suggestion-item';
        option.textContent = item.label;
        option.dataset.index = String(idx);
        option.addEventListener('mousedown', function (evt) {
          evt.preventDefault();
          addItem({ id: item.id, label: item.label });
          input.value = '';
          closeSuggestions();
        });
        suggestionBox.appendChild(option);
      });
      suggestionBox.classList.add('show');
      suggestionBox.dataset.activeIndex = '0';
      setActiveSuggestion(0);
    }

    function setActiveSuggestion(index) {
      var children = suggestionBox.querySelectorAll('.tag-suggestion-item');
      children.forEach(function (child) { child.classList.remove('active'); });
      if (index < 0 || index >= children.length) {
        suggestionBox.dataset.activeIndex = '';
        return;
      }
      children[index].classList.add('active');
      suggestionBox.dataset.activeIndex = String(index);
    }

    function moveActive(delta) {
      var children = suggestionBox.querySelectorAll('.tag-suggestion-item');
      if (!children.length) {
        return;
      }
      var current = parseInt(suggestionBox.dataset.activeIndex || '0', 10);
      if (!Number.isFinite(current)) {
        current = 0;
      }
      var next = current + delta;
      if (next < 0) {
        next = children.length - 1;
      } else if (next >= children.length) {
        next = 0;
      }
      setActiveSuggestion(next);
    }

    input.addEventListener('input', function () {
      var value = input.value || '';
      var list = createSuggestionList(
        whitelist,
        value,
        new Set(state.filter(function (item) { return item.id > 0; }).map(function (item) { return item.id; }))
      );
      if (list.length) {
        openSuggestions(list);
      } else {
        closeSuggestions();
      }
    });

    input.addEventListener('keydown', function (evt) {
      if (evt.key === 'Enter' || evt.key === ',') {
        evt.preventDefault();
        var activeIndex = parseInt(suggestionBox.dataset.activeIndex || '', 10);
        var hasActive = suggestionBox.classList.contains('show') && Number.isInteger(activeIndex);
        if (hasActive) {
          var option = suggestionBox.querySelector('.tag-suggestion-item[data-index="' + activeIndex + '"]');
          if (option) {
            var text = option.textContent || '';
            var item = whitelist.find(function (w) { return w && w.label === text; });
            if (item) {
              addItem({ id: item.id, label: item.label });
            }
          }
        } else {
          var raw = input.value.trim();
          if (raw !== '') {
            var existing = whitelist.find(function (w) { return w && w.label.toLowerCase() === raw.toLowerCase(); });
            if (existing) {
              addItem({ id: existing.id, label: existing.label });
            } else {
              addItem({ id: 0, label: raw });
            }
          }
        }
        input.value = '';
        closeSuggestions();
        return;
      }
      if (evt.key === 'Backspace' && input.value === '') {
        state.pop();
        renderChips();
        updateHiddenInputs();
        closeSuggestions();
        return;
      }
      if (evt.key === 'ArrowDown') {
        evt.preventDefault();
        moveActive(1);
      } else if (evt.key === 'ArrowUp') {
        evt.preventDefault();
        moveActive(-1);
      } else if (evt.key === 'Escape') {
        closeSuggestions();
      }
    });

    input.addEventListener('blur', function () {
      setTimeout(closeSuggestions, 150);
    });

    renderChips();
    updateHiddenInputs();
  }

  document.addEventListener('DOMContentLoaded', function () {
    var fields = document.querySelectorAll('[data-tag-field]');
    fields.forEach(initTagField);
  });
})();
