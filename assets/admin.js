(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  function applyRuntimeTranslations() {
    var runtime = (window.BfCamelCRMI18n && window.BfCamelCRMI18n.runtime) || {};
    var statuses = runtime.statuses || {};
    var sync = runtime.sync || {};
    var consentTypes = runtime.consentTypes || {};
    var consentStatuses = runtime.consentStatuses || {};
    var stats = runtime.stats || {};

    document.querySelectorAll('.bfcamel-crm-stat span').forEach(function (node) {
      var raw = node.textContent.trim();
      if (stats[raw]) node.textContent = stats[raw];
    });

    document.querySelectorAll('.bfcamel-crm-badge').forEach(function (node) {
      var raw = node.textContent.trim();
      if (statuses[raw]) node.textContent = statuses[raw];
    });

    document.querySelectorAll('select[name="status"] option').forEach(function (option) {
      if (statuses[option.value]) option.textContent = statuses[option.value];
    });

    document.querySelectorAll('.bfcamel-crm-admin table td').forEach(function (node) {
      var raw = node.textContent.trim();
      if (statuses[raw]) node.textContent = statuses[raw];
    });

    document.querySelectorAll('.bfcamel-crm-admin p').forEach(function (node) {
      Array.prototype.forEach.call(node.childNodes, function (child) {
        if (child.nodeType !== 3) return;
        var raw = child.nodeValue.trim();
        if (sync[raw]) child.nodeValue = ' ' + sync[raw];
      });
    });

    document.querySelectorAll('.bfcamel-crm-timeline li').forEach(function (item) {
      var strong = item.querySelector('strong');
      if (strong) {
        var type = strong.textContent.trim();
        if (consentTypes[type]) strong.textContent = consentTypes[type];
      }
      Array.prototype.forEach.call(item.childNodes, function (child) {
        if (child.nodeType !== 3) return;
        var text = child.nodeValue;
        Object.keys(consentStatuses).forEach(function (status) {
          text = text.replace(status, consentStatuses[status]);
        });
        child.nodeValue = text;
      });
    });
  }

  ready(function () {
    applyRuntimeTranslations();

    var root = document.getElementById('bfcamel-crm-builder');
    var hidden = document.getElementById('bfcamel-crm-schema-json');
    if (!root || !hidden || typeof window.BfCamelCRMBuilder === 'undefined') return;

    var labels = window.BfCamelCRMBuilder.labels || {};
    var schema = Array.isArray(window.BfCamelCRMBuilder.schema)
      ? JSON.parse(JSON.stringify(window.BfCamelCRMBuilder.schema))
      : [];

    var types = window.BfCamelCRMBuilder.types || {};
    var mappings = [
      ['submission_only', labels.submissionOnly || 'Submission only'],
      ['contact.name', labels.contactName || 'Contact: name'],
      ['contact.email', labels.contactEmail || 'Contact: email'],
      ['contact.phone', labels.contactPhone || 'Contact: phone'],
      ['contact.organization', labels.contactOrganization || 'Contact: organization'],
      ['contact.custom', labels.contactCustom || 'Contact: custom field']
    ];

    var widths = [
      ['100', '100%'],
      ['50', '50%'],
      ['33', '33%']
    ];

    function fieldTemplate(type) {
      var n = 'field_' + Math.random().toString(36).slice(2, 9);
      var base = {
        name: n,
        type: type || 'text',
        label: '',
        required: false,
        width: '100',
        placeholder: '',
        mapping: 'submission_only',
        custom_key: '',
        options: []
      };

      if (type === 'consent_personal_data') {
        base.name = 'consent_personal_data_' + Math.random().toString(36).slice(2, 6);
        base.label = labels.personalDataDefault || 'I consent to {personal_data_consent} and confirm that I have read the {privacy_policy}.';
        base.required = true;
      } else if (type === 'consent_marketing') {
        base.name = 'consent_marketing_' + Math.random().toString(36).slice(2, 6);
        base.label = labels.marketingDefault || 'I consent to receive informational and marketing messages under the {marketing_consent}.';
      } else if (type === 'html') {
        base.name = 'content_' + Math.random().toString(36).slice(2, 7);
        base.label = '<h3>' + (labels.sectionTitle || 'Section title') + '</h3><p>' + (labels.sectionHelp || 'Add explanatory text here.') + '</p>';
      }
      return base;
    }

    function el(tag, className, text) {
      var node = document.createElement(tag);
      if (className) node.className = className;
      if (typeof text !== 'undefined') node.textContent = text;
      return node;
    }

    function inputRow(labelText, input) {
      var label = el('label', 'bfcamel-crm-builder-control');
      var span = el('span', '', labelText || '');
      label.appendChild(span);
      label.appendChild(input);
      return label;
    }

    function makeSelect(options, value) {
      var select = document.createElement('select');
      options.forEach(function (item) {
        var option = document.createElement('option');
        option.value = item[0];
        option.textContent = item[1];
        if (String(item[0]) === String(value)) option.selected = true;
        select.appendChild(option);
      });
      return select;
    }

    function sync() {
      hidden.value = JSON.stringify(schema);
    }

    function render() {
      root.innerHTML = '';
      schema.forEach(function (field, index) {
        var card = el('div', 'bfcamel-crm-builder-field');
        card.dataset.index = String(index);

        var head = el('div', 'bfcamel-crm-builder-field__head');
        var title = el('strong', '', (labels.field || 'Field') + ' ' + (index + 1) + ' · ' + (types[field.type] || field.type));
        var actions = el('div', 'bfcamel-crm-builder-actions');

        var up = el('button', 'button button-small', '↑');
        up.type = 'button';
        up.title = labels.moveUp || 'Move up';
        up.disabled = index === 0;
        up.addEventListener('click', function () {
          if (index < 1) return;
          var tmp = schema[index - 1];
          schema[index - 1] = schema[index];
          schema[index] = tmp;
          render();
        });

        var down = el('button', 'button button-small', '↓');
        down.type = 'button';
        down.title = labels.moveDown || 'Move down';
        down.disabled = index === schema.length - 1;
        down.addEventListener('click', function () {
          if (index >= schema.length - 1) return;
          var tmp = schema[index + 1];
          schema[index + 1] = schema[index];
          schema[index] = tmp;
          render();
        });

        var remove = el('button', 'button button-small button-link-delete', labels.remove || 'Remove');
        remove.type = 'button';
        remove.addEventListener('click', function () {
          schema.splice(index, 1);
          render();
        });

        actions.appendChild(up);
        actions.appendChild(down);
        actions.appendChild(remove);
        head.appendChild(title);
        head.appendChild(actions);
        card.appendChild(head);

        var grid = el('div', 'bfcamel-crm-builder-grid');

        var labelInput;
        if (field.type === 'html') {
          labelInput = document.createElement('textarea');
          labelInput.rows = 5;
        } else {
          labelInput = document.createElement('textarea');
          labelInput.rows = 2;
        }
        labelInput.value = field.label || '';
        labelInput.addEventListener('input', function () {
          field.label = labelInput.value;
          sync();
        });
        grid.appendChild(inputRow(labels.label || 'Label / text', labelInput));

        var keyInput = document.createElement('input');
        keyInput.type = 'text';
        keyInput.value = field.name || '';
        keyInput.required = field.type !== 'html';
        keyInput.addEventListener('input', function () {
          field.name = keyInput.value.toLowerCase().replace(/[^a-z0-9_\-]/g, '_');
          keyInput.value = field.name;
          sync();
        });
        grid.appendChild(inputRow(labels.key || 'Field key', keyInput));

        if (field.type !== 'html') {
          var placeholder = document.createElement('input');
          placeholder.type = 'text';
          placeholder.value = field.placeholder || '';
          placeholder.addEventListener('input', function () {
            field.placeholder = placeholder.value;
            sync();
          });
          grid.appendChild(inputRow(labels.placeholder || 'Placeholder / hidden value', placeholder));

          var width = makeSelect(widths, field.width || '100');
          width.addEventListener('change', function () {
            field.width = width.value;
            sync();
          });
          grid.appendChild(inputRow(labels.width || 'Width', width));

          var mapping = makeSelect(mappings, field.mapping || 'submission_only');
          mapping.addEventListener('change', function () {
            field.mapping = mapping.value;
            render();
          });
          grid.appendChild(inputRow(labels.mapping || 'CRM mapping', mapping));

          if (field.mapping === 'contact.custom') {
            var custom = document.createElement('input');
            custom.type = 'text';
            custom.value = field.custom_key || '';
            custom.placeholder = field.name || 'custom_field';
            custom.addEventListener('input', function () {
              field.custom_key = custom.value.toLowerCase().replace(/[^a-z0-9_\-]/g, '_');
              custom.value = field.custom_key;
              sync();
            });
            grid.appendChild(inputRow(labels.customKey || 'Custom field key', custom));
          }

          var requiredWrap = el('label', 'bfcamel-crm-builder-check');
          var required = document.createElement('input');
          required.type = 'checkbox';
          required.checked = !!field.required;
          required.addEventListener('change', function () {
            field.required = required.checked;
            sync();
          });
          requiredWrap.appendChild(required);
          requiredWrap.appendChild(document.createTextNode(' ' + (labels.required || 'Required')));
          grid.appendChild(requiredWrap);
        }

        if (['select', 'radio', 'checkbox'].indexOf(field.type) !== -1) {
          var options = document.createElement('textarea');
          options.rows = 5;
          options.value = Array.isArray(field.options) ? field.options.join('\n') : '';
          options.addEventListener('input', function () {
            field.options = options.value.split(/\r?\n/).map(function (v) { return v.trim(); }).filter(Boolean);
            sync();
          });
          grid.appendChild(inputRow(labels.options || 'Options, one per line', options));
        }

        card.appendChild(grid);
        root.appendChild(card);
      });
      sync();
    }

    document.querySelectorAll('.bfcamel-crm-add-field-button').forEach(function (button) {
      button.addEventListener('click', function () {
        schema.push(fieldTemplate(button.dataset.type || 'text'));
        render();
      });
    });

    var editor = document.getElementById('bfcamel-crm-form-editor');
    if (editor) {
      editor.addEventListener('submit', function () {
        sync();
      });
    }

    render();
  });
})();
