# Changelog

## 0.5.2

### WordPress Plugin Check compliance
- Normalized and sanitized request data at a single controller boundary, with nonce checks retained in every state-changing handler.
- Replaced interpolated table names with WordPress `%i` identifier placeholders and allowlisted every plugin table name.
- Added precise, documented PHPCS exceptions for intentional direct access to plugin-owned CRM tables and schema operations.
- Added object caching and explicit cache invalidation for forms, immutable form revisions, contacts and the tag catalog.
- Rebuilt frontend return URLs from the trusted WordPress home URL instead of the request host header.
- Removed manual translation loading in favor of WordPress just-in-time text-domain loading.
- Scoped uninstall variables inside a prefixed function and kept database removal opt-in and allowlisted.
- Made WordPress Plugin Check warnings fail the release gate.

### Included interface fixes
- Includes the 0.5.1 terminology, dashboard-button and status-badge refinements.

## 0.5.1

### Interface polish
- Renamed the ambiguous workflow menu item from “Configuration” to “Workflow”.
- Standardized the Russian interface term for submissions as “Заявки”.
- Made the dashboard “Add contact” and “Add form” actions equal in size and interaction style.
- Removed the colored left inset from status badges while preserving their compact pill shape.

## 0.5.0

### Security and privacy
- Changed role defaults to least privilege and migrated unsafe legacy all-access matrices.
- Rebuilt personal-data export and erasure with batches, consent history, activity metadata and failure-aware transactions.
- Added independent controls for IP, source URL and browser-information storage.
- Added an advisory identifier lock to prevent duplicate contacts during concurrent submissions.

### Consent and forms
- Legal-document links are now optional and never hide consent fields or block data collection.
- Consent evidence includes the displayed field text and whether documents were configured.
- Added explicit administrator acknowledgement for operation without legal-document links.
- Blocked form rendering and submission while the database schema is incomplete.

### Administration
- Consolidated the legacy layered CSS and JavaScript assets.
- Added a responsive BfCamel CRM design system, improved empty states and accessible focus styles.
- Converted tag deletion and form archive/restore actions to nonce-protected POST requests.
- Completed localization of the new PHP and JavaScript interface strings.

### Distribution
- Removed the GitHub Update URI so WordPress.org can provide updates.
- Expanded `readme.txt` for the plugin directory and cleaned the installable package.
- Added the official WordPress Plugin Check action to the release gate.
- Gated release publication on successful validation, Plugin Check and package jobs for the merged `main` commit.

## 0.4.1

### Fixed
- Restored a green smoke-test path by loading `WorkflowService` from its canonical PSR-4-style location and removing the misplaced bridge file.
- Made built-in workflow status and priority names follow the active WordPress user locale instead of the locale used during installation.
- Made dashboard cards, submission quick views and fallbacks use configured status and priority slugs; review links no longer duplicate the default status or point to a disabled value.
- Applied configured workflow colors to admin badges and constrained custom slugs to their database column sizes.
- Guaranteed at least one enabled default status and priority.
- Applied the form editor's one-column setting and removed the hard-coded English CSS-first label.
- Preserved disabled historical status and priority values when an existing submission is edited.
- Rejected incomplete bulk updates instead of reporting them as successful.
- Checked archive, restore and tag deletion failures instead of silently redirecting.

### Reliability
- Wrapped form revision publishing, public submission/contact/consent capture and tag catalog changes in database transactions.
- Made contact synchronization report identifier, custom-field and activity-log write failures.
- Gated release packaging and publication on PHP 7.4/8.1/8.3 syntax checks, smoke tests, duplicate-load regression checks and JavaScript syntax checks.
- Added changelog-backed GitHub release notes alongside the two installable ZIP assets.

## 0.4.0

### Added
- Custom CRM workflow engine.
- Configurable submission statuses and priorities.
- Priority automation rules based on form fields.
- Shared tag catalog for submissions and contacts.
- CSS-first form layout mode with stable selectors.
- Configurable CRM settings screens.

### Improved
- Better control over form styling and page-builder integration.
- Safer handling of duplicate plugin installations.
- Expanded internal CRM management capabilities.

### Notes
- This release continues the migration from the initial form storage implementation into a configurable CRM engine.
