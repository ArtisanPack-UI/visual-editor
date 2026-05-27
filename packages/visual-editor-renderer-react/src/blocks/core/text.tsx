/**
 * Text-family core block renderers: paragraph, heading, list, list-item,
 * quote, code, preformatted, verse, and pullquote. Each one mirrors the
 * output of the matching Blade partial in
 * `artisanpack-ui/visual-editor-renderer-blade` so server-rendered and
 * React-rendered pages ship identical markup.
 */

import { attrBoolean, attrInt, attrString, classList } from '../../support/attributes';
import type { BlockRendererProps } from '../../types';

export function ParagraphBlock({ attributes }: BlockRendererProps): JSX.Element {
    const align = attrString(attributes.align);
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

    const classes = classList([
        'wp-block-heading',
        textAlign !== '' ? `has-text-align-${textAlign}` : null,
        className,
    ]);

    const Tag = `h${level}` as 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6';

    return <Tag className={classes} dangerouslySetInnerHTML={{ __html: content }} />;
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

    if (children === null || children === undefined) {
        return <li dangerouslySetInnerHTML={{ __html: content }} />;
    }

    return (
        <li>
            <span dangerouslySetInnerHTML={{ __html: content }} />
            {children}
        </li>
    );
}

export function QuoteBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    const citation = attrString(attributes.citation);
    const classes = classList(['wp-block-quote', className]);

    return (
        <blockquote className={classes}>
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

    return <pre className={classes} dangerouslySetInnerHTML={{ __html: content }} />;
}

export function PullquoteBlock({ attributes }: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    const value = attrString(attributes.value);
    const citation = attrString(attributes.citation);
    const classes = classList(['wp-block-pullquote', className]);

    return (
        <figure className={classes}>
            <blockquote>
                {value.trim() !== '' ? (
                    <span dangerouslySetInnerHTML={{ __html: value }} />
                ) : null}
                {citation.trim() !== '' ? (
                    <cite dangerouslySetInnerHTML={{ __html: citation }} />
                ) : null}
            </blockquote>
        </figure>
    );
}
