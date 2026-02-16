# Component Mapping: ArtisanPack UI vs Gutenberg

This document maps existing ArtisanPack UI Livewire components against Gutenberg components to identify what you already have and what's unique to Gutenberg that needs porting for the Visual Editor.

## Summary

- ✅ **Already Have in ArtisanPack UI**: ~50 components (no porting needed)
- 🔄 **Gutenberg Unique/Enhanced**: ~25 critical components to port
- 🎯 **Visual Editor Specific**: ~15 block editor components (Gutenberg only)

---

## ✅ Components You Already Have (Use Existing ArtisanPack UI)

### Form Components (25 components)

| ArtisanPack Component | Gutenberg Equivalent | Use | Notes |
|----------------------|----------------------|-----|-------|
| **Button** | Button | ✅ Use ArtisanPack | Already perfect for your stack |
| **Input** | TextControl | ✅ Use ArtisanPack | |
| **Textarea** | TextareaControl | ✅ Use ArtisanPack | |
| **Password** | PasswordControl | ✅ Use ArtisanPack | |
| **Checkbox** | CheckboxControl | ✅ Use ArtisanPack | |
| **CheckboxGroup** | - | ✅ Use ArtisanPack | Better than Gutenberg |
| **Radio** | RadioControl | ✅ Use ArtisanPack | |
| **RadioGroup** | - | ✅ Use ArtisanPack | Better than Gutenberg |
| **Select** | SelectControl | ✅ Use ArtisanPack | |
| **SelectGroup** | - | ✅ Use ArtisanPack | |
| **Toggle** | ToggleControl | ✅ Use ArtisanPack | |
| **DatePicker** | DateTimePicker | ✅ Use ArtisanPack | |
| **DateTime** | DateTimePicker | ✅ Use ArtisanPack | |
| **File** | FormFileUpload | ✅ Use ArtisanPack | |
| **Range** | RangeControl | ✅ Use ArtisanPack | |
| **Rating** | - | ✅ Use ArtisanPack | Unique to ArtisanPack |
| **Colorpicker** | ColorPicker | ✅ Use ArtisanPack | May enhance with Gutenberg's ColorPalette |
| **Editor** | RichText | ✅ Use ArtisanPack | May enhance for block editing |
| **Tags** | FormTokenField | ✅ Use ArtisanPack | |
| **Signature** | - | ✅ Use ArtisanPack | Unique to ArtisanPack |
| **Pin** | - | ✅ Use ArtisanPack | Unique to ArtisanPack |
| **Choices** | ComboboxControl | ✅ Use ArtisanPack | |
| **ChoicesOffline** | - | ✅ Use ArtisanPack | Unique to ArtisanPack |
| **Form** | - | ✅ Use ArtisanPack | Form wrapper |
| **Fieldset** | - | ✅ Use ArtisanPack | Form grouping |
| **Group** | - | ✅ Use ArtisanPack | Form grouping |

### Layout Components (10 components)

| ArtisanPack Component | Gutenberg Equivalent | Use | Notes |
|----------------------|----------------------|-----|-------|
| **Card** | Card | ✅ Use ArtisanPack | |
| **Modal** | Modal | ✅ Use ArtisanPack | |
| **Accordion** | - | ✅ Use ArtisanPack | |
| **Collapse** | - | ✅ Use ArtisanPack | |
| **Drawer** | - | ✅ Use ArtisanPack | |
| **Header** | - | ✅ Use ArtisanPack | |
| **Main** | - | ✅ Use ArtisanPack | |
| **Separator** | Divider | ✅ Use ArtisanPack | |
| **Swap** | - | ✅ Use ArtisanPack | Unique to ArtisanPack |
| **Popover** | Popover | ⚠️ May need enhancement | Gutenberg's is more feature-rich for editor |

### Navigation Components (14 components)

| ArtisanPack Component | Gutenberg Equivalent | Use | Notes |
|----------------------|----------------------|-----|-------|
| **Breadcrumbs** | - | ✅ Use ArtisanPack | |
| **Dropdown** | Dropdown | ✅ Use ArtisanPack | May enhance with Gutenberg patterns |
| **Link** | ExternalLink | ✅ Use ArtisanPack | |
| **Menu** | Menu | ✅ Use ArtisanPack | |
| **MenuItem** | MenuItem | ✅ Use ArtisanPack | |
| **MenuSeparator** | - | ✅ Use ArtisanPack | |
| **MenuSub** | - | ✅ Use ArtisanPack | |
| **MenuTitle** | - | ✅ Use ArtisanPack | |
| **Nav** | NavigableContainer | ✅ Use ArtisanPack | |
| **Pagination** | - | ✅ Use ArtisanPack | |
| **Spotlight** | - | ✅ Use ArtisanPack | Unique to ArtisanPack |
| **Tabs** | TabPanel | ✅ Use ArtisanPack | |
| **Tab** | - | ✅ Use ArtisanPack | |

### Data Display Components (23 components)

| ArtisanPack Component | Gutenberg Equivalent | Use | Notes |
|----------------------|----------------------|-----|-------|
| **Avatar** | - | ✅ Use ArtisanPack | |
| **Badge** | Badge | ✅ Use ArtisanPack | |
| **Calendar** | - | ✅ Use ArtisanPack | |
| **Chart** | - | ✅ Use ArtisanPack | |
| **Code** | Code | ✅ Use ArtisanPack | |
| **Diff** | - | ✅ Use ArtisanPack | Unique to ArtisanPack |
| **Heading** | - | ✅ Use ArtisanPack | |
| **Subheading** | - | ✅ Use ArtisanPack | |
| **Text** | - | ✅ Use ArtisanPack | |
| **ImageGallery** | - | ✅ Use ArtisanPack | |
| **ImageLibrary** | - | ✅ Use ArtisanPack | |
| **ImageSlider** | - | ✅ Use ArtisanPack | |
| **Kbd** | Shortcut | ✅ Use ArtisanPack | |
| **ListItem** | - | ✅ Use ArtisanPack | |
| **Markdown** | - | ✅ Use ArtisanPack | |
| **Profile** | - | ✅ Use ArtisanPack | |
| **Progress** | ProgressBar | ✅ Use ArtisanPack | |
| **ProgressRadial** | - | ✅ Use ArtisanPack | |
| **Sparkline** | - | ✅ Use ArtisanPack | |
| **Stat** | - | ✅ Use ArtisanPack | |
| **Table** | - | ✅ Use ArtisanPack | May need to port Gutenberg's DataGrid |

### Feedback Components (7 components)

| ArtisanPack Component | Gutenberg Equivalent | Use | Notes |
|----------------------|----------------------|-----|-------|
| **Alert** | Notice | ✅ Use ArtisanPack | |
| **Toast** | Snackbar | ✅ Use ArtisanPack | |
| **Loading** | Spinner | ✅ Use ArtisanPack | |
| **Steps** | - | ✅ Use ArtisanPack | |
| **Step** | - | ✅ Use ArtisanPack | |
| **TimelineItem** | - | ✅ Use ArtisanPack | |
| **Errors** | - | ✅ Use ArtisanPack | |

### Utility Components (7 components)

| ArtisanPack Component | Gutenberg Equivalent | Use | Notes |
|----------------------|----------------------|-----|-------|
| **Carousel** | - | ✅ Use ArtisanPack | |
| **Icon** | Icon | ✅ Use ArtisanPack | Already using ArtisanPack icons |
| **ThemeToggle** | - | ✅ Use ArtisanPack | |
| **KpiCard** | - | ✅ Use ArtisanPack | |
| **WidgetGrid** | - | ✅ Use ArtisanPack | |
| **EventModalContent** | - | ✅ Use ArtisanPack | |
| **StreamableContent** | - | ✅ Use ArtisanPack | |

**Total Existing Components: ~86 components** ✅

---

## 🔄 Gutenberg Components to Port (Unique or Enhanced Features)

### Critical Editor UI Components (15 components)

These are Gutenberg components that offer functionality not in ArtisanPack UI or need editor-specific enhancements.

| Gutenberg Component | Why Port It | Priority | Complexity |
|--------------------|-------------|----------|------------|
| **Panel / PanelBody / PanelRow** | Collapsible settings panels for inspector | 🎯 Critical | Low |
| **BaseControl** | Wrapper for form controls with label/help | 🎯 Critical | Low |
| **Tooltip** | Hover tooltips (ArtisanPack doesn't have) | ⭐ High | Medium |
| **ConfirmDialog** | Confirmation modals (better than generic modal) | ⭐ High | Low |
| **DropdownMenu** | Menu in dropdown (specific pattern) | ⭐ High | Medium |
| **Placeholder** | Empty state for blocks | 🎯 Critical | Low |
| **Popover (Enhanced)** | More positioning options for editor | 🔄 Enhancement | High |
| **UnitControl** | Input with unit selector (px, %, em) | ⭐ High | Medium |
| **BoxControl** | 4-sided spacing control | ⭐ High | Medium |
| **BorderControl** | Complete border editor | 🔧 Medium | High |
| **FocalPointPicker** | Image focal point selector | 🔧 Medium | High |
| **GradientPicker** | Gradient editor | 🔧 Medium | High |
| **AnglePicker** | Rotation angle selector | 🔧 Medium | Medium |
| **AlignmentMatrixControl** | 9-grid alignment | 🔧 Medium | Medium |
| **TreeSelect** | Hierarchical dropdown | 🔧 Medium | Medium |

### Toolbar System (4 components)

| Component | Status | Notes |
|-----------|--------|-------|
| **Toolbar** | ✅ Documented | In porting guide |
| **ToolbarButton** | ✅ Documented | In porting guide |
| **ToolbarGroup** | ✅ Documented | In porting guide |
| **NavigableToolbar** | ⏳ Port | Keyboard navigation wrapper |

---

## 🎯 Block Editor Specific Components (Must Port)

These components are unique to block editors and don't exist in ArtisanPack UI. They're essential for the visual editor.

### Core Block Editor (10 components)

| Component | Purpose | Priority | Complexity |
|-----------|---------|----------|------------|
| **BlockToolbar** | Contextual block toolbar | 🎯 Critical | Medium |
| **BlockInspector** | Settings sidebar for blocks | 🎯 Critical | Medium |
| **BlockList** | Renders the block canvas | 🎯 Critical | Very High |
| **BlockControls** | Slot system for block toolbar | 🎯 Critical | Medium |
| **InspectorControls** | Slot system for inspector | 🎯 Critical | Medium |
| **WritingFlow** | Text editing flow/keyboard nav | 🎯 Critical | Very High |
| **BlockMover** | Drag handle for blocks | 🎯 Critical | Medium |
| **Inserter** | Block library/picker | 🎯 Critical | Very High |
| **BlockPreview** | Block preview rendering | ⭐ High | Medium |
| **MediaPlaceholder** | Media upload UI | ⭐ High | Medium |

### Block Enhancement Components (8 components)

| Component | Purpose | Priority | Complexity |
|-----------|---------|----------|------------|
| **AlignmentControl** | Text alignment toolbar buttons | ⭐ High | Low |
| **BlockAlignmentControl** | Block width (normal/wide/full) | ⭐ High | Low |
| **URLInput** | Link URL input with search | ⭐ High | High |
| **URLPopover** | Link editing popover | ⭐ High | Medium |
| **LinkControl** | Complete link editor | ⭐ High | High |
| **BlockCard** | Block info card | 🔧 Medium | Low |
| **BlockIcon** | Block type icon display | 🔧 Medium | Low |
| **BlockNavigation** | Block tree/outline view | 🔧 Medium | High |

### Advanced Block Features (6 components)

| Component | Purpose | Priority | Complexity |
|-----------|---------|----------|------------|
| **ListView** | Hierarchical block list view | 🔧 Medium | High |
| **BlockPatternsList** | Pattern library grid | 🔧 Medium | Medium |
| **BlockManager** | Enable/disable block types | 🔧 Medium | Medium |
| **BlockLock** | Lock/unlock block controls | 💡 Nice to Have | Low |
| **ResizableBox** | Resizable container | 🔧 Medium | High |
| **Draggable / DropZone** | Drag and drop system | ⭐ High | Very High |

---

## 📊 Priority Summary for Visual Editor

### Must Port (Week 1-4) - 20 components

**Toolbar System:**
- Toolbar ✅
- ToolbarButton ✅
- ToolbarGroup ✅
- NavigableToolbar

**Essential UI:**
- Panel / PanelBody / PanelRow
- BaseControl
- Placeholder
- Tooltip
- ConfirmDialog

**Core Block Editor:**
- BlockToolbar
- BlockInspector
- BlockControls
- InspectorControls
- BlockMover
- BlockPreview
- WritingFlow
- BlockList
- Inserter
- MediaPlaceholder

### Should Port (Week 5-8) - 15 components

**Enhanced Controls:**
- UnitControl
- BoxControl
- AlignmentControl
- BlockAlignmentControl
- DropdownMenu
- Popover (enhanced version)

**Link Editing:**
- URLInput
- URLPopover
- LinkControl

**Block Features:**
- BlockCard
- BlockIcon
- Draggable / DropZone
- BlockNavigation
- BlockPatternsList
- BlockManager

### Nice to Have (Week 9+) - 10 components

- BorderControl
- GradientPicker
- FocalPointPicker
- AnglePicker
- AlignmentMatrixControl
- TreeSelect
- ListView
- BlockLock
- ResizableBox

---

## 💡 Component Reuse Strategy

### 1. Use ArtisanPack UI for General UI (86 components)

All your existing components work perfectly for:
- Form inputs and controls
- General layout (cards, modals, etc.)
- Navigation menus
- Data display
- Feedback/notifications
- Utility functions

**No need to port Gutenberg equivalents!**

### 2. Port Gutenberg for Editor-Specific Features (~35 components)

Focus porting efforts on:
- Block editor components (BlockList, BlockToolbar, etc.)
- Editor-specific UI (Panel, BaseControl, Placeholder)
- Advanced controls (UnitControl, BoxControl, etc.)
- Block enhancements (AlignmentControl, LinkControl, etc.)

### 3. Enhance Existing Components Where Needed

Some ArtisanPack components could benefit from Gutenberg patterns:
- **Popover** - Add positioning options from Gutenberg
- **Dropdown** - Adopt Gutenberg's dropdown patterns
- **Colorpicker** - Add ColorPalette component alongside

---

## 🎯 Recommended Porting Order

### Phase 1: Foundation (Week 1-2)
1. Panel / PanelBody / PanelRow
2. BaseControl
3. Tooltip
4. ConfirmDialog
5. Placeholder

### Phase 2: Toolbar (Week 2-3)
6. NavigableToolbar (Toolbar/ToolbarButton/ToolbarGroup already documented)

### Phase 3: Block Editor Core (Week 3-6)
7. BlockControls / InspectorControls (slot system)
8. BlockToolbar
9. BlockInspector
10. BlockMover
11. BlockPreview
12. Simple BlockList implementation
13. Simple Inserter implementation

### Phase 4: Enhanced Controls (Week 7-8)
14. UnitControl
15. BoxControl
16. AlignmentControl
17. BlockAlignmentControl
18. DropdownMenu

### Phase 5: Link & Media (Week 9-10)
19. URLInput / URLPopover / LinkControl
20. MediaPlaceholder
21. Draggable / DropZone

### Phase 6: Advanced Features (Week 11+)
22. WritingFlow (complex - defer if needed)
23. Full BlockList with all features
24. Full Inserter with search/patterns
25. BorderControl, GradientPicker, etc.

---

## ✅ Final Count

**Total Components Needed:**
- ✅ Already Have (ArtisanPack): **~86 components**
- 🔄 Need to Port (Gutenberg): **~35 components**
- 📦 Total Available: **~120 components**

**Effort Savings:**
By leveraging existing ArtisanPack UI components, you're **avoiding ~70% of the porting work**! 🎉

Focus your effort on the ~35 editor-specific components that make the visual editor unique.

---

## 📚 Quick Reference

- **ArtisanPack Components**: All listed in `ComponentTestController.php`
- **Gutenberg Components**: Listed in `COMPONENT-PRIORITY-LIST.md`
- **Porting Guide**: See `GUTENBERG-COMPONENT-PORTING.md`

## Next Steps

1. ✅ Use existing ArtisanPack UI components where possible
2. ⏳ Port Panel/PanelBody/PanelRow first (foundation for settings)
3. ⏳ Port BlockToolbar/BlockInspector (core editor UI)
4. ⏳ Incrementally add block editor components as needed

**Remember:** Quality over quantity. A well-integrated component from ArtisanPack UI is better than a rushed port from Gutenberg!
