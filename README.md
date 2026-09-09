# BfCamel CRM

**BfCamel CRM** is an open-source WordPress form builder and lightweight CRM developed by the People & Camels Charity Foundation.

Version **0.3.2** expands the everyday CRM workspace while preserving the established WordPress plugin identity and in-place update path introduced in earlier releases.

## 0.3.2 CRM workspace

- Edit contact name, organization, primary email and primary phone with audited changes.
- Add internal notes to contacts and submissions with author and timestamp history.
- Run bulk actions on submissions: status, priority, responsible employee, add tags and remove tags.
- Run bulk tag actions on contacts.
- Filter contacts by personal-data and marketing consent status.
- See both consent statuses directly in the contacts list.
- Open **My submissions** as a one-click view for the current WordPress user.
- Use pagination above and below contacts and submissions tables.
- Export selected contacts to UTF-8 CSV or native XLSX in addition to filtered exports.
- Use the updated complete Russian (`ru_RU`) interface.

## Upgrade compatibility

The WordPress technical identity remains unchanged:

- plugin directory: `bfcamel-crm/`
- main plugin file: `bfcamel-crm.php`
- text domain: `bfcamel-crm`
- PHP namespace: `BfCamel\\CRM`
- options/capabilities/actions: `bfcamel_crm_*`
- database tables: `wp_bfcamel_crm_*`

Install or update using the official `bfcamel-crm.zip` release asset. Do not use GitHub's generic Source code ZIP for an in-place WordPress update.

## Data model

Schema version 5 keeps the existing forms, contacts, submissions, tags, consent history and activity log and adds `wp_bfcamel_crm_notes` for internal contact/submission notes. CRM tables remain InnoDB-backed and migrations are verified before the stored schema version advances.

## Localization

English remains the source language. Russian (`ru_RU`) is bundled as PO and MO catalogs. Release builds reject incomplete translations and verify that the committed MO is semantically identical to a freshly compiled catalog.

## Core features

- Native WordPress form builder with immutable published revisions.
- CRM mapping for name, email, phone, organization and custom fields.
- Conflict-safe contact matching.
- Submission workflow with statuses, priorities, responsible employees and tags.
- Audited consent events for personal-data processing and marketing messages.
- CSV/XLSX exports with formula-injection protection.
- WordPress Privacy Exporter and Eraser integration.
- Honeypot and basic per-form rate limiting.
- Preferred shortcode: `[bfcamel_form id="1"]` or `[bfcamel_form slug="contact-form"]`.
- `[gfr_form ...]` remains as a compatibility alias for the temporary 0.1.2 rebrand.

## Requirements

- WordPress 6.2+
- PHP 7.4+

## License

GPL-2.0-or-later.
