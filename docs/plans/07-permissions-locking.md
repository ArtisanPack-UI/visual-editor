# Visual Editor - Permissions & Locking System

## Overview

The permissions and locking system provides two layers of control:
1. **Code-level permissions**: Developer-defined restrictions in config
2. **UI-based locking**: User-applied locks on specific content

This allows developers to control what clients can do, while also letting authorized users lock content to prevent accidental changes.

---

## Code-Level Permissions

### Permission Categories

```php
// config/visual-editor.php

'permissions' => [
    // Template editing
    'can_edit_templates' => true,
    'can_create_templates' => true,
    'can_delete_templates' => false,
    'can_edit_template_parts' => true,

    // Global styles
    'can_edit_global_styles' => true,
    'can_edit_colors' => true,
    'can_edit_typography' => true,
    'can_edit_spacing' => true,

    // Advanced features
    'can_add_custom_css' => false,
    'can_add_custom_html' => false,
    'can_add_custom_js' => false,
    'can_view_code' => false,

    // Sections
    'can_create_sections' => true,
    'can_delete_sections' => true,
    'can_reorder_sections' => true,
    'can_save_section_patterns' => true,

    // Blocks
    'can_add_blocks' => true,
    'can_delete_blocks' => true,
    'can_reorder_blocks' => true,

    // Block restrictions
    'allowed_blocks' => null, // null = all, array = whitelist
    'disallowed_blocks' => ['html', 'code'], // blacklist

    // Section restrictions
    'allowed_sections' => null,
    'disallowed_sections' => [],

    // Content locking
    'can_lock_content' => true,
    'can_unlock_content' => true, // false = only admins can unlock

    // Publishing
    'can_publish' => true,
    'can_schedule' => true,
    'can_unpublish' => true,

    // Versioning
    'can_view_revisions' => true,
    'can_restore_revisions' => true,
    'can_create_named_versions' => true,

    // AI features
    'can_use_ai' => true,

    // A/B testing
    'can_create_experiments' => false,
],
```

### Role-Based Permission Presets

```php
'permission_presets' => [
    'content_editor' => [
        // Limited editing - content only
        'can_edit_templates' => false,
        'can_edit_global_styles' => false,
        'can_add_custom_css' => false,
        'can_add_custom_html' => false,
        'can_create_sections' => false,
        'can_delete_sections' => false,
        'can_lock_content' => false,
        'can_create_experiments' => false,
        'disallowed_blocks' => ['html', 'code', 'shortcode'],
    ],

    'site_editor' => [
        // Full editing - no code access
        'can_edit_templates' => true,
        'can_edit_global_styles' => true,
        'can_add_custom_css' => false,
        'can_add_custom_html' => false,
        'can_lock_content' => true,
        'disallowed_blocks' => ['html', 'code'],
    ],

    'developer' => [
        // Full access
        'can_edit_templates' => true,
        'can_edit_global_styles' => true,
        'can_add_custom_css' => true,
        'can_add_custom_html' => true,
        'can_view_code' => true,
        'can_lock_content' => true,
        'can_unlock_content' => true,
        'allowed_blocks' => null,
        'disallowed_blocks' => [],
    ],
],

// Map user roles to permission presets
'role_permissions' => [
    'admin' => 'developer',
    'editor' => 'site_editor',
    'author' => 'content_editor',
    'contributor' => 'content_editor',
],
```

### Permission Checking

```php
class EditorPermissions
{
    public function can(string $permission): bool
    {
        $user = auth()->user();
        $preset = $this->getPresetForUser($user);
        $permissions = $this->mergePermissions($preset);

        return $permissions[$permission] ?? false;
    }

    public function canUseBlock(string $blockType): bool
    {
        $allowed = $this->permissions['allowed_blocks'];
        $disallowed = $this->permissions['disallowed_blocks'];

        // If whitelist exists, block must be in it
        if ($allowed !== null && !in_array($blockType, $allowed)) {
            return false;
        }

        // Check blacklist
        if (in_array($blockType, $disallowed)) {
            return false;
        }

        return true;
    }

    public function canUseSection(string $sectionType): bool
    {
        $allowed = $this->permissions['allowed_sections'];
        $disallowed = $this->permissions['disallowed_sections'];

        if ($allowed !== null && !in_array($sectionType, $allowed)) {
            return false;
        }

        if (in_array($sectionType, $disallowed)) {
            return false;
        }

        return true;
    }

    public function getAvailableBlocks(): Collection
    {
        return Blocks::all()->filter(fn($block) => $this->canUseBlock($block->getType()));
    }

    public function getAvailableSections(): Collection
    {
        return Sections::all()->filter(fn($section) => $this->canUseSection($section->getType()));
    }
}
```

---

## UI-Based Content Locking

### Lock Levels

```php
enum LockLevel: string
{
    case NONE = 'none';           // No lock
    case MOVE = 'move';           // Can edit, can't move
    case DELETE = 'delete';       // Can edit/move, can't delete
    case CONTENT_ONLY = 'content'; // Can only edit content (text, images)
    case FULL = 'full';           // No changes allowed
}
```

### Lock Application

Locks can be applied to:
- **Templates**: Lock entire template structure
- **Template Parts**: Lock header, footer, etc.
- **Sections**: Lock individual sections
- **Blocks**: Lock specific blocks

### Lock Data Structure

```php
// Stored in content JSON

[
    'sections' => [
        [
            'id' => 'section-123',
            'type' => 'hero',
            'lock' => [
                'level' => 'content',
                'locked_by' => 1,
                'locked_at' => '2026-01-26T12:00:00Z',
                'reason' => 'Approved by client',
            ],
            'blocks' => [
                [
                    'id' => 'block-456',
                    'type' => 'heading',
                    'lock' => [
                        'level' => 'full',
                        'locked_by' => 1,
                        'locked_at' => '2026-01-26T12:00:00Z',
                    ],
                    'content' => [...],
                ],
            ],
        ],
    ],
]
```

### Lock Manager

```php
class LockManager
{
    public function lock(string $itemId, LockLevel $level, ?string $reason = null): void
    {
        if (!$this->permissions->can('can_lock_content')) {
            throw new UnauthorizedException('Cannot lock content');
        }

        $item = $this->findItem($itemId);
        $item->lock = [
            'level' => $level->value,
            'locked_by' => auth()->id(),
            'locked_at' => now()->toIso8601String(),
            'reason' => $reason,
        ];

        $this->save($item);
    }

    public function unlock(string $itemId): void
    {
        if (!$this->permissions->can('can_unlock_content')) {
            throw new UnauthorizedException('Cannot unlock content');
        }

        $item = $this->findItem($itemId);
        $item->lock = null;

        $this->save($item);
    }

    public function canModify(string $itemId, string $action): bool
    {
        $item = $this->findItem($itemId);
        $lock = $item->lock ?? null;

        if (!$lock) {
            return true;
        }

        $level = LockLevel::from($lock['level']);

        return match($action) {
            'edit_content' => $level !== LockLevel::FULL,
            'move' => !in_array($level, [LockLevel::MOVE, LockLevel::CONTENT_ONLY, LockLevel::FULL]),
            'delete' => !in_array($level, [LockLevel::DELETE, LockLevel::CONTENT_ONLY, LockLevel::FULL]),
            'edit_settings' => !in_array($level, [LockLevel::CONTENT_ONLY, LockLevel::FULL]),
            default => false,
        };
    }

    public function isLocked(string $itemId): bool
    {
        $item = $this->findItem($itemId);
        return isset($item->lock) && $item->lock['level'] !== LockLevel::NONE->value;
    }

    public function getLockInfo(string $itemId): ?array
    {
        $item = $this->findItem($itemId);

        if (!isset($item->lock)) {
            return null;
        }

        $lock = $item->lock;
        $lock['locked_by_user'] = User::find($lock['locked_by'])?->name;

        return $lock;
    }
}
```

---

## Lock UI Components

### Lock Button

```blade
{{-- In block/section toolbar --}}

@if($permissions->can('can_lock_content'))
    <div x-data="{ open: false }" class="relative">
        <button
            @click="open = !open"
            class="p-1 rounded hover:bg-gray-100"
            :class="{ 'text-amber-500': {{ $isLocked ? 'true' : 'false' }} }"
        >
            @if($isLocked)
                <x-heroicon-s-lock-closed class="w-4 h-4" />
            @else
                <x-heroicon-o-lock-open class="w-4 h-4" />
            @endif
        </button>

        <div
            x-show="open"
            @click.away="open = false"
            class="absolute right-0 mt-1 w-48 bg-white rounded-lg shadow-lg z-50"
        >
            @if($isLocked)
                <div class="p-3 text-sm">
                    <p class="font-medium">Locked by {{ $lockInfo['locked_by_user'] }}</p>
                    <p class="text-gray-500 text-xs">{{ $lockInfo['locked_at'] }}</p>
                    @if($lockInfo['reason'])
                        <p class="mt-2 text-gray-600">{{ $lockInfo['reason'] }}</p>
                    @endif

                    @if($permissions->can('can_unlock_content'))
                        <button
                            wire:click="unlock('{{ $itemId }}')"
                            class="mt-3 w-full btn btn-sm btn-secondary"
                        >
                            Unlock
                        </button>
                    @endif
                </div>
            @else
                <div class="py-1">
                    <button
                        wire:click="lock('{{ $itemId }}', 'content')"
                        class="w-full px-4 py-2 text-left text-sm hover:bg-gray-50"
                    >
                        <span class="font-medium">Content Only</span>
                        <span class="block text-xs text-gray-500">Allow text/image changes</span>
                    </button>
                    <button
                        wire:click="lock('{{ $itemId }}', 'move')"
                        class="w-full px-4 py-2 text-left text-sm hover:bg-gray-50"
                    >
                        <span class="font-medium">Prevent Moving</span>
                        <span class="block text-xs text-gray-500">Can edit, can't reorder</span>
                    </button>
                    <button
                        wire:click="lock('{{ $itemId }}', 'delete')"
                        class="w-full px-4 py-2 text-left text-sm hover:bg-gray-50"
                    >
                        <span class="font-medium">Prevent Deletion</span>
                        <span class="block text-xs text-gray-500">Can edit/move, can't delete</span>
                    </button>
                    <button
                        wire:click="lock('{{ $itemId }}', 'full')"
                        class="w-full px-4 py-2 text-left text-sm hover:bg-gray-50"
                    >
                        <span class="font-medium">Fully Locked</span>
                        <span class="block text-xs text-gray-500">No changes allowed</span>
                    </button>
                </div>
            @endif
        </div>
    </div>
@endif
```

### Lock Indicator

```blade
{{-- Visual indicator in layers panel --}}

@if($item->lock)
    <span class="ml-1 text-amber-500" title="{{ $this->getLockDescription($item->lock['level']) }}">
        <x-heroicon-s-lock-closed class="w-3 h-3" />
    </span>
@endif
```

---

## Template Locking

Templates can be locked to prevent structural changes:

```php
class TemplateLocking
{
    public function lockTemplate(Template $template, LockLevel $level): void
    {
        $template->update([
            'lock' => [
                'level' => $level->value,
                'locked_by' => auth()->id(),
                'locked_at' => now(),
            ],
        ]);

        // Optionally lock all parts
        if ($level === LockLevel::FULL) {
            foreach ($template->parts as $part) {
                $this->lockTemplatePart($part, $level);
            }
        }
    }

    public function canEditTemplate(Template $template): bool
    {
        if (!$this->permissions->can('can_edit_templates')) {
            return false;
        }

        return !isset($template->lock) ||
               $template->lock['level'] !== LockLevel::FULL->value;
    }
}
```

---

## Inheritance & Cascading

Locks cascade from parent to child:

```php
class LockInheritance
{
    public function getEffectiveLock(string $itemId): ?array
    {
        $item = $this->findItem($itemId);
        $locks = [];

        // Check item's own lock
        if (isset($item->lock)) {
            $locks[] = $item->lock;
        }

        // Check parent section lock
        if ($parent = $this->getParentSection($item)) {
            if (isset($parent->lock)) {
                $locks[] = $parent->lock;
            }
        }

        // Check template part lock
        if ($templatePart = $this->getTemplatePart($item)) {
            if (isset($templatePart->lock)) {
                $locks[] = $templatePart->lock;
            }
        }

        // Check template lock
        if ($template = $this->getTemplate($item)) {
            if (isset($template->lock)) {
                $locks[] = $template->lock;
            }
        }

        // Return most restrictive lock
        if (empty($locks)) {
            return null;
        }

        return $this->getMostRestrictiveLock($locks);
    }

    protected function getMostRestrictiveLock(array $locks): array
    {
        $order = [
            LockLevel::NONE->value => 0,
            LockLevel::DELETE->value => 1,
            LockLevel::MOVE->value => 2,
            LockLevel::CONTENT_ONLY->value => 3,
            LockLevel::FULL->value => 4,
        ];

        usort($locks, fn($a, $b) =>
            ($order[$b['level']] ?? 0) - ($order[$a['level']] ?? 0)
        );

        return $locks[0];
    }
}
```

---

## Configuration Summary

```php
// config/visual-editor.php

'permissions' => [
    // ... permission settings
],

'locking' => [
    // Enable content locking
    'enabled' => true,

    // Lock levels available to users
    'available_levels' => ['content', 'move', 'delete', 'full'],

    // Require reason when locking
    'require_reason' => false,

    // Show lock indicators in editor
    'show_indicators' => true,

    // Allow bulk locking
    'allow_bulk_lock' => true,

    // Auto-lock published content
    'auto_lock_published' => false,
],
```
