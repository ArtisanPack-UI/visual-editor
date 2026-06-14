/**
 * Shared clamp helpers for the skills-slider block (#503).
 *
 * `edit.tsx` and `save.tsx` clamp the numeric attributes through the
 * same helpers so the editor preview and the persisted markup stay in
 * sync, and the three renderers (Blade / React / Vue) mirror the same
 * range to keep parity.
 */

const LEVEL_MIN = 1;
const LEVEL_MAX = 100;
const HEIGHT_MIN = 1;
const HEIGHT_MAX = 100;

export const SKILL_LEVEL_DEFAULT = 50;
export const BAR_HEIGHT_DEFAULT = 5;

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

export function clampSkillLevel(
    value: number | null | undefined,
    fallback: number = SKILL_LEVEL_DEFAULT
): number {
    return clampInt(value, LEVEL_MIN, LEVEL_MAX, fallback);
}

export function clampBarHeight(
    value: number | null | undefined,
    fallback: number = BAR_HEIGHT_DEFAULT
): number {
    return clampInt(value, HEIGHT_MIN, HEIGHT_MAX, fallback);
}
