/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Area::Frontend" ~"Phase::4"

## Problem Statement

**Is your feature request related to a problem?**
Sites need consistent styling through global design tokens (colors, typography, spacing) that can be customized and applied site-wide.

## Proposed Solution

**What would you like to happen?**
Implement a global styles system with design tokens and Tailwind CSS integration:

### Global Styles Manager

```php
namespace ArtisanPackUI\VisualEditor\Styles;

class GlobalStylesManager
{
    public function get(string $key): mixed;
    public function set(string $key, mixed $value): void;
    public function getAll(): array;
    public function reset(string $key): void;
    public function resetAll(): void;
    public function generateCSS(): string;
    public function exportToTailwind(): array;
}
```

### Design Token Categories

**Colors:**
```php
[
    'colors' => [
        'primary' => '#3b82f6',
        'secondary' => '#6366f1',
        'accent' => '#f59e0b',
        'neutral' => '#6b7280',
        'base' => '#ffffff',
        'text' => '#1f2937',
        'text-muted' => '#6b7280',
        'success' => '#10b981',
        'warning' => '#f59e0b',
        'error' => '#ef4444',
        'info' => '#3b82f6',
    ],
]
```

**Typography:**
```php
[
    'typography' => [
        'font-family-sans' => 'Inter, system-ui, sans-serif',
        'font-family-serif' => 'Georgia, serif',
        'font-family-mono' => 'JetBrains Mono, monospace',
        'font-size-base' => '16px',
        'font-size-scale' => 1.25, // Major third
        'line-height-base' => 1.6,
        'heading-font' => 'font-family-sans',
        'heading-weight' => '700',
        'body-font' => 'font-family-sans',
    ],
]
```

**Spacing:**
```php
[
    'spacing' => [
        'unit' => '4px',
        'scale' => [1, 2, 3, 4, 5, 6, 8, 10, 12, 16, 20, 24],
        'section-padding' => 'spacing-16',
        'container-padding' => 'spacing-4',
    ],
]
```

**Layout:**
```php
[
    'layout' => [
        'content-width' => '1200px',
        'narrow-width' => '720px',
        'wide-width' => '1400px',
        'border-radius' => '8px',
        'shadow' => '0 4px 6px -1px rgba(0,0,0,0.1)',
    ],
]
```

### CSS Variable Generation

```php
public function generateCSS(): string
{
    return <<<CSS
    :root {
        --ve-color-primary: {$this->get('colors.primary')};
        --ve-color-secondary: {$this->get('colors.secondary')};
        --ve-font-sans: {$this->get('typography.font-family-sans')};
        --ve-spacing-unit: {$this->get('spacing.unit')};
        /* ... */
    }
    CSS;
}
```

### Tailwind Integration

```php
// Export to tailwind.config.js format
public function exportToTailwind(): array
{
    return [
        'colors' => [
            'primary' => 'var(--ve-color-primary)',
            'secondary' => 'var(--ve-color-secondary)',
            // ...
        ],
        'fontFamily' => [
            'sans' => 'var(--ve-font-sans)',
            // ...
        ],
    ];
}
```

### Style Editor UI

- Color picker with palette management
- Typography preview with font stacks
- Spacing visualizer
- Live preview of changes
- Reset to defaults option
- Export/import functionality

## Alternatives Considered

- Tailwind config only (rejected: not user-editable)
- Per-block styling only (rejected: inconsistent)
- Theme JSON file (rejected: not visual)

## Use Cases

1. Designer customizes site color palette
2. User changes heading font family
3. Developer exports styles to Tailwind config
4. Changes apply site-wide instantly

## Acceptance Criteria

- [ ] Global styles can be get/set via manager
- [ ] CSS variables are generated correctly
- [ ] Style editor UI allows visual editing
- [ ] Color picker works with palette
- [ ] Typography settings apply to content
- [ ] Spacing values work correctly
- [ ] Reset functionality works
- [ ] Tailwind export generates valid config
- [ ] Live preview shows changes

---

**Related Issues:**
- Depends on: Database migrations
- Blocks: Block styling integration
