# FAQ Block

**Status:** v1.9 · issue #758
**Block name:** `artisanpack/faq`

The FAQ block persists a list of question / answer pairs and — when the Blade renderer runs — emits a `FAQPage` JSON-LD script alongside the visual markup so Google can surface the page in FAQ rich results.

The React and Vue renderers ship the same visual DOM but skip the JSON-LD script so downstream head managers can stay in charge of schema in headless environments.

---

## Attributes

| Attribute       | Type                                | Notes |
|-----------------|-------------------------------------|-------|
| `items`         | `array<{question: string, answer: string}>` | Ordered FAQ entries. Entries missing both a question and an answer are dropped at render time. |
| `headingLevel`  | `number` (2–6, default `3`)         | Heading level used for each question. Fractional / out-of-range values are clamped to match the React/Vue renderers. |
| `emitSchema`    | `boolean` (default `true`)          | When `false` the Blade renderer skips the `FAQPage` JSON-LD script. Turn it off on pages that surface FAQ schema through a page-level schema block or head manager. |

## Structured data

The Blade renderer emits a single `<script type="application/ld+json">` per rendered FAQ block. Question and answer text is stripped to plain text before being written into the payload (block wrappers, inline HTML, and entity-encoded `</script>` sequences are all normalised) so Google Search Console does not flag the markup as noise.

## Related

- **Accordions FAQ toggle** (#757) — the parent `artisanpack/accordions` block gained a `faqSchema` toggle in this same release; use it when FAQ content is already authored as an accordion.
- **HowTo block** (#759) — for step-by-step content that should surface as a `HowTo` rich result.
