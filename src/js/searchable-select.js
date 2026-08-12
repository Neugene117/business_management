(function () {
  'use strict';

  var instances = new WeakMap();
  var activeInstance = null;
  var sequence = 0;
  var repositionFrame = 0;
  var valueDescriptor = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
  var indexDescriptor = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'selectedIndex');

  function normalize(value) {
    var text = String(value || '').toLocaleLowerCase();
    return typeof text.normalize === 'function'
      ? text.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      : text;
  }

  function schedule(callback) {
    Promise.resolve().then(callback);
  }

  function fieldLabel(select) {
    if (select.getAttribute('aria-label')) return select.getAttribute('aria-label');
    if (select.id) {
      var explicit = document.querySelector('label[for="' + CSS.escape(select.id) + '"]');
      if (explicit) return explicit.textContent.replace(/\s+/g, ' ').trim();
    }
    var label = select.closest('label');
    if (label) {
      var clone = label.cloneNode(true);
      clone.querySelectorAll('select,input,textarea,button,small').forEach(function (node) { node.remove(); });
      var text = clone.textContent.replace(/\s+/g, ' ').trim();
      if (text) return text;
    }
    return select.name ? select.name.replace(/[\[\]_]+/g, ' ').trim() : 'field';
  }

  function hookProperty(select, property, descriptor, sync) {
    if (!descriptor || !descriptor.get || !descriptor.set) return;
    try {
      Object.defineProperty(select, property, {
        configurable: true,
        enumerable: descriptor.enumerable,
        get: function () { return descriptor.get.call(select); },
        set: function (value) {
          descriptor.set.call(select, value);
          schedule(sync);
        }
      });
    } catch (error) {}
  }

  function createSvg(path) {
    var wrapper = document.createElement('span');
    wrapper.innerHTML = '<svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="' + path + '"></path></svg>';
    return wrapper;
  }

  function enhance(select) {
    if (!(select instanceof HTMLSelectElement) || instances.has(select) || select.multiple || select.dataset.searchableSelect === 'off') return null;

    var originalParent = select.parentElement;
    var id = 'searchable-select-' + (++sequence);
    var label = fieldLabel(select);
    var root = document.createElement('div');
    root.className = 'searchable-select';
    if (originalParent && originalParent.tagName === 'FORM') root.classList.add('searchable-select--compact');

    var trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'searchable-select-trigger';
    trigger.setAttribute('role', 'combobox');
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', id + '-list');
    trigger.setAttribute('aria-label', label + ': open searchable options');

    var value = document.createElement('span');
    value.className = 'searchable-select-value';
    var chevron = createSvg('m5 7.5 5 5 5-5');
    chevron.className = 'searchable-select-chevron';
    trigger.append(value, chevron);

    var panel = document.createElement('div');
    panel.className = 'searchable-select-panel';
    panel.hidden = true;

    var searchWrap = document.createElement('div');
    searchWrap.className = 'searchable-select-search';
    var searchIcon = createSvg('M8.8 14.5a5.7 5.7 0 1 1 0-11.4 5.7 5.7 0 0 1 0 11.4Zm4-1.7 4.1 4.1');
    searchIcon.className = 'searchable-select-search-icon';
    var search = document.createElement('input');
    search.type = 'search';
    search.autocomplete = 'off';
    search.spellcheck = false;
    search.placeholder = 'Search options...';
    search.setAttribute('aria-label', 'Search ' + label);
    search.setAttribute('aria-controls', id + '-list');
    searchWrap.append(searchIcon, search);

    var list = document.createElement('div');
    list.className = 'searchable-select-list';
    list.id = id + '-list';
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', label + ' options');

    var empty = document.createElement('div');
    empty.className = 'searchable-select-empty';
    empty.textContent = 'No matching options';
    empty.hidden = true;
    panel.append(searchWrap, list, empty);

    select.classList.add('searchable-select-native');
    select.dataset.searchableEnhanced = 'true';
    select.tabIndex = -1;
    select.parentNode.insertBefore(root, select);
    root.append(select, trigger, panel);

    var state = {
      select: select,
      root: root,
      trigger: trigger,
      value: value,
      panel: panel,
      search: search,
      list: list,
      empty: empty,
      optionButtons: [],
      groupLabels: [],
      highlighted: -1,
      open: false
    };
    instances.set(select, state);

    state.sync = function () {
      var selected = select.options[select.selectedIndex] || null;
      value.textContent = selected ? selected.textContent.trim() : 'Select an option';
      root.classList.toggle('is-placeholder', !selected || selected.value === '');
      root.classList.toggle('is-disabled', select.disabled);
      root.classList.toggle('is-invalid', select.matches(':invalid') && select.dataset.validationAttempted === 'true');
      trigger.disabled = select.disabled;
      trigger.setAttribute('aria-disabled', select.disabled ? 'true' : 'false');
      trigger.setAttribute('aria-required', select.required ? 'true' : 'false');
      if (state.open) state.render();
    };

    state.render = function () {
      list.replaceChildren();
      state.optionButtons = [];
      state.groupLabels = [];
      var currentGroup = null;

      Array.from(select.options).forEach(function (option, optionIndex) {
        var group = option.parentElement && option.parentElement.tagName === 'OPTGROUP' ? option.parentElement : null;
        if (group !== currentGroup) {
          currentGroup = group;
          if (group) {
            var groupLabel = document.createElement('div');
            groupLabel.className = 'searchable-select-group';
            groupLabel.textContent = group.label;
            groupLabel.dataset.group = group.label;
            list.appendChild(groupLabel);
            state.groupLabels.push(groupLabel);
          }
        }

        if (option.hidden) return;
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'searchable-select-option';
        button.id = id + '-option-' + optionIndex;
        button.setAttribute('role', 'option');
        button.setAttribute('aria-selected', option.selected ? 'true' : 'false');
        button.dataset.index = String(optionIndex);
        button.dataset.searchText = normalize(option.textContent + ' ' + option.value);
        if (group) button.dataset.group = group.label;
        button.disabled = option.disabled || Boolean(group && group.disabled);

        var optionLabel = document.createElement('span');
        optionLabel.className = 'searchable-select-option-label';
        optionLabel.textContent = option.textContent.trim();
        var check = document.createElement('span');
        check.className = 'searchable-select-option-check';
        check.textContent = option.selected ? '✓' : '';
        button.append(optionLabel, check);
        button.addEventListener('click', function () { state.choose(optionIndex); });
        list.appendChild(button);
        state.optionButtons.push(button);
      });

      state.filter(search.value);
    };

    state.filter = function (query) {
      var needle = normalize(query.trim());
      var visible = 0;
      var visibleGroups = Object.create(null);
      state.optionButtons.forEach(function (button) {
        var match = !needle || button.dataset.searchText.indexOf(needle) !== -1;
        button.hidden = !match;
        button.classList.remove('is-highlighted');
        if (match) {
          visible++;
          if (button.dataset.group) visibleGroups[button.dataset.group] = true;
        }
      });
      state.groupLabels.forEach(function (group) { group.hidden = !visibleGroups[group.dataset.group]; });
      empty.hidden = visible !== 0;
      state.highlighted = -1;
      var selectedButton = state.optionButtons.find(function (button) { return !button.hidden && button.getAttribute('aria-selected') === 'true' && !button.disabled; });
      var firstButton = state.optionButtons.find(function (button) { return !button.hidden && !button.disabled; });
      state.highlight(selectedButton || firstButton || null, false);
      state.position();
    };

    state.visibleOptions = function () {
      return state.optionButtons.filter(function (button) { return !button.hidden && !button.disabled; });
    };

    state.highlight = function (button, scroll) {
      state.optionButtons.forEach(function (item) { item.classList.remove('is-highlighted'); });
      if (!button) {
        state.highlighted = -1;
        search.removeAttribute('aria-activedescendant');
        return;
      }
      var visible = state.visibleOptions();
      state.highlighted = visible.indexOf(button);
      button.classList.add('is-highlighted');
      search.setAttribute('aria-activedescendant', button.id);
      if (scroll) button.scrollIntoView({ block: 'nearest' });
    };

    state.move = function (direction) {
      var visible = state.visibleOptions();
      if (!visible.length) return;
      var next = state.highlighted + direction;
      if (next < 0) next = visible.length - 1;
      if (next >= visible.length) next = 0;
      state.highlight(visible[next], true);
    };

    state.choose = function (optionIndex) {
      var previous = select.value;
      select.selectedIndex = optionIndex;
      state.sync();
      state.close(true);
      if (select.value !== previous) {
        select.dispatchEvent(new Event('input', { bubbles: true }));
        select.dispatchEvent(new Event('change', { bubbles: true }));
      }
    };

    state.position = function () {
      if (!state.open || !root.isConnected) return;
      var rect = trigger.getBoundingClientRect();
      var viewportWidth = document.documentElement.clientWidth;
      var viewportHeight = document.documentElement.clientHeight;
      var width = Math.min(Math.max(rect.width, 230), viewportWidth - 16);
      panel.style.width = width + 'px';
      panel.style.maxWidth = (viewportWidth - 16) + 'px';
      var height = panel.offsetHeight;
      var spaceBelow = viewportHeight - rect.bottom - 8;
      var spaceAbove = rect.top - 8;
      var openAbove = spaceBelow < Math.min(height, 220) && spaceAbove > spaceBelow;
      var top = openAbove ? Math.max(8, rect.top - height - 6) : Math.min(viewportHeight - height - 8, rect.bottom + 6);
      var left = Math.max(8, Math.min(rect.left, viewportWidth - width - 8));
      panel.style.top = Math.max(8, top) + 'px';
      panel.style.left = left + 'px';
    };

    state.openPanel = function () {
      if (select.disabled || state.open) return;
      if (activeInstance && activeInstance !== state) activeInstance.close(false);
      state.open = true;
      activeInstance = state;
      root.classList.add('is-open');
      trigger.setAttribute('aria-expanded', 'true');
      search.value = '';
      state.render();
      document.body.appendChild(panel);
      panel.hidden = false;
      state.position();
      requestAnimationFrame(function () { search.focus(); });
    };

    state.close = function (returnFocus) {
      if (!state.open) return;
      state.open = false;
      root.classList.remove('is-open');
      trigger.setAttribute('aria-expanded', 'false');
      panel.hidden = true;
      if (root.isConnected) root.appendChild(panel); else panel.remove();
      if (activeInstance === state) activeInstance = null;
      if (returnFocus && root.isConnected) trigger.focus();
    };

    trigger.addEventListener('click', function () {
      if (state.open) state.close(false); else state.openPanel();
    });
    trigger.addEventListener('keydown', function (event) {
      if (['ArrowDown', 'ArrowUp', 'Enter', ' '].indexOf(event.key) !== -1) {
        event.preventDefault();
        state.openPanel();
      }
    });
    search.addEventListener('input', function () { state.filter(search.value); });
    search.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        state.move(event.key === 'ArrowDown' ? 1 : -1);
      } else if (event.key === 'Enter') {
        event.preventDefault();
        var visible = state.visibleOptions();
        if (state.highlighted >= 0 && visible[state.highlighted]) visible[state.highlighted].click();
      } else if (event.key === 'Escape') {
        event.preventDefault();
        state.close(true);
      } else if (event.key === 'Tab') {
        state.close(false);
      }
    });
    select.addEventListener('change', state.sync);
    select.addEventListener('input', state.sync);
    select.addEventListener('invalid', function (event) {
      select.dataset.validationAttempted = 'true';
      root.classList.add('is-invalid');
      event.preventDefault();
      state.openPanel();
    });

    hookProperty(select, 'value', valueDescriptor, state.sync);
    hookProperty(select, 'selectedIndex', indexDescriptor, state.sync);
    state.sync();
    return state;
  }

  function enhanceWithin(container) {
    if (!container) return;
    if (container instanceof HTMLSelectElement) enhance(container);
    if (container.querySelectorAll) container.querySelectorAll('select').forEach(enhance);
  }

  function refreshForMutation(node) {
    var select = node instanceof HTMLSelectElement ? node : (node.closest ? node.closest('select') : null);
    var state = select ? instances.get(select) : null;
    if (state) schedule(state.sync);
  }

  document.addEventListener('pointerdown', function (event) {
    if (activeInstance && !activeInstance.root.contains(event.target) && !activeInstance.panel.contains(event.target)) activeInstance.close(false);
  }, true);

  document.addEventListener('click', function (event) {
    var label = event.target.closest && event.target.closest('label');
    if (!label || event.target.closest('.searchable-select')) return;
    var select = label.querySelector('select.searchable-select-native');
    if (!select && label.htmlFor) select = document.getElementById(label.htmlFor);
    var state = select ? instances.get(select) : null;
    if (state) {
      event.preventDefault();
      state.openPanel();
    }
  });

  document.addEventListener('reset', function (event) {
    schedule(function () {
      event.target.querySelectorAll('select').forEach(function (select) {
        var state = instances.get(select);
        if (state) state.sync(); else enhance(select);
      });
    });
  }, true);

  function requestReposition() {
    if (!activeInstance || repositionFrame) return;
    repositionFrame = requestAnimationFrame(function () {
      repositionFrame = 0;
      if (activeInstance) activeInstance.position();
    });
  }
  window.addEventListener('resize', requestReposition);
  document.addEventListener('scroll', requestReposition, true);

  var observer = new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
      if (mutation.type === 'childList') {
        refreshForMutation(mutation.target);
        mutation.addedNodes.forEach(enhanceWithin);
        if (activeInstance && !activeInstance.root.isConnected) activeInstance.close(false);
      } else {
        refreshForMutation(mutation.target);
      }
    });
  });

  enhanceWithin(document);
  observer.observe(document.documentElement, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['disabled', 'required', 'selected', 'value', 'label', 'hidden']
  });

  window.SearchableSelect = {
    enhance: enhance,
    enhanceWithin: enhanceWithin,
    sync: function (select) {
      var state = instances.get(select);
      if (state) state.sync(); else enhance(select);
    },
    syncAll: function () { document.querySelectorAll('select').forEach(function (select) {
      var state = instances.get(select);
      if (state) state.sync(); else enhance(select);
    }); }
  };
})();
