# Visual Editor - Global Styles System

## Overview

The global styles system provides Tailwind CSS integration with visual customization. Design tokens are managed through the editor and sync with Tailwind's configuration.

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Style Editor UI                           │
│  (Colors, Typography, Spacing, Components)                   │
└─────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                    Design Tokens                             │
│  (Stored in database as JSON)                                │
└─────────────────────────────────────────────────────────────┘
                           │
            ┌──────────────┴──────────────┐
            ▼                             ▼
┌───────────────────────┐    ┌───────────────────────┐
│  CSS Custom Properties │    │  Tailwind Config Sync │
│  (Runtime values)      │    │  (Build-time JIT)     │
└───────────────────────┘    └───────────────────────┘
```

## Design Tokens

### Token Categories

```php
// Database structure for design tokens

[
    'colors' => [
        'primary' => [
            '50' => '#eff6ff',
            '100' => '#dbeafe',
            '200' => '#bfdbfe',
            '300' => '#93c5fd',
            '400' => '#60a5fa',
            '500' => '#3b82f6',
            '600' => '#2563eb',
            '700' => '#1d4ed8',
            '800' => '#1e40af',
            '900' => '#1e3a8a',
            '950' => '#172554',
        ],
        'secondary' => [...],
        'accent' => [...],
        'neutral' => [...],
        'success' => [...],
        'warning' => [...],
        'error' => [...],
        'info' => [...],
    ],
    'typography' => [
        'font_family' => [
            'heading' => 'Inter, sans-serif',
            'body' => 'Inter, sans-serif',
            'mono' => 'JetBrains Mono, monospace',
        ],
        'font_size' => [
            'xs' => '0.75rem',
            'sm' => '0.875rem',
            'base' => '1rem',
            'lg' => '1.125rem',
            'xl' => '1.25rem',
            '2xl' => '1.5rem',
            '3xl' => '1.875rem',
            '4xl' => '2.25rem',
            '5xl' => '3rem',
            '6xl' => '3.75rem',
        ],
        'font_weight' => [
            'normal' => '400',
            'medium' => '500',
            'semibold' => '600',
            'bold' => '700',
        ],
        'line_height' => [
            'tight' => '1.25',
            'normal' => '1.5',
            'relaxed' => '1.75',
        ],
    ],
    'spacing' => [
        'section_padding' => [
            'small' => '2rem',
            'medium' => '4rem',
            'large' => '6rem',
        ],
        'container_padding' => '1rem',
        'gap' => [
            'small' => '0.5rem',
            'medium' => '1rem',
            'large' => '2rem',
        ],
    ],
    'layout' => [
        'container_width' => '1280px',
        'content_width' => '768px',
        'wide_width' => '1024px',
    ],
    'borders' => [
        'radius' => [
            'none' => '0',
            'sm' => '0.125rem',
            'default' => '0.25rem',
            'md' => '0.375rem',
            'lg' => '0.5rem',
            'xl' => '0.75rem',
            '2xl' => '1rem',
            'full' => '9999px',
        ],
        'width' => [
            'default' => '1px',
            'thick' => '2px',
        ],
    ],
    'shadows' => [
        'sm' => '0 1px 2px 0 rgb(0 0 0 / 0.05)',
        'default' => '0 1px 3px 0 rgb(0 0 0 / 0.1)',
        'md' => '0 4px 6px -1px rgb(0 0 0 / 0.1)',
        'lg' => '0 10px 15px -3px rgb(0 0 0 / 0.1)',
        'xl' => '0 20px 25px -5px rgb(0 0 0 / 0.1)',
    ],
]
```

### Token Storage

```php
// ve_global_styles table

Schema::create('ve_global_styles', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->json('value');
    $table->json('theme_default')->nullable(); // Original theme value
    $table->boolean('is_customized')->default(false);
    $table->timestamps();
});
```

---

## Tailwind Integration

### CSS Custom Properties Generation

```php
class StyleGenerator
{
    public function generateCSSVariables(): string
    {
        $tokens = GlobalStyle::all()->pluck('value', 'key');
        $css = ":root {\n";

        // Colors
        foreach ($tokens['colors'] ?? [] as $name => $shades) {
            foreach ($shades as $shade => $value) {
                $css .= "  --color-{$name}-{$shade}: {$value};\n";
            }
        }

        // Typography
        foreach ($tokens['typography']['font_family'] ?? [] as $name => $value) {
            $css .= "  --font-{$name}: {$value};\n";
        }

        // Spacing
        foreach ($tokens['spacing']['section_padding'] ?? [] as $name => $value) {
            $css .= "  --section-padding-{$name}: {$value};\n";
        }

        // Layout
        $css .= "  --container-width: {$tokens['layout']['container_width']};\n";
        $css .= "  --content-width: {$tokens['layout']['content_width']};\n";

        $css .= "}\n";

        return $css;
    }
}
```

### Tailwind Config Sync

The system generates a JavaScript file that extends Tailwind config:

```php
class TailwindIntegration
{
    public function generateTailwindPreset(): string
    {
        $tokens = GlobalStyle::all()->pluck('value', 'key');

        $preset = [
            'theme' => [
                'extend' => [
                    'colors' => $this->formatColors($tokens['colors'] ?? []),
                    'fontFamily' => $this->formatFontFamily($tokens['typography']['font_family'] ?? []),
                    'fontSize' => $this->formatFontSize($tokens['typography']['font_size'] ?? []),
                    'spacing' => $this->formatSpacing($tokens['spacing'] ?? []),
                    'borderRadius' => $tokens['borders']['radius'] ?? [],
                    'boxShadow' => $tokens['shadows'] ?? [],
                ],
            ],
        ];

        return "export default " . json_encode($preset, JSON_PRETTY_PRINT);
    }

    protected function formatColors(array $colors): array
    {
        $formatted = [];
        foreach ($colors as $name => $shades) {
            $formatted[$name] = [];
            foreach ($shades as $shade => $value) {
                // Support CSS variables for runtime changes
                $formatted[$name][$shade] = "var(--color-{$name}-{$shade}, {$value})";
            }
        }
        return $formatted;
    }
}
```

### Build Integration

```javascript
// tailwind.config.js

import visualEditorPreset from './storage/visual-editor/tailwind-preset.js'

export default {
  presets: [visualEditorPreset],
  content: [
    './resources/views/**/*.blade.php',
    './vendor/artisanpack-ui/**/*.blade.php',
  ],
}
```

---

## Style Editor UI

### Color Editor

```php
// Livewire component for color editing

class ColorEditor extends Component
{
    public array $colors = [];
    public ?string $activeColor = null;
    public ?string $activeShade = null;

    public function mount()
    {
        $this->colors = GlobalStyle::where('key', 'colors')->first()?->value ?? [];
    }

    public function updateColor(string $colorName, string $shade, string $value)
    {
        $this->colors[$colorName][$shade] = $value;
        $this->saveColors();
    }

    public function generatePalette(string $colorName, string $baseColor)
    {
        // Generate full color palette from base color
        $this->colors[$colorName] = $this->generateColorScale($baseColor);
        $this->saveColors();
    }

    protected function generateColorScale(string $baseColor): array
    {
        // Algorithm to generate 50-950 scale from a base color
        $hsl = $this->hexToHsl($baseColor);

        return [
            '50' => $this->hslToHex($hsl['h'], $hsl['s'] * 0.3, 97),
            '100' => $this->hslToHex($hsl['h'], $hsl['s'] * 0.4, 94),
            '200' => $this->hslToHex($hsl['h'], $hsl['s'] * 0.5, 86),
            '300' => $this->hslToHex($hsl['h'], $hsl['s'] * 0.6, 77),
            '400' => $this->hslToHex($hsl['h'], $hsl['s'] * 0.7, 66),
            '500' => $baseColor, // Base color
            '600' => $this->hslToHex($hsl['h'], $hsl['s'] * 1.1, 45),
            '700' => $this->hslToHex($hsl['h'], $hsl['s'] * 1.2, 37),
            '800' => $this->hslToHex($hsl['h'], $hsl['s'] * 1.3, 27),
            '900' => $this->hslToHex($hsl['h'], $hsl['s'] * 1.4, 20),
            '950' => $this->hslToHex($hsl['h'], $hsl['s'] * 1.5, 12),
        ];
    }

    protected function saveColors()
    {
        GlobalStyle::updateOrCreate(
            ['key' => 'colors'],
            ['value' => $this->colors, 'is_customized' => true]
        );

        $this->dispatch('styles-updated');
    }
}
```

### Typography Editor

```php
class TypographyEditor extends Component
{
    public array $typography = [];
    public array $availableFonts = [];

    public function mount()
    {
        $this->typography = GlobalStyle::where('key', 'typography')->first()?->value ?? [];
        $this->availableFonts = $this->loadAvailableFonts();
    }

    protected function loadAvailableFonts(): array
    {
        // Load from Google Fonts API or config
        return [
            'Inter' => 'Inter, sans-serif',
            'Roboto' => 'Roboto, sans-serif',
            'Open Sans' => 'Open Sans, sans-serif',
            'Lato' => 'Lato, sans-serif',
            'Playfair Display' => 'Playfair Display, serif',
            'Merriweather' => 'Merriweather, serif',
            'JetBrains Mono' => 'JetBrains Mono, monospace',
        ];
    }

    public function updateFontFamily(string $type, string $font)
    {
        $this->typography['font_family'][$type] = $this->availableFonts[$font];
        $this->saveTypography();
    }

    public function updateFontSize(string $size, string $value)
    {
        $this->typography['font_size'][$size] = $value;
        $this->saveTypography();
    }
}
```

### Spacing Editor

```php
class SpacingEditor extends Component
{
    public array $spacing = [];

    public function mount()
    {
        $this->spacing = GlobalStyle::where('key', 'spacing')->first()?->value ?? [];
    }

    public function updateSectionPadding(string $size, string $value)
    {
        $this->spacing['section_padding'][$size] = $value;
        $this->saveSpacing();
    }

    public function updateContainerWidth(string $value)
    {
        $layout = GlobalStyle::where('key', 'layout')->first()?->value ?? [];
        $layout['container_width'] = $value;

        GlobalStyle::updateOrCreate(
            ['key' => 'layout'],
            ['value' => $layout, 'is_customized' => true]
        );

        $this->dispatch('styles-updated');
    }
}
```

---

## Theme Inheritance

User customizations layer on top of theme defaults:

```php
class ThemeInheritance
{
    public function getEffectiveValue(string $key): mixed
    {
        $style = GlobalStyle::where('key', $key)->first();

        if (!$style) {
            return $this->getThemeDefault($key);
        }

        if (!$style->is_customized) {
            return $style->theme_default ?? $style->value;
        }

        return $style->value;
    }

    public function resetToThemeDefault(string $key): void
    {
        $style = GlobalStyle::where('key', $key)->first();

        if ($style && $style->theme_default) {
            $style->update([
                'value' => $style->theme_default,
                'is_customized' => false,
            ]);
        }
    }

    public function applyThemeUpdate(string $key, mixed $newDefault): void
    {
        $style = GlobalStyle::where('key', $key)->first();

        if (!$style) {
            GlobalStyle::create([
                'key' => $key,
                'value' => $newDefault,
                'theme_default' => $newDefault,
                'is_customized' => false,
            ]);
            return;
        }

        // Update theme default but preserve user customization
        $style->update(['theme_default' => $newDefault]);

        // If not customized, also update the value
        if (!$style->is_customized) {
            $style->update(['value' => $newDefault]);
        }
    }
}
```

---

## JIT Compilation Workflow

Since Tailwind JIT requires a build step, the workflow is:

1. **Editor changes** → Design tokens saved to database
2. **CSS variables generated** → Runtime changes work immediately
3. **Tailwind preset exported** → On save/publish
4. **Build triggered** → `npm run build` for production classes
5. **Fallback system** → CSS variables provide immediate preview

```php
class StyleCompiler
{
    public function compileAndDeploy(): void
    {
        // Generate CSS variables (immediate)
        $css = $this->generateCSSVariables();
        Storage::put('visual-editor/custom-styles.css', $css);

        // Generate Tailwind preset (for build)
        $preset = $this->generateTailwindPreset();
        Storage::put('visual-editor/tailwind-preset.js', $preset);

        // Trigger build if in production
        if (app()->environment('production')) {
            Artisan::call('visual-editor:compile-styles');
        }
    }
}
```

---

## Configuration

```php
// config/visual-editor.php

'styles' => [
    // Enable global styles editing
    'enabled' => true,

    // Allow custom color creation
    'allow_custom_colors' => true,

    // Available Google Fonts
    'google_fonts' => [
        'Inter', 'Roboto', 'Open Sans', 'Lato',
        'Playfair Display', 'Merriweather',
    ],

    // Preset color palettes
    'color_presets' => [
        'blue' => '#3b82f6',
        'green' => '#22c55e',
        'purple' => '#8b5cf6',
        'red' => '#ef4444',
        'orange' => '#f97316',
    ],

    // Lock certain style categories
    'locked_categories' => [],

    // Auto-compile on save
    'auto_compile' => true,
],
```
