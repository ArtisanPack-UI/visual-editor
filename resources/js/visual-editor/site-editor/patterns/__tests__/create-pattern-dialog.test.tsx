import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { CreatePatternDialog } from '../create-pattern-dialog';
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

function makeRecord(): PatternRecord {
    return {
        id: 99,
        slug: 'new-pattern',
        title: { rendered: 'New pattern' },
        content: { raw: '', blocks: [] },
        synced: true,
        categories: [],
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

describe('<CreatePatternDialog />', () => {
    it('creates an empty synced pattern when "Create" is submitted', async () => {
        const user = userEvent.setup();
        const onCreated = vi.fn();
        const record = makeRecord();

        CREATE_MOCK.mockResolvedValue(record);

        render(
            <CreatePatternDialog
                apiConfig={API_CONFIG}
                initialSync="synced"
                sourceBlocks={null}
                onClose={() => undefined}
                onCreated={onCreated}
            />
        );

        const nameInput = screen.getByTestId(
            'ap-pattern-dialog-create-name'
        );

        await user.type(nameInput, 'Hero banner');

        const submit = screen.getByTestId(
            'ap-pattern-dialog-create-submit'
        );

        await user.click(submit);

        expect(CREATE_MOCK).toHaveBeenCalledWith(
            API_CONFIG,
            expect.objectContaining({
                slug: 'hero-banner',
                title: 'Hero banner',
                synced: true,
            })
        );
        expect(onCreated).toHaveBeenCalledWith(record, { sync: 'synced' });
    });

    it('switches to unsynced when the unsynced radio is picked', async () => {
        const user = userEvent.setup();
        const record = makeRecord();

        CREATE_MOCK.mockResolvedValue(record);

        render(
            <CreatePatternDialog
                apiConfig={API_CONFIG}
                initialSync="synced"
                sourceBlocks={[{ name: 'core/paragraph' }]}
                initialName="Lead-in"
                onClose={() => undefined}
                onCreated={vi.fn()}
            />
        );

        await user.click(
            screen.getByTestId('ap-pattern-dialog-create-sync-unsynced')
        );

        await user.click(screen.getByTestId('ap-pattern-dialog-create-submit'));

        expect(CREATE_MOCK).toHaveBeenCalledWith(
            API_CONFIG,
            expect.objectContaining({
                synced: false,
                content: expect.objectContaining({
                    blocks: expect.any(Array),
                }),
            })
        );
    });

    it('renders the convert intro copy when source blocks are provided', () => {
        render(
            <CreatePatternDialog
                apiConfig={API_CONFIG}
                initialSync={null}
                sourceBlocks={[{ name: 'core/heading' }]}
                onClose={() => undefined}
                onCreated={vi.fn()}
            />
        );

        const intro = screen.getByTestId(
            'ap-pattern-dialog-create-intro'
        );

        expect(intro).toHaveTextContent(/Sync type is permanent/i);
    });

    it('blocks submission when slug is empty', async () => {
        const user = userEvent.setup();

        render(
            <CreatePatternDialog
                apiConfig={API_CONFIG}
                initialSync="synced"
                sourceBlocks={null}
                onClose={() => undefined}
                onCreated={vi.fn()}
            />
        );

        await user.click(screen.getByTestId('ap-pattern-dialog-create-submit'));

        expect(CREATE_MOCK).not.toHaveBeenCalled();
        expect(screen.getByRole('alert')).toHaveTextContent(/Enter a slug/i);
    });
});
