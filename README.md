# BfCamel CRM

**BfCamel CRM** is an open-source WordPress form builder and lightweight CRM developed by the People & Camels Charity Foundation.

Version **0.5.1** is the current public release prepared for distribution through WordPress.org. It preserves the established plugin identity and the in-place update path from earlier BfCamel CRM versions.

## 0.5.1 release highlights

- Clearer administration menu terminology for workflow and general settings.
- Consistent Russian terminology: submissions are displayed as “Заявки”.
- Uniform dashboard action buttons and simplified pill-shaped status badges.

## 0.5.0 release highlights

- Least-privilege CRM permissions: only administrators receive access by default.
- Batched WordPress Personal Data Exporter and Eraser coverage for contacts, submissions, consent evidence and activity metadata.
- Consent fields remain usable without legal-document URLs; the stored evidence records the exact text and document availability.
- Independent privacy controls for IP addresses, source URLs and browser information.
- Concurrency protection for contact matching.
- Consolidated, responsive administrative design system with improved accessibility.
- WordPress.org-compatible update metadata, directory readme and official Plugin Check validation.

## 0.4.1 audit fixes

- Built-in statuses and priorities follow the current WordPress user locale, including Russian.
- Dashboard cards use the configured default status and highest priority instead of hard-coded slugs.
- Custom workflow colors are visible in admin badges, and custom slugs fit their database columns.
- At least one enabled default status and priority is always retained.
- The one-column form setting now changes the rendered layout.
- Form publishing, public submission capture and tag-catalog changes are transactional.
- Incomplete bulk actions and failed archive, restore or tag operations no longer report false success.
- Release publication is blocked unless the PHP 7.4/8.1/8.3/8.4/8.5, smoke, duplicate-load, JavaScript and official Plugin Check jobs pass.

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

Schema version 6 keeps the existing forms, contacts, submissions, tags, consent history, notes and activity log while hardening upgrades and privacy operations. CRM tables remain InnoDB-backed and migrations are verified before the stored schema version advances.

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
