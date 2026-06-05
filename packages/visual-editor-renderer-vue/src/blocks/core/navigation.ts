/**
 * `core/navigation`, `core/navigation-link`, and `core/navigation-submenu`
 * renderers. The container block lays its inner blocks out as the menu
 * `<ul>` so menu trees authored in the site editor render server- and
 * client-side with the same structure.
 */

import { defineComponent, h } from 'vue';
import type { VNode } from 'vue';
import { attrBoolean, attrString, classList } from '../../support/attributes';
import { safeUrl } from '../../support/urlSanitizer';
import { blockRendererProps } from '../shared';

export const NavigationBlock = defineComponent({
    name: 'NavigationBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const orientation = attrString(props.attributes.orientation, 'horizontal');
            const itemsJustify = attrString(props.attributes.itemsJustification);
            const overlayMenu = attrString(props.attributes.overlayMenu, 'mobile');
            const ariaLabel = attrString(props.attributes.ariaLabel);
            const className = attrString(props.attributes.className);

            const classes = classList([
                'wp-block-navigation',
                orientation === 'vertical' ? 'is-vertical' : null,
                orientation === 'horizontal' ? 'is-horizontal' : null,
                itemsJustify !== '' ? `items-justified-${itemsJustify}` : null,
                overlayMenu !== 'never' ? 'is-responsive' : null,
                className,
            ]);

            const navProps: Record<string, string> = { class: classes };

            if (ariaLabel !== '') {
                navProps['aria-label'] = ariaLabel;
            }

            const children: VNode[] = slots.default ? (slots.default() as VNode[]) : [];

            return h('nav', navProps, [
                h('ul', { class: 'wp-block-navigation__container' }, children),
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
