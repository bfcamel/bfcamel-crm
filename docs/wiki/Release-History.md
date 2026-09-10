# Release History

This page summarizes the documented public changes that are relevant to users and administrators. For the repository-level changelog, see `CHANGELOG.md`.

## 0.5.1

Version 0.5.1 is an interface-polish release built on the 0.5.0 architecture.

Changes include:

- Renamed the ambiguous workflow menu entry to **Workflow**.
- Standardized the Russian interface term for submissions as **«Заявки»**.
- Made the dashboard **Add contact** and **Add form** actions consistent in size and interaction style.
- Removed the colored left inset from status badges while keeping the compact pill presentation.

## 0.5.0

Version 0.5.0 focused on security, privacy, consent reliability, administration and WordPress.org-ready distribution.

Important changes include:

- Least-privilege role defaults: only administrators receive CRM access by default.
- Migration of unsafe legacy all-access role matrices.
- Reworked WordPress personal-data export and erasure with batching, consent history and activity metadata.
- Independent privacy controls for IP address, source URL and browser/User-Agent storage.
- Identifier locking to reduce duplicate-contact creation during concurrent submissions.
- Legal-document URLs made optional for consent fields.
- Consent evidence expanded to include displayed field text and document-configuration state.
- Administrator acknowledgement for operation without legal-document links.
- Public form rendering and submission blocked while the database schema is incomplete.
- Consolidated administration styling and JavaScript, responsive layouts and accessibility improvements.
- Nonce-protected form archive/restore and tag-deletion operations.
- Expanded localization coverage.
- WordPress.org-compatible update metadata and directory readme.
- Official WordPress Plugin Check added to the release gate.

## 0.4.1

Version 0.4.1 concentrated on correctness and release reliability.

Highlights include:

- Built-in workflow labels now follow the active WordPress user locale.
- Dashboard and quick-view logic now uses configured status and priority definitions rather than hard-coded assumptions.
- Custom workflow colors and database-safe custom slugs were fixed.
- At least one enabled default status and priority is always retained.
- The one-column form setting now affects frontend rendering.
- Incomplete bulk actions and failed archive/restore/tag operations no longer report false success.
- Form revision publishing, public submission/contact/consent capture and tag-catalog changes use database transactions.
- Release validation expanded across supported PHP versions, smoke tests, duplicate-load regression checks and JavaScript syntax checks.

## 0.4.0

Version 0.4.0 introduced the configurable CRM workflow layer:

- Custom statuses and priorities.
- Priority automation rules based on form fields.
- Shared tag catalog for submissions and contacts.
- CSS-first form layout mode with stable selectors.
- Configurable CRM settings screens.
- Improved duplicate-plugin handling and internal CRM controls.

## Upgrade guidance

Use the official `bfcamel-crm.zip` package for manual in-place upgrades. The plugin identity, table prefix and current compatibility aliases are intentionally preserved so existing installations can update without being installed as a separate plugin.

See [Installation and Updates](Installation-and-Updates.md) for details.
