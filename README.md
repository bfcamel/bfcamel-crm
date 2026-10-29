# BfCamel CRM

**BfCamel CRM** is an open-source WordPress form builder and lightweight CRM developed by the People & Camels Charity Foundation.

Version **0.5.6** is the current public release prepared for distribution through WordPress.org. It preserves the established plugin identity and the in-place update path from earlier BfCamel CRM versions.

## 0.5.6 release highlights

- Removed the temporary three-letter compatibility shortcode so the public shortcode namespace fully follows WordPress.org prefix requirements.
- Scoped database-upgrade notices to BfCamel CRM screens and added actionable recovery guidance.
- Switched installable packages to WordPress.org language packs while retaining and validating the complete Russian source catalog in the repository.
- Added packaging checks that reject bundled PO/MO files before a release can be published.

## 0.5.5 release highlights

- First-class city and social-page contact mappings with manual editing, search, audit-history and export support.
- Unique social profiles are merged across repeat submissions instead of replacing earlier values.
- Native, refreshed contact screens and unified brand-styled administration controls.
- Confirmed transactional deletion for contacts and submissions.
- Protected permanent deletion for archived forms without submissions.
- Complete Russian localization for the new interface and clearer form-key versus CRM-mapping guidance.
- Native server-side contact data and deletion controls replace the JavaScript-only enhancement layer from 0.5.4.

## 0.5.2 release highlights

- Resolved the warnings reported by WordPress Plugin Check for request handling, SQL identifiers, custom-table access, uninstall scope and translation loading.
- Added cache-backed reads with explicit invalidation for core CRM reference data.
- Hardened frontend return-URL construction against untrusted host headers.
- Changed the release gate so Plugin Check warnings block publication.
- Includes all interface refinements prepared for 0.5.1.

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

Schema version 7 keeps the existing forms, contacts, submissions, tags, consent history, notes and activity log while hardening upgrades and privacy operations. CRM tables remain InnoDB-backed and migrations are verified before the stored schema version advances.

## Localization

English remains the source language. The complete Russian (`ru_RU`) catalog is maintained and validated in the source repository. Installable packages omit PO and MO files so approved translations can be delivered through standard WordPress.org language packs; the POT template remains in the package.

## Core features

- Native WordPress form builder with immutable published revisions.
- CRM mapping for name, email, phone, organization, city, social pages and custom fields.
- Conflict-safe contact matching.
- Submission workflow with statuses, priorities, responsible employees and tags.
- Audited consent events for personal-data processing and marketing messages.
- CSV/XLSX exports with formula-injection protection.
- WordPress Privacy Exporter and Eraser integration.
- Honeypot and basic per-form rate limiting.
- Shortcode: `[bfcamel_form id="1"]` or `[bfcamel_form slug="contact-form"]`.

## Requirements

- WordPress 6.2+
- PHP 7.4+

## License

GPL-2.0-or-later.



## Pending security fixes (unreleased)

The pending changes add two notification-only tables in schema version 7 and do not rewrite or delete existing CRM records on update. Public requests that add an unknown email or phone to a known contact are saved for review. Exact identifier matches still link submissions, but public requests cannot edit existing contact fields or change their consent status; their consent evidence remains attached to the submission.

Form answers now use a separate request namespace. Already-rendered pre-update forms remain accepted until their existing nonce expires. Privacy tools discover detached and conflicting submissions from the immutable revision schema and scan fixed-size batches; explicit erasure anonymizes matching records without deleting submission rows.

Pushes and pull requests run validation without building an update ZIP. Packaging requires a manual workflow run with `build_release` enabled; publication additionally requires `publish_release` on `main`.

For the upgrade regression suite, use an isolated MySQL-backed WordPress installation: activate commit `d97473338d9d01275df08e08baa415342fc9584c`, run `wp eval-file tests/integration-seed.php` from this branch, replace only the plugin sources, then run `wp eval-file tests/integration.php`. Set `BFCAMEL_TEST_WP_LOAD` to that installation's absolute `wp-load.php` path. These fixture scripts must never be run against a production database. CI performs this sequence on WordPress 6.2 and latest.


## Inbox and notifications (unreleased)

The CRM landing screen is the attention inbox. Secondary filters, exports and batch actions are disclosed only when needed. Large request titles open a desktop preview; mobile users open the full card. Contact names and explicit open links provide reliable navigation without JavaScript. Taking ownership is separate from a personal read receipt and is atomic across staff members.

Notification severity follows enabled priority weights: the highest distinct weight is urgent, intermediate weights are high, and the lowest is ordinary. Configure working and closed statuses under Settings → Notifications and delivery. Existing submissions are not backfilled into the notification feed when upgrading. Schema version 7 adds `notifications` and `notification_receipts` without rewriting the original 13 tables.

Internal notices work for users with submission access throughout WordPress administration. Browser notifications and sound require an explicit choice on each device and an open administration page. Email and Telegram are disabled until an administrator configures them. External messages contain only numbers and authenticated CRM links, never submitted names, phones or message contents. Email uses WordPress mail transport; Telegram sends to the configured bot API. The bot token is kept in a non-autoloaded administrator-only option and is never returned to browser scripts or prefilled in the settings field.

Delivery is queued outside the public submission transaction, retries temporary failures up to five times, and generates one escalation for an unclaimed urgent event. Ordinary emails are grouped in hourly digests. Use a real server invocation of WordPress cron for dependable background delivery on low-traffic sites; WP-Cron alone depends on site visits. Transport acceptance does not guarantee final email delivery. As with most mail transports, a worker interruption after acceptance and before saving its receipt can result in a retry.
