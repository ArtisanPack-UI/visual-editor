# Media Picker Integration - Debug Status

## The Goal

Wire up the `media_picker` inspector field type so that selecting an image from the media library modal updates the block attribute (e.g., `backgroundImage`) in the editor store.

## What Works

1. **`@case('media_picker')` in `inspector-field.blade.php`** - Renders correctly with "Select image" button, thumbnail preview, Replace/Remove buttons
2. **`open-ve-media-picker` Livewire event** - The inspector field dispatches this, and the bridge component (`media-picker.blade.php`) receives it via `#[On('open-ve-media-picker')]`
3. **Bridge opens the modal** - `$this->dispatch('open-media-modal', context: '')` works, the MediaModal opens
4. **`window.__veMediaPickerContext`** - The bridge's `open()` method stores the context (e.g., `"blockId:backgroundImage"`) in a JS global via `$this->js()`
5. **Image selection in modal** - User can browse/upload and select an image in the MediaModal
6. **`ve-field-change` listener in `editor-state.blade.php`** - Added at line ~625, routes field changes to `updateBlock()`, resolves `"dynamic"` blockId to the focused block from the selection store
7. **EditorLayoutTest** - Fixed by adding `beforeEach` with `Livewire::addNamespace('media', classNamespace: 'Tests\\Stubs')` to avoid icon asset issues
8. **All 660 tests pass**

## What's Broken

The `media-selected` Livewire event dispatched by MediaModal's `confirmSelection()` method **never reaches any listener** — neither the bridge's PHP `#[On('media-selected')]` handler nor the client-side `Livewire.on('media-selected')` listener.

### The Event Chain (Where It Breaks)

```
Inspector field clicks "Select image"
  → Livewire.dispatch('open-ve-media-picker', { context }) ✅
  → Bridge open() receives it, stores context in window.__veMediaPickerContext ✅
  → Bridge dispatches 'open-media-modal' → MediaModal opens ✅
  → User selects image, clicks confirm ✅
  → MediaModal::confirmSelection() runs:
      → $this->dispatch('media-selected', media: [...], context: '') ✅ (PHP side)
      → $this->close() ✅
      → $this->success('...') ← THIS CAUSES A JS ERROR ❌
  → JS processing of Livewire response:
      → "Can't find variable: toast" error thrown ❌
      → media-selected event NEVER reaches JS listeners ❌
      → Bridge's #[On('media-selected')] NEVER fires ❌
      → Livewire.on('media-selected') in editor-state NEVER fires ❌
```

### The Root Cause

`MediaModal::confirmSelection()` in the media-library package (`/Users/jacobmartella/Desktop/ArtisanPack UI Packages/media-library/src/Livewire/Components/MediaModal.php`, line ~509) calls `$this->success()` after dispatching the event. This `success()` method (likely from a toast/notification trait) evaluates JS that calls a `toast()` function. That function doesn't exist in the visual editor context, causing:

```
[Error] Unhandled Promise Rejection: ReferenceError: Can't find variable: toast
```

This error appears to **prevent Livewire from completing its effect processing**, which means the `media-selected` event dispatch effect never reaches JavaScript listeners. All three of these effects are batched in the same Livewire response:
1. `dispatch('media-selected', ...)` — the event we need
2. Effects from `$this->close()` — modal state changes
3. Effects from `$this->success(...)` — the broken toast JS

The toast error kills the entire batch.

## What We've Tried

### 1. Bridge PHP Handler (Original Approach)
- `#[On('media-selected')]` on bridge's `onMediaSelected()` method
- **Result**: Never fires because the Livewire event never reaches it

### 2. `<script>` Tag in Bridge Blade with `livewire:init`
- Added `Livewire.on('media-selected', ...)` inside a `document.addEventListener('livewire:init', ...)` in the bridge's blade template
- **Result**: `livewire:init` has already fired by the time the bridge component renders (it's at the bottom of the page in `editor-layout.blade.php`)

### 3. `Livewire.on()` in Editor State's `_registerLivewireBridge()`
- Added `Livewire.on('media-selected', ...)` in `editor-state.blade.php` line ~613, inside the Alpine store's `_registerLivewireBridge()` method which runs during store initialization (early in page lifecycle)
- **Result**: Listener IS registered early enough, but the callback never fires. The toast error prevents the event from being dispatched to JS listeners entirely.

### 4. Debug Logging
- Added `console.log('[ve-bridge] ...')` statements inside the `Livewire.on()` callback
- **Result**: No logs appear at all, confirming the event never reaches JS

## Current State of Files

### Modified Files (with current debug code to clean up)

1. **`resources/views/components/editor-state.blade.php`**
   - Line ~53: `_registerLivewireBridge()` called from `init()`
   - Lines ~607-611: `ve-media-selected` DOM event listener (announce action)
   - Lines ~613-636: `Livewire.on('media-selected', ...)` — **HAS DEBUG console.logs that need removal**
   - Lines ~638-654: `ve-field-change` DOM event listener (routes to `updateBlock()`)

2. **`resources/views/components/inspector-field.blade.php`**
   - Lines ~152-205: `@case('media_picker')` — clean, debug logs already removed

3. **`resources/views/livewire/media-picker.blade.php`**
   - Bridge Volt component — clean, debug logs already removed
   - PHP: `open()` and `onMediaSelected()` methods
   - Blade: Renders `<livewire:media::media-modal>` with empty context

4. **`resources/views/components/editor-layout.blade.php`**
   - Lines ~86-88: Guard renders bridge when `MediaModal::class` exists

5. **`tests/Unit/Components/EditorLayoutTest.php`**
   - `beforeEach` with media stub namespace — working correctly

6. **`resources/lang/en/ve.php`**
   - Has `replace_image`, `remove_image`, `select_media`, `media_selected` keys

## Potential Solutions

### Option A: Fix the Toast Error in MediaModal (Simplest)
The `$this->success()` call in `MediaModal::confirmSelection()` is the root cause. Options:
1. **Wrap in try/catch**: In MediaModal, wrap `$this->success()` in a try-catch or make it conditional
2. **Remove the success call**: The modal closes anyway, the success toast is nice-to-have
3. **Ensure toast JS is available**: Include the toast library/function in the visual editor's page
4. **Reorder effects**: Move `$this->dispatch('media-selected', ...)` AFTER `$this->success()` — but this likely won't help since they're all batched

**Recommended**: Check what `$this->success()` does (which trait it comes from), and either make the toast JS available in the editor context or wrap the call so it doesn't break effect processing.

### Option B: Client-Side Interception Before Effects Process
Use Livewire's JavaScript hooks to intercept the response before effect processing:
```js
Livewire.hook('commit', ({ succeed }) => {
    succeed(({ effects }) => {
        // Extract media-selected event from effects before toast error
    });
});
```
This is complex and fragile but would bypass the toast issue entirely.

### Option C: Bypass Livewire Events Entirely
Instead of relying on the `media-selected` Livewire event, hook into the MediaModal's DOM:
1. Listen for the modal closing
2. Check if media was selected via a JS global or data attribute
3. Dispatch the `ve-media-selected` CustomEvent directly

This avoids the Livewire event system entirely but is fragile and couples to MediaModal's internals.

### Option D: Use `wire:navigate` or Alpine `$wire` to Call Bridge Directly
Instead of relying on Livewire's event broadcasting, have the inspector field call the bridge component's method directly via `$wire`. This requires knowing the bridge's component ID.

## Key Files Reference

| File | Path | Purpose |
|------|------|---------|
| MediaModal | `media-library/src/Livewire/Components/MediaModal.php` | The modal component that dispatches `media-selected` |
| Bridge | `visual-editor/resources/views/livewire/media-picker.blade.php` | Volt component bridging inspector ↔ media modal |
| Inspector Field | `visual-editor/resources/views/components/inspector-field.blade.php` | Renders `@case('media_picker')` UI |
| Editor State | `visual-editor/resources/views/components/editor-state.blade.php` | Alpine store with `Livewire.on()` listener |
| Editor Layout | `visual-editor/resources/views/components/editor-layout.blade.php` | Renders bridge component conditionally |
| Editor Layout Test | `visual-editor/tests/Unit/Components/EditorLayoutTest.php` | Tests with media stubs |
| Test Stubs | `visual-editor/tests/Stubs/` | MediaModal stub for tests |

## Environment

- **Livewire 4** (not 3 — user confirmed)
- Laravel 12, PHP 8.4
- Alpine.js (bundled with Livewire)
- daisyUI + Tailwind CSS v4
- Package is symlinked from `~/Desktop/ArtisanPack UI Packages/visual-editor/`
- Dev app at `/Users/jacobmartella/Herd/artisanpack-ui/`
- Tests: `cd visual-editor && ./vendor/bin/pest`
- Code style: `cd visual-editor && ./vendor/bin/php-cs-fixer fix`
