# Visual Editor - GitLab Issues

This directory contains issue files ready to be uploaded to GitLab for the `artisanpack-ui/visual-editor` package.

## Issue Summary

### Phase 1: Core Editor (Priority: High)

| # | Issue | Type | Area |
|---|-------|------|------|
| 001 | [Core Editor Shell](001-core-editor-shell.md) | Feature | Frontend |
| 002 | [Canvas Component](002-canvas-component.md) | Feature | Frontend |
| 003 | [Block Registry System](003-block-registry-system.md) | Feature | Backend |
| 004 | [Basic Text Blocks](004-basic-text-blocks.md) | Feature | Frontend |
| 005 | [Section System](005-section-system.md) | Feature | Backend |
| 006 | [Save/Publish Workflow](006-save-publish-workflow.md) | Feature | Backend |
| 007 | [Undo/Redo System](007-undo-redo-system.md) | Feature | Frontend |
| 021 | [Database Migrations](021-database-migrations.md) | Setup | Backend |
| 022 | [Package Configuration](022-package-configuration.md) | Setup | Backend |

### Phase 2: Full Block Library (Priority: Medium)

| # | Issue | Type | Area |
|---|-------|------|------|
| 008 | [Media Blocks](008-media-blocks.md) | Feature | Frontend |
| 009 | [Layout Blocks](009-layout-blocks.md) | Feature | Frontend |
| 010 | [Interactive Blocks](010-interactive-blocks.md) | Feature | Frontend |
| 011 | [Embed Blocks](011-embed-blocks.md) | Feature | Frontend |
| 012 | [Dynamic Blocks](012-dynamic-blocks.md) | Feature | Backend |
| 025 | [Additional Text Blocks](025-additional-text-blocks.md) | Feature | Frontend |

### Phase 3: Template System (Priority: Medium)

| # | Issue | Type | Area |
|---|-------|------|------|
| 013 | [Template System](013-template-system.md) | Feature | Backend |
| 014 | [Template Parts](014-template-parts.md) | Feature | Frontend |

### Phase 4: Global Styles (Priority: Medium)

| # | Issue | Type | Area |
|---|-------|------|------|
| 015 | [Global Styles System](015-global-styles-system.md) | Feature | Frontend |

### Phase 5: Advanced Features (Priority: Medium/Low)

| # | Issue | Type | Area |
|---|-------|------|------|
| 016 | [Permissions & Locking](016-permissions-locking.md) | Feature | Backend |
| 017 | [Revision History](017-revision-history.md) | Feature | Backend |
| 018 | [AI Assistant](018-ai-assistant.md) | Feature | Backend |
| 019 | [A/B Testing](019-ab-testing.md) | Feature | Backend |
| 023 | [SEO Integration](023-seo-integration.md) | Feature | Backend |

### Phase 6: Polish (Priority: Medium/Low)

| # | Issue | Type | Area |
|---|-------|------|------|
| 020 | [Accessibility Scanner](020-accessibility-scanner.md) | Feature | Frontend |
| 024 | [Presence Awareness](024-presence-awareness.md) | Feature | Frontend |

## Uploading to GitLab

### Using GitLab CLI (glab)

```bash
# Install glab if not already installed
brew install glab

# Authenticate
glab auth login

# Navigate to the visual-editor repository
cd /path/to/visual-editor

# Create issues from files
for file in issues/*.md; do
    if [ "$file" != "issues/README.md" ]; then
        glab issue create --title "$(head -1 "$file" | sed 's/^#* //')" --description "$(cat "$file")"
    fi
done
```

### Using GitLab API

```bash
# Set your GitLab token and project
export GITLAB_TOKEN="your-token"
export PROJECT_ID="your-project-id"

# Create issue via API
curl --request POST \
  --header "PRIVATE-TOKEN: $GITLAB_TOKEN" \
  --header "Content-Type: application/json" \
  --data '{
    "title": "Core Editor Shell",
    "description": "'"$(cat issues/001-core-editor-shell.md)"'"
  }' \
  "https://gitlab.com/api/v4/projects/$PROJECT_ID/issues"
```

### Manual Upload

1. Go to your GitLab project
2. Navigate to Issues > New Issue
3. Copy the content from each `.md` file
4. The `/label` quick action at the top will automatically apply labels
5. Submit the issue

## Labels Used

The issues use the following labels (create them in GitLab first):

**Type Labels:**
- `Type::Feature` - New functionality
- `Type::Setup` - Configuration/infrastructure
- `Type::Enhancement` - Improvements

**Status Labels:**
- `Status::Backlog` - Not yet started

**Priority Labels:**
- `Priority::High` - Critical path items
- `Priority::Medium` - Important but not blocking
- `Priority::Low` - Nice to have

**Area Labels:**
- `Area::Frontend` - UI/Livewire/Alpine
- `Area::Backend` - PHP/Laravel

**Phase Labels:**
- `Phase::1` through `Phase::6`

## Recommended Implementation Order

1. **Infrastructure First:**
   - 021 - Database Migrations
   - 022 - Package Configuration

2. **Core Editor (Phase 1):**
   - 003 - Block Registry System
   - 005 - Section System
   - 001 - Core Editor Shell
   - 002 - Canvas Component
   - 004 - Basic Text Blocks
   - 006 - Save/Publish Workflow
   - 007 - Undo/Redo System

3. **Blocks (Phase 2):**
   - 025 - Additional Text Blocks
   - 008 - Media Blocks
   - 009 - Layout Blocks
   - 010 - Interactive Blocks
   - 011 - Embed Blocks
   - 012 - Dynamic Blocks

4. **Templates (Phase 3):**
   - 013 - Template System
   - 014 - Template Parts

5. **Styling (Phase 4):**
   - 015 - Global Styles System

6. **Advanced (Phase 5):**
   - 016 - Permissions & Locking
   - 017 - Revision History
   - 023 - SEO Integration
   - 018 - AI Assistant
   - 019 - A/B Testing

7. **Polish (Phase 6):**
   - 020 - Accessibility Scanner
   - 024 - Presence Awareness

## Dependencies

```
021 (Migrations) ──┬── 022 (Config) ──┬── 003 (Block Registry)
                   │                  │
                   │                  ├── 005 (Sections)
                   │                  │
                   │                  └── 001 (Editor Shell) ── 002 (Canvas)
                   │
                   └── All other features depend on migrations
```
