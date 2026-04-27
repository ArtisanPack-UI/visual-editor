/**
 * Site-context core block renderers: site-title, site-tagline, site-logo.
 *
 * Each block reads the site-level values from `_resolved*` attributes a
 * host-side resolver stamps onto the block tree. Mirrors the React + Blade
 * renderers exactly.
 */

import { defineComponent, h } from 'vue';
import { attrBoolean, attrInt, attrString, classList } from '../../support/attributes';
import { safeUrl } from '../../support/urlSanitizer';
import { blockRendererProps } from '../shared';

const SITE_TITLE_LINK_REL = 'home';

function siteLinkProps(href: string, linkTarget: string): Record<string, string> {
    return {
        href,
        target: linkTarget === '_blank' ? '_blank' : '_self',
        rel: linkTarget === '_blank' ? 'noopener noreferrer' : SITE_TITLE_LINK_REL,
    };
}

export const SiteTitleBlock = defineComponent({
    name: 'SiteTitleBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const rawLevel = attrInt(props.attributes.level, 1);
            const level = Math.max(0, Math.min(6, rawLevel));
            const align = attrString(props.attributes.textAlign);
            const isLink = attrBoolean(props.attributes.isLink, true);
            const linkTarget = attrString(props.attributes.linkTarget, '_self');
            const className = attrString(props.attributes.className);

            const title = attrString(props.attributes._resolvedSiteTitle);
            const siteUrl = safeUrl(props.attributes._resolvedSiteUrl);

            const tag = level === 0 ? 'p' : `h${level}`;

            const classes = classList([
                'wp-block-site-title',
                align !== '' ? `has-text-align-${align}` : null,
                className,
            ]);

            if (isLink && siteUrl !== '') {
                const linkProps = siteLinkProps(siteUrl, linkTarget);

                return h(tag, { class: classes }, [h('a', linkProps, title)]);
            }

            return h(tag, { class: classes }, title);
        };
    },
});

export const SiteTaglineBlock = defineComponent({
    name: 'SiteTaglineBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const align = attrString(props.attributes.textAlign);
            const className = attrString(props.attributes.className);

            const tagline = attrString(props.attributes._resolvedSiteTagline);

            const classes = classList([
                'wp-block-site-tagline',
                align !== '' ? `has-text-align-${align}` : null,
                className,
            ]);

            return h('p', { class: classes }, tagline);
        };
    },
});

export const SiteLogoBlock = defineComponent({
    name: 'SiteLogoBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const width = Math.max(0, attrInt(props.attributes.width));
            const isLink = attrBoolean(props.attributes.isLink, true);
            const linkTarget = attrString(props.attributes.linkTarget, '_self');
            const className = attrString(props.attributes.className);

            const logoUrl = safeUrl(props.attributes._resolvedLogoUrl);
            const siteUrl = safeUrl(props.attributes._resolvedSiteUrl);
            const alt = attrString(props.attributes._resolvedSiteTitle);

            const classes = classList([
                'wp-block-site-logo',
                width <= 0 ? 'is-default-size' : null,
                className,
            ]);

            if (logoUrl === '') {
                return h('div', { class: classes });
            }

            const imgProps: Record<string, unknown> = {
                src: logoUrl,
                alt,
                class: 'custom-logo',
            };

            if (width > 0) {
                imgProps.width = width;
            }

            const img = h('img', imgProps);

            if (isLink && siteUrl !== '') {
                const linkProps = siteLinkProps(siteUrl, linkTarget);

                return h('div', { class: classes }, [
                    h('a', { ...linkProps, class: 'custom-logo-link' }, [img]),
                ]);
            }

            return h('div', { class: classes }, [img]);
        };
    },
});
