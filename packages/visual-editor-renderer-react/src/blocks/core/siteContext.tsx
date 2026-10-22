/**
 * Site-context core block renderers: site-title, site-tagline, site-logo.
 *
 * Each block reads the site-level values from `_resolved*` attributes a
 * host-side resolver stamps onto the block tree. When a value is missing
 * the renderer emits a Gutenberg-shaped empty shell so the editor preview
 * and the front-end render structure agree even before a resolver is wired
 * up.
 */

import { attrBoolean, attrInt, attrString, classList } from '../../support/attributes';
import { compileBlockSupports } from '../../support/blockSupports';
import { styleProp } from '../../support/styleProp';
import { safeUrl } from '../../support/urlSanitizer';
import type { BlockRendererProps } from '../../types';

const SITE_TITLE_LINK_REL = 'home';

function siteLinkProps(href: string, linkTarget: string): Record<string, string> {
    return {
        href,
        target: linkTarget === '_blank' ? '_blank' : '_self',
        rel: linkTarget === '_blank' ? 'noopener noreferrer' : SITE_TITLE_LINK_REL,
    };
}

export function SiteTitleBlock({ attributes }: BlockRendererProps): JSX.Element {
    const rawLevel = attrInt(attributes.level, 1);
    const level = Math.max(0, Math.min(6, rawLevel));
    const align = attrString(attributes.textAlign);
    const isLink = attrBoolean(attributes.isLink, true);
    const linkTarget = attrString(attributes.linkTarget, '_self');
    const className = attrString(attributes.className);

    const title = attrString(attributes._resolvedSiteTitle);
    const siteUrl = safeUrl(attrString(attributes._resolvedSiteUrl));

    const Tag = (level === 0 ? 'p' : (`h${level}` as 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6')) as
        | 'p'
        | 'h1'
        | 'h2'
        | 'h3'
        | 'h4'
        | 'h5'
        | 'h6';

    // #920 — the supports `site-title.blade.php` prints.
    const supports = compileBlockSupports(attributes);
    const classes = classList([
        'wp-block-site-title',
        align !== '' ? `has-text-align-${align}` : null,
        ...supports.classNames,
        className,
    ]);
    const style = styleProp(supports.style);

    if (isLink && siteUrl !== '') {
        const linkProps = siteLinkProps(siteUrl, linkTarget);

        return (
            <Tag className={classes} style={style}>
                <a {...linkProps}>{title}</a>
            </Tag>
        );
    }

    return <Tag className={classes} style={style}>{title}</Tag>;
}

export function SiteTaglineBlock({ attributes }: BlockRendererProps): JSX.Element {
    const align = attrString(attributes.textAlign);
    const className = attrString(attributes.className);

    const tagline = attrString(attributes._resolvedSiteTagline);

    const supports = compileBlockSupports(attributes);
    const classes = classList([
        'wp-block-site-tagline',
        align !== '' ? `has-text-align-${align}` : null,
        ...supports.classNames,
        className,
    ]);

    return <p className={classes} style={styleProp(supports.style)}>{tagline}</p>;
}

export function SiteLogoBlock({ attributes }: BlockRendererProps): JSX.Element {
    const width = Math.max(0, attrInt(attributes.width));
    const isLink = attrBoolean(attributes.isLink, true);
    const linkTarget = attrString(attributes.linkTarget, '_self');
    const className = attrString(attributes.className);

    const logoUrl = safeUrl(attrString(attributes._resolvedLogoUrl));
    const siteUrl = safeUrl(attrString(attributes._resolvedSiteUrl));
    const alt = attrString(attributes._resolvedSiteTitle);

    const supports = compileBlockSupports(attributes);
    const classes = classList([
        'wp-block-site-logo',
        width <= 0 ? 'is-default-size' : null,
        // Duotone scope stamped by `stampDuotoneScopes()`.
        attrString(attributes._veDuotone) || null,
        ...supports.classNames,
        className,
    ]);
    const style = styleProp(supports.style);

    if (logoUrl === '') {
        return <div className={classes} style={style} />;
    }

    const imgProps: Record<string, unknown> = {
        src: logoUrl,
        alt,
        className: 'custom-logo',
    };

    if (width > 0) {
        imgProps.width = width;
    }

    const img = <img {...(imgProps as JSX.IntrinsicElements['img'])} />;

    if (isLink && siteUrl !== '') {
        const linkProps = siteLinkProps(siteUrl, linkTarget);

        return (
            <div className={classes} style={style}>
                <a {...linkProps} className="custom-logo-link">
                    {img}
                </a>
            </div>
        );
    }

    return <div className={classes} style={style}>{img}</div>;
}
