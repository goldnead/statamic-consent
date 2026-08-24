# Changelog

## 1.1.0

### What's new

- **Google Consent Mode v2**, off by default. Map each of Google's four signals to the services
  behind it; the `consent default` call is written inline and first, the update follows the
  visitor's decision.
- **Browser tests** for the three behaviours the PHP suite cannot reach: the localStorage mirror,
  Global Privacy Control and parked scripts. `npm test`.

### What's fixed

- Closing the settings panel with **Escape** without deciding left the visitor with no banner and no
  decision. Every way out now behaves the same.
- The CI matrix tested a Laravel 11 leg that Statamic 6 can never satisfy, and `orchestra/testbench`
  was pinned to Laravel 12 only, so the Laravel 13 leg could not resolve either.

## 1.0.0

### What's new

- **The banner is a card in the bottom-left corner and the settings panel sits in the opposite
  corner**, in the shape adriangoldner.com established — not a bar across the page. No font is
  loaded: the banner inherits the site's own faces. Every colour, radius and shadow is a custom
  property.

- **Cookie banner and settings dialog**, with the wording editable in the control panel under
  **Globals → Consent**. Every route out of the banner — accept all, essential only, settings — is
  one click and looks like the others.
- **`{{ consent:gate }}`**, a two-click gate that parks the embed in a `<template>`, so no request
  reaches the third party before the visitor allows it.
- **`{{ consent:granted }}`, `{{ consent:settings_link }}`, `{{ consent:head }}`,
  `{{ consent:banner }}`** — see the README for signatures.
- **Parked scripts** via `<script type="text/plain" data-consent-service="…">`.
- **Global Privacy Control** is honoured: a visitor sending the signal is recorded as having
  rejected, and is not asked again.
- **English and German** translations; a field left empty in the control panel falls back to the
  shipped text in the visitor's language.
- **`php please consent:install`** publishes the assets and blueprint and seeds the global set.
- **Google Consent Mode v2**, off by default. Map each of Google's four signals to the services
  behind it; the `consent default` call is written inline and first, the update follows the
  visitor's decision.
