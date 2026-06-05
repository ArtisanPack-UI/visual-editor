import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { ConvertToUnsyncedDialog } from '../convert-to-unsynced-dialog';
import type { PatternRecord } from '../api-client';

const CREATE_MOCK = vi.fn();

vi.mock('@wordpress/blocks', () => ({
    serialize: vi.fn(
        (blocks: readonly { name?: string }[]) =>
            blocks.map((block) => `<!-- wp:${block.name ?? '?'} -->`).join('')
    ),
}));

vi.mock('../api-client', async () => {
    const actual =
        await vi.importActual<typeof import('../api-client')>('../api-client');

    return {
        ...actual,
        createPattern: (...args: unknown[]) => CREATE_MOCK(...args),
    };
});

const API_CONFIG = { apiBase: '/visual-editor/api' };

function makeSource(): PatternRecord {
    return {
        id: 33,
        slug: 'cta',
        title: { rendered: 'CTA banner' },
        content: { raw: '<!-- wp:paragraph -->', blocks: [{ name: 'core/paragraph' }] },
        synced: true,
        categories: ['featured'],
        status: 'publish',
        type: 'wp_block',
    };
}

beforeEach(() => {
    CREATE_MOCK.mockReset();
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('<ConvertToUnsyncedDialog />', () => {
    it('does not say "Detach" anywhere', () => {
        render(
            <ConvertToUnsyncedDialog
                apiConfig={API_CONFIG}
                source={makeSource()}
                onClose={() => undefined}
                onCreated={vi.fn()}
            />
        );

        expect(screen.queryByText(/Detach/i)).toBeNull();
        expect(
            screen.getByTestId('ap-pattern-dialog-convert-warning')
        ).toHaveTextContent(/Sync status is permanent/i);
    });

    it('creates a NEW unsynced pattern (synced: false) preserving categories', async () => {
        const user = userEvent.setup();
        const onCreated = vi.fn();
        const source = makeSource();

        CREATE_MOCK.mockResolvedValue({
            ...source,
            id: 99,
            slug: 'cta-copy',
            synced: false,
        });

        render(
            <ConvertToUnsyncedDialog
                apiConfig={API_CONFIG}
                source={source}
                onClose={() => undefined}
                onCreated={onCreated}
            />
        );

        await user.click(
            screen.getByTestId('ap-pattern-dialog-convert-submit')
        );

        expect(CREATE_MOCK).toHaveBeenCalledWith(
            API_CONFIG,
            expect.objectContaining({
                slug: 'cta-copy',
                synced: false,
                categories: ['featured'],
            })
        );
    });

    it('uses workingBlocks when provided', async () => {
        const user = userEvent.setup();
        const source = makeSource();

        CREATE_MOCK.mockResolvedValue({ ...source, id: 100, synced: false });

        const live = [{ name: 'core/heading' }];

        render(
            <ConvertToUnsyncedDialog
                apiConfig={API_CONFIG}
                source={source}
                workingBlocks={live}
                onClose={() => undefined}
                onCreated={vi.fn()}
            />
        );

        await user.click(
            screen.getByTestId('ap-pattern-dialog-convert-submit')
        );

        expect(CREATE_MOCK).toHaveBeenCalledWith(
            API_CONFIG,
            expect.objectContaining({
                content: expect.objectContaining({
                    blocks: live,
                }),
            })
        );
    });
});
