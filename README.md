# BfCamel CRM

**BfCamel CRM** is an open-source WordPress form builder and lightweight CRM developed by the People & Camels Charity Foundation.

Version `0.3.0` adds manual contact creation, contact tags, CSV/XLSX exports, a workload-focused Dashboard and administrator-controlled permissions for every WordPress role. It also hardens the 0.2.0 submissions workflow with verified migrations, atomic updates and detailed activity history.

## Upgrade compatibility

The WordPress technical identity remains unchanged:

- plugin directory: `bfcamel-crm/`
- main plugin file: `bfcamel-crm.php`
- text domain: `bfcamel-crm`
- PHP namespace: `BfCamel\\CRM`
- options/capabilities/actions: `bfcamel_crm_*`
- database tables: `wp_bfcamel_crm_*`

Version `0.3.0` keeps the same plugin basename as every earlier release, so an installable release ZIP replaces the existing plugin rather than creating a second copy. Existing forms, submissions, contacts, tags, consent history and settings remain attached to the same installation. The release build fails if the plugin header, version constant, stable tag, update URI, main file or top-level ZIP directory no longer match this identity.

## CRM workspace in 0.3.0

- Create contacts manually with name, organization, email, phone and tags.
- Search and filter contacts by tag; edit tags from the contact record.
- Export the current contacts or submissions filter to UTF-8 CSV or native XLSX.
- Review new, urgent, unassigned and conflict submissions from the updated Dashboard.
- Configure CRM permissions for every WordPress role. All current and newly detected roles receive the main CRM permissions by default; only administrators can open Settings.
- Read detailed activity changes, including previous and new status, priority, responsible employee and tags.

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

Schema version 3 includes the version 2 workflow columns/tables and adds contact/tag relationships:

- `wp_bfcamel_crm_tags`
- `wp_bfcamel_crm_submission_tags`
- `wp_bfcamel_crm_contact_tags`

The updater checks every required table and workflow column before advancing the stored schema version. A failed migration leaves the previous schema version in place, displays an administrator notice and is retried. CRM tables use InnoDB so changes to a submission and its tags/history are committed or rolled back together.

## Localization

- English is the source language.
- Russian (`ru_RU`) is bundled as editable UTF-8 PO source.
- WordPress loads translations through the standard text-domain mechanism, respecting site/user locales and normal language-pack behavior.
- Release builds require a complete Russian catalog, compile a fresh MO from the UTF-8 PO source and validate it before packaging.

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
