/**
 * Flex Layout token rules built from the active breakpoints — Vue
 * renderer (#895).
 *
 * The static `flex-layout.css` styles the Flex panel's token classes
 * (`ap-flex`, `md:ap-flex-col`, …) at the package's default breakpoint
 * widths. When a host passes its own breakpoints to `setBreakpoints()`,
 * {@link flexLayoutCss} builds the same rules at the host's widths, so
 * `<LayoutBaseline>` can print them in place of the static sheet. Mirrors
 * the Blade renderer's `FlexLayoutStyles`.
 *
 * @package @artisanpack-ui/visual-editor-renderer-vue
 * @since 1.15.1
 */

import {
    DEFAULT_BREAKPOINTS,
    DEFAULT_DEVICE_BREAKPOINTS,
    getBreakpoints,
    getDeviceBreakpoints,
} from '../visibility';

/**
 * Unprefixed Flex Layout token rules, as the static `flex-layout.css`
 * base section ships them. Mirrors `FlexLayoutStyles::BASE_RULES`.
 */
export const FLEX_LAYOUT_BASE_RULES: ReadonlyArray<string> = [
    '.ap-flex { display: flex; }',
    '.ap-flex-none { display: block; }',
    ':where(.ap-flex) > * + * { margin-block-start: 0; }',
    '.ap-flex-row { flex-direction: row; }',
    '.ap-flex-row-reverse { flex-direction: row-reverse; }',
    '.ap-flex-col { flex-direction: column; }',
    '.ap-flex-col-reverse { flex-direction: column-reverse; }',
    '.ap-flex-nowrap { flex-wrap: nowrap; }',
    '.ap-flex-wrap { flex-wrap: wrap; }',
    '.ap-flex-wrap-reverse { flex-wrap: wrap-reverse; }',
    '.ap-justify-start { justify-content: flex-start; }',
    '.ap-justify-center { justify-content: center; }',
    '.ap-justify-end { justify-content: flex-end; }',
    '.ap-justify-between { justify-content: space-between; }',
    '.ap-justify-around { justify-content: space-around; }',
    '.ap-justify-evenly { justify-content: space-evenly; }',
    '.ap-justify-left { justify-content: left; }',
    '.ap-justify-right { justify-content: right; }',
    '.ap-justify-stretch { justify-content: stretch; }',
    '.ap-items-stretch { align-items: stretch; }',
    '.ap-items-start { align-items: flex-start; }',
    '.ap-items-end { align-items: flex-end; }',
    '.ap-items-center { align-items: center; }',
    '.ap-items-baseline { align-items: baseline; }',
    '.ap-items-self-start { align-items: self-start; }',
    '.ap-items-self-end { align-items: self-end; }',
    '.ap-items-first-baseline { align-items: first baseline; }',
    '.ap-items-last-baseline { align-items: last baseline; }',
    '.ap-content-stretch { align-content: stretch; }',
    '.ap-content-start { align-content: flex-start; }',
    '.ap-content-end { align-content: flex-end; }',
    '.ap-content-center { align-content: center; }',
    '.ap-content-between { align-content: space-between; }',
    '.ap-content-around { align-content: space-around; }',
    '.ap-content-evenly { align-content: space-evenly; }',
    '.ap-content-baseline { align-content: baseline; }',
    '.ap-self-auto { align-self: auto; }',
    '.ap-self-start { align-self: flex-start; }',
    '.ap-self-end { align-self: flex-end; }',
    '.ap-self-center { align-self: center; }',
    '.ap-self-stretch { align-self: stretch; }',
    '.ap-self-baseline { align-self: baseline; }',
    '.ap-self-self-start { align-self: self-start; }',
    '.ap-self-self-end { align-self: self-end; }',
    '.ap-grow-0 { flex-grow: 0; }',
    '.ap-grow-1 { flex-grow: 1; }',
    '.ap-grow-2 { flex-grow: 2; }',
    '.ap-grow-3 { flex-grow: 3; }',
    '.ap-grow-4 { flex-grow: 4; }',
    '.ap-grow-5 { flex-grow: 5; }',
    '.ap-grow-6 { flex-grow: 6; }',
    '.ap-grow-7 { flex-grow: 7; }',
    '.ap-grow-8 { flex-grow: 8; }',
    '.ap-grow-9 { flex-grow: 9; }',
    '.ap-shrink-0 { flex-shrink: 0; }',
    '.ap-shrink-1 { flex-shrink: 1; }',
    '.ap-shrink-2 { flex-shrink: 2; }',
    '.ap-shrink-3 { flex-shrink: 3; }',
    '.ap-shrink-4 { flex-shrink: 4; }',
    '.ap-shrink-5 { flex-shrink: 5; }',
    '.ap-shrink-6 { flex-shrink: 6; }',
    '.ap-shrink-7 { flex-shrink: 7; }',
    '.ap-shrink-8 { flex-shrink: 8; }',
    '.ap-shrink-9 { flex-shrink: 9; }',
    '.ap-basis-auto { flex-basis: auto; }',
    '.ap-basis-0 { flex-basis: 0; }',
    '.ap-basis-full { flex-basis: 100%; }',
    '.ap-basis-fit-content { flex-basis: fit-content; }',
    '.ap-basis-max-content { flex-basis: max-content; }',
    '.ap-basis-min-content { flex-basis: min-content; }',
    '.ap-order-0 { order: 0; }',
    '.ap-order-1 { order: 1; }',
    '.ap-order-2 { order: 2; }',
    '.ap-order-3 { order: 3; }',
    '.ap-order-4 { order: 4; }',
    '.ap-order-5 { order: 5; }',
    '.ap-order-6 { order: 6; }',
    '.ap-order-7 { order: 7; }',
    '.ap-order-8 { order: 8; }',
    '.ap-order-9 { order: 9; }',
    '.ap-order-\\-1 { order: -1; }',
];

/**
 * Rules repeated inside each breakpoint's `@media` block; `{p}` is the
 * escaped `{key}:` class prefix. Mirrors
 * `FlexLayoutStyles::BREAKPOINT_RULES`.
 */
export const FLEX_LAYOUT_BREAKPOINT_RULES: ReadonlyArray<string> = [
    ':where(.{p}ap-flex) > * + * { margin-block-start: 0; }',
    '.{p}ap-flex { display: flex; }',
    '.{p}ap-flex-none { display: block; }',
    '.{p}ap-flex-row { flex-direction: row; }',
    '.{p}ap-flex-row-reverse { flex-direction: row-reverse; }',
    '.{p}ap-flex-col { flex-direction: column; }',
    '.{p}ap-flex-col-reverse { flex-direction: column-reverse; }',
    '.{p}ap-flex-nowrap { flex-wrap: nowrap; }',
    '.{p}ap-flex-wrap { flex-wrap: wrap; }',
    '.{p}ap-flex-wrap-reverse { flex-wrap: wrap-reverse; }',
    '.{p}ap-justify-start { justify-content: flex-start; }',
    '.{p}ap-justify-center { justify-content: center; }',
    '.{p}ap-justify-end { justify-content: flex-end; }',
    '.{p}ap-justify-between { justify-content: space-between; }',
    '.{p}ap-justify-around { justify-content: space-around; }',
    '.{p}ap-justify-evenly { justify-content: space-evenly; }',
    '.{p}ap-items-stretch { align-items: stretch; }',
    '.{p}ap-items-start { align-items: flex-start; }',
    '.{p}ap-items-end { align-items: flex-end; }',
    '.{p}ap-items-center { align-items: center; }',
    '.{p}ap-items-baseline { align-items: baseline; }',
    '.{p}ap-content-stretch { align-content: stretch; }',
    '.{p}ap-content-start { align-content: flex-start; }',
    '.{p}ap-content-end { align-content: flex-end; }',
    '.{p}ap-content-center { align-content: center; }',
    '.{p}ap-content-between { align-content: space-between; }',
    '.{p}ap-self-auto { align-self: auto; }',
    '.{p}ap-self-start { align-self: flex-start; }',
    '.{p}ap-self-end { align-self: flex-end; }',
    '.{p}ap-self-center { align-self: center; }',
    '.{p}ap-self-stretch { align-self: stretch; }',
    '.{p}ap-grow-0 { flex-grow: 0; }',
    '.{p}ap-grow-1 { flex-grow: 1; }',
    '.{p}ap-grow-2 { flex-grow: 2; }',
    '.{p}ap-shrink-0 { flex-shrink: 0; }',
    '.{p}ap-shrink-1 { flex-shrink: 1; }',
    '.{p}ap-basis-auto { flex-basis: auto; }',
    '.{p}ap-basis-full { flex-basis: 100%; }',
    '.{p}ap-order-0 { order: 0; }',
    '.{p}ap-order-1 { order: 1; }',
];

/**
 * The `{key}:` class prefix as a selector: the colon escaped, and a
 * leading digit written as a hex escape (`2xl` → `\32xl\:`), with the
 * separating space CSS needs when the next character is a hex digit.
 * Mirrors `FlexLayoutStyles::escapePrefix()`.
 */
export function escapeFlexPrefix(key: string): string {
    let escaped = key;

    if (/^[0-9]/.test(key)) {
        const rest = key.slice(1);
        escaped = `\\3${key[0]}${/^[0-9a-f]/i.test(rest) ? ' ' : ''}${rest}`;
    }

    return `${escaped}\\:`;
}

/**
 * The full token stylesheet: the base rules, then one `@media` block per
 * breakpoint in emission order (legacy keys ascending, then devices
 * largest first). Mirrors `FlexLayoutStyles::build()`.
 */
export function buildFlexLayoutCss(
    legacy: ReadonlyArray<{ key: string; minWidthPx: number }>,
    devices: ReadonlyArray<{ key: string; maxWidthPx: number }>
): string {
    const blocks = [FLEX_LAYOUT_BASE_RULES.join('\n')];
    const queries = [
        ...[...legacy]
            .sort((a, b) => a.minWidthPx - b.minWidthPx)
            .map(({ key, minWidthPx }) => ({ key, query: `(min-width: ${minWidthPx}px)` })),
        ...[...devices]
            .sort((a, b) => b.maxWidthPx - a.maxWidthPx)
            .map(({ key, maxWidthPx }) => ({ key, query: `(max-width: ${maxWidthPx}px)` })),
    ];

    for (const { key, query } of queries) {
        const prefix = escapeFlexPrefix(key);
        const rules = FLEX_LAYOUT_BREAKPOINT_RULES.map((rule) => `\t${rule.split('{p}').join(prefix)}`);

        blocks.push(`@media ${query} {\n${rules.join('\n')}\n}`);
    }

    return blocks.join('\n\n');
}

/**
 * The token stylesheet for the breakpoints `setBreakpoints()` set, or
 * `''` while they match the package defaults (the static
 * `flex-layout.css` covers those). Mirrors `FlexLayoutStyles::css()`.
 */
export function flexLayoutCss(): string {
    const css = buildFlexLayoutCss(getBreakpoints(), getDeviceBreakpoints());

    return css === buildFlexLayoutCss(DEFAULT_BREAKPOINTS, DEFAULT_DEVICE_BREAKPOINTS) ? '' : css;
}
