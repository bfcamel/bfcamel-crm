# BfCamel CRM

**BfCamel CRM** is an open-source WordPress form builder and lightweight CRM developed under the BfCamel project by the People & Camels Charity Foundation.

Version `0.1.0` is the first functional alpha and intentionally starts a clean universal codebase instead of hard-coding the foundation's forms.

## What works in 0.1.0

- Native form builder with immutable published revisions.
- Field types: text, email, phone, number, date, textarea, select, radio, checkboxes, hidden, content block, personal-data consent and marketing consent.
- Per-field widths and responsive one/two-column layouts.
- Three appearance modes: theme/unstyled, default, custom.
- Per-form colors, border radius, custom wrapper class, submit label and response messages.
- Shortcode rendering: `[bfcamel_form id="1"]` or `[bfcamel_form slug="contact-form"]`.
- Server-side validation from the stored revision schema.
- CRM mapping for contact name, email, phone, organization and custom contact fields.
- Safe contact matching: conflicting phone/email identities are flagged for manual review and are never silently merged.
- Submissions with UUID, form revision, source URL and optional IP evidence.
- Legal-document settings for:
  - Personal Data Processing Consent;
  - Privacy Policy;
  - Marketing / Information Messages Consent.
- Consent event evidence stores the exact document URLs and versions that were active at submission time.
- An unchecked optional marketing checkbox never revokes an earlier consent.
- WordPress Personal Data Exporter and Eraser integration.
- Honeypot and basic per-form rate limiting.
- Data is retained on uninstall by default; destructive uninstall must be explicitly enabled.

## Architecture

The browser never defines the CRM contract. A form submission references a stored server-side form revision. That revision is the contract used for validation, CRM mapping and evidence.

Main tables:

- `wp_bfcamel_crm_forms`
- `wp_bfcamel_crm_form_revisions`
- `wp_bfcamel_crm_submissions`
- `wp_bfcamel_crm_contacts`
- `wp_bfcamel_crm_contact_emails`
- `wp_bfcamel_crm_contact_phones`
- `wp_bfcamel_crm_contact_fields`
- `wp_bfcamel_crm_consent_events`
- `wp_bfcamel_crm_activity_log`

## Privacy

BfCamel CRM stores data submitted through forms. Source URL and User-Agent are stored with submissions. IP-address storage is disabled by default and can be enabled in **BfCamel CRM → Settings** when an operator has an appropriate reason and privacy disclosure.

The plugin does not send CRM data to BfCamel or any third-party service.

## Requirements

- WordPress 6.2+
- PHP 7.4+

## Development roadmap

The next milestones are expected to add:

1. contact/submission editing, notes, tags and assignees;
2. configurable pipelines and routing rules;
3. email notifications;
4. form preview and richer form-builder UX;
5. legacy `lv-crm-suite` migration and optional Contact Form 7 import;
6. CSV/XLSX exports and segments;
7. automated Plugin Check / PHPCS / PHPUnit CI before WordPress.org submission.

## License

GPL-2.0-or-later.
