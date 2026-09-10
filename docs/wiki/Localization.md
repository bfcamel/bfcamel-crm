# Localization

BfCamel CRM uses the WordPress text domain `bfcamel-crm`.

## Bundled languages

English is the source language. Version 0.5.1 bundles Russian (`ru_RU`) translation files:

- `languages/bfcamel-crm-ru_RU.po`
- `languages/bfcamel-crm-ru_RU.mo`
- `languages/bfcamel-crm.pot`

The plugin loads translations from its `languages/` directory on WordPress `init`.

## User locale

Administration strings use standard WordPress internationalization functions. Built-in workflow status and priority labels are translated according to the active WordPress user locale instead of being permanently stored in the language that was active when the plugin was installed.

This is important on sites where administrators use different dashboard languages.

## Built-in workflow labels

The stored built-in workflow definitions retain canonical English source names and known built-in slugs. At display time, BfCamel CRM resolves these names through translation functions when the row is recognized as a built-in definition.

Custom status, priority and tag names are administrator-provided content and are not automatically translated.

## Translation files and releases

The release process treats localization as part of the installable package. Keep the POT/PO/MO files synchronized when adding or changing user-facing strings.

The project README for 0.5.1 states that release builds verify translation completeness and semantic consistency between the committed MO catalog and a freshly compiled catalog.

## Adding another language

A translation should follow normal WordPress plugin naming conventions for the `bfcamel-crm` text domain. The plugin's bundled loader points to the `languages/` directory relative to the canonical plugin directory.

When contributing a translation, update the source catalog first, translate from the current strings, compile the MO file and test the administration area and public form output under that locale.

## Do not translate technical identifiers

Do not localize these identifiers:

- Plugin directory `bfcamel-crm/`
- Main file `bfcamel-crm.php`
- Text domain `bfcamel-crm`
- PHP namespace `BfCamel\\CRM`
- Shortcode `bfcamel_form`
- Capability, action and option names beginning with `bfcamel_crm_`
- Built-in workflow slugs such as `new`, `in_progress` and `urgent`

Only their human-readable labels should be translated.
