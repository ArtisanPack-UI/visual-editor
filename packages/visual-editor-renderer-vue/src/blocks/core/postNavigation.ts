/**
 * Post navigation / metadata family renderers (#520): post-navigation-link,
 * post-terms, read-more, term-description. Each block reads the actual
 * post data from `_resolved*` attributes that a host-side resolver stamps
 * onto the block tree before rendering — the renderer itself never reaches
 * out to a data store, so it stays pure and identical to the Blade and React
 * counterparts.
 *
 * When a `_resolved*` attribute is missing the block emits a Gutenberg-shaped
 * empty shell (correct wrapper, classes) so the editor preview and the
 * front-end render structure agree even before a resolver is wired up.
 */

import { defineComponent, h } from 'vue';
import type { VNode } from 'vue';

import { attrString, classList } from '../../support/attributes';
import { safeUrl } from '../../support/urlSanitizer';
import { blockRendererProps } from '../shared';

interface TermRecord {
    name?: string;
    slug?: string;
    url?: string;
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

export const PostNavigationLinkBlock = defineComponent({
    name: 'PostNavigationLinkBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const type =
                attrString(props.attributes.type, 'next') === 'previous'
                    ? 'previous'
                    : 'next';
            const label = attrString(props.attributes.label);
            const showTitle = !!props.attributes.showTitle;
            const arrow = attrString(props.attributes.arrow, 'none');
            const className = attrString(props.attributes.className);

            const urlKey = type === 'previous' ? '_resolvedPrevUrl' : '_resolvedNextUrl';
            const titleKey = type === 'previous' ? '_resolvedPrevTitle' : '_resolvedNextTitle';

            const url = safeUrl(attrString(props.attributes[urlKey]));
            const title = attrString(props.attributes[titleKey]);

            const classes = classList(['wp-block-post-navigation-link', className]);

            if (url === '') {
                return h('div', { class: classes });
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

            return h('div', { class: classes }, [
                h('a', { href: url, 'aria-label': ariaLabel }, visible),
            ]);
        };
    },
});

export const PostTermsBlock = defineComponent({
    name: 'PostTermsBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const taxonomy = attrString(props.attributes.term);
            const separator = attrString(props.attributes.separator, ', ');
            const prefix = attrString(props.attributes.prefix);
            const suffix = attrString(props.attributes.suffix);
            const className = attrString(props.attributes.className);

            const terms = readTerms(props.attributes._resolvedTermsByTaxonomy, taxonomy);

            const classes = classList([
                'wp-block-post-terms',
                // Only attach the `taxonomy-{slug}` class when a taxonomy
                // has actually been selected — a bare `taxonomy-` (with
                // no slug) is meaningless styling noise.
                taxonomy !== '' ? `taxonomy-${taxonomy}` : null,
                className,
            ]);

            const usableTerms = terms.filter((term) => attrString(term.name) !== '');

            if (usableTerms.length === 0) {
                return h('div', { class: classes });
            }

            const children: Array<VNode | string> = [];

            if (prefix !== '') {
                children.push(h('span', { class: 'wp-block-post-terms__prefix' }, prefix));
            }

            usableTerms.forEach((term, index) => {
                if (index > 0) {
                    // Upstream wraps the separator so themes can style it
                    // (see `wp-block-post-terms__separator` rule).
                    children.push(
                        h('span', { class: 'wp-block-post-terms__separator' }, separator)
                    );
                }

                const name = attrString(term.name);
                const url = safeUrl(attrString(term.url));

                if (url === '') {
                    // Upstream `get_the_term_list` always produces anchors;
                    // we degrade gracefully to plain text when the host
                    // can't resolve a permalink for the term.
                    children.push(name);
                } else {
                    children.push(h('a', { href: url }, name));
                }
            });

            if (suffix !== '') {
                children.push(h('span', { class: 'wp-block-post-terms__suffix' }, suffix));
            }

            return h('div', { class: classes }, children);
        };
    },
});

export const ReadMoreBlock = defineComponent({
    name: 'ReadMoreBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const contentAttr = attrString(props.attributes.content);
            const content = contentAttr !== '' ? contentAttr : 'Read more';
            const linkTarget =
                attrString(props.attributes.linkTarget, '_self') === '_blank' ? '_blank' : '_self';
            const className = attrString(props.attributes.className);
            const permalink = safeUrl(attrString(props.attributes._resolvedPermalink));

            const classes = classList(['wp-block-read-more', className]);

            const anchorProps: Record<string, string> = { class: classes };

            if (permalink !== '') {
                anchorProps.href = permalink;
            }

            if (linkTarget === '_blank') {
                anchorProps.target = '_blank';
                anchorProps.rel = 'noopener noreferrer';
            }

            return h('a', anchorProps, content);
        };
    },
});

export const TermDescriptionBlock = defineComponent({
    name: 'TermDescriptionBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const description = attrString(props.attributes._resolvedTermDescription);
            const className = attrString(props.attributes.className);

            const classes = classList(['wp-block-term-description', className]);

            if (description === '') {
                return h('div', { class: classes });
            }

            return h('div', { class: classes }, [h('p', { innerHTML: description })]);
        };
    },
});
