# HowTo Block

**Status:** v1.9 · issue #759
**Block name:** `artisanpack/howto`

The HowTo block persists an ordered list of steps (each with a name, text, optional image + alt) plus a block-level name and description. The Blade renderer emits a `HowTo` JSON-LD script at request time so Google can surface the page in HowTo rich results.

React and Vue renderers ship the same visual DOM and skip the JSON-LD script so downstream head managers can stay in charge of schema in those environments.

---

## Attributes

| Attribute       | Type                                                  | Notes |
|-----------------|-------------------------------------------------------|-------|
| `name`          | `string`                                              | HowTo title. Falls back to the first step's name when blank. |
| `description`   | `string`                                              | Optional short description. |
| `steps`         | `array<{name, text, imageUrl?, imageAlt?}>`           | Ordered steps. Fully-empty entries are skipped. |
| `headingLevel`  | `number` (2–6, default `3`)                           | Heading level for each step name. |
| `emitSchema`    | `boolean` (default `true`)                            | When `false` the Blade renderer skips the `HowTo` JSON-LD script. |

## Security

Every step's `imageUrl` is passed through `UrlSanitizer::safe()` before it reaches either the rendered `<img src>` or the `HowToStep.image` field in the JSON-LD payload. Values outside the `http`, `https`, and `mailto` allowlist are dropped — matching the Reviews block's URL handling on this same release cycle.

## Structured data

The Blade renderer emits a single `<script type="application/ld+json">` per rendered HowTo block. Step text is stripped to plain text before being written into the payload; a step with only a name (no body text) still contributes a valid `HowToStep` by falling back to the name as the required `text` field.
