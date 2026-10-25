/**
 * React renderers for the Social Icons family (#1023). Mirrors the
 * `social-links` / `social-link` Blade partials and the Vue renderer so
 * every renderer emits identical markup — WordPress's `core/social-links`
 * markup, so the bundled block-library CSS styles it unchanged.
 *
 * The parent's settings reach each child as `_veSocialLinks`, stamped by
 * `stampSocialLinksContext()` before rendering.
 */

import type { JSX } from 'react';

import { attrRecord, attrString, classList, layoutPair } from '../../support/attributes';
import { compileBlockSupports } from '../../support/blockSupports';
import { safeCssValue } from '../../support/cssValue';
import { resolveAnchor } from '../../support/headingAnchors';
import { layoutModifierClasses } from '../../support/layoutContainer';
import { styleProp } from '../../support/styleProp';
import { safeUrl } from '../../support/urlSanitizer';
import type { BlockRendererProps } from '../../types';

import { SOCIAL_LINK_SERVICES, getSocialLinkService } from './socialLinkServices';

const ICON_SIZES: ReadonlyArray<string> = [
    'has-small-icon-size',
    'has-normal-icon-size',
    'has-large-icon-size',
    'has-huge-icon-size',
];

const ICON_BORDER_STYLES: ReadonlyArray<string> = ['none', 'solid', 'dashed', 'dotted', 'double'];

/**
 * The parent's per-icon styles as `--ap-social-links-*` properties, in
 * `social-links.blade.php` order. Only values that pass `safeCssValue()`
 * survive, and each property's `has-icon-*` class follows from it.
 */
function iconStyleProperties(attributes: Record<string, unknown>): Record<string, string> {
    const borderStyle = attrString(attributes.iconBorderStyle);
    const entries: Array<[string, string]> = [
        ['--ap-social-links-color', attrString(attributes.iconColorValue)],
        ['--ap-social-links-bg', attrString(attributes.iconBackgroundColorValue)],
        ['--ap-social-links-border-color', attrString(attributes.iconBorderColorValue)],
        ['--ap-social-links-border-width', attrString(attributes.iconBorderWidth)],
        ['--ap-social-links-border-style', ICON_BORDER_STYLES.includes(borderStyle) ? borderStyle : ''],
        ['--ap-social-links-radius', attrString(attributes.iconBorderRadius)],
        ['--ap-social-links-padding', attrString(attributes.iconPadding)],
        ['--ap-social-links-hover-color', attrString(attributes.iconHoverColorValue)],
        ['--ap-social-links-hover-bg', attrString(attributes.iconHoverBackgroundColorValue)],
        ['--ap-social-links-hover-border-color', attrString(attributes.iconHoverBorderColorValue)],
    ];
    const properties: Record<string, string> = {};

    for (const [property, raw] of entries) {
        const value = safeCssValue(raw);

        if (value !== null) {
            properties[property] = value;
        }
    }

    return properties;
}

// `BlockSupports::applyAlign()`: an allowlisted `align{value}` class, plus
// `has-text-align-{value}` for left / center / right.
const ALIGN_VALUES: ReadonlyArray<string> = ['left', 'center', 'right', 'wide', 'full'];

function alignClasses(align: string): string[] {
    if (!ALIGN_VALUES.includes(align)) {
        return [];
    }

    return ['left', 'center', 'right'].includes(align) ? [`align${align}`, `has-text-align-${align}`] : [`align${align}`];
}

// The same loose check as PHP's FILTER_VALIDATE_EMAIL for the Mail
// service's bare-address case.
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function SocialLinksBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const size = attrString(attributes.size);
    const align = attrString(attributes.align);
    const className = attrString(attributes.className);
    const layoutScope = attrString(attributes._veLayoutScope);
    const supports = compileBlockSupports(attributes);
    const iconStyle = iconStyleProperties(attributes);
    const has = (...names: string[]): boolean => names.some((name) => name in iconStyle);

    const classes = classList([
        'wp-block-artisanpack-social-links',
        'wp-block-social-links',
        ICON_SIZES.includes(size) ? size : null,
        attributes.showLabels === true ? 'has-visible-labels' : null,
        has('--ap-social-links-color') ? 'has-icon-color' : null,
        has('--ap-social-links-bg') ? 'has-icon-background-color' : null,
        has('--ap-social-links-border-color', '--ap-social-links-border-width', '--ap-social-links-border-style')
            ? 'has-icon-border'
            : null,
        has('--ap-social-links-radius') ? 'has-icon-border-radius' : null,
        has('--ap-social-links-padding') ? 'has-icon-padding' : null,
        has('--ap-social-links-hover-color') ? 'has-icon-hover-color' : null,
        has('--ap-social-links-hover-bg') ? 'has-icon-hover-background-color' : null,
        has('--ap-social-links-hover-border-color') ? 'has-icon-hover-border-color' : null,
        ...layoutPair('social-links', 'is-layout-flex'),
        layoutScope !== '' ? layoutScope : null,
        ...layoutModifierClasses(attributes),
        ...alignClasses(align),
        ...supports.classNames,
        className,
    ]);

    return (
        <ul
            id={resolveAnchor(attributes.anchor)}
            className={classes}
            style={styleProp({ ...supports.style, ...iconStyle })}
        >
            {children}
        </ul>
    );
}

export function SocialLinkBlock({ attributes }: BlockRendererProps): JSX.Element | null {
    const context = attrRecord(attributes._veSocialLinks);

    const rawService = attrString(attributes.service);
    const service = /^[a-z0-9-]+$/.test(rawService) ? rawService : '';
    const known = service !== '' && Object.prototype.hasOwnProperty.call(SOCIAL_LINK_SERVICES, service);
    const glyph = getSocialLinkService(service);

    let url = attrString(attributes.url).trim();

    if (service === 'mail' && url !== '' && !url.toLowerCase().startsWith('mailto:') && EMAIL_PATTERN.test(url)) {
        url = `mailto:${url}`;
    }

    const href = safeUrl(url);

    if (href === '') {
        return null;
    }

    const label = attrString(attributes.label).trim();
    const text = label !== '' ? label : known ? glyph.label : 'Social Icon';

    const newTab = context.openInNewTab === true;
    const relTokens = attrString(attributes.rel).trim().split(/\s+/);

    if (newTab) {
        relTokens.push('noopener', 'nofollow');
    }

    const rel = Array.from(new Set(relTokens.filter((token) => token !== ''))).join(' ');

    const supports = compileBlockSupports(attributes);

    const classes = classList([
        'wp-social-link',
        `wp-social-link-${service !== '' ? service : 'chain'}`,
        'wp-block-social-link',
        ...supports.classNames,
        attrString(attributes.className),
    ]);

    return (
        <li id={resolveAnchor(attributes.anchor)} className={classes} style={styleProp(supports.style)}>
            <a
                href={href}
                className="wp-block-social-link-anchor"
                rel={rel === '' ? undefined : rel}
                target={newTab ? '_blank' : undefined}
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox={glyph.viewBox}
                    width="24"
                    height="24"
                    aria-hidden="true"
                    focusable="false"
                >
                    <path fill="currentColor" d={glyph.path} />
                </svg>
                <span
                    className={`wp-block-social-link-label${context.showLabels === true ? '' : ' screen-reader-text'}`}
                >
                    {text}
                </span>
            </a>
        </li>
    );
}
