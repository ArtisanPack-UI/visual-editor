/**
 * Legacy `artisanpack/navigation` stub edit (#808 follow-up) — the one-shot
 * migration to `core/navigation` must only count as done once the stub has
 * actually left the block tree, and a stub that cannot migrate (locked
 * parent, disabled editing mode) must say so instead of rendering an empty
 * div.
 *
 * @since 1.12.0
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { fireEvent, render } from '@testing-library/react';

interface FakeState {
    present: boolean;
    rootClientId: string;
    canInsert: boolean;
    editingMode: string;
    siblings: Array<{ clientId: string; name?: string }>;
}

const state: FakeState = {
    present: true,
    rootClientId: 'parent',
    canInsert: true,
    editingMode: 'default',
    siblings: [],
};

const replaceBlock = vi.fn();
const replaceInnerBlocks = vi.fn();

const selectors = {
    getBlocks: (id: string) => (id === 'nav' ? [] : state.siblings),
    getBlock: () => (state.present ? { clientId: 'nav' } : null),
    getBlockRootClientId: () => state.rootClientId,
    canInsertBlockType: () => state.canInsert,
    getBlockEditingMode: () => state.editingMode,
    getSettings: () => ({}),
};

const registry = {
    select: () => selectors,
    dispatch: () => ({ replaceBlock, replaceInnerBlocks }),
};

vi.mock('@wordpress/data', () => ({
    useRegistry: () => registry,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    useSelect: (mapSelect: any) => mapSelect(() => selectors),
}));

vi.mock('@wordpress/block-editor', () => ({
    store: 'core/block-editor',
    useBlockProps: () => ({ className: 'stub-wrapper' }),
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    Warning: ({ children, actions }: any) => (
        <div data-testid="warning">
            <p>{children}</p>
            {actions}
        </div>
    ),
}));

vi.mock('@wordpress/components', () => ({
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    Button: ({ children, onClick }: any) => <button onClick={onClick}>{children}</button>,
}));

vi.mock('@wordpress/blocks', () => ({
    createBlock: (name: string, attributes: Record<string, unknown>, innerBlocks: unknown[]) => ({
        clientId: 'migrated',
        name,
        attributes,
        innerBlocks,
    }),
}));

import Edit from '../edit';

const ATTRIBUTES = { ref: 7, align: 'wide', artisanpackAnimations: { entrance: { effect: 'fade-in' } } };

beforeEach(() => {
    replaceBlock.mockReset();
    replaceInnerBlocks.mockReset();
    Object.assign(state, {
        present: true,
        rootClientId: 'parent',
        canInsert: true,
        editingMode: 'default',
        siblings: [{ clientId: 'before' }, { clientId: 'nav' }, { clientId: 'after' }],
    });
});

describe('DeprecatedNavigationEdit', () => {
    it('swaps itself for core/navigation carrying every attribute', () => {
        replaceBlock.mockImplementation(() => {
            state.present = false;
        });

        const { queryByTestId } = render(<Edit clientId="nav" attributes={ATTRIBUTES} />);

        expect(replaceBlock).toHaveBeenCalledTimes(1);
        const [clientId, block] = replaceBlock.mock.calls[0];
        expect(clientId).toBe('nav');
        expect(block.name).toBe('core/navigation');
        expect(block.attributes).toEqual(ATTRIBUTES);
        expect(queryByTestId('warning')).toBeNull();
    });

    it('does not attempt a replacement the store would refuse, and shows a warning', () => {
        state.canInsert = false;

        const { getByTestId, getByRole } = render(<Edit clientId="nav" attributes={ATTRIBUTES} />);

        expect(replaceBlock).not.toHaveBeenCalled();
        expect(getByTestId('warning').textContent).toContain('legacy format');
        expect(getByRole('button', { name: 'Update block' })).not.toBeNull();
    });

    it('keeps the migration pending when replaceBlock silently no-ops, retrying once allowed', () => {
        // Store refuses: the stub stays in the tree.
        const { getByTestId, rerender } = render(<Edit clientId="nav" attributes={ATTRIBUTES} />);

        expect(replaceBlock).toHaveBeenCalledTimes(1);
        expect(getByTestId('warning')).not.toBeNull();

        // Lock state changes → the effect re-fires and this time succeeds.
        state.canInsert = false;
        rerender(<Edit clientId="nav" attributes={ATTRIBUTES} />);
        state.canInsert = true;
        replaceBlock.mockImplementation(() => {
            state.present = false;
        });
        rerender(<Edit clientId="nav" attributes={ATTRIBUTES} />);

        expect(replaceBlock).toHaveBeenCalledTimes(2);
    });

    it('"Update block" swaps the stub in place inside its parent', () => {
        state.canInsert = false;

        const { getByRole } = render(<Edit clientId="nav" attributes={ATTRIBUTES} />);
        fireEvent.click(getByRole('button', { name: 'Update block' }));

        expect(replaceInnerBlocks).toHaveBeenCalledTimes(1);
        const [rootClientId, blocks] = replaceInnerBlocks.mock.calls[0];
        expect(rootClientId).toBe('parent');
        expect(blocks.map((b: { clientId: string }) => b.clientId)).toEqual([
            'before',
            'migrated',
            'after',
        ]);
        expect(blocks[1].attributes).toEqual(ATTRIBUTES);
    });

    it('offers no action when the block itself is not editable', () => {
        state.canInsert = false;
        state.editingMode = 'disabled';

        const { getByTestId, queryByRole } = render(<Edit clientId="nav" attributes={ATTRIBUTES} />);

        expect(getByTestId('warning').textContent).toContain('legacy format');
        expect(queryByRole('button')).toBeNull();
    });

    it('offers no action for a locked top-level block', () => {
        state.canInsert = false;
        state.rootClientId = '';

        const { queryByRole } = render(<Edit clientId="nav" attributes={ATTRIBUTES} />);

        expect(queryByRole('button')).toBeNull();
    });
});
