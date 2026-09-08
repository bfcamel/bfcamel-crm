=== BfCamel CRM ===
Contributors: bfcamel
Tags: crm, forms, form builder, contacts, consent
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Native form builder and lightweight CRM for WordPress: forms, submissions, contacts and consent evidence.

== Description ==

BfCamel CRM combines a native WordPress form builder with a lightweight CRM. Published form revisions act as server-side CRM contracts, so field validation, contact mapping and consent evidence do not depend on browser-controlled hidden values.

Version 0.1.1 is an alpha intended for testing before a future WordPress.org stable release.

Features include:

* Native forms without Contact Form 7.
* English interface with a bundled Russian translation; BfCamel CRM follows the WordPress site/user language.
* Immutable form revisions.
* CRM mapping for name, email, phone, organization and custom fields.
* Conflict-safe contact matching.
* Submission UUIDs and source evidence.
* Personal-data and marketing consent fields.
* Configurable URLs and versions for legal documents.
* Consent event history with document snapshots.
* Per-form appearance controls and custom wrapper classes.
* WordPress Privacy Exporter and Eraser support.
* Optional IP storage, disabled by default.

No CRM data is transmitted to BfCamel or third parties by this plugin.

== Installation ==

1. Upload the `bfcamel-crm` directory to `/wp-content/plugins/` or install the ZIP through Plugins → Add New → Upload Plugin.
2. Activate BfCamel CRM.
3. Open BfCamel CRM → Settings and configure legal document URLs if consent fields will be used.
4. Open BfCamel CRM → Forms and create a form.
5. Insert the generated shortcode into a post, page or page-builder shortcode widget.

== Frequently Asked Questions ==

= Does it require Contact Form 7? =

No. BfCamel CRM has its own form engine.

= Does the plugin automatically merge duplicate contacts? =

Only when matching is unambiguous. If email and phone point to different existing contacts, the submission is marked for review instead of merging records.

= Does an unchecked marketing checkbox revoke a previous consent? =

No. An unchecked optional consent field creates no revocation event.

= Does BfCamel receive my CRM data? =

No. Version 0.1.1 has no telemetry or external CRM service connection.

== Privacy ==

The plugin stores form submissions and CRM contact information in the WordPress database. Source URLs and browser User-Agent strings are stored with submissions. IP storage is optional and disabled by default.

The plugin integrates with WordPress Personal Data Export and Erase tools. Site operators remain responsible for determining their actual legal basis, disclosures and retention rules.

== Changelog ==

= 0.1.1 =
* Added bundled Russian translation while keeping English as the source language.
* Localized form-builder JavaScript labels, CRM statuses, contact synchronization states, dashboard counters and consent history labels.
* Added a bundled Russian MO translation catalog under `/languages`.

= 0.1.0 =
* First universal BfCamel CRM alpha.
* Added native form builder and renderer.
* Added revisioned form schemas.
* Added submissions and contact mapping.
* Added conflict-safe contact matching.
* Added legal document settings and consent evidence.
* Added per-form appearance controls.
* Added WordPress privacy exporter and eraser integration.
