/**
 * Layout + button core block renderers: group, row, stack, columns, column,
 * buttons, button. Containers render their already-rendered inner blocks via
 * the default slot; buttons sanitize URL + target/rel tokens the same way
 * the Blade partial does.
 */

import { defineComponent, h } from 'vue';
import {
    attrBoolean,
    attrRecord,
    attrString,
    classList,
    formatPercent,
    layoutClass,
    layoutPair,
} from '../../support/attributes';
import { flexClassNames } from '../../support/flex-serializer';
import { safeUrl } from '../../support/urlSanitizer';
import { blockRendererProps } from '../shared';

const ALLOWED_GROUP_TAGS = ['div', 'section', 'article', 'aside', 'header', 'footer', 'main', 'nav'] as const;

type GroupTag = (typeof ALLOWED_GROUP_TAGS)[number];

export const GroupBlock = defineComponent({
    name: 'GroupBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const tagName = attrString(props.attributes.tagName);
            const tag: GroupTag = (ALLOWED_GROUP_TAGS as ReadonlyArray<string>).includes(tagName)
                ? (tagName as GroupTag)
                : 'div';

            const className = attrString(props.attributes.className);
            const flexClasses = flexClassNames(props.attributes.artisanpackFlex);

            // #595 / #711 — when our Flex Layout panel is active at the
            // base breakpoint, switch the layout class to `is-layout-flex`
            // so the baseline
            // `is-layout-flow > * + * { margin-block-start: gap }` rule
            // does not push children apart along the cross axis.
            //
            // Match only the unprefixed `ap-flex` (the base-breakpoint
            // emit). Breakpoint-prefixed variants like `md:ap-flex` mean
            // "flex starts at md+"; flipping the wrapper to
            // `is-layout-flex` for them would apply flex below the
            // breakpoint too. Mirrors `group.blade.php`.
            const storedLayoutType = attrString(attrRecord(props.attributes.layout).type).trim();
            let groupLayoutClass = layoutClass(props.attributes);

            if (storedLayoutType === '' && flexClasses.includes('ap-flex')) {
                groupLayoutClass = 'is-layout-flex';
            }

            // #702 — the block-library CSS targets the per-block compound
            // (`wp-block-group-is-layout-constrained`), so emit it alongside
            // the shared modifier. `layoutClass()` also covers the `grid`
            // type the old inline ternary silently rendered as flow.
            const classes = classList([
                'wp-block-group',
                ...layoutPair('group', groupLayoutClass),
                ...flexClasses,
                className,
            ]);

            return h(tag, { class: classes }, slots.default ? slots.default() : []);
        };
    },
});

export const RowBlock = defineComponent({
    name: 'RowBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const className = attrString(props.attributes.className);
            // Renders `wp-block-group` markup, so the per-block layout
            // compound is keyed on `group` rather than `row` (#702).
            const classes = classList([
                'wp-block-group',
                ...layoutPair('group', 'is-layout-flex'),
                'is-horizontal',
                className,
            ]);

            return h('div', { class: classes }, slots.default ? slots.default() : []);
        };
    },
});

export const StackBlock = defineComponent({
    name: 'StackBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const className = attrString(props.attributes.className);
            // Renders `wp-block-group` markup, so the per-block layout
            // compound is keyed on `group` rather than `stack` (#702).
            const classes = classList([
                'wp-block-group',
                ...layoutPair('group', 'is-layout-flex'),
                'is-vertical',
                className,
            ]);

            return h('div', { class: classes }, slots.default ? slots.default() : []);
        };
    },
});

export const ColumnsBlock = defineComponent({
    name: 'ColumnsBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const className = attrString(props.attributes.className);
            const isStacked =
                props.attributes.isStackedOnMobile === undefined
                    ? true
                    : attrBoolean(props.attributes.isStackedOnMobile);
            const verticalAlignment = attrString(props.attributes.verticalAlignment);

            const flexClasses = flexClassNames(props.attributes.artisanpackFlex);
            // #702 — columns is a flex layout upstream and its wrapper
            // carries both the shared modifier and the per-block compound.
            // Neither was emitted here, so layout rules keyed on either
            // never applied.
            const classes = classList([
                'wp-block-columns',
                ...layoutPair('columns', 'is-layout-flex'),
                isStacked ? 'is-stacked-on-mobile' : null,
                verticalAlignment !== '' ? `are-vertically-aligned-${verticalAlignment}` : null,
                ...flexClasses,
                className,
            ]);

            return h('div', { class: classes }, slots.default ? slots.default() : []);
        };
    },
});

export const ColumnBlock = defineComponent({
    name: 'ColumnBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const className = attrString(props.attributes.className);
            const verticalAlignment = attrString(props.attributes.verticalAlignment);
            const flexClasses = flexClassNames(props.attributes.artisanpackFlex);

            // Column responsive-width scope stamped by
            // `stampColumnWidthScopes()` (#712). Appended last so the
            // token lands in the same position the Blade partial emits it
            // (after every base / block-support class).
            const widthScope = attrString(props.attributes._veColumnWidthScope);

            const classes = classList([
                'wp-block-column',
                verticalAlignment !== '' ? `is-vertically-aligned-${verticalAlignment}` : null,
                ...flexClasses,
                className,
                widthScope !== '' ? widthScope : null,
            ]);

            const width = props.attributes.width;
            let style: Record<string, string> | undefined;

            if (width !== undefined && width !== null && width !== '') {
                let basis = '';

                if (typeof width === 'number') {
                    basis = formatPercent(width);
                } else if (typeof width === 'string' && width.trim() !== '') {
                    const numeric = Number.parseFloat(width);

                    basis =
                        Number.isFinite(numeric) && String(numeric) === width.trim()
                            ? formatPercent(numeric)
                            : width;
                }

                if (basis !== '') {
                    style = { 'flex-basis': basis };
                }
            }

            const divProps: Record<string, unknown> = { class: classes };

            if (style !== undefined) {
                divProps.style = style;
            }

            return h('div', divProps, slots.default ? slots.default() : []);
        };
    },
});

export const ButtonsBlock = defineComponent({
    name: 'ButtonsBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const layout = attrRecord(props.attributes.layout);
            const justify = attrString(layout.justifyContent);
            const className = attrString(props.attributes.className);

            const classes = classList([
                'wp-block-buttons',
                ...layoutPair('buttons', 'is-layout-flex'),
                justify !== '' ? `is-content-justification-${justify}` : 'is-content-justification-left',
                className,
            ]);

            return h('div', { class: classes }, slots.default ? slots.default() : []);
        };
    },
});

export const ButtonBlock = defineComponent({
    name: 'ButtonBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const text = attrString(props.attributes.text);
            const url = safeUrl(props.attributes.url);
            const linkTarget = attrString(props.attributes.linkTarget);
            const title = attrString(props.attributes.title);
            const className = attrString(props.attributes.className);

            const wrapperClasses = classList(['wp-block-button', className]);
            const linkClasses = 'wp-block-button__link wp-element-button';

            let rel = attrString(props.attributes.rel);

            if (linkTarget === '_blank') {
                const tokens = rel.split(/\s+/).filter((t) => t !== '');

                for (const required of ['noopener', 'noreferrer']) {
                    if (!tokens.includes(required)) {
                        tokens.push(required);
                    }
                }

                rel = tokens.join(' ');
            }

            if (url === '') {
                return h(
                    'div',
                    { class: wrapperClasses },
                    h('span', {
                        class: linkClasses,
                        title: title === '' ? undefined : title,
                        innerHTML: text,
                    })
                );
            }

            return h(
                'div',
                { class: wrapperClasses },
                h('a', {
                    class: linkClasses,
                    href: url,
                    target: linkTarget === '' ? undefined : linkTarget,
                    rel: rel === '' ? undefined : rel,
                    title: title === '' ? undefined : title,
                    innerHTML: text,
                })
            );
        };
    },
});
