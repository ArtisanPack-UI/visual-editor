import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import {
    PATTERN_PREVIEW_MOUNTS_PER_FRAME,
    pendingPreviewMounts,
    resetPreviewMountQueue,
    schedulePreviewMount,
} from '../pattern-preview-mount-queue';

describe('schedulePreviewMount (FE-1)', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) =>
            setTimeout(() => callback(0), 16)
        );
        vi.stubGlobal('cancelAnimationFrame', (handle: number) => clearTimeout(handle));
        resetPreviewMountQueue();
    });

    afterEach(() => {
        resetPreviewMountQueue();
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('runs at most N mounts per frame, in request order', () => {
        const order: number[] = [];

        for (let i = 0; i < 10; i++) {
            schedulePreviewMount(() => order.push(i));
        }

        expect(order).toEqual([]);

        vi.advanceTimersByTime(16);
        expect(order).toEqual([0, 1, 2, 3].slice(0, PATTERN_PREVIEW_MOUNTS_PER_FRAME));

        vi.advanceTimersByTime(32);
        expect(order).toHaveLength(10);
        expect(order).toEqual([0, 1, 2, 3, 4, 5, 6, 7, 8, 9]);
        expect(pendingPreviewMounts()).toBe(0);
    });

    it('skips a cancelled mount without spending its slot', () => {
        const mounted: string[] = [];
        const cancel = schedulePreviewMount(() => mounted.push('a'));

        for (const id of ['b', 'c', 'd', 'e']) {
            schedulePreviewMount(() => mounted.push(id));
        }

        cancel();
        vi.advanceTimersByTime(16);

        expect(mounted).toEqual(['b', 'c', 'd', 'e']);
    });

    it('falls back to a timer without requestAnimationFrame', () => {
        vi.stubGlobal('requestAnimationFrame', undefined);

        const mount = vi.fn();
        schedulePreviewMount(mount);

        vi.advanceTimersByTime(16);

        expect(mount).toHaveBeenCalledTimes(1);
    });
});
