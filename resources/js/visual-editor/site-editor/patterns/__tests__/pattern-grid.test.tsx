import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// #667 — `PatternThumbnail` imports `parse` from `@wordpress/blocks`
// so the pattern-grid module graph now transitively loads the real
// package. Under jsdom it trips on a `type: json` import at the top of
// the build. Stub `parse` so the module chain resolves; the theme-only
// path we care about in this suite passes it a real Gutenberg comment
// block and the stub echoes back a single block instance so the
// thumbnail renders the non-empty summary.
vi.mock('@wordpress/blocks', () => ({
    parse: (source: string) =>
        typeof source === 'string' && source.trim() !== ''
            ? [{ name: 'core/heading', innerBlocks: [] }]
            : [],
}));

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
        // H7 (#432). H6's `PatternController::index` returns a flat array.
        LIST_MOCK.mockResolvedValue([
            makePattern({
                id: 7,
                slug: 'hero',
                title: { rendered: 'Hero' },
                synced: true,
                categories: ['featured', 'hero'],
            }),
        ]);

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
        LIST_MOCK.mockResolvedValue([
            makePattern({
                id: 12,
                slug: 'plain',
                synced: false,
            }),
        ]);

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

        LIST_MOCK.mockResolvedValue([
            makePattern({ id: 22, slug: 'banner', synced: true }),
        ]);

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
        LIST_MOCK.mockResolvedValue([]);

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

    it('shows the block tree for theme patterns that only ship rawContent (#667)', async () => {
        // Theme-shipped patterns arrive with `blocks: []` because
        // cms-framework's `PatternResolver::buildThemePattern()` only
        // serializes `raw`. `PatternThumbnail` must parse the raw
        // string client-side rather than falling through to the empty
        // placeholder — otherwise every theme pattern renders as an
        // "Empty pattern" card.
        LIST_MOCK.mockResolvedValue([
            makePattern({
                id: 91,
                slug: 'theme-hero',
                title: { rendered: 'Theme hero' },
                synced: false,
                content: {
                    raw:
                        '<!-- wp:heading --><h2>Hi</h2><!-- /wp:heading -->' +
                        '<!-- wp:paragraph --><p>Body</p><!-- /wp:paragraph -->',
                    blocks: [],
                },
            }),
        ]);

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
                screen.getByTestId('ap-pattern-card-91')
            ).toBeInTheDocument()
        );

        expect(screen.queryByTestId('ap-pattern-thumb-empty')).toBeNull();
        expect(screen.getByTestId('ap-pattern-thumb')).toBeInTheDocument();
        // The mocked `parse()` at the top of this file returns a
        // single `core/heading` block for any non-empty source, so a
        // successful raw-content fallback puts that name into the
        // thumbnail's block-tree summary. Asserting the text guards
        // against a regression where the thumbnail renders the
        // wrapper markup but describeBlocks receives an empty array.
        expect(screen.getByText('core/heading')).toBeInTheDocument();
    });
});
