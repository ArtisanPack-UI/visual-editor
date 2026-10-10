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
 * A legacy mobile-first breakpoint — the only kind screen-size
 * visibility builds its ranges from.
 */
export interface MinWidthBreakpoint {
    key: string;
    minWidthPx: number;
}

/**
 * An inclusive width range the block is hidden in. `maxWidthPx` is
 * `null` for the widest breakpoint, which has no upper bound.
 */
export interface HiddenWidthRange {
    minWidthPx: number;
    maxWidthPx: number | null;
}

/**
 * Breakpoint keys the screen-size rule hides the block at. Mirrors
 * `ScreenSizeRule::evaluate()`: a missing direction means `hide`,
 * `show` hides every known breakpoint that isn't listed, and unknown
 * keys are ignored.
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

    const known     = breakpoints.map((bp) => bp.key);
    const direction = (rule?.direction ?? 'hide') === 'hide' ? 'hide' : 'show';

    return direction === 'hide'
        ? known.filter((key) => configured.includes(key))
        : known.filter((key) => !configured.includes(key));
}

/**
 * Width ranges the screen-size rule hides the block in. Mirrors
 * `BlockRenderer::wrapWithScreenSizeCss()`: each hidden breakpoint
 * spans from its min-width to one pixel below the next breakpoint's.
 */
export function screenSizeHiddenRanges(
    rule: ScreenSizeRuleAttrs | null | undefined,
    breakpoints: readonly MinWidthBreakpoint[],
): HiddenWidthRange[] {
    const hidden = screenSizeHiddenKeys(rule, breakpoints);

    if (hidden.length === 0) {
        return [];
    }

    const ordered = [...breakpoints].sort((a, b) => a.minWidthPx - b.minWidthPx);
    const ranges: HiddenWidthRange[] = [];

    for (const key of hidden) {
        const position = ordered.findIndex((bp) => bp.key === key);
        const min      = ordered[position]?.minWidthPx;

        if (!Number.isInteger(min) || min <= 0) {
            continue;
        }

        const nextMin = ordered[position + 1]?.minWidthPx;

        ranges.push({
            minWidthPx: min,
            maxWidthPx: Number.isInteger(nextMin) && nextMin > min ? nextMin - 1 : null,
        });
    }

    return ranges;
}

/**
 * `@media` condition for a hidden range, in the front end's format.
 */
export function rangeMediaQuery(range: HiddenWidthRange): string {
    return range.maxWidthPx === null
        ? `(min-width:${range.minWidthPx}px)`
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
