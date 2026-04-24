import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vitest';
import { useCallback, useEffect, useRef, useState } from 'react';
import { dispatch, select } from '@wordpress/data';

// Force the core-data shim to load + register its `core` store. In the
// real runtime Gutenberg's block-editor bundle imports `@wordpress/core-data`
// (aliased to the shim) and that side-effect runs on module load; the
// styles tests mock Gutenberg out so the shim needs an explicit import
// to register before `dispatch('core')` returns a functioning store.
import '@wordpress/core-data';

import type { SiteEditorApiConfig } from '../../api-client';
import type { EntityEditorState } from '../../entity-editor';
import { useStylesSectionViews } from '../styles-section';

type CoreSelect = Record<string, (...args: unknown[]) => unknown>;
type CoreDispatch = Record<string, (...args: unknown[]) => unknown>;

const coreSelect = (): CoreSelect => select('core') as CoreSelect;
const coreDispatch = (): CoreDispatch => dispatch('core') as CoreDispatch;

const API: SiteEditorApiConfig = { apiBase: '/visual-editor/api' };

const DEFAULT_BASE = {
    version: 3,
    settings: {
        color: {
            palette: [
                { slug: 'primary', name: 'Primary', color: '#3b82f6' },
                { slug: 'contrast', name: 'Contrast', color: '#111827' },
            ],
        },
        typography: {
            fontFamilies: [
                {
                    slug: 'sans',
                    name: 'Sans',
                    fontFamily: "'Inter', sans-serif",
                },
                {
                    slug: 'serif',
                    name: 'Serif',
                    fontFamily: "'Source Serif 4', Georgia, serif",
                },
            ],
            fontSizes: [
                { slug: 'medium', name: 'Medium', size: '1rem' },
            ],
        },
        layout: { contentSize: '720px', wideSize: '1120px' },
    },
    styles: {
        color: {
            background: 'var(--wp--preset--color--contrast)',
            text: 'var(--wp--preset--color--primary)',
        },
        typography: {
            fontFamily: 'var(--wp--preset--font-family--sans)',
            fontSize: 'var(--wp--preset--font-size--medium)',
            lineHeight: '1.6',
        },
        elements: {
            link: {
                color: {
                    text: 'var(--wp--preset--color--primary)',
                },
            },
        },
        blocks: {
            'core/button': {
                color: {
                    background: 'var(--wp--preset--color--primary)',
                },
                border: { radius: '0.5rem' },
            },
        },
    },
    variations: [
        {
            slug: 'light',
            title: 'Light',
            settings: {},
            styles: { color: { background: '#ffffff' } },
        },
        {
            slug: 'dark',
            title: 'Dark',
            settings: {},
            styles: { color: { background: '#000000' } },
        },
    ],
};

const DEFAULT_RECORD = {
    id: 7,
    version: 3,
    settings: DEFAULT_BASE.settings,
    styles: DEFAULT_BASE.styles,
};

const BLOCKS_RESPONSE = {
    blocks: [
        { name: 'core/button', title: 'Button' },
        { name: 'core/heading', title: 'Heading' },
    ],
};

interface CallRecord {
    url: string;
    method: string;
    body: unknown;
}

type ResponseFactory = (body: CallRecord['body']) => Response;

interface HarnessHandlers {
    onUpdate?: (payload: unknown) => Response;
}

function installFetchStub(
    handlers: HarnessHandlers = {}
): { calls: CallRecord[]; restore: () => void } {
    const calls: CallRecord[] = [];
    const jsonResponse: ResponseFactory = () =>
        new Response(JSON.stringify(DEFAULT_RECORD), {
            status: 200,
            headers: { 'Content-Type': 'application/json' },
        });

    const fetcher = vi.fn(
        async (input: RequestInfo | URL, init?: RequestInit) => {
            const url =
                typeof input === 'string' ? input : input.toString();
            const method =
                init?.method?.toUpperCase() ??
                (typeof input === 'object' &&
                'method' in input &&
                typeof input.method === 'string'
                    ? input.method.toUpperCase()
                    : 'GET');
            let parsedBody: unknown = null;
            const rawBody = init?.body;

            if (typeof rawBody === 'string' && rawBody !== '') {
                try {
                    parsedBody = JSON.parse(rawBody);
                } catch {
                    parsedBody = rawBody;
                }
            }

            calls.push({ url, method, body: parsedBody });

            if (url.includes('/global-styles/lookup')) {
                return new Response(JSON.stringify({ id: 7 }), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                });
            }

            if (url.includes('/global-styles/base')) {
                return new Response(JSON.stringify(DEFAULT_BASE), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                });
            }

            if (url.includes('/global-styles/7') && method === 'PUT') {
                return (
                    handlers.onUpdate?.(parsedBody) ??
                    new Response(JSON.stringify(DEFAULT_RECORD), {
                        status: 200,
                        headers: { 'Content-Type': 'application/json' },
                    })
                );
            }

            if (url.includes('/global-styles/')) {
                return jsonResponse(parsedBody);
            }

            if (url.endsWith('/blocks')) {
                return new Response(JSON.stringify(BLOCKS_RESPONSE), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                });
            }

            return new Response('not found', { status: 404 });
        }
    );

    const original = global.fetch;
    global.fetch = fetcher as unknown as typeof fetch;

    return {
        calls,
        restore: () => {
            global.fetch = original;
        },
    };
}

interface HarnessProps {
    enabled?: boolean;
    onState?: (state: EntityEditorState) => void;
}

function Harness(props: HarnessProps): JSX.Element {
    const { enabled = true, onState } = props;
    const [state, setState] = useState<EntityEditorState | null>(null);

    // Stash the observer in a ref so we can pass a stable onStateChange
    // down into the hook — otherwise every Harness render creates a new
    // callback identity, the hook's own onStateChange-watching effect
    // re-fires, setState, re-render, infinite loop.
    const onStateRef = useRef<HarnessProps['onState']>(onState);
    onStateRef.current = onState;

    const handleStateChange = useCallback(
        (next: EntityEditorState): void => {
            setState(next);
            onStateRef.current?.(next);
        },
        []
    );

    const { navigator, canvas, inspector } = useStylesSectionViews({
        apiConfig: API,
        enabled,
        onStateChange: handleStateChange,
    });

    useEffect(() => {
        // Render latest entity state as an attribute so tests can read it
        // without reaching into the top-bar chrome.
        if (state !== null) {
            document.body.setAttribute(
                'data-entity-state',
                JSON.stringify({
                    isDirty: state.isDirty,
                    saveStatus: state.saveStatus,
                    entityId: state.entityId,
                })
            );
        }
    }, [state]);

    return (
        <div data-testid="styles-harness">
            <aside data-testid="harness-navigator">{navigator}</aside>
            <main data-testid="harness-canvas">{canvas}</main>
            <aside data-testid="harness-inspector">{inspector}</aside>
        </div>
    );
}

let fetchStub: ReturnType<typeof installFetchStub>;

beforeEach(() => {
    coreDispatch().reset();
    fetchStub = installFetchStub();
});

afterEach(() => {
    fetchStub.restore();
    coreDispatch().reset();
    document.body.removeAttribute('data-entity-state');
});

describe('useStylesSectionViews — bootstrap', () => {
    it('dispatches receiveCurrentGlobalStylesId and receiveGlobalStylesBase on mount', async () => {
        render(<Harness />);

        await waitFor(() => {
            expect(
                coreSelect().__experimentalGetCurrentGlobalStylesId()
            ).toBe(7);
        });

        expect(
            coreSelect().__experimentalGlobalStylesBaseStyles()
        ).toMatchObject({
            version: 3,
            settings: expect.anything(),
            styles: expect.anything(),
        });
    });

    it('renders the six panel-root nodes in the navigator', async () => {
        render(<Harness />);

        await waitFor(() =>
            expect(
                screen.getByTestId('ap-site-editor-styles-nav-typography')
            ).toBeInTheDocument()
        );

        expect(
            screen.getByTestId('ap-site-editor-styles-nav-typography')
        ).toBeInTheDocument();
        expect(
            screen.getByTestId('ap-site-editor-styles-nav-colors')
        ).toBeInTheDocument();
        expect(
            screen.getByTestId('ap-site-editor-styles-nav-layout')
        ).toBeInTheDocument();
        expect(
            screen.getByTestId('ap-site-editor-styles-nav-blocks')
        ).toBeInTheDocument();
        expect(
            screen.getByTestId('ap-site-editor-styles-nav-elements')
        ).toBeInTheDocument();
        expect(
            screen.getByTestId('ap-site-editor-styles-nav-variations')
        ).toBeInTheDocument();
    });

    it('skips fetching when enabled is false', async () => {
        render(<Harness enabled={false} />);

        // Allow micro-tasks to flush; fetch should remain idle.
        await new Promise((resolve) => setTimeout(resolve, 10));

        expect(fetchStub.calls.length).toBe(0);
    });
});

describe('useStylesSectionViews — panels', () => {
    it('mounts the typography panel by default', async () => {
        render(<Harness />);

        await waitFor(() =>
            expect(
                screen.getByTestId(
                    'ap-site-editor-style-panel-typography'
                )
            ).toBeInTheDocument()
        );
    });

    it('edits font family and marks the state dirty', async () => {
        const onState = vi.fn();
        const user = userEvent.setup();
        render(<Harness onState={onState} />);

        const select = (await screen.findByTestId(
            'ap-site-editor-style-field-select-font-family'
        )) as HTMLSelectElement;

        // The default record ships font-family set to the "sans" preset;
        // switching to "serif" writes a new CSS var ref and marks the
        // editor dirty.
        await user.selectOptions(
            select,
            'var(--wp--preset--font-family--serif)'
        );

        await waitFor(() => {
            const last = onState.mock.calls.at(-1)?.[0] as EntityEditorState;
            expect(last?.isDirty).toBe(true);
        });
    });

    it('resets a customized value back to the base default', async () => {
        const user = userEvent.setup();
        render(<Harness />);

        const select = (await screen.findByTestId(
            'ap-site-editor-style-field-select-font-family'
        )) as HTMLSelectElement;

        await user.selectOptions(
            select,
            'var(--wp--preset--font-family--serif)'
        );

        const reset = await screen.findByTestId(
            'ap-site-editor-style-field-reset-font-family'
        );

        await user.click(reset);

        await waitFor(() =>
            expect(select.value).toBe(
                'var(--wp--preset--font-family--sans)'
            )
        );
    });

    it('switches to the Colors panel and adds a palette entry', async () => {
        const user = userEvent.setup();
        render(<Harness />);

        await screen.findByTestId(
            'ap-site-editor-style-panel-typography'
        );

        await user.click(
            screen.getByTestId('ap-site-editor-styles-nav-colors')
        );

        await screen.findByTestId('ap-site-editor-style-panel-colors');

        const before = screen.getAllByTestId(/ap-site-editor-style-palette-row-/);

        await user.click(
            screen.getByTestId('ap-site-editor-style-palette-add')
        );

        await waitFor(() => {
            const rows = screen.getAllByTestId(
                /ap-site-editor-style-palette-row-/
            );
            expect(rows.length).toBe(before.length + 1);
        });
    });

    it('surfaces a duplicate-slug error when two palette entries share a slug', async () => {
        const user = userEvent.setup();
        render(<Harness />);

        await user.click(
            screen.getByTestId('ap-site-editor-styles-nav-colors')
        );

        const otherSlug = await screen.findByTestId(
            'ap-site-editor-style-palette-slug-1'
        );

        // Force a duplicate of the first seeded palette slug ("primary").
        // `fireEvent.change` replaces the value in one step — avoids the
        // per-keystroke flicker through intermediate non-duplicate values
        // that `user.type` causes on a controlled input.
        fireEvent.change(otherSlug, { target: { value: 'primary' } });

        await waitFor(() =>
            expect(
                screen.getByTestId('ap-site-editor-style-palette-error')
            ).toBeInTheDocument()
        );
    });

    it('switches to the Blocks panel and drills into a block detail', async () => {
        const user = userEvent.setup();
        render(<Harness />);

        await user.click(
            screen.getByTestId('ap-site-editor-styles-nav-blocks')
        );

        await waitFor(() =>
            expect(
                screen.getByTestId(
                    'ap-site-editor-style-panel-blocks-index'
                )
            ).toBeInTheDocument()
        );

        const buttonEntry = await screen.findByTestId(
            'ap-site-editor-styles-nav-block-core/button'
        );

        await user.click(buttonEntry);

        await waitFor(() =>
            expect(
                screen.getByTestId(
                    'ap-site-editor-style-panel-block-detail'
                )
            ).toBeInTheDocument()
        );

        expect(
            screen
                .getByTestId('ap-site-editor-styles-breadcrumb')
                .textContent
        ).toContain('Button');
    });

    it('switches to the Elements panel and drills into the Link element', async () => {
        const user = userEvent.setup();
        render(<Harness />);

        await user.click(
            screen.getByTestId('ap-site-editor-styles-nav-elements')
        );

        await screen.findByTestId(
            'ap-site-editor-style-panel-elements-index'
        );

        await user.click(
            screen.getByTestId('ap-site-editor-styles-nav-element-link')
        );

        await waitFor(() =>
            expect(
                screen.getByTestId(
                    'ap-site-editor-style-panel-element-detail'
                )
            ).toBeInTheDocument()
        );

        expect(
            screen
                .getByTestId('ap-site-editor-styles-breadcrumb')
                .textContent
        ).toContain('Link');
    });
});

describe('useStylesSectionViews — variations', () => {
    it('lists theme variations on the canvas and applies one on click', async () => {
        const user = userEvent.setup();
        const onState = vi.fn();
        render(<Harness onState={onState} />);

        const lightVariation = await screen.findByTestId(
            'ap-site-editor-style-book-variation-light'
        );

        await user.click(lightVariation);

        await waitFor(() => {
            const last = onState.mock.calls.at(-1)?.[0] as EntityEditorState;
            expect(last?.isDirty).toBe(true);
        });
    });

    it('shows an empty state when the base payload ships no variations', async () => {
        // Swap the fetcher mid-test to return a base without variations.
        fetchStub.restore();
        const newStub = installFetchStub();
        const originalFetch = global.fetch as unknown as typeof fetch;

        global.fetch = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
            const url =
                typeof input === 'string' ? input : input.toString();

            if (url.includes('/global-styles/base')) {
                const { variations: _variations, ...rest } = DEFAULT_BASE;

                return new Response(JSON.stringify(rest), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                });
            }

            return originalFetch(input, init);
        }) as unknown as typeof fetch;

        fetchStub = newStub;

        render(<Harness />);

        await waitFor(() =>
            expect(
                screen.getByTestId(
                    'ap-site-editor-style-book-no-variations'
                )
            ).toBeInTheDocument()
        );
    });
});

describe('useStylesSectionViews — save pipeline', () => {
    it('issues a PUT and exits the dirty state on success', async () => {
        const onState = vi.fn();
        const user = userEvent.setup();
        render(<Harness onState={onState} />);

        const select = await screen.findByTestId(
            'ap-site-editor-style-field-select-font-family'
        );

        await user.selectOptions(
            select,
            'var(--wp--preset--font-family--serif)'
        );

        // Trigger save via the hook state — our harness stashes it on the
        // last onState call.
        await waitFor(() => {
            const last = onState.mock.calls.at(-1)?.[0] as EntityEditorState;
            expect(last?.save).not.toBeNull();
        });

        const last = onState.mock.calls.at(-1)?.[0] as EntityEditorState;

        await act(async () => {
            await last.save?.();
        });

        await waitFor(() => {
            const now = onState.mock.calls.at(-1)?.[0] as EntityEditorState;
            expect(now?.saveStatus).toBe('saved');
            expect(now?.isDirty).toBe(false);
        });

        const putCall = fetchStub.calls.find((call) => call.method === 'PUT');

        expect(putCall).toBeDefined();
        expect(putCall?.url).toContain('/global-styles/7');
    });

    it('renders a 422 validation error inline', async () => {
        fetchStub.restore();
        fetchStub = installFetchStub({
            onUpdate: () =>
                new Response(
                    JSON.stringify({
                        message:
                            'The given data was invalid.',
                        errors: {
                            'styles.typography.fontFamily': [
                                'Must be a CSS value.',
                            ],
                        },
                    }),
                    {
                        status: 422,
                        headers: { 'Content-Type': 'application/json' },
                    }
                ),
        });

        const onState = vi.fn();
        const user = userEvent.setup();
        render(<Harness onState={onState} />);

        const select = await screen.findByTestId(
            'ap-site-editor-style-field-select-font-family'
        );

        await user.selectOptions(
            select,
            'var(--wp--preset--font-family--serif)'
        );

        await waitFor(() => {
            const last = onState.mock.calls.at(-1)?.[0] as EntityEditorState;
            expect(last?.save).not.toBeNull();
        });

        const last = onState.mock.calls.at(-1)?.[0] as EntityEditorState;

        await act(async () => {
            await last.save?.();
        });

        await waitFor(() =>
            expect(
                screen.getByTestId(
                    'ap-site-editor-style-field-error-font-family'
                )
            ).toBeInTheDocument()
        );

        expect(
            screen.getByTestId('ap-site-editor-styles-save-error')
        ).toBeInTheDocument();
    });
});

describe('useStylesSectionViews — breadcrumb + accessibility', () => {
    it('reflects the current scope in the breadcrumb', async () => {
        const user = userEvent.setup();
        render(<Harness />);

        const crumb = await screen.findByTestId(
            'ap-site-editor-styles-breadcrumb'
        );

        expect(crumb.textContent).toContain('Styles');
        expect(crumb.textContent).toContain('Typography');

        await user.click(
            screen.getByTestId('ap-site-editor-styles-nav-colors')
        );

        await waitFor(() =>
            expect(
                screen.getByTestId('ap-site-editor-styles-breadcrumb')
                    .textContent
            ).toContain('Colors')
        );
    });

    it('labels the navigator and variation picker for screen readers', async () => {
        render(<Harness />);

        const navigator = await screen.findByTestId(
            'ap-site-editor-styles-navigator'
        );
        const variations = await screen.findByTestId(
            'ap-site-editor-style-book-variations'
        );

        expect(navigator).toHaveAttribute('aria-label');
        expect(variations).toHaveAttribute('role', 'radiogroup');
        expect(variations).toHaveAttribute('aria-label');
    });
});

describe('useStylesSectionViews — error handling', () => {
    it('surfaces a load error when the lookup request fails', async () => {
        fetchStub.restore();
        const failingFetch = vi.fn(async (input: RequestInfo | URL) => {
            const url = typeof input === 'string' ? input : input.toString();

            if (url.includes('/global-styles/lookup')) {
                return new Response('Server error', { status: 500 });
            }

            if (url.includes('/global-styles/base')) {
                return new Response(JSON.stringify(DEFAULT_BASE), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                });
            }

            if (url.endsWith('/blocks')) {
                return new Response(JSON.stringify(BLOCKS_RESPONSE), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                });
            }

            return new Response('not found', { status: 404 });
        }) as unknown as typeof fetch;

        const original = global.fetch;
        global.fetch = failingFetch;
        fetchStub = { calls: [], restore: () => (global.fetch = original) } as unknown as ReturnType<
            typeof installFetchStub
        >;

        render(<Harness />);

        await waitFor(() =>
            expect(
                screen.getByTestId('ap-site-editor-styles-load-error')
            ).toBeInTheDocument()
        );
    });
});

