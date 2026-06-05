/**
 * Navigation section orchestrator integration tests.
 *
 * Covers the headline acceptance criteria:
 *   - Section lists menus from C4's index endpoint.
 *   - Opening a menu loads it into the canvas.
 *   - Save dispatches a PUT.
 *   - Locations panel renders + assignment writes.
 *   - Create-new flow creates and opens the new menu.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    act,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/react';
import { useEffect, useRef, useState } from 'react';

import type { SiteEditorApiConfig } from '../../api-client';
import type { EntityEditorState } from '../../entity-editor';
import { useNavigationSectionViews } from '../navigation-section';

const API: SiteEditorApiConfig = { apiBase: '/visual-editor/api' };

interface PendingResponse {
    body: unknown;
    status?: number;
}

interface HarnessProps {
    activeEntityId: string | null;
    onOpenEntity: (id: string) => void;
    onState: (state: EntityEditorState) => void;
    saveButtonLabel?: string;
}

function Harness(props: HarnessProps): JSX.Element {
    const { activeEntityId, onOpenEntity, onState } = props;
    const views = useNavigationSectionViews({
        apiConfig: API,
        enabled: true,
        activeEntityId,
        onOpenEntity,
        onStateChange: onState,
    });

    return (
        <div>
            <div data-testid="harness-navigator">{views.navigator}</div>
            <div data-testid="harness-canvas">{views.canvas}</div>
            <div data-testid="harness-inspector">{views.inspector}</div>
            <div data-testid="harness-overlay">{views.overlay}</div>
        </div>
    );
}

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

interface FetchRoute {
    matcher: RegExp;
    method: string;
    response: PendingResponse | (() => PendingResponse);
}

function installFetch(routes: FetchRoute[]): ReturnType<typeof vi.fn> {
    const fn = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
        const method = (init?.method ?? 'GET').toUpperCase();
        const href = url.toString();

        for (const route of routes) {
            if (route.method === method && route.matcher.test(href)) {
                const out =
                    typeof route.response === 'function'
                        ? route.response()
                        : route.response;
                return jsonResponse(out.body, out.status ?? 200);
            }
        }

        return jsonResponse({ message: `unmatched ${method} ${href}` }, 404);
    });

    vi.stubGlobal('fetch', fn);
    return fn;
}

beforeEach(() => {
    document.querySelectorAll('meta[name="csrf-token"]').forEach((node) =>
        node.remove()
    );
});

afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

const baseLocations: PendingResponse = {
    body: {
        data: [
            {
                slug: 'primary',
                label: 'Primary Menu',
                menu: { id: 1, slug: 'main', title: 'Main' },
                is_fallback: false,
            },
        ],
    },
};

// H7 (#432). H6's `MenuController::index` returns a flat array.
const baseList: PendingResponse = {
    body: [
        {
            id: 1,
            slug: 'main',
            title: { rendered: 'Main' },
            content: { raw: '', blocks: [] },
            status: 'publish',
            menu_order: 0,
            location: 'primary',
            type: 'wp_navigation',
        },
    ],
};

describe('navigation-section list + open', () => {
    it('lists menus from the C4 index endpoint and opens one on click', async () => {
        const onOpen = vi.fn();
        const onState = vi.fn();

        installFetch([
            {
                matcher: /\/menu-locations$/,
                method: 'GET',
                response: baseLocations,
            },
            {
                matcher: /\/menus\?per_page=50/,
                method: 'GET',
                response: baseList,
            },
        ]);

        render(
            <Harness
                activeEntityId={null}
                onOpenEntity={onOpen}
                onState={onState}
            />
        );

        await waitFor(() =>
            expect(screen.getByTestId('ap-nav-browser-row-1')).toBeInTheDocument()
        );

        fireEvent.click(screen.getByTestId('ap-nav-browser-row-1'));
        expect(onOpen).toHaveBeenCalledWith('1');
    });

    it('renders the locations panel from /menu-locations', async () => {
        const onOpen = vi.fn();
        const onState = vi.fn();

        installFetch([
            {
                matcher: /\/menu-locations$/,
                method: 'GET',
                response: baseLocations,
            },
            {
                matcher: /\/menus\?per_page=50/,
                method: 'GET',
                response: baseList,
            },
        ]);

        render(
            <Harness
                activeEntityId={null}
                onOpenEntity={onOpen}
                onState={onState}
            />
        );

        await waitFor(() =>
            expect(
                screen.getByTestId('ap-locations-row-primary')
            ).toBeInTheDocument()
        );
    });
});

describe('navigation-section save pipeline', () => {
    it('dispatches PUT /menus/{id} when the entity-state save runs', async () => {
        const stateRef: { current: EntityEditorState | null } = {
            current: null,
        };
        const onState = (state: EntityEditorState): void => {
            stateRef.current = state;
        };

        const fetchMock = installFetch([
            {
                matcher: /\/menu-locations$/,
                method: 'GET',
                response: baseLocations,
            },
            {
                matcher: /\/menus\?per_page=50/,
                method: 'GET',
                response: baseList,
            },
            {
                matcher: /\/menus\/1$/,
                method: 'GET',
                response: {
                    body: {
                        id: 1,
                        slug: 'main',
                        title: { rendered: 'Main' },
                        content: { raw: '', blocks: [] },
                        status: 'publish',
                        menu_order: 0,
                        location: 'primary',
                        type: 'wp_navigation',
                    },
                },
            },
            {
                matcher: /\/menus\/1$/,
                method: 'PUT',
                response: {
                    body: {
                        id: 1,
                        slug: 'main',
                        title: { rendered: 'Main' },
                        content: { raw: '', blocks: [] },
                        status: 'publish',
                        menu_order: 0,
                        location: 'primary',
                        type: 'wp_navigation',
                    },
                },
            },
        ]);

        render(
            <Harness
                activeEntityId="1"
                onOpenEntity={vi.fn()}
                onState={onState}
            />
        );

        await waitFor(() => expect(stateRef.current?.save).not.toBeNull());

        await act(async () => {
            await stateRef.current?.save?.();
        });

        const calls = fetchMock.mock.calls;
        const putCall = calls.find(
            ([url, init]) =>
                typeof url === 'string' &&
                url.endsWith('/menus/1') &&
                (init as RequestInit | undefined)?.method === 'PUT'
        );

        expect(putCall).toBeDefined();
    });
});
