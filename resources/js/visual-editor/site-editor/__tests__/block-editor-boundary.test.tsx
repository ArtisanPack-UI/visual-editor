/**
 * #436 regression — `BlockEditorBoundary` must wrap *both* the canvas
 * and the inspector in a single `BlockEditorProvider` so they share one
 * `core/block-editor` registry. Before the fix each canvas mounted its
 * own provider and the inspector sat outside it, so block selection
 * never reached the inspector ("Click on a block to view its settings"
 * stuck on).
 *
 * The structural assertion here — exactly one provider, with both the
 * canvas slot and the inspector slot inside it — is the direct proof of
 * the fix. The provider internals are Gutenberg's; they're stubbed.
 */

import { render, screen, within } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';

// Stub `BlockEditorProvider` as a labelled wrapper so the test can
// assert structure (how many providers, which children sit inside)
// without booting the real Gutenberg data store under jsdom.
vi.mock('@wordpress/block-editor', () => ({
    BlockEditorProvider: ({ children }: { children?: ReactNode }): JSX.Element => (
        <div data-testid="ap-stub-block-editor-provider">{children}</div>
    ),
}));

vi.mock('@wordpress/components', () => {
    const SlotFillProvider = ({ children }: { children?: ReactNode }): JSX.Element => (
        <div>{children}</div>
    );

    function PopoverSlot(): null {
        return null;
    }

    const Popover = Object.assign(() => null, { Slot: PopoverSlot });

    return { SlotFillProvider, Popover };
});

vi.mock('@wordpress/format-library', () => ({}));

vi.mock('../../editor/convert-to-pattern-control', () => ({
    ConvertToPatternControl: (): null => null,
}));

import { BlockEditorBoundary } from '../block-editor-boundary';

describe('BlockEditorBoundary', () => {
    it('wraps the canvas and inspector slots in a single shared provider', () => {
        render(
            <BlockEditorBoundary
                blocks={[]}
                onChange={() => undefined}
                onInput={() => undefined}
            >
                <div data-testid="canvas-slot" />
                <div data-testid="inspector-slot" />
            </BlockEditorBoundary>
        );

        // Exactly one provider — not one-per-canvas as before #436.
        const providers = screen.getAllByTestId('ap-stub-block-editor-provider');
        expect(providers).toHaveLength(1);

        // Both slots live inside that single provider, so they resolve
        // the same `core/block-editor` registry.
        const provider = providers[0]!;
        expect(within(provider).getByTestId('canvas-slot')).toBeInTheDocument();
        expect(within(provider).getByTestId('inspector-slot')).toBeInTheDocument();
    });

    it('omits the convert-to-pattern control when no apiBase is given', () => {
        // ConvertToPatternControl is stubbed to null either way; this
        // just guards the `apiBase` branch from throwing when unset.
        expect(() =>
            render(
                <BlockEditorBoundary
                    blocks={[]}
                    onChange={() => undefined}
                    onInput={() => undefined}
                >
                    <div data-testid="canvas-slot" />
                </BlockEditorBoundary>
            )
        ).not.toThrow();
    });
});
