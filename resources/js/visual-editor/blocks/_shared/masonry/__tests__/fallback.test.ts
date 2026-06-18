/**
 * Issue #593 — JS fallback for the shared masonry layout.
 *
 * Exercises the `initMasonry` controller against a jsdom-backed
 * container. jsdom returns `0` for layout-derived measurements
 * (`offsetHeight`, `clientWidth`) by default, so we stub those onto the
 * elements before each layout pass to assert the shortest-column-first
 * packing math.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import {
    initMasonry,
    supportsNativeMasonry,
} from '../fallback';

let supportsImpl: ((property: string, value?: string) => boolean) | null = null;

function stubCssSupports(returnValue: boolean | ((property: string, value?: string) => boolean)): void {
    supportsImpl = typeof returnValue === 'function' ? returnValue : (): boolean => returnValue;
}

function installCssShim(): void {
    const cssLike = {
        supports(property: string, value?: string): boolean {
            return supportsImpl ? supportsImpl(property, value) : false;
        },
    };
    // jsdom does not implement CSS.supports — install a writable shim so
    // each test can dictate the answer via stubCssSupports().
    Object.defineProperty(window, 'CSS', {
        configurable: true,
        writable: true,
        value: cssLike,
    });
}

interface MeasuredItem {
    el: HTMLDivElement;
    height: number;
    span?: number;
}

function makeContainer(
    items: ReadonlyArray<{ height: number; span?: number }>,
    containerWidth = 300,
): { container: HTMLDivElement; measured: MeasuredItem[] } {
    const container = document.createElement('div');
    Object.defineProperty(container, 'clientWidth', {
        configurable: true,
        value: containerWidth,
    });
    const measured: MeasuredItem[] = [];
    for (const spec of items) {
        const el = document.createElement('div');
        Object.defineProperty(el, 'offsetHeight', {
            configurable: true,
            value: spec.height,
        });
        if (spec.span !== undefined) {
            el.setAttribute('data-ap-col-span', String(spec.span));
        }
        container.appendChild(el);
        measured.push({ el, height: spec.height, span: spec.span });
    }
    document.body.appendChild(container);
    return { container, measured };
}

beforeEach(() => {
    // Reset the cached native-support flag so each test starts from a
    // clean detection — different tests stub `CSS.supports` differently.
    delete (window as unknown as { __apMasonrySupports?: boolean }).__apMasonrySupports;
    document.body.innerHTML = '';
    supportsImpl = null;
    installCssShim();
});

afterEach(() => {
    vi.restoreAllMocks();
});

describe('supportsNativeMasonry', () => {
    it('returns true when CSS.supports reports grid-template-rows: masonry', () => {
        stubCssSupports(true);
        expect(supportsNativeMasonry()).toBe(true);
    });

    it('returns false when CSS.supports rejects the rule', () => {
        stubCssSupports(false);
        expect(supportsNativeMasonry()).toBe(false);
    });

    it('caches the detection result for subsequent calls', () => {
        let callCount = 0;
        stubCssSupports(() => {
            callCount += 1;
            return true;
        });
        expect(supportsNativeMasonry()).toBe(true);
        expect(supportsNativeMasonry()).toBe(true);
        expect(callCount).toBe(1);
    });
});

describe('initMasonry — native path', () => {
    it('returns a no-op controller when native masonry is supported and forceFallback is false', () => {
        stubCssSupports(true);

        const { container } = makeContainer([
            { height: 100 },
            { height: 200 },
        ]);

        const controller = initMasonry(container, { columns: 2 });

        // The fallback class is only stamped on the fallback path.
        expect(container.classList.contains('ap-masonry-js-fallback')).toBe(false);
        // Items keep their original positioning (no absolute positioning).
        expect(container.firstElementChild?.classList.contains('ap-masonry-item')).toBe(false);
        // The controller is callable but does nothing.
        expect(() => controller.relayout()).not.toThrow();
        expect(() => controller.destroy()).not.toThrow();
    });

    it('runs the fallback path when forceFallback is true even with native support', () => {
        stubCssSupports(true);

        const { container } = makeContainer([
            { height: 100 },
        ]);

        initMasonry(container, { columns: 2, forceFallback: true });

        expect(container.classList.contains('ap-masonry-js-fallback')).toBe(true);
    });
});

describe('initMasonry — fallback packing', () => {
    beforeEach(() => {
        stubCssSupports(false);
    });

    it('positions items into the shortest column first', () => {
        const { container, measured } = makeContainer(
            [
                { height: 100 }, // → col 0 (height: 100)
                { height: 200 }, // → col 1 (height: 200)
                { height: 50 },  // → col 0 (shorter): top: 100
                { height: 75 },  // → col 0 still shorter than col 1: top: 150
            ],
            300,
        );

        initMasonry(container, { columns: 2, gap: 0, forceFallback: true });

        // Column 0: items at top 0 and top 100.
        // Column 1: item at top 0.
        // 4th item lands in col 0 since col 0 total (150) < col 1 (200).
        expect(measured[0].el.style.left).toBe('0px');
        expect(measured[0].el.style.top).toBe('0px');

        expect(measured[1].el.style.left).toBe('150px');
        expect(measured[1].el.style.top).toBe('0px');

        expect(measured[2].el.style.left).toBe('0px');
        expect(measured[2].el.style.top).toBe('100px');

        expect(measured[3].el.style.left).toBe('0px');
        expect(measured[3].el.style.top).toBe('150px');
    });

    it('honors data-ap-col-span for wider items, skipping under-occupied columns', () => {
        const { container, measured } = makeContainer(
            [
                { height: 50 }, // single-span → col 0 (height 50)
                { height: 50 }, // single-span → col 1 (height 50)
                { height: 50 }, // single-span → col 2 (height 50)
                { height: 100, span: 2 }, // 2-span → starts at col 0 (slot with min max)
            ],
            300,
        );

        initMasonry(container, { columns: 3, gap: 0, forceFallback: true });

        // Each column is 100px wide.
        expect(measured[0].el.style.width).toBe('100px');
        expect(measured[3].el.style.width).toBe('200px');
        // 2-span item lands at column 0 top after the row of three 50px items.
        expect(measured[3].el.style.left).toBe('0px');
        expect(measured[3].el.style.top).toBe('50px');
    });

    it('reads the column count from data-ap-cols when no option is passed', () => {
        const { container, measured } = makeContainer(
            [
                { height: 50 },
                { height: 50 },
            ],
            400,
        );
        container.setAttribute('data-ap-cols', '4');

        initMasonry(container, { gap: 0, forceFallback: true });

        // Each of 4 columns is 100px wide.
        expect(measured[0].el.style.width).toBe('100px');
        expect(measured[1].el.style.left).toBe('100px');
    });

    it('clamps column counts outside 1-12 to safe defaults', () => {
        const { container } = makeContainer(
            [
                { height: 10 },
                { height: 10 },
                { height: 10 },
            ],
            300,
        );

        const controller = initMasonry(container, {
            columns: 99,
            gap: 0,
            forceFallback: true,
        });

        // 99 clamps to 12 columns → each column is 25px wide.
        const firstItem = container.firstElementChild as HTMLElement;
        expect(firstItem.style.width).toBe('25px');

        controller.destroy();
    });

    it('updates the container height to the tallest packed column', () => {
        const { container } = makeContainer(
            [
                { height: 100 },
                { height: 200 },
            ],
            300,
        );

        initMasonry(container, { columns: 2, gap: 0, forceFallback: true });

        expect(container.style.height).toBe('200px');
    });

    it('relayouts when relayout() is called after a child height changes', () => {
        const { container, measured } = makeContainer(
            [
                { height: 100 },
                { height: 200 },
            ],
            300,
        );

        const controller = initMasonry(container, {
            columns: 2,
            gap: 0,
            forceFallback: true,
        });

        // Simulate the second item growing taller.
        Object.defineProperty(measured[1].el, 'offsetHeight', {
            configurable: true,
            value: 400,
        });

        controller.relayout();

        expect(container.style.height).toBe('400px');
    });

    it('stamps the wrapper + item classes and restores them on destroy', () => {
        const { container, measured } = makeContainer(
            [
                { height: 100 },
                { height: 200 },
            ],
            300,
        );

        const controller = initMasonry(container, {
            columns: 2,
            gap: 0,
            forceFallback: true,
        });

        expect(container.classList.contains('ap-masonry-js-fallback')).toBe(true);
        for (const item of measured) {
            expect(item.el.classList.contains('ap-masonry-item')).toBe(true);
            expect(item.el.style.position).toBe('absolute');
        }

        controller.destroy();

        expect(container.classList.contains('ap-masonry-js-fallback')).toBe(false);
        for (const item of measured) {
            expect(item.el.classList.contains('ap-masonry-item')).toBe(false);
            expect(item.el.style.position).toBe('');
        }
    });

    it('detects and re-applies layout on subsequent calls (idempotent)', () => {
        const { container, measured } = makeContainer(
            [
                { height: 100 },
                { height: 200 },
            ],
            300,
        );

        const controller = initMasonry(container, {
            columns: 2,
            gap: 0,
            forceFallback: true,
        });

        const firstTop = measured[0].el.style.top;
        controller.relayout();
        expect(measured[0].el.style.top).toBe(firstTop);

        controller.destroy();
    });

    it('reads gridColumnSpan from grid-item span classes when no data attribute is set', () => {
        const container = document.createElement('div');
        Object.defineProperty(container, 'clientWidth', { configurable: true, value: 300 });

        const item = document.createElement('div');
        item.classList.add('ap-grid-item-span-3-base-columns');
        Object.defineProperty(item, 'offsetHeight', { configurable: true, value: 100 });
        container.appendChild(item);
        document.body.appendChild(container);

        initMasonry(container, { columns: 3, gap: 0, forceFallback: true });

        // 3-span item gets full 300px width.
        expect(item.style.width).toBe('300px');
    });
});
