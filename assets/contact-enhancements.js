(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function () {
    var config = window.BfCamelCRMEnhancements || {};
    var labels = config.labels || {};
    var page = config.page || '';

    enhanceFormBuilder();
    enhanceContactDetail();
    enhanceContactList();
    enhanceDeletionUi();

    function enhanceFormBuilder() {
      if (page !== 'bfcamel-crm-forms') return;
      var root = document.getElementById('bfcamel-crm-builder');
      var hidden = document.getElementById('bfcamel-crm-schema-json');
      var editor = document.getElementById('bfcamel-crm-form-editor');
      if (!root || !hidden || !editor) return;

      var running = false;
      function decorate() {
        if (running) return;
        running = true;
        try {
          var schema = [];
          try { schema = JSON.parse(hidden.value || '[]'); } catch (e) { schema = []; }
          root.querySelectorAll('.bfcamel-crm-builder-field').forEach(function (card, index) {
            var field = schema[index] || {};
            var mappingSelect = null;
            card.querySelectorAll('select').forEach(function (select) {
              if (mappingSelect) return;
              if (Array.from(select.options).some(function (option) { return option.value === 'contact.custom'; })) {
                mappingSelect = select;
              }
            });
            if (!mappingSelect) return;

            addOption(mappingSelect, 'contact.city', labels.contactCity || 'Contact: city');
            addOption(mappingSelect, 'contact.social_page', labels.contactSocialPage || 'Contact: social page');

            var virtual = '';
            if (field.mapping === 'contact.city' || field.mapping === 'contact.social_page') {
              virtual = field.mapping;
            } else if (field.mapping === 'contact.custom' && field.custom_key === 'city') {
              virtual = 'contact.city';
            } else if (field.mapping === 'contact.custom' && field.custom_key === 'social_page') {
              virtual = 'contact.social_page';
            }
            if (virtual) mappingSelect.value = virtual;

            if (virtual) {
              card.querySelectorAll('.bfcamel-crm-builder-control').forEach(function (row) {
                var input = row.querySelector('input[type="text"]');
                if (!input) return;
                if (input.value === 'city' || input.value === 'social_page') {
                  row.style.display = 'none';
                }
              });
            }
          });
        } finally {
          running = false;
        }
      }

      function addOption(select, value, text) {
        if (Array.from(select.options).some(function (option) { return option.value === value; })) return;
        var option = document.createElement('option');
        option.value = value;
        option.textContent = text;
        select.appendChild(option);
      }

      var observer = new MutationObserver(function () {
        window.requestAnimationFrame(decorate);
      });
      observer.observe(root, { childList: true, subtree: true });
      decorate();

      editor.addEventListener('submit', function () {
        var schema = [];
        try { schema = JSON.parse(hidden.value || '[]'); } catch (e) { schema = []; }
        schema.forEach(function (field) {
          if (field.mapping === 'contact.city') {
            field.mapping = 'contact.custom';
            field.custom_key = 'city';
          } else if (field.mapping === 'contact.social_page') {
            field.mapping = 'contact.custom';
            field.custom_key = 'social_page';
          }
        });
        hidden.value = JSON.stringify(schema);
      });
    }

    function enhanceContactDetail() {
      if (page !== 'bfcamel-crm-contacts' || !config.contactDetail || !config.contactDetail.id) return;
      var main = document.querySelector('.bfcamel-crm-editor-grid > main');
      if (!main) return;

      var detail = config.contactDetail;
      var panel = document.createElement('div');
      panel.className = 'bfcamel-crm-panel bfcamel-crm-contact-summary';
      var h2 = document.createElement('h2');
      h2.textContent = labels.additionalData || 'Additional contact data';
      panel.appendChild(h2);

      var dl = document.createElement('dl');
      dl.className = 'bfcamel-crm-dl';
      addDl(dl, labels.city || 'City', detail.city || dash());
      addHtmlList(dl, labels.knownEmails || 'Known email addresses', detail.emails || [], 'email');
      addHtmlList(dl, labels.knownPhones || 'Known phone numbers', detail.phones || [], 'phone');
      addHtmlList(dl, labels.socialPages || 'Social pages', (detail.socialPages || []).map(function (value) { return { value: value }; }), 'social');

      var custom = detail.custom || {};
      Object.keys(custom).forEach(function (key) {
        addDl(dl, key, custom[key] || dash());
      });
      panel.appendChild(dl);
      main.insertBefore(panel, main.firstChild);
    }

    function addDl(dl, term, value) {
      var dt = document.createElement('dt');
      dt.textContent = term;
      var dd = document.createElement('dd');
      dd.textContent = value || dash();
      dl.appendChild(dt);
      dl.appendChild(dd);
    }

    function addHtmlList(dl, term, items, kind) {
      var dt = document.createElement('dt');
      dt.textContent = term;
      var dd = document.createElement('dd');
      if (!items || !items.length) {
        dd.textContent = dash();
      } else {
        var list = document.createElement('ul');
        list.style.margin = '0';
        list.style.paddingLeft = '18px';
        items.forEach(function (item) {
          var value = String(item.value || '');
          var li = document.createElement('li');
          var target = null;
          if (kind === 'email') target = 'mailto:' + value;
          if (kind === 'phone') target = 'tel:' + value.replace(/[^+0-9]/g, '');
          if (kind === 'social' && /^https?:\/\//i.test(value)) target = value;
          if (target) {
            var a = document.createElement('a');
            a.href = target;
            a.textContent = value;
            if (kind === 'social') {
              a.target = '_blank';
              a.rel = 'noopener noreferrer';
            }
            li.appendChild(a);
          } else {
            li.textContent = value;
          }
          if (item.is_primary) {
            li.appendChild(document.createTextNode(' (' + (labels.primary || 'primary') + ')'));
          }
          list.appendChild(li);
        });
        dd.appendChild(list);
      }
      dl.appendChild(dt);
      dl.appendChild(dd);
    }

    function enhanceContactList() {
      if (page !== 'bfcamel-crm-contacts' || config.contactDetail || !config.contactListMeta) return;
      var table = document.querySelector('.bfcamel-crm-contacts-table');
      if (!table) return;
      var headRow = table.querySelector('thead tr');
      if (!headRow || headRow.children.length < 5) return;

      insertHeader(headRow, 5, labels.city || 'City');
      insertHeader(headRow, 6, labels.socialPages || 'Social pages');

      table.querySelectorAll('tbody tr').forEach(function (row) {
        var checkbox = row.querySelector('input[name="contact_ids[]"]');
        if (!checkbox) {
          var onlyCell = row.querySelector('td[colspan]');
          if (onlyCell) onlyCell.colSpan = Number(onlyCell.colSpan || 9) + 2;
          return;
        }
        var meta = config.contactListMeta[String(checkbox.value)] || {};
        insertCell(row, 5, meta.city || dash());
        insertCell(row, 6, (meta.socialPages || []).join(', ') || dash());
      });
    }

    function insertHeader(row, index, text) {
      var th = document.createElement('th');
      th.textContent = text;
      row.insertBefore(th, row.children[index] || null);
    }

    function insertCell(row, index, text) {
      var td = document.createElement('td');
      td.textContent = text;
      row.insertBefore(td, row.children[index] || null);
    }

    function enhanceDeletionUi() {
      if (page === 'bfcamel-crm-contacts') {
        addBulkDeleteOption('contact');
        if (config.deleteContact && config.deleteContact.id) {
          addDeleteButton('contact', config.deleteContact.id, config.deleteContact.nonce);
        }
      }
      if (page === 'bfcamel-crm-submissions') {
        addBulkDeleteOption('submission');
        if (config.deleteSubmission && config.deleteSubmission.id) {
          addDeleteButton('submission', config.deleteSubmission.id, config.deleteSubmission.nonce);
        }
      }
    }

    function addBulkDeleteOption(kind) {
      var select = document.querySelector('.bfcamel-crm-bulk-form select[name="bulk_action"]');
      if (!select || Array.from(select.options).some(function (option) { return option.value === 'delete'; })) return;
      var option = document.createElement('option');
      option.value = 'delete';
      option.textContent = labels.deletePermanently || 'Delete permanently';
      select.appendChild(option);
      var form = select.closest('form');
      if (form) {
        form.addEventListener('submit', function (event) {
          if (select.value !== 'delete') return;
          if (!window.confirm(labels.bulkDeleteWarn || 'Permanently delete the selected records?')) {
            event.preventDefault();
          }
        });
      }
    }

    function addDeleteButton(kind, id, nonce) {
      var title = document.querySelector('.bfcamel-crm-page-title');
      if (!title) return;
      var form = document.createElement('form');
      form.method = 'post';
      form.action = config.adminPostUrl || '';
      form.className = 'bfcamel-crm-inline-form';

      var action = document.createElement('input');
      action.type = 'hidden';
      action.name = 'action';
      action.value = kind === 'contact' ? 'bfcamel_crm_delete_contact' : 'bfcamel_crm_delete_submission';
      form.appendChild(action);

      var idInput = document.createElement('input');
      idInput.type = 'hidden';
      idInput.name = kind === 'contact' ? 'contact_id' : 'submission_id';
      idInput.value = String(id);
      form.appendChild(idInput);

      var nonceInput = document.createElement('input');
      nonceInput.type = 'hidden';
      nonceInput.name = '_wpnonce';
      nonceInput.value = nonce || '';
      form.appendChild(nonceInput);

      var button = document.createElement('button');
      button.type = 'submit';
      button.className = 'button button-link-delete';
      button.textContent = kind === 'contact'
        ? (labels.deleteContact || 'Delete contact permanently')
        : (labels.deleteSubmission || 'Delete submission permanently');
      form.appendChild(button);

      form.addEventListener('submit', function (event) {
        var message = kind === 'contact'
          ? (labels.deleteContactWarn || 'Permanently delete this contact and all linked submissions?')
          : (labels.deleteSubmissionWarn || 'Permanently delete this submission?');
        if (!window.confirm(message)) event.preventDefault();
      });

      var back = title.querySelector('a.button');
      if (back && back.parentNode === title) title.insertBefore(form, back);
      else title.appendChild(form);
    }

    function dash() {
      return labels.none || '—';
    }
  });
})();
