import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { PatternGrid } from '../pattern-grid';
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
        title: { rendered: 'Sample' },
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

describe('<PatternGrid />', () => {
    it('renders one card per pattern with a synced badge', async () => {
        LIST_MOCK.mockResolvedValue({
            data: [
                makePattern({
                    id: 7,
                    slug: 'hero',
                    title: { rendered: 'Hero' },
                    synced: true,
                    categories: ['featured', 'hero'],
                }),
            ],
            meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
        });

        render(
            <PatternGrid
                apiConfig={API_CONFIG}
                synced={true}
                activeEntityId={null}
                onEdit={() => undefined}
                onConvertToUnsynced={() => undefined}
                onDelete={() => undefined}
                onCreate={() => undefined}
            />
        );

        await waitFor(() =>
            expect(screen.getByTestId('ap-pattern-card-7')).toBeInTheDocument()
        );

        expect(screen.getByTestId('ap-pattern-card-badge-7')).toHaveTextContent(
            'Synced'
        );
        expect(screen.getByTestId('ap-pattern-card-7')).toHaveTextContent(
            'featured, hero'
        );
    });

    it('does not render the convert-to-unsynced button on unsynced cards', async () => {
        LIST_MOCK.mockResolvedValue({
            data: [
                makePattern({
                    id: 12,
                    slug: 'plain',
                    synced: false,
                }),
            ],
            meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
        });

        render(
            <PatternGrid
                apiConfig={API_CONFIG}
                synced={false}
                activeEntityId={null}
                onEdit={() => undefined}
                onConvertToUnsynced={() => undefined}
                onDelete={() => undefined}
                onCreate={() => undefined}
            />
        );

        await waitFor(() =>
            expect(
                screen.getByTestId('ap-pattern-card-12')
            ).toBeInTheDocument()
        );

        expect(
            screen.queryByTestId('ap-pattern-card-convert-12')
        ).toBeNull();
    });

    it('triggers Edit when the edit action is activated', async () => {
        const onEdit = vi.fn();

        LIST_MOCK.mockResolvedValue({
            data: [makePattern({ id: 22, slug: 'banner', synced: true })],
            meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
        });

        const user = userEvent.setup();

        render(
            <PatternGrid
                apiConfig={API_CONFIG}
                synced={true}
                activeEntityId={null}
                onEdit={onEdit}
                onConvertToUnsynced={() => undefined}
                onDelete={() => undefined}
                onCreate={() => undefined}
            />
        );

        const editButton = await screen.findByTestId(
            'ap-pattern-card-edit-22'
        );

        await user.click(editButton);

        expect(onEdit).toHaveBeenCalledWith('22');
    });

    it('renders an empty state when no patterns match the active sync flag', async () => {
        LIST_MOCK.mockResolvedValue({
            data: [],
            meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
        });

        render(
            <PatternGrid
                apiConfig={API_CONFIG}
                synced={false}
                activeEntityId={null}
                onEdit={() => undefined}
                onConvertToUnsynced={() => undefined}
                onDelete={() => undefined}
                onCreate={() => undefined}
            />
        );

        const empty = await screen.findByTestId('ap-pattern-grid-empty');

        expect(empty).toHaveTextContent('No unsynced patterns yet');
    });
});
