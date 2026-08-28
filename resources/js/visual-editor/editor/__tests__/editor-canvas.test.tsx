/**
 * `EditorCanvas` mounts the post title above the iframed `BlockCanvas`
 * (#347). The real `BlockCanvas` mounts an iframe and pulls from a
 * Gutenberg data store — neither of which jsdom reproduces reliably —
 * so `@wordpress/block-editor` is stubbed here (the same approach
 * `site-editor/__tests__/canvas-frame.test.tsx` takes). The stubs let
 * the suite focus on this component's wiring:
 *   - the canvas renders inside a `BlockCanvas` (iframe mount intent);
 *   - `BlockCanvas` receives the assembled `canvasStyles` bundle (style
 *     injection into the iframe);
 *   - `PostTitle` renders above `BlockCanvas`, and only when supported;
 *   - cms-framework entities get a `BlockContextProvider` wrap.
 */

import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { ReactNode } from 'react';

const blockCanvasProps = vi.fn();

vi.mock('@wordpress/block-editor', () => ({
    BlockCanvas: (props: { children: ReactNode; styles: unknown; height: string }): JSX.Element => {
        blockCanvasProps(props);

        return <div data-testid="ap-stub-block-canvas">{props.children}</div>;
    },
    BlockContextProvider: ({
        children,
        value,
    }: {
        children: ReactNode;
        value: unknown;
    }): JSX.Element => (
        <div
            data-testid="ap-stub-block-context-provider"
            data-context={JSON.stringify(value)}
        >
            {children}
        </div>
    ),
    BlockList: (): JSX.Element => <div data-testid="ap-stub-block-list" />,
    ObserveTyping: ({ children }: { children: ReactNode }): JSX.Element => (
        <div data-testid="ap-stub-observe-typing">{children}</div>
    ),
}));

/*
 * #695 — the canvas derives `--ap-editor-canvas-bg` / `-fg` from the
 * resolved theme.json. Stub the global-styles settings hook so the
 * tests can drive the payload without hitting the network; it defaults
 * to the empty payload the real hook returns for an undefined `apiBase`.
 */
let mockThemeGlobalStyles: {
    settings: Record<string, unknown>;
    styles: Record<string, unknown>;
    mergedStyles?: Record<string, unknown>;
} = { settings: {}, styles: {} };

let mockThemeGlobalStylesCss: string | undefined;

vi.mock('../../site-editor/use-theme-global-styles-settings', () => ({
    useThemeGlobalStylesSettings: (): typeof mockThemeGlobalStyles =>
        mockThemeGlobalStyles,
}));

vi.mock('../../site-editor/use-theme-global-styles-css', () => ({
    useThemeGlobalStylesCss: (): string | undefined => mockThemeGlobalStylesCss,
}));

vi.mock('../post-title', () => ({
    PostTitle: ({
        value,
        onChange,
    }: {
        value: string;
        onChange: (next: string) => void;
    }): JSX.Element => (
        <input
            data-testid="ap-stub-post-title"
            value={value}
            onChange={(event) => onChange(event.target.value)}
        />
    ),
}));

import { canvasStyles } from '../canvas-styles';
import { EditorCanvas } from '../editor-canvas';

describe('EditorCanvas', () => {
    it('renders the block list inside a BlockCanvas iframe', () => {
        render(
            <EditorCanvas
                showTitle
                title="Hello"
                onTitleChange={() => undefined}
                blockContext={null}
            />
        );

        const canvas = screen.getByTestId('ap-stub-block-canvas');

        expect(canvas).toBeInTheDocument();
        expect(canvas).toContainElement(
            screen.getByTestId('ap-stub-block-list')
        );
    });

    it('hands the assembled canvasStyles bundle to BlockCanvas for iframe injection', () => {
        blockCanvasProps.mockClear();

        render(
            <EditorCanvas
                showTitle
                title=""
                onTitleChange={() => undefined}
                blockContext={null}
            />
        );

        expect(blockCanvasProps).toHaveBeenCalledTimes(1);
        expect(blockCanvasProps.mock.calls[0]?.[0]).toMatchObject({
            styles: canvasStyles,
            height: '100%',
        });
    });

    it('renders PostTitle above the BlockCanvas when the document type supports a title', () => {
        render(
            <EditorCanvas
                showTitle
                title="My post"
                onTitleChange={() => undefined}
                blockContext={null}
            />
        );

        const title = screen.getByTestId('ap-stub-post-title');
        const canvas = screen.getByTestId('ap-stub-block-canvas');

        expect(title).toHaveValue('My post');
        // Source order: the title precedes the canvas in the DOM.
        expect(
            title.compareDocumentPosition(canvas) &
                Node.DOCUMENT_POSITION_FOLLOWING
        ).toBeTruthy();
    });

    it('omits PostTitle when the document type has no title support', () => {
        render(
            <EditorCanvas
                showTitle={false}
                title=""
                onTitleChange={() => undefined}
                blockContext={null}
            />
        );

        expect(
            screen.queryByTestId('ap-stub-post-title')
        ).not.toBeInTheDocument();
    });

    it('wraps the block list in a BlockContextProvider for cms-framework entities', () => {
        render(
            <EditorCanvas
                showTitle
                title=""
                onTitleChange={() => undefined}
                blockContext={{ postType: 'page', postId: 42 }}
            />
        );

        const provider = screen.getByTestId('ap-stub-block-context-provider');

        expect(provider).toHaveAttribute(
            'data-context',
            JSON.stringify({ postType: 'page', postId: 42 })
        );
        expect(provider).toContainElement(
            screen.getByTestId('ap-stub-block-list')
        );
    });

    it('skips the BlockContextProvider wrap when there is no entity context', () => {
        render(
            <EditorCanvas
                showTitle
                title=""
                onTitleChange={() => undefined}
                blockContext={null}
            />
        );

        expect(
            screen.queryByTestId('ap-stub-block-context-provider')
        ).not.toBeInTheDocument();
        expect(screen.getByTestId('ap-stub-block-list')).toBeInTheDocument();
    });

    /*
     * #617 — the post editor stamps a preview width onto the canvas
     * frame when the viewport switcher selects a device preset. These
     * tests confirm the frame reflects it via inline max-width +
     * data-attribute so both CSS and the RTL wiring test can react.
     */
    describe('#617 preview width prop', () => {
        it('marks the frame as base when no preview width is provided', () => {
            render(
                <EditorCanvas
                    showTitle
                    title=""
                    onTitleChange={() => undefined}
                    blockContext={null}
                />
            );

            const frame = screen.getByTestId('ap-visual-editor-canvas-frame');

            expect(frame).toHaveAttribute('data-preview-width', 'base');
            expect(frame.style.width).toBe('');
        });

        it('applies an inline width and data attribute when a preview width is set', () => {
            render(
                <EditorCanvas
                    showTitle
                    title=""
                    onTitleChange={() => undefined}
                    blockContext={null}
                    previewWidthPx={375}
                />
            );

            const frame = screen.getByTestId('ap-visual-editor-canvas-frame');

            // #617 — inline `width` (not `max-width`) so wide presets
            // scroll horizontally on the parent instead of clamping.
            expect(frame).toHaveAttribute('data-preview-width', '375');
            expect(frame.style.width).toBe('375px');
            expect(frame.style.flexShrink).toBe('0');
        });

        it('treats null / non-positive previewWidthPx as base', () => {
            const { rerender } = render(
                <EditorCanvas
                    showTitle
                    title=""
                    onTitleChange={() => undefined}
                    blockContext={null}
                    previewWidthPx={null}
                />
            );

            expect(
                screen.getByTestId('ap-visual-editor-canvas-frame')
            ).toHaveAttribute('data-preview-width', 'base');

            rerender(
                <EditorCanvas
                    showTitle
                    title=""
                    onTitleChange={() => undefined}
                    blockContext={null}
                    previewWidthPx={0}
                />
            );

            const frame = screen.getByTestId('ap-visual-editor-canvas-frame');
            expect(frame).toHaveAttribute('data-preview-width', 'base');
            expect(frame.style.width).toBe('');
        });
    });

    describe('composed-view ribbon (#623)', () => {
        it('is absent in bare-content mode', () => {
            render(
                <EditorCanvas
                    showTitle
                    title=""
                    onTitleChange={() => undefined}
                    blockContext={null}
                    chrome={null}
                />
            );

            expect(
                screen.queryByTestId('ap-composed-view-ribbon')
            ).not.toBeInTheDocument();
        });

        it('mounts inside the canvas iframe when chrome is supplied', () => {
            render(
                <EditorCanvas
                    showTitle
                    title=""
                    onTitleChange={() => undefined}
                    blockContext={null}
                    chrome={{
                        header: [],
                        footer: [],
                        templateName: 'Single Post',
                        templateSlug: 'single',
                    }}
                />
            );

            const ribbon = screen.getByTestId('ap-composed-view-ribbon');

            // Inside the `BlockCanvas` stub, not beside it — the ribbon
            // has to scroll with the composed preview rather than sit in
            // the editor chrome above the frame.
            expect(
                screen.getByTestId('ap-stub-block-canvas')
            ).toContainElement(ribbon);
            expect(
                screen.getByTestId('ap-composed-view-ribbon-cta')
            ).toHaveAttribute(
                'href',
                '/visual-editor/site?entity=template&slug=single'
            );
        });

        it('points the CTA at a host-supplied site-editor mount', () => {
            // The ribbon has always accepted this prop; nothing passed it,
            // so a host that mounts the site editor elsewhere got a CTA
            // pointing at a path that does not exist for them.
            render(
                <EditorCanvas
                    showTitle
                    title=""
                    onTitleChange={() => undefined}
                    blockContext={null}
                    siteEditorRouteBase="/admin/site-editor"
                    chrome={{
                        header: [],
                        footer: [],
                        templateName: 'Single Post',
                        templateSlug: 'single',
                    }}
                />
            );

            expect(
                screen.getByTestId('ap-composed-view-ribbon-cta')
            ).toHaveAttribute(
                'href',
                '/admin/site-editor?entity=template&slug=single'
            );
        });

        it('drops the CTA when composing against the fallback template', () => {
            render(
                <EditorCanvas
                    showTitle
                    title=""
                    onTitleChange={() => undefined}
                    blockContext={null}
                    chrome={{
                        header: [],
                        footer: [],
                        templateName: 'Default template',
                        templateSlug: null,
                    }}
                />
            );

            expect(
                screen.getByTestId('ap-composed-view-ribbon')
            ).toBeInTheDocument();
            expect(
                screen.queryByTestId('ap-composed-view-ribbon-cta')
            ).not.toBeInTheDocument();
        });
    });

    /*
     * #695 — the iframe body is painted through `--ap-editor-canvas-bg`
     * / `--ap-editor-canvas-fg`. Nothing supplied them, so a dark
     * theme.json rendered a white canvas with invisible white headings.
     * These cover the wiring: the tokens ride along in the styles array
     * for a themed canvas, and stay absent otherwise.
     */
    describe('#695 canvas color tokens', () => {
        afterEach(() => {
            mockThemeGlobalStyles = { settings: {}, styles: {} };
        });

        function lastStyles(): { css: string }[] {
            return blockCanvasProps.mock.calls.at(-1)?.[0].styles as {
                css: string;
            }[];
        }

        function renderCanvas(): void {
            blockCanvasProps.mockClear();

            render(
                <EditorCanvas
                    showTitle={false}
                    title=""
                    onTitleChange={() => undefined}
                    blockContext={null}
                    apiBase="/visual-editor/api"
                />
            );
        }

        it('appends the theme.json canvas colors as a :root entry', () => {
            mockThemeGlobalStyles = {
                settings: {},
                styles: { color: { background: '#111827', text: '#FFFFFF' } },
            };

            renderCanvas();

            const styles = lastStyles();
            const tokens = styles.at(-1);

            // Appended after the base bundle so it wins over the
            // package baseline, and scoped to `:root` so a host rule on
            // `body` / `.editor-styles-wrapper` still out-specifies it.
            expect(styles.length).toBe(canvasStyles.length + 1);
            expect(tokens?.css).toContain(':root {');
            expect(tokens?.css).toContain('--ap-editor-canvas-bg: #111827;');
            expect(tokens?.css).toContain('--ap-editor-canvas-fg: #FFFFFF;');
        });

        it('derives the canvas colors from the merged styles so a site-editor palette override wins (#M1)', () => {
            mockThemeGlobalStyles = {
                settings: {},
                // Theme default is light…
                styles: { color: { background: '#FFFFFF', text: '#111827' } },
                // …but the user's site-editor palette override is dark.
                mergedStyles: { color: { background: '#0B1220', text: '#F8FAFC' } },
            };

            renderCanvas();

            const tokens = lastStyles().at(-1);

            expect(tokens?.css).toContain('--ap-editor-canvas-bg: #0B1220;');
            expect(tokens?.css).toContain('--ap-editor-canvas-fg: #F8FAFC;');
            expect(tokens?.css).not.toContain('#FFFFFF');
        });

        it('leaves the styles bundle untouched when the theme declares no colors', () => {
            mockThemeGlobalStyles = { settings: {}, styles: {} };

            renderCanvas();

            // Identity, not just equality — nothing appended, so the
            // `var()` fallbacks in `canvas-theme-tokens.css` still paint
            // the canvas white with dark text.
            expect(lastStyles()).toBe(canvasStyles);
        });

        it('emits no token entry for a theme whose colors are unusable', () => {
            mockThemeGlobalStyles = {
                settings: {},
                styles: { color: { background: '', text: null } },
            };

            renderCanvas();

            expect(lastStyles()).toBe(canvasStyles);
        });
    });

    /*
     * #700 — the emitter compiles a theme's applied top-level styles
     * (including the site-editor-customized font-family) onto `:root`,
     * which in this iframe only inherits down and loses to the canvas's
     * own direct `.editor-styles-wrapper` defaults. The canvas rewrites
     * `:root` → `.editor-styles-wrapper` before injection so the applied
     * styles win on source order — matching the site editor.
     */
    describe('#700 applied-style scoping', () => {
        afterEach(() => {
            mockThemeGlobalStyles = { settings: {}, styles: {} };
            mockThemeGlobalStylesCss = '';
        });

        function lastStyles(): { css: string }[] {
            return blockCanvasProps.mock.calls.at(-1)?.[0].styles as {
                css: string;
            }[];
        }

        function renderCanvas(): void {
            blockCanvasProps.mockClear();

            render(
                <EditorCanvas
                    showTitle={false}
                    title=""
                    onTitleChange={() => undefined}
                    blockContext={null}
                    apiBase="/visual-editor/api"
                />
            );
        }

        it('rewrites the compiled theme CSS :root to the canvas scope', () => {
            mockThemeGlobalStylesCss =
                ':root {\n\tfont-family: var(--wp--preset--font-family--aboreto);\n}';

            renderCanvas();

            const themeEntry = lastStyles().at(-1);

            // The applied font-family now sits on `.editor-styles-wrapper`
            // — the same element as the package default — appended last, so
            // it wins. The unscoped `:root` selector must be gone.
            expect(themeEntry?.css).toContain(
                '.editor-styles-wrapper {\n\tfont-family: var(--wp--preset--font-family--aboreto);\n}'
            );
            expect(themeEntry?.css).not.toContain(':root {');
        });

        it('leaves the styles bundle untouched when no theme CSS is present', () => {
            mockThemeGlobalStylesCss = '';

            renderCanvas();

            expect(lastStyles()).toBe(canvasStyles);
        });
    });
});
