(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  function escapeHtml(value) {
    return String(value || '').replace(/[&<>'"]/g, function (c) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c];
    });
  }

  ready(function () {
    var cfg = window.BfCamelCRM04 || {};
    var strings = cfg.strings || {};
    var params = new URLSearchParams(window.location.search);
    var page = params.get('page') || '';

    function parseSchema() {
      var hidden = document.getElementById('bfcamel-crm-schema-json');
      if (!hidden || !hidden.value) return [];
      try { var data = JSON.parse(hidden.value); return Array.isArray(data) ? data : []; }
      catch (e) { return []; }
    }

    /* Form builder: extend the existing 0.3.x builder without breaking its
       internal schema model. CSS classes are merged into schema_json at submit. */
    var builder = document.getElementById('bfcamel-crm-builder');
    var editor = document.getElementById('bfcamel-crm-form-editor');
    var schemaHidden = document.getElementById('bfcamel-crm-schema-json');
    var cssByName = {};
    if (window.BfCamelCRMBuilder && Array.isArray(window.BfCamelCRMBuilder.schema)) {
      window.BfCamelCRMBuilder.schema.forEach(function (field) {
        if (field && field.name) cssByName[field.name] = field.css_class || '';
      });
    }

    function enhanceBuilder() {
      if (!builder) return;
      var schema = parseSchema();
      builder.querySelectorAll('.bfcamel-crm-builder-field').forEach(function (card, index) {
        var field = schema[index] || {};
        var widthSelect = Array.from(card.querySelectorAll('select')).find(function (select) {
          var values = Array.from(select.options).map(function (o) { return o.value; });
          return values.indexOf('100') !== -1 && values.indexOf('50') !== -1 && values.indexOf('33') !== -1;
        });
        if (widthSelect && !widthSelect.querySelector('option[value="25"]')) {
          var option = document.createElement('option'); option.value = '25'; option.textContent = '25%';
          widthSelect.appendChild(option);
          if (String(field.width) === '25') widthSelect.value = '25';
        }

        if (!card.querySelector('.bfcamel-crm-css-class-control')) {
          var grid = card.querySelector('.bfcamel-crm-builder-grid');
          if (!grid) return;
          var label = document.createElement('label'); label.className = 'bfcamel-crm-builder-control bfcamel-crm-css-class-control';
          var span = document.createElement('span'); span.textContent = strings.fieldCssClass || 'Field CSS class';
          var input = document.createElement('input'); input.type = 'text'; input.placeholder = 'my-custom-field';
          var name = field.name || '';
          input.value = Object.prototype.hasOwnProperty.call(cssByName, name) ? cssByName[name] : (field.css_class || '');
          input.addEventListener('input', function () {
            var current = parseSchema()[index] || {};
            var currentName = current.name || name;
            cssByName[currentName] = input.value.replace(/[^a-zA-Z0-9_\- ]/g, ' ').replace(/\s+/g, ' ').trim();
          });
          label.appendChild(span); label.appendChild(input); grid.appendChild(label);
        }
      });
    }

    if (builder) {
      new MutationObserver(function () { window.requestAnimationFrame(enhanceBuilder); }).observe(builder, {childList:true, subtree:true});
      window.setTimeout(enhanceBuilder, 0);
    }

    if (editor) {
      var aside = editor.querySelector('.bfcamel-crm-sticky');
      if (aside && !aside.querySelector('.bfcamel-crm-workflow-defaults')) {
        var box = document.createElement('div'); box.className = 'bfcamel-crm-workflow-defaults';
        var status = document.createElement('label'); status.textContent = strings.defaultStatus || 'Default status';
        var statusSelect = document.createElement('select'); statusSelect.name = 'settings[default_status]';
        Object.keys(cfg.statuses || {}).forEach(function (slug) { var o=document.createElement('option');o.value=slug;o.textContent=cfg.statuses[slug];if(String((cfg.formSettings||{}).default_status)===slug)o.selected=true;statusSelect.appendChild(o); });
        status.appendChild(statusSelect);
        var priority = document.createElement('label'); priority.textContent = strings.defaultPriority || 'Default priority';
        var prioritySelect = document.createElement('select'); prioritySelect.name = 'settings[default_priority]';
        Object.keys(cfg.priorities || {}).forEach(function (slug) { var o=document.createElement('option');o.value=slug;o.textContent=cfg.priorities[slug];if(String((cfg.formSettings||{}).default_priority)===slug)o.selected=true;prioritySelect.appendChild(o); });
        priority.appendChild(prioritySelect); box.appendChild(status); box.appendChild(priority);
        var firstHr = aside.querySelector('hr'); aside.insertBefore(box, firstHr || aside.firstChild);
      }
      var styleSelect = editor.querySelector('select[name="settings[style_mode]"]');
      if (styleSelect) {
        var themeOption = styleSelect.querySelector('option[value="theme"]');
        if (themeOption) themeOption.textContent = strings.styleModeTheme || 'Theme / unstyled';
      }
      editor.addEventListener('submit', function () {
        if (!schemaHidden) return;
        var schema = parseSchema();
        builder && builder.querySelectorAll('.bfcamel-crm-builder-field').forEach(function (card, index) {
          if (!schema[index]) return;
          var input = card.querySelector('.bfcamel-crm-css-class-control input');
          var name = schema[index].name || '';
          schema[index].css_class = input ? input.value : (cssByName[name] || schema[index].css_class || '');
        });
        schemaHidden.value = JSON.stringify(schema);
      });
    }

    /* Central tag picker. The old text input remains the submitted value for
       backwards compatibility, but users select only from the managed catalog. */
    function tagsForScope(scope) { return scope === 'contact' ? (cfg.contactTags || []) : (cfg.submissionTags || []); }
    function scopeForPage() { return page.indexOf('contacts') !== -1 ? 'contact' : 'submission'; }
    function makeTagPicker(input, scope) {
      if (!input || input.dataset.bfcamelPicker === '1') return;
      input.dataset.bfcamelPicker = '1'; input.type = 'hidden';
      var select = document.createElement('select'); select.multiple = true; select.className = 'bfcamel-crm-tag-picker';
      var current = String(input.value || '').split(',').map(function (v) { return v.trim(); }).filter(Boolean);
      tagsForScope(scope).forEach(function (tag) {
        var o = document.createElement('option'); o.value = tag.name; o.textContent = tag.name;
        if (current.indexOf(tag.name) !== -1) o.selected = true; select.appendChild(o);
      });
      select.setAttribute('aria-label', strings.tagPicker || 'Select tags');
      select.addEventListener('change', function () {
        input.value = Array.from(select.selectedOptions).map(function (o) { return o.value; }).join(', ');
      });
      input.parentNode.insertBefore(select, input.nextSibling);
    }
    document.querySelectorAll('input[name="tags"]').forEach(function (input) { makeTagPicker(input, scopeForPage()); });
    document.querySelectorAll('input[name="bulk_tags"]').forEach(function (input) { makeTagPicker(input, 'submission'); });
    document.querySelectorAll('input[name="bulk_value"]').forEach(function (input) {
      var form = input.closest('form'); var action = form && form.querySelector('select[name="bulk_action"]');
      if (action && (page.indexOf('contacts') !== -1)) makeTagPicker(input, 'contact');
    });

    /* Archive is the safe implementation of form deletion: historical
       submissions and immutable revisions stay intact. */
    if (page === 'bfcamel-crm-forms' && cfg.formsStatus) {
      document.querySelectorAll('table tbody tr').forEach(function (row) {
        var edit = row.querySelector('a[href*="page=bfcamel-crm-forms"][href*="id="]'); if (!edit) return;
        var url = new URL(edit.href, window.location.origin); var id = url.searchParams.get('id'); if (!id || !cfg.formsStatus[id]) return;
        var archived = cfg.formsStatus[id] === 'archived';
        if (archived) { var badge=document.createElement('span');badge.className='bfcamel-crm-archived-badge';badge.textContent=strings.archived||'Archived';edit.parentNode.appendChild(document.createTextNode(' '));edit.parentNode.appendChild(badge); }
        var action = document.createElement('a'); action.className = archived ? 'bfcamel-crm-form-row-action' : 'bfcamel-crm-form-row-action bfcamel-crm-form-row-action--delete';
        action.textContent = archived ? (strings.restoreForm || 'Restore form') : (strings.deleteForm || 'Delete form');
        var template = archived ? cfg.restoreFormUrl : cfg.archiveFormUrl;
        if (template) { action.href = template.replace('__ID__', encodeURIComponent(id)); edit.parentNode.appendChild(document.createTextNode(' · ')); edit.parentNode.appendChild(action); }
      });
    }
  });
})();
