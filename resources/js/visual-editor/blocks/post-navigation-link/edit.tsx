/**
 * Post Navigation Link — edit component.
 *
 * Server-rendered display block: the real markup is produced by the
 * Blade / React / Vue renderers from stamped `_resolvedAdjacent*`
 * attributes. The fork previews through a thin wrapper around
 * `createEntityPlaceholderEdit`:
 *
 *  1. Stamped `_resolvedPrevTitle` / `_resolvedNextTitle` attribute,
 *     decorated with the configured arrow glyph (front-end / saved-tree
 *     path).
 *  2. `artisanpack/postPreview.adjacent` query-loop context (#520) —
 *     the resolved adjacent title from the per-post envelope.
 *  3. The block's own `label` attribute, when set — so authors get
 *     instant feedback while editing it.
 *  4. A neutral "Previous post" / "Next post" placeholder decorated
 *     with the configured arrow glyph so the canvas shows the styled
 *     shape even with no adjacent post resolved.
 *
 * Phase I-Block-Fork — post navigation / metadata family (#520).
 */

import {
    createEntityPlaceholderEdit,
    type EntityPreviewValue,
} from '../_shared/entity-placeholder-edit';
import type { QueryPreviewPost } from '../../editor/use-query-preview';

interface NavigationAttributes {
    readonly type?: string;
    readonly label?: string;
    readonly arrow?: string;
    readonly _resolvedPrevTitle?: string;
    readonly _resolvedNextTitle?: string;
    readonly _resolvedAdjacentTitle?: string;
}

function arrowFor( type: string, arrow: string ): string {
    if ( arrow === 'arrow' ) {
        return type === 'previous' ? '←' : '→';
    }

    if ( arrow === 'chevron' ) {
        return type === 'previous' ? '«' : '»';
    }

    return '';
}

function decoratePlaceholderText(
    attributes: NavigationAttributes,
    baseText: string,
): string {
    const type = attributes.type === 'previous' ? 'previous' : 'next';
    const arrow = typeof attributes.arrow === 'string' ? attributes.arrow : 'none';
    const glyph = arrowFor( type, arrow );

    if ( glyph === '' ) {
        return baseText;
    }

    return type === 'previous' ? `${ glyph } ${ baseText }` : `${ baseText } ${ glyph }`;
}

function readQueryPreviewAdjacent(
    context: unknown,
    direction: 'previous' | 'next',
): string {
    if ( context === null || typeof context !== 'object' ) {
        return '';
    }

    const preview = ( context as Record<string, unknown> )[ 'artisanpack/postPreview' ];

    if ( preview === null || typeof preview !== 'object' ) {
        return '';
    }

    const adjacent = ( preview as QueryPreviewPost ).adjacent;

    if ( adjacent === null || adjacent === undefined ) {
        return '';
    }

    const entry = adjacent[ direction ];

    if ( entry === null || entry === undefined ) {
        return '';
    }

    return typeof entry.title === 'string' ? entry.title : '';
}

const PlaceholderEdit = createEntityPlaceholderEdit( {
    label: 'Post Navigation Link',
    resolvedKey: '_resolvedAdjacentTitle',
    kind: 'text',
} );

export default function PostNavigationLinkEdit( props: {
    attributes?: NavigationAttributes;
    context?: unknown;
} ): ReturnType<typeof PlaceholderEdit > {
    const attributes = props.attributes ?? {};
    const type = attributes.type === 'previous' ? 'previous' : 'next';
    const label = typeof attributes.label === 'string' ? attributes.label : '';

    const stampedKey: keyof NavigationAttributes =
        type === 'previous' ? '_resolvedPrevTitle' : '_resolvedNextTitle';
    const stamped =
        typeof attributes[ stampedKey ] === 'string' ? ( attributes[ stampedKey ] as string ) : '';

    // Priority order (highest first):
    //   1. Stamped `_resolvedPrevTitle` / `_resolvedNextTitle`
    //   2. `artisanpack/postPreview.adjacent[direction].title`
    //   3. The block's own `label` attribute
    //   4. Neutral fallback ("Previous post" / "Next post")
    let text = stamped;

    if ( text === '' ) {
        text = readQueryPreviewAdjacent( props.context, type );
    }

    if ( text === '' && label !== '' ) {
        text = label;
    }

    if ( text === '' ) {
        text = type === 'previous' ? 'Previous post' : 'Next post';
    }

    const decorated = decoratePlaceholderText( attributes, text );

    const synthesizedAttributes: NavigationAttributes & EntityPreviewValue = {
        ...attributes,
        _resolvedAdjacentTitle: decorated,
    };

    return PlaceholderEdit( { ...props, attributes: synthesizedAttributes } );
}
