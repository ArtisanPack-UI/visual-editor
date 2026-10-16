---
title: Translations
---

# Translations

*Since v1.15.1 (#896).*

Every string in the editor UI goes through `__()` with the
`artisanpack-visual-editor` text domain. The package ships the
template at `languages/artisanpack-visual-editor.pot`. To translate the
editor, publish a JSON file per locale. The editor mounts load it
automatically.

## Workflow

1. **Translate the template.** Copy
   `vendor/artisanpack-ui/visual-editor/languages/artisanpack-visual-editor.pot`
   to a `.po` file for your locale (for example `fr.po`) and translate
   it with Poedit, Loco, Weblate or any other PO editor.

2. **Convert it to Jed JSON.** Use
   [`po2json`](https://www.npmjs.com/package/po2json) in Jed 1.x format:

   ```bash
   npx po2json fr.po fr.json -f jed1.x -d artisanpack-visual-editor
   ```

   `wp i18n make-json` writes the same Jed 1.x shape, but it splits a
   `.po` into one file per source script. The editor needs a single file
   per locale, so use `po2json` (or merge the `make-json` output).

3. **Publish it** at `lang/vendor/visual-editor/{locale}.json`:

   ```
   lang/
   └── vendor/
       └── visual-editor/
           ├── fr.json
           └── pt_BR.json
   ```

That's all. `<x-visual-editor>`, the package's `/editor` view and the
site editor resolve the file for the request's locale and stamp it on
the mount as `data-locale-data`.

## Locale resolution

`ArtisanPackUI\VisualEditor\Support\EditorLocaleData::resolve()` takes
the app locale (`app()->getLocale()`) and tries, in order:

1. the locale itself (`pt_BR.json`);
2. its language alone (`pt.json`);
3. the app's `fallback_locale`.

It uses the first file that decodes to a Jed payload with at least one
translation. Without one, no attribute is stamped and the editor stays
in English. Only locale-shaped codes are tried, so a locale can never
point outside `lang/vendor/visual-editor/`.

The `ap.visualEditor.localeData` filter can supply or adjust the data,
for example to load it from somewhere else. See
[[Hooks and Events#apvisualeditorlocaledata]].

## Accepted formats

Both forms work, from a file, the filter or the JS mount:

```json
{
    "domain": "artisanpack-visual-editor",
    "locale_data": {
        "artisanpack-visual-editor": {
            "": { "domain": "artisanpack-visual-editor", "lang": "fr", "plural-forms": "nplurals=2; plural=(n > 1);" },
            "Group": ["Groupe"],
            "%d block": ["%d bloc", "%d blocs"]
        }
    }
}
```

or the bare locale data (the object under `locale_data.{domain}`).
`locale_data.messages`, which some tools write, is read too. Entries
whose translations aren't strings are dropped.

## Custom mounts

Hosts that mount the editor themselves pass the data directly:

- **JS:** `mountEditor( element, { …config, localeData } )` (also
  through the Vue wrapper), or stamp `data-locale-data` (JSON) on a
  `[data-ap-visual-editor]` or `[data-ap-site-editor]` element before
  `bootVisualEditor()` / `bootSiteEditor()`.
- **Blade:** the `<x-visual-editor>` component exposes the resolved data
  as `localeData`, which `ap.visualEditor.editorConfig` callbacks can
  override.

The editor applies the translations before it loads its main bundle,
so strings evaluated when that bundle loads (block variation titles,
the default palette and font-size names) are translated as well as the
panels.

## Scope

This covers the editor UI only. The front-end renderers don't use this
text domain: strings in the Blade partials go through Laravel's own
translator.
