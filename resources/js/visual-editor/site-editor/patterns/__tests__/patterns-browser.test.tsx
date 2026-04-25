import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { PatternsBrowser } from '../patterns-browser';
import type { PatternRecord } from '../api-client';

const LIST_MOCK = vi.fn();

vi.mock('../api-client', async () => {
    const actual =
        await vi.importActual<typeof import('../api-client')>('../api-client');

    return {
        ...actual,
        listPatterns: (...args: unknown[]) => LIST_MOCK(...args),
    };
});

const API_CONFIG = { apiBase: '/visual-editor/api' };

function makePattern(overrides: Partial<PatternRecord> = {}): PatternRecord {
    return {
        id: 1,
        slug: 'sample',
        title: { rendered: 'Sample pattern' },
        content: { raw: '', blocks: [] },
        synced: true,
        categories: [],
        status: 'publish',
        type: 'wp_block',
        ...overrides,
    };
}

beforeEach(() => {
    LIST_MOCK.mockReset();
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('<PatternsBrowser />', () => {
    it('renders Synced and Unsynced tabs and shows synced rows by default', async () => {
        LIST_MOCK.mockResolvedValue({
            data: [
                makePattern({
                    id: 5,
                    slug: 'hero',
                    title: { rendered: 'Hero pattern' },
                    synced: true,
                }),
            ],
            meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
        });

        render(
            <PatternsBrowser
                apiConfig={API_CONFIG}
                activeEntityId={null}
                activeTab="synced"
                onSelectTab={() => undefined}
                onOpen={() => undefined}
                onRequestCreate={() => undefined}
            />
        );

        const synced = screen.getByTestId('ap-patterns-browser-tab-synced');
        const unsynced = screen.getByTestId(
            'ap-patterns-browser-tab-unsynced'
        );

        expect(synced).toHaveAttribute('aria-selected', 'true');
        expect(unsynced).toHaveAttribute('aria-selected', 'false');

        await waitFor(() =>
            expect(
                screen.getByTestId('ap-patterns-browser-row-5')
            ).toBeInTheDocument()
        );

        expect(LIST_MOCK).toHaveBeenCalledWith(
            API_CONFIG,
            expect.objectContaining({ synced: true })
        );
    });

    it('switches to the unsynced tab when the parent updates activeTab', async () => {
        const user = userEvent.setup();

        LIST_MOCK.mockResolvedValue({
            data: [],
            meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
        });

        const onSelectTab = vi.fn();

        render(
            <PatternsBrowser
                apiConfig={API_CONFIG}
                activeEntityId={null}
                activeTab="synced"
                onSelectTab={onSelectTab}
                onOpen={() => undefined}
                onRequestCreate={() => undefined}
            />
        );

        await user.click(
            screen.getByTestId('ap-patterns-browser-tab-unsynced')
        );

        expect(onSelectTab).toHaveBeenCalledWith('unsynced');
    });

    it('opens a row when activated', async () => {
        const onOpen = vi.fn();
        const user = userEvent.setup();

        LIST_MOCK.mockResolvedValue({
            data: [
                makePattern({
                    id: 9,
                    slug: 'cta',
                    title: { rendered: 'Call to action' },
                }),
            ],
            meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
        });

        render(
            <PatternsBrowser
                apiConfig={API_CONFIG}
                activeEntityId={null}
                activeTab="synced"
                onSelectTab={() => undefined}
                onOpen={onOpen}
                onRequestCreate={() => undefined}
            />
        );

        const row = await screen.findByTestId('ap-patterns-browser-row-9');

        await user.click(row);

        expect(onOpen).toHaveBeenCalledWith('9');
    });

    it('triggers create with the active sync flag', async () => {
        const user = userEvent.setup();
        const onRequestCreate = vi.fn();

        LIST_MOCK.mockResolvedValue({
            data: [],
            meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
        });

        render(
            <PatternsBrowser
                apiConfig={API_CONFIG}
                activeEntityId={null}
                activeTab="unsynced"
                onSelectTab={() => undefined}
                onOpen={() => undefined}
                onRequestCreate={onRequestCreate}
            />
        );

        await user.click(screen.getByTestId('ap-patterns-browser-create'));

        expect(onRequestCreate).toHaveBeenCalledWith(false);
    });

    it('renders the empty state copy that names the active sync mode', async () => {
        LIST_MOCK.mockResolvedValue({
            data: [],
            meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
        });

        render(
            <PatternsBrowser
                apiConfig={API_CONFIG}
                activeEntityId={null}
                activeTab="unsynced"
                onSelectTab={() => undefined}
                onOpen={() => undefined}
                onRequestCreate={() => undefined}
            />
        );

        const empty = await screen.findByTestId(
            'ap-patterns-browser-empty'
        );

        expect(empty).toHaveTextContent('No unsynced patterns yet');
    });
});
