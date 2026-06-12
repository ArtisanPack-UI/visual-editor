# Claude conventions for `artisanpack-ui/visual-editor`

## Porting blocks from `crosswinds-blocks`

The crosswinds-blocks → ArtisanPack UI port (umbrella #495, CW0–CW7 clusters) ships every block as a **first-party** ArtisanPack UI block. Treat the crosswinds source as a structural reference only — none of the lineage should surface in the new code.

**Do NOT reference any of the following in code, comments, asset filenames, class/property names, CSS comments, test descriptions, or commit messages:**

- `CW0`, `CW1`, `CW2`, …  or any other internal phase label
- `crosswinds-blocks`, `crosswinds_blocks`, `Crosswinds Blocks`, `crosswinds`
- Phrases like "CW1 pilot", "port of crosswinds-blocks/…", "upstream crosswinds-blocks", "ported from CW#"

**Acceptable references:**

- The GitHub issue number on its own — e.g. `(#497)` — that is a real artifact of the work.
- Generic words like "upstream" or "the original design" when explaining *why* a design choice differs, with no proper-noun attribution.

**Why:** Jacob created crosswinds-blocks and is intentionally rebranding these blocks as first-party ArtisanPack UI. Carrying the CW / crosswinds naming into the new code conflates the lineage with the brand and reads as noise.

**How to apply:**

- When writing a new block or reviewing a port, default to first-party naming everywhere.
- Asset files: name them by family (`accordion.css`, `tabs.css`, `grid.css`, `interactivity.js`), never `cw1.css` / `cw2-interactivity.js`.
- Before opening a PR, grep the diff for `CW[0-9]`, `crosswinds-blocks`, `crosswinds_blocks`, `crosswinds`. Anything found needs to be replaced before the PR opens.
- The CW0 breadcrumbs PR (commit `fb258ee`) does carry "CW0 pilot" wording — that was an oversight, not a precedent to follow.

## In-progress work: issue #501 (single-post content cluster)

> Remove this section once the PR for #501 is merged.

**Branch:** `feature/501-single-post-content-cluster`, off `feature/495-port-crosswinds-blocks` (NOT off `main`). Initial scaffold is already committed + pushed to the GitHub remote.

**Status:** Implementation complete; manual browser verification + CodeRabbit review + draft PR remain.

**What's done:**

- 4 blocks forked under `artisanpack/*` with full Blade + React + Vue parity:
  - `artisanpack/single-content`
  - `artisanpack/related-posts`
  - `artisanpack/author-social-icons`
  - `artisanpack/social-share-content`
- `src/Resources/PostResolver.php` extended with `resolveAuthorSocialIcons()` + `resolveSocialShareContent()` (stamp `_resolvedAuthorSocialLinks` / `_resolvedShareLinks`). Two new entries added to `SUPPORTED_BLOCKS`.
- `src/Resources/QueryInliner.php` extended with `expandSingleContent()` + `expandRelatedPosts()` — both resolve through `QueryResolverContract` per the issue's "no direct DB" requirement. `inline()` now takes an optional `$hostPost` so `related-posts` can derive taxonomy term IDs from the page's host post.
- `packages/visual-editor-renderer-blade/src/View/Components/BlocksComponent.php` updated to forward the host post into `QueryInliner::inline()`.
- `packages/renderer-parity.json` manifest updated; both `registerCoreBlocks.ts` files updated.
- Shared social-icon SVG registries: one each in `_shared/social-icons.ts` (editor), `visual-editor-renderer-blade/src/Support/SocialIconRegistry.php`, `visual-editor-renderer-react/src/blocks/artisanpack/socialIcons.ts`, `visual-editor-renderer-vue/src/blocks/artisanpack/socialIcons.ts`.

**Tests added (all passing locally):**

- `resources/js/visual-editor/blocks/__tests__/single-post-content-family.test.ts` — block.json + save-contract suite (13 tests).
- `tests/Unit/VisualEditor/Resources/PostResolverSocialIconsTest.php` — 7 tests.
- `tests/Unit/VisualEditor/Resources/QueryInlinerSinglePostContentTest.php` — 7 tests.
- 8 new fixtures appended to `packages/visual-editor-renderer-vue/tests/parity.test.ts` (covers `has-post: true/false`, related-posts populated/empty, author-icons + share-content icon/label permutations, share-content unsafe-URL drop).

**Test status (last full run):**

- 1993 / 1993 JS tests passing; renderer-parity script clean.
- 1075 PHP tests passing. The 2 pre-existing failures in `tests/Unit/VisualEditor/Services/Icon/FontAwesomeFreeIconSetsTest.php` ("Class `ArtisanPackUI\\Icons\\Registries\\IconSetRegistration` not found") predate this branch — verified by stashing and re-running. Do NOT spend time on them in this PR.

**Resume here (mac mini, pick up tomorrow):**

1. **Manual browser verification** in the dev app at `~/Herd/artisanpack-ui-dev` (`npm run dev` + `composer run dev`). Insert each of the 4 new blocks from the inserter (ArtisanPack category), exercise the inspector controls, and confirm the editor preview matches expectations:
   - `single-content`: post-id + post-type inputs; canvas previews inner blocks against the host post.
   - `related-posts`: numPosts + columns range controls; canvas previews the inner template once.
   - `author-social-icons` + `social-share-content`: platform checkboxes, chip-style select, direction + stretch selects, border-radius range. Canvas should preview the chips inline.
2. **CodeRabbit CLI loop** — per the user's original instruction, run `cr review --base feature/495-port-crosswinds-blocks --prompt-only`. The base is `feature/495-…`, NOT `main`. Up to 3 passes; re-run tests after each fix pass. Commit fixes as a follow-up commit on this branch.
3. **Open draft PR** targeting `feature/495-port-crosswinds-blocks` (NOT `main`). Use the repo's PR template; include "Closes #501". GitHub remote: `git@github.com:ArtisanPack-UI/visual-editor.git`. Use the `gh` CLI.
4. **PR CodeRabbit loop** — CodeRabbit auto-reviews on PR creation + push (do NOT post `@coderabbitai review` comments). Wait for the bot, address feedback, push. Up to 5 passes or until the "No actionable comments were generated in the recent review. 🎉" done-signal appears.
5. **Report the PR URL** to the user.

**Convention reminders:**

- No `CW[0-9]` labels, no `crosswinds-blocks` proper-noun references in code, comments, asset filenames, or commit messages. Use the issue number `#501`.
- Base branch is `feature/495-port-crosswinds-blocks` everywhere — CodeRabbit base, PR target, the lot. Do not retarget to `main`.
