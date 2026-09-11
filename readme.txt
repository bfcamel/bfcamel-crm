=== BfCamel CRM ===
Contributors: bfcamel
Tags: crm, forms, contacts, consent, submissions
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.5.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build forms and manage submissions, contacts, workflow, consent evidence and privacy requests inside WordPress.

== Description ==

BfCamel CRM combines a native WordPress form builder with a lightweight contact and submission workspace.

Create forms without an external form service, publish immutable revisions, map submitted fields to CRM contacts, assign submissions, use configurable statuses and priorities, maintain internal notes and export selected data.

= Core features =

* Native form builder with responsive field widths.
* Shortcodes by form ID or slug.
* Immutable published form revisions.
* Contact matching by normalized email and phone.
* Configurable submission statuses and priorities.
* Responsible-user assignment and shared tags.
* Internal contact and submission notes.
* Personal-data and marketing consent events.
* Optional legal document links and version snapshots.
* CSV and XLSX exports with spreadsheet formula-injection protection.
* WordPress Personal Data Exporter and Eraser integration.
* Per-role CRM permissions.
* English and Russian interface.
* No telemetry and no required external service.

= Legal documents and consent =

Document URLs are optional. Forms continue to work when URLs are not configured. Consent evidence records the text displayed to the visitor and whether document links were available at submission time.

BfCamel CRM provides technical tools for recording form choices. It does not provide legal advice and does not by itself guarantee compliance with any particular law. The site owner is responsible for the legal basis, wording, document links and retention periods used on the site.

= Privacy =

Depending on the form and plugin settings, BfCamel CRM may store:

* submitted form values;
* names, email addresses, phone numbers and organizations;
* internal notes;
* consent events and document snapshots;
* source page URLs and browser information;
* IP addresses when the site owner explicitly enables IP storage;
* structured activity history.

The plugin registers handlers with the WordPress Personal Data Export and Erase tools. Technical metadata can be disabled in CRM settings. Data is preserved on uninstall by default; administrators can explicitly enable complete deletion.

= External services =

BfCamel CRM does not send data to an external service and does not load a remote SDK, analytics service or CAPTCHA.

== Installation ==

1. Upload the `bfcamel-crm` folder to `/wp-content/plugins/`, or install the ZIP through Plugins > Add Plugin > Upload Plugin.
2. Activate BfCamel CRM.
3. Open BfCamel CRM > Settings and review access, privacy and legal-document options.
4. Open BfCamel CRM > Forms and create a form.
5. Insert `[bfcamel_form id="1"]` into a page, replacing `1` with the form ID.

To update an existing installation, upload the installable `bfcamel-crm.zip` package. Do not install GitHub's automatically generated source-code archive.

== Frequently Asked Questions ==

= Are legal-document links required? =

No. Forms can collect data without document URLs. The settings screen displays a warning and lets an administrator acknowledge responsibility for the site's legal configuration.

= Does the plugin send CRM data to a cloud service? =

No. CRM data remains in the site's WordPress database unless an administrator exports it or another installed integration processes it.

= Does uninstall delete CRM data? =

Not by default. Enable the explicit deletion option before uninstalling if all plugin tables and settings should be removed.

= Can I use my theme or page builder to style forms? =

Yes. Choose Theme / unstyled in the form editor and use the stable `bfcamel-form`, grid and field classes documented under CRM configuration > Form CSS.

= Is multisite supported? =

Version 0.5.2 supports activation on individual sites. Network-wide activation is not supported.

== Changelog ==

= 0.5.2 =

* Resolved WordPress Plugin Check warnings for request handling, database identifiers, custom-table access, uninstall scope and translation loading.
* Added cache-backed form, revision, contact and tag reads with explicit invalidation after writes.
* Rebuilt frontend return URLs against the trusted WordPress home URL.
* Made Plugin Check warnings block release publication.
* Included the interface refinements prepared for 0.5.1.

= 0.5.1 =

* Clarified the workflow menu label so it is no longer confused with general settings.
* Standardized the Russian interface term for submissions as “Заявки”.
* Made the dashboard action buttons equal in size and hover behavior.
* Simplified status badges to a clean pill shape without the colored left inset.

= 0.5.0 =

* Changed CRM role defaults to least privilege and added a secure migration for legacy permissions.
* Rebuilt the WordPress Personal Data Exporter and Eraser with batching and broader data coverage.
* Kept consent fields available without legal-document URLs and recorded the exact displayed consent evidence.
* Added explicit administrator acknowledgement for operation without document links.
* Added independent controls for IP, source URL and browser-information storage.
* Added an identifier lock to prevent duplicate contacts during concurrent submissions.
* Blocked public form processing while the CRM database schema is incomplete.
* Converted tag deletion and form archive/restore actions to nonce-protected POST requests.
* Consolidated legacy administrative assets and introduced a responsive, accessible design system.
* Completed localization of newly added PHP and JavaScript interface strings.
* Removed the third-party Update URI so WordPress.org can provide updates.
* Expanded release validation with the official WordPress Plugin Check action.
* Updated directory documentation and package exclusions for the first public release.

= 0.4.1 =

* Fixed configurable workflow localization, dashboard links and form layout.
* Added transactional writes and gated GitHub release packaging on smoke checks.

= 0.4.0 =

* Added configurable statuses, priorities, automation foundations and shared tag management.

= 0.3.2 =

* Added contact editing, internal notes, bulk actions, consent filters, assignments and selected exports.

== Upgrade Notice ==

= 0.5.2 =

This compliance update resolves the WordPress Plugin Check report without changing the database schema or stored CRM data.

= 0.5.1 =

This interface update clarifies CRM terminology and standardizes dashboard controls without changing stored data.

= 0.5.0 =

This security and privacy update removes automatic CRM access from non-administrator roles. Review role permissions after updating.
