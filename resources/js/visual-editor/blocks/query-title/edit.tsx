/**
 * QueryTitle — edit component.
 *
 * Server-rendered display block. Previews as a heading-shaped
 * placeholder reflecting the configured `type` (archive / search /
 * post-type) and `level`. Upstream's full archive-label / post-type-
 * label lookups depend on `@wordpress/core-data` selectors the post
 * editor's shim does not implement; the real query title is emitted
 * server-side by the Blade / React / Vue renderers from
 * `_resolvedQueryTitle`. Phase I-Block-Fork query family (#521).
 */

import type { ReactElement } from 'react';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

interface QueryTitleEditProps {
    attributes: {
        type?: string;
        level?: number;
    };
}

function defaultTitleFor( type: string ): string {
    switch ( type ) {
        case 'archive':
            return __( 'Archive title', TEXT_DOMAIN );
        case 'search':
            return __( 'Search results', TEXT_DOMAIN );
        case 'post-type':
            return __( 'Posts', TEXT_DOMAIN );
        default:
            return __( 'Query title', TEXT_DOMAIN );
    }
}

export default function QueryTitleEdit( { attributes }: QueryTitleEditProps ): ReactElement {
    const type = typeof attributes.type === 'string' ? attributes.type : '';
    // Clamp `level` to the WP heading range (0 = paragraph, 1-6 = h1-h6)
    // so a malformed saved value never produces an invalid tag like
    // `<h7>` in the canvas preview. Mirrors the renderer-side guards
    // (see query-title.blade.php / queryContext.tsx parity).
    const rawLevel = typeof attributes.level === 'number' && Number.isFinite( attributes.level )
        ? attributes.level
        : 1;
    const level = 0 === rawLevel ? 0 : Math.min( 6, Math.max( 1, Math.trunc( rawLevel ) ) );
    const tagName = ( 0 === level ? 'p' : `h${ level }` ) as keyof JSX.IntrinsicElements;

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )();

    // Read the front-end resolved title when present so block previews
    // that have been server-rendered before opening in the editor stay
    // faithful. Otherwise fall back to the type-shaped placeholder.
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const resolved = ( attributes as any )._resolvedQueryTitle;
    const text = typeof resolved === 'string' && '' !== resolved
        ? resolved
        : defaultTitleFor( type );

    const Tag = tagName;
    return <Tag { ...blockProps }>{ text }</Tag>;
}
