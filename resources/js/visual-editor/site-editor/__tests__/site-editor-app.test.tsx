import { act, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// Stub the Gutenberg block-library registration pathway: loading it
// under jsdom pulls in the full block catalog which fails on strict
// JSON-attribute ESM rules. The shell tests don't exercise block
// rendering; they only care that the shell wires regions correctly.
vi.mock('@wordpress/blocks', () => ({
    getBlockType: (): undefined => undefined,
    getBlockTypes: (): never[] => [],
    unregisterBlockType: (): void => undefined,
}));

vi.mock('@wordpress/block-library', () => ({
    registerCoreBlocks: (): void => undefined,
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
    useEntityEditorViews: (): { canvas: JSX.Element; inspector: JSX.Element } => ({
        canvas: <div data-testid="ap-site-editor-stub-entity-canvas" />,
        inspector: <div data-testid="ap-site-editor-stub-entity-inspector" />,
    }),
}));

// D3 mounts the styles section inside the shell; the shell tests only
// care that the navigator routes to it, not the global-styles fetch
// chain. The styles-section test file exercises the hook end-to-end.
vi.mock('../styles/styles-section', () => ({
    useStylesSectionViews: (): {
        navigator: JSX.Element;
        canvas: JSX.Element;
        inspector: JSX.Element;
    } => ({
        navigator: <div data-testid="ap-site-editor-stub-styles-navigator" />,
        canvas: <div data-testid="ap-site-editor-stub-styles-canvas" />,
        inspector: <div data-testid="ap-site-editor-stub-styles-inspector" />,
    }),
}));

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
});

function renderApp(): void {
    render(
        <SiteEditorApp
            routeBase={ROUTE_BASE}
            postEditorUrl="/editor"
            apiBase="/visual-editor/api"
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

    it('toggles the navigator sidebar from the top bar and persists the state', async () => {
        const user = userEvent.setup();
        renderApp();

        const toggle = screen.getByTestId('ap-visual-editor-top-bar-inserter');

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

    it('renders a back-to-post-editor link pointing at the post editor URL', () => {
        renderApp();

        const back = screen.getByTestId('ap-site-editor-back-to-post-editor');

        expect(back).toHaveAttribute('href', '/editor');
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

    it('falls back to the section-outlet placeholder for phase-not-yet sections', async () => {
        const user = userEvent.setup();
        renderApp();

        await user.click(screen.getByTestId('ap-site-editor-navigator-patterns'));

        const outlet = screen.getByTestId(
            'ap-site-editor-section-outlet-patterns'
        );

        expect(outlet).toBeInTheDocument();
        expect(outlet).toHaveTextContent(/Patterns UI lands in D5\./);
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
