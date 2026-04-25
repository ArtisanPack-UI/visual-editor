/**
 * Link-control picker tests.
 *
 * Covers the type switch (page/post/custom), debounced search, and
 * the "select a result" path. The custom-URL branch is exercised
 * separately so the search code-path is never reached for `custom`.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

import { LinkPicker } from '../link-picker';
import { makeMenuItem } from '../menu-tree';
import type { MenuItem } from '../menu-tree';
import type { SiteEditorApiConfig } from '../../api-client';

const API: SiteEditorApiConfig = { apiBase: '/visual-editor/api' };

beforeEach(() => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
});

afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    vi.useRealTimers();
});

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

function renderPicker(item: MenuItem) {
    const onChange = vi.fn();

    const utils = render(
        <LinkPicker apiConfig={API} item={item} onChange={onChange} />
    );

    return { ...utils, onChange };
}

describe('LinkPicker', () => {
    it('shows a URL field when the type is custom', () => {
        renderPicker(makeMenuItem({ type: 'custom', url: '/contact' }));

        expect(screen.getByTestId('ap-nav-link-picker-url')).toBeInTheDocument();
        expect(
            screen.queryByTestId('ap-nav-link-picker-query')
        ).not.toBeInTheDocument();
    });

    it('clears targetId when the user switches type away from a typed reference', () => {
        const fetchMock = vi.fn(
            async () => jsonResponse({ data: [] })
        );
        vi.stubGlobal('fetch', fetchMock);

        const { onChange } = renderPicker(
            makeMenuItem({ type: 'page', targetId: 12 })
        );

        fireEvent.change(screen.getByTestId('ap-nav-link-picker-type'), {
            target: { value: 'custom' },
        });

        expect(onChange).toHaveBeenCalledWith({
            type: 'custom',
            targetId: null,
            url: null,
        });
    });

    it('debounces a search and renders results', async () => {
        const fetchMock = vi.fn(async () =>
            jsonResponse({
                data: [
                    {
                        type: 'page',
                        id: 7,
                        title: 'About',
                        url: '/about',
                    },
                ],
            })
        );
        vi.stubGlobal('fetch', fetchMock);

        const { onChange } = renderPicker(
            makeMenuItem({ type: 'page', targetId: null })
        );

        fireEvent.change(screen.getByTestId('ap-nav-link-picker-query'), {
            target: { value: 'abo' },
        });

        // Pre-debounce, no fetch yet.
        expect(fetchMock).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(260);

        await waitFor(() =>
            expect(
                screen.getByTestId('ap-nav-link-picker-result-7')
            ).toBeInTheDocument()
        );

        fireEvent.click(screen.getByTestId('ap-nav-link-picker-result-7'));

        expect(onChange).toHaveBeenCalledWith(
            expect.objectContaining({
                targetId: 7,
                autoLabel: 'About',
                url: '/about',
            })
        );
    });
});
