/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Area::Backend" ~"Phase::1"

## Problem Statement

**Is your feature request related to a problem?**
The editor needs a robust save and publish workflow that handles drafts, autosave, scheduled publishing, and content status management.

## Proposed Solution

**What would you like to happen?**
Implement the complete save and publish workflow:

### Content Status States

```php
enum ContentStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Published = 'published';
    case Scheduled = 'scheduled';
    case Private = 'private';
}
```

### Save Operations

**Autosave:**
- Automatic save every 60 seconds (configurable)
- Save on significant changes (10+ character change)
- Debounced save on blur
- Visual indicator of save status
- Stored as revision with type 'autosave'

**Manual Save:**
- Save button in toolbar
- Keyboard shortcut (Ctrl+S)
- Creates revision with type 'manual'
- Shows success toast notification

### Publish Operations

```php
class ContentService
{
    public function saveDraft(Content $content, array $data): Content;
    public function publish(Content $content): Content;
    public function unpublish(Content $content): Content;
    public function schedule(Content $content, Carbon $publishAt): Content;
    public function submitForReview(Content $content): Content;
}
```

**Publish Flow:**
1. Click "Publish" button
2. Pre-publish checklist panel opens
3. Set visibility (public, private)
4. Optionally schedule for future
5. Confirm publish

**Pre-publish Checklist:**
- Title is set
- Featured image (optional warning)
- SEO meta (if SEO package installed)
- Categories/tags assigned
- Accessibility check passed

### API Endpoints

```
POST /api/visual-editor/content/{id}/save
POST /api/visual-editor/content/{id}/publish
POST /api/visual-editor/content/{id}/unpublish
POST /api/visual-editor/content/{id}/schedule
```

## Alternatives Considered

- Auto-publish on save (rejected: no review workflow)
- Separate draft/live copies (rejected: complexity)
- Version-based publishing (rejected: overcomplicated)

## Use Cases

1. User saves draft while working
2. Autosave recovers content after browser crash
3. User publishes content when ready
4. User schedules content for future date
5. Editor submits content for review

## Acceptance Criteria

- [ ] Autosave works at configured interval
- [ ] Manual save creates revision
- [ ] Publish changes status and sets published_at
- [ ] Unpublish reverts to draft
- [ ] Schedule sets scheduled_at and status
- [ ] Pre-publish checklist validates content
- [ ] Save status indicator in toolbar
- [ ] Ctrl+S keyboard shortcut works
- [ ] Proper error handling and user feedback

---

**Related Issues:**
- Depends on: Database migrations, Editor Shell
- Blocks: Revision history, Content preview
