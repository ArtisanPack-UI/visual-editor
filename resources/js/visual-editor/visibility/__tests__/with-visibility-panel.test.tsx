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
}));

vi.mock('../VisibilityPanel', () => ({
    VisibilityPanel: () => <div data-testid="visibility-panel" />,
}));

vi.mock('../user-search', () => ({
    searchUsers: vi.fn(),
}));

import { withVisibilityPanel } from '../with-visibility-panel';

const BlockEdit = () => <div data-testid="block-edit" />;
const Wrapped = withVisibilityPanel(BlockEdit);

function renderBlock(name: string, attributes: Record<string, unknown>): void {
    render(<Wrapped name={name} attributes={attributes} setAttributes={() => undefined} />);
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
});
