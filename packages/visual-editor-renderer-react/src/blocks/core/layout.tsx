/**
 * Layout + button core block renderers: group, row, stack, columns, column,
 * buttons, button. Containers render their already-rendered inner blocks as
 * React children; buttons sanitize URL + target/rel tokens the same way the
 * Blade partial does.
 */

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
import { applyBackground } from '../../support/background';
import { applyBlockGap, applyColumnsGap } from '../../support/blockGap';
import { applyDimensions, dimensionsClassName } from '../../support/dimensions';
import { layoutModifierClasses, panelLayoutClass } from '../../support/layoutContainer';
import { safeUrl } from '../../support/urlSanitizer';
import type { BlockRendererProps } from '../../types';

const ALLOWED_GROUP_TAGS = ['div', 'section', 'article', 'aside', 'header', 'footer', 'main', 'nav'] as const;

type GroupTag = (typeof ALLOWED_GROUP_TAGS)[number];

export function GroupBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const tagName = attrString(attributes.tagName);
    const Tag: GroupTag = (ALLOWED_GROUP_TAGS as ReadonlyArray<string>).includes(tagName)
        ? (tagName as GroupTag)
        : 'div';

    const className = attrString(attributes.className);
    const flexClasses = flexClassNames(attributes.artisanpackFlex);

    // #595 / #711 — when our Flex Layout panel is active at the base
    // breakpoint, switch the layout class to `is-layout-flex` so the
    // baseline `is-layout-flow > * + * { margin-block-start: gap }` rule
    // does not push children apart along the cross axis.
    //
    // Match only the unprefixed `ap-flex` (the base-breakpoint emit).
    // Breakpoint-prefixed variants like `md:ap-flex` mean "flex starts at
    // md+"; flipping the wrapper to `is-layout-flex` for them would apply
    // flex below the breakpoint too. Mirrors `group.blade.php`.
    // Mirror `group.blade.php`: read the raw `layout.type`, treat a
    // non-string as `''` (not coerced via `String()`, which would turn a
    // numeric `5` into `'5'` and skip the override where Blade fires it),
    // and trim with PHP's whitespace set.
    const groupLayoutClass = panelLayoutClass(attributes);

    // #702 — the block-library CSS targets the per-block compound
    // (`wp-block-group-is-layout-constrained`), so emit it alongside the
    // shared modifier. `layoutClass()` also covers the `grid` type the
    // old inline ternary silently rendered as flow.
    // Photo Grid wrapper scope stamped by `stampPhotoGridScopes()` (#714).
    // Emitted after the flex classes and before the author `className`, the
    // same position the Blade partial merges it into `$wrapperBaseClasses`.
    const photoGridScope = attrString(attributes._vePhotoGridScope);
    // Per-instance layout scope stamped by `stampLayoutScopes()` (#871),
    // right after the layout pair as in `group.blade.php`, then the
    // layout modifier classes (F-3).
    const layoutScope = attrString(attributes._veLayoutScope);

    const classes = classList([
        'wp-block-group',
        ...layoutPair('group', groupLayoutClass),
        layoutScope !== '' ? layoutScope : null,
        ...layoutModifierClasses(attributes),
        ...flexClasses,
        photoGridScope !== '' ? photoGridScope : null,
        dimensionsClassName(attributes),
        className,
    ]);

    // #776 — surface `style.dimensions.minHeight` / `.aspectRatio` on
    // the wrapper so a Group opted into `supports.dimensions.minHeight`
    // matches the Blade renderer's inline style output. #883 — the
    // block's own Block spacing rides first, in `BlockSupports` order,
    // so the flex/grid baseline `gap` reads it.
    // F-4 — the background image rides first, as `BlockSupports::compile()`
    // emits it before spacing.
    const wrapperStyle = { ...applyBackground(attributes), ...applyBlockGap(attributes), ...applyDimensions(attributes) };

    if (Object.keys(wrapperStyle).length > 0) {
        return <Tag className={classes} style={wrapperStyle as React.CSSProperties}>{children}</Tag>;
    }

    return <Tag className={classes}>{children}</Tag>;
}

export function RowBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    // Renders `wp-block-group` markup, so the per-block layout compound
    // is keyed on `group` rather than `row` (#702).
    const classes = classList([
        'wp-block-group',
        ...layoutPair('group', 'is-layout-flex'),
        'is-horizontal',
        className,
    ]);

    return <div className={classes}>{children}</div>;
}

export function StackBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    // Renders `wp-block-group` markup, so the per-block layout compound
    // is keyed on `group` rather than `stack` (#702).
    const classes = classList([
        'wp-block-group',
        ...layoutPair('group', 'is-layout-flex'),
        'is-vertical',
        className,
    ]);

    return <div className={classes}>{children}</div>;
}

export function ColumnsBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    const isStacked =
        attributes.isStackedOnMobile === undefined ? true : attrBoolean(attributes.isStackedOnMobile);
    const verticalAlignment = attrString(attributes.verticalAlignment);

    const flexClasses = flexClassNames(attributes.artisanpackFlex);
    // Photo Grid wrapper scope stamped by `stampPhotoGridScopes()` (#714),
    // emitted in the same position the columns Blade partial merges it.
    const photoGridScope = attrString(attributes._vePhotoGridScope);
    // #702 — columns is a flex layout upstream and its wrapper carries
    // both the shared modifier and the per-block compound. Neither was
    // emitted here, so layout rules keyed on either never applied.
    const classes = classList([
        'wp-block-columns',
        ...layoutPair('columns', 'is-layout-flex'),
        isStacked ? 'is-stacked-on-mobile' : null,
        verticalAlignment !== '' ? `are-vertically-aligned-${verticalAlignment}` : null,
        ...flexClasses,
        photoGridScope !== '' ? photoGridScope : null,
        className,
    ]);

    // The Columns' own Block spacing, as `columns.blade.php` prints it.
    // #914 — plus the per-axis left gap for the Column width calc, last.
    const style = { ...applyBlockGap(attributes), ...applyColumnsGap(attributes) };

    if (Object.keys(style).length > 0) {
        return <div className={classes} style={style as React.CSSProperties}>{children}</div>;
    }

    return <div className={classes}>{children}</div>;
}

export function ColumnBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    const verticalAlignment = attrString(attributes.verticalAlignment);
    const flexClasses = flexClassNames(attributes.artisanpackFlex);

    // Column responsive-width scope stamped by `stampColumnWidthScopes()`
    // (#712). Appended last so the token lands in the same position the
    // Blade partial emits it (after every base / block-support class).
    const widthScope = attrString(attributes._veColumnWidthScope);
    // F-3 — the column's layout pair, per-instance scope and modifiers,
    // in `column.blade.php` order.
    const layoutScope = attrString(attributes._veLayoutScope);

    const classes = classList([
        'wp-block-column',
        ...layoutPair('column', panelLayoutClass(attributes)),
        layoutScope !== '' ? layoutScope : null,
        ...layoutModifierClasses(attributes),
        verticalAlignment !== '' ? `is-vertically-aligned-${verticalAlignment}` : null,
        ...flexClasses,
        className,
        widthScope !== '' ? widthScope : null,
    ]);

    const width = attributes.width;
    // The column's own Block spacing rides first, in `BlockSupports` order.
    let style: Record<string, string> = { ...applyBlockGap(attributes) };

    // Mirror the Blade partial's inline `flex-basis`: gate on PHP `empty()`
    // semantics (`isNonEmptyWidth` drops `0`/`'0'`/`''`/booleans/non-scalars)
    // and normalize through the shared `normalizeBasis` so `'60.0'` renders
    // as `60%` (not raw) and only whitelist-safe values are emitted (#720).
    if (isNonEmptyWidth(width)) {
        const normalized = normalizeBasis(width);

        if (normalized !== null && safeCssValue(normalized.basis) !== null) {
            style = { ...style, flexBasis: normalized.basis };
        }
    }

    return (
        <div className={classes} style={Object.keys(style).length > 0 ? (style as React.CSSProperties) : undefined}>
            {children}
        </div>
    );
}

const ALLOWED_JUSTIFY_CONTENT = [
    'left',
    'center',
    'right',
    'space-between',
    'space-around',
    'space-evenly',
    'stretch',
] as const;

export function ButtonsBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const layout = attrRecord(attributes.layout);
    // Whitelist `layout.justifyContent` against WP's enum so an authored
    // block can't inject an arbitrary class token; anything else falls back
    // to `left`, WP's own default. Mirrors `buttons.blade.php`.
    const rawJustify = attrString(layout.justifyContent);
    const justify = (ALLOWED_JUSTIFY_CONTENT as ReadonlyArray<string>).includes(rawJustify)
        ? rawJustify
        : 'left';
    const className = attrString(attributes.className);
    // F-3 — per-instance scope stamped by `stampLayoutScopes()`, then the
    // orientation / wrap modifiers, in `buttons.blade.php` order.
    const layoutScope = attrString(attributes._veLayoutScope);

    const classes = classList([
        'wp-block-buttons',
        ...layoutPair('buttons', 'is-layout-flex'),
        layoutScope !== '' ? layoutScope : null,
        `is-content-justification-${justify}`,
        ...layoutModifierClasses(attributes, true, false),
        className,
    ]);

    const gapStyle = applyBlockGap(attributes);

    if (Object.keys(gapStyle).length > 0) {
        return <div className={classes} style={gapStyle as React.CSSProperties}>{children}</div>;
    }

    return <div className={classes}>{children}</div>;
}

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

export function ButtonBlock({ attributes }: BlockRendererProps): JSX.Element {
    const text = attrString(attributes.text);
    const url = safeUrl(attributes.url);
    const linkTarget = attrString(attributes.linkTarget);
    const title = attrString(attributes.title);
    const className = attrString(attributes.className);

    const width = buttonWidth(attributes);
    const wrapperClasses = classList(['wp-block-button', ...width.classes, className]);
    const wrapperStyle = width.property ? ({ [width.property]: width.style } as React.CSSProperties) : undefined;
    const linkClasses = 'wp-block-button__link wp-element-button';

    let rel = attrString(attributes.rel);

    if (linkTarget === '_blank') {
        const tokens = rel.split(/\s+/).filter((t) => t !== '');

        for (const required of ['noopener', 'noreferrer']) {
            if (!tokens.includes(required)) {
                tokens.push(required);
            }
        }

        rel = tokens.join(' ');
    }

    return (
        <div className={wrapperClasses} style={wrapperStyle}>
            {url === '' ? (
                <span
                    className={linkClasses}
                    title={title === '' ? undefined : title}
                    dangerouslySetInnerHTML={{ __html: text }}
                />
            ) : (
                <a
                    className={linkClasses}
                    href={url}
                    target={linkTarget === '' ? undefined : linkTarget}
                    rel={rel === '' ? undefined : rel}
                    title={title === '' ? undefined : title}
                    dangerouslySetInnerHTML={{ __html: text }}
                />
            )}
        </div>
    );
}
