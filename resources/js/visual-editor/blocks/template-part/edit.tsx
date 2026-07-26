/**
 * TemplatePart — edit component.
 *
 * The I5 (#413) implementation delegated to `core/template-part`'s edit
 * component. That worked while the block library still registered core
 * blocks, but the I7 cutover (#415) replaced `registerCoreBlocks()`
 * with `registerArtisanPackBlocks()`, leaving `core/template-part`
 * unregistered — the delegating fork's `getBlockType('core/template-part')`
 * lookup returned undefined, the fork fell back to rendering an empty
 * `<div>`, and any `<!-- wp:template-part /-->` reference inside a
 * template surfaced as a blank strip in the canvas (#674).
 *
 * This edit is a minimal replacement: read `slug` + `theme` from
 * attributes, compose the WP composite id (`<theme>//<slug>`), and hand
 * off to the shim's `useEntityBlockEditor`. The shim already knows how
 * to fetch `wp_template_part` records by composite id (falls through
 * to `?theme=&slug=` on the index endpoint since the numeric-primary
 * `show` route rejects composite ids), receives `content.blocks` from
 * the adapter (which parses raw server-side and rewrites forked block
 * names — see `TemplateAdapter::toArray()`), and wires the resolved
 * tree back through `[blocks]`. `useInnerBlocksProps` mounts it under
 * the tagName the block author picked.
 *
 * Saves through the shim are still no-ops (`useEntityBlockEditor` returns
 * `noopSetter` for both `onInput` and `onChange`), so the InnerBlocks
 * surface is locked (`templateLock: 'all'`, `renderAppender: false`).
 * Users edit template parts through the Template Parts navigator, not
 * inline — surfacing an editable UI here would silently discard the
 * edits on reload.
 *
 * The composite-id call is split into a child component so React can
 * skip the hook entirely when `slug` or `theme` is missing. The shim's
 * `useEntityBlockEditor` resolves a missing `options.id` through the
 * ambient `EntityProvider` context (`options?.id ?? ambientId ?? null`),
 * and for a template-part block nested inside a template that ambient
 * id is the parent template — so a legacy `<!-- wp:template-part
 * {"slug":"header"} /-->` reference (older themes often omit `theme`)
 * would mount the template's own root blocks under the placeholder,
 * producing recursive nesting rather than a clean empty state.
 */

import type { ReactElement } from 'react';
import { createElement } from 'react';
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

import { useEntityBlockEditor } from '../../vendor/core-data-shim';

interface Attributes {
    readonly slug?: string;
    readonly theme?: string;
    readonly tagName?: string;
}

interface Props {
    readonly attributes: Attributes;
}

export default function TemplatePartEdit({ attributes }: Props): ReactElement {
    const slug = typeof attributes.slug === 'string' ? attributes.slug : '';
    const theme = typeof attributes.theme === 'string' ? attributes.theme : '';
    const tagName =
        typeof attributes.tagName === 'string' && attributes.tagName !== ''
            ? attributes.tagName
            : 'div';

    if (slug === '' || theme === '') {
        // Empty wrapper with no InnerBlocks — deliberately doesn't call
        // `useEntityBlockEditor`, which would fall through to the ambient
        // entity id and mount the wrong tree.
        const blockProps = useBlockProps();
        return createElement(tagName, blockProps);
    }

    return (
        <TemplatePartBody slug={slug} theme={theme} tagName={tagName} />
    );
}

interface BodyProps {
    readonly slug: string;
    readonly theme: string;
    readonly tagName: string;
}

function TemplatePartBody({ slug, theme, tagName }: BodyProps): ReactElement {
    const compositeId = `${theme}//${slug}`;

    const [blocks, onInput, onChange] = useEntityBlockEditor(
        'postType',
        'wp_template_part',
        { id: compositeId },
    );

    const blockProps = useBlockProps();
    // `useInnerBlocksProps` in controlled mode (`value:`) needs valid
    // `onInput` / `onChange` callbacks or it can't hydrate the tree
    // into the editor's block store. The shim returns `noopSetter` for
    // both — that's fine here because `templateLock: 'all'` locks the
    // surface against structural edits, so the setters never see a
    // real mutation to persist. Save-side plumbing for inline
    // template-part edits is a separate follow-up; today the Template
    // Parts navigator is the editing entrypoint.
    const innerBlocksProps = useInnerBlocksProps(blockProps, {
        value: blocks,
        onInput,
        onChange,
        templateLock: 'all',
        renderAppender: false,
    });

    return createElement(tagName, innerBlocksProps);
}
