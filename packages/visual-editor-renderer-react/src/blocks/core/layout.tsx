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
    formatPercent,
} from '../../support/attributes';
import { safeUrl } from '../../support/urlSanitizer';
import type { BlockRendererProps } from '../../types';

const ALLOWED_GROUP_TAGS = ['div', 'section', 'article', 'aside', 'header', 'footer', 'main', 'nav'] as const;

type GroupTag = (typeof ALLOWED_GROUP_TAGS)[number];

export function GroupBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const tagName = attrString(attributes.tagName);
    const Tag: GroupTag = (ALLOWED_GROUP_TAGS as ReadonlyArray<string>).includes(tagName)
        ? (tagName as GroupTag)
        : 'div';

    const layout = attrRecord(attributes.layout);
    const layoutType = attrString(layout.type);
    const layoutClass =
        layoutType === 'constrained'
            ? 'is-layout-constrained'
            : layoutType === 'flex'
            ? 'is-layout-flex'
            : 'is-layout-flow';

    const className = attrString(attributes.className);
    const classes = classList(['wp-block-group', layoutClass, className]);

    return <Tag className={classes}>{children}</Tag>;
}

export function RowBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    const classes = classList(['wp-block-group', 'is-layout-flex', 'is-horizontal', className]);

    return <div className={classes}>{children}</div>;
}

export function StackBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    const classes = classList(['wp-block-group', 'is-layout-flex', 'is-vertical', className]);

    return <div className={classes}>{children}</div>;
}

export function ColumnsBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    const isStacked =
        attributes.isStackedOnMobile === undefined ? true : attrBoolean(attributes.isStackedOnMobile);
    const verticalAlignment = attrString(attributes.verticalAlignment);

    const classes = classList([
        'wp-block-columns',
        isStacked ? 'is-stacked-on-mobile' : null,
        verticalAlignment !== '' ? `are-vertically-aligned-${verticalAlignment}` : null,
        className,
    ]);

    return <div className={classes}>{children}</div>;
}

export function ColumnBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    const verticalAlignment = attrString(attributes.verticalAlignment);

    const classes = classList([
        'wp-block-column',
        verticalAlignment !== '' ? `is-vertically-aligned-${verticalAlignment}` : null,
        className,
    ]);

    const width = attributes.width;
    let style: React.CSSProperties | undefined;

    if (width !== undefined && width !== null && width !== '') {
        let basis = '';

        if (typeof width === 'number') {
            basis = formatPercent(width);
        } else if (typeof width === 'string' && width.trim() !== '') {
            const numeric = Number.parseFloat(width);

            basis = Number.isFinite(numeric) && String(numeric) === width.trim() ? formatPercent(numeric) : width;
        }

        if (basis !== '') {
            style = { flexBasis: basis };
        }
    }

    return (
        <div className={classes} style={style}>
            {children}
        </div>
    );
}

export function ButtonsBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const layout = attrRecord(attributes.layout);
    const justify = attrString(layout.justifyContent);
    const className = attrString(attributes.className);

    const classes = classList([
        'wp-block-buttons',
        'is-layout-flex',
        justify !== '' ? `is-content-justification-${justify}` : 'is-content-justification-left',
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
