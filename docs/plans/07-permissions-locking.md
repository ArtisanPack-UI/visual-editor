# Visual Editor - Permissions & Locking System

> **Phase:** 5 (Advanced Features) — Medium Priority
>
> Includes: CMS Framework permissions integration, UI-based content locking, Lock levels

---

## Overview

The permissions and locking system provides two layers of control:
1. **CMS Framework Permissions**: Role-based permissions via `artisanpack-ui/cms-framework`
2. **UI-based locking**: User-applied locks on specific content

This leverages the CMS framework's role and permission system, allowing both code-level restrictions and admin interface configuration.

---

## Integration with CMS Framework

The visual editor uses `artisanpack-ui/cms-framework` for all permission management:

- **Roles & Permissions**: Uses the CMS framework's `HasRolesAndPermissions` trait
- **Permission Registration**: Uses `ap_register_permission()` helper
- **Role Management**: Uses `ap_register_role()` and `ap_add_permission_to_role()`
- **Admin Interface**: Permissions can be managed via the CMS admin interface

---

## Permission Registration

### Visual Editor Permissions

Permissions are registered during package boot via the CMS framework:

```php
// VisualEditorServiceProvider.php

public function boot(): void
{
    $this->registerPermissions();
    $this->registerRoles();
}

protected function registerPermissions(): void
{
    // Core editor access
    ap_register_permission('visual_editor.access', 'Access Visual Editor');
    ap_register_permission('visual_editor.manage', 'Manage Visual Editor Settings');

    // Template permissions
    ap_register_permission('visual_editor.templates.view', 'View Templates');
    ap_register_permission('visual_editor.templates.create', 'Create Templates');
    ap_register_permission('visual_editor.templates.edit', 'Edit Templates');
    ap_register_permission('visual_editor.templates.delete', 'Delete Templates');
    ap_register_permission('visual_editor.template_parts.edit', 'Edit Template Parts');

    // Global styles permissions
    ap_register_permission('visual_editor.styles.view', 'View Global Styles');
    ap_register_permission('visual_editor.styles.edit', 'Edit Global Styles');
    ap_register_permission('visual_editor.styles.colors', 'Edit Colors');
    ap_register_permission('visual_editor.styles.typography', 'Edit Typography');
    ap_register_permission('visual_editor.styles.spacing', 'Edit Spacing');

    // Advanced features
    ap_register_permission('visual_editor.custom_css', 'Add Custom CSS');
    ap_register_permission('visual_editor.custom_html', 'Add Custom HTML');
    ap_register_permission('visual_editor.custom_js', 'Add Custom JavaScript');
    ap_register_permission('visual_editor.view_code', 'View Generated Code');

    // Section permissions
    ap_register_permission('visual_editor.sections.create', 'Create Sections');
    ap_register_permission('visual_editor.sections.delete', 'Delete Sections');
    ap_register_permission('visual_editor.sections.reorder', 'Reorder Sections');
    ap_register_permission('visual_editor.sections.save_patterns', 'Save Section Patterns');

    // Block permissions
    ap_register_permission('visual_editor.blocks.add', 'Add Blocks');
    ap_register_permission('visual_editor.blocks.delete', 'Delete Blocks');
    ap_register_permission('visual_editor.blocks.reorder', 'Reorder Blocks');

    // Content locking
    ap_register_permission('visual_editor.content.lock', 'Lock Content');
    ap_register_permission('visual_editor.content.unlock', 'Unlock Content');

    // Publishing
    ap_register_permission('visual_editor.content.publish', 'Publish Content');
    ap_register_permission('visual_editor.content.schedule', 'Schedule Content');
    ap_register_permission('visual_editor.content.unpublish', 'Unpublish Content');

    // Versioning
    ap_register_permission('visual_editor.revisions.view', 'View Revisions');
    ap_register_permission('visual_editor.revisions.restore', 'Restore Revisions');
    ap_register_permission('visual_editor.versions.create', 'Create Named Versions');

    // Optional features
    ap_register_permission('visual_editor.ai.use', 'Use AI Assistant');
    ap_register_permission('visual_editor.experiments.create', 'Create A/B Experiments');
}
```

### Default Roles

The visual editor registers default roles with appropriate permissions:

```php
protected function registerRoles(): void
{
    // Content Editor - Limited to content only
    $contentEditor = ap_register_role('visual_editor_content', 'Content Editor');
    ap_add_permission_to_role('visual_editor_content', 'visual_editor.access');
    ap_add_permission_to_role('visual_editor_content', 'visual_editor.blocks.add');
    ap_add_permission_to_role('visual_editor_content', 'visual_editor.blocks.delete');
    ap_add_permission_to_role('visual_editor_content', 'visual_editor.blocks.reorder');
    ap_add_permission_to_role('visual_editor_content', 'visual_editor.revisions.view');

    // Site Editor - Full editing, no code access
    $siteEditor = ap_register_role('visual_editor_site', 'Site Editor');
    ap_add_permission_to_role('visual_editor_site', 'visual_editor.access');
    ap_add_permission_to_role('visual_editor_site', 'visual_editor.templates.view');
    ap_add_permission_to_role('visual_editor_site', 'visual_editor.templates.edit');
    ap_add_permission_to_role('visual_editor_site', 'visual_editor.template_parts.edit');
    ap_add_permission_to_role('visual_editor_site', 'visual_editor.styles.view');
    ap_add_permission_to_role('visual_editor_site', 'visual_editor.styles.edit');
    ap_add_permission_to_role('visual_editor_site', 'visual_editor.styles.colors');
    ap_add_permission_to_role('visual_editor_site', 'visual_editor.styles.typography');
    ap_add_permission_to_role('visual_editor_site', 'visual_editor.styles.spacing');
    ap_add_permission_to_role('visual_editor_site', 'visual_editor.sections.create');
    ap_add_permission_to_role('visual_editor_site', 'visual_editor.sections.delete');
    ap_add_permission_to_role('visual_editor_site', 'visual_editor.sections.reorder');
    ap_add_permission_to_role('visual_editor_site', 'visual_editor.sections.save_patterns');
    ap_add_permission_to_role('visual_editor_site', 'visual_editor.blocks.add');
    ap_add_permission_to_role('visual_editor_site', 'visual_editor.blocks.delete');
    ap_add_permission_to_role('visual_editor_site', 'visual_editor.blocks.reorder');
    ap_add_permission_to_role('visual_editor_site', 'visual_editor.content.lock');
    ap_add_permission_to_role('visual_editor_site', 'visual_editor.content.publish');
    ap_add_permission_to_role('visual_editor_site', 'visual_editor.content.schedule');
    ap_add_permission_to_role('visual_editor_site', 'visual_editor.revisions.view');
    ap_add_permission_to_role('visual_editor_site', 'visual_editor.revisions.restore');
    ap_add_permission_to_role('visual_editor_site', 'visual_editor.versions.create');

    // Developer - Full access including code
    $developer = ap_register_role('visual_editor_developer', 'Editor Developer');
    // All permissions from site_editor plus:
    ap_add_permission_to_role('visual_editor_developer', 'visual_editor.manage');
    ap_add_permission_to_role('visual_editor_developer', 'visual_editor.templates.create');
    ap_add_permission_to_role('visual_editor_developer', 'visual_editor.templates.delete');
    ap_add_permission_to_role('visual_editor_developer', 'visual_editor.custom_css');
    ap_add_permission_to_role('visual_editor_developer', 'visual_editor.custom_html');
    ap_add_permission_to_role('visual_editor_developer', 'visual_editor.custom_js');
    ap_add_permission_to_role('visual_editor_developer', 'visual_editor.view_code');
    ap_add_permission_to_role('visual_editor_developer', 'visual_editor.content.unlock');
    ap_add_permission_to_role('visual_editor_developer', 'visual_editor.ai.use');
    ap_add_permission_to_role('visual_editor_developer', 'visual_editor.experiments.create');
}
```

### Permission Checking

Uses the CMS framework's `HasRolesAndPermissions` trait on the User model:

```php
class EditorPermissions
{
    protected User $user;

    public function __construct(?User $user = null)
    {
        $this->user = $user ?? auth()->user();
    }

    public function can(string $permission): bool
    {
        // Uses CMS framework's permission check
        return $this->user->hasPermissionTo($permission);
    }

    public function canAccessEditor(): bool
    {
        return $this->can('visual_editor.access');
    }

    public function canEditTemplates(): bool
    {
        return $this->can('visual_editor.templates.edit');
    }

    public function canUseBlock(string $blockType): bool
    {
        // Check base permission
        if (!$this->can('visual_editor.blocks.add')) {
            return false;
        }

        // Check block-specific restrictions from settings
        $allowedBlocks = apGetSetting('visual_editor.allowed_blocks');
        $disallowedBlocks = apGetSetting('visual_editor.disallowed_blocks', []);

        // If whitelist exists, block must be in it
        if ($allowedBlocks !== null && !in_array($blockType, $allowedBlocks)) {
            return false;
        }

        // Check blacklist
        if (in_array($blockType, $disallowedBlocks)) {
            return false;
        }

        // Check code blocks require additional permission
        if (in_array($blockType, ['html', 'code']) && !$this->can('visual_editor.custom_html')) {
            return false;
        }

        return true;
    }

    public function canUseSection(string $sectionType): bool
    {
        if (!$this->can('visual_editor.sections.create')) {
            return false;
        }

        $allowedSections = apGetSetting('visual_editor.allowed_sections');
        $disallowedSections = apGetSetting('visual_editor.disallowed_sections', []);

        if ($allowedSections !== null && !in_array($sectionType, $allowedSections)) {
            return false;
        }

        if (in_array($sectionType, $disallowedSections)) {
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
    protected EditorPermissions $permissions;

    public function __construct()
    {
        $this->permissions = new EditorPermissions();
    }

    public function lock(string $itemId, LockLevel $level, ?string $reason = null): void
    {
        // Uses CMS framework permission
        if (!$this->permissions->can('visual_editor.content.lock')) {
            throw new UnauthorizedException(__('Cannot lock content'));
        }

        $item = $this->findItem($itemId);
        $item->lock = [
            'level' => $level->value,
            'locked_by' => auth()->id(),
            'locked_at' => now()->toIso8601String(),
            'reason' => $reason,
        ];

        $this->save($item);

        // Fire hook for extensions
        doAction('ap.visualEditor.contentLocked', $item, $level);
    }

    public function unlock(string $itemId): void
    {
        // Uses CMS framework permission
        if (!$this->permissions->can('visual_editor.content.unlock')) {
            throw new UnauthorizedException(__('Cannot unlock content'));
        }

        $item = $this->findItem($itemId);
        $item->lock = null;

        $this->save($item);

        // Fire hook for extensions
        doAction('ap.visualEditor.contentUnlocked', $item);
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

Uses `artisanpack-ui/livewire-ui-components` for all UI elements.

### Lock Button

```blade
{{-- In block/section toolbar --}}

@if($permissions->can('visual_editor.content.lock'))
    <x-artisanpack-dropdown>
        <x-slot:trigger>
            <x-artisanpack-button size="sm" variant="ghost">
                @if($isLocked)
                    <x-artisanpack-icon name="o-lock-closed" class="w-4 h-4 text-amber-500" />
                @else
                    <x-artisanpack-icon name="o-lock-open" class="w-4 h-4" />
                @endif
            </x-artisanpack-button>
        </x-slot:trigger>

        @if($isLocked)
            <div class="p-3">
                <p class="font-medium text-sm">{{ __('Locked by :user', ['user' => $lockInfo['locked_by_user']]) }}</p>
                <p class="text-gray-500 text-xs">{{ $lockInfo['locked_at'] }}</p>
                @if($lockInfo['reason'])
                    <p class="mt-2 text-gray-600 text-sm">{{ $lockInfo['reason'] }}</p>
                @endif

                @if($permissions->can('visual_editor.content.unlock'))
                    <x-artisanpack-button wire:click="unlock('{{ $itemId }}')" size="sm" class="mt-3 w-full">
                        {{ __('Unlock') }}
                    </x-artisanpack-button>
                @endif
            </div>
        @else
            <x-artisanpack-menu>
                <x-artisanpack-menu-item wire:click="lock('{{ $itemId }}', 'content')">
                    <span class="font-medium">{{ __('Content Only') }}</span>
                    <span class="block text-xs text-gray-500">{{ __('Allow text/image changes') }}</span>
                </x-artisanpack-menu-item>
                <x-artisanpack-menu-item wire:click="lock('{{ $itemId }}', 'move')">
                    <span class="font-medium">{{ __('Prevent Moving') }}</span>
                    <span class="block text-xs text-gray-500">{{ __('Can edit, can\'t reorder') }}</span>
                </x-artisanpack-menu-item>
                <x-artisanpack-menu-item wire:click="lock('{{ $itemId }}', 'delete')">
                    <span class="font-medium">{{ __('Prevent Deletion') }}</span>
                    <span class="block text-xs text-gray-500">{{ __('Can edit/move, can\'t delete') }}</span>
                </x-artisanpack-menu-item>
                <x-artisanpack-menu-item wire:click="lock('{{ $itemId }}', 'full')">
                    <span class="font-medium">{{ __('Fully Locked') }}</span>
                    <span class="block text-xs text-gray-500">{{ __('No changes allowed') }}</span>
                </x-artisanpack-menu-item>
            </x-artisanpack-menu>
        @endif
    </x-artisanpack-dropdown>
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
    protected EditorPermissions $permissions;

    public function __construct()
    {
        $this->permissions = new EditorPermissions();
    }

    public function lockTemplate(Template $template, LockLevel $level): void
    {
        // Uses CMS framework permission
        if (!$this->permissions->can('visual_editor.content.lock')) {
            throw new UnauthorizedException(__('Cannot lock templates'));
        }

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

        doAction('ap.visualEditor.templateLocked', $template, $level);
    }

    public function canEditTemplate(Template $template): bool
    {
        // Uses CMS framework permission
        if (!$this->permissions->can('visual_editor.templates.edit')) {
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

## Admin Interface Integration

The visual editor integrates with the CMS framework's admin interface for permissions and settings management.

### Admin Menu Registration

```php
// VisualEditorServiceProvider.php

protected function registerAdminPages(): void
{
    // Add Visual Editor section to admin menu
    apAddAdminSection('visual-editor', __('Visual Editor'), 20);

    // Main editor page
    apAddAdminPage(
        __('Editor'),
        'visual-editor',
        'visual-editor',
        [
            'action' => VisualEditorController::class . '@index',
            'icon' => 'o-pencil-square',
            'capability' => 'visual_editor.access',
            'order' => 10,
        ]
    );

    // Templates management
    apAddAdminPage(
        __('Templates'),
        'visual-editor-templates',
        'visual-editor',
        [
            'action' => TemplateController::class . '@index',
            'icon' => 'o-document-duplicate',
            'capability' => 'visual_editor.templates.view',
            'order' => 20,
        ]
    );

    // Global styles
    apAddAdminPage(
        __('Global Styles'),
        'visual-editor-styles',
        'visual-editor',
        [
            'action' => GlobalStylesController::class . '@index',
            'icon' => 'o-paint-brush',
            'capability' => 'visual_editor.styles.view',
            'order' => 30,
        ]
    );

    // Settings (requires manage permission)
    apAddAdminPage(
        __('Settings'),
        'visual-editor-settings',
        'visual-editor',
        [
            'action' => SettingsController::class . '@index',
            'icon' => 'o-cog-6-tooth',
            'capability' => 'visual_editor.manage',
            'order' => 40,
        ]
    );
}
```

### Settings Storage via CMS Framework

Settings are stored using the CMS framework's settings system, allowing admin interface control:

```php
// VisualEditorServiceProvider.php

protected function registerSettings(): void
{
    // Block restrictions (can be configured via admin)
    apRegisterSetting('visual_editor.allowed_blocks', null, fn($v) => $v, 'json');
    apRegisterSetting('visual_editor.disallowed_blocks', [], fn($v) => $v, 'json');

    // Section restrictions
    apRegisterSetting('visual_editor.allowed_sections', null, fn($v) => $v, 'json');
    apRegisterSetting('visual_editor.disallowed_sections', [], fn($v) => $v, 'json');

    // Locking settings
    apRegisterSetting('visual_editor.locking.enabled', true, fn($v) => (bool) $v, 'boolean');
    apRegisterSetting('visual_editor.locking.available_levels', ['content', 'move', 'delete', 'full'], fn($v) => $v, 'json');
    apRegisterSetting('visual_editor.locking.require_reason', false, fn($v) => (bool) $v, 'boolean');
    apRegisterSetting('visual_editor.locking.show_indicators', true, fn($v) => (bool) $v, 'boolean');
    apRegisterSetting('visual_editor.locking.allow_bulk_lock', true, fn($v) => (bool) $v, 'boolean');
    apRegisterSetting('visual_editor.locking.auto_lock_published', false, fn($v) => (bool) $v, 'boolean');

    // AI settings
    apRegisterSetting('visual_editor.ai.enabled', false, fn($v) => (bool) $v, 'boolean');
    apRegisterSetting('visual_editor.ai.provider', 'openai', fn($v) => sanitizeText($v), 'string');

    // Performance settings
    apRegisterSetting('visual_editor.performance.max_weight', 2097152, fn($v) => (int) $v, 'integer');
    apRegisterSetting('visual_editor.performance.max_images', 20, fn($v) => (int) $v, 'integer');

    // Accessibility settings
    apRegisterSetting('visual_editor.accessibility.require_alt_text', true, fn($v) => (bool) $v, 'boolean');
    apRegisterSetting('visual_editor.accessibility.check_contrast', true, fn($v) => (bool) $v, 'boolean');
}
```

### Retrieving Settings

```php
// Anywhere in the application

// Get a setting with fallback
$maxImages = apGetSetting('visual_editor.performance.max_images', 20);

// Check if AI is enabled
$aiEnabled = apGetSetting('visual_editor.ai.enabled', false);

// Get disallowed blocks
$disallowedBlocks = apGetSetting('visual_editor.disallowed_blocks', []);
```

### Admin Settings Page Component

```php
// Livewire component for settings page

class VisualEditorSettings extends Component
{
    public bool $lockingEnabled;
    public bool $requireReason;
    public bool $aiEnabled;
    public string $aiProvider;
    public int $maxImages;
    public int $maxWeight;

    public function mount(): void
    {
        $this->lockingEnabled = apGetSetting('visual_editor.locking.enabled', true);
        $this->requireReason = apGetSetting('visual_editor.locking.require_reason', false);
        $this->aiEnabled = apGetSetting('visual_editor.ai.enabled', false);
        $this->aiProvider = apGetSetting('visual_editor.ai.provider', 'openai');
        $this->maxImages = apGetSetting('visual_editor.performance.max_images', 20);
        $this->maxWeight = apGetSetting('visual_editor.performance.max_weight', 2097152);
    }

    public function save(): void
    {
        // Requires manage permission
        if (!auth()->user()->hasPermissionTo('visual_editor.manage')) {
            abort(403);
        }

        apUpdateSetting('visual_editor.locking.enabled', $this->lockingEnabled);
        apUpdateSetting('visual_editor.locking.require_reason', $this->requireReason);
        apUpdateSetting('visual_editor.ai.enabled', $this->aiEnabled);
        apUpdateSetting('visual_editor.ai.provider', $this->aiProvider);
        apUpdateSetting('visual_editor.performance.max_images', $this->maxImages);
        apUpdateSetting('visual_editor.performance.max_weight', $this->maxWeight);

        $this->dispatch('toast', message: __('Settings saved successfully'));
    }
}
```

---

## Configuration Summary

The visual editor uses a hybrid configuration approach:

1. **Static Config**: `config/visual-editor.php` for non-admin-configurable settings
2. **CMS Framework Settings**: `apGetSetting()` / `apUpdateSetting()` for admin-configurable settings
3. **CMS Framework Permissions**: Role-based access via `hasPermissionTo()`

```php
// config/visual-editor.php (static config only)

return [
    // Route configuration (not admin-configurable)
    'route_prefix' => 'admin/editor',
    'middleware' => ['web', 'auth', 'verified'],

    // Default values (admin can override via settings)
    'defaults' => [
        'locking' => [
            'enabled' => true,
            'available_levels' => ['content', 'move', 'delete', 'full'],
            'require_reason' => false,
            'show_indicators' => true,
            'allow_bulk_lock' => true,
            'auto_lock_published' => false,
        ],
    ],
];
```

### Settings vs Config

| Setting | Source | Admin Editable |
|---------|--------|----------------|
| Route prefix | Config | No |
| Middleware | Config | No |
| Block restrictions | CMS Settings | Yes |
| Section restrictions | CMS Settings | Yes |
| Locking options | CMS Settings | Yes |
| AI settings | CMS Settings | Yes |
| Performance budgets | CMS Settings | Yes |
| User permissions | CMS Roles | Yes |
