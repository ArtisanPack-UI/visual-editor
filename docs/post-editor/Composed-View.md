# Composed view

The composed view previews an author's content *inside the template it
will actually render in* — header, footer, and all — without leaving the
post editor. Content stays fully editable; the surrounding template
chrome is a read-only preview.

It answers the question the bare canvas can't: "does this heading still
work under the site header, and does the page end sensibly above the
footer?" Before it, the only way to find out was to open Preview in
another tab and lose the editing context.

---

## 1. When to use it

Use it when the *shape* of the page matters:

- Checking a hero or opening heading against the real site header.
- Judging spacing and rhythm at the top and bottom of the content.
- Confirming which template a piece of content actually resolved to.

Keep it off for ordinary writing. The chrome is extra rendering on every
canvas update, and the content area is narrower on small screens because
the template's layout constraints apply to it.

The composed view is **not** pixel-parity with the front end. Template
wrappers that contain the content slot are split in two around it (see
§3), which the front end does not do. Use **Preview** when you need the
real thing.

---

## 2. Toggling it

The topbar carries a switch beside the save status, labelled **Content
only** / **With template** (`role="switch"`, accessible name *View with
template*).

- Every editor mount starts in **Content only**. The view mode is
  ephemeral — it is not persisted per user or per document.
- The first flip to **With template** issues
  `GET /visual-editor/api/{resource}/{id}/applied-template`, passing the
  template currently selected in the document panel rather than the one
  last saved. The switch stays enabled while that request is in flight,
  so flipping back to **Content only** always works — a request that
  never settles can never strand the author in composed view.
- Results are cached per `(resource, id, template)` for the lifetime of
  the editor mount, so later flips are instant. Picking a different
  template in the document panel invalidates that cache and re-resolves.

Toggling is a presentation change only. It records no undo entry, marks
nothing dirty, and never touches the block tree — selection, undo history
and unsaved changes are all exactly where you left them when you flip
back.

---

## 3. What is editable, and what is not

| Region | State |
|--------|-------|
| Post title | Editable — the canvas title field, as in bare-content mode |
| Content blocks | Editable — the live block canvas, unchanged |
| Template header chrome | Read-only preview |
| Template footer chrome | Read-only preview |
| Ribbon at the top of the canvas | Not content — editor chrome (see §5) |

The template's blocks render *above and below* the block canvas, inside
the same iframe, so they pick up the theme's compiled CSS exactly as the
content does.

**Why a preview rather than a locked editable.** The chrome is rendered
through Gutenberg's block-preview provider, which mounts its own isolated
block-editor store and wraps the subtree in `useDisabled()`. That is what
makes it genuinely non-selectable and non-editable — not a `lock`
attribute, which only asks the UI nicely and still leaves an editable
surface with a live selection. The isolation matters for a second reason:
the content editor's own block tree is never swapped when you toggle, so
there is no state to lose and no feedback loop between the two trees.

The practical consequence: clicking the site header in the composed view
does nothing at all. That is intended, and the standing notice above the
canvas says so — *Only your content is editable here — the surrounding
template areas are edited in the site editor.* To change the chrome, use
the ribbon CTA (§5).

**Where the split happens.** The template is cut at its
`post-content` slot: everything before becomes header chrome, everything
after becomes footer chrome. When the slot is nested inside a layout
wrapper, the wrapper is cloned onto both sides so the surrounding layout
still reads correctly. If a template declares no content slot at all, the
whole template renders as header chrome and a warning notice says so —
*The template "…" has no content area, so your content is shown below the
whole template.*

**Bindings resolve against the current content.** `post-title`,
`post-author`, `post-date` and `post-featured-image` blocks inside the
template chrome resolve their bound values against the post being edited,
not against the template's sample data. The chrome renders inside the
same entity block context as the content, so it shows this post's title
and this post's author.

---

## 4. The fallback template

The composed view always has something to show. When no real template
resolves, it composes against a built-in **Default template** — a minimal
tree of post title, featured image, content slot, in that order.

It kicks in for three cases:

| Case | Notice tone | Copy |
|------|-------------|------|
| Content has no template selected | warning | *No template is set for this content — previewing on the default template.* |
| Selected slug resolves to nothing | warning | *The template "{slug}" is unavailable — previewing on the default template.* |
| The request failed outright | error | *The template could not be loaded — previewing on the default template.* |

Each is announced twice, deliberately: once as a toast when the toggle is
flipped on, and once as a standing notice above the canvas that is still
there a minute later when the toast has auto-dismissed.

The default template is a client-side constant, not a database record.
It is not editable, does not appear in the site editor's template list,
and has no slug — which is why the ribbon's **Edit template ↗** CTA is
hidden while composing against it.

---

## 5. The ribbon and the Edit template deep link

A sticky ribbon leads the composed canvas: *Editing content inside
{template name}*, plus a single **Edit template ↗** link.

It sits *inside* the canvas iframe so it scrolls with the preview and
reads as a property of the page rather than another editor toolbar.
Individual template parts get no per-part badge — one statement at the
top, not five competing controls on a surface whose whole point is to
read like the finished page.

The CTA opens the site editor in a **new tab**, at a query-string deep
link:

```text
/visual-editor/site?entity=template&slug=single
```

New tab on purpose: the post editor may be holding unsaved content, and
navigating away from it to edit template chrome would be a data-loss
trap.

The site editor parses that query string on mount, resolves the slug, and
lands on that template's editor view — rewriting the address bar to the
canonical path route as it goes. The full contract, including the
`entity_id` escape hatch and the unresolvable-slug behaviour, is in
[Site editor §5](../site-editor.md#deep-links-by-slug).

Build these links with `buildTemplateDeepLink()` from
`site-editor/deep-link.ts` rather than assembling the query string by
hand. It defaults to the package's own mount path; a host that mounts the
site editor elsewhere will get a CTA pointing at the default path until
that route base is threaded through to the post editor.

---

## 6. Endpoint

```http
GET /visual-editor/api/{resource}/{id}/applied-template?template={slug}
```

Returns the resolved template and its referenced template parts, or a
discriminated miss the client turns into the §4 fallback:

```jsonc
// hit
{ "status": "ok", "slug": "single", "name": "Single Post", "source": "db",
  "blocks": [ /* … */ ], "template_parts": { "header": { /* … */ } } }

// miss
{ "status": "missing", "reason": "empty" }
{ "status": "missing", "reason": "unknown-slug", "slug": "does-not-exist" }
```

The `template` query parameter overrides the slug persisted on the model,
so a preview taken right after a template change does not race the
debounced save. A blank `?template=` is meaningful: it says the selection
was *cleared*, and is answered with `reason: "empty"`.

---

## See also

- [Post editor](../post-editor.md) — the surrounding surface
- [Site editor §5](../site-editor.md#5-url-routing) — deep-link contract
- [Templates](../site-editor/Templates.md) — hierarchy, fallback chain, template parts
