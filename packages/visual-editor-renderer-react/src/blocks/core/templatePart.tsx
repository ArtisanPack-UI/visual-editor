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

import { attrString, classList } from '../../support/attributes';
import type { BlockRendererProps } from '../../types';

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

export function TemplatePartBlock({ attributes, children }: BlockRendererProps): JSX.Element {
    const slug = attrString(attributes.slug);
    const theme = attrString(attributes.theme);
    const tagName = attrString(attributes.tagName);
    const resolutionError = attrString(attributes._resolutionError);

    const Tag: PartTag = (ALLOWED_PART_TAGS as ReadonlyArray<string>).includes(tagName)
        ? (tagName as PartTag)
        : 'div';

    const slugClass = slug === '' ? '' : `wp-block-template-part--${slug.replace(SLUG_CLASS_PATTERN, '-')}`;
    const className = attrString(attributes.className);
    const classes = classList(['wp-block-template-part', slugClass, className]);

    const dataAttributes: Record<string, string> = {
        'data-ve-template-part': slug,
    };

    if (theme !== '') {
        dataAttributes['data-ve-theme'] = theme;
    }

    if (resolutionError !== '' && isDevelopment()) {
        dataAttributes['data-ve-resolution-error'] = resolutionError;
    }

    if (resolutionError !== '') {
        return <Tag className={classes} {...dataAttributes} />;
    }

    return (
        <Tag className={classes} {...dataAttributes}>
            {children}
        </Tag>
    );
}
