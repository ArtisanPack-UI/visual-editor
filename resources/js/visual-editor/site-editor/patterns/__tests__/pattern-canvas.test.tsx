import { render } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';

// `PatternCanvas` is a pure presentational shell post-#436 — the
// `BlockEditorProvider` lives in `BlockEditorBoundary`, above the
// canvas. Stub the block-editor primitives so the canvas renders under
// jsdom without booting the real Gutenberg data store.
vi.mock('@wordpress/block-editor', () => ({
    BlockList: (): JSX.Element => <div data-testid="ap-stub-block-list" />,
    BlockTools: ({ children }: { children?: ReactNode }): JSX.Element => (
        <div>{children}</div>
    ),
    ObserveTyping: ({ children }: { children?: ReactNode }): JSX.Element => (
        <div>{children}</div>
    ),
    WritingFlow: ({ children }: { children?: ReactNode }): JSX.Element => (
        <div>{children}</div>
    ),
}));

import { PatternCanvas } from '../pattern-canvas';

describe('PatternCanvas', () => {
    it('inlines the default canvas stylesheet into the canvas surface (#418)', () => {
        render(<PatternCanvas title="Test pattern" synced={false} />);

        // Gutenberg's `settings.styles` only reaches an iframed canvas;
        // this editor renders in-tree, so the stylesheet must be inlined
        // into the `editor-styles-wrapper` surface — otherwise the
        // canvas falls back to browser-default serif (#418).
        const surface = document.querySelector('.ap-pattern-canvas__surface');
        const style = surface?.querySelector('style');

        expect(style).not.toBeNull();
        expect(style?.textContent).toContain('.editor-styles-wrapper');
    });

    it('renders the loading state without the canvas surface', () => {
        render(<PatternCanvas title="Test pattern" synced={false} isLoading />);

        expect(
            document.querySelector('[data-testid="ap-pattern-canvas-loading"]')
        ).not.toBeNull();
        expect(
            document.querySelector('.ap-pattern-canvas__surface')
        ).toBeNull();
    });
});
