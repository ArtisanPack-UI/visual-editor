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
import { flexClassNames } from '../../support/flex-serializer';
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

        // Validate raw is a parseable finite number BEFORE clamping —
        // otherwise non-numeric values like 'evil' silently snap to the
        // clamp min (1) and emit a bogus `ap-grid-*-1-{bp}-…` class.
        const numeric = typeof raw === 'number' ? raw : Number(raw);
        if (!Number.isFinite(numeric)) {
            continue;
        }

        const value = clampInt(numeric, 1, 12, 1);
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
            const layoutMode = attrString(props.attributes.layoutMode);
            const isMasonry = 'masonry' === layoutMode;

            const classes = classList([
                'ap-grid',
                `ap-grid-has-${baseColumns}-base-columns`,
                ...responsiveSpanClasses(responsiveColumns, 'columns', 'ap-grid-has'),
                isMasonry ? 'ap-grid-layout-masonry' : 'ap-grid-layout-fixed',
                className,
            ]);

            const attrs: Record<string, unknown> = { class: classes };
            if (isMasonry) {
                attrs['data-ap-cols'] = baseColumns;

                // Per-breakpoint overrides ride alongside the base
                // count so the JS bootstrap can pick the active
                // breakpoint at runtime instead of locking masonry to
                // the base `data-ap-cols`.
                for (const bp of BREAKPOINTS) {
                    const raw = responsiveColumns[bp];
                    if (raw === undefined || raw === null) {
                        continue;
                    }
                    const numeric = typeof raw === 'number' ? raw : Number(raw);
                    if (!Number.isFinite(numeric)) {
                        continue;
                    }
                    attrs[`data-ap-cols-${bp}`] = clampInt(numeric, 1, 12, baseColumns);
                }
            }

            return h(
                'div',
                attrs,
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
                ...flexClassNames(props.attributes.artisanpackFlex),
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
