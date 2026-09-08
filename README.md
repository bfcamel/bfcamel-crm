# BfCamel CRM

**BfCamel CRM** is an open-source WordPress form builder and lightweight CRM developed by the People & Camels Charity Foundation.

Version `0.2.0` turns incoming form submissions into a practical CRM work queue: staff can search and filter submissions, assign responsible employees, set priorities, add tags and review a complete activity history.

## Upgrade compatibility

The WordPress technical identity remains unchanged:

- plugin directory: `bfcamel-crm/`
- main plugin file: `bfcamel-crm.php`
- text domain: `bfcamel-crm`
- PHP namespace: `BfCamel\\CRM`
- options/capabilities/actions: `bfcamel_crm_*`
- database tables: `wp_bfcamel_crm_*`

Version `0.2.0` keeps the same plugin basename as earlier releases, so an installable release ZIP replaces the existing plugin rather than creating a second copy. Existing forms, contacts, consent history and settings remain attached to the same installation.

## Submissions workflow in 0.2.0

- Server-side search by ID, name, email, phone, form name and submitted data.
- Filters by status, form, responsible employee, priority, tag and date range.
- Server-side pagination instead of the previous hard 250-row limit.
- Responsible employee assignment using WordPress users.
- Priorities: normal, high and urgent.
- Free-form submission tags with automatic tag creation and tag filtering.
- Activity history for submission creation, status changes, assignment changes, priority changes and tag changes.
- Existing statuses remain compatible: `new`, `in_progress`, `waiting`, `completed`, `needs_review`.

## Data model

Schema version 2 adds `assigned_to` and `priority` to submissions plus two tag tables:

- `wp_bfcamel_crm_tags`
- `wp_bfcamel_crm_submission_tags`

There is no legacy submission backfill or data conversion logic in 0.2.0. The normal schema installer/dbDelta path only ensures the required columns and tables exist.

## Localization

- English is the source language.
- Russian (`ru_RU`) is bundled as editable UTF-8 PO source.
- Russian locales use the deterministic PO fallback introduced in 0.1.4, preventing stale global MO catalogs from corrupting Cyrillic text.
- Release builds compile a fresh MO catalog from the PO source and validate it before packaging.

## Core features

- Native WordPress form builder with immutable published revisions.
- CRM mapping for name, email, phone, organization and custom contact fields.
- Conflict-safe contact matching.
- Submission UUIDs and source evidence.
- Personal-data and marketing consent fields with document snapshots.
- WordPress Privacy Exporter and Eraser support.
- Honeypot and basic per-form rate limiting.
- Preferred shortcode: `[bfcamel_form id="1"]` or `[bfcamel_form slug="contact-form"]`.
- `[gfr_form ...]` remains as a compatibility alias for the temporary 0.1.2 rebrand.

## Installable ZIP

Use the release ZIP produced by GitHub Actions. Its top-level directory is exactly `bfcamel-crm/`. Do not use GitHub's generic **Source code (zip)** archive for an in-place WordPress update.

## Requirements

- WordPress 6.2+
- PHP 7.4+

## License

GPL-2.0-or-later.
