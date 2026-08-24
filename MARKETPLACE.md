# Statamic Consent — Marketplace

## Price

**$20, one edition.** Decided by Adrian on 2026-08-24.

Not a suggestion and not a range: this addon is one product. There is no Core/Pro split, so
`extra.statamic.editions` stays absent from `composer.json` — the field only earns its place when a
feature actually sits behind a tier, and inventing one here would be decoration.

### Where that sits in the market

From the comparison of 2026-08-24 (`TASKS/statamic-consent-marktvergleich-2026-08-24.md`):

| Addon | Price | Blocks embeds | Wording in the control panel |
|---|---|---|---|
| **Statamic Consent** | **$20** | yes, via `<template>` | **yes** |
| Consent Manager (kiwikiwi) | $49 | yes, placeholder | no — language files |
| Cookie Notice (duncanmcclean) | $49 | no, an example to build yourself | no |
| Cookie Byte (dryven) | $19 | covers only, **no Statamic 6** | yes |
| Cookie Dialog (emplify) | free / $20 | not documented | yes |

**$20 is under half of both $49 competitors**, and this is the only addon at any price that both
keeps an embed out of the document *and* lets the site owner edit the wording without touching a
language file.

The one thing the $49 leader has and this does not is a scanner that finds services for you. Google
Consent Mode v2 is no longer a difference — it shipped in 1.1.0.

## What it is

Cookie consent banner and two-click embed gate for Statamic 6, editable in the control panel.

A blocked embed is **absent from the document**, not hidden with CSS: it waits inside a `<template>`,
which browsers parse without requesting anything inside it. That is the difference between looking
compliant and being it, and it is measurable — zero requests to the third party before the click,
fifteen after.

## Who it is for

- German and EU sites that need the two-click rule to actually hold, not merely appear to.
- Agencies running several client sites: the wording lives in a global set the client edits, the
  handles live in the config the developer controls, and neither can break the other.
- Anyone who does not want a consent tool that is itself a third-party script phoning home.

## Not for

- Sites wanting automatic discovery of what they load. Every service is entered by hand.
- IAB TCF / programmatic advertising.
- A hosted dashboard across many sites.

## Categories

Privacy · GDPR · Utility · Frontend

## Requirements

Statamic 6 · PHP 8.2+ · no queue, no database, no build step.

*(A database is needed only for the optional proof-of-consent log.)*

## Screenshots

`docs/` — control panel (light and dark), the banner, the settings panel, the blocked embed, and a
narrow screen.
