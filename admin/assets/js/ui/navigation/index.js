import { initConfirmModals } from '../confirm-modal.js';
import { initTooltips } from '../tooltips.js';

const navigationManagers = new WeakMap();

export function initNavigationQuickAdd(root) {
  var modal = document.getElementById('navigationContentModal');
  if (!modal) {
    return;
  }

  var defaultForm = document.getElementById('navigation-item-form');
  if (defaultForm) {
    initNavigationLinkSourceControls(defaultForm);
  }

  var fillButtons = [].slice.call(modal.querySelectorAll('[data-nav-fill]'));
  fillButtons.forEach(function (btn) {
    if (btn.dataset.navFillBound === '1') {
      return;
    }
    btn.dataset.navFillBound = '1';
    btn.addEventListener('click', function () {
      var targetSelector = btn.getAttribute('data-nav-target') || '#navigation-item-form';
      var form;
      try {
        form = document.querySelector(targetSelector);
      } catch (err) {
        form = document.getElementById('navigation-item-form');
      }
      if (!form) {
        return;
      }

      initNavigationLinkSourceControls(form);

      var titleInput = form.querySelector('[name="title"]');
      var urlInput = form.querySelector('[name="url"]');
      var typeInput = form.querySelector('[name="link_type"]');
      var referenceInput = form.querySelector('[name="link_reference"]');
      var badge = form.querySelector('[data-nav-link-badge]');
      var badgeLabel = form.querySelector('[data-nav-link-type-label]');
      var metaInfo = form.querySelector('[data-nav-link-meta]');
      var warningInfo = form.querySelector('[data-nav-link-warning]');
      var titleValue = btn.getAttribute('data-nav-title') || '';
      var urlValue = btn.getAttribute('data-nav-url') || '';
      var typeValue = btn.getAttribute('data-nav-type') || 'custom';
      var referenceValue = btn.getAttribute('data-nav-reference') || '';

      if (titleInput && typeof titleInput.value !== 'undefined') {
        titleInput.value = titleValue;
      }

      if (urlInput && typeof urlInput.value !== 'undefined') {
        urlInput.value = urlValue;
      }

      if (typeInput && typeof typeInput.value !== 'undefined') {
        typeInput.value = typeValue;
      }

      if (referenceInput && typeof referenceInput.value !== 'undefined') {
        referenceInput.value = referenceValue;
      }

      if (badge && badge.classList) {
        if (typeValue === 'custom') {
          badge.classList.add('d-none');
        } else {
          badge.classList.remove('d-none');
        }
      }

      if (badgeLabel && typeof badgeLabel.textContent !== 'undefined') {
        badgeLabel.textContent = btn.getAttribute('data-nav-type-label') || typeValue;
      }

      if (metaInfo && typeof metaInfo.textContent !== 'undefined') {
        var meta = btn.getAttribute('data-nav-meta') || '';
        if (meta === '' && metaInfo.classList) {
          metaInfo.classList.add('d-none');
        } else {
          metaInfo.textContent = meta;
          if (metaInfo.classList) {
            metaInfo.classList.remove('d-none');
          }
        }
      }

      if (warningInfo && warningInfo.classList) {
        warningInfo.textContent = '';
        warningInfo.classList.add('d-none');
      }

      var clearButtons = [].slice.call(form.querySelectorAll('[data-nav-clear-source]'));
      clearButtons.forEach(function (clearBtn) {
        clearBtn.textContent = typeValue === 'custom' ? 'Přepnout na vlastní URL' : 'Zrušit napojení';
      });

      if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal === 'function') {
        bootstrap.Modal.getOrCreateInstance(modal).hide();
      }

      var focusTarget = titleInput || urlInput;
      if (focusTarget && typeof focusTarget.focus === 'function') {
        try {
          focusTarget.focus();
          if (typeof focusTarget.select === 'function' && focusTarget.value) {
            focusTarget.select();
          }
        } catch (err) {
          /* ignore focus issues */
        }
      }

      var highlightTarget = form;
      if (form && typeof form.closest === 'function') {
        var closestCard = form.closest('.card');
        if (closestCard) {
          highlightTarget = closestCard;
        }
      }

      if (highlightTarget && highlightTarget.classList) {
        highlightTarget.classList.add('navigation-item-filled');
        window.setTimeout(function () {
          highlightTarget.classList.remove('navigation-item-filled');
        }, 1500);
      }
    });
  });
}

export function initNavigationLinkSourceControls(form) {
  if (!form || form.dataset.navLinkBound === '1') {
    return;
  }
  form.dataset.navLinkBound = '1';

  var typeInput = form.querySelector('[name="link_type"]');
  var referenceInput = form.querySelector('[name="link_reference"]');
  var urlInput = form.querySelector('[name="url"]');
  var badge = form.querySelector('[data-nav-link-badge]');
  var badgeLabel = form.querySelector('[data-nav-link-type-label]');
  var metaInfo = form.querySelector('[data-nav-link-meta]');
  var warningInfo = form.querySelector('[data-nav-link-warning]');

  var clearButtons = [].slice.call(form.querySelectorAll('[data-nav-clear-source]'));
  clearButtons.forEach(function (btn) {
    btn.addEventListener('click', function (event) {
      event.preventDefault();
      if (typeInput && typeof typeInput.value !== 'undefined') {
        typeInput.value = 'custom';
      }
      if (referenceInput && typeof referenceInput.value !== 'undefined') {
        referenceInput.value = '';
      }
      if (badge && badge.classList) {
        badge.classList.add('d-none');
      }
      if (badgeLabel && typeof badgeLabel.textContent !== 'undefined') {
        badgeLabel.textContent = '';
      }
      if (metaInfo && metaInfo.classList) {
        metaInfo.classList.add('d-none');
        metaInfo.textContent = '';
      }
      if (warningInfo && warningInfo.classList) {
        warningInfo.classList.add('d-none');
        warningInfo.textContent = '';
      }
      btn.textContent = 'Přepnout na vlastní URL';
    });
  });

  if (urlInput) {
    urlInput.addEventListener('input', function () {
      if (typeInput && typeof typeInput.value !== 'undefined') {
        typeInput.value = 'custom';
      }
      if (referenceInput && typeof referenceInput.value !== 'undefined') {
        referenceInput.value = '';
      }
      if (badge && badge.classList) {
        badge.classList.add('d-none');
      }
      if (badgeLabel && typeof badgeLabel.textContent !== 'undefined') {
        badgeLabel.textContent = '';
      }
      if (metaInfo && metaInfo.classList) {
        metaInfo.classList.add('d-none');
        metaInfo.textContent = '';
      }
      if (warningInfo && warningInfo.classList) {
        warningInfo.classList.add('d-none');
        warningInfo.textContent = '';
      }
      clearButtons.forEach(function (btn) {
        btn.textContent = 'Přepnout na vlastní URL';
      });
    });
  }
}

export function createNavigationManager(container) {
  if (!container) {
    return null;
  }

  var stateScript = container.querySelector('[data-navigation-state]');
  var pendingSubmissions = new Map();
  var destroyed = false;

  var elements = {
    script: stateScript,
    menuList: container.querySelector('[data-navigation-menu-list]'),
    menuEmpty: container.querySelector('[data-navigation-menu-empty]'),
    editCard: container.querySelector('[data-navigation-edit-card]'),
    editForm: container.querySelector('[data-navigation-edit-menu-form]'),
    deleteForm: container.querySelector('[data-navigation-delete-form]'),
    createForm: container.querySelector('[data-navigation-create-menu-form]'),
    createLocationHelp: container.querySelector('[data-navigation-create-location-help]'),
    selectedHeader: container.querySelector('[data-navigation-selected-menu-header]'),
    selectedTitle: container.querySelector('[data-navigation-selected-menu-title]'),
    cancelLink: container.querySelector('[data-navigation-cancel-link]'),
    resetLink: container.querySelector('[data-navigation-reset-link]'),
    itemCard: container.querySelector('[data-navigation-item-card]'),
    itemCardTitle: container.querySelector('[data-navigation-item-card-title]'),
    itemForm: container.querySelector('[data-navigation-item-form]'),
    itemsCard: container.querySelector('[data-navigation-items-card]'),
    itemsBody: container.querySelector('[data-navigation-items-body]'),
    itemsEmpty: container.querySelector('[data-navigation-items-empty]'),
    emptyStateCard: container.querySelector('[data-navigation-empty-state]')
  };

  var targetIcons = {
    '_self': { icon: 'bi-arrow-return-right', title: 'Otevřít ve stejném okně' },
    '_blank': { icon: 'bi-box-arrow-up-right', title: 'Otevřít v novém okně' }
  };

  var state = sanitizeNavigationState(readStateFromScript(stateScript), null);

  function readStateFromScript(scriptEl) {
    if (!scriptEl) {
      return {};
    }
    var text = scriptEl.textContent || '';
    if (!text.trim()) {
      return {};
    }
    try {
      return JSON.parse(text);
    } catch (err) {
      console.error('navigation state parse failed', err);
      return {};
    }
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function cloneState(source) {
    return JSON.parse(JSON.stringify(source || {}));
  }

  function normalizeMenu(menu) {
    if (!menu || typeof menu !== 'object') {
      return null;
    }
    return {
      id: String(menu.id !== undefined ? menu.id : ''),
      name: String(menu.name !== undefined ? menu.name : ''),
      slug: String(menu.slug !== undefined ? menu.slug : ''),
      location: String(menu.location !== undefined ? menu.location : ''),
      description: String(menu.description !== undefined ? menu.description : '')
    };
  }

  function normalizeMenuLocations(locations) {
    var normalized = Object.create(null);
    if (!locations || typeof locations !== 'object') {
      return normalized;
    }
    Object.keys(locations).forEach(function (key) {
      var info = locations[key];
      if (!info || typeof info !== 'object') {
        return;
      }
      var value = String(info.value !== undefined ? info.value : key);
      normalized[value] = {
        value: value,
        label: String(info.label !== undefined ? info.label : value),
        description: info.description !== undefined && info.description !== null
          ? String(info.description)
          : null,
        assigned_menu_id: info.assigned_menu_id !== undefined && info.assigned_menu_id !== null
          ? String(info.assigned_menu_id)
          : null,
        assigned_menu_name: info.assigned_menu_name !== undefined && info.assigned_menu_name !== null
          ? String(info.assigned_menu_name)
          : null
      };
    });
    return normalized;
  }

  function normalizeItem(item) {
    if (!item || typeof item !== 'object') {
      return null;
    }
    var depthValue = Number(item.depth);
    if (!isFinite(depthValue) || depthValue < 0) {
      depthValue = 0;
    }
    return {
      id: String(item.id !== undefined ? item.id : ''),
      menu_id: String(item.menu_id !== undefined ? item.menu_id : ''),
      parent_id: item.parent_id !== undefined && item.parent_id !== null ? String(item.parent_id) : null,
      title: String(item.title !== undefined ? item.title : ''),
      url: String(item.url !== undefined ? item.url : ''),
      target: String(item.target !== undefined ? item.target : '_self'),
      sort_order: Number(item.sort_order !== undefined ? item.sort_order : 0) || 0,
      depth: depthValue,
      link_type: String(item.link_type !== undefined ? item.link_type : 'custom'),
      link_reference: item.link_reference !== undefined && item.link_reference !== null
        ? String(item.link_reference)
        : '',
      link_meta: item.link_meta && typeof item.link_meta === 'object' ? item.link_meta : {},
      link_reason: item.link_reason !== undefined && item.link_reason !== null ? String(item.link_reason) : '',
      css_class: item.css_class !== undefined && item.css_class !== null ? String(item.css_class) : ''
    };
  }

  function normalizeItems(items) {
    if (!Array.isArray(items)) {
      return [];
    }
    return items.map(normalizeItem).filter(Boolean);
  }

  function normalizeParentOptions(options, editingId) {
    var opts = [];
    if (!Array.isArray(options) || !options.length) {
      opts.push({ value: '0', label: '— Bez rodiče —', disabled: false });
      return opts;
    }
    options.forEach(function (opt) {
      if (!opt || typeof opt !== 'object') {
        return;
      }
      var value = String(opt.value !== undefined ? opt.value : '');
      if (value === '') {
        return;
      }
      var disabled = !!opt.disabled;
      if (editingId && value === editingId) {
        disabled = true;
      }
      opts.push({
        value: value,
        label: String(opt.label !== undefined ? opt.label : value),
        disabled: disabled
      });
    });
    if (!opts.length) {
      opts.push({ value: '0', label: '— Bez rodiče —', disabled: false });
    }
    return opts;
  }

  function normalizeTargets(targets) {
    if (!Array.isArray(targets)) {
      return [];
    }
    return targets.map(function (target) {
      if (!target || typeof target !== 'object') {
        return null;
      }
      var value = String(target.value !== undefined ? target.value : '');
      if (!value) {
        return null;
      }
      return {
        value: value,
        label: String(target.label !== undefined ? target.label : value)
      };
    }).filter(Boolean);
  }

  function sanitizeNavigationState(data, prev) {
    var previous = prev || null;
    var next = {
      tablesReady: !!(data && data.tablesReady),
      menus: [],
      menu: null,
      menuId: '0',
      menuLocations: Object.create(null),
      menuLocationValue: null,
      items: [],
      itemsTree: Array.isArray(data && data.itemsTree) ? data.itemsTree : [],
      editingItem: null,
      parentOptions: [],
      targets: [],
      linkTypeLabels: data && data.linkTypeLabels && typeof data.linkTypeLabels === 'object'
        ? data.linkTypeLabels
        : (previous ? previous.linkTypeLabels : {}),
      linkStatusMessages: data && data.linkStatusMessages && typeof data.linkStatusMessages === 'object'
        ? data.linkStatusMessages
        : (previous ? previous.linkStatusMessages : {}),
      csrf: data && typeof data.csrf === 'string'
        ? data.csrf
        : (previous && typeof previous.csrf === 'string' ? previous.csrf : ''),
      hasQuickAdd: data && typeof data.hasQuickAdd === 'boolean'
        ? data.hasQuickAdd
        : (previous && typeof previous.hasQuickAdd === 'boolean' ? previous.hasQuickAdd : false)
    };

    if (data && Array.isArray(data.menus)) {
      next.menus = data.menus.map(normalizeMenu).filter(Boolean);
    }

    if (data && data.menu) {
      next.menu = normalizeMenu(data.menu);
    }

    next.menuLocations = normalizeMenuLocations(data && data.menuLocations ? data.menuLocations : (previous ? previous.menuLocations : {}));

    if (next.menu) {
      next.menuId = next.menu.id;
      if (next.menu.location) {
        next.menuLocationValue = next.menu.location;
      }
    } else if (data && data.menuId !== undefined && data.menuId !== null) {
      next.menuId = String(data.menuId);
    } else if (previous && previous.menuId) {
      next.menuId = String(previous.menuId);
    }

    if (!next.menu && next.menus.length) {
      var matched = next.menus.find(function (menu) { return menu.id === next.menuId; });
      if (matched) {
        next.menu = matched;
        if (matched.location) {
          next.menuLocationValue = matched.location;
        }
      }
    }

    if (data && data.menuLocationValue !== undefined && data.menuLocationValue !== null) {
      next.menuLocationValue = String(data.menuLocationValue);
    } else if (!next.menuLocationValue && previous && previous.menuLocationValue) {
      next.menuLocationValue = String(previous.menuLocationValue);
    }

    next.items = normalizeItems(data && data.items ? data.items : (previous ? previous.items : []));

    if (data && data.editingItem) {
      next.editingItem = normalizeItem(data.editingItem);
    } else if (previous && previous.editingItem) {
      next.editingItem = normalizeItem(previous.editingItem);
    }

    next.parentOptions = normalizeParentOptions(data && data.parentOptions ? data.parentOptions : (previous ? previous.parentOptions : []), next.editingItem ? next.editingItem.id : null);
    next.targets = normalizeTargets(data && data.targets ? data.targets : (previous ? previous.targets : []));

    return next;
  }

  function updateScriptState() {
    if (!elements.script) {
      return;
    }
    try {
      elements.script.textContent = JSON.stringify(state, null, 2);
    } catch (err) {
      /* ignore serialization issues */
    }
  }

  function renderMenuList() {
    if (!elements.menuList) {
      return;
    }
    var html = '';
    state.menus.forEach(function (menu) {
      var active = menu.id === state.menuId ? ' active' : '';
      var href = 'admin.php?r=navigation&menu_id=' + encodeURIComponent(menu.id);
      html += '<a class="list-group-item list-group-item-action' + active + '" href="' + escapeHtml(href) + '" data-navigation-menu-link data-navigation-menu-id="' + escapeHtml(menu.id) + '">';
      html += '<div class="fw-semibold">' + escapeHtml(menu.name) + '</div>';
      html += '<div class="small text-secondary">' + escapeHtml(menu.slug) + ' · ' + escapeHtml(menu.location) + '</div>';
      html += '</a>';
    });
    elements.menuList.innerHTML = html;
    if (elements.menuEmpty) {
      if (state.menus.length) {
        elements.menuEmpty.classList.add('d-none');
      } else {
        elements.menuEmpty.classList.remove('d-none');
      }
    }
  }

  function applyMenuLocationsToSelect(selectEl, selectedValue, currentMenuId) {
    if (!selectEl) {
      return;
    }
    if (selectEl.tagName && selectEl.tagName.toLowerCase() !== 'select') {
      selectEl.value = selectedValue || '';
      return;
    }
    var optionsHtml = '';
    Object.keys(state.menuLocations).forEach(function (key) {
      var info = state.menuLocations[key];
      var isSelected = selectedValue && key === selectedValue;
      var assignedId = info.assigned_menu_id;
      var isDisabled = assignedId && assignedId !== currentMenuId;
      var titleAttr = info.description ? ' title="' + escapeHtml(info.description) + '"' : '';
      var disabledAttr = isDisabled ? ' disabled' : '';
      var selectedAttr = isSelected ? ' selected' : '';
      var suffix = '';
      if (assignedId && assignedId !== currentMenuId && info.assigned_menu_name) {
        suffix = ' — obsazeno (' + escapeHtml(info.assigned_menu_name) + ')';
      }
      optionsHtml += '<option value="' + escapeHtml(info.value) + '"' + selectedAttr + disabledAttr + titleAttr + '>' + escapeHtml(info.label) + suffix + '</option>';
    });
    selectEl.innerHTML = optionsHtml;
  }

  function updateEditForm() {
    if (!elements.editCard) {
      return;
    }
    if (state.menu) {
      elements.editCard.classList.remove('d-none');
    } else {
      elements.editCard.classList.add('d-none');
    }
    if (!elements.editForm) {
      return;
    }
    var idInput = elements.editForm.querySelector('[data-navigation-menu-id-input]');
    if (idInput) {
      idInput.value = state.menu ? state.menu.id : '';
    }
    var nameInput = elements.editForm.querySelector('[data-navigation-edit-name]');
    if (nameInput) {
      nameInput.value = state.menu ? state.menu.name : '';
    }
    var slugInput = elements.editForm.querySelector('[data-navigation-edit-slug]');
    if (slugInput) {
      slugInput.value = state.menu ? state.menu.slug : '';
    }
    var descriptionInput = elements.editForm.querySelector('[data-navigation-edit-description]');
    if (descriptionInput) {
      descriptionInput.value = state.menu ? state.menu.description : '';
    }
    var locationInput = elements.editForm.querySelector('[data-navigation-edit-location]');
    applyMenuLocationsToSelect(locationInput, state.menuLocationValue, state.menu ? state.menu.id : null);
  }

  function computeCreateLocationState() {
    var options = Object.keys(state.menuLocations).map(function (key) { return state.menuLocations[key]; });
    var defaultValue = null;
    var hasAvailable = false;
    for (var i = 0; i < options.length; i += 1) {
      var info = options[i];
      if (!info.assigned_menu_id) {
        defaultValue = info.value;
        hasAvailable = true;
        break;
      }
    }
    if (!hasAvailable && options.length) {
      defaultValue = options[0].value;
    }
    return { options: options, hasAvailable: hasAvailable, defaultValue: defaultValue };
  }

  function updateCreateForm() {
    if (!elements.createForm) {
      return;
    }
    var csrfInput = elements.createForm.querySelector('[data-navigation-csrf]');
    if (csrfInput) {
      csrfInput.value = state.csrf;
    }
    var locationInput = elements.createForm.querySelector('[data-navigation-create-location]');
    if (locationInput && locationInput.tagName && locationInput.tagName.toLowerCase() === 'select') {
      var info = computeCreateLocationState();
      var optionsHtml = '';
      info.options.forEach(function (opt) {
        var isSelected = info.hasAvailable ? (info.defaultValue === opt.value && !opt.assigned_menu_id) : (info.defaultValue === opt.value);
        var disabled = !!opt.assigned_menu_id;
        var titleAttr = opt.description ? ' title="' + escapeHtml(opt.description) + '"' : '';
        var suffix = '';
        if (opt.assigned_menu_id && opt.assigned_menu_name) {
          suffix = ' — obsazeno (' + escapeHtml(opt.assigned_menu_name) + ')';
        }
        optionsHtml += '<option value="' + escapeHtml(opt.value) + '"' + (isSelected ? ' selected' : '') + (disabled ? ' disabled' : '') + titleAttr + '>' + escapeHtml(opt.label) + suffix + '</option>';
      });
      locationInput.innerHTML = optionsHtml;
      locationInput.disabled = !info.hasAvailable;
      if (elements.createLocationHelp) {
        if (info.hasAvailable) {
          elements.createLocationHelp.innerHTML = 'Umístění jsou načtena ze šablony. Každé lze použít pouze jednou.';
        } else {
          elements.createLocationHelp.innerHTML = 'Umístění jsou načtena ze šablony. Každé lze použít pouze jednou. <span class="text-danger">Všechna umístění jsou již obsazena.</span>';
        }
      }
    }
  }

  function updateSelectedHeader() {
    if (elements.selectedHeader) {
      if (state.menu) {
        elements.selectedHeader.classList.remove('d-none');
      } else {
        elements.selectedHeader.classList.add('d-none');
      }
    }
    var title = 'Položky menu';
    if (state.menu) {
      title = 'Položky menu „' + state.menu.name + '“';
    }
    if (elements.selectedTitle) {
      elements.selectedTitle.textContent = title;
    }
    var href = state.menu ? 'admin.php?r=navigation&menu_id=' + encodeURIComponent(state.menu.id) : '#';
    if (elements.cancelLink) {
      elements.cancelLink.setAttribute('href', href);
    }
    if (elements.resetLink) {
      if (state.editingItem) {
        elements.resetLink.classList.remove('d-none');
      } else {
        elements.resetLink.classList.add('d-none');
      }
      elements.resetLink.setAttribute('href', href);
    }
  }

  function buildParentOptionsFromItems(editingId) {
    var options = [{ value: '0', label: '— Bez rodiče —', disabled: false }];
    state.items.forEach(function (item) {
      options.push({
        value: item.id,
        label: Array(item.depth + 1).join('— ') + item.title,
        disabled: editingId ? item.id === editingId : false
      });
    });
    return options;
  }

  function computeDefaultSortOrder() {
    if (state.editingItem) {
      return state.editingItem.sort_order;
    }
    var max = 0;
    state.items.forEach(function (item) {
      if (item.sort_order > max) {
        max = item.sort_order;
      }
    });
    return max + 1;
  }

  function describeLinkMeta(item) {
    if (!item) {
      return '';
    }
    var meta = item.link_meta || {};
    if (meta.slug) {
      return 'Slug: ' + meta.slug;
    }
    if (meta.route) {
      return 'Klíč: ' + meta.route;
    }
    if (item.link_reference && item.link_type !== 'custom') {
      return 'Reference: ' + item.link_reference;
    }
    return '';
  }

  function updateItemForm() {
    if (!elements.itemCard) {
      return;
    }
    if (state.menu) {
      elements.itemCard.classList.remove('d-none');
    } else {
      elements.itemCard.classList.add('d-none');
    }
    if (!elements.itemForm) {
      return;
    }
    var editing = state.editingItem;
    var action = editing ? 'update-item' : 'create-item';
    elements.itemForm.setAttribute('action', 'admin.php?r=navigation&a=' + action);
    elements.itemForm.setAttribute('data-navigation-action', action);

    var csrfInput = elements.itemForm.querySelector('[data-navigation-csrf]');
    if (csrfInput) {
      csrfInput.value = state.csrf;
    }

    var menuIdInput = elements.itemForm.querySelector('[data-navigation-field="menu_id"]');
    if (menuIdInput) {
      menuIdInput.value = state.menu ? state.menu.id : '';
    }

    var idInput = elements.itemForm.querySelector('[data-navigation-field="id"]');
    if (idInput) {
      idInput.value = editing ? editing.id : '';
    }

    var linkTypeInput = elements.itemForm.querySelector('[data-navigation-field="link_type"]');
    if (linkTypeInput) {
      linkTypeInput.value = editing ? editing.link_type : 'custom';
    }

    var linkReferenceInput = elements.itemForm.querySelector('[data-navigation-field="link_reference"]');
    if (linkReferenceInput) {
      linkReferenceInput.value = editing ? editing.link_reference : '';
    }

    var titleInput = elements.itemForm.querySelector('[data-navigation-field="title"]');
    if (titleInput) {
      titleInput.value = editing ? editing.title : '';
    }

    var urlInput = elements.itemForm.querySelector('[data-navigation-field="url"]');
    if (urlInput) {
      urlInput.value = editing ? editing.url : '';
    }

    var targetSelect = elements.itemForm.querySelector('[data-navigation-field="target"]');
    if (targetSelect) {
      var targetOptionsHtml = '';
      var currentTarget = editing ? editing.target : '_self';
      state.targets.forEach(function (opt) {
        var selectedAttr = opt.value === currentTarget ? ' selected' : '';
        targetOptionsHtml += '<option value="' + escapeHtml(opt.value) + '"' + selectedAttr + '>' + escapeHtml(opt.label) + '</option>';
      });
      targetSelect.innerHTML = targetOptionsHtml;
    }

    var parentSelect = elements.itemForm.querySelector('[data-navigation-parent-select]');
    if (parentSelect) {
      if (!state.parentOptions || !state.parentOptions.length) {
        state.parentOptions = buildParentOptionsFromItems(editing ? editing.id : null);
      }
      var parentOptionsHtml = '';
      var currentParent = editing && editing.parent_id ? editing.parent_id : '0';
      state.parentOptions.forEach(function (opt) {
        var selected = currentParent === String(opt.value) ? ' selected' : '';
        var disabled = opt.disabled ? ' disabled' : '';
        parentOptionsHtml += '<option value="' + escapeHtml(String(opt.value)) + '"' + disabled + selected + '>' + escapeHtml(opt.label) + '</option>';
      });
      parentSelect.innerHTML = parentOptionsHtml;
    }

    var sortInput = elements.itemForm.querySelector('[data-navigation-field="sort"]');
    if (sortInput) {
      sortInput.value = editing ? editing.sort_order : computeDefaultSortOrder();
    }

    var classInput = elements.itemForm.querySelector('[data-navigation-field="css_class"]');
    if (classInput) {
      classInput.value = editing ? editing.css_class : '';
    }

    var submitBtn = elements.itemForm.querySelector('[data-navigation-submit]');
    if (submitBtn) {
      submitBtn.textContent = editing ? 'Uložit položku' : 'Přidat položku';
      submitBtn.className = 'btn btn-' + (editing ? 'primary' : 'success');
    }

    if (elements.itemCardTitle) {
      elements.itemCardTitle.textContent = editing ? 'Upravit položku' : 'Přidat položku';
    }

    var badge = elements.itemForm.querySelector('[data-nav-link-badge]');
    var badgeLabel = elements.itemForm.querySelector('[data-nav-link-type-label]');
    var metaInfo = elements.itemForm.querySelector('[data-nav-link-meta]');
    var warningInfo = elements.itemForm.querySelector('[data-nav-link-warning]');
    var clearButtons = [].slice.call(elements.itemForm.querySelectorAll('[data-nav-clear-source]'));
    var typeLabel = editing ? (state.linkTypeLabels[editing.link_type] || editing.link_type) : '';
    if (badge) {
      if (editing && editing.link_type !== 'custom') {
        badge.classList.remove('d-none');
      } else {
        badge.classList.add('d-none');
      }
    }
    if (badgeLabel) {
      badgeLabel.textContent = typeLabel;
    }
    var metaText = describeLinkMeta(editing);
    if (metaInfo) {
      metaInfo.textContent = metaText;
      if (metaText === '') {
        metaInfo.classList.add('d-none');
      } else {
        metaInfo.classList.remove('d-none');
      }
    }
    if (warningInfo) {
      var warningText = '';
      if (editing && editing.link_reason) {
        warningText = state.linkStatusMessages[editing.link_reason] || 'Odkaz má problém a může vést na neplatnou stránku.';
      }
      warningInfo.textContent = warningText;
      if (warningText === '') {
        warningInfo.classList.add('d-none');
      } else {
        warningInfo.classList.remove('d-none');
      }
    }
    clearButtons.forEach(function (btn) {
      btn.textContent = editing && editing.link_type !== 'custom' ? 'Zrušit napojení' : 'Přepnout na vlastní URL';
    });
  }

  function renderItemsTable() {
    if (!elements.itemsCard) {
      return;
    }
    if (state.menu) {
      elements.itemsCard.classList.remove('d-none');
    } else {
      elements.itemsCard.classList.add('d-none');
    }
    if (!elements.itemsBody) {
      return;
    }
    if (!state.items.length) {
      elements.itemsBody.innerHTML = '<tr data-navigation-items-empty><td colspan="5" class="text-center text-secondary py-4">Toto menu zatím neobsahuje žádné položky.</td></tr>';
      return;
    }
    var rows = '';
    state.items.forEach(function (item) {
      var isEditing = state.editingItem && state.editingItem.id === item.id;
      var indent = Math.max(0, item.depth) * 16;
      var targetInfo = targetIcons[item.target] || { icon: 'bi-question-circle', title: item.target };
      var editHref = state.menu ? 'admin.php?r=navigation&menu_id=' + encodeURIComponent(state.menu.id) + '&item_id=' + encodeURIComponent(item.id) + '#item-form' : '#';
      var deleteAction = 'admin.php?r=navigation&a=delete-item';
      var linkLabel = state.linkTypeLabels[item.link_type] || item.link_type;
      var metaText = describeLinkMeta(item);
      var warningText = '';
      if (item.link_reason) {
        warningText = state.linkStatusMessages[item.link_reason] || 'Odkaz má problém a může vést na neplatnou stránku.';
      }
      rows += '<tr class="' + (isEditing ? 'table-active' : '') + '" data-navigation-item-row data-navigation-item-id="' + escapeHtml(item.id) + '">';
      rows += '<td><div style="padding-left: ' + indent + 'px">';
      rows += '<div class="fw-semibold" data-navigation-item-title>' + escapeHtml(item.title) + '</div>';
      rows += '<div class="small text-secondary">' + escapeHtml(linkLabel);
      if (metaText) {
        rows += ' · ' + escapeHtml(metaText);
      }
      rows += '</div>';
      if (warningText) {
        rows += '<div class="small text-danger"><i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>' + escapeHtml(warningText) + '</div>';
      }
      if (item.css_class) {
        rows += '<span class="badge text-bg-secondary mt-1" data-navigation-item-class>' + escapeHtml(item.css_class) + '</span>';
      }
      if (item.parent_id) {
        var parent = state.items.find(function (it) { return it.id === item.parent_id; });
        var parentTitle = parent ? parent.title : '—';
        rows += '<div class="small text-secondary">Rodič: ' + escapeHtml(parentTitle) + '</div>';
      }
      rows += '</div></td>';
      rows += '<td><div class="text-truncate" style="max-width:240px;" data-navigation-item-url>' + escapeHtml(item.url) + '</div></td>';
      rows += '<td class="text-center"><span class="admin-icon-indicator" data-bs-toggle="tooltip" data-bs-title="' + escapeHtml(targetInfo.title) + '"><i class="bi ' + escapeHtml(targetInfo.icon) + '" aria-hidden="true"></i><span class="visually-hidden">' + escapeHtml(targetInfo.title) + '</span></span></td>';
      rows += '<td data-navigation-item-sort>' + escapeHtml(String(item.sort_order)) + '</td>';
      rows += '<td class="text-end"><div class="d-inline-flex gap-2 align-items-center">';
      rows += '<a class="admin-icon-btn" href="' + escapeHtml(editHref) + '" aria-label="Upravit položku" data-bs-toggle="tooltip" data-bs-title="Upravit položku" data-navigation-edit-link><i class="bi bi-pencil" aria-hidden="true"></i></a>';
      rows += '<form method="post" action="' + escapeHtml(deleteAction) + '" class="d-inline" data-ajax data-confirm-modal="Opravdu odstranit tuto položku?" data-confirm-modal-title="Smazat položku" data-confirm-modal-confirm-label="Ano, smazat" data-confirm-modal-cancel-label="Ne" data-navigation-action="delete-item">';
      rows += '<input type="hidden" name="csrf" value="' + escapeHtml(state.csrf) + '" data-navigation-csrf>';
      rows += '<input type="hidden" name="menu_id" value="' + (state.menu ? escapeHtml(state.menu.id) : '') + '">';
      rows += '<input type="hidden" name="id" value="' + escapeHtml(item.id) + '">';
      rows += '<button class="admin-icon-btn" type="submit" aria-label="Smazat položku" data-bs-toggle="tooltip" data-bs-title="Smazat položku"><i class="bi bi-trash" aria-hidden="true"></i></button>';
      rows += '</form></div></td>';
      rows += '</tr>';
    });
    elements.itemsBody.innerHTML = rows;
  }

  function updateCsrfInputs() {
    var inputs = [].slice.call(container.querySelectorAll('[data-navigation-csrf]'));
    inputs.forEach(function (input) {
      input.value = state.csrf;
    });
  }

  function updateDeleteForm() {
    if (!elements.deleteForm) {
      return;
    }
    var idInput = elements.deleteForm.querySelector('[data-navigation-menu-id-input]');
    if (idInput) {
      idInput.value = state.menu ? state.menu.id : '';
    }
    var csrfInput = elements.deleteForm.querySelector('[data-navigation-csrf]');
    if (csrfInput) {
      csrfInput.value = state.csrf;
    }
  }

  function updateVisibility() {
    var hasMenu = !!state.menu;
    if (elements.emptyStateCard) {
      if (hasMenu) {
        elements.emptyStateCard.classList.add('d-none');
      } else {
        elements.emptyStateCard.classList.remove('d-none');
      }
    }
  }

  function renderAll() {
    if (destroyed) {
      return;
    }
    renderMenuList();
    updateEditForm();
    updateCreateForm();
    updateDeleteForm();
    updateSelectedHeader();
    updateItemForm();
    renderItemsTable();
    updateCsrfInputs();
    updateVisibility();
    updateScriptState();
    initTooltips(container);
    initConfirmModals(container);
    initNavigationQuickAdd(container);
  }

  function captureFormEntries(formData) {
    var entries = Object.create(null);
    if (!formData) {
      return entries;
    }
    formData.forEach(function (value, key) {
      if (!Object.prototype.hasOwnProperty.call(entries, key)) {
        entries[key] = [];
      }
      entries[key].push(value);
    });
    return entries;
  }

  function preserveFormSubmission(form, entries) {
    if (!(form instanceof HTMLFormElement)) {
      return;
    }
    var handler = function (event) {
      if (!event || !event.formData) {
        return;
      }
      Object.keys(entries).forEach(function (key) {
        var values = entries[key];
        if (!values || !values.length) {
          return;
        }
        if (typeof event.formData.delete === 'function') {
          event.formData.delete(key);
        }
        values.forEach(function (value) {
          event.formData.append(key, value);
        });
      });
      form.removeEventListener('formdata', handler);
    };
    form.addEventListener('formdata', handler);
  }

  function applyOptimisticUpdate(action, form, submitter) {
    var snapshot = cloneState(state);
    var formData = new FormData(form);
    var submissionEntries = captureFormEntries(formData);
    preserveFormSubmission(form, submissionEntries);

    function finalize(newState) {
      state = sanitizeNavigationState(newState, snapshot);
      renderAll();
    }

    switch (action) {
      case 'create-menu': {
        var name = String(formData.get('name') || '').trim();
        var slug = String(formData.get('slug') || '').trim();
        var location = String(formData.get('location') || '').trim();
        var description = String(formData.get('description') || '').trim();
        var tempId = '__pending_menu_' + Date.now();
        var newMenu = {
          id: tempId,
          name: name || 'Nové menu',
          slug: slug || (name || 'menu'),
          location: location || '',
          description: description
        };
        var next = cloneState(snapshot);
        next.menus = snapshot.menus ? snapshot.menus.slice() : [];
        next.menus.push(newMenu);
        next.menu = newMenu;
        next.menuId = newMenu.id;
        next.menuLocationValue = newMenu.location || null;
        next.items = [];
        next.itemsTree = [];
        next.editingItem = null;
        next.parentOptions = [{ value: '0', label: '— Bez rodiče —', disabled: false }];
        if (!next.targets || !next.targets.length) {
          next.targets = snapshot.targets || [];
        }
        if (next.menuLocationValue) {
          var loc = next.menuLocationValue;
          if (!next.menuLocations) {
            next.menuLocations = Object.create(null);
          }
          if (!next.menuLocations[loc]) {
            next.menuLocations[loc] = {
              value: loc,
              label: loc,
              description: null,
              assigned_menu_id: newMenu.id,
              assigned_menu_name: newMenu.name
            };
          } else {
            next.menuLocations[loc].assigned_menu_id = newMenu.id;
            next.menuLocations[loc].assigned_menu_name = newMenu.name;
          }
        }
        finalize(next);
        pendingSubmissions.set(form, { snapshot: snapshot });
        return;
      }
      case 'update-menu': {
        if (!snapshot.menu) {
          break;
        }
        var newName = String(formData.get('name') || '').trim();
        var newSlug = String(formData.get('slug') || '').trim();
        var newLocation = String(formData.get('location') || '').trim();
        var newDescription = String(formData.get('description') || '').trim();
        var updatedMenu = Object.assign({}, snapshot.menu, {
          name: newName || snapshot.menu.name,
          slug: newSlug || snapshot.menu.slug,
          location: newLocation || snapshot.menu.location,
          description: newDescription
        });
        var nextState = cloneState(snapshot);
        nextState.menu = updatedMenu;
        nextState.menuId = updatedMenu.id;
        nextState.menuLocationValue = updatedMenu.location || null;
        nextState.menus = snapshot.menus.map(function (menu) {
          if (menu.id === updatedMenu.id) {
            return Object.assign({}, menu, updatedMenu);
          }
          return menu;
        });
        if (snapshot.menuLocationValue && nextState.menuLocations && nextState.menuLocations[snapshot.menuLocationValue]) {
          var prevLoc = nextState.menuLocations[snapshot.menuLocationValue];
          if (prevLoc.assigned_menu_id === snapshot.menu.id) {
            prevLoc.assigned_menu_id = null;
            prevLoc.assigned_menu_name = null;
          }
        }
        if (updatedMenu.location) {
          if (!nextState.menuLocations) {
            nextState.menuLocations = Object.create(null);
          }
          if (!nextState.menuLocations[updatedMenu.location]) {
            nextState.menuLocations[updatedMenu.location] = {
              value: updatedMenu.location,
              label: updatedMenu.location,
              description: null,
              assigned_menu_id: updatedMenu.id,
              assigned_menu_name: updatedMenu.name
            };
          } else {
            nextState.menuLocations[updatedMenu.location].assigned_menu_id = updatedMenu.id;
            nextState.menuLocations[updatedMenu.location].assigned_menu_name = updatedMenu.name;
          }
        }
        finalize(nextState);
        pendingSubmissions.set(form, { snapshot: snapshot });
        return;
      }
      case 'delete-menu': {
        var nextMenus = snapshot.menus.filter(function (menu) {
          return menu.id !== snapshot.menuId;
        });
        var nextStateDelete = cloneState(snapshot);
        nextStateDelete.menus = nextMenus;
        nextStateDelete.menu = null;
        nextStateDelete.menuId = nextMenus.length ? nextMenus[0].id : '0';
        nextStateDelete.menuLocationValue = null;
        if (snapshot.menuLocationValue && nextStateDelete.menuLocations && nextStateDelete.menuLocations[snapshot.menuLocationValue]) {
          var removedLoc = nextStateDelete.menuLocations[snapshot.menuLocationValue];
          var currentMenuId = snapshot.menu ? snapshot.menu.id : snapshot.menuId;
          if (removedLoc.assigned_menu_id === snapshot.menuId || removedLoc.assigned_menu_id === currentMenuId) {
            removedLoc.assigned_menu_id = null;
            removedLoc.assigned_menu_name = null;
          }
        }
        nextStateDelete.items = [];
        nextStateDelete.itemsTree = [];
        nextStateDelete.editingItem = null;
        nextStateDelete.parentOptions = [{ value: '0', label: '— Bez rodiče —', disabled: false }];
        finalize(nextStateDelete);
        pendingSubmissions.set(form, { snapshot: snapshot });
        return;
      }
      case 'create-item': {
        if (!snapshot.menu) {
          break;
        }
        var itemTitle = String(formData.get('title') || '').trim();
        var itemUrl = String(formData.get('url') || '').trim();
        var itemTarget = String(formData.get('target') || '_self');
        var itemParent = String(formData.get('parent_id') || '0');
        var itemSort = Number(formData.get('sort_order') || 0) || 0;
        var itemClass = String(formData.get('css_class') || '').trim();
        var itemType = String(formData.get('link_type') || 'custom');
        var itemReference = String(formData.get('link_reference') || '');
        var tempItemId = '__pending_item_' + Date.now();
        var parentItem = snapshot.items.find(function (it) { return it.id === itemParent; });
        var depth = parentItem ? parentItem.depth + 1 : 0;
        var newItem = {
          id: tempItemId,
          menu_id: snapshot.menu.id,
          parent_id: itemParent !== '0' ? itemParent : null,
          title: itemTitle,
          url: itemUrl,
          target: itemTarget || '_self',
          sort_order: itemSort,
          depth: depth,
          link_type: itemType,
          link_reference: itemReference,
          link_meta: {},
          link_reason: '',
          css_class: itemClass
        };
        var nextStateItem = cloneState(snapshot);
        nextStateItem.items = snapshot.items.slice();
        nextStateItem.items.push(newItem);
        var editingItemId = snapshot.editingItem ? snapshot.editingItem.id : null;
        nextStateItem.editingItem = snapshot.editingItem || null;
        var originalItems = state.items;
        state.items = nextStateItem.items;
        nextStateItem.parentOptions = buildParentOptionsFromItems(editingItemId);
        state.items = originalItems;
        finalize(nextStateItem);
        pendingSubmissions.set(form, { snapshot: snapshot });
        return;
      }
      case 'update-item': {
        var editId = String(formData.get('id') || '');
        if (!editId) {
          break;
        }
        var existing = snapshot.items.find(function (item) { return item.id === editId; });
        if (!existing) {
          break;
        }
        var updTitle = String(formData.get('title') || '').trim();
        var updUrl = String(formData.get('url') || '').trim();
        var updTarget = String(formData.get('target') || '_self');
        var updParent = String(formData.get('parent_id') || '0');
        var updSort = Number(formData.get('sort_order') || 0) || 0;
        var updClass = String(formData.get('css_class') || '').trim();
        var updType = String(formData.get('link_type') || existing.link_type);
        var updReference = String(formData.get('link_reference') || existing.link_reference);
        var parentCandidate = snapshot.items.find(function (item) { return item.id === updParent; });
        var updatedItem = Object.assign({}, existing, {
          title: updTitle || existing.title,
          url: updUrl,
          target: updTarget || '_self',
          parent_id: updParent !== '0' ? updParent : null,
          sort_order: updSort,
          css_class: updClass,
          link_type: updType,
          link_reference: updReference,
          depth: parentCandidate ? parentCandidate.depth + 1 : 0,
          link_reason: ''
        });
        var nextStateUpdate = cloneState(snapshot);
        nextStateUpdate.items = snapshot.items.map(function (item) {
          if (item.id === editId) {
            return updatedItem;
          }
          return item;
        });
        nextStateUpdate.editingItem = updatedItem;
        nextStateUpdate.parentOptions = buildParentOptionsFromItems(updatedItem.id);
        finalize(nextStateUpdate);
        pendingSubmissions.set(form, { snapshot: snapshot });
        return;
      }
      case 'delete-item': {
        var deleteId = String(formData.get('id') || '');
        if (!deleteId) {
          break;
        }
        var nextStateDelItem = cloneState(snapshot);
        nextStateDelItem.items = snapshot.items.filter(function (item) { return item.id !== deleteId; });
        nextStateDelItem.editingItem = null;
        nextStateDelItem.parentOptions = buildParentOptionsFromItems(null);
        finalize(nextStateDelItem);
        pendingSubmissions.set(form, { snapshot: snapshot });
        return;
      }
      default:
        break;
    }
  }

  function handleSubmit(event) {
    if (event.defaultPrevented) {
      return;
    }
    var form = event.target;
    if (!(form instanceof HTMLFormElement)) {
      return;
    }
    if (!container.contains(form)) {
      return;
    }
    if (!form.hasAttribute('data-navigation-action')) {
      return;
    }
    if (form.hasAttribute('data-confirm-modal') && form.dataset.confirmModalHandled !== '1') {
      return;
    }
    applyOptimisticUpdate(form.getAttribute('data-navigation-action') || '', form, event.submitter || null);
  }

  function handleFormSuccess(event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement)) {
      return;
    }
    if (!container.contains(form)) {
      return;
    }
    if (!form.hasAttribute('data-navigation-action')) {
      return;
    }
    var detail = event.detail || {};
    var result = detail.result || null;
    var data = result && result.data ? result.data : null;
    pendingSubmissions.delete(form);
    if (!data || typeof data !== 'object') {
      renderAll();
      return;
    }
    state = sanitizeNavigationState(data, state);
    renderAll();
  }

  function handleFormError(event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement)) {
      return;
    }
    if (!container.contains(form)) {
      return;
    }
    var pending = pendingSubmissions.get(form);
    if (pending && pending.snapshot) {
      state = sanitizeNavigationState(pending.snapshot, pending.snapshot);
      renderAll();
    }
    pendingSubmissions.delete(form);
  }

  function handleNavigation(event) {
    if (!container.isConnected) {
      cleanup();
    }
  }

  function cleanup() {
    if (destroyed) {
      return;
    }
    destroyed = true;
    container.removeEventListener('submit', handleSubmit, true);
    container.removeEventListener('cms:admin:form:success', handleFormSuccess);
    container.removeEventListener('cms:admin:form:error', handleFormError);
    document.removeEventListener('cms:admin:navigated', handleNavigation);
    navigationManagers.delete(container);
    pendingSubmissions.clear();
  }

  container.addEventListener('submit', handleSubmit, true);
  container.addEventListener('cms:admin:form:success', handleFormSuccess);
  container.addEventListener('cms:admin:form:error', handleFormError);
  document.addEventListener('cms:admin:navigated', handleNavigation);

  renderAll();

  return {
    refresh: renderAll,
    dispose: cleanup
  };
}

export function initNavigationManager(root) {
  var scope = root || document;
  var containers = [].slice.call(scope.querySelectorAll('[data-navigation-manager]'));
  containers.forEach(function (container) {
    if (navigationManagers.has(container)) {
      var existing = navigationManagers.get(container);
      if (existing && typeof existing.refresh === 'function') {
        existing.refresh();
      }
      return;
    }
    var manager = createNavigationManager(container);
    if (manager) {
      navigationManagers.set(container, manager);
    }
  });
}


export function initNavigationUI(root) {
  initNavigationQuickAdd(root);
  initNavigationManager(root);
}
