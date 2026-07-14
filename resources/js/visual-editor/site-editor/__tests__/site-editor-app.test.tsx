import { act, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// Stub the artisanpack block registration entrypoint: loading it under
// jsdom pulls in the full block catalog via Vite's import.meta.glob
// which fails outside a Vite transform. The shell tests don't exercise
// block rendering; they only care that the shell wires regions correctly.
vi.mock('../../blocks', () => ({
    registerArtisanPackBlocks: (): void => undefined,
}));

// #649 — same reason as the BlockEditorBoundary + BlockLibrarySidebar
// stubs below: the real background-controls barrel pulls
// `@wordpress/block-editor` (and its `block-compare` → `diff` subpath)
// into the graph, which doesn't resolve under jsdom. The shell test
// only checks that `ensureEditorBoot()` runs the registrars; the HOC
// itself has its own focused test suite.
vi.mock('../../background-controls', () => ({
    registerBackgroundControls: (): void => undefined,
}));

// #436: the shell now imports `BlockEditorBoundary` directly to wrap
// the D2 canvas + inspector in one `BlockEditorProvider`. The real
// boundary pulls `@wordpress/block-editor` (and its `block-compare` →
// `diff` subpath) into the graph, which doesn't resolve under jsdom.
// Stub it as a passthrough — the shell test only checks that the
// boundary wraps the body, not the provider internals.
vi.mock('../block-editor-boundary', () => ({
    BlockEditorBoundary: ({
        children,
    }: {
        children?: React.ReactNode;
    }): JSX.Element => (
        <div data-testid="ap-site-editor-stub-block-editor-boundary">
            {children}
        </div>
    ),
}));

// #439: same reason as the BlockEditorBoundary stub — BlockLibrarySidebar
// imports `__experimentalLibrary` / `__experimentalListView` from
// `@wordpress/block-editor`, which doesn't resolve under jsdom. Stub it
// out; the shell test verifies the toggle wiring, not the library UI.
vi.mock('../../editor/block-library-sidebar', () => ({
    BlockLibrarySidebar: (): JSX.Element => (
        <div data-testid="ap-site-editor-stub-block-library-sidebar" />
    ),
}));

// Stub the canvas frame to avoid mounting `BlockCanvas` (which needs a
// real iframe + the @wordpress/block-editor data store) under jsdom.
// The shell-level integration is what we're verifying here; the canvas
// has its own focused test.
vi.mock('../canvas-frame', () => ({
    CanvasFrame: ({
        sectionLabel,
        hasEntity,
    }: {
        sectionLabel: string;
        hasEntity?: boolean;
    }): JSX.Element => (
        <div
            data-testid="ap-site-editor-canvas"
            data-has-entity={hasEntity ?? false}
        >
            {sectionLabel}
        </div>
    ),
}));

// D2 mounts real browsers into the templates/parts sections; the shell
// tests only care that the navigator wires them through, so stub them
// with simple placeholders that opt out of the REST fetch chain.
vi.mock('../templates-section', () => ({
    TemplatesBrowser: (): JSX.Element => (
        <div data-testid="ap-site-editor-stub-templates-browser" />
    ),
    TemplateCreateDialog: (): null => null,
    TemplateDocumentPanel: (): null => null,
}));

vi.mock('../template-parts-section', () => ({
    TemplatePartsBrowser: (): JSX.Element => (
        <div data-testid="ap-site-editor-stub-parts-browser" />
    ),
    TemplatePartCreateDialog: (): null => null,
    TemplatePartDocumentPanel: (): null => null,
}));

vi.mock('../entity-editor', () => ({
    useEntityEditorViews: (): {
        canvas: JSX.Element;
        inspector: JSX.Element;
        editorBoundary: {
            blocks: readonly unknown[];
            onChange: () => void;
            onInput: () => void;
        } | null;
    } => ({
        canvas: <div data-testid="ap-site-editor-stub-entity-canvas" />,
        inspector: <div data-testid="ap-site-editor-stub-entity-inspector" />,
        // #436: a non-null boundary so the shell exercises its
        // wrap-in-`BlockEditorBoundary` branch. The boundary itself is
        // mocked as a passthrough above.
        editorBoundary: { blocks: [], onChange: () => undefined, onInput: () => undefined },
    }),
}));

// D3 mounts the styles section inside the shell; the shell tests only
// care that the navigator routes to it, not the global-styles fetch
// chain. The styles-section test file exercises the hook end-to-end.
//
// H7 (#432) — the shell now lazy-imports each section's default
// export, so the mock has to expose one alongside the hook. The stub
// component just renders the same three views inline (no portals);
// the tests check stub existence by `data-testid`, not DOM position.
vi.mock('../styles/styles-section', () => {
    const navigator = (
        <div data-testid="ap-site-editor-stub-styles-navigator" />
    );
    const canvas = <div data-testid="ap-site-editor-stub-styles-canvas" />;
    const inspector = (
        <div data-testid="ap-site-editor-stub-styles-inspector" />
    );

    return {
        useStylesSectionViews: (): {
            navigator: JSX.Element;
            canvas: JSX.Element;
            inspector: JSX.Element;
        } => ({ navigator, canvas, inspector }),
        default: (): JSX.Element => (
            <>
                {navigator}
                {canvas}
                {inspector}
            </>
        ),
    };
});

// D5 mounts the patterns section. Stub the hook entry point so the
// shell tests don't pull in the patterns canvas (and therefore
// `@wordpress/block-editor`) at module-load time. The patterns-section
// test file exercises the hook end-to-end.
vi.mock('../patterns/patterns-section', () => {
    const navigator = (
        <div data-testid="ap-site-editor-stub-patterns-navigator" />
    );
    const canvas = <div data-testid="ap-site-editor-stub-patterns-canvas" />;
    const inspector = (
        <div data-testid="ap-site-editor-stub-patterns-inspector" />
    );

    return {
        usePatternsSectionViews: (): {
            navigator: JSX.Element;
            canvas: JSX.Element;
            inspector: JSX.Element;
            overlay: null;
        } => ({ navigator, canvas, inspector, overlay: null }),
        default: (): JSX.Element => (
            <>
                {navigator}
                {canvas}
                {inspector}
            </>
        ),
    };
});

// Same reasoning for D4 — keep the navigation-section module out of
// the shell test's import graph so its API client doesn't try to
// fetch menu locations during the shell unit tests.
vi.mock('../navigation/navigation-section', () => {
    const navigator = (
        <div data-testid="ap-site-editor-stub-navigation-navigator" />
    );
    const canvas = <div data-testid="ap-site-editor-stub-navigation-canvas" />;
    const inspector = (
        <div data-testid="ap-site-editor-stub-navigation-inspector" />
    );

    return {
        useNavigationSectionViews: (): {
            navigator: JSX.Element;
            canvas: JSX.Element;
            inspector: JSX.Element;
            overlay: null;
        } => ({ navigator, canvas, inspector, overlay: null }),
        default: (): JSX.Element => (
            <>
                {navigator}
                {canvas}
                {inspector}
            </>
        ),
    };
});

vi.mock('../../editor/synced-pattern-indicator', () => ({
    registerSyncedPatternIndicator: () => undefined,
}));

// #490: the gradient-border registrars pull in `@wordpress/blocks`
// through the BlockEdit HOC, which trips the JSON-import-attribute
// requirement under jsdom. Same rationale as the `../../blocks`
// stub above — the shell tests don't exercise gradient picker UI.
vi.mock('../../gradient-borders/register', () => ({
    registerGradientBorders: (): void => undefined,
}));

// #607: same JSON-import-attribute trip for the box-shadow registrar.
vi.mock('../../box-shadows/register', () => ({
    registerBoxShadows: (): void => undefined,
}));

// #640: same JSON-import-attribute trip for the positioning registrar.
vi.mock('../../positioning/register', () => ({
    registerPositioning: (): void => undefined,
}));

import { resetActiveBreakpoint } from '../../responsive/active-breakpoint';
import { SiteEditorApp } from '../site-editor-app';

const ROUTE_BASE = '/visual-editor/site';

function setPath(pathname: string): void {
    window.history.replaceState(null, '', pathname);
}

beforeEach(() => {
    setPath(ROUTE_BASE);
    window.localStorage.clear();
});

afterEach(() => {
    setPath('/');
    // #617 — the viewport switcher writes to a module-level
    // active-breakpoint singleton. Without a reset, residue from the
    // viewport-preset test (or a mid-test early-return) leaks into
    // subsequent tests in the same worker and cross-contaminates
    // aria-pressed assertions.
    resetActiveBreakpoint();
});

function renderApp(): void {
    render(
        <SiteEditorApp
            routeBase={ROUTE_BASE}
            apiBase="/visual-editor/api"
            exitUrl="/editor"
            exitLabel="← Post editor"
        />
    );
}

describe('SiteEditorApp', () => {
    it('renders the four shell regions', () => {
        renderApp();

        expect(screen.getByTestId('ap-site-editor-shell')).toBeInTheDocument();
        expect(
            screen.getByTestId('ap-visual-editor-top-bar')
        ).toBeInTheDocument();
        expect(
            screen.getByTestId('ap-site-editor-navigator')
        ).toBeInTheDocument();
        expect(screen.getByTestId('ap-site-editor-canvas')).toBeInTheDocument();
        expect(
            screen.getByTestId('ap-site-editor-inspector')
        ).toBeInTheDocument();
    });

    it('lands on Templates by default and reflects it in the mode indicator', () => {
        renderApp();

        const indicator = screen.getByTestId('ap-site-editor-mode-indicator');

        expect(indicator).toHaveAttribute('data-section', 'templates');
        expect(indicator).toHaveTextContent('Editing: Templates');
        expect(screen.getByTestId('ap-site-editor-save')).toHaveTextContent(
            'Save template'
        );
    });

    it('switches sections when the user picks one in the navigator', async () => {
        const user = userEvent.setup();
        renderApp();

        await user.click(screen.getByTestId('ap-site-editor-navigator-styles'));

        expect(window.location.pathname).toBe(`${ROUTE_BASE}/styles`);
        expect(
            screen.getByTestId('ap-site-editor-mode-indicator')
        ).toHaveTextContent('Editing: Global styles');
        expect(screen.getByTestId('ap-site-editor-save')).toHaveTextContent(
            'Save global styles'
        );
    });

    it('reflects browser back/forward (popstate) in the active section', () => {
        renderApp();

        act(() => {
            window.history.replaceState(
                null,
                '',
                `${ROUTE_BASE}/navigation`
            );
            window.dispatchEvent(new PopStateEvent('popstate'));
        });

        expect(
            screen.getByTestId('ap-site-editor-mode-indicator')
        ).toHaveAttribute('data-section', 'navigation');
        expect(screen.getByTestId('ap-site-editor-save')).toHaveTextContent(
            'Save menu'
        );
    });

    it('toggles the navigator sidebar from the leading navigator button and persists the state', async () => {
        const user = userEvent.setup();
        renderApp();

        const toggle = screen.getByTestId('ap-site-editor-top-bar-navigator');

        expect(toggle).toHaveAttribute('aria-pressed', 'true');
        expect(toggle).toHaveAttribute('aria-label', 'Close navigator');
        expect(
            screen.getByTestId('ap-site-editor-navigator')
        ).toBeInTheDocument();

        await user.click(toggle);

        expect(toggle).toHaveAttribute('aria-pressed', 'false');
        expect(toggle).toHaveAttribute('aria-label', 'Open navigator');
        expect(
            screen.queryByTestId('ap-site-editor-navigator')
        ).not.toBeInTheDocument();
        expect(
            window.localStorage.getItem('ap-site-editor:navigator-open')
        ).toBe('false');
    });

    it('labels the top-bar "+" button as the block inserter (#439)', () => {
        renderApp();

        const inserterToggle = screen.getByTestId(
            'ap-visual-editor-top-bar-inserter'
        );

        // Pre-#439, the "+" button toggled the navigator and was labeled
        // "Close navigator". It now belongs to the inserter. The default
        // landing has no active entity so the button is disabled with a
        // hinting label — the "Open block inserter" state lives behind
        // an active D2 entity (separate test).
        expect(inserterToggle).toHaveAttribute(
            'aria-label',
            'Select a template or template part to insert blocks'
        );
        expect(inserterToggle).toBeDisabled();
    });

    it('preseeds the inserter as closed and the navigator as open (#439)', () => {
        renderApp();

        // The toggles persist independently. The default lands with the
        // navigator open and the inserter closed — even before any entity
        // is selected, the persisted state must be sane.
        expect(
            screen.getByTestId('ap-site-editor-top-bar-navigator')
        ).toHaveAttribute('aria-pressed', 'true');
        expect(
            screen.getByTestId('ap-visual-editor-top-bar-inserter')
        ).toHaveAttribute('aria-pressed', 'false');

        const shell = screen.getByTestId('ap-site-editor-shell');

        expect(shell).toHaveAttribute('data-navigator-open', 'true');
        expect(shell).toHaveAttribute('data-inserter-open', 'false');
    });

    it('toggles the inspector and re-labels its trigger for the site editor', async () => {
        const user = userEvent.setup();
        renderApp();

        const toggle = screen.getByTestId('ap-visual-editor-top-bar-inspector');

        expect(toggle).toHaveAttribute('aria-pressed', 'true');
        expect(toggle).toHaveAttribute('aria-label', 'Close inspector');

        await user.click(toggle);

        expect(toggle).toHaveAttribute('aria-pressed', 'false');
        expect(
            screen.queryByTestId('ap-site-editor-inspector')
        ).not.toBeInTheDocument();
    });

    it('renders the exit link with the supplied url and label', () => {
        renderApp();

        const exit = screen.getByTestId('ap-site-editor-exit-link');

        expect(exit).toHaveAttribute('href', '/editor');
        expect(exit).toHaveTextContent('← Post editor');
    });

    it('omits the exit link entirely when no exitUrl is supplied', () => {
        // #446: the exit link is optional — a standalone embed with
        // nowhere to go back to shouldn't be forced to invent a target.
        render(
            <SiteEditorApp routeBase={ROUTE_BASE} apiBase="/visual-editor/api" />
        );

        expect(
            screen.queryByTestId('ap-site-editor-exit-link')
        ).not.toBeInTheDocument();
    });

    it('falls back to a generic exit label when only exitUrl is given', () => {
        render(
            <SiteEditorApp
                routeBase={ROUTE_BASE}
                apiBase="/visual-editor/api"
                exitUrl="/somewhere"
            />
        );

        const exit = screen.getByTestId('ap-site-editor-exit-link');

        expect(exit).toHaveAttribute('href', '/somewhere');
        expect(exit).toHaveTextContent('← Back');
    });

    it('keeps the save button disabled until a per-section panel wires it', () => {
        renderApp();

        expect(screen.getByTestId('ap-site-editor-save')).toBeDisabled();
    });

    it('mounts the D2 templates browser inside the navigator', () => {
        renderApp();

        expect(
            screen.getByTestId('ap-site-editor-stub-templates-browser')
        ).toBeInTheDocument();
    });

    it('mounts the D5 patterns navigator when the section is selected', async () => {
        const user = userEvent.setup();
        renderApp();

        await user.click(screen.getByTestId('ap-site-editor-navigator-patterns'));

        expect(
            screen.getByTestId('ap-site-editor-stub-patterns-navigator')
        ).toBeInTheDocument();
    });

    it('updates document.title to identify the active scope', () => {
        renderApp();

        expect(document.title).toBe('Site Editor · Templates');
    });

    it('initializes from the URL when deep-linked into a section', () => {
        setPath(`${ROUTE_BASE}/patterns`);

        renderApp();

        const outlet = within(screen.getByTestId('ap-site-editor-navigator'));

        expect(outlet.getByTestId('ap-site-editor-navigator-patterns')).toHaveAttribute(
            'aria-selected',
            'true'
        );
        expect(
            screen.getByTestId('ap-site-editor-mode-indicator')
        ).toHaveAttribute('data-section', 'patterns');
    });

    /*
     * #617 — the shell's TopBar hosts the viewport switcher. Clicking
     * a preset should stamp `data-preview-width` on the site-editor
     * canvas container so templates/parts/patterns editing surfaces
     * (which all render into that container) resize.
     */
    it('#617: applies the viewport preset width to the site-editor canvas container', async () => {
        const user = userEvent.setup();
        renderApp();

        // Land in a section that renders the real canvas div (with
        // `data-preview-width`) rather than the stubbed `CanvasFrame`
        // empty state — Styles is a D3 section and takes the lazy
        // path immediately without needing an entity id.
        await user.click(screen.getByTestId('ap-site-editor-navigator-styles'));

        const canvas = screen.getByTestId('ap-site-editor-canvas');
        expect(canvas).toHaveAttribute('data-preview-width', 'base');

        // Pick "Mobile" — matches the shipped `sm` label so tests
        // don't need to reach into the registry to stay green.
        await user.click(screen.getByRole('button', { name: 'Mobile' }));

        expect(
            screen.getByTestId('ap-site-editor-canvas')
        ).toHaveAttribute('data-preview-width', '375');

        // Switching back to "All sizes" clears the preview width so
        // the canvas fills the shell area again.
        await user.click(screen.getByRole('button', { name: 'All sizes' }));

        expect(
            screen.getByTestId('ap-site-editor-canvas')
        ).toHaveAttribute('data-preview-width', 'base');
    });

    it('marks the body region as the tabpanel and labels it by the active tab', async () => {
        const user = userEvent.setup();
        renderApp();

        const panel = document.getElementById('ap-site-editor-section-outlet');

        expect(panel).not.toBeNull();
        expect(panel).toHaveAttribute('role', 'tabpanel');
        expect(panel).toHaveAttribute(
            'aria-labelledby',
            'ap-site-editor-tab-templates'
        );
        expect(panel).toHaveAttribute('tabindex', '0');

        await user.click(screen.getByTestId('ap-site-editor-navigator-styles'));

        expect(panel).toHaveAttribute(
            'aria-labelledby',
            'ap-site-editor-tab-styles'
        );
    });
});
