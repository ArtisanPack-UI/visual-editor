import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { KeyboardShortcutsModal } from '../keyboard-shortcuts-modal';

// jsdom ships `HTMLDialogElement` but `showModal()` / `close()` are not
// implemented in every version we run in CI. Stub just enough behaviour
// for @artisanpack-ui/react's Modal to toggle the `open` attribute.
function ensureDialogPolyfill(): void {
    if (typeof HTMLDialogElement === 'undefined') {
        return;
    }

    if (typeof HTMLDialogElement.prototype.showModal !== 'function') {
        HTMLDialogElement.prototype.showModal = function showModal(): void {
            this.setAttribute('open', '');
        };
    }

    if (typeof HTMLDialogElement.prototype.close !== 'function') {
        HTMLDialogElement.prototype.close = function close(): void {
            this.removeAttribute('open');
            this.dispatchEvent(new Event('close'));
        };
    }
}

ensureDialogPolyfill();

afterEach(() => {
    vi.restoreAllMocks();
});

describe('KeyboardShortcutsModal', () => {
    it('renders the shortcut list when open', () => {
        render(<KeyboardShortcutsModal open={true} onClose={vi.fn()} />);

        const modal = screen.getByTestId(
            'ap-visual-editor-keyboard-shortcuts-modal'
        );

        expect(modal).toBeInTheDocument();
        expect(modal).toHaveTextContent('Save the current draft.');
        expect(modal).toHaveTextContent('Undo the last block edit.');
        expect(modal).toHaveTextContent('Redo the last undone edit.');
    });

    it('does not display shortcut content when closed', () => {
        render(<KeyboardShortcutsModal open={false} onClose={vi.fn()} />);

        const dialog = screen.queryByTestId(
            'ap-visual-editor-keyboard-shortcuts-modal'
        );

        // When `open` is false the Modal's <dialog> has no `open` attribute,
        // so its contents are hidden even if still in the DOM tree.
        expect(dialog).not.toHaveAttribute('open');
    });

    it('fires onClose when the action button is activated', async () => {
        const onClose = vi.fn();
        const user = userEvent.setup();

        render(<KeyboardShortcutsModal open={true} onClose={onClose} />);

        await user.click(screen.getByRole('button', { name: 'Got it' }));

        expect(onClose).toHaveBeenCalledTimes(1);
    });
});
