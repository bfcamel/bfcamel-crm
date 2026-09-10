# BfCamel CRM Wiki

BfCamel CRM is an open-source native WordPress form builder and lightweight CRM developed by the People & Camels Charity Foundation. The current documented release is **0.5.1**.

The plugin combines public forms with contact management, submission workflow, consent evidence, exports and WordPress privacy tools without requiring an external CRM service.

## What BfCamel CRM provides

- Native form builder with immutable published revisions.
- Public forms embedded with the `bfcamel_form` shortcode.
- CRM contact mapping for name, email, phone, organization and custom fields.
- Conflict-aware contact matching during form submission.
- Submission statuses, priorities, responsible employees and tags.
- Priority automation rules based on form fields.
- Personal-data and marketing consent history, including manual status changes.
- CSV and XLSX export for contacts and submissions.
- WordPress Personal Data Exporter and Eraser integration.
- Configurable storage of IP address, source URL and browser information.
- English source strings and bundled Russian localization.

## Requirements

BfCamel CRM 0.5.1 requires WordPress **6.2 or later** and PHP **7.4 or later**. Network-wide multisite activation is intentionally not supported; on multisite, activate the plugin separately for each site.

## Documentation

Start with [Installation and Updates](Installation-and-Updates.md), then follow [Getting Started](Getting-Started.md).

For day-to-day administration, see [Forms and Shortcodes](Forms-and-Shortcodes.md), [Submissions](Submissions.md), [Contacts](Contacts.md), [Workflow and Tags](Workflow-and-Tags.md), [Consent and Privacy](Consent-and-Privacy.md), [Roles and Permissions](Roles-and-Permissions.md), and [Export](Export.md).

For technical information, see [Data Model](Data-Model.md), [Developer Guide](Developer-Guide.md), [Localization](Localization.md), [Troubleshooting](Troubleshooting.md), and [Release History](Release-History.md).

## Plugin identity

For reliable in-place upgrades, the WordPress technical identity remains stable:

- Directory: `bfcamel-crm/`
- Main file: `bfcamel-crm.php`
- Text domain: `bfcamel-crm`
- PHP namespace: `BfCamel\\CRM`
- Options, capabilities and actions: `bfcamel_crm_*`
- Database tables: `{prefix}bfcamel_crm_*`

Use the official installable **`bfcamel-crm.zip`** release package when updating manually. Do not use GitHub's generic “Source code” archive as an in-place WordPress update package.

## License

BfCamel CRM is licensed under **GPL-2.0-or-later**.
