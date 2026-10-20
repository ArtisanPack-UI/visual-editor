/**
 * Text-family core block renderers: paragraph, heading, list, list-item,
 * quote, code, preformatted, verse, and pullquote. Each one mirrors the
 * output of the matching Blade partial in
 * `artisanpack-ui/visual-editor-renderer-blade` so server-rendered and
 * React-rendered pages ship identical markup.
 */

import { attrBoolean, attrInt, attrRecord, attrString, classList, layoutClass, phpTrim } from '../../support/attributes';
import { layoutWrapperClasses } from '../../support/layoutContainer';
import { resolveAnchor } from '../../support/headingAnchors';
import { compileBlockSupports } from '../../support/blockSupports';
import { styleProp } from '../../support/styleProp';
import type { BlockRendererProps } from '../../types';

export function ParagraphBlock({ attributes }: BlockRendererProps): JSX.Element {
    const style = attrRecord(attributes.style);
    const typography = attrRecord(style.typography);
    const align = attrString(typography.textAlign, attrString(attributes.align));
    const className = attrString(attributes.className);
    const content = attrString(attributes.content);
    const dropCap = attrBoolean(attributes.dropCap);
    const direction = attrString(attributes.direction);

    // Upstream save.js disables the drop-cap when the text alignment
    // matches the reading-end side — `right` in LTR, `left` in RTL —
    // or when it's centered. The block's own `direction` attribute
    // ('ltr' | 'rtl') is the per-block override; absent that we
    // assume LTR (the renderer has no signal for global page RTL).
    const isRtl = direction === 'rtl';
    const dropCapDisabled =
        align === 'center' || align === (isRtl ? 'left' : 'right');
    // #920 — the `paragraph.blade.php` supports.
    const supports = compileBlockSupports(attributes);
    const classes = classList([
        'wp-block-paragraph',
        align !== '' ? `has-text-align-${align}` : null,
        dropCap && !dropCapDisabled ? 'has-drop-cap' : null,
        ...supports.classNames,
        className,
    ]);

    const props: Record<string, unknown> = {
        className: classes,
        style: styleProp(supports.style),
        dangerouslySetInnerHTML: { __html: content },
    };

    if (direction === 'ltr' || direction === 'rtl') {
        props.dir = direction;
    }

    return <p {...props} />;
}

export function HeadingBlock({ attributes }: BlockRendererProps): JSX.Element {
    const rawLevel = attrInt(attributes.level, 2);
    const level = Math.max(1, Math.min(6, rawLevel));
    const textAlign = attrString(attributes.textAlign);
    const className = attrString(attributes.className);
    const content = attrString(attributes.content);

    // The supports (#888, #920) and the anchor `id` match the Blade
    // partial's `BlockSupports::wrapperAttrs()` output.
    const supports = compileBlockSupports(attributes);
    const classes = classList([
        'wp-block-heading',
        textAlign !== '' ? `has-text-align-${textAlign}` : null,
        ...supports.classNames,
        className,
    ]);

    const Tag = `h${level}` as 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6';

    return (
        <Tag
            className={classes}
            id={resolveAnchor(attributes.anchor)}
            style={styleProp(supports.style)}
            dangerouslySetInnerHTML={{ __html: content }}
        />
    );
}

export function ListBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const ordered = attrBoolean(attributes.ordered);
    const className = attrString(attributes.className);
    const legacyValues = attrString(attributes.values);
    const supports = compileBlockSupports(attributes);
    const classes = classList(['wp-block-list', ...supports.classNames, className]);

    const commonProps: Record<string, unknown> = { className: classes, style: styleProp(supports.style) };

    if (ordered) {
        if (attributes.start !== undefined && attributes.start !== null) {
            commonProps.start = attrInt(attributes.start);
        }

        if (attrBoolean(attributes.reversed)) {
            commonProps.reversed = true;
        }
    }

    const hasChildren = children !== null && children !== undefined;

    if (hasChildren) {
        return ordered ? <ol {...commonProps}>{children}</ol> : <ul {...commonProps}>{children}</ul>;
    }

    if (legacyValues === '') {
        return ordered ? <ol {...commonProps} /> : <ul {...commonProps} />;
    }

    const htmlProps = { ...commonProps, dangerouslySetInnerHTML: { __html: legacyValues } };

    return ordered ? <ol {...htmlProps} /> : <ul {...htmlProps} />;
}

export function ListItemBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const content = attrString(attributes.content);
    const supports = compileBlockSupports(attributes);
    const className = classList([...supports.classNames, attrString(attributes.className)]) || undefined;
    const style = styleProp(supports.style);

    if (children === null || children === undefined) {
        return <li className={className} style={style} dangerouslySetInnerHTML={{ __html: content }} />;
    }

    return (
        <li className={className} style={style}>
            <span dangerouslySetInnerHTML={{ __html: content }} />
            {children}
        </li>
    );
}

export function QuoteBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    const citation = attrString(attributes.citation);
    const supports = compileBlockSupports(attributes);
    // F-3 — layout pair, per-instance scope and modifiers, as in `quote.blade.php`.
    const classes = classList([
        'wp-block-quote',
        ...layoutWrapperClasses(attributes, 'quote', layoutClass(attributes)),
        ...supports.classNames,
        className,
    ]);
    const style = styleProp(supports.style);

    return (
        <blockquote className={classes} style={style}>
            {children}
            {citation.trim() !== '' ? (
                <cite dangerouslySetInnerHTML={{ __html: citation }} />
            ) : null}
        </blockquote>
    );
}

export function CodeBlock({ attributes }: BlockRendererProps): JSX.Element {
    const content = attrString(attributes.content);
    const supports = compileBlockSupports(attributes);
    // `className` carries the `ve-r-*` / `ve-fx-*` scopes too (M-7).
    const classes = classList(['wp-block-code', ...supports.classNames, attrString(attributes.className)]);

    return (
        <pre className={classes} style={styleProp(supports.style)}>
            <code dangerouslySetInnerHTML={{ __html: content }} />
        </pre>
    );
}

export function PreformattedBlock({ attributes }: BlockRendererProps): JSX.Element {
    const content = attrString(attributes.content);

    const supports = compileBlockSupports(attributes);
    const classes = classList(['wp-block-preformatted', ...supports.classNames, attrString(attributes.className)]);

    return <pre className={classes} style={styleProp(supports.style)} dangerouslySetInnerHTML={{ __html: content }} />;
}

export function VerseBlock({ attributes }: BlockRendererProps): JSX.Element {
    const content = attrString(attributes.content);
    const textAlign = attrString(attributes.textAlign);
    const supports = compileBlockSupports(attributes);
    const classes = classList([
        'wp-block-verse',
        textAlign !== '' ? `has-text-align-${textAlign}` : null,
        ...supports.classNames,
        attrString(attributes.className),
    ]);
    const style = styleProp(supports.style);

    return <pre className={classes} style={style} dangerouslySetInnerHTML={{ __html: content }} />;
}

export function PullquoteBlock({ attributes }: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    const value = attrString(attributes.value);
    const citation = attrString(attributes.citation);
    const supports = compileBlockSupports(attributes);
    const classes = classList(['wp-block-pullquote', ...supports.classNames, className]);
    const style = styleProp(supports.style);

    return (
        <figure className={classes} style={style}>
            {/* The value's HTML goes straight into the blockquote, as in
                `pullquote.blade.php` (no wrapping element). */}
            <blockquote dangerouslySetInnerHTML={{ __html: pullquoteInnerHtml(value, citation) }} />
        </figure>
    );
}

/**
 * The pullquote `<blockquote>` content, as `pullquote.blade.php` prints it:
 * the value's HTML, then the citation in a `<cite>`, each only when it has
 * non-whitespace content.
 */
function pullquoteInnerHtml(value: string, citation: string): string {
    return (phpTrim(value) !== '' ? value : '') + (phpTrim(citation) !== '' ? `<cite>${citation}</cite>` : '');
}
