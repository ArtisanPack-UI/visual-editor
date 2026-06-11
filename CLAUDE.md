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
