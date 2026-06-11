/**
 * Vue renderer for the `artisanpack/breadcrumbs` block (CW0 pilot — #496).
 *
 * Mirrors the Blade partial and the React renderer so every environment
 * emits identical markup. The trail is server-resolved and arrives on
 * the `_resolvedTrail` attribute as an array of
 * `{ label, url?, current? }` entries.
 */

import { defineComponent, h, type VNode } from 'vue';

import { attrArray, attrBoolean, attrString, classList } from '../../support/attributes';
import { safeUrl } from '../../support/urlSanitizer';
import { blockRendererProps } from '../shared';

type SeparatorIconName =
    | 'arrow-right'
    | 'chevron-right'
    | 'chevron-double-right'
    | 'long-arrow-right';

const VALID_SEPARATORS: ReadonlyArray<SeparatorIconName> = [
    'arrow-right',
    'chevron-right',
    'chevron-double-right',
    'long-arrow-right',
];

const SEPARATOR_PATHS: Readonly<Record<SeparatorIconName, string>> = {
    'arrow-right': 'M5 12h14m-6-6 6 6-6 6',
    'chevron-right': 'm9 6 6 6-6 6',
    'chevron-double-right': 'm6 6 6 6-6 6m6-12 6 6-6 6',
    'long-arrow-right': 'M3 12h17m-5-5 5 5-5 5',
};

interface TrailItem {
    readonly label: string;
    readonly url: string | null;
    readonly current: boolean;
}

function normalizeSeparator(value: unknown): SeparatorIconName {
    const raw = attrString(value, 'chevron-right');
    return (VALID_SEPARATORS as ReadonlyArray<string>).includes(raw)
        ? (raw as SeparatorIconName)
        : 'chevron-right';
}

function normalizeTrail(value: unknown): ReadonlyArray<TrailItem> {
    const items: TrailItem[] = [];

    for (const entry of attrArray(value)) {
        if (entry === null || typeof entry !== 'object') {
            continue;
        }

        const record = entry as Record<string, unknown>;
        const label = attrString(record.label);

        if (label === '') {
            continue;
        }

        const sanitizedUrl = safeUrl(record.url);

        items.push({
            label,
            url: sanitizedUrl === '' ? null : sanitizedUrl,
            current: attrBoolean(record.current, false),
        });
    }

    return items;
}

export const BreadcrumbsBlock = defineComponent({
    name: 'BreadcrumbsBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const separatorIcon = normalizeSeparator(props.attributes.separatorIcon);
            const breadcrumbsSchema = attrBoolean(
                props.attributes.breadcrumbsSchema,
                true
            );
            const trail = normalizeTrail(props.attributes._resolvedTrail);
            const ariaLabel = attrString(props.attributes.ariaLabel, 'Breadcrumb');
            const className = attrString(props.attributes.className);

            const wrapperClasses = classList(['ap-breadcrumbs', className]);
            const separatorPath = SEPARATOR_PATHS[separatorIcon];

            const listAttrs: Record<string, unknown> = {
                class: 'ap-breadcrumbs__list',
            };

            if (breadcrumbsSchema) {
                listAttrs.itemscope = '';
                listAttrs.itemtype = 'https://schema.org/BreadcrumbList';
            }

            const renderItem = (item: TrailItem, index: number): VNode => {
                const isLast = index === trail.length - 1;
                const position = index + 1;

                const itemAttrs: Record<string, unknown> = {
                    class: 'ap-breadcrumbs__item',
                    key: `${position}-${item.label}`,
                };

                if (breadcrumbsSchema) {
                    itemAttrs.itemprop = 'itemListElement';
                    itemAttrs.itemscope = '';
                    itemAttrs.itemtype = 'https://schema.org/ListItem';
                }

                const children: VNode[] = [];

                if (item.url !== null && !item.current) {
                    const linkAttrs: Record<string, unknown> = {
                        class: 'ap-breadcrumbs__link',
                        href: item.url,
                    };
                    if (breadcrumbsSchema) {
                        linkAttrs.itemprop = 'item';
                    }
                    const labelAttrs: Record<string, unknown> = {};
                    if (breadcrumbsSchema) {
                        labelAttrs.itemprop = 'name';
                    }
                    children.push(
                        h('a', linkAttrs, [h('span', labelAttrs, item.label)])
                    );
                } else {
                    const labelAttrs: Record<string, unknown> = {};
                    if (item.current) {
                        labelAttrs.class = 'ap-breadcrumbs__current';
                        labelAttrs['aria-current'] = 'page';
                    }
                    if (breadcrumbsSchema) {
                        labelAttrs.itemprop = 'name';
                    }
                    children.push(h('span', labelAttrs, item.label));
                }

                if (breadcrumbsSchema) {
                    children.push(
                        h('meta', { itemprop: 'position', content: String(position) })
                    );
                }

                if (!isLast) {
                    children.push(
                        h(
                            'span',
                            {
                                class: 'ap-breadcrumbs__separator',
                                'aria-hidden': 'true',
                            },
                            [
                                h(
                                    'svg',
                                    {
                                        xmlns: 'http://www.w3.org/2000/svg',
                                        viewBox: '0 0 24 24',
                                        width: '1em',
                                        height: '1em',
                                        fill: 'none',
                                        stroke: 'currentColor',
                                        'stroke-width': '2',
                                        'stroke-linecap': 'round',
                                        'stroke-linejoin': 'round',
                                        'aria-hidden': 'true',
                                        focusable: 'false',
                                    },
                                    [h('path', { d: separatorPath })]
                                ),
                            ]
                        )
                    );
                }

                return h('li', itemAttrs, children);
            };

            return h(
                'nav',
                { class: wrapperClasses, 'aria-label': ariaLabel },
                [h('ol', listAttrs, trail.map(renderItem))]
            );
        };
    },
});
