import { act, fireEvent, render, screen } from '@testing-library/react';
import { createRef } from 'react';
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
    PATTERN_PREVIEW_IN_VIEW_ROOT_MARGIN,
    PATTERN_PREVIEW_KEEP_MOUNTED_ROOT_MARGIN,
    PatternPreview,
    PatternPreviewScrollRootContext,
    inertProps,
    measurePreviewDocument,
    patternViewportWidth,
} from '../pattern-preview';
import {
    PATTERN_PREVIEW_BATCH_DELAY,
    resetPatternPreviewLoaders,
} from '../pattern-preview-loader';
import {
    PATTERN_PREVIEW_MOUNTS_PER_FRAME,
    resetPreviewMountQueue,
} from '../pattern-preview-mount-queue';

type ObserverCallback = (entries: Array<{ isIntersecting: boolean }>) => void;

interface ObserverRecord {
    readonly callback: ObserverCallback;
    readonly options: IntersectionObserverInit | undefined;
    readonly targets: Element[];
}

let observerCallbacks: ObserverCallback[] = [];
let observers: ObserverRecord[] = [];

class FakeIntersectionObserver {
    private readonly record: ObserverRecord;

    constructor(
        private readonly callback: ObserverCallback,
        options?: IntersectionObserverInit
    ) {
        observerCallbacks.push(callback);
        this.record = { callback, options, targets: [] };
        observers.push(this.record);
    }

    observe(target: Element): void {
        this.record.targets.push(target);
    }

    disconnect(): void {
        observerCallbacks = observerCallbacks.filter((cb) => cb !== this.callback);
        observers = observers.filter((record) => record !== this.record);
    }
}

function scrollAllIntoView(): void {
    for (const callback of [...observerCallbacks]) {
        callback([{ isIntersecting: true }]);
    }
}

/** Reports every keep-mounted observer for `target` as (not) intersecting. */
function reportNear(target: Element, isIntersecting: boolean): void {
    for (const record of [...observers]) {
        if (
            record.options?.rootMargin === PATTERN_PREVIEW_KEEP_MOUNTED_ROOT_MARGIN &&
            record.targets.some((observed) => observed === target || target.contains(observed))
        ) {
            record.callback([{ isIntersecting }]);
        }
    }
}

/** One animation frame of the stubbed `requestAnimationFrame`. */
const FRAME_MS = 16;

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
    // Let the mount queue hand out a frame.
    await advanceFrames(1);
}

async function advanceFrames(count: number): Promise<void> {
    await act(async () => {
        await vi.advanceTimersByTimeAsync(FRAME_MS * count);
    });
}

describe('PatternPreview', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        observerCallbacks = [];
        observers = [];
        resetPatternPreviewLoaders();
        resetPreviewMountQueue();
        PREVIEW_MOCK.mockReset();
        vi.stubGlobal('IntersectionObserver', FakeIntersectionObserver);
        vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) =>
            setTimeout(() => callback(performance.now()), FRAME_MS)
        );
        vi.stubGlobal('cancelAnimationFrame', (handle: number) => clearTimeout(handle));
    });

    afterEach(() => {
        resetPreviewMountQueue();
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
        expect(PREVIEW_MOCK).toHaveBeenCalledWith(
            { apiBase: '/api' },
            ['7'],
            expect.objectContaining({ signal: expect.anything() })
        );
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

describe('PatternPreview performance and sizing (FE-1 · FE-2 · FE-3)', () => {
    let resizeObservers: Array<{ target: Element | null; disconnected: boolean; callback: () => void }> = [];

    class FakeResizeObserver {
        private readonly record: { target: Element | null; disconnected: boolean; callback: () => void };

        constructor(callback: () => void) {
            this.record = { target: null, disconnected: false, callback };
            resizeObservers.push(this.record);
        }

        observe(target: Element): void {
            this.record.target = target;
        }

        disconnect(): void {
            this.record.disconnected = true;
        }
    }

    beforeEach(() => {
        vi.useFakeTimers();
        observerCallbacks = [];
        observers = [];
        resizeObservers = [];
        resetPatternPreviewLoaders();
        resetPreviewMountQueue();
        PREVIEW_MOCK.mockReset();
        vi.stubGlobal('IntersectionObserver', FakeIntersectionObserver);
        vi.stubGlobal('ResizeObserver', FakeResizeObserver);
        vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) =>
            setTimeout(() => callback(performance.now()), FRAME_MS)
        );
        vi.stubGlobal('cancelAnimationFrame', (handle: number) => clearTimeout(handle));
    });

    afterEach(() => {
        resetPreviewMountQueue();
        vi.restoreAllMocks();
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    function batchFor(count: number): { styles: string; patterns: Record<string, { html: string }> } {
        return {
            styles: '',
            patterns: Object.fromEntries(
                Array.from({ length: count }, (_, i) => [String(i + 1), { html: `<p>${i + 1}</p>` }])
            ),
        };
    }

    it('mounts at most N iframes per frame', async () => {
        PREVIEW_MOCK.mockResolvedValue(batchFor(30));

        render(
            <>
                {Array.from({ length: 30 }, (_, i) => (
                    <PatternPreview
                        key={i}
                        pattern={makePattern({ id: i + 1 })}
                        title={`P${i + 1}`}
                        apiBase="/api"
                    />
                ))}
            </>
        );

        act(() => scrollAllIntoView());

        await act(async () => {
            await vi.advanceTimersByTimeAsync(PATTERN_PREVIEW_BATCH_DELAY);
        });

        // Two chunks (24 + 6) resolved, but nothing mounted yet.
        expect(document.querySelectorAll('iframe')).toHaveLength(0);

        await advanceFrames(1);
        expect(document.querySelectorAll('iframe')).toHaveLength(PATTERN_PREVIEW_MOUNTS_PER_FRAME);

        await advanceFrames(1);
        expect(document.querySelectorAll('iframe')).toHaveLength(PATTERN_PREVIEW_MOUNTS_PER_FRAME * 2);

        await advanceFrames(10);
        expect(document.querySelectorAll('iframe')).toHaveLength(30);
    });

    it('unmounts the iframe when the card scrolls far away and keeps its height', async () => {
        PREVIEW_MOCK.mockResolvedValue({ styles: '', patterns: { '7': { html: '<p>Far</p>' } } });

        // jsdom lays everything out at 0px; give the card a width so it
        // has a height to preserve.
        vi.spyOn(HTMLElement.prototype, 'clientWidth', 'get').mockReturnValue(300);

        render(<PatternPreview pattern={makePattern()} title="Hero" apiBase="/api" />);

        act(() => scrollAllIntoView());
        await flushBatch();

        const slot = screen.getByTestId('ap-pattern-preview-slot');

        expect(slot.querySelector('iframe')).not.toBeNull();

        const heightWhileMounted = screen.getByTestId('ap-pattern-preview').style.height;

        expect(heightWhileMounted).toBe('225px');

        act(() => reportNear(slot, false));

        expect(slot.querySelector('iframe')).toBeNull();
        expect(slot).toHaveAttribute('data-preview-state', 'parked');
        expect(screen.getByTestId('ap-pattern-preview-parked').style.height).toBe(heightWhileMounted);

        act(() => reportNear(slot, true));
        await advanceFrames(1);

        expect(slot.querySelector('iframe')).not.toBeNull();
        expect(slot).toHaveAttribute('data-preview-state', 'rendered');
        // Remounting reuses the cached preview — no new request.
        expect(PREVIEW_MOCK).toHaveBeenCalledTimes(1);
        vi.restoreAllMocks();
    });

    it('observes against the scroll root from context', async () => {
        const rootRef = createRef<HTMLDivElement>();

        render(
            <div ref={rootRef}>
                <PatternPreviewScrollRootContext.Provider value={rootRef}>
                    <PatternPreview pattern={makePattern()} title="Hero" apiBase="/api" />
                </PatternPreviewScrollRootContext.Provider>
            </div>
        );

        expect(observers).toHaveLength(1);
        expect(observers[0]?.options?.root).toBe(rootRef.current);
        expect(observers[0]?.options?.rootMargin).toBe(PATTERN_PREVIEW_IN_VIEW_ROOT_MARGIN);
    });

    it('prefers an explicit scrollRoot prop', () => {
        const root = document.createElement('div');

        render(<PatternPreview pattern={makePattern()} title="Hero" apiBase="/api" scrollRoot={root} />);

        expect(observers[0]?.options?.root).toBe(root);
    });

    it('observes against the viewport without a scroll root', () => {
        render(<PatternPreview pattern={makePattern()} title="Hero" apiBase="/api" />);

        expect(observers[0]?.options?.root).toBeNull();
    });

    async function renderLoadedPreview(documentHeight: { current: number }) {
        // jsdom lays everything out at 0px; a 300px card scales the
        // 1200px layout by 0.25 and caps the preview at 225px.
        vi.spyOn(HTMLElement.prototype, 'clientWidth', 'get').mockReturnValue(300);

        const view = render(<PatternPreview pattern={makePattern()} title="Hero" apiBase="/api" />);

        act(() => scrollAllIntoView());
        await flushBatch();

        const iframe = document.querySelector('iframe') as HTMLIFrameElement;
        const doc = iframe.contentDocument as Document;

        Object.defineProperty(doc.documentElement, 'scrollHeight', {
            configurable: true,
            get: () => documentHeight.current,
        });
        Object.defineProperty(doc.body, 'scrollHeight', { configurable: true, value: 100 });

        act(() => {
            fireEvent.load(iframe);
        });

        return { ...view, iframe, doc };
    }

    it('measures with documentElement and re-measures through a ResizeObserver', async () => {
        PREVIEW_MOCK.mockResolvedValue({ styles: '', patterns: { '7': { html: '<p>Tall</p>' } } });

        const documentHeight = { current: 600 };
        const { doc } = await renderLoadedPreview(documentHeight);

        // documentElement (600) wins over body (100): 600 × 0.25.
        expect(screen.getByTestId('ap-pattern-preview').style.height).toBe('150px');
        expect(measurePreviewDocument(doc)).toBe(600);

        const contentObserver = resizeObservers.filter((record) => record.target === doc.documentElement).pop();

        expect(contentObserver).toBeDefined();

        documentHeight.current = 800;
        act(() => contentObserver?.callback());

        expect(screen.getByTestId('ap-pattern-preview').style.height).toBe('200px');
    });

    it('resets the height to the cap when a new result arrives, until it loads', async () => {
        PREVIEW_MOCK.mockResolvedValueOnce({ styles: '', patterns: { '7': { html: '<p>v1</p>' } } });

        const documentHeight = { current: 600 };
        const { rerender } = await renderLoadedPreview(documentHeight);

        expect(screen.getByTestId('ap-pattern-preview').style.height).toBe('150px');

        PREVIEW_MOCK.mockResolvedValueOnce({ styles: '', patterns: { '7': { html: '<p>v2</p>' } } });

        rerender(
            <PatternPreview
                pattern={makePattern({ content: { raw: '<p>edited</p>', blocks: [] } })}
                title="Hero"
                apiBase="/api"
            />
        );

        await flushBatch();

        const iframe = document.querySelector('iframe') as HTMLIFrameElement;

        expect(iframe.getAttribute('srcdoc')).toContain('<p>v2</p>');
        expect(screen.getByTestId('ap-pattern-preview').style.height).toBe('225px');
    });

    it('disconnects the content observer on unmount', async () => {
        PREVIEW_MOCK.mockResolvedValue({ styles: '', patterns: { '7': { html: '<p>x</p>' } } });

        const { unmount } = render(<PatternPreview pattern={makePattern()} title="Hero" apiBase="/api" />);

        act(() => scrollAllIntoView());
        await flushBatch();

        const iframe = document.querySelector('iframe') as HTMLIFrameElement;
        const doc = iframe.contentDocument as Document;

        Object.defineProperty(doc.documentElement, 'scrollHeight', { configurable: true, value: 400 });
        act(() => {
            fireEvent.load(iframe);
        });

        const contentObserver = resizeObservers.filter((record) => record.target === doc.documentElement).pop();

        expect(contentObserver?.disconnected).toBe(false);

        unmount();

        expect(contentObserver?.disconnected).toBe(true);
    });
});

describe('inertProps (FE-10a)', () => {
    it('uses the string form React 18 needs to render the attribute', () => {
        expect(inertProps('18.3.1')).toEqual({ inert: '' });
    });

    it('uses the boolean form React 19 expects', () => {
        expect(inertProps('19.0.0')).toEqual({ inert: true });
        expect(inertProps('20.1.0')).toEqual({ inert: true });
    });

    it('falls back to the string form for an unparseable version', () => {
        expect(inertProps('canary')).toEqual({ inert: '' });
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
