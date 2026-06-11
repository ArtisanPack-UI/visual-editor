/**
 * Vue renderers for the `artisanpack/grid` family (#498). Mirrors the
 * Blade partials and React renderers so every environment emits identical
 * markup.
 *
 * Per-breakpoint column count + item span are encoded as static classes
 * (`ap-grid-has-N-{bp}-columns`, `ap-grid-item-span-N-{bp}-{columns|row}`)
 * keyed on the Tailwind-style breakpoint registry (base / sm / md / lg /
 * xl / 2xl). The matching media-query rules ship in `grid.css`; mobile-
 * first cascade picks the active breakpoint's class at runtime.
 */

import { defineComponent, h } from 'vue';

import { attrInt, attrRecord, attrString, classList } from '../../support/attributes';
import { blockRendererProps } from '../shared';

const BREAKPOINTS: ReadonlyArray<string> = ['sm', 'md', 'lg', 'xl', '2xl'];

type GridItemInnerLayout = 'normal' | 'equal' | 'center' | 'bottom' | 'last-bottom';

const VALID_INNER_LAYOUTS: ReadonlyArray<GridItemInnerLayout> = [
    'normal',
    'equal',
    'center',
    'bottom',
    'last-bottom',
];

function clampInt(value: unknown, min: number, max: number, fallback: number): number {
    const parsed = attrInt(value, fallback);
    if (parsed < min) {
        return min;
    }
    if (parsed > max) {
        return max;
    }
    return parsed;
}

function normalizeInnerLayout(value: unknown): GridItemInnerLayout {
    const raw = attrString(value, 'normal');
    return (VALID_INNER_LAYOUTS as ReadonlyArray<string>).includes(raw)
        ? (raw as GridItemInnerLayout)
        : 'normal';
}

function responsiveSpanClasses(
    overrides: Record<string, unknown>,
    suffix: 'columns' | 'row',
    prefix: 'ap-grid-has' | 'ap-grid-item-span'
): string[] {
    const classes: string[] = [];

    for (const bp of BREAKPOINTS) {
        const raw = overrides[bp];
        if (raw === undefined || raw === null) {
            continue;
        }

        const value = clampInt(raw, 1, 12, 0);
        if (value === 0) {
            continue;
        }

        classes.push(`${prefix}-${value}-${bp}-${suffix}`);
    }

    return classes;
}

export const GridBlock = defineComponent({
    name: 'GridBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const baseColumns = clampInt(props.attributes.numColumns, 1, 12, 4);
            const responsive = attrRecord(props.attributes.responsive);
            const responsiveColumns = attrRecord(responsive.numColumns);
            const className = attrString(props.attributes.className);

            const classes = classList([
                'ap-grid',
                `ap-grid-has-${baseColumns}-base-columns`,
                ...responsiveSpanClasses(responsiveColumns, 'columns', 'ap-grid-has'),
                className,
            ]);

            return h(
                'div',
                { class: classes },
                slots.default ? slots.default() : []
            );
        };
    },
});

export const GridItemBlock = defineComponent({
    name: 'GridItemBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const innerLayout = normalizeInnerLayout(props.attributes.innerLayout);
            const baseColumnSpan = clampInt(props.attributes.gridColumnSpan, 1, 12, 1);
            const baseRowSpan = clampInt(props.attributes.gridRowSpan, 1, 12, 1);
            const responsive = attrRecord(props.attributes.responsive);
            const responsiveColumnSpan = attrRecord(responsive.gridColumnSpan);
            const responsiveRowSpan = attrRecord(responsive.gridRowSpan);
            const className = attrString(props.attributes.className);

            const classes = classList([
                'ap-grid-item',
                `ap-grid-item-layout-${innerLayout}`,
                `ap-grid-item-span-${baseColumnSpan}-base-columns`,
                `ap-grid-item-span-${baseRowSpan}-base-row`,
                ...responsiveSpanClasses(responsiveColumnSpan, 'columns', 'ap-grid-item-span'),
                ...responsiveSpanClasses(responsiveRowSpan, 'row', 'ap-grid-item-span'),
                className,
            ]);

            return h(
                'div',
                { class: classes },
                slots.default ? slots.default() : []
            );
        };
    },
});
