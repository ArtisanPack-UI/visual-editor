import { render, screen, waitFor } from '@testing-library/react';
import { ToastProvider } from '@artisanpack-ui/react/feedback';
import { describe, expect, it } from 'vitest';

import { ApiError } from '../../api-client';
import { useComposedFallbackToast } from '../use-fallback-toast';
import type { AppliedTemplateState } from '../use-applied-template';

interface HarnessProps {
    viewMode: 'content' | 'with-template';
    state: AppliedTemplateState;
}

function Harness(props: HarnessProps): JSX.Element {
    useComposedFallbackToast(props);

    return <div data-testid="ap-composed-fallback-harness" />;
}

function renderHarness(props: HarnessProps) {
    const result = render(
        <ToastProvider>
            <Harness {...props} />
        </ToastProvider>
    );

    return {
        ...result,
        rerenderHarness: (next: HarnessProps): void => {
            result.rerender(
                <ToastProvider>
                    <Harness {...next} />
                </ToastProvider>
            );
        },
    };
}

const unknownSlug: AppliedTemplateState = {
    status: 'missing',
    missing: { status: 'missing', reason: 'unknown-slug', slug: 'landing-xl' },
};

const emptyTemplate: AppliedTemplateState = {
    status: 'missing',
    missing: { status: 'missing', reason: 'empty' },
};

const networkError: AppliedTemplateState = {
    status: 'error',
    error: new ApiError('boom', 500, null),
};

const unavailableCopy =
    'The template “landing-xl” is unavailable — previewing on the default template.';

describe('useComposedFallbackToast', () => {
    it('names the unavailable template when the slug does not resolve', async () => {
        const { rerenderHarness } = renderHarness({
            viewMode: 'content',
            state: { status: 'idle' },
        });

        rerenderHarness({ viewMode: 'with-template', state: unknownSlug });

        await waitFor(() => {
            expect(screen.getByText(unavailableCopy)).toBeInTheDocument();
        });
    });

    it('uses the no-template copy when the content has no template field', async () => {
        const { rerenderHarness } = renderHarness({
            viewMode: 'content',
            state: { status: 'idle' },
        });

        rerenderHarness({ viewMode: 'with-template', state: emptyTemplate });

        await waitFor(() => {
            expect(
                screen.getByText(
                    'No template is set for this content — previewing on the default template.'
                )
            ).toBeInTheDocument();
        });
    });

    it('announces a network error separately from a routine miss', async () => {
        const { rerenderHarness } = renderHarness({
            viewMode: 'content',
            state: { status: 'idle' },
        });

        rerenderHarness({ viewMode: 'with-template', state: networkError });

        await waitFor(() => {
            expect(
                screen.getByText(
                    'The template could not be loaded — previewing on the default template.'
                )
            ).toBeInTheDocument();
        });
    });

    it('fires exactly once per toggle-on event', async () => {
        const { rerenderHarness } = renderHarness({
            viewMode: 'content',
            state: { status: 'idle' },
        });

        rerenderHarness({ viewMode: 'with-template', state: { status: 'loading' } });
        rerenderHarness({ viewMode: 'with-template', state: unknownSlug });

        await waitFor(() => {
            expect(screen.getByText(unavailableCopy)).toBeInTheDocument();
        });

        // Re-renders while still toggled on must not stack a second toast.
        rerenderHarness({ viewMode: 'with-template', state: unknownSlug });
        rerenderHarness({ viewMode: 'with-template', state: unknownSlug });

        await waitFor(() => {
            expect(screen.getAllByText(unavailableCopy)).toHaveLength(1);
        });
    });

    it('announces a different failure without leaving composed mode', async () => {
        const { rerenderHarness } = renderHarness({
            viewMode: 'with-template',
            state: unknownSlug,
        });

        await waitFor(() => {
            expect(screen.getByText(unavailableCopy)).toBeInTheDocument();
        });

        // Author picks a second, also-broken template from the document
        // panel: a refetch, then a different miss. Silence here would read
        // as the template change having done nothing at all.
        rerenderHarness({
            viewMode: 'with-template',
            state: { status: 'loading' },
        });
        rerenderHarness({
            viewMode: 'with-template',
            state: {
                status: 'missing',
                missing: {
                    status: 'missing',
                    reason: 'unknown-slug',
                    slug: 'archive-wide',
                },
            },
        });

        await waitFor(() => {
            expect(
                screen.getByText(
                    'The template “archive-wide” is unavailable — previewing on the default template.'
                )
            ).toBeInTheDocument();
        });
    });

    it('announces again after the toggle is flipped off and back on', async () => {
        const { rerenderHarness } = renderHarness({
            viewMode: 'with-template',
            state: unknownSlug,
        });

        await waitFor(() => {
            expect(screen.getByText(unavailableCopy)).toBeInTheDocument();
        });

        rerenderHarness({ viewMode: 'content', state: unknownSlug });
        rerenderHarness({ viewMode: 'with-template', state: unknownSlug });

        // The first toast may still be on screen (auto-dismiss is on a
        // timer), so the second toggle-on shows as a second copy rather
        // than a replacement.
        await waitFor(() => {
            expect(
                screen.getAllByText(unavailableCopy).length
            ).toBeGreaterThan(1);
        });
    });

    it('stays silent when a real template resolves', async () => {
        const { rerenderHarness } = renderHarness({
            viewMode: 'content',
            state: { status: 'idle' },
        });

        rerenderHarness({
            viewMode: 'with-template',
            state: {
                status: 'ok',
                template: {
                    status: 'ok',
                    slug: 'single-post',
                    name: 'Single Post',
                    source: 'db',
                    blocks: [],
                    template_parts: {},
                },
            },
        });

        await waitFor(() => {
            expect(
                screen.getByTestId('ap-composed-fallback-harness')
            ).toBeInTheDocument();
        });

        expect(screen.queryByText(/previewing on the default template/)).toBe(
            null
        );
    });
});
