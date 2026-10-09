import { act, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const PREVIEW_MOCK = vi.fn();

vi.mock('../api-client', async () => {
    const actual =
        await vi.importActual<typeof import('../api-client')>('../api-client');

    return {
        ...actual,
        previewPatterns: (...args: unknown[]) => PREVIEW_MOCK(...args),
    };
});

vi.mock('@wordpress/blocks', () => ({
    parse: () => [{ name: 'core/paragraph', innerBlocks: [] }],
}));

import type { PatternRecord } from '../api-client';
import {
    DEFAULT_PATTERN_VIEWPORT_WIDTH,
    MAX_PATTERN_VIEWPORT_WIDTH,
    MIN_PATTERN_VIEWPORT_WIDTH,
    PatternPreview,
    patternViewportWidth,
} from '../pattern-preview';
import {
    PATTERN_PREVIEW_BATCH_DELAY,
    resetPatternPreviewLoaders,
} from '../pattern-preview-loader';

type ObserverCallback = (entries: Array<{ isIntersecting: boolean }>) => void;

let observerCallbacks: ObserverCallback[] = [];

class FakeIntersectionObserver {
    constructor(private readonly callback: ObserverCallback) {
        observerCallbacks.push(callback);
    }

    observe(): void {}

    disconnect(): void {
        observerCallbacks = observerCallbacks.filter((cb) => cb !== this.callback);
    }
}

function scrollAllIntoView(): void {
    for (const callback of [...observerCallbacks]) {
        callback([{ isIntersecting: true }]);
    }
}

function makePattern(overrides: Partial<PatternRecord> = {}): PatternRecord {
    return {
        id: 7,
        slug: 'hero',
        title: { rendered: 'Hero', raw: 'Hero' },
        content: {
            raw: '<!-- wp:paragraph --><p>Hero</p><!-- /wp:paragraph -->',
            blocks: [],
        },
        synced: false,
        categories: [],
        status: 'publish',
        type: 'wp_block',
        ...overrides,
    };
}

async function flushBatch(): Promise<void> {
    await act(async () => {
        await vi.advanceTimersByTimeAsync(PATTERN_PREVIEW_BATCH_DELAY);
    });
}

describe('PatternPreview', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        observerCallbacks = [];
        resetPatternPreviewLoaders();
        PREVIEW_MOCK.mockReset();
        vi.stubGlobal('IntersectionObserver', FakeIntersectionObserver);
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('shows the block-name tree and sends nothing until the card is in view', async () => {
        render(<PatternPreview pattern={makePattern()} title="Hero" apiBase="/api" />);

        await flushBatch();

        expect(screen.getByTestId('ap-pattern-thumb')).toBeInTheDocument();
        expect(PREVIEW_MOCK).not.toHaveBeenCalled();
    });

    it('renders the server HTML in a sandboxed, non-interactive iframe once in view', async () => {
        PREVIEW_MOCK.mockResolvedValue({
            styles: '<style>.x{}</style>',
            patterns: { '7': { html: '<section>Rendered hero</section>' } },
        });

        render(<PatternPreview pattern={makePattern()} title="Hero" apiBase="/api" />);

        act(() => scrollAllIntoView());
        await flushBatch();

        const slot = screen.getByTestId('ap-pattern-preview-slot');
        const iframe = slot.querySelector('iframe');

        expect(slot).toHaveAttribute('data-preview-state', 'rendered');
        expect(slot).toHaveAttribute('aria-hidden', 'true');
        expect(slot).toHaveAttribute('inert');
        expect(iframe).not.toBeNull();
        expect(iframe).toHaveAttribute('sandbox', 'allow-same-origin');
        expect(iframe?.getAttribute('sandbox')).not.toContain('allow-scripts');
        expect(iframe).toHaveAttribute('tabindex', '-1');
        expect(iframe?.getAttribute('src')).toBeNull();
        expect(iframe?.getAttribute('srcdoc')).toContain('<section>Rendered hero</section>');
        expect(iframe?.getAttribute('srcdoc')).toContain('<style>.x{}</style>');
        expect(PREVIEW_MOCK).toHaveBeenCalledWith({ apiBase: '/api' }, ['7']);
    });

    it('batches every card that comes into view into one request', async () => {
        PREVIEW_MOCK.mockResolvedValue({ styles: '', patterns: {} });

        render(
            <>
                <PatternPreview pattern={makePattern({ id: 1 })} title="A" apiBase="/api" />
                <PatternPreview pattern={makePattern({ id: 2 })} title="B" apiBase="/api" />
                <PatternPreview pattern={makePattern({ id: 3 })} title="C" apiBase="/api" />
            </>
        );

        act(() => scrollAllIntoView());
        await flushBatch();

        expect(PREVIEW_MOCK).toHaveBeenCalledTimes(1);
        expect(PREVIEW_MOCK.mock.calls[0]?.[1]).toEqual(['1', '2', '3']);
    });

    it('keeps the tree fallback when the pattern fails to render', async () => {
        PREVIEW_MOCK.mockResolvedValue({
            styles: '',
            patterns: { '7': { error: 'render_failed' } },
        });

        render(<PatternPreview pattern={makePattern()} title="Hero" apiBase="/api" />);

        act(() => scrollAllIntoView());
        await flushBatch();

        expect(screen.getByTestId('ap-pattern-preview-slot')).toHaveAttribute(
            'data-preview-state',
            'fallback'
        );
        expect(screen.getByTestId('ap-pattern-thumb')).toBeInTheDocument();
    });

    it('keeps the tree fallback on a network error', async () => {
        PREVIEW_MOCK.mockRejectedValue(new Error('Forbidden'));

        render(<PatternPreview pattern={makePattern()} title="Hero" apiBase="/api" />);

        act(() => scrollAllIntoView());
        await flushBatch();

        expect(screen.getByTestId('ap-pattern-thumb')).toBeInTheDocument();
        expect(document.querySelector('iframe')).toBeNull();
    });

    it('shows the empty placeholder and sends nothing for an empty pattern', async () => {
        render(
            <PatternPreview
                pattern={makePattern({ content: { raw: '', blocks: [] } })}
                title="Empty"
                apiBase="/api"
            />
        );

        act(() => scrollAllIntoView());
        await flushBatch();

        expect(screen.getByTestId('ap-pattern-thumb-empty')).toBeInTheDocument();
        expect(PREVIEW_MOCK).not.toHaveBeenCalled();
    });

    it('sends nothing without an API base', async () => {
        render(<PatternPreview pattern={makePattern()} title="Hero" />);

        act(() => scrollAllIntoView());
        await flushBatch();

        expect(screen.getByTestId('ap-pattern-thumb')).toBeInTheDocument();
        expect(PREVIEW_MOCK).not.toHaveBeenCalled();
    });

    it('sends nothing where IntersectionObserver is unavailable', async () => {
        vi.stubGlobal('IntersectionObserver', undefined);

        render(<PatternPreview pattern={makePattern()} title="Hero" apiBase="/api" />);

        await flushBatch();

        expect(screen.getByTestId('ap-pattern-thumb')).toBeInTheDocument();
        expect(PREVIEW_MOCK).not.toHaveBeenCalled();
    });

    it('lays the frame out at the pattern viewport width', async () => {
        PREVIEW_MOCK.mockResolvedValue({
            styles: '',
            patterns: { '7': { html: '<p>Wide</p>' } },
        });

        render(
            <PatternPreview
                pattern={makePattern({ viewport_width: 1400 })}
                title="Wide"
                apiBase="/api"
            />
        );

        act(() => scrollAllIntoView());
        await flushBatch();

        expect(screen.getByTestId('ap-pattern-preview')).toHaveAttribute(
            'data-viewport-width',
            '1400'
        );
        expect(document.querySelector('iframe')?.style.width).toBe('1400px');
    });
});

describe('patternViewportWidth', () => {
    const base = makePattern();

    it.each([
        [undefined, DEFAULT_PATTERN_VIEWPORT_WIDTH],
        [null, DEFAULT_PATTERN_VIEWPORT_WIDTH],
        [0, DEFAULT_PATTERN_VIEWPORT_WIDTH],
        [-500, DEFAULT_PATTERN_VIEWPORT_WIDTH],
        [Number.NaN, DEFAULT_PATTERN_VIEWPORT_WIDTH],
        [1400, 1400],
        [100, MIN_PATTERN_VIEWPORT_WIDTH],
        [9000, MAX_PATTERN_VIEWPORT_WIDTH],
    ])('maps %s to %s', (declared, expected) => {
        expect(
            patternViewportWidth({ ...base, viewport_width: declared as number | null | undefined })
        ).toBe(expected);
    });
});
