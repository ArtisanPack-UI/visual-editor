/**
 * Post Title — edit component.
 *
 * Hybrid edit: editable inline against the live post entity when a
 * single page/post is in scope (#546), readonly preview otherwise.
 *
 * Resolution priority, highest first:
 *
 *  1. The stamped `_resolvedTitle` attribute (front-end / saved-tree
 *     path) — render readonly.
 *  2. `artisanpack/postPreview` block context — readonly query-loop
 *     preview (#483).
 *  3. Live page entity via `postType` / `postId` block context AND no
 *     `queryId` in scope — editable `PlainText` wired through the
 *     core-data shim's `useEntityProp` so typing stages an
 *     `editEntityRecord` edit and the canvas updates in real time
 *     (#546). Falls through to (4) when `useEntityProp` returns
 *     `undefined` for the title (resolver in flight, no record).
 *  4. Live page entity readonly preview (#481) — keeps the existing
 *     "shows the loaded title" behavior for query-loop-adjacent or
 *     unresolved cases.
 *  5. Placeholder label.
 */

import type { CSSProperties, ReactElement } from 'react';
import { useBlockProps, PlainText } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

import { useEntityProp } from '../../vendor/core-data-shim';
import { TEXT_DOMAIN } from '../../vendor/i18n';
import {
    PREVIEW_CONTEXT_KEY,
    readEntityString,
} from '../_shared/entity-placeholder-edit';

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyProps = Record<string, any>;

interface PostEntityIdentity {
    readonly postType: string;
    readonly postId: number;
}

const placeholderStyle: CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    gap: '0.5em',
    minHeight: '1.5em',
    padding: '0.25em 0.6em',
    border: '1px dashed currentColor',
    borderRadius: '4px',
    opacity: 0.55,
    fontStyle: 'italic',
};

function asString( value: unknown ): string {
    return typeof value === 'string' ? value : '';
}

function readPostEntityIdentity( context: unknown ): PostEntityIdentity | null {
    if ( context === null || typeof context !== 'object' ) {
        return null;
    }

    const record = context as Record<string, unknown>;
    const postType = record.postType;
    const rawPostId = record.postId;

    if ( typeof postType !== 'string' || postType === '' ) {
        return null;
    }

    if (
        typeof rawPostId === 'number'
        && Number.isInteger( rawPostId )
        && rawPostId > 0
    ) {
        return { postType, postId: rawPostId };
    }

    if ( typeof rawPostId === 'string' && /^[1-9]\d*$/.test( rawPostId ) ) {
        return { postType, postId: Number.parseInt( rawPostId, 10 ) };
    }

    return null;
}

function readQueryPreviewTitle( context: unknown ): string | null {
    if ( context === null || typeof context !== 'object' ) {
        return null;
    }

    const value = ( context as Record<string, unknown> )[ PREVIEW_CONTEXT_KEY ];

    if ( value === null || typeof value !== 'object' ) {
        return null;
    }

    const title = ( value as Record<string, unknown> ).title;
    return typeof title === 'string' && title !== '' ? title : null;
}

function isInQueryLoop( context: unknown ): boolean {
    if ( context === null || typeof context !== 'object' ) {
        return false;
    }

    return Number.isFinite( ( context as Record<string, unknown> ).queryId );
}

function tagNameForLevel( level: unknown ): string {
    if ( typeof level === 'number' && level >= 1 && level <= 6 ) {
        return `h${ level }`;
    }

    if ( level === 0 ) {
        return 'p';
    }

    return 'h2';
}

export default function PostTitleEdit( props: AnyProps ): ReactElement {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )();
    const attributes = props.attributes ?? {};
    const context = props.context ?? {};

    const identity = readPostEntityIdentity( context );
    const inQueryLoop = isInQueryLoop( context );
    const TagName = tagNameForLevel( attributes.level );
    const hasLiveEntity = identity !== null && ! inQueryLoop;

    // Hooks must fire unconditionally. The shim returns `[undefined,
    // noop, undefined]` when any of the args are missing, so the
    // call is cheap when the block has no live-entity context.
    const [ editedTitle, setTitle ] = useEntityProp<
        string | { raw?: string; rendered?: string }
    >(
        hasLiveEntity ? 'postType' : undefined,
        hasLiveEntity ? identity.postType : undefined,
        hasLiveEntity ? 'title' : undefined,
        hasLiveEntity ? identity.postId : null,
    );

    // 1. Stamped `_resolvedTitle` — server-rendered preview.
    const stamped = asString( attributes._resolvedTitle );
    if ( stamped !== '' ) {
        return <div { ...blockProps }>{ stamped }</div>;
    }

    // 2. Query-loop preview — readonly first-post title.
    const queryPreviewTitle = readQueryPreviewTitle( context );
    if ( queryPreviewTitle !== null ) {
        return <div { ...blockProps }>{ queryPreviewTitle }</div>;
    }

    // 3. Live entity, not in a query loop — editable inline.
    if ( hasLiveEntity ) {
        const titleText = readEntityString( editedTitle );

        // Render the editable PlainText once the entity has resolved
        // (`editedTitle !== undefined`) OR once the user has typed
        // something non-empty. Both halves matter: an empty-but-resolved
        // title (`title: ''`) still needs an editable surface, and a
        // typed-then-cleared title must not collapse back to the
        // placeholder. The placeholder branch (4) therefore only fires
        // while the resolver is still in flight.
        if ( titleText !== '' || editedTitle !== undefined ) {
            // `tagName` and `__experimentalVersion={2}` are supported by
            // `PlainText` at runtime in `@wordpress/block-editor` >= 14
            // (the version pinned by this release — see
            // `docs/release-notes-inputs-1.0.0.md`), but
            // `@types/wordpress__block-editor` doesn't expose them yet,
            // hence the `as never` cast. We intentionally use
            // `PlainText` over `RichText` so the heading stays
            // plain-text only — inline formatting on a `core/post-title`
            // would diverge from the rendered output.
            return (
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                <PlainText
                    tagName={ TagName as never }
                    placeholder={ __( '(no title)', TEXT_DOMAIN ) }
                    value={ titleText }
                    onChange={ ( next: string ) => setTitle( next ) }
                    __experimentalVersion={ 2 }
                    { ...blockProps }
                />
            );
        }
    }

    // 4. Placeholder.
    return (
        <div { ...blockProps }>
            <span style={ placeholderStyle } contentEditable={ false }>
                { __( 'Post Title', TEXT_DOMAIN ) }
            </span>
        </div>
    );
}
