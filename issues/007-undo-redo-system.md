/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Area::Frontend" ~"Phase::1"

## Problem Statement

**Is your feature request related to a problem?**
Users need the ability to undo and redo changes during editing sessions to correct mistakes and experiment with different content arrangements.

## Proposed Solution

**What would you like to happen?**
Implement a client-side undo/redo system with history stack:

### History Manager

```javascript
class HistoryManager {
    constructor(maxStates = 50) {
        this.states = [];
        this.currentIndex = -1;
        this.maxStates = maxStates;
    }

    push(state) {
        // Remove any forward history
        this.states = this.states.slice(0, this.currentIndex + 1);
        this.states.push(structuredClone(state));

        // Limit history size
        if (this.states.length > this.maxStates) {
            this.states.shift();
        } else {
            this.currentIndex++;
        }
    }

    undo() {
        if (this.canUndo()) {
            this.currentIndex--;
            return this.states[this.currentIndex];
        }
        return null;
    }

    redo() {
        if (this.canRedo()) {
            this.currentIndex++;
            return this.states[this.currentIndex];
        }
        return null;
    }

    canUndo() { return this.currentIndex > 0; }
    canRedo() { return this.currentIndex < this.states.length - 1; }
}
```

### Tracked Operations

Record state on:
- Block added/removed
- Block content changed (debounced)
- Block moved/reordered
- Section added/removed
- Section reordered
- Settings changed
- Style changes

### UI Integration

- Undo button in toolbar (disabled when unavailable)
- Redo button in toolbar (disabled when unavailable)
- Keyboard shortcuts: Ctrl+Z (undo), Ctrl+Shift+Z (redo)
- History dropdown showing recent actions (optional)

### State Structure

```javascript
{
    timestamp: Date.now(),
    action: 'block_added',
    description: 'Added Heading block',
    sections: [...], // Full sections state
}
```

### Livewire Integration

```php
// Livewire component receives state from JS
public function restoreState(array $sections): void
{
    $this->sections = $sections;
    $this->dispatch('state-restored');
}
```

## Alternatives Considered

- Server-side history (rejected: too slow, too many requests)
- Revision-based undo (rejected: doesn't support fine-grained undo)
- Command pattern (rejected: overcomplicated for this use case)

## Use Cases

1. User accidentally deletes a block and undoes it
2. User experiments with layouts and reverts changes
3. User uses keyboard shortcuts for quick undo/redo
4. User sees undo/redo buttons disabled when unavailable

## Acceptance Criteria

- [ ] Undo reverts the last change
- [ ] Redo re-applies undone change
- [ ] Multiple undos work in sequence
- [ ] Redo stack clears on new change
- [ ] History limited to 50 states
- [ ] Ctrl+Z triggers undo
- [ ] Ctrl+Shift+Z triggers redo
- [ ] Buttons show disabled state appropriately
- [ ] Text changes are debounced for history

---

**Related Issues:**
- Depends on: Canvas, Block System
- Related: Revision history feature
