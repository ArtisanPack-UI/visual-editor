# Visual Editor Documentation

Welcome to the ArtisanPack UI Visual Editor documentation. This directory contains all planning, implementation, and reference materials.

---

## Start Here

**New to the project?** Read these in order:

1. **[IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md)** ← **START HERE**
   - Current state and what's implemented
   - Phase progress and next priorities
   - Quick overview of the entire project

2. **[plans/01-comprehensive-plan.md](plans/01-comprehensive-plan.md)**
   - High-level architecture and philosophy
   - Core concepts (blocks, sections, templates)
   - All 6 phases explained

3. **[plans/02-directory-structure.md](plans/02-directory-structure.md)**
   - Complete package structure
   - Where to find things

---

## Documentation Categories

### 📊 Status & Progress

| Document | Purpose | Audience |
|----------|---------|----------|
| **[IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md)** | Current implementation state, what works, what's next | Everyone |

### 🏗️ Architecture & Planning

Strategic documents defining the overall vision:

| Document | Purpose | Phase |
|----------|---------|-------|
| **[plans/01-comprehensive-plan.md](plans/01-comprehensive-plan.md)** | Executive overview, philosophy, all phases | All |
| **[plans/02-directory-structure.md](plans/02-directory-structure.md)** | Package structure (~430 files mapped) | All |
| **[plans/03-block-system.md](plans/03-block-system.md)** | Block interface, field types, 25+ core blocks | 1-2 |
| **[plans/04-section-system.md](plans/04-section-system.md)** | 16 default sections, categories, registration | 1 |
| **[plans/05-template-system.md](plans/05-template-system.md)** | Template hierarchy, parts, export/import | 3 |
| **[plans/06-global-styles.md](plans/06-global-styles.md)** | Design tokens, Tailwind integration | 4 |
| **[plans/07-permissions-locking.md](plans/07-permissions-locking.md)** | CMS Framework integration, 35+ permissions | 5 |
| **[plans/08-additional-features.md](plans/08-additional-features.md)** | AI, A/B testing, SEO, accessibility | 5-6 |
| **[plans/09-database-schema.md](plans/09-database-schema.md)** | Complete schema for all 9 tables | 1 |
| **[plans/10-configuration.md](plans/10-configuration.md)** | Configuration reference | All |

### 🎨 Gutenberg Reference

Tactical implementation specs from WordPress Gutenberg analysis:

| Document | Purpose | Use Case |
|----------|---------|----------|
| **[GUTENBERG-ANALYSIS-AND-INTEGRATION-PLAN.md](GUTENBERG-ANALYSIS-AND-INTEGRATION-PLAN.md)** | Comprehensive feature analysis (2,251 lines) | Reference when adding features |
| **[GUTENBERG-COMPONENT-PORTING.md](GUTENBERG-COMPONENT-PORTING.md)** | Systematic porting guide | When building UI components |
| **[COMPONENT-PRIORITY-LIST.md](COMPONENT-PRIORITY-LIST.md)** | 54 prioritized components (30 MVP + 24 Phase 2) | Planning component work |
| **[COMPONENT-MAPPING-ARTISANPACK-VS-GUTENBERG.md](COMPONENT-MAPPING-ARTISANPACK-VS-GUTENBERG.md)** | What we have vs what to port | Avoid rebuilding existing components |

**How to Use Gutenberg Docs:**
- Use as **reference** when enhancing existing implementation
- Learn UX patterns and accessibility best practices
- Don't port blindly - adapt patterns to Laravel/Livewire
- See what ArtisanPack UI components you can reuse first

---

## Quick Reference

### Current Implementation Status

```
Phase 1 (Core Editor):        100% ✅ COMPLETE
Phase 2 (Full Block Library):  25% 🔄 IN PROGRESS
Phase 3 (Templates):            10% ⏸️ Models only
Phase 4 (Global Styles):        10% ⏸️ Service only
Phase 5 (Advanced):              0% ⏸️ Not started
Phase 6 (Polish):                0% ⏸️ Not started
```

### What's Working Now

✅ Create/edit content with visual editor
✅ Blocks: heading, text, list, quote, image, columns, group
✅ Block transformations and drag & drop
✅ Inline editing and rich toolbar
✅ Save/publish with autosave
✅ 43 tests passing

### Next Priorities

1. Complete Phase 2 blocks (video, audio, gallery, buttons, etc.)
2. Enhance UI with Gutenberg patterns (toolbar, block inserter)
3. Build Template and Global Styles UIs

---

## Development Approach

### Our Philosophy

**Custom Laravel/Livewire Implementation Enhanced with Gutenberg Learnings**

We're building a native Laravel/Livewire editor that follows Laravel best practices while learning from WordPress Gutenberg's proven UX patterns.

**What We Built Custom:**
- Config-based block system (not class-based)
- Livewire components for all UI
- Laravel Eloquent models for data
- Registry pattern for extensibility

**What We Learn from Gutenberg:**
- UX patterns (toolbar, inserter, panels)
- Keyboard shortcuts and accessibility
- Advanced form controls
- Component specifications

### Key Decisions

1. **Config-Based Blocks** - Faster to add, no PHP classes needed per block
2. **Livewire-First** - Server-side rendering, Laravel-native
3. **Blade Views** - Simple, maintainable templates
4. **Registry Pattern** - Extensible via hooks

---

## For Developers

### Adding a New Block

1. Register in `BlockRegistry::registerDefaults()`:
   ```php
   $this->register('my-block', [
       'name' => __('My Block'),
       'icon' => 'fas.icon-name',
       'category' => 'text',
       'content_schema' => [...],
       'settings_schema' => [...],
   ]);
   ```

2. Add rendering in `resources/views/livewire/partials/block-renderer.blade.php`

3. Write tests in `tests/Unit/BlockRegistryTest.php`

See existing blocks for examples.

### Running Tests

```bash
./vendor/bin/pest                    # All tests
./vendor/bin/pest --filter=BlockTest # Specific test
```

### Code Formatting

```bash
vendor/bin/pint  # Auto-format all PHP files
```

---

## For Contributors

### Before Contributing

1. Read [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md) to understand current state
2. Check [COMPONENT-MAPPING-ARTISANPACK-VS-GUTENBERG.md](COMPONENT-MAPPING-ARTISANPACK-VS-GUTENBERG.md) before building UI components
3. Follow existing patterns in the codebase
4. Write tests for all new features

### Testing Requirements

- All new features must have tests
- Tests must pass before merging
- Aim for >80% code coverage

---

## Document Index

### By Phase

**Phase 1 (Complete):**
- Block system: [03-block-system.md](plans/03-block-system.md)
- Section system: [04-section-system.md](plans/04-section-system.md)
- Database: [09-database-schema.md](plans/09-database-schema.md)

**Phase 2 (In Progress):**
- Block system (continued): [03-block-system.md](plans/03-block-system.md)
- Gutenberg components: [COMPONENT-PRIORITY-LIST.md](COMPONENT-PRIORITY-LIST.md)

**Phase 3:**
- Templates: [05-template-system.md](plans/05-template-system.md)

**Phase 4:**
- Global styles: [06-global-styles.md](plans/06-global-styles.md)

**Phase 5-6:**
- Advanced features: [08-additional-features.md](plans/08-additional-features.md)
- Permissions: [07-permissions-locking.md](plans/07-permissions-locking.md)

### By Topic

**Architecture:**
- [01-comprehensive-plan.md](plans/01-comprehensive-plan.md)
- [02-directory-structure.md](plans/02-directory-structure.md)

**Features:**
- [03-block-system.md](plans/03-block-system.md) - Blocks
- [04-section-system.md](plans/04-section-system.md) - Sections
- [05-template-system.md](plans/05-template-system.md) - Templates
- [06-global-styles.md](plans/06-global-styles.md) - Styles
- [07-permissions-locking.md](plans/07-permissions-locking.md) - Security
- [08-additional-features.md](plans/08-additional-features.md) - AI, A/B, SEO

**Implementation:**
- [GUTENBERG-COMPONENT-PORTING.md](GUTENBERG-COMPONENT-PORTING.md) - How to port
- [COMPONENT-PRIORITY-LIST.md](COMPONENT-PRIORITY-LIST.md) - What to port
- [COMPONENT-MAPPING-ARTISANPACK-VS-GUTENBERG.md](COMPONENT-MAPPING-ARTISANPACK-VS-GUTENBERG.md) - What we have

---

## Questions?

- **What's implemented?** → [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md)
- **What's the plan?** → [plans/01-comprehensive-plan.md](plans/01-comprehensive-plan.md)
- **How do I add a block?** → [plans/03-block-system.md](plans/03-block-system.md)
- **What can I reuse?** → [COMPONENT-MAPPING-ARTISANPACK-VS-GUTENBERG.md](COMPONENT-MAPPING-ARTISANPACK-VS-GUTENBERG.md)
- **What should I build next?** → [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md#next-priorities)

---

**Last Updated:** February 15, 2026
