# Livewire embedding recipe

The visual editor ships as a self-contained React app, so Livewire can't
render it directly. Instead, mount it inside `wire:ignore` and bridge
editor activity back to the component with three browser events.

## 1. Render the editor inside `wire:ignore`

`wire:ignore` tells Livewire to leave the mount point alone during DOM
diffs. Without it, Livewire's morph would wipe out the React tree on every
`$wire` update.

```blade
<div>
    <div
        wire:ignore
        @ve:editor:change.window="$wire.set('dirty', true)"
        @ve:editor:autosave.window="$wire.handleAutosaved($event.detail)"
        @ve:editor:save.window="$wire.handleSaved($event.detail)"
    >
        <x-visual-editor :model="$post" />
    </div>

    @if ($flash)
        <div class="alert alert-success">{{ $flash }}</div>
    @endif
</div>
```

The `@ve:editor:*` attributes are Alpine listeners (Alpine ships with
Livewire 3 by default). `.window` is required — the events are dispatched
on `window`, not on the editor mount point.

## 2. Receive the payload in the Livewire component

```php
use Livewire\Attributes\Locked;
use Livewire\Component;

class PostEditor extends Component
{
    #[Locked]
    public int $postId;

    public bool $dirty = false;

    public ?string $lastSavedAt = null;

    public ?string $flash = null;

    /**
     * @param  array{resource: string, id: string, blocks: array, updatedAt: ?string}  $detail
     */
    public function handleAutosaved(array $detail): void
    {
        $this->dirty = false;
        $this->lastSavedAt = $detail['updatedAt'];
    }

    /**
     * @param  array{resource: string, id: string, blocks: array, updatedAt: ?string}  $detail
     */
    public function handleSaved(array $detail): void
    {
        $this->dirty = false;
        $this->lastSavedAt = $detail['updatedAt'];
        $this->flash = __('Post saved at :time', ['time' => $detail['updatedAt']]);
    }
}
```

Each handler receives `$event.detail` — the typed `CustomEvent` payload
documented below. Livewire serializes it as a PHP array, so type-hint with
an array shape.

## 3. Event contract

Every event is dispatched on `window` as a `CustomEvent`. TypeScript hosts
can `import { VE_EDITOR_SAVE, type VeEditorSaveDetail } from
'@artisanpack-ui/visual-editor/editor'` to stay in sync.

| Event | When it fires | `detail` shape |
|-------|--------------|----------------|
| `ve:editor:change` | Debounce window closes, right before the autosave request goes out. | `{ resource, id, blocks }` |
| `ve:editor:autosave` | Debounce-triggered save returns `200`. | `{ resource, id, blocks, updatedAt }` |
| `ve:editor:save` | Explicit save (⌘S / top-bar Save) returns `200`. | `{ resource, id, blocks, updatedAt }` |

`resource` and `id` match the `data-resource` / `data-id` attributes the
Blade component emits, so a single listener can disambiguate when multiple
editors are mounted on the same page.

## 4. Listening in plain JavaScript

Outside Alpine — e.g. a vanilla Blade page with inline script:

```html
<script>
    window.addEventListener('ve:editor:save', (event) => {
        console.log('saved', event.detail.resource, event.detail.id, event.detail.updatedAt);
    });
</script>
```

## Working example

The `artisanpack-ui` dev app ships a Volt component that wires all three
events to live UI. See
`resources/views/packages/visual-editor/m13-livewire-editor.blade.php`
(route: `/packages/visual-editor/m13/livewire/post/{post}`).
