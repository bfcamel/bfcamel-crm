(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (typeof text !== 'undefined') node.textContent = text;
    return node;
  }

  function makeList(items, labelPrimary) {
    var ul = el('ul', 'bfcamel-crm-data-list');
    (items || []).forEach(function (item) {
      var li = el('li');
      li.appendChild(document.createTextNode(item.value || ''));
      if (item.primary) {
        var badge = el('span', 'bfcamel-crm-badge', labelPrimary || 'Primary');
        badge.style.marginLeft = '8px';
        li.appendChild(badge);
      }
      ul.appendChild(li);
    });
    if (!ul.children.length) ul.appendChild(el('li', '', '—'));
    return ul;
  }

  function addDetailSection(panel, config, labels) {
    if (!panel || !config) return;

    var old = panel.querySelector('dl.bfcamel-crm-dl');
    if (old) old.style.display = 'none';

    var wrap = el('div', 'bfcamel-crm-contact-extra');
    wrap.appendChild(document.createElement('hr'));

    var emailsTitle = el('h3', '', labels.allEmails || 'All email addresses');
    wrap.appendChild(emailsTitle);
    wrap.appendChild(makeList(config.emails || [], labels.primary || 'Primary'));

    var phonesTitle = el('h3', '', labels.allPhones || 'All phone numbers');
    wrap.appendChild(phonesTitle);
    wrap.appendChild(makeList(config.phones || [], labels.primary || 'Primary'));

    var dl = el('dl', 'bfcamel-crm-dl');
    var addPair = function (name, value) {
      var dt = el('dt', '', name);
      var dd = el('dd');
      if (Array.isArray(value)) {
        if (!value.length) {
          dd.textContent = '—';
        } else {
          value.forEach(function (item, index) {
            if (index) dd.appendChild(document.createElement('br'));
            if (/^https?:\/\//i.test(item)) {
              var a = el('a', '', item);
              a.href = item;
              a.target = '_blank';
              a.rel = 'noopener noreferrer';
              dd.appendChild(a);
            } else {
              dd.appendChild(document.createTextNode(item));
            }
          });
        }
      } else {
        dd.textContent = value || '—';
      }
      dl.appendChild(dt);
      dl.appendChild(dd);
    };

    addPair(labels.city || 'City', config.city || '');
    addPair(labels.socialPages || 'Social pages', config.socialPages || []);

    var custom = config.custom || {};
    Object.keys(custom).sort().forEach(function (key) {
      addPair(key, custom[key]);
    });

    wrap.appendChild(el('h3', '', labels.customFields || 'Additional fields'));
    wrap.appendChild(dl);
    panel.appendChild(wrap);
  }

  function makeDeleteForm(kind, config, allConfig) {
    if (!config || !config.id || !config.nonce) return null;
    var labels = allConfig.labels || {};
    var form = el('form', 'bfcamel-crm-inline-form');
    form.method = 'post';
    form.action = allConfig.adminPost || '';

    var action = document.createElement('input');
    action.type = 'hidden';
    action.name = 'action';
    action.value = kind === 'contact' ? 'bfcamel_crm_delete_contact' : 'bfcamel_crm_delete_submission';
    form.appendChild(action);

    var id = document.createElement('input');
    id.type = 'hidden';
    id.name = kind === 'contact' ? 'contact_id' : 'submission_id';
    id.value = String(config.id);
    form.appendChild(id);

    var nonce = document.createElement('input');
    nonce.type = 'hidden';
    nonce.name = '_wpnonce';
    nonce.value = config.nonce;
    form.appendChild(nonce);

    var button = el('button', 'button button-link-delete', kind === 'contact'
      ? (labels.deleteContact || 'Delete contact permanently')
      : (labels.deleteSubmission || 'Delete submission permanently'));
    button.type = 'submit';
    button.addEventListener('click', function (event) {
      var message = kind === 'contact'
        ? (labels.confirmContact || 'Delete this contact permanently?')
        : (labels.confirmSubmission || 'Delete this submission permanently?');
      if (!window.confirm(message)) event.preventDefault();
    });
    form.appendChild(button);
    return form;
  }

  function addDeleteButton(kind, config, allConfig) {
    var form = makeDeleteForm(kind, config, allConfig);
    if (!form) return;
    var title = document.querySelector('.bfcamel-crm-page-title');
    if (!title) return;
    var actions = title.querySelector('.bfcamel-crm-actions');
    if (!actions) {
      actions = el('div', 'bfcamel-crm-actions');
      title.appendChild(actions);
    }
    actions.appendChild(form);
  }

  function addCityColumn(cities, labels) {
    if (!cities) return;
    var table = document.querySelector('.bfcamel-crm-contacts-table');
    if (!table) return;
    var headRow = table.querySelector('thead tr');
    if (!headRow) return;

    var headers = Array.prototype.slice.call(headRow.children);
    var orgIndex = headers.findIndex(function (cell) {
      return String(cell.textContent || '').trim().toLowerCase() === 'organization';
    });
    if (orgIndex < 0) orgIndex = 4;

    var cityHead = el('th', '', labels.city || 'City');
    if (headers[orgIndex] && headers[orgIndex].nextSibling) headRow.insertBefore(cityHead, headers[orgIndex].nextSibling);
    else headRow.appendChild(cityHead);

    table.querySelectorAll('tbody tr').forEach(function (row) {
      var link = row.querySelector('a[href*="page=bfcamel-crm-contacts"][href*="id="]');
      var id = '';
      if (link) {
        try { id = new URL(link.href, window.location.href).searchParams.get('id') || ''; } catch (e) {}
      }
      var cells = row.children;
      var td = el('td', '', id && Object.prototype.hasOwnProperty.call(cities, id) && cities[id] ? cities[id] : '—');
      if (cells[orgIndex] && cells[orgIndex].nextSibling) row.insertBefore(td, cells[orgIndex].nextSibling);
      else row.appendChild(td);
    });
  }

  ready(function () {
    var config = window.BfCamelCRMData || {};
    var labels = config.labels || {};

    if (config.contact) {
      var panel = document.querySelector('.bfcamel-crm-editor-grid main .bfcamel-crm-panel:first-child');
      addDetailSection(panel, config.contact, labels);
      addDeleteButton('contact', config.contact, config);
    }

    if (config.submission) {
      addDeleteButton('submission', config.submission, config);
    }

    if (config.cities) addCityColumn(config.cities, labels);
  });
})();
