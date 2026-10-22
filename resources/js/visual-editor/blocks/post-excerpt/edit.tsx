/**
 * Post Excerpt — edit component.
 *
 * Hybrid edit: editable inline against the live entry when a single
 * post / page / custom content entry is in scope (#992), readonly
 * preview otherwise. Mirrors the Post Title edit (#546).
 *
 * Resolution priority, highest first:
 *
 *  1. Stamped `_resolvedExcerpt` attribute — readonly.
 *  2. `artisanpack/postPreview` context — readonly query-loop (#483) or
 *     site-editor template preview (#993).
 *  3. Live entry via `postType` / `postId` block context and no
 *     `queryId` — editable `PlainText` wired through the core-data
 *     shim's `useEntityProp`, so typing stages an `editEntityRecord`
 *     edit the editor saves with the entry's other metadata and mirrors
 *     into the document panel's Excerpt field.
 *  4. Clearly-labelled placeholder while the entry is still resolving.
 *
 * Phase I5 entity cluster (#413), live-preview path #481.
 */

import type { ReactElement } from 'react';
import { PlainText, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

import { useEntityProp } from '../../vendor/core-data-shim';
import { TEXT_DOMAIN } from '../../vendor/i18n';
import {
    createEntityPlaceholderEdit,
    hasPreviewContext,
    isInQueryLoop,
    readEntityString,
    readPostEntityIdentity,
    type PostEntityIdentity,
} from '../_shared/entity-placeholder-edit';

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyProps = Record<string, any>;

const ReadonlyPostExcerptEdit = createEntityPlaceholderEdit( {
    label: () => __( 'Post Excerpt', TEXT_DOMAIN ),
    resolvedKey: '_resolvedExcerpt',
    kind: 'text',
    fromQueryPreview: ( post ) =>
        typeof post.excerpt === 'string' && post.excerpt !== ''
            ? { text: post.excerpt }
            : null,
    fromPostEntity: ( entity ) => {
        const excerpt = readEntityString( entity.excerpt );
        return excerpt !== '' ? { text: excerpt } : null;
    },
} );

interface EditableExcerptProps {
    readonly identity: PostEntityIdentity;
    readonly blockEditProps: AnyProps;
}

interface ExcerptInputProps {
    readonly value: string;
    readonly onChange: ( next: string ) => void;
}

function PostExcerptInput( { value, onChange }: ExcerptInputProps ): ReactElement {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const blockProps = ( useBlockProps as any )();

    // `tagName` / `__experimentalVersion` are runtime props of
    // `PlainText` the bundled types don't declare — see the Post Title
    // edit for the same cast. `PlainText` (not `RichText`) keeps the
    // excerpt plain text, which is how the entry stores it.
    return (
        <PlainText
            tagName={ 'p' as never }
            placeholder={ __( 'Add an excerpt…', TEXT_DOMAIN ) }
            value={ value }
            onChange={ onChange }
            __experimentalVersion={ 2 }
            { ...blockProps }
        />
    );
}

function EditablePostExcerpt( { identity, blockEditProps }: EditableExcerptProps ): ReactElement {
    const [ editedExcerpt, setExcerpt ] = useEntityProp<
        string | { raw?: string; rendered?: string }
    >( 'postType', identity.postType, 'excerpt', identity.postId );

    // `undefined` means the record hasn't resolved yet — keep the
    // readonly preview (placeholder chip) until there's a value to edit.
    if ( editedExcerpt === undefined ) {
        return <ReadonlyPostExcerptEdit { ...blockEditProps } />;
    }

    return (
        <PostExcerptInput
            value={ readEntityString( editedExcerpt ) }
            onChange={ ( next: string ) => setExcerpt( next ) }
        />
    );
}

export default function PostExcerptEdit( props: AnyProps ): ReactElement {
    const attributes = props.attributes ?? {};
    const context = props.context ?? {};
    const identity = readPostEntityIdentity( context );

    const isEditable =
        identity !== null
        && ! isInQueryLoop( context )
        && ! hasPreviewContext( context )
        && readEntityString( attributes._resolvedExcerpt ) === '';

    if ( ! isEditable || identity === null ) {
        return <ReadonlyPostExcerptEdit { ...props } />;
    }

    return <EditablePostExcerpt identity={ identity } blockEditProps={ props } />;
}
