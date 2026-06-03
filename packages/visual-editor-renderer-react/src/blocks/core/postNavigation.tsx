/**
 * Post navigation / metadata family renderers (#520): post-navigation-link,
 * post-terms, read-more, term-description. Each block reads stamped
 * `_resolved*` attributes that the host-side `PostResolver` writes onto the
 * block tree before rendering — the renderer itself never reaches out to a
 * data store, so it stays pure and identical to the Blade and Vue
 * counterparts.
 *
 * When a `_resolved*` attribute is missing the block emits a Gutenberg-shaped
 * empty shell (correct wrapper, classes) so the editor preview and the
 * front-end render structure agree even before a resolver is wired up.
 */

import { attrString, classList } from '../../support/attributes';
import { safeUrl } from '../../support/urlSanitizer';
import type { BlockRendererProps } from '../../types';

interface TermRecord {
    readonly name?: string;
    readonly slug?: string;
    readonly url?: string;
}

function readTerms(value: unknown, taxonomy: string): ReadonlyArray<TermRecord> {
    if (value === null || typeof value !== 'object' || taxonomy === '') {
        return [];
    }

    const entry = (value as Record<string, unknown>)[taxonomy];

    if (!Array.isArray(entry)) {
        return [];
    }

    return entry.filter(
        (term): term is TermRecord => term !== null && typeof term === 'object'
    );
}

function arrowFor(type: 'next' | 'previous', arrow: string): string {
    if (arrow === 'arrow') {
        return type === 'previous' ? '←' : '→';
    }

    if (arrow === 'chevron') {
        return type === 'previous' ? '«' : '»';
    }

    return '';
}

export function PostNavigationLinkBlock({ attributes }: BlockRendererProps): JSX.Element {
    const type = attrString(attributes.type, 'next') === 'previous' ? 'previous' : 'next';
    const label = attrString(attributes.label);
    const showTitle = !!attributes.showTitle;
    const arrow = attrString(attributes.arrow, 'none');
    const className = attrString(attributes.className);

    const urlKey = type === 'previous' ? '_resolvedPrevUrl' : '_resolvedNextUrl';
    const titleKey = type === 'previous' ? '_resolvedPrevTitle' : '_resolvedNextTitle';

    const url = safeUrl(attrString(attributes[urlKey]));
    const title = attrString(attributes[titleKey]);

    const classes = classList(['wp-block-post-navigation-link', className]);

    if (url === '') {
        return <div className={classes} />;
    }

    let visible: string;
    if (showTitle) {
        visible = label !== '' ? `${label}${title}` : title;
    } else if (label !== '') {
        visible = label;
    } else {
        visible = title;
    }

    const glyph = arrowFor(type, arrow);

    if (glyph !== '') {
        visible = type === 'previous' ? `${glyph} ${visible}` : `${visible} ${glyph}`;
    }

    const ariaLabel = type === 'previous' ? 'Previous post' : 'Next post';

    return (
        <div className={classes}>
            <a href={url} aria-label={ariaLabel}>
                {visible}
            </a>
        </div>
    );
}

export function PostTermsBlock({ attributes }: BlockRendererProps): JSX.Element {
    const taxonomy = attrString(attributes.term);
    const separator = attrString(attributes.separator, ', ');
    const prefix = attrString(attributes.prefix);
    const suffix = attrString(attributes.suffix);
    const className = attrString(attributes.className);

    const terms = readTerms(attributes._resolvedTermsByTaxonomy, taxonomy);

    const classes = classList([
        'wp-block-post-terms',
        // Only attach the `taxonomy-{slug}` class when a taxonomy has
        // actually been selected — a bare `taxonomy-` (with no slug) is
        // meaningless styling noise.
        taxonomy !== '' ? `taxonomy-${taxonomy}` : null,
        className,
    ]);

    const usableTerms = terms.filter((term) => attrString(term.name) !== '');

    if (usableTerms.length === 0) {
        return <div className={classes} />;
    }

    const items: Array<JSX.Element | string> = [];

    usableTerms.forEach((term, index) => {
        if (index > 0) {
            // Upstream wraps the separator so themes can style it
            // (see `wp-block-post-terms__separator` rule).
            items.push(
                <span key={`sep-${index}`} className="wp-block-post-terms__separator">
                    {separator}
                </span>
            );
        }

        const name = attrString(term.name);
        const url = safeUrl(attrString(term.url));

        if (url === '') {
            // Upstream `get_the_term_list` always produces anchors; we
            // degrade gracefully to plain text when the host can't
            // resolve a permalink for the term — matches the Blade
            // partial's bare-name path.
            items.push(name);
        } else {
            items.push(
                <a key={`term-${index}`} href={url}>
                    {name}
                </a>
            );
        }
    });

    return (
        <div className={classes}>
            {prefix !== '' ? <span className="wp-block-post-terms__prefix">{prefix}</span> : null}
            {items}
            {suffix !== '' ? <span className="wp-block-post-terms__suffix">{suffix}</span> : null}
        </div>
    );
}

export function ReadMoreBlock({ attributes }: BlockRendererProps): JSX.Element {
    const content = attrString(attributes.content) !== '' ? attrString(attributes.content) : 'Read more';
    const linkTarget = attrString(attributes.linkTarget, '_self') === '_blank' ? '_blank' : '_self';
    const className = attrString(attributes.className);
    const permalink = safeUrl(attrString(attributes._resolvedPermalink));

    const classes = classList(['wp-block-read-more', className]);

    const props: Record<string, string> = { className: classes };

    if (permalink !== '') {
        props.href = permalink;
    }

    if (linkTarget === '_blank') {
        props.target = '_blank';
        props.rel = 'noopener noreferrer';
    }

    return <a {...props}>{content}</a>;
}

export function TermDescriptionBlock({ attributes }: BlockRendererProps): JSX.Element {
    const description = attrString(attributes._resolvedTermDescription);
    const className = attrString(attributes.className);

    const classes = classList(['wp-block-term-description', className]);

    if (description === '') {
        return <div className={classes} />;
    }

    return (
        <div className={classes}>
            <p dangerouslySetInnerHTML={{ __html: description }} />
        </div>
    );
}
