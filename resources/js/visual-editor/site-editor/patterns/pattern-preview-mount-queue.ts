/**
 * Shared frame queue for mounting pattern preview iframes.
 *
 * A preview batch can resolve two dozen cards at once, and every card
 * mounts a same-origin `srcdoc` document carrying the site's full
 * stylesheet. Mounting them all in one tick stalls the main thread, so
 * cards ask this queue for a slot instead: it runs at most
 * {@link PATTERN_PREVIEW_MOUNTS_PER_FRAME} mount callbacks per animation
 * frame, in request order, across every preview surface on the page.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.13.0
 */

/**
 * Most preview iframes mounted per animation frame.
 *
 * @since 1.13.0
 */
export const PATTERN_PREVIEW_MOUNTS_PER_FRAME = 4;

interface QueuedMount {
    readonly mount: () => void;
}

let queue: QueuedMount[] = [];
let frameScheduled = false;
let cancelFrame: (() => void) | null = null;

function requestFrame(callback: () => void): () => void {
    if (typeof requestAnimationFrame === 'function') {
        const handle = requestAnimationFrame(() => callback());

        return () => cancelAnimationFrame(handle);
    }

    const handle = setTimeout(callback, 16);

    return () => clearTimeout(handle);
}

function runFrame(): void {
    frameScheduled = false;
    cancelFrame = null;

    const batch = queue.slice(0, PATTERN_PREVIEW_MOUNTS_PER_FRAME);

    queue = queue.slice(batch.length);

    for (const entry of batch) {
        entry.mount();
    }

    ensureFrame();
}

function ensureFrame(): void {
    if (frameScheduled || queue.length === 0) {
        return;
    }

    frameScheduled = true;
    cancelFrame = requestFrame(runFrame);
}

/**
 * Queues a preview mount for a later animation frame.
 *
 * @since 1.13.0
 *
 * @param mount Called once the card's slot comes up.
 *
 * @return Cancels the mount if it hasn't run yet.
 */
export function schedulePreviewMount(mount: () => void): () => void {
    const entry: QueuedMount = { mount };

    queue.push(entry);
    ensureFrame();

    return () => {
        queue = queue.filter((queued) => queued !== entry);
    };
}

/**
 * Number of mounts waiting for a frame. For tests and diagnostics.
 *
 * @since 1.13.0
 */
export function pendingPreviewMounts(): number {
    return queue.length;
}

/**
 * Drops every queued mount and any scheduled frame. For tests.
 *
 * @since 1.13.0
 */
export function resetPreviewMountQueue(): void {
    cancelFrame?.();
    cancelFrame = null;
    frameScheduled = false;
    queue = [];
}
