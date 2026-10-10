/**
 * `core/navigation`, `core/navigation-link`, and `core/navigation-submenu`
 * renderers. The container block lays its inner blocks out as the menu
 * `<ul>` so menu trees authored in the site editor render server- and
 * client-side with the same structure.
 *
 * When `overlayMenu` is `mobile` (default) or `always`, the navigation
 * block renders the same responsive overlay as the Blade partial: a
 * hamburger open button plus a `wp-block-navigation__responsive-container`
 * dialog wrapping the menu (#804). When the block's `overlay` template
 * part resolves (see `inlineTemplateParts`), its blocks arrive through
 * the `overlay` slot and replace the menu inside the open drawer.
 */

import { useEffect, useRef, useState } from 'react';
import type { CSSProperties, MouseEvent } from 'react';

import { attrBoolean, attrString, classList } from '../../support/attributes';
import { applyBlockGap, hasBlockGapStyle } from '../../support/blockGap';
import { safeCssValue } from '../../support/cssValue';
import { safeUrl } from '../../support/urlSanitizer';
import { NAVIGATION_OVERLAY_SLOT } from '../../templateParts';
import type { BlockRendererProps } from '../../types';

/** Open-button / dialog label. Mirrors the Blade partial's default. */
const OPEN_LABEL = 'Menu';
const CLOSE_LABEL = 'Close menu';

/** Elements that take focus when the overlay opens. */
const FOCUSABLE_SELECTOR = 'button,[href],[tabindex]:not([tabindex="-1"])';

interface OverlayColors {
    classes: string[];
    style: CSSProperties;
}

/**
 * Resolve the overlay-specific color attributes onto the responsive
 * container. A preset slug wins over the matching custom hex, mirroring
 * the Blade partial.
 */
function overlayColors(attributes: Record<string, unknown>): OverlayColors {
    const classes: string[] = [];
    const style: CSSProperties = {};

    const backgroundSlug = attrString(attributes.overlayBackgroundColor).trim();
    // Custom colors land in an inline `style`; drop anything outside the
    // CSS-value whitelist so a stored value can't add declarations.
    const backgroundCustom = safeCssValue(attrString(attributes.customOverlayBackgroundColor).trim()) ?? '';
    const textSlug = attrString(attributes.overlayTextColor).trim();
    const textCustom = safeCssValue(attrString(attributes.customOverlayTextColor).trim()) ?? '';

    if (backgroundSlug !== '') {
        classes.push(`has-${backgroundSlug}-background-color`, 'has-background');
    } else if (backgroundCustom !== '') {
        style.backgroundColor = backgroundCustom;
        classes.push('has-background');
    }

    if (textSlug !== '') {
        classes.push(`has-${textSlug}-color`, 'has-text-color');
    } else if (textCustom !== '') {
        style.color = textCustom;
        classes.push('has-text-color');
    }

    return { classes, style };
}

function HamburgerIcon(): JSX.Element {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
            <rect x="4" y="7.5" width="16" height="1.5" />
            <rect x="4" y="15" width="16" height="1.5" />
        </svg>
    );
}

function CloseIcon(): JSX.Element {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
            <path d="M13 11.8l6.1-6.3-1-1-6.1 6.2-6.1-6.2-1 1 6.1 6.3-6.5 6.7 1 1 6.5-6.6 6.5 6.6 1-1z" />
        </svg>
    );
}

export function NavigationBlock({ attributes, children, slots }: BlockRendererProps): JSX.Element {
    const orientation = attrString(attributes.orientation, 'horizontal');
    const itemsJustify = attrString(attributes.itemsJustification);
    const overlayMenu = attrString(attributes.overlayMenu, 'mobile');
    const ariaLabel = attrString(attributes.ariaLabel);
    const className = attrString(attributes.className);

    const wantsOverlay = overlayMenu !== 'never';
    const isAlwaysOverlay = overlayMenu === 'always';
    const overlayContent = slots?.[NAVIGATION_OVERLAY_SLOT];
    const hasOverlayTemplate = overlayContent !== undefined && overlayContent !== null;

    const [isOpen, setIsOpen] = useState(false);
    const openButtonRef = useRef<HTMLButtonElement>(null);
    const dialogRef = useRef<HTMLDivElement>(null);

    const close = (): void => {
        setIsOpen(false);
        openButtonRef.current?.focus();
    };

    // While open: lock page scroll, move focus into the dialog, and
    // close on Escape — the same behavior as the Blade partial's script.
    useEffect(() => {
        if (!isOpen) {
            return undefined;
        }

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        dialogRef.current?.querySelector<HTMLElement>(FOCUSABLE_SELECTOR)?.focus();

        const onKeyDown = (event: KeyboardEvent): void => {
            if (event.key !== 'Escape') {
                return;
            }

            event.preventDefault();
            setIsOpen(false);
            openButtonRef.current?.focus();
        };

        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('keydown', onKeyDown);
            document.body.style.overflow = previousOverflow;
        };
    }, [isOpen]);

    const classes = classList([
        'wp-block-navigation',
        orientation === 'vertical' ? 'is-vertical' : null,
        orientation === 'horizontal' ? 'is-horizontal' : null,
        itemsJustify !== '' ? `items-justified-${itemsJustify}` : null,
        wantsOverlay ? 'is-responsive' : null,
        className,
    ]);

    const navProps: Record<string, unknown> = { className: classes };

    if (ariaLabel !== '') {
        navProps['aria-label'] = ariaLabel;
    }

    // #814 — Block spacing. The stylesheet turns the custom property
    // into the items' `gap`; mirrors `BlockSupports::applySpacing()`.
    const blockGapStyle = applyBlockGap(attributes);

    if (hasBlockGapStyle(blockGapStyle)) {
        navProps.style = blockGapStyle;
    }

    const menu = <ul className="wp-block-navigation__container">{children}</ul>;

    if (!wantsOverlay) {
        return <nav {...navProps}>{menu}</nav>;
    }

    const openLabel = ariaLabel !== '' ? ariaLabel : OPEN_LABEL;
    const colors = overlayColors(attributes);

    // `always` uses upstream's `hidden-by-default` / `always-shown` so
    // the drawer stays collapsed behind the button at every width.
    const containerProps: Record<string, unknown> = {
        className: classList([
            'wp-block-navigation__responsive-container',
            isAlwaysOverlay ? 'is-always-overlay' : null,
            isAlwaysOverlay ? 'hidden-by-default' : null,
            isOpen ? 'is-menu-open' : null,
            ...colors.classes,
        ]),
        'aria-hidden': isOpen ? 'false' : 'true',
    };

    if (Object.keys(colors.style).length > 0) {
        containerProps.style = colors.style;
    }

    const onBackdropClick = (event: MouseEvent<HTMLDivElement>): void => {
        if (event.target === event.currentTarget) {
            close();
        }
    };

    return (
        <nav {...navProps}>
            <button
                type="button"
                aria-haspopup="dialog"
                aria-label={openLabel}
                className={classList([
                    'wp-block-navigation__responsive-container-open',
                    isAlwaysOverlay ? 'always-shown' : null,
                ])}
                ref={openButtonRef}
                onClick={() => setIsOpen(true)}
            >
                <HamburgerIcon />
            </button>
            <div {...containerProps}>
                <div className="wp-block-navigation__responsive-close" tabIndex={-1} onClick={onBackdropClick}>
                    <div
                        className="wp-block-navigation__responsive-dialog"
                        aria-label={openLabel}
                        aria-modal="true"
                        role="dialog"
                        ref={dialogRef}
                    >
                        <button
                            type="button"
                            aria-label={CLOSE_LABEL}
                            className="wp-block-navigation__responsive-container-close"
                            onClick={close}
                        >
                            <CloseIcon />
                        </button>
                        <div
                            className={classList([
                                'wp-block-navigation__responsive-container-content',
                                hasOverlayTemplate ? 'has-overlay-template' : null,
                            ])}
                        >
                            {menu}
                            {hasOverlayTemplate ? (
                                <div className="wp-block-navigation__overlay-content">{overlayContent}</div>
                            ) : null}
                        </div>
                    </div>
                </div>
            </div>
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
