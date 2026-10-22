/**
 * Tests for the `artisanpack/single-content` edit component and its
 * shared record cache (#1003).
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, waitFor } from '@testing-library/react';

vi.mock('@wordpress/i18n', () => ({
    __: (text: string) => text,
}));

vi.mock('@wordpress/element', () => {
    const react = require('react') as typeof import('react');
    return {
        useEffect: react.useEffect,
        useMemo: react.useMemo,
        useState: react.useState,
    };
});

vi.mock('@wordpress/components', () => ({
    PanelBody: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
    TextControl: () => null,
    ComboboxControl: ({
        options,
    }: {
        options: ReadonlyArray<{ label: string; value: string }>;
    }) => (
        <ul data-testid="picker">
            {options.map((option) => (
                <li key={option.value}>{option.label}</li>
            ))}
        </ul>
    ),
}));

vi.mock('@wordpress/block-editor', () => ({
    BlockContextProvider: ({
        value,
        children,
    }: {
        value: Record<string, { title?: string; content?: string } | undefined>;
        children: React.ReactNode;
    }) => (
        <div
            data-testid="preview-context"
            data-title={value['artisanpack/postPreview']?.title ?? ''}
            data-content={value['artisanpack/postPreview']?.content ?? ''}
            data-preview-key={'artisanpack/postPreview' in value ? 'set' : 'unset'}
        >
            {children}
        </div>
    ),
    InspectorControls: ({ children }: { children: React.ReactNode }) => (
        <div data-testid="inspector">{children}</div>
    ),
    useBlockProps: (props?: Record<string, unknown>) => ({ ...props }),
    useInnerBlocksProps: (props?: Record<string, unknown>) => ({
        ...props,
        children: <span>inner blocks</span>,
    }),
}));

vi.mock('../../../editor/content-type-registry', () => ({
    getContentTypes: () => [
        { slug: 'post', plural: 'posts' },
        { slug: 'package', plural: 'packages' },
    ],
}));

(globalThis as { React?: unknown }).React = require('react');

import SingleContentEdit from '../edit';
import {
    __resetRecordsCache,
    fetchRecordLabel,
    fetchRecordList,
    fetchRecordPreview,
} from '../records-cache';

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

function wpEntity(id: number, title: string, content = ''): Record<string, unknown> {
    return {
        id,
        title: { rendered: title, raw: title },
        excerpt: { rendered: '', raw: '' },
        content: { rendered: content, raw: '', blocks: [] },
    };
}

const PACKAGES: Record<number, string> = { 11: 'Hooks', 12: 'Icons', 99: 'Older package' };

/**
 * Routes the editor's requests: `query/resolve` returns the requested
 * `postIn` records, collection reads return a one-item list (or the
 * `include` ids).
 */
function routeRequests(url: string, init?: RequestInit): Promise<Response> {
    if (url.endsWith('/query/resolve')) {
        const body = JSON.parse(String(init?.body ?? '{}')) as { postIn?: number[] };
        const data = (body.postIn ?? [])
            .filter((id) => PACKAGES[id] !== undefined)
            .map((id) => wpEntity(id, PACKAGES[id], `<p>${PACKAGES[id]} body</p>`));
        return Promise.resolve(jsonResponse({ data, meta: { total: data.length } }));
    }

    const include = new URL(url, 'http://localhost').searchParams.get('include');
    if (include !== null) {
        const data = include
            .split(',')
            .map(Number)
            .filter((id) => PACKAGES[id] !== undefined)
            .map((id) => ({ id, title: PACKAGES[id] }));
        return Promise.resolve(jsonResponse({ data }));
    }

    return Promise.resolve(jsonResponse({ data: [{ id: 11, title: 'Hooks' }] }));
}

const originalFetch = globalThis.fetch;
let fetchMock: ReturnType<typeof vi.fn>;

beforeEach(() => {
    __resetRecordsCache();
    fetchMock = vi.fn(routeRequests);
    globalThis.fetch = fetchMock as unknown as typeof fetch;
});

afterEach(() => {
    globalThis.fetch = originalFetch;
});

function requestedUrls(): string[] {
    return fetchMock.mock.calls.map((call) => String(call[0]));
}

function resolveBodies(): Array<Record<string, unknown>> {
    return fetchMock.mock.calls
        .filter((call) => String(call[0]).endsWith('/query/resolve'))
        .map((call) => JSON.parse(String((call[1] as RequestInit).body)) as Record<string, unknown>);
}

describe('SingleContentEdit', () => {
    it('previews every block on load with one batched resolve per content type', async () => {
        const { getAllByTestId } = render(
            <>
                <SingleContentEdit
                    attributes={{ postId: 11, postType: 'package' }}
                    setAttributes={vi.fn()}
                />
                <SingleContentEdit
                    attributes={{ postId: 12, postType: 'package' }}
                    setAttributes={vi.fn()}
                />
                <SingleContentEdit
                    attributes={{ postId: 11, postType: 'packages' }}
                    setAttributes={vi.fn()}
                />
            </>
        );

        await waitFor(() => {
            expect(
                getAllByTestId('preview-context').map((node) => node.dataset.title)
            ).toEqual(['Hooks', 'Icons', 'Hooks']);
        });

        expect(getAllByTestId('preview-context')[0].dataset.content).toBe('<p>Hooks body</p>');
        expect(requestedUrls()).toEqual(['/visual-editor/api/query/resolve']);
        expect(resolveBodies()).toEqual([{ postType: 'package', postIn: [11, 12], perPage: 2 }]);
    });

    it('sends no preview request for a block with no entry picked', async () => {
        const { getByTestId } = render(
            <SingleContentEdit
                attributes={{ postId: 0, postType: 'package' }}
                setAttributes={vi.fn()}
            />
        );

        await Promise.resolve();

        expect(getByTestId('preview-context').dataset.title).toBe('');
        expect(getByTestId('preview-context').dataset.previewKey).toBe('unset');
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('masks an inherited preview with null while the picked entry is unresolved', async () => {
        const { getByTestId } = render(
            <SingleContentEdit
                attributes={{ postId: 13, postType: 'package' }}
                setAttributes={vi.fn()}
            />
        );

        expect(getByTestId('preview-context').dataset.previewKey).toBe('set');
        expect(getByTestId('preview-context').dataset.title).toBe('');

        await waitFor(() => expect(fetchMock).toHaveBeenCalled());
        expect(getByTestId('preview-context').dataset.previewKey).toBe('set');
    });

    it('does not load the picker list while the block is not selected', async () => {
        const { queryByTestId } = render(
            <SingleContentEdit
                attributes={{ postId: 11, postType: 'package' }}
                setAttributes={vi.fn()}
            />
        );

        await waitFor(() => expect(fetchMock).toHaveBeenCalled());

        expect(queryByTestId('picker')).toBeNull();
        expect(requestedUrls().some((url) => url.includes('/packages'))).toBe(false);
    });

    it('loads the picker list from the collection when selected', async () => {
        const { findByText } = render(
            <SingleContentEdit
                attributes={{ postId: 11, postType: 'package' }}
                setAttributes={vi.fn()}
                isSelected
            />
        );

        expect(await findByText('Hooks')).toBeTruthy();
        expect(requestedUrls()).toContain('/visual-editor/api/packages?per_page=50');
    });

    it('labels a selected record missing from the list with the preview title', async () => {
        const { findByText } = render(
            <SingleContentEdit
                attributes={{ postId: 99, postType: 'package' }}
                setAttributes={vi.fn()}
                isSelected
            />
        );

        expect(await findByText('Older package')).toBeTruthy();
        expect(requestedUrls().some((url) => url.includes('include='))).toBe(false);
    });

    it('shares one list request across selected blocks of the same type', async () => {
        const { findAllByText } = render(
            <>
                <SingleContentEdit
                    attributes={{ postId: 11, postType: 'package' }}
                    setAttributes={vi.fn()}
                    isSelected
                />
                <SingleContentEdit
                    attributes={{ postId: 0, postType: 'package' }}
                    setAttributes={vi.fn()}
                    isSelected
                />
            </>
        );

        await findAllByText('Hooks');

        expect(
            requestedUrls().filter((url) => url.includes('/packages?per_page=50'))
        ).toHaveLength(1);
    });
});

describe('records cache', () => {
    it('de-duplicates identical list requests, keyed on the search term', async () => {
        await Promise.all([
            fetchRecordList('packages', ''),
            fetchRecordList('packages', ''),
            fetchRecordList('packages', 'hoo'),
        ]);
        await fetchRecordList('packages', '');

        expect(requestedUrls()).toEqual([
            '/visual-editor/api/packages?per_page=50',
            '/visual-editor/api/packages?per_page=50&search=hoo',
        ]);
    });

    it('batches title lookups requested in the same tick into one include request', async () => {
        const labels = await Promise.all([
            fetchRecordLabel('packages', 11),
            fetchRecordLabel('packages', 13),
            fetchRecordLabel('packages', 12),
            fetchRecordLabel('packages', 11),
        ]);

        expect(labels).toEqual(['Hooks', null, 'Icons', 'Hooks']);
        expect(requestedUrls()).toEqual(['/visual-editor/api/packages?include=11%2C13%2C12']);
    });

    it('reuses titles from a loaded list without a lookup request', async () => {
        await fetchRecordList('packages', '');
        const label = await fetchRecordLabel('packages', 11);

        expect(label).toBe('Hooks');
        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('batches previews per content type and resolves missing records to null', async () => {
        const previews = await Promise.all([
            fetchRecordPreview('package', 11),
            fetchRecordPreview('package', 13),
            fetchRecordPreview('post', 5),
        ]);

        expect(previews.map((post) => post?.title ?? null)).toEqual(['Hooks', null, null]);
        expect(resolveBodies()).toEqual([
            { postType: 'package', postIn: [11, 13], perPage: 2 },
            { postType: 'post', postIn: [5], perPage: 1 },
        ]);

        await fetchRecordPreview('package', 11);
        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('caches a non-OK response instead of re-requesting it', async () => {
        fetchMock.mockImplementation(() =>
            Promise.resolve(jsonResponse({ message: 'Not Found' }, 404))
        );

        expect(await fetchRecordList('widgets', '')).toEqual([]);
        expect(await fetchRecordList('widgets', '')).toEqual([]);
        expect(await fetchRecordPreview('widget', 1)).toBeNull();
        expect(await fetchRecordPreview('widget', 1)).toBeNull();
        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('evicts a failed request so a later call can retry', async () => {
        fetchMock.mockRejectedValueOnce(new Error('offline'));

        await expect(fetchRecordPreview('package', 11)).rejects.toThrow('offline');
        expect((await fetchRecordPreview('package', 11))?.title).toBe('Hooks');
        expect(fetchMock).toHaveBeenCalledTimes(2);
    });
});
