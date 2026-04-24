# Site Editor — UX Research & Macro Design Brief

**Package:** `artisanpack-ui/visual-editor`
**Issue:** [#367 — D0 · Site-editor UX research + macro design brief](https://github.com/ArtisanPack-UI/visual-editor/issues/367)
**Phase:** D0 (gates D1–D5)
**Status:** Draft for review
**Owner:** Jacob Martella
**Created:** 2026-04-23

---

## 0. Why this doc exists

The V1 expansion plan ([`docs/plans/11-v1-expansion.md`](../plans/11-v1-expansion.md) §2.2) commits to building the
site-editor shell ourselves rather than depending on `@wordpress/edit-site`, "to preserve the #309 premise of owning
admin chrome." That ownership is only valuable if we don't clone WordPress's IA by default. D0 is where we earn the
decision.

This is a **research + design** task. It produces a macro-level brief that D1–D5 implement against. It does **not**
produce pixel-level screen designs, Figma libraries, or final copy — those live inside each D-phase PR, reviewed against
the principles and flows documented here.

**What this brief covers:**

1. Research: catalogued WordPress Site Editor friction patterns, tagged structural vs polish.
2. Research: competitive scan of four non-WP tools (Webflow, Framer, Craft CMS, Squarespace Fluid Engine).
3. Design brief: information architecture for each of the five site-editor sections.
4. Design brief: mode model — how users move between scopes and what "Save" means on every screen.
5. Design brief: storyboard-level flows for five core scenarios.
6. UX principles — the rules D1–D5 must be reviewed against.

**What this brief does not cover:**

- Pixel-level UI design, Figma components, final copy, or screen-by-screen mockups.
- Post-editor UX (shipped in Phase A).
- Frontend rendering UX (Phase E).
- Accessibility audit of specific controls (happens inside each D issue).

---

## 1. Research: WordPress Site Editor pain points

WordPress's Site Editor is the best single reference for the site-editing problem space, and also the best source of
documented user friction. The catalogue below is compiled from Gutenberg GitHub issues, WP Tavern, Make WordPress,
community blog posts (Brian Coords, WordPress.com support), and the FSE Outreach Program testing summaries.

Each item is tagged:

- **Structural** — IA / entity model / mode-switching / discoverability. V1 must address.
- **Polish** — copy, empty states, command palette ranking, visibility bugs. Defer to 1.1 unless cheap.

### 1.1 Structural frictions (V1 must address)

#### F1. Entity confusion: local content vs. global scope

Users repeatedly add what they think is "page content" into template parts, or destroy template blocks thinking they
were editing a page. Gutenberg contributors acknowledge that "since the start of the Site Editor, a lack of distinction
between editing templates vs content has led to confusion and incorrect actions on the part of users." The current
toolbar-color-change cue is not enough.

Sources:
- [Ensuring local content is not added at the global scope, and vice versa (gutenberg#55025)](https://github.com/WordPress/gutenberg/issues/55025)
- [Site Editing: Saving drafts and switching between template & post (gutenberg#27905)](https://github.com/WordPress/gutenberg/issues/27905)
- [Fix content added to a template instead of a page — WordPress.com Support](https://wordpress.com/support/templates/template-vs-page/)

#### F2. Post-editor vs. site-editor duplication ("The Great Unification")

WordPress shipped two editors with heavily overlapping capabilities — users can edit templates in either, and edit
content in either. The editor's own design lead wrote: "When should I use the post editor vs FSE editor? You can modify
content (e.g. home) in both editors, you can also modify templates in both editors. This is creating confusion and a
hazy idea of how things work." Unification is tracked as a multi-year effort.

Sources:
- [The great unification (gutenberg#41717)](https://github.com/WordPress/gutenberg/issues/41717)
- [Full Site Editing Breakdown (gutenberg#20791)](https://github.com/WordPress/gutenberg/issues/20791)

#### F3. Save-button scope ambiguity (multi-entity save)

Pressing Save surfaces a checklist of entities (template, template parts, post, global styles, site options) the user
didn't realise they had modified. Single ACF block edits have been documented firing the multi-entity save flow and
double-submitting. FSE Outreach consistently flags this: "the main sticking points came when trying to dismiss changes,
save changes as a draft, and understanding what each 'sub' item to save meant."

Sources:
- [Editing Post Meta triggers multi-entity save flow (gutenberg#63355)](https://github.com/WordPress/gutenberg/issues/63355)
- [Post Editor: Revert "Save" button to "Update," at least temporarily (gutenberg#63387)](https://github.com/WordPress/gutenberg/issues/63387)
- [How/if to improve save workflows (gutenberg#63425)](https://github.com/WordPress/gutenberg/issues/63425)
- [High Level Feedback from the FSE Outreach Program (May 2022)](https://make.wordpress.org/core/2022/05/31/high-level-feedback-from-the-fse-outreach-program-may-2022/)

#### F4. Navigation block as a one-block black hole

Brian Coords has documented that the Navigation block abandons traditional menu locations (Primary / Secondary /
Footer), converts `wp_nav_menu` classic menus into an opaque `wp_navigation` post whose `post_id` gets baked into
template parts, and cannot be version-controlled by themes. A Gutenberg meta-issue calls the block "too convoluted for
the most common flows (just a few pages with one or two nested menus)."

Sources:
- [FSE Navigation Block Woes — Brian Coords](https://www.briancoords.com/fse-navigation-block-woes/)
- [A Lighter Navigation Block Experience (gutenberg#34041)](https://github.com/WordPress/gutenberg/issues/34041)
- [WordPress Community Attributes Declining Market Share to… Complexity — WP Tavern](https://wptavern.com/wordpress-community-attributes-declining-market-share-to-performance-issues-increased-complexity-and-the-lagging-full-site-editing-project)

#### F5. Navigation settings/styles unreachable from the Nav context

Clicking "Navigation" in the site-editor sidebar shows a nearly blank screen with the menu tucked in a corner and no
way to reach the block's Settings or Styles tabs. The only working path is: Site Editor → Template → Header → Edit
Original → Menu → tabs.

Sources:
- [Site Editor - Navigation: The journey of finding Settings and Styles (gutenberg#76419)](https://github.com/WordPress/gutenberg/issues/76419)
- [Cannot edit navigation settings or styles when editing navigation specifically (gutenberg#69101)](https://github.com/WordPress/gutenberg/issues/69101)

#### F6. Synced/unsynced pattern mental model and the "Detach" trap

The 6.3 rename from "Reusable Blocks" to "Patterns (synced/unsynced)" broke existing mental models. Core committers
acknowledge "a large combination of extremely generic words: 'block,' 'pattern,' 'sync,' 'partial sync,' and 'content.'"
The "Detach" action was renamed to "Disconnect pattern" because users didn't understand it silently dropped their
override data.

Sources:
- [Reusable Blocks Renamed to Patterns — WP Tavern](https://wptavern.com/reusable-blocks-renamed-to-patterns-with-synced-and-non-synced-options)
- [Update synced pattern language from "Detach" to "Disconnect pattern" (gutenberg PR #73105)](https://github.com/WordPress/gutenberg/pull/73105)
- [Update "detach" language for navigation block and synced patterns flow (gutenberg#54625)](https://github.com/WordPress/gutenberg/issues/54625)
- [Patterns: Consider a more streamlined way to convert sync status (gutenberg#53320)](https://github.com/WordPress/gutenberg/issues/53320)

#### F7. Template hierarchy is invisible to users

The fallback chain (`single → singular → index`) deciding which template renders a URL is never surfaced in the UI. A
new Search template shows an empty canvas instead of the expected fallback content, and there's no way to answer "which
pages currently use this template?" Core's own summary: "the mental model has been that these themes provide
interconnected entities (templates, styles, patterns) that the user can edit in the UI. This mental model has proven
challenging."

Sources:
- [Edit Site: Display current theme template and template hierarchy selection (gutenberg#19252)](https://github.com/WordPress/gutenberg/issues/19252)
- [Block Template creation uses empty content instead of appropriate fallback (gutenberg#36648)](https://github.com/WordPress/gutenberg/issues/36648)
- [Block Themes and site editor: Refactoring templates (gutenberg#66950)](https://github.com/WordPress/gutenberg/issues/66950)

#### F8. Template-part swap / detach / re-attach friction

Replacing one template part with another is inconsistent — the "Replace" button disappeared from custom template-part
areas between WP 6.0 and 6.1 and is still patchy. Swapping headers presents all parts flat instead of suggesting other
headers first. Importing classic widgets into a footer silently created a parallel "Widget area: footer" and deleted the
original. Theme switching leaves orphan customizations rendering indefinitely.

Sources:
- [Add support for replacing "general" template parts (gutenberg#44689)](https://github.com/WordPress/gutenberg/issues/44689)
- [Remove coupling of templates, parts, and patterns when switching themes (gutenberg#25071)](https://github.com/WordPress/gutenberg/issues/25071)
- [Improve importing widgets from classic to block theme UX (gutenberg#47291)](https://github.com/WordPress/gutenberg/issues/47291)
- [Explore a pattern for template part editing that is consistent in both content and template editing contexts (gutenberg#27852)](https://github.com/WordPress/gutenberg/issues/27852)

#### F9. Zoom-out is a mode without signage

Zoom-out was shipped as "a view" but is actually a distinct editing mode (patterns become top-level selectable, inner
blocks become unselectable, device preview stops working). "There's nothing in the editor UI that tells users what this
'mode' is about. It only says 'Zoom Out.' It doesn't mention nor explain in any way it's about editing patterns."

Sources:
- [Zoom out (gutenberg#50739)](https://github.com/WordPress/gutenberg/issues/50739)
- [Merge Zoom Out and "Edit" (Select) Modes (gutenberg#65736)](https://github.com/WordPress/gutenberg/issues/65736)
- [Group Write, Design, and Zoom out modes (gutenberg#65856)](https://github.com/WordPress/gutenberg/issues/65856)
- [Device preview doesn't work when zoom out view is enabled (gutenberg#65411)](https://github.com/WordPress/gutenberg/issues/65411)

#### F10. List View / Navigator — shallow vs. deep tree collapse

List View is simultaneously the most-loved and most-frustrating site-editor feature. Deep trees exceed the viewport,
auto-scroll loses the selected item, drop indicators disappear over the active row, many standard keyboard affordances
(Cmd+A, duplicate, PgUp/PgDn, insert-before/after) don't exist. Nav Link blocks selected in List View don't surface
their own inspector controls.

Sources:
- [List View: Tweaks to improve usability and keyboard behaviour (gutenberg#49563)](https://github.com/WordPress/gutenberg/issues/49563)
- [List View Design Updates (gutenberg#24029)](https://github.com/WordPress/gutenberg/issues/24029)
- [Enable sidebar inspector controls for Navigation Link blocks selected in List View (gutenberg#74895)](https://github.com/WordPress/gutenberg/issues/74895)
- [List View: Explore Naming Blocks (gutenberg#33583)](https://github.com/WordPress/gutenberg/issues/33583)

#### F11. Global Styles vs. per-block styles vs. Style Book confusion

Users can't predict where a setting lives: local block inspector? Block-type default in Global Styles → Blocks → Button?
A global element (links, headings)? A style variation? Theme.json? The core Styles hierarchy issue explicitly asks to
surface the hierarchy visually because today "we need to show a hierarchy of styles clearly (theme, user, global
unspecific, global specific, local, etc)." The Style Book opens from Styles but drops users into a context where
blocks sometimes aren't clickable and tabs disappear.

Sources:
- [Styles: show a hierarchy of styles clearly (gutenberg#49278)](https://github.com/WordPress/gutenberg/issues/49278)
- [Explore improvements to Styles panel in site editor (gutenberg#53483)](https://github.com/WordPress/gutenberg/issues/53483)
- [Style Book: When navigating from the sidebar, there are no tabs and blocks cannot be clicked (gutenberg#51887)](https://github.com/WordPress/gutenberg/issues/51887)
- [The Global Styles Interface (gutenberg#34574)](https://github.com/WordPress/gutenberg/issues/34574)

#### F12. Accessibility regressions: focus management, iframe, keyboard nav

The 2019 WPCampus audit found "significant and pervasive accessibility problems" and the Accessibility Team has
characterized the editor as an "accessibility regression." Dynamic block re-renders dump keyboard focus in unpredictable
places; Up/Down arrow can silently move focus into a different block; the Navigation Editor / 6.3 nav-block rework
still uses "hacks" to disable block features it can't support. The iframe canvas makes screen-reader virtual-cursor and
tab-order flows brittle.

Sources:
- [Gutenberg 6.3 Improves Accessibility with New Navigation and Edit Modes — WP Tavern](https://wptavern.com/gutenberg-6-3-improves-accessibility-with-new-navigation-and-edit-modes)
- [Gutenberg Accessibility Testing — Make WordPress Accessible](https://make.wordpress.org/accessibility/gutenberg-testing/)
- [Post editing area: keyboard accessibility, tab order and focus (Trac #29838)](https://core.trac.wordpress.org/ticket/29838)
- [Provide a better way to disable/enable nav block features for the nav editor (gutenberg#30007)](https://github.com/WordPress/gutenberg/issues/30007)

#### F13. Control dispersion: toolbar vs. inspector vs. sidebar groups vs. three-dot menu

The FSE Outreach May 2022 summary quoted testers bluntly: "Why are some settings in the block toolbar and some in the
inspector?" The 6.2 tab split (Settings / Appearance / List View) helped, but users still can't predict where a given
control lives. "Options are all over the place, on the block, on hover, on vertical-dots submenus, and in the
properties sidebar."

Sources:
- [High Level Feedback from the FSE Outreach Program (May 2022)](https://make.wordpress.org/core/2022/05/31/high-level-feedback-from-the-fse-outreach-program-may-2022/)
- [Introduction of Block Inspector Tabs — Make WordPress Core](https://make.wordpress.org/core/2023/03/07/introduction-of-block-inspector-tabs/)

### 1.2 Polish frictions (defer to 1.1 unless cheap)

#### F14. Empty template / empty post-content placeholder blends in

The Post Content placeholder and empty templates are visually indistinguishable from intentional blank space. Users
delete them by accident or fail to notice where to start typing.

Sources:
- [Ensuring local content is not added at the global scope (gutenberg#55025)](https://github.com/WordPress/gutenberg/issues/55025)
- [FSE Program Template Editing Testing Summary](https://make.wordpress.org/test/2021/01/15/fse-program-template-editing-testing-summary/)

#### F15. Command Palette ranking and scope

Results are undifferentiated (typing "a" returns "add post," "add page," navigation targets, and fuzzy-matched page
titles equally) and the palette only works inside block editors, not across wp-admin.

Sources:
- [Command Palette: Mapping Contextual Commands (gutenberg#50407)](https://github.com/WordPress/gutenberg/issues/50407)
- [Command Palette: enable the command palette to be evoked everywhere (gutenberg#58218)](https://github.com/WordPress/gutenberg/issues/58218)

#### F16. Starter patterns and pattern-inserter visibility regressions

Patterns intermittently fail to show — starter patterns disappeared from new-page creation in 6.6, custom patterns
aren't reachable by Editor role, Pattern Explorer silently renders empty inside the site editor while working in the
post editor. Classifier/permission bugs rather than IA decisions.

Sources:
- [Site Editor: starter patterns not visible when creating a new page (gutenberg#63373)](https://github.com/WordPress/gutenberg/issues/63373)
- [Pattern explorer not loading patterns in the site editor (gutenberg#59984)](https://github.com/WordPress/gutenberg/issues/59984)
- [My patterns > Editor Role > cannot access patterns (gutenberg#66847)](https://github.com/WordPress/gutenberg/issues/66847)

### 1.3 Which frictions gate IA most directly

The structural frictions with the highest up-stream effect on IA are:

- **F1 (entity scope)** and **F3 (save ambiguity)** — gate the mode model.
- **F2 (editor duplication)** — gates the post-editor ↔ site-editor boundary.
- **F7 (template hierarchy)** — gates the Templates section's IA.
- **F10 (navigator depth)** — gates the sidebar pattern across every section.
- **F11 (styles hierarchy)** — gates the Styles section's IA.
- **F4 (navigation block as opaque post)** and **F5 (settings unreachable)** — gate the Nav section's IA.

Nailing these seven defines the brief; F6, F8, F9, F12, F13 ride on top of those decisions.

---

## 2. Research: Competitive scan

Four non-WP tools were scanned along three axes: (1) site-editor vs content-editor distinction, (2) navigation / menu
editor, (3) global styles / design system.

### 2.1 Webflow

Professional visual web design tool. Exposes the CSS box model, class inheritance, flex/grid, and breakpoints as
first-class UI primitives. The most deliberate answer in this scan to "who owns structure vs content."

**Site-editor vs content-editor.** Webflow ships two distinct editors. The **Designer** is a single-user design tool
with the full class/CSS/box-model UI. The **Editor** is a completely different UI — a collapsed gray toolbar at the
bottom of the live published site, accessed by appending `/?edit` (e.g. `yoursite.com/?edit`). In the Editor,
content editors click directly on text, images, and CMS items on the live canvas; design elements are locked. The mode
is differentiated by URL, by chrome, and by concurrency model (Designer is single-user; Editor is multi-user).
Publishing is separate in each.

**Navigation.** No dedicated menu editor. Navigation is a **Navbar component** dragged in from the Add panel and turned
into a **Symbol** (Webflow's component primitive) to reuse across pages. No "menu locations" — a symbol exists once and
gets placed anywhere. Linking to CMS items requires a Collection List bound to a Collection. Editors can edit link text
on the symbol but cannot add items without Designer access.

**Global styles.** **Variables** organised into **Collections** with **Modes** (up to 12 per collection, e.g.
Light/Dark). Cover colors, typography, spacing, dimensions, fonts. Classes reference variables; switching a parent
class's mode re-themes the subtree. Per-element overrides exist, but the convention is to reference variables. Figma
sync is supported.

**Takeaway for ArtisanPack.** The two-editors-two-audiences split is the single biggest lesson. Webflow's Editor is
loved by marketing teams precisely because it refuses to show them class names, box models, or structural controls.
Gutenberg conflates this — the site editor and post editor share 90% of the same UI and same block inserter, which is
why non-technical users bounce off it. However, avoid Webflow's navigation weakness: making users turn a navbar into a
symbol is a power-user move. A first-class menu builder is a differentiator.

Sources:
- [Edit site content as a content editor – Webflow Help Center](https://help.webflow.com/hc/en-us/articles/33961251014931-Edit-site-content-as-a-content-editor)
- [Webflow Editor vs. Designer (Flowout)](https://www.flowout.com/blog/difference-webflow-editor-vs-designer)
- [Variables | The Webflow Way](https://webflow.com/webflow-way/design-systems/variables)
- [Theming with design tokens at Webflow](https://webflow.com/blog/theming-design-tokens)

### 2.2 Framer

Design-first visual site builder, positioned as a Webflow competitor optimized for designers. Distinctive bet:
WYSIWYG-on-the-live-site — no separate "preview" step — content editing happens on the published domain.

**Site-editor vs content-editor.** Modal rather than application-based separation. Designers work on the **canvas**
inside the Framer app. Non-designer teammates with **Content** permission don't open the Framer app — they open the
**published site** in their browser and see an "Edit" button on the right edge (only visible to authenticated
collaborators). Clicking engages **On-Page Editing**: hovering highlights editable zones; clicking opens inline edit
affordances. Layouts and overlays remain locked for content-role users. Save is unified — edits sync in real time back
to the canvas.

**Navigation.** No dedicated menu-builder UI. Navigation is a Frame of link elements turned into a reusable component,
placed on pages or in a site-wide layout. Links can be manual or CMS-bound (via component variants + CMS field
bindings). No "menu locations." Pre-built helpers exist (Previous/Next CMS pagination, auto-generating ToC side nav).

**Global styles.** Two layers: **Styles** (reusable Text Styles and Color Styles that propagate) and **Variables**
(proper design-token system covering colors, typography, spacing, shadows, with mode support). Tokens are surfaced in
side panels with visual swatches and live previews.

**Takeaway for ArtisanPack.** The "edit on the live site" pattern is worth considering for a future content-editor mode
— an Edit button for authenticated admins on the real site URL feels less intimidating than "go to `/wp-admin`." The
two-permission split (Design vs Content) is a clean capability model. But don't copy Framer's menu weakness or its
one-template-per-CMS-collection rigidity.

Sources:
- [Framer Help: On-Page Editing](https://www.framer.com/help/articles/on-page-editing/)
- [Team Permissions | Framer](https://www.framer.com/support/using-framer/permissions/)
- [Design Tokens — Framer Dictionary](https://www.framer.com/dictionary/design-tokens)
- [Framer Developers: Styles](https://www.framer.com/developers/styles)

### 2.3 Craft CMS

Developer-centric, template-driven CMS — the antithesis of a block editor. Structure lives in code (Twig templates) and
a declarative content model (sections, entry types, fields); editors work in a structured admin panel, not a visual
canvas. Included here because its IA around content kinds is the cleanest counter-pattern to "everything is a block."

**Site-editor vs content-editor.** No site editor in the visual sense. Templates are developer-owned Twig files edited
in the IDE, not the browser. The content distinction lives in the model: a **Section** classifies entries as Channels
(syndicated content like posts), Structures (hierarchical trees like docs or page hierarchies), or Singles (one-off
pages like Home, About). Editors work in the Control Panel on entries with dev-defined field layouts. **Live Preview**
runs real Twig and shows a split-screen rendered canvas beside the form; editors never rearrange the page. Craft 5
promoted entry types to a global resource reusable across sections and inside **Matrix** fields (their "content
blocks").

**Navigation.** No built-in menu UI. Canonical solution is the community **Verbb Navigation plugin** — so universally
adopted it's effectively default. It creates a dedicated Navigations section where editors build named menus, drag-sort
nodes into nested trees, and link each node to a typed reference (Entry, Category, Asset, Commerce Product) or a custom
URL. Nodes auto-update when the linked element's title/status changes. A common alternative: use a Structure section
directly as the nav (pages are already a tree, sort them, render the tree). That pattern collapses pages and menu into
one thing.

**Global styles.** None, by design. "Globals" in Craft is a content concept — a named bag of fields accessible from
every template. Developers wire those fields into CSS however they want. Craft's answer to theme.json is "that's a
developer problem, the admin is for content, not design."

**Takeaway for ArtisanPack.** Three lessons. (1) **Channels / Structures / Singles** is a better content-kind taxonomy
than WordPress's post-type-plus-page dichotomy because it makes hierarchy and singletons explicit. ArtisanPack should
consider this distinction in post-type config. (2) The **Verbb data model — named navigations with typed-reference
nodes that auto-sync** — is dramatically better than WordPress's "type the menu label yourself and hope you remember to
update it" model. Worth stealing outright. (3) The anti-pattern: Craft's lack of any theme-token UI means content teams
need developer involvement to change colors/fonts. That's why competitors kill Craft in "marketing team self-service."
Don't go that far.

Sources:
- [Exploring Craft CMS's Section Types — Made By Shape](https://madebyshape.co.uk/web-design-blog/exploring-craft-cmss-section-types-channels-structures-and-singles/)
- [Entries | Craft CMS Documentation 5.x](https://craftcms.com/docs/5.x/reference/element-types/entries.html)
- [Matrix Fields | Craft CMS Documentation 5.x](https://craftcms.com/docs/5.x/reference/field-types/matrix.html)
- [Navigation Features | Verbb](https://verbb.io/craft-plugins/navigation/features)

### 2.4 Squarespace Fluid Engine

Squarespace 7.1's page-editing grid — a 24-column drag-and-drop block canvas with absolute/overlap positioning.
Critically **Fluid Engine is not a site editor**; it's a per-section layout tool inside a traditional admin shell. All
site-wide concerns (styles, navigation, header/footer, templates) live in separated UI regions. That separation is
itself the lesson.

**Site-editor vs content-editor.** Separation is **spatial**, not modal. Users click **Edit** on their site; Fluid
Engine's drag-grid engages inside page sections. Everything else has its own location: **Pages** panel for hierarchy +
navigation ordering, **Design → Site Styles** for global tokens, **Edit Site Header / Edit Footer** buttons on the
chrome itself. Block content changes are global across mobile and desktop ("any changes to the block content or section
styles are global, affecting both mobile and computer layouts") while layout/sizing can vary per viewport. The verb is
just "Save"; the *scope* is implicit in which panel you're in — no mode indicator needed.

**Navigation.** **The Pages panel IS the nav builder.** Drag a page up, down, or into a folder to reorder / nest the
site's navigation. The navbar is automatically built from the Main Navigation group. Multiple groups exist: Main
Navigation, Secondary Navigation, Footer Navigation, each mapped to a structural region. Custom non-page items (external
links, anchors) added via "Add Link." Style options for each are surfaced separately in Site Styles.

**Global styles.** **Site Styles** (Design panel) is the theme.json equivalent — global Fonts, Colors, Spacing, and
Buttons. Most interesting primitive: **Section Themes**. Preset color themes (Lightest, Light, Bright, Dark, Darkest)
are configured once in Site Styles, then applied per section from Section Settings. A section-level override doesn't
invent colors — it picks a predefined global theme and applies it. The design system is preserved while per-section
contrast is still possible.

**Takeaway for ArtisanPack.** (1) **Separation of editor regions by scope** is the cleanest lesson in this scan —
Squarespace never asks "are you in site mode or post mode?" because each scope has its own UI region. Different regions,
different URLs, different panels. Give each site-editor section its own page, let the URL carry scope. (2) **Section
Themes as named token bundles** is a beautiful compromise between "everything the same" and "everything overridable."
ArtisanPack could ship named color themes from `theme.json` `styles.variations`, selectable per-section, rather than a
per-element color picker everywhere. (3) **Pages panel as nav builder** collapses pages and menu into one mental model
— works for 80% of sites. Make it the default; richer menu builder stays available as a power-user escape hatch.

Sources:
- [Editing your site with Fluid Engine – Squarespace Help Center](https://support.squarespace.com/hc/en-us/articles/6421525446541-Editing-your-site-with-Fluid-Engine)
- [Fluid Engine – Drag and Drop Website Editor — Squarespace](https://www.squarespace.com/websites/fluid-engine)
- [Making style changes – Squarespace Help Center](https://support.squarespace.com/hc/en-us/articles/205815788-Making-style-changes)
- [Styling navigation – Squarespace Help Center](https://support.squarespace.com/hc/en-us/articles/205816038-Styling-navigation)

### 2.5 Cross-tool patterns

**Patterns to copy:**

- **Scope is communicated spatially, not modally.** Squarespace uses different admin regions; Webflow uses different
  apps entirely; Framer uses different URLs (canvas vs. published-site Edit button). None of them rely on a mode toggle
  to tell the user "you are now editing site-wide things." That's a strong signal against Gutenberg's pill-toggle at
  the top of the editor.
- **Menu data models are typed references, not freeform labels.** Craft's Verbb plugin, Framer's CMS-bound nav
  components, and Webflow's symbol-based navbar all treat a menu item as a reference to an existing element. When the
  source title changes, the menu updates. Refutes WordPress's "type-the-label-yourself" model.
- **Page tree doubles as primary navigation in most cases.** Squarespace's Pages panel is the nav editor; Craft's
  Structure sections are commonly used the same way. Collapses two mental models into one for the common case.
- **Theme tokens exist as a named layer with modes.** Webflow Variables + Collections + Modes, Framer Styles/Variables,
  Squarespace Site Styles + Section Themes — all expose a named, global, mode-aware token layer with visual swatches
  and live preview. WP's theme.json is philosophically the same but its editor surface is widely considered underbaked.
- **Named theme bundles beat free-form overrides for section-level variation.** Squarespace Section Themes + Webflow
  Variable Modes are the same pattern at different granularity: editors never invent colors, they pick from a curated
  set.
- **Content-role users get a stripped UI.** Webflow Editor, Framer on-page editing — deliberately strip block inserter,
  global styles access, structural controls. Only show "edit this text / swap this image."

**Anti-patterns to avoid:**

- **"One template rules all CMS items" rigidity** (Framer, partially Webflow). Per-post-type template variants should
  stay first-class. Gutenberg actually gets this right and we must preserve it.
- **Menu-as-hand-assembled-link-components** (Webflow, Framer). Pushes menu editing into designer hands. A dedicated
  menu builder with typed-reference nodes and named locations is a differentiator.
- **Burying global styles inside the page editor's sidebar** (Gutenberg). Dedicated Design panel (Squarespace) or
  dedicated Variables UI (Webflow) is far more discoverable.
- **Unclear-scope multi-entity save** (Gutenberg). Saving a template ≠ publishing content; the UI should name the scope
  of every save.
- **Secondary/tertiary menu support as an afterthought** (Squarespace 7.1 regressed; Framer/Webflow skip it entirely).
  Real multi-location menus are table stakes.
- **Page-scoped tools marketed as site tools** (Gutenberg blurs this). Be honest about scope.

---

## 3. Design Brief: Information Architecture

### 3.1 Entry model

The site editor is a sibling of the post editor, not a superset. The admin navigation surfaces these as peer sections:

```
Dashboard
├── Posts          ← post-editor (already shipped)
├── Pages          ← post-editor (already shipped)
├── Media
├── Site Editor    ← new in V1 — this brief
│   ├── Templates
│   ├── Template Parts
│   ├── Patterns
│   ├── Styles
│   └── Navigation
├── Users
└── Settings
```

**Principle:** the site editor is for editing site-wide entities (templates, parts, global styles, navigation, patterns).
The post editor is for editing individual posts/pages. Users never have to ask "which editor am I in?" because the
admin navigation told them before they got there. This is the direct answer to F1 + F2.

### 3.2 Site-editor shell

```
┌──────────────────────────────────────────────────────────────────────────┐
│  Top Bar:  [ArtisanPack] [Section picker ▾]  [Entity title]      [Save] │
├──────────────────────────────────────────────────────────────────────────┤
│                         │                                                │
│  Navigator Sidebar      │  Canvas (iframe when editing an entity;        │
│  (section-specific      │  list/grid when browsing a section)            │
│   browser of the        │                                                │
│   section's entities)   │                                 ┌─ Inspector ─┐│
│                         │                                 │ (Block /    ││
│                         │                                 │  Entity /   ││
│                         │                                 │  Section)   ││
│                         │                                 └─────────────┘│
└──────────────────────────────────────────────────────────────────────────┘
```

The shell has **four regions** and their responsibilities are fixed:

- **Top bar** — identity (ArtisanPack brand, entity title), section switcher, Save action with explicit scope.
- **Navigator sidebar** — left-hand panel, owned by the current section; content varies per section but the *role*
  never varies: it browses the entities of the current section.
- **Canvas** — the main surface. When browsing a section, shows a list/grid of entities. When editing an entity, shows
  the iframe-rendered editor for that entity.
- **Inspector** — right-hand panel. Block-scoped when a block is selected; entity-scoped when editing entity-level
  settings; section-scoped in list views.

Rationale: F10 (navigator depth), F11 (styles hierarchy), F13 (control dispersion) all stem from ambiguity about *which
region owns what*. Locking these roles means controls have a single predictable home.

#### 3.2.1 Editing-mode regions (block inserter, list view, pattern picker)

D1 (#368) ships the four-region shell above. As D2–D5 wire entity editing into the canvas, three additional
regions need a fixed home so the chrome doesn't drift section by section: the **block inserter**, the **list view**
(block outline), and the **pattern picker**. Decision (recorded after D1 prototype review):

- **Navigator stays visible.** When the user enters edit mode (selects an entity in the section list), the navigator
  collapses to an **icon-only** rail (≈48px). It does not disappear. Section labels are revealed on hover / focus and
  the active section keeps its accent. This preserves P5 ("navigator browses, canvas edits, inspector configures") and
  the cross-section jump that motivates the persistent-shell IA in the first place — losing the navigator while
  editing is one of the WP frictions (F11) we're explicitly designing out.
- **Inserter + pattern picker live in a secondary left rail.** A second column opens *to the right of* the navigator
  when toggled from the top bar's left button. It hosts the block inserter and pattern picker (synced/unsynced tabs
  from §3.6) as a tabbed panel. The panel is roughly the same width as the post-editor's inserter (≈350px). When
  closed, the rail collapses entirely; the canvas reclaims the space.
- **List view rides in the inspector.** The right-rail inspector grows a third tab — **List view** — alongside Block
  and Entity. This avoids competing with the inserter for left-rail space and keeps the inspector's charter intact:
  it's the panel for *configuring what's on the canvas*, and the outline of the canvas is part of that.
- **Top bar adds three editing-mode toggles.** Inserter (left-rail), list-view (right-rail tab focus), and a future
  zoom-out / preview affordance. Each toggle's `aria-pressed` reflects the panel's open state, mirroring the post
  editor's M7 conventions.

```
Edit mode (entity selected):
┌──────────────────────────────────────────────────────────────────────────┐
│  Top Bar:  [☰] [+] [List] [Brand]  [Entity title]   [Save template]    │
├────┬─────────────────┬─────────────────────────────┬─────────────────────┤
│    │                 │                             │                     │
│ N  │  Inserter /     │  Canvas (iframe)            │  Inspector          │
│ a  │  pattern        │                             │  ┌──────────────┐   │
│ v  │  picker         │                             │  │ Block │ Doc  │   │
│ (  │  (toggled,      │                             │  │ List view    │   │
│ i  │  optional)      │                             │  └──────────────┘   │
│ c  │                 │                             │                     │
│ )  │                 │                             │                     │
└────┴─────────────────┴─────────────────────────────┴─────────────────────┘
```

```
Browse mode (no entity selected):
┌──────────────────────────────────────────────────────────────────────────┐
│  Top Bar:  [☰] [Brand]  [Section name]                                  │
├──────────────────────┬───────────────────────────────┬───────────────────┤
│                      │                               │                   │
│  Navigator           │  Canvas (entity list/grid)    │  Inspector        │
│  (full-width with    │                               │  (section-scoped) │
│  section list)       │                               │                   │
│                      │                               │                   │
└──────────────────────┴───────────────────────────────┴───────────────────┘
```

Rationale: this is the "Pattern B" hybrid considered after D1's shell landed. Pattern A (swap left rail entirely
with editing tools) loses the navigator at exactly the moment cross-section jumps are most useful. Pattern C
(floating overlays) makes the pattern picker too cramped for the synced/unsynced + filter UI in §3.6. Pattern B
preserves the four-region shell while giving editing tools dedicated space, at the cost of needing one extra
column on small viewports — addressed by the inserter rail being collapsible.

D2 implements the icon-only navigator collapse and the inserter rail. D3–D5 plug their per-section editor surfaces
into the same chrome. Every D-issue's PR must reference this section when it lands editing-mode UI.

### 3.3 Section IA — overview

Five sections, each with consistent region semantics:

| Section         | Navigator sidebar                          | Canvas (list)                | Canvas (edit)                   | Inspector (edit)                           |
|-----------------|--------------------------------------------|------------------------------|---------------------------------|--------------------------------------------|
| Templates       | Template list, filtered by kind + status   | Cards with preview + usage   | Block editor iframe             | Block / Template settings                  |
| Template Parts  | Template-part list, filtered by area       | Cards with area + usage      | Block editor iframe             | Block / Part settings                      |
| Patterns        | Pattern list, tabs: Synced / Unsynced      | Cards with preview + usage   | Block editor iframe             | Block / Pattern metadata                   |
| Styles          | Styles navigator (typography/colors/…)     | Style book (all blocks)      | Block editor iframe (preview)   | Active style scope editor                  |
| Navigation      | Menu list, one per location                | Menu tree editor (not iframe)| Menu tree editor                | Selected menu item's target + link settings |

### 3.4 Section IA — Templates

**Purpose:** browse, edit, add, delete, and reset templates. A template determines how a URL or kind of content renders.

**Navigator sidebar (list mode):**

- Filter chips: All / Theme / Custom / Unused.
- Grouped list by template kind: Front page, Home, Single post, Single page, Archive, 404, Search, Custom.
- Each row: template name, derived-from indicator (theme / customized / custom), usage count ("used by 42 pages").

**Canvas (list mode):**

- Card grid. Each card: thumbnail preview, template name, kind, fallback indicator, "used by N" link that opens a panel
  listing the pages/posts currently resolving to this template.
- Primary action per card: Edit. Secondary: Duplicate, Reset (if customized from theme), Delete (if custom).
- Top of canvas: "Add new template" button → opens a chooser of common kinds + "Custom (URL pattern)" → opens a fallback
  picker (see §3.4 fallback chain flow).

**Canvas (edit mode):**

- Block editor iframe, using the same `@wordpress/block-editor` surface as the post editor but with the templates data
  source. Fallback chain shown as a breadcrumb: `Home ▸ Front page (falls back to Home if empty)`.

**Inspector (edit mode):**

- Block tab (when block selected): standard `InspectorControls`.
- Template tab: name, slug, kind, fallback chain visualizer, "pages using this template" count-link, "Reset to theme"
  (if customized).

**Solves:** F7 (template hierarchy invisible), F8 (swap/reset friction).

### 3.5 Section IA — Template Parts

**Purpose:** browse, edit, add, delete template parts (header, footer, sidebar, and custom areas). Typed areas are a
first-class concept.

**Navigator sidebar:**

- Filter chips: All areas / Header / Footer / Sidebar / Other.
- Grouped list by area.
- Each row: part name, area, usage count (which templates include it).

**Canvas (list mode):**

- Card grid. Each card: thumbnail, name, area badge, templates using it.
- Actions per card: Edit, Duplicate, Delete.

**Canvas (edit mode):**

- Block editor iframe.

**Inspector (edit mode):**

- Block tab, same as templates.
- Part tab: name, area (dropdown of allowed areas), "used by N templates" count-link.

**Swap flow:** when editing a template, the Template Part block's inspector has a "Swap" action that opens a picker
**pre-filtered to parts of the same area**. No flat mixed-area list. (F8.)

### 3.6 Section IA — Patterns

**Purpose:** browse, edit, add, delete patterns. Patterns are **synced** (changes propagate to all insertions) or
**unsynced** (inserted as a copy of the block tree, no back-reference).

We adopt the "synced / unsynced" vocabulary from day one — no legacy "reusable blocks" rename drama. (F6.)

**Navigator sidebar:**

- Tabs: **Synced** / **Unsynced**.
- Filter chips per tab: All / My patterns / Theme patterns.
- Each row: pattern name, sync status, usage count.

**Canvas (list mode):**

- Card grid with thumbnails.
- Actions per card: Edit, Duplicate, Delete. No "Detach" action — "Convert to unsynced copy" is a first-class menu item
  surfaced with a confirmation dialog that names what will happen to overrides. (F6.)

**Canvas (edit mode):**

- Block editor iframe.

**Inspector (edit mode):**

- Block tab.
- Pattern tab: name, slug, sync status (read-only — conversion is a destructive action, not a toggle), category
  (multi-select), "inserted N times" count-link.

**V1 position on bindings:** unsynced patterns are pure block-tree copies. No bindings in V1 (per plan §8). This must
be visible in the pattern creation flow: when saving an unsynced pattern, the UI says "A copy of these blocks will be
inserted each time. Changes to the pattern will only affect future insertions." Synced flow says "Changes to this
pattern will propagate to every insertion."

### 3.7 Section IA — Styles

**Purpose:** edit global styles (typography, colors, layout, blocks, elements). Browse style variations. Preview with
Style Book.

**Navigator sidebar (the Styles navigator):**

- Hierarchical browser matching theme.json structure:
  - **Typography** → Font family, Font size, Line height, Elements (headings H1–H6, links, captions).
  - **Colors** → Palette, Gradients, Duotones, Elements.
  - **Layout** → Content size, Wide size, Spacing presets.
  - **Blocks** → per-block overrides (Button, Heading, List, …).
  - **Variations** → named `styles.variations` bundles (see below).

**Canvas (list mode, section landing):**

- **Style Book** is the landing canvas, not a buried icon. Shows a gallery of block examples rendered against the
  current style state. Users see the Style Book *by default* when they enter the Styles section. (F11.)
- Top of canvas: style variation picker as a horizontal strip of cards ("Default", "Light", "Dark", any theme-provided
  variations). Selecting a variation switches the previewed state.

**Canvas (edit mode):**

- Not a separate mode. Selecting an item in the Styles navigator opens the inspector on the right; the canvas continues
  to show the Style Book so the user sees the effect live.

**Inspector (edit mode):**

- Scoped to the selected Styles-navigator item. Typography → global typography controls. Blocks → Button → button-scoped
  typography / color / spacing. Each inspector panel has a breadcrumb at the top: `Styles ▸ Blocks ▸ Button` so the user
  always sees where they are in the styles hierarchy. (F11.)

**Style variations** (`styles.variations` in theme.json): V1 ships a **picker** UI (like Squarespace Section Themes).
Users switch variations site-wide via the picker strip; themes can ship multiple. Variations are editable in V1 only to
the extent of "duplicate a variation and modify its values" — authoring new variations from scratch is 1.1.
*(Open question from plan §8, answered here: yes, ship the picker in V1; full author flow in 1.1.)*

### 3.8 Section IA — Navigation

**Purpose:** build and edit menus. A menu is a named, typed tree of links bound to menu locations.

**Data model decisions (binding D4 and B1.nav-shim):**

- A **menu** is a named entity (`primary`, `footer`, `mobile`, …).
- A **menu location** is a string key declared in `config/visual-editor.php` (config-driven in V1 — plan §8 open
  question answered: config, not DB CRUD). A menu is assigned to a location.
- A **menu item** is a typed-reference node: `{ type: page|post|taxonomy|custom, target_id?, label_override?, url?,
  children: [...] }`. When the target's title changes, the menu item's displayed label auto-updates unless
  `label_override` is set. **This is the single biggest win from the Craft/Verbb pattern and it is non-negotiable for V1.**
  (F4.)

**Navigator sidebar:**

- Menu list: one row per menu. Shows name, location assignment, item count.
- "Add new menu" button → prompts name + optional location.

**Canvas (not an iframe — a native tree editor):**

- The Nav editor is the one section that doesn't use the block-editor iframe canvas. Reason: nav trees are structural
  data, not block trees; a tree editor with drag-sort and typed-target picker is the correct UI.
- Drag-sortable nested tree. Each node shows label, target icon (page / post / custom), target title. Inline actions:
  Edit item, Add child, Delete.

**Inspector (when a menu is selected):**

- Menu tab: name, location assignment (dropdown of declared locations), fallback behavior (if assigned location has no
  menu, render this menu, or render nothing).

**Inspector (when a menu item is selected):**

- Target picker: type selector (Page / Post / Custom URL / Taxonomy term) + entity picker (search + select from typed
  list; for custom URL, a URL field).
- Label override (with placeholder showing the auto-label).
- CSS class + rel attributes (advanced, collapsed).
- Open in new tab toggle.

**No `core/navigation` block dependency for editing the menu itself.** The nav section edits menu data; the
`core/navigation` block on templates consumes that data at render time via a location reference. This decouples "editing
the menu" from "placing the menu" and avoids F4 + F5. (F4 says the Nav block has become a black hole; F5 says settings
are unreachable from the Nav context.) In our model, block-level styling of the nav (spacing, orientation, typography)
lives on the `core/navigation` block's inspector when placed on a template. Menu **content** (which items, in what
order, pointing where) lives in the Navigation section. Clear separation.

**Relation to pages-as-nav (cross-tool pattern):** V1 supports auto-generated menus from a page tree (opt-in at
menu-create time: "Auto-generate from published pages" → menu mirrors the page hierarchy, editable afterwards). This
gets us 80% of small-site cases out of the box. (Squarespace / Craft pattern.)

### 3.9 Inspector pattern (applies across all sections)

The inspector is always one of three scopes, with the current scope always visible at the top:

```
┌────────────────┐
│  Block         │ ← default when a block is selected in the canvas
├────────────────┤
│  [panels…]     │
└────────────────┘

┌────────────────┐
│  Entity        │ ← when the entity-level tab is active
├────────────────┤
│  [panels…]     │
└────────────────┘

┌────────────────┐
│  Section       │ ← when no entity is being edited (list views)
├────────────────┤
│  [panels…]     │
└────────────────┘
```

This directly addresses F13 (control dispersion): users learn that the inspector has a tab for Block and a tab for
Entity and every control they need is in one of the two. No three-dot menu surprises, no toolbar-only settings. The
block toolbar is for *transient* actions (format, align, move, duplicate); the inspector is for *persistent* settings.

---

## 4. Design Brief: Mode Model

### 4.1 The fundamental claim

**Scope is communicated spatially, not modally.** No pill-toggle at the top of the editor. No hidden mode state. Instead:

- Different scopes live at different URLs.
- The URL and the admin-nav breadcrumb always answer "where am I?"
- The Save button's label always answers "what will Save do?"

This is the Squarespace lesson applied end-to-end.

### 4.2 Scopes and their URLs

| Scope                         | Entry URL                                          | Save label                       |
|-------------------------------|----------------------------------------------------|----------------------------------|
| Post editor (existing)        | `/admin/posts/{id}/edit`                           | "Save post" / "Update post"      |
| Page editor (existing)        | `/admin/pages/{id}/edit`                           | "Save page" / "Update page"      |
| Site Editor: Templates list   | `/admin/site-editor/templates`                     | *(no save — list view)*          |
| Site Editor: Template edit    | `/admin/site-editor/templates/{id}`                | **"Save template"**              |
| Site Editor: Template Parts   | `/admin/site-editor/template-parts`                | *(no save — list view)*          |
| Site Editor: Template Part edit | `/admin/site-editor/template-parts/{id}`         | **"Save template part"**         |
| Site Editor: Patterns list    | `/admin/site-editor/patterns`                      | *(no save — list view)*          |
| Site Editor: Pattern edit     | `/admin/site-editor/patterns/{id}`                 | **"Save pattern"**               |
| Site Editor: Styles           | `/admin/site-editor/styles`                        | **"Save global styles"**         |
| Site Editor: Navigation list  | `/admin/site-editor/navigation`                    | *(no save — list view)*          |
| Site Editor: Navigation edit  | `/admin/site-editor/navigation/{id}`               | **"Save menu"**                  |

**The Save button always names its scope.** Never ambiguous "Save" or "Update" without a noun. (F3.)

### 4.3 What Save does on every screen

| Screen                 | Save affects                                                   | Never affects                                 |
|------------------------|----------------------------------------------------------------|-----------------------------------------------|
| Template edit          | This one template + embedded template-part references, if any | Other templates, posts, global styles         |
| Template Part edit     | This one template part                                        | Templates that include it (they'll re-render) |
| Pattern edit (synced)  | The pattern definition (propagates on next page render)        | Pages already rendered (until they re-render) |
| Pattern edit (unsynced)| The pattern definition only for future insertions              | Existing insertions (they're copies)          |
| Styles                 | Global styles (theme.json overrides)                           | Any specific template / post / pattern        |
| Menu edit              | This one menu                                                  | Other menus                                   |

**No multi-entity save dialog.** If the user somehow has unsaved changes in two scopes (e.g., editing a template that
embeds a template part they modified inline), we force an explicit commit of each at its own save action. The template
save dialog shows: "You also have unsaved changes in: Footer (template part). [Save footer first] [Discard footer
changes]." No mystery checklist. (F3.)

### 4.4 Why not a mode switcher

Gutenberg ships a mode concept (Edit / Select / Zoom out / Write) because it tries to compress multiple editing
experiences into a single canvas. We sidestep this by:

- Giving each section its own URL + landing page (no compression).
- Using the block-editor canvas only for entities that are actually block trees (templates, parts, patterns — not menus
  or styles).
- Deferring zoom-out/select-mode equivalents to 1.1. V1 ships a single canvas mode per entity type. (F9.)

### 4.5 Post editor ↔ Site editor boundary

**Post editor does not edit templates.** In the post editor we drop the "Edit template" button Gutenberg surfaces in
the document sidebar. If the user wants to edit the template, they go to the Site Editor → Templates section. This is
the direct answer to F2.

The post-editor document sidebar *does* show which template renders the current post ("Rendered by: Single post ↗"
as a link to the template in the Site Editor). But editing always happens in its own scope. (F2.)

---

## 5. Design Brief: Key Flows

Storyboard-level. No pixel-specific affordances.

### 5.1 Flow — Edit a template

```
Admin nav: click "Site Editor" → "Templates"
  ↓
Templates section landing (card grid)
  ↓
Card: "Single post"  →  shows preview, "used by 47 posts"
  ↓ click Edit
Template edit screen (block-editor iframe canvas)
  Top bar: "Single post  ▸  Save template"
  Sidebar: navigator with Blocks (list view of the template's block tree)
  Inspector: Block tab when a block is selected; Template tab for slug/kind/fallback
  ↓ make a change, click Save
Confirm dialog (only if multi-entity):
  "Save template 'Single post'?
   [Save template]"
  ↓
Saved. Stay on the edit screen.
```

Key details:
- The template's fallback chain is shown as a breadcrumb inside the canvas: `Single post (falls back to Singular, then Index)`.
- "Used by 47 posts" is a clickable count that opens a panel listing the posts/pages currently resolving to this template.
  (F7.)

### 5.2 Flow — Customize global styles

```
Admin nav: click "Site Editor" → "Styles"
  ↓
Styles section landing:
  Canvas: **Style Book** (gallery of all block examples rendered against the current theme state)
  Sidebar navigator: Typography / Colors / Layout / Blocks / Variations
  Top of canvas: variation picker strip ("Default", "Light", "Dark", …)
  ↓ click Sidebar → Blocks → Button
Inspector (right) opens on "Styles ▸ Blocks ▸ Button":
  Typography / Colors / Spacing / Borders panels
  Canvas: Style Book updates live — Button examples re-render as values change
  Top bar: Save label is "Save global styles"
  ↓ click Save
Saved. Canvas continues to show updated Style Book.
```

Key details:
- Style Book is the **default canvas** for the Styles section, not hidden behind an icon. (F11.)
- Inspector breadcrumb (`Styles ▸ Blocks ▸ Button`) always shows scope. (F11.)
- Variation picker at the top of canvas exposes `styles.variations` as named bundles. (Competitive learning: Section Themes.)

### 5.3 Flow — Build a nav menu

```
Admin nav: click "Site Editor" → "Navigation"
  ↓
Navigation section landing: list of existing menus (may be empty first time)
  ↓ click "Add new menu"
Prompt: Name = "Primary"; Location = [dropdown from config: Primary / Footer / Mobile]
  Option: "Auto-generate from published pages" [checkbox]
  ↓ Create
Menu edit screen (tree editor, NOT an iframe)
  Canvas: drag-sortable tree; if auto-generated, pre-populated from pages
  Sidebar navigator: menu list (current menu highlighted)
  Inspector:
    When menu root selected: name / location / fallback behavior
    When menu item selected: target picker (Page/Post/Custom URL/Taxonomy) + label override + advanced
  Top bar: Save label is "Save menu"
  ↓ add / reorder / retarget items, click Save
Saved.
```

Key details:
- Menu items are typed references. Changing a page's title auto-updates the menu's displayed label. (F4, cross-tool pattern.)
- Menu locations are config-driven. (Plan §8 open question.)
- Placing the menu on a template is a separate action: a `core/navigation` block on a template consumes a menu by
  location. The Nav section does not own placement.
- Nav section does **not** use the block-editor canvas — a tree editor is the right UI for hierarchical data. (F4, F5.)

### 5.4 Flow — Create a synced pattern

```
Post editor: select a group of blocks → block toolbar three-dot → "Create pattern"
  ↓
Dialog:
  Name = "CTA banner"
  Type = [radio] Synced / Unsynced
    Synced caption: "Changes to this pattern will update every insertion."
    Unsynced caption: "A copy of these blocks will be inserted each time. Changes only affect future insertions."
  Category = [multi-select]
  ↓ Create
Confirmation: "Pattern 'CTA banner' created. Edit pattern in Site Editor → Patterns."
  ↓ optional: click link to jump to Site Editor
```

Alternative entry:

```
Site Editor: Patterns → "Add new pattern" → choose Synced/Unsynced → opens empty block-editor canvas
```

Key details:
- "Detach" doesn't exist. The menu item is **"Convert to unsynced copy"**, with a confirmation dialog naming that
  overrides will be preserved but future changes to the source pattern won't propagate. (F6.)
- Sync status is immutable after creation. Conversion requires creating a new unsynced copy. (F6 — prevents the
  accidental toggle that Gutenberg has.)

### 5.5 Flow — Swap a template part

```
Editing a template that includes a Template Part block (e.g., header)
  ↓ select the Template Part block in the canvas
Inspector → Block tab:
  Part name: Header (link to edit in its own scope)
  "Swap part" button
  ↓ click Swap part
Picker dialog:
  **Pre-filtered to parts whose area = Header**
  Cards: thumbnail, name, "used by N templates"
  ↓ select "Header (Minimal)"
Confirm: "Swap header for 'Header (Minimal)'? This template will render with the new part. Other templates using the
  original header are unaffected."
  ↓ confirm
Block updated to reference new part. Top bar shows unsaved template changes.
  ↓ Save (label: "Save template")
```

Key details:
- Picker is pre-filtered to same-area parts. No flat mixed-area list. (F8.)
- Confirmation names scope: this template, not all templates. (F3, F8.)
- "Edit original" (the action Gutenberg has, where you jump into the part) remains available but is a separate link in
  the Block inspector. (F8.)

---

## 6. UX Principles

These are the rules D1–D5 must be reviewed against. A PR that violates one needs either a new principle or a waiver.

### P1. The user always knows what entity is being edited and what scope Save affects.

Every screen names the entity in the top bar. Every Save button has an explicit noun ("Save template", "Save menu",
"Save global styles"). No bare "Save" anywhere. No multi-entity checklist. Direct answer to F1 + F3.

### P2. Scope is communicated spatially, not modally.

Different scopes live at different URLs. The admin-nav breadcrumb answers "where am I" without requiring users to
track hidden mode state. No zoom-out-style ambiguous modes in V1. Direct answer to F2 + F9.

### P3. Site editor and post editor are siblings, not nested.

The site editor is for site-wide entities. The post editor is for individual posts. Neither can edit the other's
content. Users reach each from the admin navigation, not by drilling into a button labelled "Edit template" inside the
other. Direct answer to F2.

### P4. Inspector owns persistent controls; toolbar owns transient actions.

Every persistent setting lives in the inspector, scoped to Block / Entity / Section tabs. The block toolbar is for
format, alignment, move, and duplicate — transient manipulation only. Three-dot menus host the long-tail destructive
actions. No "where did my setting go?" Direct answer to F13.

### P5. The navigator sidebar browses; the canvas edits; the inspector configures.

Region roles are fixed. The navigator is never the place to edit values. The canvas is never the place to configure
metadata. The inspector is never the place to select a different entity. Direct answer to F10 + F13.

### P6. Menu items are typed references to real entities, not freeform labels.

A menu item points to a page / post / taxonomy / custom URL. When the source title changes, the menu auto-updates
unless the user has set a label override. No more "update the menu after you rename the page." Direct answer to F4.

### P7. Template fallback chains are visible to users.

A template's fallback chain ("Single post falls back to Singular, then Index") is shown as a breadcrumb inside the
canvas. "Used by N" is clickable and lists the posts/pages resolving to the template. New templates inherit their
fallback's content by default, not an empty canvas. Direct answer to F7.

### P8. Styles has a visible hierarchy: Theme variation → Global → Element → Block → Local.

The inspector always shows a breadcrumb of where the current setting lives in the hierarchy
(`Styles ▸ Blocks ▸ Button`). The Style Book is the default canvas of the Styles section, not a buried icon. Variations
are a named layer with a picker UI. Direct answer to F11.

### P9. Destructive actions name their effect and require explicit confirmation.

"Convert to unsynced copy" (not "Detach"). "Swap part" confirms the scope. "Delete template" confirms usage count. No
silent data loss. Direct answer to F6 + F8.

### P10. Accessibility is a first-class acceptance criterion, not a cleanup task.

Every D-phase PR must demonstrate keyboard-only flows for the new surface, focus management across iframe boundaries,
and screen-reader labels on all interactive elements. We inherit the iframe accessibility cost from adopting
`@wordpress/block-editor`; we do not inherit its accessibility debt. Direct answer to F12.

---

## 7. Phase-D issue impact

How each D issue implements this brief:

| Issue | Topic                         | What this brief dictates                                                                                   |
|-------|-------------------------------|------------------------------------------------------------------------------------------------------------|
| D1    | Site-editor shell             | Implements §3.2 (shell regions) + §4.2 (URL routing) + §4.3 (Save scope per screen) + P1, P2, P3, P5.       |
| D2    | Template browser + editor     | Implements §3.4 (Templates IA) + §3.5 (Template Parts IA) + §5.1 + §5.5 (swap flow) + P7, P9.               |
| D3    | Global styles UI              | Implements §3.7 (Styles IA) + §5.2 (customize flow) + style variations picker + P8.                         |
| D4    | Nav editor screen             | Implements §3.8 (Nav IA) + §5.3 (build flow) + typed-reference menu items + config-driven locations + P6.  |
| D5    | Patterns library UI           | Implements §3.6 (Patterns IA) + §5.4 (create flow) + "Convert to unsynced copy" (not Detach) + P9.          |

---

## 8. Open questions flagged for specific D issues

- **D3 (Styles):** this brief commits V1 to shipping a **variations picker**, answering plan §8's open question.
  Authoring new variations from scratch is 1.1. D3 confirms scope in its acceptance criteria.
- **D3 (Styles):** the Style Book is the default canvas. If that turns out to be prohibitive in cost, the escape valve
  is to surface Style Book as a prominent tab (not a buried icon), but this brief prefers the default-canvas option.
- **D4 (Navigation):** this brief commits to **config-driven menu locations** in V1, answering plan §8. DB-driven
  locations with admin CRUD is a 1.1 consideration.
- **D4 (Navigation):** this brief commits to **auto-generated-from-pages** as an opt-in at menu creation. This needs a
  concrete page-tree-source API in B1's nav shim work.
- **D5 (Patterns):** this brief commits to **pure block-tree copy** for unsynced patterns in V1, answering plan §8.
  Bindings / partial sync are a separate future system.

---

## 9. Glossary

- **Scope** — the entity a save action affects. Always named on the Save button.
- **Navigator sidebar** — the left-hand panel, section-specific, used to browse entities. Never to edit values.
- **Canvas** — the main editing surface. An iframe block-editor for templates/parts/patterns; a tree editor for
  navigation; a Style Book for styles.
- **Inspector** — the right-hand panel, scoped to Block / Entity / Section. The home of persistent settings.
- **Typed-reference menu item** — a menu node that points to a page/post/taxonomy/URL by ID, auto-updating when the
  target changes.
- **Variation** — a named `styles.variations` bundle, selectable from the Styles section's variation picker.
- **Area** (for template parts) — the kind of part it is (header / footer / sidebar / custom); filters the swap picker.
- **Convert to unsynced copy** — our rename of Gutenberg's "Detach". Clear about what happens to the data.

---

## 10. Sign-off

This brief requires review and approval before D1 work begins. Reviewers should validate:

- The 10 UX principles are ones the reviewer would apply when reviewing a D-phase PR.
- The mode model answers "what does Save do" on every screen.
- The IA tables for each section are implementable as described.
- The navigator / canvas / inspector region split is acceptable everywhere, including the Nav section (which is the
  deliberate outlier — a tree editor, not a block-editor iframe).

Approval gates D1 kickoff. Any substantive change to this brief after approval needs a follow-up PR that updates the
brief and notes which D-issues are affected.
