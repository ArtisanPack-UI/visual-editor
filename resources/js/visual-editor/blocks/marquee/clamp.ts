/**
 * Shared clamp helpers for the marquee block (#500).
 *
 * Both `edit.tsx` and `save.tsx` need to clamp the `marqueeWidth` and
 * `marqueeSpeed` attributes to the same range so the editor preview
 * and the persisted markup stay in sync. Keeping the helpers in their
 * own module guards against drift if the range / default ever changes
 * for one but not the other.
 */

const WIDTH_MIN = 1;
const WIDTH_MAX = 100;
const SPEED_MIN = 1;
const SPEED_MAX = 100;

export const MARQUEE_WIDTH_DEFAULT = 100;
export const MARQUEE_SPEED_DEFAULT = 5;

function clampInt(
    value: number | null | undefined,
    min: number,
    max: number,
    fallback: number
): number {
    if (typeof value !== 'number' || !Number.isFinite(value)) {
        return fallback;
    }
    return Math.min(max, Math.max(min, Math.round(value)));
}

export function clampPercent(
    value: number | null | undefined,
    fallback: number = MARQUEE_WIDTH_DEFAULT
): number {
    return clampInt(value, WIDTH_MIN, WIDTH_MAX, fallback);
}

export function clampSpeed(
    value: number | null | undefined,
    fallback: number = MARQUEE_SPEED_DEFAULT
): number {
    return clampInt(value, SPEED_MIN, SPEED_MAX, fallback);
}
