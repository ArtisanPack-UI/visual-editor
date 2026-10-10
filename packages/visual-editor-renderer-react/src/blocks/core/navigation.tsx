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

import { useEffect, useId, useRef, useState } from 'react';
import type { CSSProperties, MouseEvent } from 'react';

import { attrBoolean, attrString, classList, phpTrim } from '../../support/attributes';
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

/**
 * The menu `<ul>` the swap CSS hides while an overlay template part is
 * shown in the open drawer; its links are skipped by the focus wrap.
 */
const SWAPPED_MENU_SELECTOR = '.has-overlay-template > .wp-block-navigation__container';

/**
 * Reduce a color preset slug to `[a-z0-9-]` so a stored value can't mint
 * extra class tokens (e.g. `x is-menu-open`). Mirrors the Blade
 * partial's slug sanitizing.
 */
function presetSlug(value: unknown): string {
    return phpTrim(attrString(value))
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

/**
 * Focusable elements of the open dialog, in DOM order, minus the menu
 * links hidden by the overlay-template swap.
 */
function dialogFocusables(dialog: HTMLElement): HTMLElement[] {
    return Array.from(dialog.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR)).filter(
        (element) => element.closest(SWAPPED_MENU_SELECTOR) === null
    );
}

/**
 * Keep Tab / Shift+Tab inside the open dialog: wrap from the last
 * focusable element to the first (and back), and pull focus that has
 * escaped the dialog back in.
 */
function wrapDialogFocus(event: KeyboardEvent, dialog: HTMLElement): void {
    const focusables = dialogFocusables(dialog);

    if (focusables.length === 0) {
        return;
    }

    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    const active = document.activeElement;

    if (active === null || !dialog.contains(active)) {
        event.preventDefault();
        (event.shiftKey ? last : first).focus();
    } else if (event.shiftKey && active === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && active === last) {
        event.preventDefault();
        first.focus();
    }
}

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

    // `phpTrim` (not `.trim()`) so NBSP-padded values resolve exactly as
    // they do through PHP `trim()` in the Blade partial.
    const backgroundSlug = presetSlug(attributes.overlayBackgroundColor);
    // Custom colors land in an inline `style`; drop anything outside the
    // CSS-value whitelist so a stored value can't add declarations.
    const backgroundCustom = safeCssValue(phpTrim(attrString(attributes.customOverlayBackgroundColor))) ?? '';
    const textSlug = presetSlug(attributes.overlayTextColor);
    const textCustom = safeCssValue(phpTrim(attrString(attributes.customOverlayTextColor))) ?? '';

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
    // SSR-safe id tying the open button's `aria-controls` to the drawer.
    const containerId = useId();
    const openButtonRef = useRef<HTMLButtonElement>(null);
    const dialogRef = useRef<HTMLDivElement>(null);

    const close = (): void => {
        setIsOpen(false);
        openButtonRef.current?.focus();
    };

    // While open: lock page scroll, move focus into the dialog, keep Tab
    // inside it, and close on Escape — the same behavior as the Blade
    // partial's script.
    useEffect(() => {
        if (!isOpen) {
            return undefined;
        }

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        dialogRef.current?.querySelector<HTMLElement>(FOCUSABLE_SELECTOR)?.focus();

        const onKeyDown = (event: KeyboardEvent): void => {
            if (event.key === 'Tab') {
                if (dialogRef.current !== null) {
                    wrapDialogFocus(event, dialogRef.current);
                }

                return;
            }

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
        id: containerId,
    };

    if (Object.keys(colors.style).length > 0) {
        containerProps.style = colors.style;
    }

    const onBackdropClick = (event: MouseEvent<HTMLDivElement>): void => {
        if (event.target === event.currentTarget) {
            close();
        }
    };

    // A followed link (same-page anchor, client-side route) closes the
    // drawer; the navigation itself is left alone and focus isn't moved.
    const onDialogClick = (event: MouseEvent<HTMLDivElement>): void => {
        if (isOpen && event.target instanceof Element && event.target.closest('a[href]') !== null) {
            setIsOpen(false);
        }
    };

    // The container stays in the accessibility tree (it is the inline
    // desktop menu above the breakpoint); dialog semantics apply only
    // while the drawer is open.
    const dialogProps: Record<string, string> = isOpen
        ? { role: 'dialog', 'aria-modal': 'true', 'aria-label': openLabel }
        : {};

    return (
        <nav {...navProps}>
            <button
                type="button"
                aria-haspopup="dialog"
                aria-label={openLabel}
                aria-expanded={isOpen ? 'true' : 'false'}
                aria-controls={containerId}
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
                        {...dialogProps}
                        ref={dialogRef}
                        onClick={onDialogClick}
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
