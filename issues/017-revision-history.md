/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Area::Backend" ~"Phase::5"

## Problem Statement

**Is your feature request related to a problem?**
Users need to view content history, compare versions, and restore previous states to recover from mistakes or review changes over time.

## Proposed Solution

**What would you like to happen?**
Implement a comprehensive revision history system:

### Revision Types

```php
enum RevisionType: string
{
    case Autosave = 'autosave';    // Automatic periodic saves
    case Manual = 'manual';         // User-triggered saves
    case Named = 'named';           // User-named versions
    case Publish = 'publish';       // State at publish time
    case PreRestore = 'pre_restore'; // Backup before restore
}
```

### Revision Model

```php
namespace ArtisanPackUI\VisualEditor\Models;

class ContentRevision extends Model
{
    protected $table = 've_content_revisions';

    protected $casts = [
        'data' => 'array',
        'type' => RevisionType::class,
    ];

    public function content(): BelongsTo;
    public function user(): BelongsTo;
}
```

### Revision Service

```php
namespace ArtisanPackUI\VisualEditor\Services;

class RevisionService
{
    public function createRevision(Content $content, RevisionType $type, ?string $name = null): ContentRevision;
    public function getRevisions(Content $content, ?RevisionType $type = null): Collection;
    public function restore(Content $content, ContentRevision $revision): Content;
    public function compare(ContentRevision $a, ContentRevision $b): array;
    public function cleanup(Content $content): int; // Returns deleted count
}
```

### Revision Data Structure

```php
[
    'data' => [
        'title' => 'Page Title',
        'slug' => 'page-slug',
        'sections' => [...],
        'settings' => [...],
        'template' => 'default',
        'status' => 'draft',
        'meta' => [...],
    ],
]
```

### Revision UI Features

**Revision Panel:**
- Timeline view of revisions
- Filter by type (autosave, manual, named)
- User and timestamp display
- Change summary (if provided)

**Version Comparison:**
- Side-by-side visual diff
- Highlight added/removed sections
- Highlight changed blocks
- Text diff for content blocks

**Restore Flow:**
1. Select revision to restore
2. Preview restored state
3. Confirm restore
4. Pre-restore backup created automatically
5. Content restored

### Named Versions

- Create named version with custom label
- Named versions never auto-deleted
- Useful for milestones: "Before redesign", "v2.0"

### Cleanup Policy

```php
// Configuration for revision cleanup
'revisions' => [
    'autosave_retention' => 24, // hours
    'manual_retention' => 30,   // days
    'max_autosaves' => 10,      // per content
    'keep_all_named' => true,
    'keep_all_publish' => true,
]
```

## Alternatives Considered

- Full content copies (rejected: storage bloat)
- Diff-only storage (rejected: complex reconstruction)
- External versioning (rejected: integration complexity)

## Use Cases

1. User restores content after accidental deletion
2. Editor compares two versions to see changes
3. Manager creates named version before major update
4. System cleans up old autosaves automatically

## Acceptance Criteria

- [ ] Autosave creates revisions at configured interval
- [ ] Manual save creates revision
- [ ] Named versions can be created
- [ ] Revision panel shows history
- [ ] Revisions can be filtered by type
- [ ] Restore works correctly
- [ ] Pre-restore backup is created
- [ ] Comparison view shows differences
- [ ] Cleanup policy is enforced
- [ ] Named and publish revisions are preserved

---

**Related Issues:**
- Depends on: Database migrations, Save/Publish workflow
- Related: Undo/redo system
