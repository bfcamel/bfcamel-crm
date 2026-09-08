# BfCamel CRM

**BfCamel CRM** is an open-source WordPress form builder and lightweight CRM developed by the People & Camels Charity Foundation.

Version `0.1.4` fixes the corrupted Russian interface seen with stale/malformed MO catalogs and makes Russian localization deterministic even when WordPress has an outdated global translation file cached in `wp-content/languages/plugins`.

## Upgrade compatibility

The WordPress technical identity intentionally remains unchanged:

- plugin directory: `bfcamel-crm/`
- main plugin file: `bfcamel-crm.php`
- text domain: `bfcamel-crm`
- PHP namespace: `BfCamel\\CRM`
- options/capabilities/actions: `bfcamel_crm_*`
- database tables: `wp_bfcamel_crm_*`

This means `0.1.4` keeps the same plugin basename (`bfcamel-crm/bfcamel-crm.php`) as the earlier BfCamel CRM versions and the temporary GFR CRM `0.1.2` release. Existing forms, submissions, contacts, consent history, settings and permissions therefore remain attached to the same installation.

### Important: use the installable release ZIP

An installable/update ZIP must have this exact top-level layout:

```text
bfcamel-crm/
├── bfcamel-crm.php
├── assets/
├── languages/
├── src/
└── uninstall.php
```

A generic GitHub **Source code (zip)** archive normally uses a repository/branch-derived folder name and must not be used as the WordPress update package. The repository includes a release-build workflow that produces `bfcamel-crm-<version>.zip` with the correct `bfcamel-crm/` root folder. Uploading that package through **Plugins → Add Plugin → Upload Plugin** makes WordPress offer replacement of the installed version instead of creating a second plugin directory.

## Localization

- English is the source language.
- Russian (`ru_RU`) is bundled as editable UTF-8 PO source.
- The interface follows the WordPress site/user locale.
- The technical text domain remains `bfcamel-crm` for backward compatibility.
- Russian locales use a deterministic PO fallback registered before plugin UI strings are rendered. This prevents a stale or corrupted MO file from producing truncated Cyrillic text or `�` replacement characters.
- The stale binary MO is not kept in the source tree anymore.
- Release builds compile a fresh `bfcamel-crm-ru_RU.mo` from the PO source and fail if the catalog cannot be decoded or the known `Forms → Формы` translation is missing.
- Other locales continue through WordPress' normal gettext loading path.

## What works

- Native form builder with immutable published revisions.
- Field types: text, email, phone, number, date, textarea, select, radio, checkboxes, hidden, content block, personal-data consent and marketing consent.
- Per-field widths and responsive one/two-column layouts.
- Theme/default/custom appearance modes.
- Per-form colors, border radius, custom wrapper class, submit label and response messages.
- Preferred shortcode: `[bfcamel_form id="1"]` or `[bfcamel_form slug="contact-form"]`.
- Temporary-rebrand shortcode `[gfr_form ...]` remains supported as a compatibility alias.
- Server-side validation from stored form revisions.
- CRM mapping for contact name, email, phone, organization and custom contact fields.
- Conflict-safe contact matching.
- Submission UUIDs, form revision tracking, source URL and optional IP evidence.
- Legal-document URLs and versions for personal-data consent, privacy policy and marketing consent.
- Consent event history with document snapshots.
- WordPress Personal Data Exporter and Eraser integration.
- Honeypot and basic per-form rate limiting.

## Architecture and data compatibility

The server-side form revision is the CRM contract. Browser-controlled hidden values do not define field mapping or consent-document versions.

Existing database tables remain unchanged, including:

- `wp_bfcamel_crm_forms`
- `wp_bfcamel_crm_form_revisions`
- `wp_bfcamel_crm_submissions`
- `wp_bfcamel_crm_contacts`
- `wp_bfcamel_crm_contact_emails`
- `wp_bfcamel_crm_contact_phones`
- `wp_bfcamel_crm_contact_fields`
- `wp_bfcamel_crm_consent_events`
- `wp_bfcamel_crm_activity_log`

## Requirements

- WordPress 6.2+
- PHP 7.4+

## License

GPL-2.0-or-later.
