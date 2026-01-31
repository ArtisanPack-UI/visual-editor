/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Area::Backend" ~"Phase::5"

## Problem Statement

**Is your feature request related to a problem?**
Multi-user editing environments need role-based permissions and content locking to prevent conflicts and unauthorized changes.

## Proposed Solution

**What would you like to happen?**
Implement permissions via cms-framework integration and content locking system:

### Permission Integration

```php
// Uses artisanpack-ui/cms-framework permissions
use function ArtisanPackUI\CmsFramework\hasPermissionTo;

// Visual Editor Capabilities
'visual_editor.access'          // Access visual editor
'visual_editor.edit_content'    // Edit content in visual editor
'visual_editor.publish'         // Publish content
'visual_editor.edit_templates'  // Edit templates
'visual_editor.edit_styles'     // Edit global styles
'visual_editor.manage_blocks'   // Manage custom blocks
'visual_editor.edit_locked'     // Override content locks
```

### Permission Checks

```php
namespace ArtisanPackUI\VisualEditor\Services;

class PermissionService
{
    public function canEdit(User $user, Content $content): bool;
    public function canPublish(User $user, Content $content): bool;
    public function canEditTemplates(User $user): bool;
    public function canOverrideLock(User $user, Content $content): bool;
}
```

### Content Locking Levels

```php
[
    'content' => false,  // Prevent content editing
    'move' => false,     // Prevent reordering
    'delete' => false,   // Prevent deletion
    'full' => false,     // Complete lock
]
```

### Lock Structure (on blocks/sections)

```php
[
    'lock' => [
        'type' => 'content',     // content, move, delete, full
        'reason' => 'Brand guidelines require this section',
        'locked_by' => 1,        // User ID
        'locked_at' => '2024-01-15T10:00:00Z',
    ],
]
```

### Lock Service

```php
namespace ArtisanPackUI\VisualEditor\Services;

class LockService
{
    public function lockBlock(string $blockId, string $type, string $reason): void;
    public function unlockBlock(string $blockId): void;
    public function isLocked(string $blockId): bool;
    public function getLock(string $blockId): ?array;
    public function canUserEdit(User $user, string $blockId): bool;
}
```

### UI Indicators

- Locked blocks show lock icon
- Locked blocks are visually distinguished (opacity, border)
- Hover shows lock reason
- Administrators see unlock option
- Settings panel shows lock controls

### Editor Lock (Active Editing)

```php
// Prevent simultaneous editing
namespace ArtisanPackUI\VisualEditor\Models;

class EditorLock extends Model
{
    protected $table = 've_editor_locks';

    public function isStale(): bool
    {
        return $this->last_heartbeat < now()->subMinutes(2);
    }
}
```

Features:
- Heartbeat system (every 30 seconds)
- Stale lock detection (2 minutes)
- Takeover option with warning
- Lock release on editor close

## Alternatives Considered

- No locking (rejected: content conflicts)
- First-come-first-served (rejected: no collaboration)
- Real-time collaboration (rejected: complexity for v1)

## Use Cases

1. Admin locks branding section from editing
2. Editor tries to edit locked block, sees reason
3. Two users try to edit same content, second user sees lock
4. Admin takes over stale lock

## Acceptance Criteria

- [ ] Permissions integrate with cms-framework
- [ ] Permission checks work in editor
- [ ] Block locking UI shows lock status
- [ ] Lock reasons display on hover
- [ ] Administrators can override locks
- [ ] Editor locks prevent simultaneous editing
- [ ] Heartbeat keeps lock active
- [ ] Stale locks can be taken over
- [ ] Lock takeover shows warning

---

**Related Issues:**
- Depends on: artisanpack-ui/cms-framework, Database migrations
- Related: Presence awareness feature
