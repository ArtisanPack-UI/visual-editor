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

import { defineComponent, h, onBeforeUnmount, ref, watch } from 'vue';
import type { VNode } from 'vue';
import { attrBoolean, attrString, classList } from '../../support/attributes';
import { applyBlockGap, hasBlockGapStyle } from '../../support/blockGap';
import { safeCssValue } from '../../support/cssValue';
import { safeUrl } from '../../support/urlSanitizer';
import { NAVIGATION_OVERLAY_SLOT } from '../../templateParts';
import { blockRendererProps } from '../shared';

/** Open-button / dialog label. Mirrors the Blade partial's default. */
const OPEN_LABEL = 'Menu';
const CLOSE_LABEL = 'Close menu';

/** Elements that take focus when the overlay opens. */
const FOCUSABLE_SELECTOR = 'button,[href],[tabindex]:not([tabindex="-1"])';

interface OverlayColors {
    classes: string[];
    style: Record<string, string>;
}

/**
 * Resolve the overlay-specific color attributes onto the responsive
 * container. A preset slug wins over the matching custom hex, mirroring
 * the Blade partial.
 */
function overlayColors(attributes: Record<string, unknown>): OverlayColors {
    const classes: string[] = [];
    const style: Record<string, string> = {};

    const backgroundSlug = attrString(attributes.overlayBackgroundColor).trim();
    // Custom colors land in an inline `style`; drop anything outside the
    // CSS-value whitelist so a stored value can't add declarations.
    const backgroundCustom = safeCssValue(attrString(attributes.customOverlayBackgroundColor).trim()) ?? '';
    const textSlug = attrString(attributes.overlayTextColor).trim();
    const textCustom = safeCssValue(attrString(attributes.customOverlayTextColor).trim()) ?? '';

    if (backgroundSlug !== '') {
        classes.push(`has-${backgroundSlug}-background-color`, 'has-background');
    } else if (backgroundCustom !== '') {
        style['background-color'] = backgroundCustom;
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

const SVG_ATTRS = {
    xmlns: 'http://www.w3.org/2000/svg',
    viewBox: '0 0 24 24',
    width: '24',
    height: '24',
    'aria-hidden': 'true',
    focusable: 'false',
};

function hamburgerIcon(): VNode {
    return h('svg', SVG_ATTRS, [
        h('rect', { x: '4', y: '7.5', width: '16', height: '1.5' }),
        h('rect', { x: '4', y: '15', width: '16', height: '1.5' }),
    ]);
}

function closeIcon(): VNode {
    return h('svg', SVG_ATTRS, [
        h('path', { d: 'M13 11.8l6.1-6.3-1-1-6.1 6.2-6.1-6.2-1 1 6.1 6.3-6.5 6.7 1 1 6.5-6.6 6.5 6.6 1-1z' }),
    ]);
}

export const NavigationBlock = defineComponent({
    name: 'NavigationBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        const isOpen = ref(false);
        const openButton = ref<HTMLButtonElement | null>(null);
        const dialog = ref<HTMLDivElement | null>(null);

        let previousOverflow = '';

        const close = (): void => {
            isOpen.value = false;
            openButton.value?.focus();
        };

        const onKeyDown = (event: KeyboardEvent): void => {
            if (event.key !== 'Escape') {
                return;
            }

            event.preventDefault();
            close();
        };

        // While open: lock page scroll, move focus into the dialog, and
        // close on Escape — the same behavior as the Blade partial's
        // script. `flush: 'post'` runs after the dialog is visible.
        watch(
            isOpen,
            (open) => {
                if (open) {
                    previousOverflow = document.body.style.overflow;
                    document.body.style.overflow = 'hidden';
                    dialog.value?.querySelector<HTMLElement>(FOCUSABLE_SELECTOR)?.focus();
                    document.addEventListener('keydown', onKeyDown);

                    return;
                }

                document.removeEventListener('keydown', onKeyDown);
                document.body.style.overflow = previousOverflow;
            },
            { flush: 'post' },
        );

        onBeforeUnmount(() => {
            if (isOpen.value) {
                document.removeEventListener('keydown', onKeyDown);
                document.body.style.overflow = previousOverflow;
            }
        });

        return () => {
            const orientation = attrString(props.attributes.orientation, 'horizontal');
            const itemsJustify = attrString(props.attributes.itemsJustification);
            const overlayMenu = attrString(props.attributes.overlayMenu, 'mobile');
            const ariaLabel = attrString(props.attributes.ariaLabel);
            const className = attrString(props.attributes.className);

            const wantsOverlay = overlayMenu !== 'never';
            const isAlwaysOverlay = overlayMenu === 'always';

            const classes = classList([
                'wp-block-navigation',
                orientation === 'vertical' ? 'is-vertical' : null,
                orientation === 'horizontal' ? 'is-horizontal' : null,
                itemsJustify !== '' ? `items-justified-${itemsJustify}` : null,
                wantsOverlay ? 'is-responsive' : null,
                className,
            ]);

            const navProps: Record<string, unknown> = { class: classes };

            if (ariaLabel !== '') {
                navProps['aria-label'] = ariaLabel;
            }

            // #814 — Block spacing. The stylesheet turns the custom
            // property into the items' `gap`; mirrors
            // `BlockSupports::applySpacing()`.
            const blockGapStyle = applyBlockGap(props.attributes);

            if (hasBlockGapStyle(blockGapStyle)) {
                navProps.style = blockGapStyle;
            }

            const children: VNode[] = slots.default ? (slots.default() as VNode[]) : [];
            const menu = h('ul', { class: 'wp-block-navigation__container' }, children);

            if (!wantsOverlay) {
                return h('nav', navProps, [menu]);
            }

            const overlaySlot = slots[NAVIGATION_OVERLAY_SLOT];
            const overlayContent: VNode[] = overlaySlot ? (overlaySlot() as VNode[]) : [];
            const hasOverlayTemplate = overlayContent.length > 0;

            const openLabel = ariaLabel !== '' ? ariaLabel : OPEN_LABEL;
            const colors = overlayColors(props.attributes);

            // `always` uses upstream's `hidden-by-default` / `always-shown`
            // so the drawer stays collapsed behind the button at every
            // width.
            const containerProps: Record<string, unknown> = {
                class: classList([
                    'wp-block-navigation__responsive-container',
                    isAlwaysOverlay ? 'is-always-overlay' : null,
                    isAlwaysOverlay ? 'hidden-by-default' : null,
                    isOpen.value ? 'is-menu-open' : null,
                    ...colors.classes,
                ]),
                'aria-hidden': isOpen.value ? 'false' : 'true',
            };

            if (Object.keys(colors.style).length > 0) {
                containerProps.style = colors.style;
            }

            const contentChildren: VNode[] = [menu];

            if (hasOverlayTemplate) {
                contentChildren.push(
                    h('div', { class: 'wp-block-navigation__overlay-content' }, overlayContent)
                );
            }

            return h('nav', navProps, [
                h(
                    'button',
                    {
                        type: 'button',
                        'aria-haspopup': 'dialog',
                        'aria-label': openLabel,
                        class: classList([
                            'wp-block-navigation__responsive-container-open',
                            isAlwaysOverlay ? 'always-shown' : null,
                        ]),
                        ref: openButton,
                        onClick: () => {
                            isOpen.value = true;
                        },
                    },
                    [hamburgerIcon()]
                ),
                h('div', containerProps, [
                    h(
                        'div',
                        {
                            class: 'wp-block-navigation__responsive-close',
                            tabindex: '-1',
                            onClick: (event: MouseEvent) => {
                                if (event.target === event.currentTarget) {
                                    close();
                                }
                            },
                        },
                        [
                            h(
                                'div',
                                {
                                    class: 'wp-block-navigation__responsive-dialog',
                                    'aria-label': openLabel,
                                    'aria-modal': 'true',
                                    role: 'dialog',
                                    ref: dialog,
                                },
                                [
                                    h(
                                        'button',
                                        {
                                            type: 'button',
                                            'aria-label': CLOSE_LABEL,
                                            class: 'wp-block-navigation__responsive-container-close',
                                            onClick: close,
                                        },
                                        [closeIcon()]
                                    ),
                                    h(
                                        'div',
                                        {
                                            class: classList([
                                                'wp-block-navigation__responsive-container-content',
                                                hasOverlayTemplate ? 'has-overlay-template' : null,
                                            ]),
                                        },
                                        contentChildren
                                    ),
                                ]
                            ),
                        ]
                    ),
                ]),
            ]);
        };
    },
});

function buildNavLinkProps(
    url: string,
    opensInNewTab: boolean,
    rel: string
): Record<string, string> {
    const props: Record<string, string> = { class: 'wp-block-navigation-item__content' };

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

export const NavigationLinkBlock = defineComponent({
    name: 'NavigationLinkBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const label = attrString(props.attributes.label);
            const url = safeUrl(props.attributes.url);
            const opensInNewTab = attrBoolean(props.attributes.opensInNewTab);
            const rel = attrString(props.attributes.rel);
            const title = attrString(props.attributes.title);
            const description = attrString(props.attributes.description);
            const className = attrString(props.attributes.className);

            const classes = classList([
                'wp-block-navigation-item',
                'wp-block-navigation-link',
                className,
            ]);

            const linkProps = buildNavLinkProps(url, opensInNewTab, rel);

            if (title !== '') {
                linkProps.title = title;
            }

            const linkChildren: VNode[] = [
                h('span', { class: 'wp-block-navigation-item__label' }, label),
            ];

            if (description !== '') {
                linkChildren.push(
                    h('span', { class: 'wp-block-navigation-item__description' }, description)
                );
            }

            return h('li', { class: classes }, [h('a', linkProps, linkChildren)]);
        };
    },
});

export const NavigationSubmenuBlock = defineComponent({
    name: 'NavigationSubmenuBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const label = attrString(props.attributes.label);
            const url = safeUrl(props.attributes.url);
            const opensInNewTab = attrBoolean(props.attributes.opensInNewTab);
            const rel = attrString(props.attributes.rel);
            const className = attrString(props.attributes.className);

            const classes = classList([
                'wp-block-navigation-item',
                'wp-block-navigation-submenu',
                'has-child',
                className,
            ]);

            const linkProps = buildNavLinkProps(url, opensInNewTab, rel);
            const children: VNode[] = slots.default ? (slots.default() as VNode[]) : [];

            return h('li', { class: classes }, [
                h('a', linkProps, [
                    h('span', { class: 'wp-block-navigation-item__label' }, label),
                ]),
                h('ul', { class: 'wp-block-navigation__submenu-container' }, children),
            ]);
        };
    },
});
