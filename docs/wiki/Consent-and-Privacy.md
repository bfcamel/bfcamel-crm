# Consent and Privacy

BfCamel CRM records consent as an event history rather than a single mutable flag. It also integrates with WordPress's built-in personal-data tools.

## Consent types

Two consent types are built in:

- `personal_data` — Personal data processing
- `marketing` — Email and marketing messages

Supported statuses are `unknown`, `granted`, `denied` and `revoked`.

## Form consent events

When a public form contains a personal-data or marketing consent field, BfCamel CRM records an event for that consent type as part of the submission transaction.

The event can include:

- Contact ID
- Submission ID
- Consent type and status
- Form ID and immutable form revision ID
- Snapshot of the displayed field text
- Snapshot of configured legal-document metadata
- Whether the required legal-document URLs were configured at that moment
- Source URL, IP address and User-Agent according to privacy settings
- Event timestamp

This evidence is stored as a snapshot. Editing legal-document settings later does not rewrite old consent history.

## Manual consent changes

Authorized CRM users can record a new consent state from the contact interface. Manual events use `source_type = manual` and record the WordPress user who made the change.

The current consent state shown for a contact is derived from the latest event for each consent type.

## Legal documents

Open **BfCamel CRM → Settings → Legal documents** to configure metadata for:

- Personal Data Processing Consent
- Privacy Policy
- Marketing / Information Messages Consent

For each document you can store title, URL, link text, version and effective date.

Document URLs are optional. BfCamel CRM continues rendering and accepting consent fields without URLs; in that case the evidence snapshot records that the documents were not fully configured. The settings page provides an administrator acknowledgement for operating without document links.

BfCamel CRM does not determine whether your wording or legal basis satisfies the law in your jurisdiction. Site owners remain responsible for consent text, lawful processing and retention periods.

## Technical metadata settings

Under **Settings → Privacy**, administrators can independently control:

- IP address storage — disabled by default
- Source page URL storage — enabled by default on a fresh installation
- Browser/User-Agent storage — enabled by default on a fresh installation

These settings affect new submissions and form-originated consent evidence. Disabling a setting does not retroactively erase previously stored data.

## WordPress Personal Data Exporter

BfCamel CRM registers a WordPress personal-data exporter keyed by email address. Exported data can include matching contacts, related submissions, internal notes, consent history and activity metadata.

Export is processed in batches of 50 records for the paged data sets.

## WordPress Personal Data Eraser

BfCamel CRM also registers a personal-data eraser. It anonymizes identifiers and submission contents and removes internal notes and technical metadata for matching contact data. Anonymized consent timestamps and document evidence are retained for audit purposes, and WordPress is told that some anonymized items remain.

Erasure is transaction-aware and processed in batches where necessary.

## WordPress Privacy Policy helper

The plugin adds suggested privacy-policy text through WordPress's privacy-policy content API. The text explains that BfCamel CRM may store form data, contacts, notes, consent events, source URLs and browser information, while IP storage is optional and disabled by default.

## Uninstall and data retention

CRM data is retained on uninstall unless the administrator explicitly enables **Delete all BfCamel CRM data when the plugin is uninstalled**. See [Installation and Updates](Installation-and-Updates.md).
