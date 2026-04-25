/**
 * Navigation canvas (native tree editor) UI tests.
 *
 * The drag-sort behavior itself isn't unit-testable in jsdom (dnd-kit
 * relies on real pointer events), so these tests cover the rest:
 * empty state, add/select/delete, nested rows, and the
 * confirm-delete two-step.
 */

import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';

import { NavigationCanvas } from '../navigation-canvas';
import { makeMenuItem, type MenuItem } from '../menu-tree';

afterEach(() => {
    vi.restoreAllMocks();
});

function renderCanvas(initial: MenuItem[]) {
    let tree = initial;
    let selected: string | null = null;
    const onSelect = vi.fn((id: string | null) => {
        selected = id;
    });
    const onTreeChange = vi.fn((next: readonly MenuItem[]) => {
        tree = [...next];
    });

    const utils = render(
        <NavigationCanvas
            tree={tree}
            selectedItemId={selected}
            onSelectItem={onSelect}
            onTreeChange={onTreeChange}
        />
    );

    return { ...utils, onSelect, onTreeChange };
}

describe('NavigationCanvas', () => {
    it('renders the empty state when the tree is empty', () => {
        renderCanvas([]);

        expect(
            screen.getByTestId('ap-nav-canvas')
        ).toHaveAttribute('data-empty', 'true');
        expect(screen.getByTestId('ap-nav-canvas-add')).toBeInTheDocument();
    });

    it('emits an onTreeChange call when the user adds an item from empty', () => {
        const { onTreeChange } = renderCanvas([]);

        fireEvent.click(screen.getByTestId('ap-nav-canvas-add'));

        expect(onTreeChange).toHaveBeenCalledOnce();
        const next = onTreeChange.mock.calls[0]?.[0] as readonly MenuItem[];
        expect(next).toHaveLength(1);
        expect(next[0].type).toBe('custom');
    });

    it('selects an item when its row is clicked', () => {
        const item = makeMenuItem({ autoLabel: 'Home', url: '/' });
        const { onSelect } = renderCanvas([item]);

        fireEvent.click(
            screen.getByTestId(`ap-nav-canvas-row-${item.localId}`)
        );

        expect(onSelect).toHaveBeenCalledWith(item.localId);
    });

    it('requires a confirm click before deleting', () => {
        const item = makeMenuItem({ autoLabel: 'Home', url: '/' });
        const { onTreeChange } = renderCanvas([item]);

        const deleteButton = screen.getByTestId(
            `ap-nav-canvas-delete-${item.localId}`
        );

        fireEvent.click(deleteButton);
        expect(onTreeChange).not.toHaveBeenCalled();
        expect(deleteButton).toHaveAttribute('data-confirming', 'true');

        fireEvent.click(deleteButton);
        expect(onTreeChange).toHaveBeenCalledOnce();
    });

    it('appends a child when "Add child" is clicked', () => {
        const parent = makeMenuItem({ autoLabel: 'Parent' });
        const { onTreeChange } = renderCanvas([parent]);

        fireEvent.click(
            screen.getByTestId(`ap-nav-canvas-add-child-${parent.localId}`)
        );

        const next = onTreeChange.mock.calls[0]?.[0] as readonly MenuItem[];
        expect(next[0].children).toHaveLength(1);
    });

    it('renders nested children under their parent row', () => {
        const child = makeMenuItem({ autoLabel: 'Child' });
        const parent = makeMenuItem({
            autoLabel: 'Parent',
            children: [child],
        });

        renderCanvas([parent]);

        expect(
            screen.getByTestId(`ap-nav-canvas-item-${child.localId}`)
        ).toBeInTheDocument();
    });
});
