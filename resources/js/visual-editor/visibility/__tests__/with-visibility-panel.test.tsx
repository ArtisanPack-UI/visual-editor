/**
 * Tests for the `editor.BlockEdit` HOC that mounts the VisibilityPanel.
 *
 * The HOC gates on the `artisanpackVisibility` attribute. Blocks
 * hydrated from an entity (e.g. `core/navigation-link` items loaded
 * from a `wp_navigation` menu by the core-data shim) carry their saved
 * attributes verbatim, so the key is absent on the instance even
 * though the block type registers it. The panel must still mount for
 * those blocks.
 */

import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';

const blockTypes: Record<string, { attributes: Record<string, unknown> }> = {
    'core/navigation-link': {
        attributes: {
            label: { type: 'string' },
            artisanpackVisibility: { type: 'object', default: null },
        },
    },
    'acme/opted-out': {
        attributes: {
            label: { type: 'string' },
        },
    },
};

vi.mock('@wordpress/blocks', () => ({
    getBlockType: (name: string) => blockTypes[name],
}));

vi.mock('@wordpress/block-editor', () => ({
    InspectorControls: ({ children }: { children?: ReactNode }) => (
        <div data-testid="inspector-controls">{children}</div>
    ),
    store: 'core/block-editor',
}));

const blockTree: Record<string, { name: string; order: string[] }> = {
    group: { name: 'artisanpack/group', order: ['related'] },
    related: { name: 'artisanpack/related-posts', order: [] },
};

const getBlockOrder = vi.fn((clientId: string) => blockTree[clientId]?.order ?? []);

vi.mock('@wordpress/data', () => {
    const store = {
        getBlockName: (clientId: string) => blockTree[clientId]?.name ?? null,
        getBlockOrder: (clientId: string) => getBlockOrder(clientId),
    };

    return {
        useSelect: (mapSelect: (select: () => typeof store) => unknown) => mapSelect(() => store),
    };
});

vi.mock('../VisibilityPanel', () => ({
    VisibilityPanel: ({ subtreeBlockNames }: { subtreeBlockNames: string[] }) => (
        <div data-testid="visibility-panel">{subtreeBlockNames.join(',')}</div>
    ),
}));

vi.mock('../user-search', () => ({
    searchUsers: vi.fn(),
}));

import { withVisibilityPanel } from '../with-visibility-panel';

const BlockEdit = () => <div data-testid="block-edit" />;
const Wrapped = withVisibilityPanel(BlockEdit);

function renderBlock(name: string, attributes: Record<string, unknown>, clientId?: string): void {
    render(<Wrapped name={name} clientId={clientId} attributes={attributes} setAttributes={() => undefined} />);
}

describe('withVisibilityPanel', () => {
    it('mounts the panel when the instance carries the attribute', () => {
        renderBlock('core/navigation-link', { label: 'Home', artisanpackVisibility: null });

        expect(screen.getByTestId('block-edit')).toBeTruthy();
        expect(screen.getByTestId('visibility-panel')).toBeTruthy();
    });

    it('mounts the panel for an entity-hydrated block whose instance lacks the default key', () => {
        renderBlock('core/navigation-link', { label: 'Home' });

        expect(screen.getByTestId('visibility-panel')).toBeTruthy();
    });

    it('does not mount the panel for a block type without the attribute', () => {
        renderBlock('acme/opted-out', { label: 'Nope' });

        expect(screen.getByTestId('block-edit')).toBeTruthy();
        expect(screen.queryByTestId('visibility-panel')).toBeNull();
    });

    it('does not mount the panel for an unregistered block type', () => {
        renderBlock('acme/unregistered', {});

        expect(screen.queryByTestId('visibility-panel')).toBeNull();
    });

    it('hands the panel the subtree when the block has a content rule', () => {
        renderBlock(
            'core/navigation-link',
            { artisanpackVisibility: { content: { source: 'relatedPosts', condition: 'hasResults' } } },
            'group',
        );

        expect(screen.getByTestId('visibility-panel').textContent)
            .toBe('artisanpack/group,artisanpack/related-posts');
    });

    it('skips the subtree walk without a content rule', () => {
        getBlockOrder.mockClear();

        renderBlock('core/navigation-link', { artisanpackVisibility: null }, 'group');

        expect(getBlockOrder).not.toHaveBeenCalled();
        expect(screen.getByTestId('visibility-panel').textContent).toBe('');
    });
});
