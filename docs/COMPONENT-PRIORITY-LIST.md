# Visual Editor: Gutenberg Component Priority List

This document identifies the most important Gutenberg components to port to Livewire for the ArtisanPack UI Visual Editor, organized by category and priority.

## Component Categories

### 🎯 **Critical Path** (Required for MVP)
Components needed for basic block editing functionality.

### ⭐ **High Priority** (Phase 2)
Components that significantly enhance the editing experience.

### 🔧 **Medium Priority** (Phase 3-4)
Useful components for advanced features.

### 💡 **Nice to Have** (Phase 5+)
Components that add polish and specialized functionality.

---

## 🎯 Critical Path Components (MVP - Weeks 1-4)

### From `packages/components/src/`

| Component | Purpose | Complexity | Gutenberg Path | Notes |
|-----------|---------|------------|----------------|-------|
| **Button** | Primary interactive element | Low | `button/` | Foundation for all clickable actions |
| **Toolbar** | Toolbar container | Low | `toolbar/toolbar/` | ✅ Already documented in porting guide |
| **ToolbarButton** | Individual toolbar button | Low | `toolbar/toolbar-button/` | ✅ Already documented |
| **ToolbarGroup** | Groups toolbar buttons | Low | `toolbar/toolbar-group/` | ✅ Already documented |
| **Panel** | Collapsible settings panels | Medium | `panel/` | Settings sidebar foundation |
| **PanelBody** | Individual panel section | Low | `panel/panel-body/` | Used within Panel |
| **PanelRow** | Panel content row | Low | `panel/panel-row/` | Layout for panel content |
| **Popover** | Floating positioned content | High | `popover/` | Used for tooltips, dropdowns, menus |
| **Dropdown** | Dropdown menu wrapper | Medium | `dropdown/` | Combines button + popover |
| **Modal** | Full-screen overlay dialogs | Medium | `modal/` | For settings, confirmations |
| **BaseControl** | Form control wrapper | Low | `base-control/` | Foundation for all form inputs |
| **TextControl** | Text input field | Low | `text-control/` | Basic text editing |
| **TextareaControl** | Multi-line text input | Low | `textarea-control/` | Longer text content |
| **SelectControl** | Dropdown select | Low | `select-control/` | Option selection |
| **ToggleControl** | Toggle switch | Low | `toggle-control/` | Boolean settings |
| **RangeControl** | Slider input | Medium | `range-control/` | Numeric ranges |
| **Icon** | Icon display | Low | Various | Icon rendering (use ArtisanPack icons) |
| **Spinner** | Loading indicator | Low | `spinner/` | Loading states |
| **Notice** | Notification/alert | Low | `notice/` | User feedback |
| **Placeholder** | Empty state component | Low | `placeholder/` | Block placeholders |

### From `packages/block-editor/src/components/`

| Component | Purpose | Complexity | Path | Notes |
|-----------|---------|------------|------|-------|
| **BlockToolbar** | Block-specific toolbar | Medium | `block-toolbar/` | Contextual block controls |
| **BlockInspector** | Block settings sidebar | Medium | `block-inspector/` | Settings panel for selected block |
| **BlockList** | Renders list of blocks | High | `block-list/` | Core editor canvas |
| **BlockPreview** | Block preview rendering | Medium | `block-preview/` | Visual block representation |
| **BlockControls** | Slot for block toolbar items | Medium | `block-controls/` | Extensibility for block controls |
| **InspectorControls** | Slot for inspector settings | Medium | `inspector-controls/` | Settings sidebar extensibility |
| **WritingFlow** | Keyboard/selection handling | High | `writing-flow/` | Text editing flow |
| **BlockMover** | Drag handle for blocks | Medium | `block-mover/` | Block reordering |
| **Inserter** | Block inserter/library | High | `inserter/` | Add new blocks interface |
| **MediaPlaceholder** | Media upload placeholder | Medium | `media-placeholder/` | Image/video uploads |

**MVP Estimated Total:** 30 components

---

## ⭐ High Priority Components (Phase 2 - Weeks 5-8)

### Form Controls (Settings)

| Component | Purpose | Complexity | Path |
|-----------|---------|------------|------|
| **ColorPicker** | Color selection | Medium | `components/src/color-picker/` |
| **ColorPalette** | Predefined color options | Low | `components/src/color-palette/` |
| **FontSizePicker** | Font size selector | Low | `components/src/font-size-picker/` |
| **UnitControl** | Value + unit input (px, %, em) | Medium | `components/src/unit-control/` |
| **NumberControl** | Numeric input with steppers | Low | `components/src/number-control/` |
| **RadioControl** | Radio button group | Low | `components/src/radio-control/` |
| **CheckboxControl** | Checkbox input | Low | `components/src/checkbox-control/` |
| **ComboboxControl** | Searchable select | Medium | `components/src/combobox-control/` |
| **CustomSelectControl** | Styled select dropdown | Medium | `components/src/custom-select-control-v2/` |

### Layout & Structure

| Component | Purpose | Complexity | Path |
|-----------|---------|------------|------|
| **Card** | Container with header/body/footer | Low | `components/src/card/` |
| **Flex** | Flexbox layout utility | Low | `components/src/flex/` |
| **Grid** | Grid layout (if exists) | Low | `components/src/grid/` |
| **Spacer** | Spacing utility | Low | `components/src/spacer/` |
| **Divider** | Visual separator | Low | `components/src/divider/` |
| **TabPanel** | Tabbed interface | Medium | `components/src/tab-panel/` |

### Block Editor Enhancements

| Component | Purpose | Complexity | Path |
|-----------|---------|------------|------|
| **AlignmentControl** | Text alignment toolbar | Low | `block-editor/src/components/alignment-control/` |
| **BlockAlignmentControl** | Block alignment (wide, full) | Low | `block-editor/src/components/block-alignment-control/` |
| **URLInput** | Link URL input with search | High | `block-editor/src/components/url-input/` |
| **URLPopover** | Link editing popover | Medium | `block-editor/src/components/url-popover/` |
| **LinkControl** | Complete link editor | High | `components/src/link-control/` |
| **BlockCard** | Block preview card | Low | `block-editor/src/components/block-card/` |
| **BlockIcon** | Block type icon | Low | `block-editor/src/components/block-icon/` |

**Phase 2 Estimated Total:** 24 components

---

## 🔧 Medium Priority Components (Phase 3-4 - Weeks 9-16)

### Advanced Form Controls

| Component | Purpose | Complexity | Path |
|-----------|---------|------------|------|
| **BorderControl** | Border style editor | High | `components/src/border-control/` |
| **BoxControl** | Padding/margin editor (4 sides) | Medium | `components/src/box-control/` |
| **DimensionControl** | Width/height control | Medium | Various |
| **GradientPicker** | Gradient color picker | High | `components/src/gradient-picker/` |
| **DuotonePicker** | Duotone filter picker | High | `components/src/duotone-picker/` |
| **FocalPointPicker** | Image focal point selector | High | `components/src/focal-point-picker/` |
| **AnglePicker** | Rotation angle picker | Medium | `components/src/angle-picker-control/` |
| **DateTimePicker** | Date and time selection | High | `components/src/date-time/` |
| **TimePicker** | Time selection | Medium | `components/src/date-time/time/` |

### Rich Content Editing

| Component | Purpose | Complexity | Path |
|-----------|---------|------------|------|
| **RichText** | Rich text editor component | Very High | `block-editor/src/components/rich-text/` |
| **Autocomplete** | Autocomplete suggestions | High | `components/src/autocomplete/` |
| **FormTokenField** | Tag/token input field | Medium | `components/src/form-token-field/` |

### UI Feedback

| Component | Purpose | Complexity | Path |
|-----------|---------|------------|------|
| **Snackbar** | Toast notification | Low | `components/src/snackbar/` |
| **ProgressBar** | Progress indicator | Low | `components/src/progress-bar/` |
| **Tip** | Helpful tip callout | Low | `components/src/tip/` |
| **Tooltip** | Hover tooltip | Medium | `components/src/tooltip/` |
| **ConfirmDialog** | Confirmation modal | Low | `components/src/confirm-dialog/` |

### Navigation & Organization

| Component | Purpose | Complexity | Path |
|-----------|---------|------------|------|
| **TreeSelect** | Hierarchical select | Medium | `components/src/tree-select/` |
| **TreeGrid** | Hierarchical grid/list | High | `components/src/tree-grid/` |
| **NavigableContainer** | Keyboard navigation | Medium | `components/src/navigable-container/` |
| **Menu** | Menu component | Medium | `components/src/menu/` |
| **MenuItem** | Individual menu item | Low | `components/src/menu-item/` |
| **DropdownMenu** | Menu in dropdown | Medium | `components/src/dropdown-menu/` |

### Block Editor Advanced

| Component | Purpose | Complexity | Path |
|-----------|---------|------------|------|
| **BlockNavigation** | Block outline/tree view | High | `block-editor/src/components/block-navigation/` |
| **ListView** | Hierarchical block list | High | `block-editor/src/components/list-view/` |
| **BlockPatternsList** | Pattern library grid | Medium | `block-editor/src/components/block-patterns-list/` |
| **BlockManager** | Enable/disable block types | Medium | `block-editor/src/components/block-manager/` |
| **BlockLock** | Lock/unlock controls | Low | `block-editor/src/components/block-lock/` |
| **ResizableBox** | Resizable container | High | `components/src/resizable-box/` |
| **Draggable** | Drag and drop wrapper | High | `components/src/draggable/` |
| **DropZone** | Drop target | Medium | `components/src/drop-zone/` |

**Phase 3-4 Estimated Total:** 35 components

---

## 💡 Nice to Have Components (Phase 5+ - Future)

### Specialized Inputs

- **AlignmentMatrixControl** - 9-grid alignment selector
- **CircularOptionPicker** - Circular option selector
- **CustomGradientPicker** - Advanced gradient editor
- **PaletteEdit** - Color palette editor
- **QueryControls** - Query builder interface

### Media & Visuals

- **Gallery** - Image gallery component
- **VideoPlayer** - Video player with controls
- **AudioPlayer** - Audio player with controls
- **ClipboardButton** - Copy to clipboard
- **ExternalLink** - External link with icon

### Layout Utilities

- **Animate** - Animation wrapper
- **ScrollLock** - Prevent scroll
- **VisuallyHidden** - Screen reader only content
- **Surface** - Styled surface container
- **Elevation** - Shadow/elevation utility
- **VStack** / **HStack** - Vertical/horizontal stacks
- **ZStack** - Layered stack

### Advanced Block Editor

- **BlockCompare** - Compare block versions
- **BlockBreadcrumb** - Breadcrumb navigation
- **BlockPatternSetup** - Pattern setup wizard
- **MultiSelectionInspector** - Multi-block settings
- **TypeWriter** - Typewriter mode
- **ObserveTyping** - Typing observation

---

## Component Complexity Legend

- **Low**: 1-2 hours to port (simple props, minimal logic)
- **Medium**: 4-8 hours to port (moderate props, some state management)
- **High**: 1-3 days to port (complex interactions, extensive logic)
- **Very High**: 1+ weeks to port (core editor functionality, heavy dependencies)

---

## Recommended Porting Order

### Week 1-2: Foundation
1. Button
2. Icon (adapt to ArtisanPack icons)
3. Spinner
4. BaseControl
5. Panel / PanelBody / PanelRow
6. Toolbar (already documented)
7. ToolbarButton (already documented)
8. ToolbarGroup (already documented)

### Week 3-4: Basic Controls
9. TextControl
10. TextareaControl
11. SelectControl
12. ToggleControl
13. CheckboxControl
14. RadioControl
15. RangeControl
16. NumberControl

### Week 5-6: Popups & Modals
17. Popover (critical for many components)
18. Dropdown
19. Modal
20. DropdownMenu
21. Tooltip

### Week 7-8: Block Editor Core
22. BlockList
23. BlockToolbar
24. BlockInspector
25. BlockControls
26. InspectorControls
27. BlockMover
28. Placeholder
29. BlockPreview

### Week 9-10: Inserter & Media
30. Inserter
31. MediaPlaceholder
32. URLInput
33. LinkControl

### Week 11-12: Advanced Controls
34. ColorPicker / ColorPalette
35. FontSizePicker
36. UnitControl
37. BoxControl
38. BorderControl

### Week 13-16: Layout & Polish
39. Card
40. Flex / Spacer / Divider
41. TabPanel
42. Notice / Snackbar
43. ConfirmDialog
44. TreeSelect
45. Menu / MenuItem

---

## Component Dependencies

Some components depend on others. Port dependencies first:

```
Button
├── Icon
└── Spinner (for loading state)

Popover (many components need this!)
├── Dropdown
├── DropdownMenu
├── Tooltip
└── Modal

Panel
├── PanelBody
└── PanelRow

Toolbar
├── ToolbarGroup
├── ToolbarButton
└── ToolbarItem

BaseControl (foundation for form controls)
├── TextControl
├── TextareaControl
├── SelectControl
├── ToggleControl
├── RangeControl
└── All other form controls

BlockToolbar
├── Toolbar
├── BlockControls
├── BlockMover
└── ToolbarGroup

BlockInspector
├── Panel
├── PanelBody
├── InspectorControls
└── Various form controls
```

---

## Integration Strategy

### Phase 1: Standalone Components
Port components that have no/minimal dependencies:
- Button, Icon, Spinner
- BaseControl and form controls
- Panel components

### Phase 2: Composite Components
Port components that depend on Phase 1:
- Popover, Dropdown, Modal
- Toolbar system (already documented)
- Card, layout utilities

### Phase 3: Block Editor Components
Port block-specific components:
- BlockToolbar, BlockInspector
- BlockList, BlockPreview
- BlockControls, InspectorControls

### Phase 4: Advanced Features
Port complex, feature-rich components:
- Inserter, MediaPlaceholder
- RichText, LinkControl
- BlockNavigation, ListView

---

## Quick Reference: File Paths

### Gutenberg Base
- **Base Components**: `/Users/jacobmartella/Downloads/gutenberg-trunk/packages/components/src/`
- **Block Editor**: `/Users/jacobmartella/Downloads/gutenberg-trunk/packages/block-editor/src/components/`

### Visual Editor Package
- **Livewire Components**: `~/Desktop/ArtisanPack UI Packages/visual-editor/src/Livewire/Components/`
- **Blade Views**: `~/Desktop/ArtisanPack UI Packages/visual-editor/resources/views/livewire/components/`
- **Styles**: `~/Desktop/ArtisanPack UI Packages/visual-editor/resources/css/`

---

## Component Tracking

Mark components as you port them:

- ✅ Toolbar
- ✅ ToolbarButton
- ✅ ToolbarGroup
- ⏳ Button (in progress)
- ⬜ Panel (not started)

---

## Resources

- [WordPress Storybook](https://wordpress.github.io/gutenberg/) - Interactive component demos
- [Block Editor Handbook](https://developer.wordpress.org/block-editor/) - Official documentation
- [Component Reference](https://developer.wordpress.org/block-editor/reference-guides/components/) - Component API docs
- [Gutenberg Component Porting Guide](./GUTENBERG-COMPONENT-PORTING.md) - Porting process documentation

---

## Next Steps

1. Start with **Button** component (foundation for everything)
2. Port **Icon** (adapt to ArtisanPack UI icons)
3. Port **Spinner** (loading states)
4. Port **Popover** (critical dependency for many components)
5. Continue through the recommended porting order

Remember: **Quality over quantity**. A well-ported, accessible component is better than rushing through many components poorly.
