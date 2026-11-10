# Code Block

**Block name:** `artisanpack/code`

The Code block shows code with its spacing and tabs preserved. *Since v1.20.0*, authors can pick a language, and the code is highlighted on the published page.

---

## Choosing a language

Select the block and open **Settings → Language** in the sidebar. The options are Bash, Blade, CSS, Go, HTML, JavaScript, JSON, Markdown, PHP, Python, Ruby, Rust, SQL, TypeScript and YAML, plus:

| Option | Result |
|--------|--------|
| **None** | The default. The block saves exactly as it did before languages existed. |
| **Plain text** | Saves `language-plaintext`, so the code is marked as text, but nothing is highlighted. |

The editor canvas shows the select only, and doesn't highlight the code yet.

## Leaving the block

*Since v1.21.0 (#1101).* Pressing Enter at the end of a blank last line removes that line and continues in a new paragraph below, so a double Enter at the end leaves the block. Shift+Enter always adds a newline, and blank lines in the middle of the code work as usual, but a blank line can't be typed at the very end of the block. Preformatted and Poetry blocks behave the same way.

## Markup

A block with a language saves the common `language-*` convention on the `<code>` element:

```html
<pre class="wp-block-code"><code class="language-php">&lt;?php echo 1;</code></pre>
```

The server always sends plain, escaped code, and the highlighter adds its `<span>`s in the browser. Without JavaScript, or until the highlighter loads, the code stays readable. It reads the element's text, so block content can't become markup.

A block with inline formatting, such as a link or bold text, keeps that formatting and isn't highlighted.

## Loading

The highlighter is about 19 KB gzipped and bundles [Prism](https://prismjs.com).

| Renderer | How it loads |
|----------|--------------|
| Blade | `<x-ve-blocks-styles>` prints a stylesheet and a deferred script, and both are removed from pages with no highlighted block. See [Frontend Performance](../frontend-performance.md#per-page-stylesheets). |
| React, Vue | Imported in the browser the first time a highlighted block mounts. It inserts its own `<style data-ve-code-highlight>` tag, which a strict `style-src` Content Security Policy blocks, leaving the tokens uncoloured. |

As with stylesheets, a Blade response that skips the `web` middleware group, or a host with `frontend_styles.conditional` set to `false`, keeps both tags on every page.

A block added after the page loads, such as through a Livewire update, needs two things. Keep the assets on the page with the [`ap.visualEditor.requiredStylesheets`](../Hooks-and-Events.md#apvisualeditorrequiredstylesheets) filter, using `data-ve-code-highlight`, then dispatch `ap:rebind` on `window`.

## Theming

Highlighting works on light and dark backgrounds with no setup. The highlighter measures the solid background behind each block in the browser and picks a light or dark palette, both of which meet WCAG AA. That covers a dark site, a dark section inside a light site, and a block with its own background colour, and it measures again when a theme toggle changes `class`, `style` or `data-theme` on `<html>` or `<body>`.

Behind an image or gradient there's no colour to measure, so the palette follows the text colour: light text picks the dark palette.

If the block's text is unreadable on that background, such as a dark section with no text colour set, a highlighted block replaces it with a readable colour. Blocks with no language aren't changed.

To use your own colours, set `--ap-code-*` custom properties. On `:root` a value applies to every block, and on `[data-ap-code-scheme="dark"]` or `[data-ap-code-scheme="light"]` it applies to one palette:

```css
[data-ap-code-scheme='dark'] {
    --ap-code-keyword: #f0883e;
}
```

| Property | Colours | Light | Dark |
|----------|---------|-------|------|
| `--ap-code-comment` | Comments, doctypes | `#57606a` | `#94a3b8` |
| `--ap-code-keyword` | Keywords, at-rules, Blade directives | `#a0111f` | `#ff7b72` |
| `--ap-code-string` | Strings, attribute values, regular expressions | `#0a3069` | `#a5d6ff` |
| `--ap-code-number` | Numbers, booleans, constants | `#0550ae` | `#79c0ff` |
| `--ap-code-function` | Function names | `#6639ba` | `#d2a8ff` |
| `--ap-code-type` | Class names, built-ins | `#7d4e00` | `#ffa657` |
| `--ap-code-tag` | HTML tags, CSS selectors | `#116329` | `#7ee787` |
| `--ap-code-attribute` | HTML attributes, object and CSS properties | `#953800` | `#ffa657` |
| `--ap-code-variable` | Variables | `#1f4e79` | `#a5d6ff` |
| `--ap-code-punctuation` | Punctuation, operators | Text colour | Text colour |
| `--ap-code-text` | Replacement for unreadable text | `#1f2937` | `#e6edf3` |

The token rules sit in the `ve-code-highlight` cascade layer, so any unlayered rule in your theme beats them. Token elements use `ap-code-*` classes, such as `ap-code-keyword`, so host CSS for names like `.table` can't reach them.

## Contributing

The highlighter's source is in `packages/code-highlight`. After changing it, run `npm run build:code-highlight` to regenerate the files the renderers ship. CI fails if they're out of date. A new language has to be added to the block, each renderer and the highlighter, and `npm run verify:parity` checks that they match.
