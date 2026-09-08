=== BfCamel CRM ===
Contributors: bfcamel
Tags: crm, forms, form builder, contacts, consent
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Native form builder and lightweight CRM for WordPress: forms, submissions, contacts and consent evidence.

== Description ==

BfCamel CRM combines a native WordPress form builder with a lightweight CRM. Published form revisions act as server-side CRM contracts, so field validation, contact mapping and consent evidence do not depend on browser-controlled hidden values.

Version 0.1.3 is an alpha intended for testing before a future stable release.

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

No CRM data is transmitted to BfCamel or third parties by this plugin.

== Installation ==

1. Download an installable BfCamel CRM release ZIP whose top-level folder is exactly `bfcamel-crm/`. Do not use GitHub's generic Source code ZIP as an update package.
2. If an earlier BfCamel CRM or temporary GFR CRM 0.1.2 installation is already present, upload the release ZIP through Plugins → Add Plugin → Upload Plugin. WordPress should offer to replace the existing plugin because the package keeps the technical `bfcamel-crm/bfcamel-crm.php` path.
3. For a clean installation, upload the same ZIP and activate BfCamel CRM.
4. Open BfCamel CRM → Settings and configure legal document URLs if consent fields will be used.
5. Open BfCamel CRM → Forms and create a form.
6. Insert `[bfcamel_form id="..."]` into a post, page or page-builder shortcode widget. `[gfr_form ...]` remains available only as a compatibility alias for pages created during 0.1.2.

== Frequently Asked Questions ==

= Why must the plugin directory remain bfcamel-crm? =

WordPress identifies an installed plugin by its plugin basename. Keeping `bfcamel-crm/bfcamel-crm.php` preserves in-place update compatibility and prevents the ZIP from being installed alongside the existing plugin under a second folder name.

= Can I use the GitHub Source code ZIP directly in WordPress? =

Not for an in-place update. GitHub source archives normally contain a branch-derived top-level folder. Use the release ZIP produced by the repository build workflow; it contains the required `bfcamel-crm/` root folder.

= Does it require Contact Form 7? =

No. BfCamel CRM has its own form engine.

= Does the plugin automatically merge duplicate contacts? =

Only when matching is unambiguous. If email and phone point to different existing contacts, the submission is marked for review instead of merging records.

= Does an unchecked marketing checkbox revoke a previous consent? =

No. An unchecked optional consent field creates no revocation event.

== Privacy ==

The plugin stores form submissions and CRM contact information in the WordPress database. Source URLs and browser User-Agent strings are stored with submissions. IP storage is optional and disabled by default.

The plugin integrates with WordPress Personal Data Export and Erase tools. Site operators remain responsible for determining their actual legal basis, disclosures and retention rules.

== Changelog ==

= 0.1.3 =
* Restored the visible product name to BfCamel CRM.
* Removed the temporary GFR rebranding gettext shim.
* Moved text-domain loading to `init` so the current WordPress site/user locale is resolved reliably.
* Restored BfCamel CRM strings in privacy tools, frontend notices and translation sources.
* Kept `[gfr_form]` as a compatibility alias while returning `[bfcamel_form]` to the preferred shortcode.
* Added release packaging that always creates a ZIP with the `bfcamel-crm/` root folder for in-place WordPress updates.
* Kept database tables, options, capabilities, actions, namespace, text domain and plugin basename unchanged.

= 0.1.2 =
* Temporary GFR CRM visible rebrand.
* Added PO and POT localization source files and `[gfr_form]` compatibility shortcode.

= 0.1.1 =
* Added Russian localization infrastructure.

= 0.1.0 =
* First universal CRM + Forms alpha.
