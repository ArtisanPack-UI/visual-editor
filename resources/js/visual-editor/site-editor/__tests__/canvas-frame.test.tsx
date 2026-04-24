import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import type { ReactNode } from 'react';

// Stub `BlockCanvas` and `BlockEditorProvider` from `@wordpress/block-editor`.
// The real `BlockCanvas` mounts an iframe and pulls from a Gutenberg data
// store, neither of which jsdom + the package's coreData shim produce
// reliably. Stubbing keeps the test focused on the wrapper's behaviour:
//   - render the canvas region;
//   - render the empty state when `hasEntity={false}`;
//   - swap to `children` when an entity is supplied.
vi.mock('@wordpress/block-editor', () => ({
    BlockCanvas: ({ children }: { children: ReactNode }): JSX.Element => (
        <div data-testid="ap-stub-block-canvas">{children}</div>
    ),
    BlockEditorProvider: ({ children }: { children: ReactNode }): JSX.Element => (
        <div data-testid="ap-stub-block-editor-provider">{children}</div>
    ),
}));

vi.mock('@wordpress/components', () => {
    const SlotFillProvider = ({ children }: { children: ReactNode }): JSX.Element => (
        <div data-testid="ap-stub-slotfill-provider">{children}</div>
    );

    function PopoverSlot(): null {
        return null;
    }

    const Popover = Object.assign(() => null, { Slot: PopoverSlot });

    return { SlotFillProvider, Popover };
});

import { CanvasFrame } from '../canvas-frame';

describe('CanvasFrame', () => {
    it('renders the canvas wrapper and the empty state when no entity is selected', () => {
        render(<CanvasFrame sectionLabel="Editing: Templates" />);

        const canvas = screen.getByTestId('ap-site-editor-canvas');

        expect(canvas).toBeInTheDocument();
        expect(canvas).toHaveAttribute('data-has-entity', 'false');

        const emptyState = screen.getByTestId('ap-site-editor-canvas-empty');

        expect(emptyState).toHaveTextContent('Editing: Templates');
        expect(emptyState).toHaveTextContent(
            'Select an entity from the navigator to start editing.'
        );
    });

    it('mounts the BlockCanvas and provider chain', () => {
        render(<CanvasFrame sectionLabel="Editing: Styles" />);

        expect(
            screen.getByTestId('ap-stub-block-editor-provider')
        ).toBeInTheDocument();
        expect(
            screen.getByTestId('ap-stub-block-canvas')
        ).toBeInTheDocument();
    });

    it('renders supplied children inside the canvas when hasEntity is true', () => {
        render(
            <CanvasFrame sectionLabel="Editing: Patterns" hasEntity>
                <div data-testid="ap-test-entity">My pattern</div>
            </CanvasFrame>
        );

        expect(screen.getByTestId('ap-test-entity')).toBeInTheDocument();
        expect(
            screen.queryByTestId('ap-site-editor-canvas-empty')
        ).not.toBeInTheDocument();
        expect(screen.getByTestId('ap-site-editor-canvas')).toHaveAttribute(
            'data-has-entity',
            'true'
        );
    });
});
