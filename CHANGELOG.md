# Changelog

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
