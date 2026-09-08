=== GFR CRM ===
Contributors: bfcamel
Tags: crm, forms, form builder, contacts, consent
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Native form builder and lightweight CRM for WordPress: forms, submissions, contacts and consent evidence.

== Description ==

GFR CRM combines a native WordPress form builder with a lightweight CRM. Published form revisions act as server-side CRM contracts, so field validation, contact mapping and consent evidence do not depend on browser-controlled hidden values.

Version 0.1.2 is an alpha intended for testing before a future WordPress.org stable release.

Features include:

* Native forms without Contact Form 7.
* English source interface with a bundled Russian (`ru_RU`) translation.
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

No CRM data is transmitted to GFR, BfCamel or third parties by this plugin.

== Installation ==

1. If BfCamel CRM 0.1.0 or 0.1.1 is already installed, upload the GFR CRM 0.1.2 ZIP through Plugins → Add New → Upload Plugin. WordPress should offer to replace the existing plugin because the release intentionally keeps the technical `bfcamel-crm/bfcamel-crm.php` path.
2. For a clean installation, upload the same ZIP and activate GFR CRM.
3. Open GFR CRM → Settings and configure legal document URLs if consent fields will be used.
4. Open GFR CRM → Forms and create a form.
5. Insert `[gfr_form id="..."]` into a post, page or page-builder shortcode widget. Legacy `[bfcamel_form ...]` shortcodes continue to work.

== Frequently Asked Questions ==

= Why does the plugin directory still use bfcamel-crm? =

To preserve update compatibility with the earlier alpha versions. Changing the directory and main plugin filename during the rebrand would make WordPress treat the ZIP as a separate plugin.

= Does it require Contact Form 7? =

No. GFR CRM has its own form engine.

= Does the plugin automatically merge duplicate contacts? =

Only when matching is unambiguous. If email and phone point to different existing contacts, the submission is marked for review instead of merging records.

= Does an unchecked marketing checkbox revoke a previous consent? =

No. An unchecked optional consent field creates no revocation event.

== Privacy ==

The plugin stores form submissions and CRM contact information in the WordPress database. Source URLs and browser User-Agent strings are stored with submissions. IP storage is optional and disabled by default.

The plugin integrates with WordPress Personal Data Export and Erase tools. Site operators remain responsible for determining their actual legal basis, disclosures and retention rules.

== Changelog ==

= 0.1.2 =
* Renamed the visible product from BfCamel CRM to GFR CRM.
* Updated the project repository URL to `bfcamel/gfr-crm`.
* Fixed corrupted Russian localization by replacing the MO file with a binary-safe compiled catalog.
* Added PO and POT localization source files to the repository.
* Added the new `[gfr_form]` shortcode while retaining `[bfcamel_form]` as a compatibility alias.
* Kept the technical plugin directory and main filename unchanged so 0.1.2 updates earlier alpha installations instead of installing beside them.

= 0.1.1 =
* Added Russian localization infrastructure.

= 0.1.0 =
* First universal CRM + Forms alpha.
