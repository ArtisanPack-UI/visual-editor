/**
 * #799 — parity regression guard for the site-editor boot.
 *
 * The site editor previously reached first render without registering
 * the animations, visibility, bindings, dynamic-content, or dynamic-link
 * feature modules, so the inspector was missing Motion preferences,
 * Entrance/Hover/Continuous animation, Visibility, and Block bindings
 * for every selected block. This test asserts that `ensureEditorBoot()`
 * calls every registrar the post editor's `registerOnce()` calls, so a
 * future feature module cannot be silently omitted from one boot path.
 */

import { render } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// Same jsdom-graph stubs as the sibling `site-editor-app.test.tsx` —
// the real modules pull `@wordpress/block-editor` (and JSON-imported
// block manifests) into the resolver, which trips under jsdom.
vi.mock('../../blocks', () => ({
    registerArtisanPackBlocks: vi.fn(),
}));

vi.mock('../../background-controls', () => ({
    registerBackgroundControls: vi.fn(),
}));

vi.mock('../../formats/inline-icon/register', () => ({
    registerInlineIconFormat: vi.fn(),
}));

vi.mock('../block-editor-boundary', () => ({
    BlockEditorBoundary: ({
        children,
    }: {
        children?: React.ReactNode;
    }): JSX.Element => <div>{children}</div>,
}));

vi.mock('../../editor/block-library-sidebar', () => ({
    BlockLibrarySidebar: (): JSX.Element => <div />,
}));

vi.mock('../canvas-frame', () => ({
    CanvasFrame: (): JSX.Element => <div />,
}));

vi.mock('../templates-section', () => ({
    TemplatesBrowser: (): JSX.Element => <div />,
    TemplateCreateDialog: (): null => null,
    TemplateDocumentPanel: (): null => null,
}));

vi.mock('../template-parts-section', () => ({
    TemplatePartsBrowser: (): JSX.Element => <div />,
    TemplatePartCreateDialog: (): null => null,
    TemplatePartDocumentPanel: (): null => null,
}));

vi.mock('../entity-editor', () => ({
    useEntityEditorViews: (): {
        canvas: JSX.Element;
        inspector: JSX.Element;
        editorBoundary: null;
    } => ({
        canvas: <div />,
        inspector: <div />,
        editorBoundary: null,
    }),
}));

vi.mock('../styles/styles-section', () => ({
    default: (): JSX.Element => <div />,
}));

vi.mock('../patterns/patterns-section', () => ({
    default: (): JSX.Element => <div />,
}));

vi.mock('../navigation/navigation-section', () => ({
    default: (): JSX.Element => <div />,
}));

vi.mock('../../editor/synced-pattern-indicator', () => ({
    registerSyncedPatternIndicator: vi.fn(),
}));

vi.mock('../../gradient-borders/register', () => ({
    registerGradientBorders: vi.fn(),
}));

vi.mock('../../box-shadows/register', () => ({
    registerBoxShadows: vi.fn(),
}));

vi.mock('../../positioning/register', () => ({
    registerPositioning: vi.fn(),
}));

vi.mock('../../responsive/register-attribute', () => ({
    registerResponsiveAttribute: vi.fn(),
}));

vi.mock('../../responsive/with-responsive-attributes', () => ({
    registerResponsiveAttributesFilter: vi.fn(),
}));

vi.mock('../../states/register-attribute', () => ({
    registerStateAttribute: vi.fn(),
}));

vi.mock('../../states/with-state-attributes', () => ({
    registerStateAttributesFilter: vi.fn(),
}));

vi.mock('../../states/with-state-styles', () => ({
    registerStateStylesFilters: vi.fn(),
}));

vi.mock('../../animations/register-attribute', () => ({
    registerAnimationsAttribute: vi.fn(),
}));

vi.mock('../../animations/with-animations-panel', () => ({
    registerAnimationsPanel: vi.fn(),
}));

vi.mock('../../visibility/register-attribute', () => ({
    registerVisibilityAttribute: vi.fn(),
}));

vi.mock('../../visibility/with-visibility-panel', () => ({
    registerVisibilityPanel: vi.fn(),
}));

vi.mock('../../bindings/register-attribute', () => ({
    registerBindingsAttribute: vi.fn(),
}));

vi.mock('../../bindings/with-bindings-panel', () => ({
    registerBindingsPanel: vi.fn(),
}));

vi.mock('../../dynamic-content', () => ({
    registerDynamicContent: vi.fn(),
}));

vi.mock('../../formats/dynamic-link/register', () => ({
    registerDynamicLinkFormat: vi.fn(),
}));

vi.mock('../../editor/contrast-warning', () => ({
    disableContrastCheckerOnBlocks: vi.fn(),
    registerContrastWarning: vi.fn(),
}));

const ROUTE_BASE = '/visual-editor/site';

beforeEach(() => {
    window.history.replaceState(null, '', ROUTE_BASE);
    window.localStorage.clear();
    // The module-level `editorBooted` flag would short-circuit
    // `ensureEditorBoot()` on the second test render, hiding the
    // registrar calls from the spies. Reset the module graph so each
    // test observes a fresh boot.
    vi.resetModules();
});

afterEach(() => {
    window.history.replaceState(null, '', '/');
});

describe('site-editor boot parity (#799)', () => {
    it('registers every ArtisanPack default section during ensureEditorBoot()', async () => {
        const { SiteEditorApp } = await import('../site-editor-app');
        const animationsAttr = await import('../../animations/register-attribute');
        const animationsPanel = await import(
            '../../animations/with-animations-panel'
        );
        const visibilityAttr = await import('../../visibility/register-attribute');
        const visibilityPanel = await import(
            '../../visibility/with-visibility-panel'
        );
        const bindingsAttr = await import('../../bindings/register-attribute');
        const bindingsPanel = await import('../../bindings/with-bindings-panel');
        const dynamicContent = await import('../../dynamic-content');
        const dynamicLink = await import('../../formats/dynamic-link/register');
        const contrastWarning = await import('../../editor/contrast-warning');

        render(<SiteEditorApp routeBase={ROUTE_BASE} apiBase="/visual-editor/api" />);

        expect(animationsAttr.registerAnimationsAttribute).toHaveBeenCalledTimes(1);
        expect(animationsPanel.registerAnimationsPanel).toHaveBeenCalledTimes(1);
        expect(visibilityAttr.registerVisibilityAttribute).toHaveBeenCalledTimes(1);
        expect(visibilityPanel.registerVisibilityPanel).toHaveBeenCalledTimes(1);
        expect(bindingsAttr.registerBindingsAttribute).toHaveBeenCalledTimes(1);
        expect(bindingsPanel.registerBindingsPanel).toHaveBeenCalledTimes(1);
        expect(dynamicContent.registerDynamicContent).toHaveBeenCalledTimes(1);
        expect(dynamicLink.registerDynamicLinkFormat).toHaveBeenCalledTimes(1);
        expect(
            contrastWarning.disableContrastCheckerOnBlocks
        ).toHaveBeenCalledTimes(1);
        expect(contrastWarning.registerContrastWarning).toHaveBeenCalledTimes(1);
    });
});
