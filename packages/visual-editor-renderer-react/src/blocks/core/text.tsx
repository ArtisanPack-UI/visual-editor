/**
 * Text-family core block renderers: paragraph, heading, list, list-item,
 * quote, code, preformatted, verse, and pullquote. Each one mirrors the
 * output of the matching Blade partial in
 * `artisanpack-ui/visual-editor-renderer-blade` so server-rendered and
 * React-rendered pages ship identical markup.
 */

import { attrBoolean, attrInt, attrRecord, attrString, classList, phpTrim } from '../../support/attributes';
import { applyBackground } from '../../support/background';
import { applyDimensions, hasDimensionStyle } from '../../support/dimensions';
import { resolveAnchor } from '../../support/headingAnchors';
import { presetClasses } from '../../support/presetClasses';
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
    const classes = classList([
        'wp-block-paragraph',
        align !== '' ? `has-text-align-${align}` : null,
        dropCap && !dropCapDisabled ? 'has-drop-cap' : null,
        className,
    ]);

    const props: Record<string, unknown> = {
        className: classes,
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

    // Preset colour / font-size classes and the anchor `id` match the
    // Blade partial's `BlockSupports::wrapperAttrs()` output (#888).
    const classes = classList([
        'wp-block-heading',
        textAlign !== '' ? `has-text-align-${textAlign}` : null,
        ...presetClasses(attributes),
        className,
    ]);

    const Tag = `h${level}` as 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6';

    return <Tag className={classes} id={resolveAnchor(attributes.anchor)} dangerouslySetInnerHTML={{ __html: content }} />;
}

export function ListBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const ordered = attrBoolean(attributes.ordered);
    const className = attrString(attributes.className);
    const legacyValues = attrString(attributes.values);
    const classes = classList(['wp-block-list', className]);

    const commonProps: Record<string, unknown> = { className: classes };

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
    const className = classList([attrString(attributes.className)]) || undefined;

    if (children === null || children === undefined) {
        return <li className={className} dangerouslySetInnerHTML={{ __html: content }} />;
    }

    return (
        <li className={className}>
            <span dangerouslySetInnerHTML={{ __html: content }} />
            {children}
        </li>
    );
}

export function QuoteBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    const citation = attrString(attributes.citation);
    const classes = classList(['wp-block-quote', className]);
    const style = backgroundAndDimensions(attributes);

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

    return (
        <pre className="wp-block-code">
            <code dangerouslySetInnerHTML={{ __html: content }} />
        </pre>
    );
}

export function PreformattedBlock({ attributes }: BlockRendererProps): JSX.Element {
    const content = attrString(attributes.content);

    return (
        <pre className="wp-block-preformatted" dangerouslySetInnerHTML={{ __html: content }} />
    );
}

export function VerseBlock({ attributes }: BlockRendererProps): JSX.Element {
    const content = attrString(attributes.content);
    const textAlign = attrString(attributes.textAlign);
    const classes = classList([
        'wp-block-verse',
        textAlign !== '' ? `has-text-align-${textAlign}` : null,
    ]);
    const style = backgroundAndDimensions(attributes);

    return <pre className={classes} style={style} dangerouslySetInnerHTML={{ __html: content }} />;
}

export function PullquoteBlock({ attributes }: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    const value = attrString(attributes.value);
    const citation = attrString(attributes.citation);
    const classes = classList(['wp-block-pullquote', className]);
    const style = backgroundAndDimensions(attributes);

    return (
        <figure className={classes} style={style}>
            {/* The value's HTML goes straight into the blockquote, as in
                `pullquote.blade.php` (no wrapping element). */}
            <blockquote dangerouslySetInnerHTML={{ __html: pullquoteInnerHtml(value, citation) }} />
        </figure>
    );
}

/**
 * The wrapper style quote, pullquote and verse share: the background image
 * (F-4) and then the dimensions, in `BlockSupports::compile()` order.
 * `undefined` when neither is set.
 */
function backgroundAndDimensions(attributes: Record<string, unknown>): React.CSSProperties | undefined {
    const dimensionStyle = applyDimensions(attributes);
    const style = { ...applyBackground(attributes), ...(hasDimensionStyle(dimensionStyle) ? dimensionStyle : {}) };

    return Object.keys(style).length > 0 ? (style as React.CSSProperties) : undefined;
}

/**
 * The pullquote `<blockquote>` content, as `pullquote.blade.php` prints it:
 * the value's HTML, then the citation in a `<cite>`, each only when it has
 * non-whitespace content.
 */
function pullquoteInnerHtml(value: string, citation: string): string {
    return (phpTrim(value) !== '' ? value : '') + (phpTrim(citation) !== '' ? `<cite>${citation}</cite>` : '');
}
