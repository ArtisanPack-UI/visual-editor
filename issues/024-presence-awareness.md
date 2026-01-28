/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Low" ~"Area::Frontend" ~"Phase::6"

## Problem Statement

**Is your feature request related to a problem?**
In multi-user environments, editors need to know who else is viewing or editing the same content to avoid conflicts and coordinate work.

## Proposed Solution

**What would you like to happen?**
Implement presence awareness showing active users on content:

### Presence Service

```php
namespace ArtisanPackUI\VisualEditor\Services;

class PresenceService
{
    public function join(Content $content, User $user): void;
    public function leave(Content $content, User $user): void;
    public function heartbeat(Content $content, User $user): void;
    public function getActiveUsers(Content $content): Collection;
    public function isUserActive(Content $content, User $user): bool;
    public function cleanupStale(): int;
}
```

### Active Users Display

- Avatar stack in editor header
- Hover to see user names
- Color-coded cursor/selection (future: real-time collaboration)
- Badge showing count if many users

### Heartbeat System

```javascript
// Client-side heartbeat
setInterval(() => {
    fetch('/api/visual-editor/presence/heartbeat', {
        method: 'POST',
        body: JSON.stringify({
            content_id: contentId,
        }),
    });
}, 30000); // Every 30 seconds
```

### Backend Storage

Uses `ve_editor_locks` table:
- Tracks session ID, user, and last heartbeat
- Stale sessions cleaned up (>2 minutes)
- Supports multiple tabs/sessions per user

### Notifications

- Toast when another user joins
- Toast when another user leaves
- Warning when user has edit lock

### Livewire Integration

```php
// Poll for presence updates
class PresenceIndicator extends Component
{
    public Content $content;
    public Collection $activeUsers;

    public function mount(Content $content): void
    {
        $this->content = $content;
        $this->refresh();
    }

    #[Polling(30)]
    public function refresh(): void
    {
        $this->activeUsers = app(PresenceService::class)
            ->getActiveUsers($this->content);
    }
}
```

## Alternatives Considered

- No presence awareness (rejected: poor multi-user experience)
- WebSocket-based (rejected: complexity for v1)
- Database-only without UI (rejected: not useful without visibility)

## Use Cases

1. Editor sees who else is viewing the page
2. Editor gets notified when colleague joins
3. Editor sees warning if someone has edit lock
4. Stale sessions are cleaned up automatically

## Acceptance Criteria

- [ ] Users see avatar stack of active viewers
- [ ] Heartbeat keeps presence active
- [ ] Stale sessions are removed
- [ ] Join notification appears
- [ ] Leave notification appears
- [ ] Edit lock warning is displayed
- [ ] Works with multiple browser tabs

---

**Related Issues:**
- Depends on: Permissions/Locking system
- Future: Real-time collaboration
