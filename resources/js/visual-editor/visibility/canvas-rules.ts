/**
 * Pure helpers behind the editor-canvas visibility preview (#805).
 *
 * The screen-size helpers mirror the PHP front end exactly —
 * `ScreenSizeRule::evaluate()` decides which breakpoints are hidden
 * and `BlockRenderer::wrapWithScreenSizeCss()` turns each one into a
 * `@media` width range. Emitting the same ranges into the canvas
 * iframe means the media queries evaluate against the iframe's width,
 * so every viewport-switcher preview hides exactly what the published
 * page would at that width. Any change to either PHP method needs a
 * matching change here.
 *
 * @package @artisanpack-ui/visual-editor
 * @since 1.13.0
 */

import { __ } from '@wordpress/i18n';

import { isVisibilityActive, type ScreenSizeRuleAttrs, type VisibilityAttribute } from './types';

/**
 * A breakpoint screen-size visibility can reference: a legacy
 * mobile-first entry (`minWidthPx`, a width range up to the next
 * one) or a desktop-first device entry (`maxWidthPx`, everything at
 * or below it — #974). Exactly one width is set.
 */
export interface MinWidthBreakpoint {
    key: string;
    minWidthPx?: number;
    maxWidthPx?: number;
}

/**
 * An inclusive width range the block is hidden in. `maxWidthPx` is
 * `null` for the widest breakpoint, which has no upper bound;
 * `minWidthPx` is `0` for a device key, which has no lower bound.
 */
export interface HiddenWidthRange {
    minWidthPx: number;
    maxWidthPx: number | null;
}

function legacyBreakpoints(breakpoints: readonly MinWidthBreakpoint[]): Array<{ key: string; minWidthPx: number }> {
    return breakpoints.flatMap((bp) =>
        typeof bp.minWidthPx === 'number' && Number.isInteger(bp.minWidthPx) && bp.minWidthPx > 0
            ? [{ key: bp.key, minWidthPx: bp.minWidthPx }]
            : [],
    );
}

function deviceBreakpoints(breakpoints: readonly MinWidthBreakpoint[]): Array<{ key: string; maxWidthPx: number }> {
    return breakpoints.flatMap((bp) =>
        typeof bp.minWidthPx !== 'number'
        && typeof bp.maxWidthPx === 'number'
        && Number.isInteger(bp.maxWidthPx)
        && bp.maxWidthPx > 0
            ? [{ key: bp.key, maxWidthPx: bp.maxWidthPx }]
            : [],
    );
}

/**
 * Breakpoint keys the screen-size rule hides the block at. Mirrors
 * `ScreenSizeRule::evaluate()`: a missing direction means `hide`,
 * unknown keys are ignored, and `show` hides every unlisted legacy
 * range that starts above the widest listed device key (so
 * `show: ['mobile']` hides from 768px up).
 */
export function screenSizeHiddenKeys(
    rule: ScreenSizeRuleAttrs | null | undefined,
    breakpoints: readonly MinWidthBreakpoint[],
): string[] {
    const configured = Array.isArray(rule?.breakpoints)
        ? [...new Set(rule.breakpoints.filter((key): key is string => typeof key === 'string'))]
        : [];

    if (configured.length === 0) {
        return [];
    }

    const legacy    = legacyBreakpoints(breakpoints);
    const devices   = deviceBreakpoints(breakpoints);
    const direction = (rule?.direction ?? 'hide') === 'hide' ? 'hide' : 'show';

    if (direction === 'hide') {
        return [...legacy, ...devices].map((bp) => bp.key).filter((key) => configured.includes(key));
    }

    const deviceCeiling = devices
        .filter((bp) => configured.includes(bp.key))
        .reduce((ceiling, bp) => Math.max(ceiling, bp.maxWidthPx), 0);

    return legacy
        .filter((bp) => !configured.includes(bp.key) && bp.minWidthPx > deviceCeiling)
        .map((bp) => bp.key);
}

/**
 * Width ranges the screen-size rule hides the block in. Mirrors
 * `BlockRenderer::wrapWithScreenSizeCss()`: each hidden legacy
 * breakpoint spans from its min-width to one pixel below the next
 * breakpoint's, and each hidden device key spans 0 to its max-width.
 */
export function screenSizeHiddenRanges(
    rule: ScreenSizeRuleAttrs | null | undefined,
    breakpoints: readonly MinWidthBreakpoint[],
): HiddenWidthRange[] {
    const hidden = screenSizeHiddenKeys(rule, breakpoints);

    if (hidden.length === 0) {
        return [];
    }

    const ordered = legacyBreakpoints(breakpoints).sort((a, b) => a.minWidthPx - b.minWidthPx);
    const devices = deviceBreakpoints(breakpoints);
    const ranges: HiddenWidthRange[] = [];

    for (const key of hidden) {
        const device = devices.find((bp) => bp.key === key);

        if (device !== undefined) {
            ranges.push({ minWidthPx: 0, maxWidthPx: device.maxWidthPx });
            continue;
        }

        const position = ordered.findIndex((bp) => bp.key === key);
        const min      = ordered[position]?.minWidthPx;

        if (min === undefined) {
            continue;
        }

        const nextMin = ordered[position + 1]?.minWidthPx;

        ranges.push({
            minWidthPx: min,
            maxWidthPx: nextMin !== undefined && nextMin > min ? nextMin - 1 : null,
        });
    }

    return ranges;
}

/**
 * `@media` condition for a hidden range, in the front end's format.
 */
export function rangeMediaQuery(range: HiddenWidthRange): string {
    if (range.maxWidthPx === null) {
        return `(min-width:${range.minWidthPx}px)`;
    }

    return range.minWidthPx <= 0
        ? `(max-width:${range.maxWidthPx}px)`
        : `(min-width:${range.minWidthPx}px) and (max-width:${range.maxWidthPx}px)`;
}

/**
 * Whether the block carries a rule the canvas can't evaluate — the
 * master Hide toggle or any request / user / schedule rule. Those
 * blocks are dimmed rather than hidden. Screen-size is excluded: the
 * canvas evaluates it through {@link screenSizeHiddenRanges}.
 */
export function hasServerEvaluatedRule(value: VisibilityAttribute | null | undefined): boolean {
    if (!value) {
        return false;
    }

    return isVisibilityActive({ ...value, screenSize: undefined });
}

/**
 * Short List View suffix describing a block's visibility, or `null`
 * when no rule is active.
 */
export function visibilityListViewSuffix(
    value: VisibilityAttribute | null | undefined,
    breakpoints: readonly MinWidthBreakpoint[],
): string | null {
    if (value?.hide?.hidden === true) {
        return __('Hidden', 'artisanpack-visual-editor');
    }

    if (screenSizeHiddenRanges(value?.screenSize, breakpoints).length > 0) {
        return __('Hidden at some screen sizes', 'artisanpack-visual-editor');
    }

    if (hasServerEvaluatedRule(value)) {
        return __('Conditionally visible', 'artisanpack-visual-editor');
    }

    return null;
}
