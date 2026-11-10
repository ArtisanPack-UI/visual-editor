import { render } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';

// `PatternCanvas` is a pure presentational shell post-#436 — the
// `BlockEditorProvider` lives in `BlockEditorBoundary`, above the
// canvas. Stub the block-editor primitives so the canvas renders under
// jsdom without booting the real Gutenberg data store.
// #814 — the spacing-preset styles read the block-editor store, which the
// `@wordpress/block-editor` mock below doesn't provide.
vi.mock('../../../editor/spacing-preset-styles', () => ({
    SpacingPresetStyles: (): null => null,
}));

vi.mock('@wordpress/block-editor', () => ({
    __unstableEditorStyles: (): null => null,
    BlockList: (): JSX.Element => <div data-testid="ap-stub-block-list" />,
    BlockToolbar: (): JSX.Element => <div data-testid="ap-stub-block-toolbar" />,
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

// #1102 — the click target reads the block-editor store, which the
// `@wordpress/block-editor` mock above doesn't provide. It has its own suite.
vi.mock('../../../editor/canvas-end-click-target', () => ({
    CanvasEndClickTarget: ({ compact }: { compact?: boolean }): JSX.Element => (
        <div data-testid="ap-stub-canvas-end-click-target" data-compact={compact === true ? 'true' : 'false'} />
    ),
}));

import { CANVAS_LAYOUT_STYLES } from '../../../editor-settings';
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

    it('inlines the layout baseline the iframe gets from canvasStyles (#1040)', () => {
        render(<PatternCanvas title="Test pattern" synced={false} />);

        const surface = document.querySelector('.ap-pattern-canvas__surface');
        const sheets = Array.from(surface?.querySelectorAll('style') ?? []).map(
            (style) => style.textContent
        );

        expect(sheets).toContain(CANVAS_LAYOUT_STYLES);
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

    it('mounts the fixed block toolbar above the canvas body (#791)', () => {
        // The floating popover toolbar escaped the canvas viewport on
        // scroll (#791); the fix pairs `hasFixedToolbar: true` with a
        // <BlockToolbar/> docked into a fixed bar above the canvas body.
        // Removing or reordering that bar must fail this test, otherwise
        // the regression comes back silently — the setting-only guard
        // in use-themed-editor-settings.test.ts would still pass.
        render(<PatternCanvas title="Test pattern" synced={false} />);

        const bar = document.querySelector(
            '[data-testid="ap-pattern-canvas-block-toolbar"]',
        );
        const surface = document.querySelector('.ap-pattern-canvas__surface');

        expect(bar).not.toBeNull();
        expect(bar?.querySelector('[data-testid="ap-stub-block-toolbar"]'))
            .not.toBeNull();
        expect(surface).not.toBeNull();
        // Document order matters: the toolbar must precede the surface
        // so it sits at the top of the flex column and never below or
        // inside the scrolling canvas body.
        expect(
            bar!.compareDocumentPosition(surface!)
                & Node.DOCUMENT_POSITION_FOLLOWING,
        ).toBeTruthy();
    });
});
