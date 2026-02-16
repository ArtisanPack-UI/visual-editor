# Gutenberg Component Porting Guide

This document provides a systematic process for porting Gutenberg components to Livewire equivalents for the ArtisanPack UI Visual Editor package.

## Component Hierarchy: Toolbar System

### Gutenberg Structure

```
Toolbar (Container)
├── ToolbarGroup (Grouping with separator)
│   ├── ToolbarButton (Individual button)
│   ├── ToolbarButton
│   └── ToolbarDropdownMenu (Dropdown with buttons)
└── ToolbarItem (Wrapper for custom controls)

BlockToolbar (Editor-specific toolbar)
├── Toolbar
├── BlockMover (Drag handle)
├── BlockParentSelector
├── BlockControls (Slot for block-specific controls)
└── BlockSettingsMenu
```

### File Locations in Gutenberg

- **Base Components**: `/packages/components/src/toolbar/`
  - `toolbar/` - Main container component
  - `toolbar-button/` - Individual button
  - `toolbar-group/` - Grouping component
  - `toolbar-item/` - Generic item wrapper
  - `toolbar-dropdown-menu/` - Dropdown menu

- **Block-Specific**: `/packages/block-editor/src/components/block-toolbar/`
  - `index.js` - Main BlockToolbar component
  - `style.scss` - Toolbar styling

### Props & Features Mapping

#### Toolbar Component

| Gutenberg Prop | Type | Livewire Equivalent | Notes |
|----------------|------|---------------------|-------|
| `label` | string | `$label` | Required for accessibility |
| `variant` | 'toolbar' \| 'unstyled' | `$variant` | Controls styling context |
| `className` | string | `$class` | Additional CSS classes |

**Features:**
- Accessible keyboard navigation
- Context provider for nested components
- Automatic ARIA labels

#### ToolbarButton Component

| Gutenberg Prop | Type | Livewire Equivalent | Notes |
|----------------|------|---------------------|-------|
| `icon` | IconType | `$icon` | Icon component/name |
| `label` | string | `$label` | Button label (tooltip) |
| `isActive` | boolean | `$isActive` | Pressed/selected state |
| `onClick` | function | `wire:click` | Click handler |
| `disabled` | boolean | `$disabled` | Disabled state |
| `shortcut` | string | `$shortcut` | Keyboard shortcut display |
| `className` | string | `$class` | Additional CSS classes |

**Features:**
- Icon + label combination
- Active/pressed state styling
- Keyboard shortcut display
- Disabled state handling

#### ToolbarGroup Component

| Gutenberg Prop | Type | Livewire Equivalent | Notes |
|----------------|------|---------------------|-------|
| `isCollapsed` | boolean | `$isCollapsed` | Show as dropdown on mobile |
| `title` | string | `$title` | Group label |
| `controls` | array | `$controls` | Array of button configs |

**Features:**
- Visual separator between groups
- Responsive collapse to dropdown
- Grouping related controls

## Porting Process

### Step 1: Analyze the Component

1. **Read the TypeScript/JSX file** to understand:
   - Props and their types
   - State management
   - Event handlers
   - Child components

2. **Read the SCSS file** to understand:
   - CSS classes used
   - Responsive breakpoints
   - Variants and modifiers

3. **Check the README.md** for:
   - Usage examples
   - API documentation
   - Related components

### Step 2: Create the Livewire Component Structure

```php
<?php

/**
 * Visual Editor Toolbar Component
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\Components
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Livewire\Components;

use Livewire\Component;

class Toolbar extends Component
{
    // Public properties (component API)
    public string $label;
    public string $variant = 'unstyled';
    public string $class = '';

    // Internal state
    public array $groups = [];

    // Computed properties
    public function getFinalClassAttribute(): string
    {
        $classes = [
            've-toolbar',
            'components-accessible-toolbar',
        ];

        if ( $this->variant ) {
            $classes[] = "is-{$this->variant}";
        }

        if ( $this->class ) {
            $classes[] = $this->class;
        }

        return implode( ' ', $classes );
    }

    public function render()
    {
        return view( 'visual-editor::livewire.components.toolbar' );
    }
}
```

### Step 3: Create the Blade View

```blade
{{-- resources/views/livewire/components/toolbar.blade.php --}}
<div
    role="toolbar"
    aria-label="{{ $label }}"
    class="{{ $this->finalClassAttribute }}"
    {{ $attributes }}
>
    {{ $slot }}
</div>
```

### Step 4: Port the Styles

```css
/* resources/css/visual-editor/toolbar.css */

/* Base toolbar styles */
.ve-toolbar {
    display: flex;
    flex-grow: 1;
    width: 100%;
    position: relative;
    overflow-y: hidden;
    overflow-x: auto;
    gap: 0;
}

/* Responsive: No scroll on larger screens */
@media (min-width: 782px) {
    .ve-toolbar {
        overflow: inherit;
    }
}

/* Toolbar groups */
.ve-toolbar-group {
    display: flex;
    background: none;
    border: 0;
    border-right: 1px solid theme('colors.base-300');
    margin-top: -1px;
    margin-bottom: -1px;
}

/* Last group: no border */
.ve-toolbar-group:last-child {
    border-right: none;
}

/* Toolbar variant */
.ve-toolbar.is-toolbar {
    /* Variant-specific styles */
}
```

### Step 5: Register the Component

```php
// In VisualEditorServiceProvider.php boot() method
Livewire::component('visual-editor::toolbar', \ArtisanPackUI\VisualEditor\Livewire\Components\Toolbar::class);
Livewire::component('visual-editor::toolbar-button', \ArtisanPackUI\VisualEditor\Livewire\Components\ToolbarButton::class);
Livewire::component('visual-editor::toolbar-group', \ArtisanPackUI\VisualEditor\Livewire\Components\ToolbarGroup::class);
```

### Step 6: Create Usage Examples

```blade
{{-- Usage Example --}}
<livewire:visual-editor::toolbar label="Block Toolbar">
    <livewire:visual-editor::toolbar-group>
        <livewire:visual-editor::toolbar-button
            icon="o-bold"
            label="Bold"
            :isActive="$isBold"
            wire:click="toggleBold"
        />
        <livewire:visual-editor::toolbar-button
            icon="o-italic"
            label="Italic"
            :isActive="$isItalic"
            wire:click="toggleItalic"
        />
    </livewire:visual-editor::toolbar-group>

    <livewire:visual-editor::toolbar-group>
        <livewire:visual-editor::toolbar-button
            icon="o-link"
            label="Link"
            wire:click="openLinkDialog"
        />
    </livewire:visual-editor::toolbar-group>
</livewire:visual-editor::toolbar>
```

## Testing Checklist

After porting a component:

- [ ] Props work as expected
- [ ] Events/actions trigger correctly
- [ ] Styles match Gutenberg appearance
- [ ] Keyboard navigation works
- [ ] Screen reader accessible
- [ ] Responsive behavior correct
- [ ] Works with daisyUI theme
- [ ] Integrates with ArtisanPack UI icons

## Common Patterns

### React State → Livewire State

```javascript
// Gutenberg (React)
const [isActive, setIsActive] = useState(false);
```

```php
// Livewire
public bool $isActive = false;

public function toggle(): void
{
    $this->isActive = !$this->isActive;
}
```

### React Props → Livewire Properties

```jsx
// Gutenberg
<ToolbarButton
    icon={formatBold}
    label="Bold"
    isActive={isBold}
    onClick={() => toggleFormat('bold')}
/>
```

```blade
{{-- Livewire --}}
<livewire:visual-editor::toolbar-button
    icon="o-bold"
    label="Bold"
    :isActive="$isBold"
    wire:click="toggleFormat('bold')"
/>
```

### Context Providers → Livewire Events/Properties

```javascript
// Gutenberg uses Context API
const toolbarState = useContext(ToolbarContext);
```

```php
// Livewire: Use events or parent properties
$this->dispatch('toolbar-action', action: 'bold');

// Or pass data from parent
<livewire:child :parentState="$state" />
```

### Conditional Rendering

```jsx
// Gutenberg
{showIcon && <Icon icon={icon} />}
```

```blade
{{-- Livewire --}}
@if ($showIcon)
    <x-artisanpack-icon name="{{ $icon }}" />
@endif
```

## Priority Components to Port

### Phase 1: Core Toolbar (Week 1)
1. ✅ Toolbar - Base container
2. ⏳ ToolbarButton - Individual button
3. ⏳ ToolbarGroup - Button grouping
4. ⏳ ToolbarItem - Generic wrapper

### Phase 2: Block Toolbar (Week 2)
5. ⏳ BlockToolbar - Editor-specific toolbar
6. ⏳ NavigableToolbar - Keyboard navigation
7. ⏳ BlockControls - Slot system for block controls

### Phase 3: Specialized Controls (Week 3)
8. ⏳ AlignmentControl - Text alignment buttons
9. ⏳ BlockFormatControls - Bold, italic, etc.
10. ⏳ LinkControl - Link editing interface

### Phase 4: Advanced Features (Week 4)
11. ⏳ ToolbarDropdownMenu - Dropdown menus
12. ⏳ BlockMover - Drag and drop controls
13. ⏳ BlockSettingsMenu - Block options menu

## Resources

### Gutenberg Documentation
- [Components Storybook](https://wordpress.github.io/gutenberg/)
- [Toolbar Component Docs](https://developer.wordpress.org/block-editor/reference-guides/components/toolbar/)
- [Block Editor Handbook](https://developer.wordpress.org/block-editor/)

### Package Locations
- Gutenberg Download: `/Users/jacobmartella/Downloads/gutenberg-trunk/`
- Base Components: `packages/components/src/toolbar/`
- Block Components: `packages/block-editor/src/components/`

### Visual Editor Package
- Package Root: `~/Desktop/ArtisanPack UI Packages/visual-editor/`
- Livewire Components: `src/Livewire/Components/`
- Views: `resources/views/livewire/components/`
- Styles: `resources/css/`

## Quick Reference: File Structure

```
visual-editor/
├── src/
│   └── Livewire/
│       └── Components/
│           ├── Toolbar.php
│           ├── ToolbarButton.php
│           ├── ToolbarGroup.php
│           └── BlockToolbar.php
├── resources/
│   ├── views/
│   │   └── livewire/
│   │       └── components/
│   │           ├── toolbar.blade.php
│   │           ├── toolbar-button.blade.php
│   │           ├── toolbar-group.blade.php
│   │           └── block-toolbar.blade.php
│   └── css/
│       └── visual-editor/
│           ├── toolbar.css
│           └── block-toolbar.css
└── docs/
    ├── GUTENBERG-COMPONENT-PORTING.md (this file)
    └── components/
        ├── toolbar.md
        └── toolbar-button.md
```

## Next Steps

1. **Start with Toolbar** - Port the base Toolbar component first
2. **Add ToolbarButton** - Port button component
3. **Test Together** - Verify they work in combination
4. **Add ToolbarGroup** - Complete the basic toolbar system
5. **Build BlockToolbar** - Create editor-specific toolbar
6. **Iterate** - Continue with remaining components

This systematic approach will make porting much faster and more consistent!
