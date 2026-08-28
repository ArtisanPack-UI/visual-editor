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
    layoutClass,
    layoutPair,
    phpTrim,
} from '../../support/attributes';
import { isNonEmptyWidth, normalizeBasis } from '../../support/columnWidth';
import { flexClassNames } from '../../support/flex-serializer';
import { safeCssValue } from '../../support/cssValue';
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
    const rawLayoutType = attrRecord(attributes.layout).type;
    const storedLayoutType = typeof rawLayoutType === 'string' ? phpTrim(rawLayoutType) : '';
    let groupLayoutClass = layoutClass(attributes);

    if (storedLayoutType === '' && flexClasses.includes('ap-flex')) {
        groupLayoutClass = 'is-layout-flex';
    }

    // #702 — the block-library CSS targets the per-block compound
    // (`wp-block-group-is-layout-constrained`), so emit it alongside the
    // shared modifier. `layoutClass()` also covers the `grid` type the
    // old inline ternary silently rendered as flow.
    // Photo Grid wrapper scope stamped by `stampPhotoGridScopes()` (#714).
    // Emitted after the flex classes and before the author `className`, the
    // same position the Blade partial merges it into `$wrapperBaseClasses`.
    const photoGridScope = attrString(attributes._vePhotoGridScope);

    const classes = classList([
        'wp-block-group',
        ...layoutPair('group', groupLayoutClass),
        ...flexClasses,
        photoGridScope !== '' ? photoGridScope : null,
        className,
    ]);

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

    const classes = classList([
        'wp-block-column',
        verticalAlignment !== '' ? `is-vertically-aligned-${verticalAlignment}` : null,
        ...flexClasses,
        className,
        widthScope !== '' ? widthScope : null,
    ]);

    const width = attributes.width;
    let style: React.CSSProperties | undefined;

    // Mirror the Blade partial's inline `flex-basis`: gate on PHP `empty()`
    // semantics (`isNonEmptyWidth` drops `0`/`'0'`/`''`/booleans/non-scalars)
    // and normalize through the shared `normalizeBasis` so `'60.0'` renders
    // as `60%` (not raw) and only whitelist-safe values are emitted (#720).
    if (isNonEmptyWidth(width)) {
        const normalized = normalizeBasis(width);

        if (normalized !== null && safeCssValue(normalized.basis) !== null) {
            style = { flexBasis: normalized.basis };
        }
    }

    return (
        <div className={classes} style={style}>
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

    const classes = classList([
        'wp-block-buttons',
        ...layoutPair('buttons', 'is-layout-flex'),
        `is-content-justification-${justify}`,
        className,
    ]);

    return <div className={classes}>{children}</div>;
}

export function ButtonBlock({ attributes }: BlockRendererProps): JSX.Element {
    const text = attrString(attributes.text);
    const url = safeUrl(attributes.url);
    const linkTarget = attrString(attributes.linkTarget);
    const title = attrString(attributes.title);
    const className = attrString(attributes.className);

    const wrapperClasses = classList(['wp-block-button', className]);
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
        <div className={wrapperClasses}>
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
