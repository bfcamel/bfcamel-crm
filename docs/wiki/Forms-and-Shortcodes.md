# Forms and Shortcodes

## Form revisions

BfCamel CRM treats the published form schema as a CRM contract. Every save publishes a new immutable form revision. Existing submissions keep the `revision_id` that was active when they were received, so later form edits do not change the historical schema associated with older submissions.

Forms can be archived and restored. Archiving does not physically delete the form because submission history may still refer to it.

## Supported field types

The form builder supports:

- Text
- Email
- Phone
- Number
- Date
- Textarea
- Select
- Radio
- Checkboxes
- Hidden
- Personal data consent
- Marketing consent
- Text / heading (`html` content field)

Each non-content field needs a unique field key. Select, radio and checkbox fields accept a list of allowed options. Field widths can be 100%, 50%, 33% or 25% within the form grid.

## CRM mapping

A field can be stored only in the submission or mapped to CRM contact data. Supported mappings are:

- Submission only
- Contact: name
- Contact: email
- Contact: phone
- Contact: organization
- Contact: custom field

For a custom contact field, provide a custom field key. Mapping lets BfCamel CRM create or update contact data while retaining the original submission payload.

## Consent fields

Two dedicated field types exist for consent collection: personal-data consent and marketing consent. Consent fields are checkbox inputs and may be required or optional.

The default labels support document tokens:

```text
{personal_data_consent}
{privacy_policy}
{marketing_consent}
```

When a corresponding legal-document URL is configured, the token becomes a link. When no URL is configured, the configured or default link text is shown as plain text. Missing document URLs do not hide consent fields or block form submission.

See [Consent and Privacy](Consent-and-Privacy.md) for evidence storage details.

## Appearance settings

Each form revision stores its own appearance and workflow settings. Available appearance settings include:

- Style mode: Theme / unstyled, Default, Custom
- One or two columns
- Primary/focus color
- Text color
- Field background
- Border color
- Button color
- Button text color
- Border radius
- Custom wrapper class
- Submit-button label
- Success message
- Error message

The frontend renderer exposes stable BfCamel CSS classes and CSS custom properties, making the Theme / unstyled mode useful for page builders and custom themes.

## Shortcodes

Preferred shortcode by numeric ID:

```text
[bfcamel_form id="1"]
```

Preferred shortcode by slug:

```text
[bfcamel_form slug="contact-form"]
```

The legacy shortcode is retained as a compatibility alias:

```text
[gfr_form id="1"]
```

New documentation and integrations should use `bfcamel_form`.

## Public submission protection

Public forms include WordPress nonce validation, a honeypot field and a basic per-form rate limiter. The rate limiter uses a short-lived request fingerprint and currently allows a new request after roughly five seconds.

Submitted values are validated against the stored revision schema. Email addresses, numbers and option values receive type-specific validation. Select and radio values must match configured options.

## Success and error handling

After processing, the submission handler redirects back to the source page with form-specific success or error query parameters. The renderer displays the configured message only for the matching form ID.
