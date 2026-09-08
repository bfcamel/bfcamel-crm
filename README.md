# GFR CRM

**GFR CRM** is an open-source WordPress form builder and lightweight CRM developed by the People & Camels Charity Foundation.

Version `0.1.2` fixes localization packaging, preserves update compatibility with the earlier BfCamel CRM alpha, and completes the visible rebrand to GFR CRM.

## Important upgrade compatibility

The public product name and repository are now **GFR CRM** / `bfcamel/gfr-crm`, but the WordPress plugin directory, main file, text domain, database table prefixes and existing internal identifiers intentionally remain `bfcamel-crm` / `bfcamel_crm_*` for now. This allows version `0.1.2` to replace `0.1.0` / `0.1.1` as an update instead of appearing as a separate clean installation.

Installable release ZIPs therefore keep this structure:

```text
bfcamel-crm/
└── bfcamel-crm.php
```

Do not rename that directory on an existing installation. The visible WordPress plugin name is **GFR CRM**.

## Localization

- English is the source language.
- Russian (`ru_RU`) is bundled.
- The interface follows the WordPress site/user locale.
- The repository contains editable `.po` / `.pot` sources and a correctly compiled binary `.mo` catalog.

The previous `0.1.1` `.mo` file was uploaded through a text path and could be corrupted in transit, producing mojibake. `0.1.2` replaces it with a binary-safe compiled catalog.

## What works

- Native form builder with immutable published revisions.
- Field types: text, email, phone, number, date, textarea, select, radio, checkboxes, hidden, content block, personal-data consent and marketing consent.
- Per-field widths and responsive one/two-column layouts.
- Theme/default/custom appearance modes.
- Per-form colors, border radius, custom wrapper class, submit label and response messages.
- Preferred shortcode: `[gfr_form id="1"]` or `[gfr_form slug="contact-form"]`.
- Legacy `[bfcamel_form ...]` remains supported for backward compatibility.
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

Existing database tables are intentionally unchanged in `0.1.2`, including:

- `wp_bfcamel_crm_forms`
- `wp_bfcamel_crm_form_revisions`
- `wp_bfcamel_crm_submissions`
- `wp_bfcamel_crm_contacts`
- `wp_bfcamel_crm_contact_emails`
- `wp_bfcamel_crm_contact_phones`
- `wp_bfcamel_crm_contact_fields`
- `wp_bfcamel_crm_consent_events`
- `wp_bfcamel_crm_activity_log`

This is deliberate: rebranding must not disconnect an existing installation from its data.

## Requirements

- WordPress 6.2+
- PHP 7.4+

## License

GPL-2.0-or-later.
