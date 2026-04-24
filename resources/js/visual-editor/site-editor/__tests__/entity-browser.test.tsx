import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { EntityBrowser } from '../entity-browser';

const LIST_MOCK = vi.fn();

vi.mock('../api-client', async () => {
    const actual = await vi.importActual<typeof import('../api-client')>(
        '../api-client'
    );

    return {
        ...actual,
        listEntities: (...args: unknown[]) => LIST_MOCK(...args),
    };
});

const API_CONFIG = { apiBase: '/visual-editor/api' };

beforeEach(() => {
    LIST_MOCK.mockReset();
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('EntityBrowser', () => {
    it('shows a loading status then the rows returned by the API', async () => {
        LIST_MOCK.mockResolvedValue({
            data: [
                {
                    id: 1,
                    slug: 'single',
                    title: { rendered: 'Single post' },
                    content: { raw: '', blocks: [] },
                    status: 'publish',
                    theme: 'default',
                    type: 'wp_template',
                    source: 'theme',
                    origin: null,
                },
            ],
            meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
        });

        const onOpen = vi.fn();

        render(
            <EntityBrowser
                apiConfig={API_CONFIG}
                kind="template"
                activeEntityId={null}
                onOpen={onOpen}
                onRequestCreate={() => undefined}
                listLabel="Templates"
                emptyTitle="No templates yet"
                emptyBody="Create a template."
                createLabel="Add new"
                openAriaLabel={(entity) =>
                    `template: ${entity.title.rendered || entity.slug}`
                }
                renderRow={(entity) => <span>{entity.slug}</span>}
            />
        );

        expect(
            screen.getByTestId('ap-site-editor-entity-browser-loading-template')
        ).toBeInTheDocument();

        await waitFor(() =>
            expect(
                screen.getByTestId(
                    'ap-site-editor-entity-browser-list-template'
                )
            ).toBeInTheDocument()
        );

        const row = screen.getByTestId(
            'ap-site-editor-entity-browser-row-template-1'
        );
        expect(row).toHaveTextContent('Single post');
        expect(row).toHaveAttribute('aria-label', 'Open template: Single post');
    });

    it('calls onOpen with the row id when a row is activated', async () => {
        LIST_MOCK.mockResolvedValue({
            data: [
                {
                    id: 3,
                    slug: 'page',
                    title: { rendered: '' },
                    content: { raw: '', blocks: [] },
                    status: 'publish',
                    theme: 'default',
                    type: 'wp_template',
                    source: 'custom',
                    origin: null,
                },
            ],
            meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
        });

        const onOpen = vi.fn();
        const user = userEvent.setup();

        render(
            <EntityBrowser
                apiConfig={API_CONFIG}
                kind="template"
                activeEntityId={null}
                onOpen={onOpen}
                onRequestCreate={() => undefined}
                listLabel="Templates"
                emptyTitle="No templates yet"
                emptyBody="Create a template."
                createLabel="Add new"
                openAriaLabel={(entity) => `template: ${entity.slug}`}
                renderRow={(entity) => <span>{entity.slug}</span>}
            />
        );

        const row = await screen.findByTestId(
            'ap-site-editor-entity-browser-row-template-3'
        );
        await user.click(row);

        expect(onOpen).toHaveBeenCalledWith('3');
    });

    it('shows the empty state when the API returns no rows', async () => {
        LIST_MOCK.mockResolvedValue({
            data: [],
            meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
        });

        render(
            <EntityBrowser
                apiConfig={API_CONFIG}
                kind="template-part"
                activeEntityId={null}
                onOpen={() => undefined}
                onRequestCreate={() => undefined}
                listLabel="Template Parts"
                emptyTitle="No template parts yet"
                emptyBody="Create a template part."
                createLabel="Add new"
                openAriaLabel={() => 'part'}
                renderRow={() => null}
            />
        );

        await waitFor(() =>
            expect(
                screen.getByTestId(
                    'ap-site-editor-entity-browser-empty-template-part'
                )
            ).toBeInTheDocument()
        );

        expect(screen.getByText('No template parts yet')).toBeInTheDocument();
    });

    it('renders an error + retry when the API fails', async () => {
        LIST_MOCK.mockRejectedValueOnce(new Error('boom'));

        render(
            <EntityBrowser
                apiConfig={API_CONFIG}
                kind="template"
                activeEntityId={null}
                onOpen={() => undefined}
                onRequestCreate={() => undefined}
                listLabel="Templates"
                emptyTitle="None"
                emptyBody=""
                createLabel="Add"
                openAriaLabel={() => 'x'}
                renderRow={() => null}
            />
        );

        await waitFor(() =>
            expect(
                screen.getByTestId(
                    'ap-site-editor-entity-browser-error-template'
                )
            ).toBeInTheDocument()
        );
    });

    it('triggers onRequestCreate when the add-new button is clicked', async () => {
        LIST_MOCK.mockResolvedValue({
            data: [],
            meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
        });

        const onRequestCreate = vi.fn();
        const user = userEvent.setup();

        render(
            <EntityBrowser
                apiConfig={API_CONFIG}
                kind="template"
                activeEntityId={null}
                onOpen={() => undefined}
                onRequestCreate={onRequestCreate}
                listLabel="Templates"
                emptyTitle="No templates yet"
                emptyBody="Create a template."
                createLabel="Add new"
                openAriaLabel={() => 'x'}
                renderRow={() => null}
            />
        );

        await user.click(
            screen.getByTestId('ap-site-editor-entity-browser-create-template')
        );

        expect(onRequestCreate).toHaveBeenCalled();
    });

    it('filters via chips and re-fetches on chip change', async () => {
        LIST_MOCK.mockImplementation(
            async (_config, _kind, params: { status?: string }) => ({
                data:
                    params.status === 'draft'
                        ? [
                              {
                                  id: 5,
                                  slug: 'draft-page',
                                  title: { rendered: 'Draft' },
                                  content: { raw: '', blocks: [] },
                                  status: 'draft',
                                  theme: 'default',
                                  type: 'wp_template',
                                  source: 'custom',
                                  origin: null,
                              },
                          ]
                        : [],
                meta: {
                    current_page: 1,
                    last_page: 1,
                    per_page: 25,
                    total: params.status === 'draft' ? 1 : 0,
                },
            })
        );

        const user = userEvent.setup();

        render(
            <EntityBrowser
                apiConfig={API_CONFIG}
                kind="template"
                activeEntityId={null}
                onOpen={() => undefined}
                onRequestCreate={() => undefined}
                chips={[
                    { id: 'all', label: 'All', filter: {} },
                    {
                        id: 'draft',
                        label: 'Drafts',
                        filter: { status: 'draft' },
                    },
                ]}
                listLabel="Templates"
                emptyTitle="No templates"
                emptyBody=""
                createLabel="Add"
                openAriaLabel={(entity) => `template: ${entity.slug}`}
                renderRow={(entity) => <span>{entity.slug}</span>}
            />
        );

        // Initial fetch — no status filter.
        await waitFor(() => expect(LIST_MOCK).toHaveBeenCalledTimes(1));

        await user.click(
            screen.getByTestId(
                'ap-site-editor-entity-browser-chip-template-draft'
            )
        );

        await waitFor(() =>
            expect(
                within(
                    screen.getByTestId(
                        'ap-site-editor-entity-browser-list-template'
                    )
                ).getByTestId('ap-site-editor-entity-browser-row-template-5')
            ).toBeInTheDocument()
        );

        const secondCall = LIST_MOCK.mock.calls[1];
        expect(secondCall?.[2]?.status).toBe('draft');
    });
});
