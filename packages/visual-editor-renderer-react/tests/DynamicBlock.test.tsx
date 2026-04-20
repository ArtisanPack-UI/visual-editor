import { render, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { DynamicBlock } from '../src/DynamicBlock';

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

describe('DynamicBlock', () => {
    it('renders HTML returned by the preview endpoint', async () => {
        const fetchFn = vi
            .fn()
            .mockResolvedValue(jsonResponse({ name: 'acme/live', html: '<aside>Live!</aside>' }));

        const { container } = render(
            <DynamicBlock
                name="acme/live"
                attributes={{ title: 'Hi' }}
                endpoint="/visual-editor/api/blocks/preview"
                fetchFn={fetchFn as unknown as typeof fetch}
            />
        );

        await waitFor(() => {
            expect(container.innerHTML).toContain('<aside>Live!</aside>');
        });

        expect(fetchFn).toHaveBeenCalledOnce();
        const [url, init] = fetchFn.mock.calls[0];
        expect(url).toBe('/visual-editor/api/blocks/preview');
        expect(init?.method).toBe('POST');
        expect(init?.body).toBe(JSON.stringify({ name: 'acme/live', attributes: { title: 'Hi' } }));
    });

    it('renders the unknown-block marker when the endpoint 404s', async () => {
        const fetchFn = vi
            .fn()
            .mockResolvedValue(jsonResponse({ error: 'block_not_registered' }, 404));

        const { container } = render(
            <DynamicBlock
                name="acme/missing"
                attributes={{}}
                endpoint="/visual-editor/api/blocks/preview"
                fetchFn={fetchFn as unknown as typeof fetch}
            />
        );

        await waitFor(() => {
            expect(container.innerHTML).toContain('data-ve-unknown-block="acme/missing"');
        });
    });

    it('renders the unknown-block marker when the fetch rejects', async () => {
        const fetchFn = vi.fn().mockRejectedValue(new TypeError('network error'));

        const { container } = render(
            <DynamicBlock
                name="acme/broken"
                attributes={{}}
                endpoint="/visual-editor/api/blocks/preview"
                fetchFn={fetchFn as unknown as typeof fetch}
            />
        );

        await waitFor(() => {
            expect(container.innerHTML).toContain('data-ve-unknown-block="acme/broken"');
        });
    });
});
