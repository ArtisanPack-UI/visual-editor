/**
 * Vue renderer for the `artisanpack/author-social-icons` block (#501).
 *
 * Mirrors the Blade partial and the React renderer. Server-side
 * `PostResolver` reads the post author's stored profile URLs and
 * stamps `_resolvedAuthorSocialLinks`; this renderer intersects them
 * with the block's saved `socialIcons` picker and emits one chip per
 * surviving platform.
 */

import { defineComponent, h, type VNode } from 'vue';

import {
    attrArray,
    attrInt,
    attrString,
    classList,
} from '../../support/attributes';
import { safeUrl } from '../../support/urlSanitizer';
import { blockRendererProps } from '../shared';

import { getAuthorIcon } from './socialIcons';

type IconStyle = 'show-label-icon' | 'show-icon' | 'show-label';
type IconsDirection = 'horizontal' | 'vertical';
type IconsStretch = 'full-width' | 'auto-width';

const VALID_STYLES: ReadonlyArray<IconStyle> = [
    'show-label-icon',
    'show-icon',
    'show-label',
];

const VALID_DIRECTIONS: ReadonlyArray<IconsDirection> = ['horizontal', 'vertical'];

const VALID_STRETCHES: ReadonlyArray<IconsStretch> = ['full-width', 'auto-width'];

interface ResolvedChip {
    readonly slug: string;
    readonly url: string;
    readonly label: string;
    readonly path: string;
}

function clampRadius(value: unknown): number {
    const parsed = attrInt(value, 0);
    if (parsed < 0) {
        return 0;
    }
    if (parsed > 50) {
        return 50;
    }
    return parsed;
}

function normalizeStyle(value: unknown): IconStyle {
    const raw = attrString(value, 'show-label-icon');
    return (VALID_STYLES as ReadonlyArray<string>).includes(raw)
        ? (raw as IconStyle)
        : 'show-label-icon';
}

function normalizeDirection(value: unknown): IconsDirection {
    const raw = attrString(value, 'vertical');
    return (VALID_DIRECTIONS as ReadonlyArray<string>).includes(raw)
        ? (raw as IconsDirection)
        : 'vertical';
}

function normalizeStretch(value: unknown): IconsStretch {
    const raw = attrString(value, 'full-width');
    return (VALID_STRETCHES as ReadonlyArray<string>).includes(raw)
        ? (raw as IconsStretch)
        : 'full-width';
}

function resolvedChips(
    selected: ReadonlySet<string>,
    links: unknown
): ReadonlyArray<ResolvedChip> {
    const chips: ResolvedChip[] = [];

    for (const entry of attrArray(links)) {
        if (entry === null || typeof entry !== 'object') {
            continue;
        }

        const record = entry as Record<string, unknown>;
        const slug = attrString(record.slug);

        if (slug === '' || !selected.has(slug)) {
            continue;
        }

        const rawUrl = attrString(record.url);
        const url = rawUrl.startsWith('mailto:') ? rawUrl : safeUrl(rawUrl);

        if (url === '') {
            continue;
        }

        const definition = getAuthorIcon(slug);

        if (definition === undefined) {
            continue;
        }

        chips.push({
            slug,
            url,
            label: definition.label,
            path: definition.path,
        });
    }

    return chips;
}

export const AuthorSocialIconsBlock = defineComponent({
    name: 'AuthorSocialIconsBlock',
    props: blockRendererProps,
    setup(props) {
        return (): VNode => {
            const iconStyle = normalizeStyle(props.attributes.iconStyle);
            const direction = normalizeDirection(props.attributes.iconsDirection);
            const stretch = normalizeStretch(props.attributes.iconsStretch);
            const radius = clampRadius(props.attributes.iconsBorderRadius);

            const selected = new Set<string>();
            for (const entry of attrArray(props.attributes.socialIcons)) {
                if (typeof entry === 'string') {
                    selected.add(entry);
                }
            }

            const chips = resolvedChips(
                selected,
                props.attributes._resolvedAuthorSocialLinks
            );
            const className = attrString(props.attributes.className);

            const classes = classList([
                'ap-author-social-icons',
                `ap-author-social-icons--${direction}`,
                `ap-author-social-icons--${stretch}`,
                className,
            ]);

            const showIcon = iconStyle !== 'show-label';
            const showLabel = iconStyle !== 'show-icon';

            return h(
                'div',
                { class: classes },
                chips.map((chip) => {
                    const linkAttrs: Record<string, unknown> = {
                        class: `ap-author-social-icons__chip ${chip.slug}`,
                        href: chip.url,
                    };

                    // Vue's SSR serializes `style: undefined` as
                    // `style=""`, which breaks parity with React (which
                    // omits the attribute). Only set the key when there
                    // is a non-zero radius to encode.
                    if (radius > 0) {
                        linkAttrs.style = { 'border-radius': `${radius}px` };
                    }

                    return h('div', { class: 'ap-author-social-icons__item', key: chip.slug }, [
                        h(
                            'a',
                            linkAttrs,
                            [
                                showIcon
                                    ? h(
                                          'svg',
                                          {
                                              xmlns: 'http://www.w3.org/2000/svg',
                                              viewBox: '0 0 24 24',
                                              width: '1em',
                                              height: '1em',
                                              'aria-hidden': 'true',
                                              focusable: 'false',
                                              class: 'ap-author-social-icons__icon',
                                          },
                                          [h('path', { d: chip.path })]
                                      )
                                    : null,
                                showLabel
                                    ? h(
                                          'span',
                                          { class: 'ap-author-social-icons__label' },
                                          chip.label
                                      )
                                    : null,
                            ]
                        ),
                    ]);
                })
            );
        };
    },
});
