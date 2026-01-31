# Visual Editor - Directory Structure

> **Phases:** All (1-6) — This structure supports all phases of implementation

---

## Package Structure

```
packages/visual-editor/
├── src/
│   ├── Blocks/
│   │   ├── Contracts/
│   │   │   ├── BlockInterface.php
│   │   │   ├── BlockRendererInterface.php
│   │   │   └── BlockTransformerInterface.php
│   │   ├── Registry/
│   │   │   └── BlockRegistry.php
│   │   ├── Core/
│   │   │   ├── HeadingBlock.php
│   │   │   ├── ParagraphBlock.php
│   │   │   ├── ImageBlock.php
│   │   │   ├── ListBlock.php
│   │   │   ├── QuoteBlock.php
│   │   │   ├── CodeBlock.php
│   │   │   ├── ButtonBlock.php
│   │   │   ├── ButtonGroupBlock.php
│   │   │   ├── VideoBlock.php
│   │   │   ├── AudioBlock.php
│   │   │   ├── FileBlock.php
│   │   │   ├── GalleryBlock.php
│   │   │   ├── ColumnsBlock.php
│   │   │   ├── GroupBlock.php
│   │   │   ├── SpacerBlock.php
│   │   │   ├── DividerBlock.php
│   │   │   ├── TabsBlock.php
│   │   │   ├── AccordionBlock.php
│   │   │   ├── FormBlock.php
│   │   │   ├── MapBlock.php
│   │   │   ├── SocialEmbedBlock.php
│   │   │   ├── HtmlBlock.php
│   │   │   ├── ShortcodeBlock.php
│   │   │   ├── LatestPostsBlock.php
│   │   │   ├── TableOfContentsBlock.php
│   │   │   └── GlobalContentBlock.php
│   │   ├── Renderers/
│   │   │   ├── BladeBlockRenderer.php
│   │   │   └── LivewireBlockRenderer.php
│   │   ├── BaseBlock.php
│   │   └── ConfigBlock.php
│   │
│   ├── Sections/
│   │   ├── Contracts/
│   │   │   └── SectionInterface.php
│   │   ├── Registry/
│   │   │   └── SectionRegistry.php
│   │   ├── Core/
│   │   │   ├── HeroSection.php
│   │   │   ├── HeroImageSection.php
│   │   │   ├── FeaturesSection.php
│   │   │   ├── ServicesSection.php
│   │   │   ├── TestimonialsSection.php
│   │   │   ├── TeamSection.php
│   │   │   ├── GallerySection.php
│   │   │   ├── CtaSection.php
│   │   │   ├── ContactSection.php
│   │   │   ├── FaqSection.php
│   │   │   ├── PricingSection.php
│   │   │   ├── StatsSection.php
│   │   │   ├── LogoCloudSection.php
│   │   │   ├── BlogPostsSection.php
│   │   │   ├── TextSection.php
│   │   │   └── TextImageSection.php
│   │   └── BaseSection.php
│   │
│   ├── Templates/
│   │   ├── Contracts/
│   │   │   ├── TemplateInterface.php
│   │   │   └── TemplatePartInterface.php
│   │   ├── Registry/
│   │   │   └── TemplateRegistry.php
│   │   ├── Hierarchy/
│   │   │   └── TemplateHierarchy.php
│   │   ├── Parts/
│   │   │   ├── HeaderPart.php
│   │   │   ├── FooterPart.php
│   │   │   ├── SidebarPart.php
│   │   │   └── CommentsPart.php
│   │   ├── BaseTemplate.php
│   │   └── TemplateResolver.php
│   │
│   ├── Styles/
│   │   ├── GlobalStyles.php
│   │   ├── DesignTokens.php
│   │   ├── TailwindIntegration.php
│   │   ├── StyleCompiler.php
│   │   └── ThemeInheritance.php
│   │
│   ├── Editor/
│   │   ├── EditorState.php
│   │   ├── EditorHistory.php
│   │   ├── SelectionManager.php
│   │   └── PresenceManager.php
│   │
│   ├── Locking/
│   │   ├── ContentLocking.php
│   │   ├── LockLevel.php
│   │   └── LockManager.php
│   │
│   ├── Versioning/
│   │   ├── RevisionManager.php
│   │   ├── VersionMigrator.php
│   │   ├── DiffGenerator.php
│   │   └── BlockMigrations/
│   │       └── MigrationRunner.php
│   │
│   ├── AI/
│   │   ├── Contracts/
│   │   │   └── AIProviderInterface.php
│   │   ├── AIAssistant.php
│   │   ├── ContentSuggestions.php
│   │   ├── AltTextGenerator.php
│   │   ├── LayoutSuggestions.php
│   │   └── Providers/
│   │       ├── OpenAIProvider.php
│   │       └── AnthropicProvider.php
│   │
│   ├── Performance/
│   │   ├── PerformanceBudget.php
│   │   ├── PageAnalyzer.php
│   │   └── Recommendations.php
│   │
│   ├── ABTesting/
│   │   ├── ExperimentManager.php
│   │   ├── VariantSelector.php
│   │   ├── ConversionTracker.php
│   │   └── StatisticsCalculator.php
│   │
│   ├── Offline/
│   │   ├── OfflineSyncService.php
│   │   ├── ConflictResolver.php
│   │   └── QueueManager.php
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── EditorController.php
│   │   │   ├── PreviewController.php
│   │   │   ├── BlockController.php
│   │   │   ├── SectionController.php
│   │   │   ├── TemplateController.php
│   │   │   ├── StyleController.php
│   │   │   ├── RevisionController.php
│   │   │   └── Api/
│   │   │       ├── ContentApiController.php
│   │   │       ├── BlockApiController.php
│   │   │       ├── StyleApiController.php
│   │   │       └── MediaApiController.php
│   │   │
│   │   ├── Livewire/
│   │   │   ├── EditorShell.php
│   │   │   ├── EditorCanvas.php
│   │   │   ├── EditorToolbar.php
│   │   │   │
│   │   │   ├── LeftSidebar/
│   │   │   │   ├── LayersPanel.php
│   │   │   │   ├── BlockInserter.php
│   │   │   │   ├── SectionLibrary.php
│   │   │   │   └── SearchPanel.php
│   │   │   │
│   │   │   ├── RightSidebar/
│   │   │   │   ├── SettingsPanel.php
│   │   │   │   ├── StylesPanel.php
│   │   │   │   ├── AdvancedPanel.php
│   │   │   │   ├── SeoPanel.php
│   │   │   │   └── PageSettingsPanel.php
│   │   │   │
│   │   │   ├── Canvas/
│   │   │   │   ├── BlockWrapper.php
│   │   │   │   ├── SectionWrapper.php
│   │   │   │   ├── InsertionPoint.php
│   │   │   │   └── SelectionOverlay.php
│   │   │   │
│   │   │   ├── Modals/
│   │   │   │   ├── PublishModal.php
│   │   │   │   ├── VersionHistoryModal.php
│   │   │   │   ├── TemplatePickerModal.php
│   │   │   │   ├── SectionSaveModal.php
│   │   │   │   ├── MediaPickerModal.php
│   │   │   │   ├── LinkPickerModal.php
│   │   │   │   └── AIAssistantModal.php
│   │   │   │
│   │   │   └── Inputs/
│   │   │       ├── RichTextInput.php
│   │   │       ├── ColorPicker.php
│   │   │       ├── MediaPicker.php
│   │   │       ├── LinkPicker.php
│   │   │       ├── IconPicker.php
│   │   │       ├── AlignmentPicker.php
│   │   │       └── SpacingPicker.php
│   │   │
│   │   └── Middleware/
│   │       ├── EditorAccess.php
│   │       ├── ValidateContentType.php
│   │       └── CheckEditorPermissions.php
│   │
│   ├── Models/
│   │   ├── Content.php
│   │   ├── ContentRevision.php
│   │   ├── Template.php
│   │   ├── TemplatePart.php
│   │   ├── UserSection.php
│   │   ├── GlobalStyle.php
│   │   ├── Experiment.php
│   │   ├── ExperimentVariant.php
│   │   └── EditorLock.php
│   │
│   ├── Services/
│   │   ├── ContentRenderer.php
│   │   ├── BlockTransformer.php
│   │   ├── SectionBuilder.php
│   │   ├── StyleGenerator.php
│   │   ├── ExportService.php
│   │   └── ImportService.php
│   │
│   ├── Events/
│   │   ├── ContentCreated.php
│   │   ├── ContentUpdated.php
│   │   ├── ContentPublished.php
│   │   ├── ContentDeleted.php
│   │   ├── RevisionCreated.php
│   │   ├── TemplateUpdated.php
│   │   ├── StylesUpdated.php
│   │   └── ExperimentStarted.php
│   │
│   ├── Listeners/
│   │   ├── ClearContentCache.php
│   │   ├── NotifyContentPublished.php
│   │   └── TrackExperimentConversion.php
│   │
│   ├── Commands/
│   │   ├── InstallCommand.php
│   │   ├── MakeBlockCommand.php
│   │   ├── MakeSectionCommand.php
│   │   ├── CompileStylesCommand.php
│   │   └── CleanupRevisionsCommand.php
│   │
│   ├── Facades/
│   │   ├── VisualEditor.php
│   │   ├── Blocks.php
│   │   ├── Sections.php
│   │   ├── Templates.php
│   │   └── Styles.php
│   │
│   ├── Traits/
│   │   ├── HasVisualContent.php
│   │   ├── HasRevisions.php
│   │   └── HasGlobalStyles.php
│   │
│   └── VisualEditorServiceProvider.php
│
├── config/
│   └── visual-editor.php
│
├── database/
│   └── migrations/
│       ├── 2026_01_01_000001_create_ve_contents_table.php
│       ├── 2026_01_01_000002_create_ve_content_revisions_table.php
│       ├── 2026_01_01_000003_create_ve_templates_table.php
│       ├── 2026_01_01_000004_create_ve_template_parts_table.php
│       ├── 2026_01_01_000005_create_ve_user_sections_table.php
│       ├── 2026_01_01_000006_create_ve_global_styles_table.php
│       ├── 2026_01_01_000007_create_ve_experiments_table.php
│       ├── 2026_01_01_000008_create_ve_experiment_variants_table.php
│       └── 2026_01_01_000009_create_ve_editor_locks_table.php
│
├── resources/
│   ├── views/
│   │   ├── editor/
│   │   │   ├── shell.blade.php
│   │   │   ├── canvas.blade.php
│   │   │   ├── iframe.blade.php
│   │   │   └── toolbar.blade.php
│   │   │
│   │   ├── blocks/
│   │   │   ├── heading.blade.php
│   │   │   ├── paragraph.blade.php
│   │   │   ├── image.blade.php
│   │   │   ├── list.blade.php
│   │   │   ├── quote.blade.php
│   │   │   ├── code.blade.php
│   │   │   ├── button.blade.php
│   │   │   ├── button-group.blade.php
│   │   │   ├── video.blade.php
│   │   │   ├── audio.blade.php
│   │   │   ├── file.blade.php
│   │   │   ├── gallery.blade.php
│   │   │   ├── columns.blade.php
│   │   │   ├── group.blade.php
│   │   │   ├── spacer.blade.php
│   │   │   ├── divider.blade.php
│   │   │   ├── tabs.blade.php
│   │   │   ├── accordion.blade.php
│   │   │   ├── form.blade.php
│   │   │   ├── map.blade.php
│   │   │   ├── social-embed.blade.php
│   │   │   ├── html.blade.php
│   │   │   ├── shortcode.blade.php
│   │   │   ├── latest-posts.blade.php
│   │   │   ├── table-of-contents.blade.php
│   │   │   └── global-content.blade.php
│   │   │
│   │   ├── sections/
│   │   │   ├── hero.blade.php
│   │   │   ├── hero-image.blade.php
│   │   │   ├── features.blade.php
│   │   │   ├── services.blade.php
│   │   │   ├── testimonials.blade.php
│   │   │   ├── team.blade.php
│   │   │   ├── gallery.blade.php
│   │   │   ├── cta.blade.php
│   │   │   ├── contact.blade.php
│   │   │   ├── faq.blade.php
│   │   │   ├── pricing.blade.php
│   │   │   ├── stats.blade.php
│   │   │   ├── logo-cloud.blade.php
│   │   │   ├── blog-posts.blade.php
│   │   │   ├── text.blade.php
│   │   │   └── text-image.blade.php
│   │   │
│   │   ├── templates/
│   │   │   ├── parts/
│   │   │   │   ├── header.blade.php
│   │   │   │   ├── footer.blade.php
│   │   │   │   └── sidebar.blade.php
│   │   │   └── layouts/
│   │   │       ├── default.blade.php
│   │   │       ├── full-width.blade.php
│   │   │       └── sidebar.blade.php
│   │   │
│   │   ├── components/
│   │   │   ├── block-wrapper.blade.php
│   │   │   ├── section-wrapper.blade.php
│   │   │   ├── toolbar-button.blade.php
│   │   │   ├── sidebar-panel.blade.php
│   │   │   └── device-frame.blade.php
│   │   │
│   │   └── livewire/
│   │       ├── editor-shell.blade.php
│   │       ├── editor-canvas.blade.php
│   │       ├── editor-toolbar.blade.php
│   │       ├── left-sidebar/
│   │       │   ├── layers-panel.blade.php
│   │       │   ├── block-inserter.blade.php
│   │       │   └── section-library.blade.php
│   │       ├── right-sidebar/
│   │       │   ├── settings-panel.blade.php
│   │       │   ├── styles-panel.blade.php
│   │       │   └── advanced-panel.blade.php
│   │       └── modals/
│   │           ├── publish-modal.blade.php
│   │           ├── version-history-modal.blade.php
│   │           └── template-picker-modal.blade.php
│   │
│   ├── js/
│   │   ├── editor.js
│   │   ├── iframe-bridge.js
│   │   ├── keyboard-shortcuts.js
│   │   ├── drag-drop.js
│   │   ├── inline-editing.js
│   │   ├── offline-sync.js
│   │   ├── presence.js
│   │   └── alpine/
│   │       ├── editor-state.js
│   │       ├── block-controls.js
│   │       └── selection.js
│   │
│   └── css/
│       ├── editor.css
│       ├── canvas.css
│       ├── sidebar.css
│       └── blocks.css
│
├── routes/
│   ├── web.php
│   └── api.php
│
├── tests/
│   ├── Feature/
│   │   ├── EditorTest.php
│   │   ├── BlockRenderingTest.php
│   │   ├── SectionTest.php
│   │   ├── TemplateTest.php
│   │   ├── VersioningTest.php
│   │   └── PermissionsTest.php
│   ├── Unit/
│   │   ├── BlockRegistryTest.php
│   │   ├── ContentRendererTest.php
│   │   ├── StyleGeneratorTest.php
│   │   └── RevisionManagerTest.php
│   ├── TestCase.php
│   └── Pest.php
│
├── stubs/
│   ├── block.stub
│   ├── block-view.stub
│   ├── section.stub
│   └── section-view.stub
│
├── docs/
│   ├── plans/
│   │   ├── 01-comprehensive-plan.md
│   │   ├── 02-directory-structure.md
│   │   ├── 03-block-system.md
│   │   └── ...
│   ├── blocks.md
│   ├── sections.md
│   ├── templates.md
│   ├── styles.md
│   └── extensibility.md
│
├── .gitignore
├── .php-cs-fixer.dist.php
├── phpcs.xml
├── phpunit.xml
├── composer.json
├── CHANGELOG.md
├── CONTRIBUTING.md
├── LICENSE
└── README.md
```

## Key Directories Explained

### `/src/Blocks`
Contains the complete block system including interfaces, registry, core blocks, and renderers.

### `/src/Sections`
Pre-designed section layouts that combine blocks into reusable patterns.

### `/src/Templates`
Template system including hierarchy resolver, template parts, and template registry.

### `/src/Styles`
Global styling system with Tailwind integration and design tokens.

### `/src/Editor`
Core editor functionality including state management, history, and presence.

### `/src/Http/Livewire`
All Livewire components organized by location (left sidebar, right sidebar, canvas, modals).

### `/resources/views`
Blade views organized by type: editor UI, block templates, section templates, and Livewire views.

### `/resources/js`
JavaScript for the editor including iframe communication, keyboard shortcuts, and Alpine components.
