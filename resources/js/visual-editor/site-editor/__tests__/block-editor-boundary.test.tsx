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

import { act, render, screen, within } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';

// Stub `BlockEditorProvider` as a labelled wrapper so the test can
// assert structure (how many providers, which children sit inside)
// without booting the real Gutenberg data store under jsdom. We also
// capture the latest `settings` prop so the Keystone #47 parity tests
// can assert what ends up in the canvas's `styles` array.
const LATEST_SETTINGS = { value: null as unknown };

vi.mock('@wordpress/block-editor', () => ({
    BlockEditorProvider: ({
        children,
        settings,
    }: {
        children?: ReactNode;
        settings?: unknown;
    }): JSX.Element => {
        LATEST_SETTINGS.value = settings;

        return <div data-testid="ap-stub-block-editor-provider">{children}</div>;
    },
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

// Keystone #47: the boundary fetches compiled theme CSS through this
// module when an apiBase is supplied. Stub it so the test stays
// deterministic and doesn't touch the network under jsdom.
vi.mock('../styles/global-styles-api', () => ({
    fetchGlobalStylesCss: vi.fn(async (): Promise<string> => ''),
    fetchGlobalStylesBase: vi.fn(async () => ({ settings: {}, styles: {} })),
}));

const CONVERT_TO_PATTERN_CONTROL_MOCK = vi.fn((): null => null);

vi.mock('../../editor/convert-to-pattern-control', () => ({
    ConvertToPatternControl: (): null => CONVERT_TO_PATTERN_CONTROL_MOCK(),
}));

import { BlockEditorBoundary } from '../block-editor-boundary';
import { DEFAULT_CANVAS_STYLES } from '../../editor-settings';
import { resetThemeGlobalStylesCssCache } from '../use-theme-global-styles-css';

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
        CONVERT_TO_PATTERN_CONTROL_MOCK.mockClear();

        render(
            <BlockEditorBoundary
                blocks={[]}
                onChange={() => undefined}
                onInput={() => undefined}
            >
                <div data-testid="canvas-slot" />
            </BlockEditorBoundary>
        );

        expect(CONVERT_TO_PATTERN_CONTROL_MOCK).not.toHaveBeenCalled();
    });

    it('passes only DEFAULT_CANVAS_STYLES to the provider when no theme CSS is available (Keystone #47)', () => {
        LATEST_SETTINGS.value = null;
        resetThemeGlobalStylesCssCache();

        render(
            <BlockEditorBoundary
                blocks={[]}
                onChange={() => undefined}
                onInput={() => undefined}
                themeGlobalStylesCss=""
            >
                <div data-testid="canvas-slot" />
            </BlockEditorBoundary>
        );

        const settings = LATEST_SETTINGS.value as { styles: { css: string }[] };
        expect(settings.styles).toHaveLength(1);
        expect(settings.styles[0]?.css).toBe(DEFAULT_CANVAS_STYLES);
    });

    it('appends the theme global-styles CSS after DEFAULT_CANVAS_STYLES so it wins on cascade (Keystone #47)', () => {
        LATEST_SETTINGS.value = null;
        resetThemeGlobalStylesCssCache();

        const themeCss = ':root { --wp--preset--color--primary: #0f172a; }';

        render(
            <BlockEditorBoundary
                blocks={[]}
                onChange={() => undefined}
                onInput={() => undefined}
                themeGlobalStylesCss={themeCss}
            >
                <div data-testid="canvas-slot" />
            </BlockEditorBoundary>
        );

        const settings = LATEST_SETTINGS.value as { styles: { css: string }[] };
        // Order matters — Gutenberg cascades the array in order, so the
        // theme CSS must come after the package's default baseline.
        expect(settings.styles).toHaveLength(2);
        expect(settings.styles[0]?.css).toBe(DEFAULT_CANVAS_STYLES);
        expect(settings.styles[1]?.css).toBe(themeCss);
    });

    it('mounts the convert-to-pattern control when an apiBase is given', async () => {
        CONVERT_TO_PATTERN_CONTROL_MOCK.mockClear();
        resetThemeGlobalStylesCssCache();

        // The boundary's theme-CSS hook fires a fetch when apiBase is
        // non-empty; flush the in-flight promise inside act() so the
        // setState that resolves it doesn't trigger the "update was
        // not wrapped in act" warning.
        await act(async () => {
            render(
                <BlockEditorBoundary
                    blocks={[]}
                    onChange={() => undefined}
                    onInput={() => undefined}
                    apiBase="/visual-editor/api"
                >
                    <div data-testid="canvas-slot" />
                </BlockEditorBoundary>
            );
        });

        expect(CONVERT_TO_PATTERN_CONTROL_MOCK).toHaveBeenCalled();
    });
});
