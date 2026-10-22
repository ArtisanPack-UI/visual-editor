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
    layoutPair,
} from '../../support/attributes';
import { isNonEmptyWidth, normalizeBasis } from '../../support/columnWidth';
import { flexClassNames } from '../../support/flex-serializer';
import { safeCssValue } from '../../support/cssValue';
import { applyColumnsGap } from '../../support/blockGap';
import { compileBlockSupports } from '../../support/blockSupports';
import { layoutModifierClasses, panelLayoutClass } from '../../support/layoutContainer';
import { safeUrl } from '../../support/urlSanitizer';
import { blockRendererProps, withStyle } from '../shared';

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
            // Mirror `group.blade.php`: read the raw `layout.type`, treat a
            // non-string as `''` (not coerced via `String()`, which would
            // turn a numeric `5` into `'5'` and skip the override where
            // Blade fires it), and trim with PHP's whitespace set.
            const groupLayoutClass = panelLayoutClass(props.attributes);

            // #702 — the block-library CSS targets the per-block compound
            // (`wp-block-group-is-layout-constrained`), so emit it alongside
            // the shared modifier. `layoutClass()` also covers the `grid`
            // type the old inline ternary silently rendered as flow.
            // Photo Grid wrapper scope stamped by `stampPhotoGridScopes()`
            // (#714). Emitted after the flex classes and before the author
            // `className`, the same position the Blade partial merges it
            // into `$wrapperBaseClasses`.
            const photoGridScope = attrString(props.attributes._vePhotoGridScope);
            // Per-instance layout scope stamped by `stampLayoutScopes()`
            // (#871), right after the layout pair as in `group.blade.php`,
            // then the layout modifier classes (F-3).
            const layoutScope = attrString(props.attributes._veLayoutScope);
            // #920 — colour, background, spacing, border, typography and
            // dimensions supports, as `group.blade.php`'s `wrapperAttrs()`
            // prints them.
            const supports = compileBlockSupports(props.attributes);

            const classes = classList([
                'wp-block-group',
                ...layoutPair('group', groupLayoutClass),
                layoutScope !== '' ? layoutScope : null,
                ...layoutModifierClasses(props.attributes),
                ...flexClasses,
                photoGridScope !== '' ? photoGridScope : null,
                ...supports.classNames,
                className,
            ]);

            return h(tag, withStyle({ class: classes }, supports.style), slots.default ? slots.default() : []);
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
            const supports = compileBlockSupports(props.attributes);
            const classes = classList([
                'wp-block-group',
                ...layoutPair('group', 'is-layout-flex'),
                'is-horizontal',
                ...supports.classNames,
                className,
            ]);

            return h('div', withStyle({ class: classes }, supports.style), slots.default ? slots.default() : []);
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
            const supports = compileBlockSupports(props.attributes);
            const classes = classList([
                'wp-block-group',
                ...layoutPair('group', 'is-layout-flex'),
                'is-vertical',
                ...supports.classNames,
                className,
            ]);

            return h('div', withStyle({ class: classes }, supports.style), slots.default ? slots.default() : []);
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
            // Photo Grid wrapper scope stamped by `stampPhotoGridScopes()`
            // (#714), emitted in the same position the columns Blade partial
            // merges it.
            const photoGridScope = attrString(props.attributes._vePhotoGridScope);
            const supports = compileBlockSupports(props.attributes);
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
                photoGridScope !== '' ? photoGridScope : null,
                ...supports.classNames,
                className,
            ]);

            // The Columns' supports (#920), as `columns.blade.php` prints
            // them. #914 — plus the per-axis left gap for the Column width
            // calc, last.
            const style: Record<string, string> = {
                ...supports.style,
                ...applyColumnsGap(props.attributes),
            };

            return h('div', withStyle({ class: classes }, style), slots.default ? slots.default() : []);
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
            // F-3 — the column's layout pair, per-instance scope and
            // modifiers, in `column.blade.php` order.
            const layoutScope = attrString(props.attributes._veLayoutScope);
            const supports = compileBlockSupports(props.attributes);

            const classes = classList([
                'wp-block-column',
                ...layoutPair('column', panelLayoutClass(props.attributes)),
                layoutScope !== '' ? layoutScope : null,
                ...layoutModifierClasses(props.attributes),
                verticalAlignment !== '' ? `is-vertically-aligned-${verticalAlignment}` : null,
                ...flexClasses,
                ...supports.classNames,
                className,
                widthScope !== '' ? widthScope : null,
            ]);

            const width = props.attributes.width;
            // The column's supports (#920) ride first, as `column.blade.php`
            // prints them.
            let style: Record<string, string> = { ...supports.style };

            // Mirror the Blade partial's inline `flex-basis`: gate on PHP
            // `empty()` semantics (`isNonEmptyWidth` drops
            // `0`/`'0'`/`''`/booleans/non-scalars) and normalize through the
            // shared `normalizeBasis` so `'60.0'` renders as `60%` (not raw)
            // and only whitelist-safe values are emitted (#720).
            if (isNonEmptyWidth(width)) {
                const normalized = normalizeBasis(width);

                if (normalized !== null && safeCssValue(normalized.basis) !== null) {
                    style = { ...style, 'flex-basis': normalized.basis };
                }
            }

            return h('div', withStyle({ class: classes }, style), slots.default ? slots.default() : []);
        };
    },
});

const ALLOWED_JUSTIFY_CONTENT = [
    'left',
    'center',
    'right',
    'space-between',
    'space-around',
    'space-evenly',
    'stretch',
] as const;

export const ButtonsBlock = defineComponent({
    name: 'ButtonsBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const layout = attrRecord(props.attributes.layout);
            // Whitelist `layout.justifyContent` against WP's enum so an
            // authored block can't inject an arbitrary class token; anything
            // else falls back to `left`, WP's own default. Mirrors
            // `buttons.blade.php`.
            const rawJustify = attrString(layout.justifyContent);
            const justify = (ALLOWED_JUSTIFY_CONTENT as ReadonlyArray<string>).includes(rawJustify)
                ? rawJustify
                : 'left';
            const className = attrString(props.attributes.className);
            // F-3 — per-instance scope stamped by `stampLayoutScopes()`,
            // then the orientation / wrap modifiers, in `buttons.blade.php`
            // order.
            const layoutScope = attrString(props.attributes._veLayoutScope);
            const supports = compileBlockSupports(props.attributes);

            const classes = classList([
                'wp-block-buttons',
                ...layoutPair('buttons', 'is-layout-flex'),
                layoutScope !== '' ? layoutScope : null,
                `is-content-justification-${justify}`,
                ...layoutModifierClasses(props.attributes, true, false),
                ...supports.classNames,
                className,
            ]);

            return h('div', withStyle({ class: classes }, supports.style), slots.default ? slots.default() : []);
        };
    },
});

/**
 * Width classes and style for a button wrapper (#921). Mirrors the Blade
 * view: 25/50/75/100% get the legacy class, another percentage sets
 * `--wp--block-button--width`, and a plain length is set directly.
 */
function buttonWidth(attributes: Record<string, unknown>): { classes: string[]; style?: string; property?: string } {
    const style = attributes.style as { dimensions?: { width?: unknown } } | undefined;
    const raw = style?.dimensions?.width;

    if ('string' !== typeof raw || '' === raw.trim()) {
        return { classes: [] };
    }

    const width = raw.trim();

    if (width.endsWith('%')) {
        const amount = width.slice(0, -1);

        if (['25', '50', '75', '100'].includes(amount)) {
            return { classes: ['has-custom-width', 'wp-block-button__width', `wp-block-button__width-${amount}`] };
        }

        return '' !== amount && !Number.isNaN(Number(amount))
            ? { classes: ['has-custom-width', 'wp-block-button__width'], property: '--wp--block-button--width', style: String(parseFloat(amount)) }
            : { classes: ['has-custom-width', 'wp-block-button__width'] };
    }

    return /^\d*\.?\d+(px|rem|em|vw|ch)$/.test(width)
        ? { classes: ['has-custom-width'], property: 'width', style: width }
        : { classes: ['has-custom-width'] };
}

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

            const width = buttonWidth(props.attributes);
            const wrapperClasses = classList(['wp-block-button', ...width.classes, className]);
            const wrapperProps = width.property
                ? { class: wrapperClasses, style: `${width.property}:${width.style}` }
                : { class: wrapperClasses };
            // #920 — the visual supports land on the inner link, which
            // theme.json's `styles.elements.button` targets, as in
            // `button.blade.php`.
            const supports = compileBlockSupports(props.attributes);
            const linkClasses = classList(['wp-block-button__link', 'wp-element-button', ...supports.classNames]);

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
                    wrapperProps,
                    h('span', {
                        ...withStyle({ class: linkClasses }, supports.style),
                        title: title === '' ? undefined : title,
                        innerHTML: text,
                    })
                );
            }

            return h(
                'div',
                wrapperProps,
                h('a', {
                    ...withStyle({ class: linkClasses }, supports.style),
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
