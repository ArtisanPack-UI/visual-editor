/**
 * QueryTitle — edit component.
 *
 * Server-rendered display block. The real query title is emitted
 * server-side by the Blade / React / Vue renderers from
 * `_resolvedQueryTitle` once the front-end paginator settles. In the
 * canvas the title falls back to the `artisanpack/queryPreview` block
 * context (#599) — populated by the surrounding query block from the
 * resolver payload — and then to a heading-shaped placeholder
 * reflecting the configured `type` (archive / search / post-type) and
 * `level`. Phase I-Block-Fork query family (#521).
 */

import type { ReactElement } from 'react';
import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

import { readQueryPreviewContext } from '../../editor/query-preview-context';
import { TEXT_DOMAIN } from '../../vendor/i18n';

interface QueryTitleEditProps {
    attributes: {
        type?: string;
        level?: number;
    };
    context?: Record<string, unknown>;
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

function readResolvedQueryTitle( context: Record<string, unknown> | undefined ): string {
    const preview = readQueryPreviewContext( context );
    return preview?.queryTitle ?? '';
}

export default function QueryTitleEdit( { attributes, context }: QueryTitleEditProps ): ReactElement {
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

    // Resolution priority, highest first:
    //   1. Stamped `_resolvedQueryTitle` attribute (server pre-render path).
    //   2. `artisanpack/queryPreview` block context (#599 query-loop path).
    //   3. Type-shaped placeholder.
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const stamped = ( attributes as any )._resolvedQueryTitle;
    const fromContext = readResolvedQueryTitle( context );
    let text: string;

    if ( typeof stamped === 'string' && '' !== stamped ) {
        text = stamped;
    } else if ( '' !== fromContext ) {
        text = fromContext;
    } else {
        text = defaultTitleFor( type );
    }

    const Tag = tagName;
    return <Tag { ...blockProps }>{ text }</Tag>;
}
