# Typography System

The visual editor includes a comprehensive typography system with two layers: **global typography presets** (site-wide defaults) and **block-level typography controls** (per-block overrides in the inspector). This document covers how developers can customize both.

---

## Table of Contents

1. [Overview](#overview)
2. [Font Collection Registry](#font-collection-registry)
3. [Registering Fonts](#registering-fonts)
4. [Removing & Filtering Fonts](#removing--filtering-fonts)
5. [Font Categories](#font-categories)
6. [Google Fonts](#google-fonts)
7. [Custom Font Files](#custom-font-files)
8. [Global Typography Presets](#global-typography-presets)
9. [Element Presets](#element-presets)
10. [Type Scale](#type-scale)
11. [CSS Custom Properties](#css-custom-properties)
12. [Block-Level Typography Supports](#block-level-typography-supports)
13. [Inspector Typography Panel](#inspector-typography-panel)
14. [Filter Hooks](#filter-hooks)
15. [Helper Functions](#helper-functions)
16. [Configuration Reference](#configuration-reference)

---

## Overview

The typography system is managed by `TypographyPresetsManager`, registered as a singleton at `visual-editor.typography-presets`. It handles:

- **Font collection** — a registry of all available fonts shown in block inspector dropdowns
- **Font family slots** — semantic slots (heading, body, mono) for global font assignments
- **Element presets** — per-element typography styles (h1-h6, body, small, caption, blockquote, code)
- **Type scale** — generate harmonious heading sizes from a base size and ratio
- **Font loading** — Google Fonts URL generation and custom @font-face declarations
- **CSS generation** — output all typography values as CSS custom properties

---

## Font Collection Registry

The font collection is the list of fonts available in block inspector font family dropdowns. It ships with 12 web-safe system fonts and can be extended with custom fonts, Google Fonts, or fonts from packages.

Each font entry has:

| Key        | Type   | Description                                      |
|------------|--------|--------------------------------------------------|
| `name`     | string | Display name shown in dropdowns                  |
| `family`   | string | CSS `font-family` stack (e.g. `"Inter", sans-serif`) |
| `category` | string | `all`, `heading`, or `body` — controls where the font appears |
| `source`   | string | `system`, `custom`, or `google` — for filtering/identification |

### Default System Fonts

These are available out of the box:

- System UI, Arial, Georgia, Times New Roman, Trebuchet MS, Verdana
- Courier New, Palatino, Garamond, Tahoma, Impact, Monospace

---

## Registering Fonts

### Via Config

Add fonts in `config/artisanpack/visual-editor.php`:

```php
'typography_presets' => [
    'fonts' => [
        'brand-serif' => [
            'name'     => 'Brand Serif',
            'family'   => '"Brand Serif", Georgia, serif',
            'category' => 'heading',
            'source'   => 'custom',
        ],
        'brand-sans' => [
            'name'     => 'Brand Sans',
            'family'   => '"Brand Sans", Arial, sans-serif',
            'category' => 'body',
            'source'   => 'custom',
        ],
    ],
],
```

### Via Service Provider

```php
use ArtisanPackUI\VisualEditor\Services\TypographyPresetsManager;

// In AppServiceProvider::boot()
$typography = app( TypographyPresetsManager::class );

// Register individual fonts
$typography->registerFont( 'brand', 'Brand Font', '"Brand Font", sans-serif', 'all', 'custom' );

// Or use the helper
veRegisterFont( 'brand', 'Brand Font', '"Brand Font", sans-serif', 'heading' );
```

### Via Filter Hook (for packages)

```php
addFilter( 'ap.visualEditor.availableFonts', function ( array $fonts ) {
    $fonts['my-package-font'] = [
        'name'     => 'Package Font',
        'family'   => '"Package Font", sans-serif',
        'category' => 'all',
        'source'   => 'custom',
    ];
    return $fonts;
} );
```

---

## Removing & Filtering Fonts

### Remove specific fonts

```php
addFilter( 'ap.visualEditor.availableFonts', function ( array $fonts ) {
    unset( $fonts['impact'], $fonts['tahoma'], $fonts['courier'] );
    return $fonts;
} );
```

### Remove all system fonts (only show custom/Google fonts)

```php
addFilter( 'ap.visualEditor.availableFonts', function ( array $fonts ) {
    return array_filter( $fonts, fn ( $font ) => 'system' !== $font['source'] );
} );
```

### Replace the entire font list

```php
addFilter( 'ap.visualEditor.availableFonts', function () {
    return [
        'brand' => [
            'name'     => 'Brand Font',
            'family'   => '"Brand Font", sans-serif',
            'category' => 'all',
            'source'   => 'custom',
        ],
        // ... only your fonts
    ];
} );
```

### Programmatic removal

```php
$typography = app( 'visual-editor.typography-presets' );
$typography->unregisterFont( 'impact' );
```

---

## Font Categories

Fonts have a `category` that controls where they appear:

| Category  | Behavior |
|-----------|----------|
| `all`     | Appears in all font family dropdowns (default) |
| `heading` | Only appears when the block restricts fonts to heading |
| `body`    | Only appears when the block restricts fonts to body |

**Important:** Fonts with `category: 'all'` always appear in every dropdown, regardless of filtering. So if you have a font that should be available for both headings and body text, use `'all'`.

### Restricting fonts per block

In `block.json`, you can restrict the font dropdown:

```json
{
    "supports": {
        "typography": {
            "fontFamily": true
        }
    }
}
```

This shows all fonts. To restrict:

```json
{
    "supports": {
        "typography": {
            "fontFamily": "heading"
        }
    }
}
```

This shows only fonts with `category: 'heading'` or `category: 'all'`. The `"body"` value works the same way for body fonts.

---

## Google Fonts

Register Google Fonts to both load them and add them to the font collection:

```php
// Basic registration (adds to collection + generates embed URL)
veRegisterGoogleFont( 'Inter', [ '400', '600', '700' ] );

// With italic styles
veRegisterGoogleFont( 'Playfair Display', [ '400', '700' ], [ 'normal', 'italic' ], 'heading' );

// Multiple families
veRegisterGoogleFont( 'Roboto', [ '300', '400', '500', '700' ], [ 'normal' ], 'body' );
veRegisterGoogleFont( 'Roboto Mono', [ '400' ], [ 'normal' ] );
```

### Loading the Google Fonts stylesheet

```php
$typography = app( 'visual-editor.typography-presets' );
$url = $typography->generateGoogleFontsUrl();
// => https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Playfair+Display:ital,wght@0,400;0,700;1,400;1,700&display=swap
```

In Blade:

```blade
@php
    $googleFontsUrl = app( 'visual-editor.typography-presets' )->generateGoogleFontsUrl();
@endphp

@if ( $googleFontsUrl )
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ $googleFontsUrl }}" rel="stylesheet">
@endif
```

---

## Custom Font Files

Register custom font files for `@font-face` generation:

```php
// Basic registration
veRegisterCustomFont( 'MyFont', '/fonts/myfont.woff2' );

// With weight, style, and category
veRegisterCustomFont( 'MyFont', '/fonts/myfont-bold.woff2', '700', 'normal', 'heading' );
veRegisterCustomFont( 'MyFont', '/fonts/myfont-italic.woff2', '400', 'italic' );
```

### Generating @font-face CSS

```php
$css = app( 'visual-editor.typography-presets' )->generateFontFaceDeclarations();
```

Output:

```css
@font-face {
    font-family: 'MyFont';
    src: url('/fonts/myfont.woff2') format('woff2');
    font-weight: 400;
    font-style: normal;
    font-display: swap;
}
```

Supported formats: `.woff2`, `.woff`, `.ttf`, `.otf`, `.eot`, `.svg`

---

## Global Typography Presets

### Font Family Slots

Three semantic slots define the site's primary fonts:

```php
$typography = app( 'visual-editor.typography-presets' );

// Get
$heading = $typography->getFontFamily( 'heading' ); // "Inter, sans-serif"
$body    = $typography->getFontFamily( 'body' );
$mono    = $typography->getFontFamily( 'mono' );

// Set
$typography->setFontFamily( 'heading', '"Playfair Display", serif' );
```

### Via Config

```php
'typography_presets' => [
    'fontFamilies' => [
        'heading' => '"Playfair Display", serif',
        'body'    => '"Source Sans Pro", sans-serif',
        'mono'    => '"Fira Code", monospace',
    ],
],
```

---

## Element Presets

Define typography styles for each text element:

```php
$typography->setElement( 'h1', [
    'fontSize'      => '2.5rem',
    'fontWeight'    => '700',
    'lineHeight'    => '1.2',
    'letterSpacing' => '-0.02em',
] );

// Update a single property
$typography->setElementProperty( 'body', 'lineHeight', '1.8' );
```

### Supported elements

`h1`, `h2`, `h3`, `h4`, `h5`, `h6`, `body`, `small`, `caption`, `blockquote`, `code`

### Supported properties per element

| Property        | Example    | Description |
|-----------------|------------|-------------|
| `fontSize`      | `2.5rem`   | CSS font-size value |
| `fontWeight`    | `700`      | CSS font-weight (100-900 or named) |
| `lineHeight`    | `1.2`      | Unitless or with units |
| `letterSpacing` | `-0.02em`  | CSS letter-spacing |
| `fontStyle`     | `italic`   | CSS font-style |

---

## Type Scale

Generate harmonious heading sizes from a base size and mathematical ratio:

```php
// Generate sizes without applying
$scale = $typography->generateTypeScale( 1.0, 1.25 );
// ['h6' => '1.25rem', 'h5' => '1.563rem', 'h4' => '1.953rem', ...]

// Generate and apply to current element presets
$typography->applyTypeScale( 1.0, 1.25 );
```

### Common type scale ratios

| Name              | Ratio |
|-------------------|-------|
| Minor Third       | 1.2   |
| Major Third       | 1.25  |
| Perfect Fourth    | 1.333 |
| Augmented Fourth  | 1.414 |
| Perfect Fifth     | 1.5   |
| Golden Ratio      | 1.618 |

---

## CSS Custom Properties

The typography system generates CSS custom properties:

```php
// Properties only (no selector)
$css = $typography->generateCssProperties();

// Full :root block
$css = $typography->generateCssBlock();
```

### Generated variable naming

```css
:root {
    /* Font family slots */
    --ve-font-heading: "Playfair Display", serif;
    --ve-font-body: "Source Sans Pro", sans-serif;
    --ve-font-mono: "Fira Code", monospace;

    /* Element styles */
    --ve-text-h1-font-size: 2.5rem;
    --ve-text-h1-font-weight: 700;
    --ve-text-h1-line-height: 1.2;
    --ve-text-h1-letter-spacing: -0.02em;
    --ve-text-body-font-size: 1rem;
    /* ... */
}
```

### Using in CSS

```css
h1 {
    font-family: var(--ve-font-heading);
    font-size: var(--ve-text-h1-font-size);
    font-weight: var(--ve-text-h1-font-weight);
    line-height: var(--ve-text-h1-line-height);
}

body {
    font-family: var(--ve-font-body);
    font-size: var(--ve-text-body-font-size);
}

code {
    font-family: var(--ve-font-mono);
}
```

---

## Block-Level Typography Supports

Blocks opt into typography controls via `block.json`:

```json
{
    "supports": {
        "typography": {
            "fontSize": true,
            "fontFamily": true,
            "appearance": true,
            "lineHeight": true,
            "letterSpacing": true,
            "decoration": true,
            "letterCase": true,
            "dropCap": true
        }
    }
}
```

Each support adds a corresponding attribute to the block and a control to the inspector Styles tab.

### Support → Attribute → Inspector Control mapping

| Support          | Attribute         | Inspector Control       |
|------------------|-------------------|-------------------------|
| `fontSize`       | `fontSize`        | Preset buttons (S/M/L/XL) + custom input |
| `fontFamily`     | `fontFamily`      | Dropdown of available fonts |
| `appearance`     | `fontAppearance`  | Dropdown (weight + italic combos) |
| `lineHeight`     | `lineHeight`      | Number input with +/- buttons |
| `letterSpacing`  | `letterSpacing`   | Number input with px unit |
| `decoration`     | `textDecoration`  | Button group: none, underline, strikethrough |
| `letterCase`     | `textTransform`   | Button group: —, AB, ab, Ab |
| `dropCap`        | `dropCap`         | Toggle switch |

### Partial support

Blocks can enable only the controls they need:

```json
{
    "supports": {
        "typography": {
            "fontSize": true,
            "lineHeight": true
        }
    }
}
```

---

## Inspector Typography Panel

When a block supports typography, a "Typography" panel appears in the Styles tab of the block inspector. The controls render in a WordPress-style layout:

- **Font** — full-width dropdown
- **Font Size** — preset buttons + custom
- **Appearance + Line Height** — side by side (2-column grid)
- **Letter Spacing + Decoration** — side by side (2-column grid)
- **Letter Case** — button group
- **Drop Cap** — toggle (paragraph only)

---

## Filter Hooks

| Hook | Arguments | Description |
|------|-----------|-------------|
| `ap.visualEditor.availableFonts` | `(array $fonts, ?string $category)` | Filter the font collection before it reaches dropdowns. Add, remove, or replace fonts. |
| `ap.visualEditor.typographyFontFamilies` | `(array $families)` | Filter the font family slots (heading/body/mono). |
| `ap.visualEditor.typographyElements` | `(array $elements)` | Filter the element presets. |

---

## Helper Functions

| Function | Description |
|----------|-------------|
| `veRegisterFont( $slug, $name, $family, $category, $source )` | Register a font in the collection |
| `veGetAvailableFonts( ?$category )` | Get all available fonts, optionally filtered |
| `veGetFontOptions( ?$category )` | Get fonts as dropdown options (family => name) |
| `veRegisterGoogleFont( $family, $weights, $styles, $category )` | Register a Google Font |
| `veRegisterCustomFont( $family, $src, $weight, $style, $category )` | Register a custom font file |
| `veGetTypographyPresets()` | Get current presets (families + elements) |
| `veGetFontFamily( $slot )` | Get a font family by slot |
| `veGetTypographyElement( $element )` | Get element typography styles |
| `veGenerateTypographyCss()` | Generate full CSS :root block |

---

## Configuration Reference

Full config structure in `config/artisanpack/visual-editor.php`:

```php
'typography_presets' => [
    // Font family slots
    'fontFamilies' => [
        'heading' => '"Playfair Display", serif',
        'body'    => '"Source Sans Pro", sans-serif',
        'mono'    => '"Fira Code", monospace',
    ],

    // Element presets
    'elements' => [
        'h1' => [
            'fontSize'      => '3rem',
            'fontWeight'    => '800',
            'lineHeight'    => '1.1',
            'letterSpacing' => '-0.02em',
        ],
        'body' => [
            'fontSize'   => '1.125rem',
            'fontWeight' => '400',
            'lineHeight' => '1.7',
        ],
        // ...
    ],

    // Font collection (added to the default system fonts)
    'fonts' => [
        'brand-serif' => [
            'name'     => 'Brand Serif',
            'family'   => '"Brand Serif", Georgia, serif',
            'category' => 'heading',  // 'all', 'heading', or 'body'
            'source'   => 'custom',   // 'system', 'custom', or 'google'
        ],
    ],
],
```
