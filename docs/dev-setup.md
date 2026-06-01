# Dev-app setup

Short reference for anyone spinning up the visual editor against the
ArtisanPack UI dev app (`~/Herd/artisanpack-ui`) or another host app
running the package in-place.

## Seeding sample content (B2)

Phase B2 (`#354`) ships a set of fixtures that cover every entity the B1
core-data shim (`#353`) knows how to read or write: templates, template
parts, navigation menus, patterns (synced + unsynced), and the
singleton global styles record. Seeding them once gives Phase C/D work
something concrete to render against without hand-authoring JSON into
the database for every new contributor.

### Run

```bash
php artisan visual-editor:seed-sample-content
```

The command is idempotent — re-running it produces the same files on
disk. Stale fixtures (entries removed from the fixtures directory
between runs) are pruned.

### What lands

By default the command writes to the host app's default filesystem
disk (`storage/app/visual-editor/sample-content/…` on the `local`
disk) so that Phase C seeders can pick up the payloads without a
second fixtures round-trip:

```text
storage/app/visual-editor/sample-content/
├── postType/
│   ├── wp_template/{1,2,3,4}.json         # single, page, index, 404
│   ├── wp_template_part/{10,11,12}.json   # header, footer, sidebar
│   ├── wp_navigation/{20,21,22}.json      # primary, footer, nested
│   └── wp_block/{30,31,32}.json           # hero (synced), pricing-row, call-out-pair
└── root/
    └── globalStyles/40.json               # theme.json singleton
```

The shapes match the contract in
[`docs/core-data-shim.md`](./core-data-shim.md) — every record
round-trips through `fetchEntityRecord` without munging.

### Options

- `--path=/absolute/path/to/fixtures` — point the seeder at a custom
  fixtures directory with the same sub-folder layout (one folder per
  REST URL fragment).
- `--disk=public` — write to a non-default filesystem disk.

Set `artisanpack.visual-editor.sample_content.fixtures_path` in
`config/artisanpack/visual-editor.php` to persist a custom directory
across runs.

### Where the fixtures live

Canonical fixtures ship under the package's
`tests/Fixtures/sample-content/` directory (excluded from the
distributed composer tarball via `.gitattributes`). Host apps that
want to vendor their own can copy the tree into, for example,
`database/seeders/fixtures/visual-editor/` and point `--path` at it.

## Breakpoints (#487)

The editor reads its registered breakpoints from the same hierarchy
documented in [`theming.md`](theming.md#breakpoints-487): theme.json →
config → package defaults. The JS bootstrap snapshot exposes the
resolved registry on `window.artisanpackVisualEditor.settings`, and
block edit components hydrate a client-side `BreakpointRegistry`
through `registryFromSnapshot()`.

See [`responsive-design-tools.md`](responsive-design-tools.md) for the
editor + developer workflow.
