import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { DeletePatternDialog } from '../delete-pattern-dialog';
import type { PatternRecord } from '../api-client';

const DELETE_MOCK = vi.fn();
const LIST_ENTITIES_MOCK = vi.fn();
const FETCH_ENTITY_MOCK = vi.fn();

vi.mock('../api-client', async () => {
    const actual =
        await vi.importActual<typeof import('../api-client')>('../api-client');

    return {
        ...actual,
        deletePattern: (...args: unknown[]) => DELETE_MOCK(...args),
    };
});

vi.mock('../../api-client', async () => {
    const actual =
        await vi.importActual<typeof import('../../api-client')>(
            '../../api-client'
        );

    return {
        ...actual,
        listEntities: (...args: unknown[]) => LIST_ENTITIES_MOCK(...args),
        fetchEntity: (...args: unknown[]) => FETCH_ENTITY_MOCK(...args),
    };
});

vi.mock('@wordpress/blocks', () => ({
    parse: () => [],
}));

const API_CONFIG = { apiBase: '/visual-editor/api' };

function makeSyncedPattern(overrides: Partial<PatternRecord> = {}): PatternRecord {
    return {
        id: 42,
        slug: 'hero',
        title: { rendered: 'Hero pattern' },
        content: { raw: '', blocks: [] },
        synced: true,
        categories: [],
        status: 'publish',
        type: 'wp_block',
        ...overrides,
    };
}

beforeEach(() => {
    DELETE_MOCK.mockReset();
    LIST_ENTITIES_MOCK.mockReset();
    FETCH_ENTITY_MOCK.mockReset();
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('<DeletePatternDialog />', () => {
    it('shows a usage count derived from templates and parts for synced patterns', async () => {
        // H7 (#432). H6's list endpoints return a flat array.
        LIST_ENTITIES_MOCK.mockImplementation((_config, kind) => {
            if (kind === 'template') {
                return Promise.resolve([
                    {
                        id: 1,
                        slug: 'index',
                        content: {
                            raw: '',
                            blocks: [
                                {
                                    name: 'core/block',
                                    attributes: { ref: 42 },
                                    innerBlocks: [],
                                },
                                {
                                    name: 'core/group',
                                    attributes: {},
                                    innerBlocks: [
                                        {
                                            name: 'core/block',
                                            attributes: { ref: 42 },
                                            innerBlocks: [],
                                        },
                                    ],
                                },
                            ],
                        },
                    },
                ]);
            }

            return Promise.resolve([]);
        });

        render(
            <DeletePatternDialog
                apiConfig={API_CONFIG}
                pattern={makeSyncedPattern()}
                onClose={() => undefined}
                onDeleted={() => undefined}
            />
        );

        await waitFor(() =>
            expect(
                screen.getByTestId('ap-pattern-dialog-delete-usage-count')
            ).toHaveTextContent(/Used in 2 places/i)
        );
    });

    it('omits the usage panel for unsynced patterns', () => {
        render(
            <DeletePatternDialog
                apiConfig={API_CONFIG}
                pattern={makeSyncedPattern({ synced: false })}
                onClose={() => undefined}
                onDeleted={() => undefined}
            />
        );

        expect(screen.queryByTestId('ap-pattern-dialog-delete-usage')).toBeNull();
    });

    it('calls deletePattern when confirmed', async () => {
        DELETE_MOCK.mockResolvedValue(undefined);
        LIST_ENTITIES_MOCK.mockResolvedValue([]);

        const onDeleted = vi.fn();
        const user = userEvent.setup();
        const pattern = makeSyncedPattern({ synced: false });

        render(
            <DeletePatternDialog
                apiConfig={API_CONFIG}
                pattern={pattern}
                onClose={() => undefined}
                onDeleted={onDeleted}
            />
        );

        await user.click(screen.getByTestId('ap-pattern-dialog-delete-submit'));

        await waitFor(() => expect(DELETE_MOCK).toHaveBeenCalled());
        expect(DELETE_MOCK).toHaveBeenCalledWith(API_CONFIG, pattern.id);
        expect(onDeleted).toHaveBeenCalledWith(pattern);
    });
});
