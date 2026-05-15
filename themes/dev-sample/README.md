# dev-sample

Minimal theme that exercises every Phase H surface — used by the H8 smoke
flow as a regression net. See
[`docs/h8-smoke-flow.md`](../../docs/h8-smoke-flow.md) for the step-by-step
walkthrough.

## Layout

```text
themes/dev-sample/
├── theme.json                     manifest (settings + styles + templateParts + menus.locations + customTemplates)
├── templates/
│   └── index.html                 default template, references both parts
├── parts/
│   ├── header.html                site title + nav (primary location)
│   └── footer.html                separator + copyright
├── patterns/
│   ├── hero-cta.php               unsynced (copy-into-canvas on insert)
│   └── footer-credits.php         synced (core/block reference on insert)
└── README.md                      this file
```

## What's intentionally minimal

- **Two template parts** so the smoke flow exercises both file→DB
  override and revert without exhausting authoring.
- **One unsynced + one synced pattern** so the inserter's Patterns tab
  has at least one of each to surface and smoke step C
  (synced/unsynced insertion) is actually exercisable.
- **Two menu locations** declared in `theme.json` so the smoke flow
  can verify location pickers, but only one (primary) is wired into a
  template part — the second proves orphaned locations don't break.
- **Color palette + `styles.elements`** in `theme.json` so the
  GlobalStylesCssProvider has a non-default record to compile from on
  activation.

## Not in scope

Real production styling, screenshot, RTL support, additional templates
(`single`, `archive`, `404`, etc.). Add those if and when the smoke
flow needs them; otherwise leave the surface area minimal so smoke
flow regressions point at the right place.
