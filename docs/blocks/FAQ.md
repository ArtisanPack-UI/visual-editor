# FAQ Block

**Status:** v1.9 · issue #758
**Block name:** `artisanpack/faq`

The FAQ block persists a list of question / answer pairs and — when the Blade renderer runs — adds them to the page's `FAQPage` JSON-LD script so Google can surface the page in FAQ rich results.

The React and Vue renderers ship the same visual DOM but skip the JSON-LD script so downstream head managers can stay in charge of schema in headless environments.

---

## Attributes

| Attribute       | Type                                | Notes |
|-----------------|-------------------------------------|-------|
| `items`         | `array<{question: string, answer: string}>` | Ordered FAQ entries. Entries missing both a question and an answer are dropped at render time. |
| `headingLevel`  | `number` (2–6, default `3`)         | Heading level used for each question. Fractional / out-of-range values are clamped to match the React/Vue renderers. |
| `emitSchema`    | `boolean` (default `true`)          | When `false` the Blade renderer skips the `FAQPage` JSON-LD script. Turn it off on pages that surface FAQ schema through a page-level schema block or head manager. |

## Structured data

*Since v1.19.0 (#1034).* Google expects one `FAQPage` per page, so FAQ blocks queue their questions while rendering, and the Blade renderer prints one merged `<script type="application/ld+json">` after the block tree. The Accordions block's FAQ toggle joins the same script, and a question repeated word for word is listed once. Separate `<x-ve-blocks>` renders on one page each print their own script, so render a page's FAQs in one tree. Calling `BlockRenderer::renderBlock()` directly prints the script with that block.

Question and answer text is stripped to plain text before being written into the payload (block wrappers, inline HTML, and entity-encoded `</script>` sequences are all normalised) so Google Search Console does not flag the markup as noise.

## Related

- **Accordions FAQ toggle** (#757) — the parent `artisanpack/accordions` block gained a `faqSchema` toggle in this same release; use it when FAQ content is already authored as an accordion.
- **HowTo block** (#759) — for step-by-step content that should surface as a `HowTo` rich result.
