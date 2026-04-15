import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { EditorBoot } from '../EditorBoot';
import {
    clearRegistry,
    registerBlock,
    type BlockEditProps,
} from '../../registry';

function Paragraph({ attributes, clientId }: BlockEditProps) {
    return <p data-client-id={clientId}>{String(attributes.content ?? '')}</p>;
}

const originalFetch = globalThis.fetch;

beforeEach(() => {
    clearRegistry();
    registerBlock({ name: 'core/paragraph', edit: Paragraph });
});

afterEach(() => {
    clearRegistry();
    globalThis.fetch = originalFetch;
    vi.restoreAllMocks();
});

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

describe('EditorBoot', () => {
    it('fetches the post on mount and renders the editor', async () => {
        const payload = {
            id: 1,
            title: 'Test',
            blocks: [
                {
                    clientId: 'loaded-1',
                    name: 'core/paragraph',
                    attributes: { content: 'Loaded from REST' },
                    innerBlocks: [],
                },
            ],
            updated_at: '2026-04-14T12:00:00+00:00',
        };

        globalThis.fetch = vi
            .fn()
            .mockResolvedValue(jsonResponse(payload)) as unknown as typeof fetch;

        render(
            <EditorBoot postId="1" postType="post" apiBase="/visual-editor/api" />
        );

        expect(screen.getByText('Loading editor…')).toBeInTheDocument();

        await waitFor(() => {
            expect(screen.getByText('Loaded from REST')).toBeInTheDocument();
        });

        expect(document.querySelector('[data-ve-editor-shell]')).not.toBeNull();
        expect(globalThis.fetch).toHaveBeenCalledWith(
            '/visual-editor/api/posts/1',
            expect.objectContaining({ method: 'GET' })
        );
    });

    it('renders the error state with a retry button on load failure', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(
                new Response(JSON.stringify({ message: 'Nope' }), { status: 500 })
            )
            .mockResolvedValueOnce(
                jsonResponse({
                    id: 1,
                    title: 'Test',
                    blocks: [],
                    updated_at: '2026-04-14T12:00:00+00:00',
                })
            );

        globalThis.fetch = fetchMock as unknown as typeof fetch;

        render(
            <EditorBoot postId="1" postType="post" apiBase="/visual-editor/api" />
        );

        await waitFor(() => {
            expect(screen.getByRole('alert')).toBeInTheDocument();
        });

        expect(screen.getByText(/Failed to load the post/)).toBeInTheDocument();

        const user = userEvent.setup();
        await user.click(screen.getByRole('button', { name: /retry/i }));

        await waitFor(() => {
            expect(document.querySelector('[data-ve-editor-shell]')).not.toBeNull();
        });

        expect(fetchMock).toHaveBeenCalledTimes(2);
    });
});
