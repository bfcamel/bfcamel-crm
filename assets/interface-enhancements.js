(function () {
  'use strict';

  var config = window.BfCamelCRMInterface || {};
  var labels = config.labels || {};

  if (window.BfCamelCRMData && window.BfCamelCRMData.labels) {
    if (labels.city) window.BfCamelCRMData.labels.city = labels.city;
    if (labels.socialPages) window.BfCamelCRMData.labels.socialPages = labels.socialPages;
  }

  if (window.BfCamelCRMBuilder && window.BfCamelCRMBuilder.labels) {
    if (labels.contactCity) window.BfCamelCRMBuilder.labels.contactCity = labels.contactCity;
    if (labels.contactSocialPage) window.BfCamelCRMBuilder.labels.contactSocialPage = labels.contactSocialPage;
  }

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  function hidden(name, value) {
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = String(value || '');
    return input;
  }

  function deleteForm(formId) {
    if (!formId || !config.deleteFormNonce || !config.adminPost) return null;

    var form = document.createElement('form');
    form.className = 'bfcamel-crm-inline-form bfcamel-crm-permanent-delete';
    form.method = 'post';
    form.action = config.adminPost;
    form.appendChild(hidden('action', 'bfcamel_crm_delete_form'));
    form.appendChild(hidden('form_id', formId));
    form.appendChild(hidden('_wpnonce', config.deleteFormNonce));

    var button = document.createElement('button');
    button.type = 'submit';
    button.className = 'button button-link-delete bfcamel-crm-danger';
    button.textContent = labels.deleteForm || 'Delete form permanently';
    button.addEventListener('click', function (event) {
      var message = labels.confirmDeleteForm || 'Delete this form permanently? This cannot be undone.';
      if (!window.confirm(message)) event.preventDefault();
    });
    form.appendChild(button);
    return form;
  }

  function addDeleteButtonsToFormList() {
    if (config.page !== 'bfcamel-crm-forms') return;

    document.querySelectorAll('.bfcamel-crm-table tbody tr').forEach(function (row) {
      if (row.querySelector('.bfcamel-crm-permanent-delete')) return;
      var formIdInput = row.querySelector('input[name="form_id"]');
      if (!formIdInput || !formIdInput.value) return;
      var actionCell = formIdInput.closest('td');
      if (!actionCell) return;
      var form = deleteForm(formIdInput.value);
      if (form) actionCell.appendChild(form);
    });
  }

  function addDeleteButtonToFormEditor() {
    if (config.page !== 'bfcamel-crm-forms' || !config.formId) return;
    var title = document.querySelector('.bfcamel-crm-page-title');
    if (!title || title.querySelector('.bfcamel-crm-permanent-delete')) return;
    var form = deleteForm(config.formId);
    if (form) title.appendChild(form);
  }

  function normalizeButtons() {
    document.querySelectorAll('.bfcamel-crm-admin .button:not(.button-link-delete)').forEach(function (button) {
      button.classList.add('button-primary');
    });
    document.querySelectorAll('.bfcamel-crm-admin .page-title-action').forEach(function (button) {
      button.classList.add('button', 'button-primary');
    });
  }

  ready(function () {
    addDeleteButtonsToFormList();
    addDeleteButtonToFormEditor();
    normalizeButtons();
  });
})();
