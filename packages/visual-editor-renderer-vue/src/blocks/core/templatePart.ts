/**
 * `core/template-part` renderer.
 *
 * Operates on a tree where the template-part block already carries its
 * resolved part's blocks under `innerBlocks` (see
 * {@link inlineTemplateParts}). The renderer wraps the children in a
 * semantic element matching the part's `tagName` attribute (default
 * `div`) and stamps the slug + theme onto data attributes so
 * client-side scripts can target the rendered region.
 *
 * If the inliner could not resolve the part, it stamps an
 * `_resolutionError` attribute. In production this renderer emits an
 * empty wrapper so the surrounding layout stays intact; in dev the
 * wrapper carries a `data-ve-resolution-error` attribute the developer
 * can spot in the inspector.
 */

import { defineComponent, h } from 'vue';
import { attrString, classList } from '../../support/attributes';
import { blockRendererProps } from '../shared';

const ALLOWED_PART_TAGS = ['div', 'header', 'footer', 'aside', 'section', 'main', 'nav'] as const;

type PartTag = (typeof ALLOWED_PART_TAGS)[number];

const SLUG_CLASS_PATTERN = /[^a-zA-Z0-9_-]/g;

function isDevelopment(): boolean {
    if (typeof process === 'undefined') {
        return false;
    }

    const env = process.env?.NODE_ENV;

    return env !== 'production';
}

export const TemplatePartBlock = defineComponent({
    name: 'TemplatePartBlock',
    props: blockRendererProps,
    setup(props, { slots }) {
        return () => {
            const slug = attrString(props.attributes.slug);
            const theme = attrString(props.attributes.theme);
            const tagName = attrString(props.attributes.tagName);
            const resolutionError = attrString(props.attributes._resolutionError);

            const tag: PartTag = (ALLOWED_PART_TAGS as ReadonlyArray<string>).includes(tagName)
                ? (tagName as PartTag)
                : 'div';

            const slugClass = slug === '' ? '' : `wp-block-template-part--${slug.replace(SLUG_CLASS_PATTERN, '-')}`;
            const className = attrString(props.attributes.className);
            const classes = classList(['wp-block-template-part', slugClass, className]);

            const elementProps: Record<string, string> = {
                class: classes,
                'data-ve-template-part': slug,
            };

            if (theme !== '') {
                elementProps['data-ve-theme'] = theme;
            }

            if (resolutionError !== '' && isDevelopment()) {
                elementProps['data-ve-resolution-error'] = resolutionError;
            }

            const children = resolutionError !== '' ? [] : slots.default ? slots.default() : [];

            return h(tag, elementProps, children);
        };
    },
});
