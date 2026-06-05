/**
 * `core/navigation`, `core/navigation-link`, and `core/navigation-submenu`
 * renderers. The container block lays its inner blocks out as the menu
 * `<ul>` so menu trees authored in the site editor render server- and
 * client-side with the same structure.
 */

import { attrBoolean, attrString, classList } from '../../support/attributes';
import { safeUrl } from '../../support/urlSanitizer';
import type { BlockRendererProps } from '../../types';

export function NavigationBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const orientation = attrString(attributes.orientation, 'horizontal');
    const itemsJustify = attrString(attributes.itemsJustification);
    const overlayMenu = attrString(attributes.overlayMenu, 'mobile');
    const ariaLabel = attrString(attributes.ariaLabel);
    const className = attrString(attributes.className);

    const classes = classList([
        'wp-block-navigation',
        orientation === 'vertical' ? 'is-vertical' : null,
        orientation === 'horizontal' ? 'is-horizontal' : null,
        itemsJustify !== '' ? `items-justified-${itemsJustify}` : null,
        overlayMenu !== 'never' ? 'is-responsive' : null,
        className,
    ]);

    const navProps: Record<string, string> = { className: classes };

    if (ariaLabel !== '') {
        navProps['aria-label'] = ariaLabel;
    }

    return (
        <nav {...navProps}>
            <ul className="wp-block-navigation__container">{children}</ul>
        </nav>
    );
}

function buildNavLinkProps(
    url: string,
    opensInNewTab: boolean,
    rel: string
): Record<string, string> {
    const props: Record<string, string> = { className: 'wp-block-navigation-item__content' };

    if (url !== '') {
        props.href = url;
    }

    if (opensInNewTab) {
        props.target = '_blank';
        props.rel = `noopener noreferrer${rel === '' ? '' : ` ${rel}`}`.trim();
    } else if (rel !== '') {
        props.rel = rel;
    }

    return props;
}

export function NavigationLinkBlock({ attributes }: BlockRendererProps): JSX.Element {
    const label = attrString(attributes.label);
    const url = safeUrl(attrString(attributes.url));
    const opensInNewTab = attrBoolean(attributes.opensInNewTab);
    const rel = attrString(attributes.rel);
    const title = attrString(attributes.title);
    const description = attrString(attributes.description);
    const className = attrString(attributes.className);

    const classes = classList(['wp-block-navigation-item', 'wp-block-navigation-link', className]);

    const linkProps = buildNavLinkProps(url, opensInNewTab, rel);

    if (title !== '') {
        linkProps.title = title;
    }

    return (
        <li className={classes}>
            <a {...linkProps}>
                <span className="wp-block-navigation-item__label">{label}</span>
                {description !== '' ? (
                    <span className="wp-block-navigation-item__description">{description}</span>
                ) : null}
            </a>
        </li>
    );
}

export function NavigationSubmenuBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const label = attrString(attributes.label);
    const url = safeUrl(attrString(attributes.url));
    const opensInNewTab = attrBoolean(attributes.opensInNewTab);
    const rel = attrString(attributes.rel);
    const className = attrString(attributes.className);

    const classes = classList([
        'wp-block-navigation-item',
        'wp-block-navigation-submenu',
        'has-child',
        className,
    ]);

    const linkProps = buildNavLinkProps(url, opensInNewTab, rel);

    return (
        <li className={classes}>
            <a {...linkProps}>
                <span className="wp-block-navigation-item__label">{label}</span>
            </a>
            <ul className="wp-block-navigation__submenu-container">{children}</ul>
        </li>
    );
}
