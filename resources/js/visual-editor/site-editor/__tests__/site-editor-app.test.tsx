import { act, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

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

    it('mounts the section outlet placeholder inside the navigator', () => {
        renderApp();

        const outlet = screen.getByTestId(
            'ap-site-editor-section-outlet-templates'
        );

        expect(outlet).toBeInTheDocument();
        expect(outlet).toHaveTextContent(/Templates UI lands in D2\./);
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
});
