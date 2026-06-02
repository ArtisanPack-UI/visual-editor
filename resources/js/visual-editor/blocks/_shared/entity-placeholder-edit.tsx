/**
 * Lightweight editor preview for server-rendered entity display blocks.
 *
 * The Phase I5 entity cluster (#413) forks nine *display* entity blocks —
 * the `post-*` and `site-*` families. Their real markup is produced
 * server-side by the Blade / React / Vue renderers from stamped
 * `_resolved*` attributes; the editor's `@wordpress/core-data` shim does
 * not expose the page or site entity to the post editor, so upstream's
 * edit components either render an empty placeholder (`site-*`) or crash
 * (`post-author` unlocks block-editor private APIs and queries the user
 * entity). Delegating to them therefore gives a poor editing experience.
 *
 * Instead each display fork uses this preview. Resolution priority,
 * highest first:
 *
 *  1. The stamped `_resolved*` attribute (front-end / saved-tree path) —
 *     a block that already carries `_resolved*` data previews faithfully.
 *  2. The `artisanpack/postPreview` block context the query block pipes
 *     down (#483) — when inside an `artisanpack/query` loop the editor
 *     previews the resolved first post's data.
 *  3. The live page entity record fetched through the core-data shim
 *     (#481) — when a `post-*` block is placed at the page level
 *     (outside a loop), the preview reads the title / excerpt /
 *     featured image / author / date from the page being edited.
 *  4. The live site-meta entity record fetched through the shim (#481)
 *     — site-title / site-tagline / site-logo preview the configured
 *     site values.
 *  5. A clean, clearly-labelled placeholder so the block looks
 *     intentional in the canvas.
 *
 * `navigation` and `template-part` keep delegating to their upstream
 * edits (see `forked-entity-edit.tsx`) because they drive the V1
 * interactive editor surfaces that #413 must not regress.
 */

import type { CSSProperties, ReactElement } from 'react';
import { useBlockProps } from '@wordpress/block-editor';

import {
    SITE_ENTITY_ID,
    useEntityRecord,
    type EntityRecord,
} from '../../vendor/core-data-shim';
import type { QueryPreviewPost } from '../../editor/use-query-preview';

export type EntityPreviewKind = 'text' | 'html' | 'image';

/**
 * Resolved values pulled from the `artisanpack/postPreview` block
 * context — the editor-side analogue of the `_resolvedX` attributes
 * the server-side `PostResolver` stamps for the front-end renderers.
 *
 * `text` / `html` are mutually exclusive: a `text`/`html` kind block
 * consumes `text`, an `image` kind block consumes `image`. The shape
 * is open so post-* edits can return whichever the block needs.
 */
export interface EntityPreviewValue {
    /** Plain text or HTML fragment to render. */
    readonly text?: string;
    /** Image source URL (`image` kind blocks only). */
    readonly imageUrl?: string;
    /** Image alt text (`image` kind blocks only). */
    readonly imageAlt?: string;
}

/**
 * Shape of the live post entity record returned by the core-data
 * shim for `useEntityRecord('postType', postType, postId)`. Matches
 * the WP REST envelope emitted by `WpEntityResource`. `_preview`
 * carries the same author / date / featured-image data the
 * server-side `PostResolver` stamps for the front-end render.
 *
 * `title` / `excerpt` come back in two shapes depending on the
 * shim selector path: the unflattened `{ raw, rendered }` envelope
 * from `useEntityRecord` (which our `fromPostEntity` extractors
 * consume via `readEntityString` below), or the flattened raw
 * string the shim's `getEditedEntityRecord` returns after
 * `flattenRawProperties` collapses the envelope. Typing them as the
 * union lets the same `PostEntityRecord` cover both reads without a
 * separate "edited" record type.
 */
export interface PostEntityRecord extends EntityRecord {
    readonly id?: number | string;
    readonly title?: string | { raw?: string; rendered?: string };
    readonly excerpt?: string | { raw?: string; rendered?: string };
    readonly date?: string;
    readonly featured_media?: number | null;
    readonly _preview?: {
        readonly dateFormatted?: string | null;
        readonly author?: {
            readonly name?: string;
            readonly bio?: string;
            readonly url?: string;
            readonly avatarUrl?: string;
        } | null;
        readonly featuredImage?: {
            readonly url: string;
            readonly alt?: string;
            readonly width?: number;
            readonly height?: number;
        } | null;
    };
}

/**
 * Shape of the live site-meta entity record returned by
 * `useEntityRecord('root', '__unstableBase', SITE_ENTITY_ID)`. The
 * controller backing the request emits `title` / `description` as
 * `{ raw, rendered }` so the shim's `flattenRawProperties` collapses
 * them to a string on the edited-record read path, but the unflattened
 * `record` shape preserves the object form — the extractor below
 * handles either.
 */
export interface SiteEntityRecord extends EntityRecord {
    readonly id?: number | string;
    readonly title?: string | { raw?: string; rendered?: string };
    readonly description?: string | { raw?: string; rendered?: string };
    readonly url?: string;
    readonly logo?: number | null;
    readonly logoUrl?: string;
}

export interface EntityPlaceholderConfig {
    /** Human-readable block label, e.g. "Post Title". */
    readonly label: string;
    /** Attribute key holding the resolved value, e.g. `_resolvedTitle`. */
    readonly resolvedKey: string;
    /** How to render the resolved value. */
    readonly kind: EntityPreviewKind;
    /** Optional second attribute key (e.g. image alt text). */
    readonly altKey?: string;
    /**
     * Optional extractor that pulls the block's value from the
     * `artisanpack/postPreview` block context the query block pipes
     * down (#483). When the block is inside an `artisanpack/query`
     * loop, the resolved first post is available here; otherwise the
     * extractor is skipped and the next strategy is tried.
     */
    readonly fromQueryPreview?: ( post: QueryPreviewPost ) => EntityPreviewValue | null;
    /**
     * Optional extractor that pulls the block's value from a live
     * post entity record fetched through the core-data shim. Triggered
     * when the block's `postId` / `postType` block context is present
     * (the editor wraps the canvas in `BlockContextProvider` with the
     * active page's id/type — see `editor-app.tsx`). Used by the
     * `artisanpack/post-*` previews so a block placed at the page
     * level (outside a query loop) reads the live page entity (#481).
     */
    readonly fromPostEntity?: ( entity: PostEntityRecord ) => EntityPreviewValue | null;
    /**
     * Optional extractor that pulls the block's value from the live
     * singleton site-meta entity (`root/__unstableBase`). Used by the
     * `artisanpack/site-*` previews (#481) so the editor canvas
     * shows the configured site title / tagline / logo instead of a
     * generic placeholder chip.
     */
    readonly fromSiteEntity?: ( site: SiteEntityRecord ) => EntityPreviewValue | null;
    /**
     * Realistic dummy data to display in the editor canvas when no
     * resolved value, no `artisanpack/postPreview` context, and no
     * live core-data entity is available. Authors editing a template
     * (in the site editor or post editor) get a representative block
     * they can style — much more useful than a generic chip. Front-end
     * render never sees this; only the editor does.
     */
    readonly dummyValue?: EntityPreviewValue;
}

const PREVIEW_CONTEXT_KEY = 'artisanpack/postPreview';
const POST_ID_CONTEXT_KEY = 'postId';
const POST_TYPE_CONTEXT_KEY = 'postType';

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

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type AnyProps = Record<string, any>;

function asString( value: unknown ): string {
    return typeof value === 'string' ? value : '';
}

/**
 * Reduce an HTML string to its text content for the editor preview.
 *
 * The placeholder edit is only a canvas preview — the real, fully-formatted
 * markup is produced by the server-side renderers. Rendering the resolved
 * HTML as text (rather than via `dangerouslySetInnerHTML`) keeps the
 * preview dependency-free and removes any HTML-injection surface from the
 * editor. Falls back to a tag-strip when `DOMParser` is unavailable.
 */
function htmlToText( html: string ): string {
    if ( typeof DOMParser === 'undefined' ) {
        return html.replace( /<[^>]*>/g, '' );
    }

    return (
        new DOMParser().parseFromString( html, 'text/html' ).body
            .textContent ?? ''
    );
}

function readQueryPreviewPost( context: unknown ): QueryPreviewPost | null {
    if ( context === null || typeof context !== 'object' ) {
        return null;
    }

    const value = ( context as Record<string, unknown> )[ PREVIEW_CONTEXT_KEY ];

    if ( value === null || typeof value !== 'object' ) {
        return null;
    }

    return value as QueryPreviewPost;
}

interface PostEntityIdentity {
    readonly postType: string;
    readonly postId: number;
}

/**
 * Reads the `postId` / `postType` pair from a block's `usesContext`
 * payload. Returns null unless both are present and well-formed —
 * post-* blocks placed outside any entity context (e.g. on a non-
 * cms-framework resource) skip the entity-fetch path entirely.
 */
function readPostEntityIdentity( context: unknown ): PostEntityIdentity | null {
    if ( context === null || typeof context !== 'object' ) {
        return null;
    }

    const record = context as Record<string, unknown>;
    const postType = record[ POST_TYPE_CONTEXT_KEY ];
    const rawPostId = record[ POST_ID_CONTEXT_KEY ];

    if ( typeof postType !== 'string' || postType === '' ) {
        return null;
    }

    if ( typeof rawPostId === 'number' && Number.isInteger( rawPostId ) && rawPostId > 0 ) {
        return { postType, postId: rawPostId };
    }

    if ( typeof rawPostId === 'string' && /^[1-9]\d*$/.test( rawPostId ) ) {
        return { postType, postId: Number.parseInt( rawPostId, 10 ) };
    }

    return null;
}

/**
 * Subscribes the block's edit component to the live page entity
 * record for its ambient `postType` / `postId` block context and
 * extracts a preview value through the supplied `extractor`. Returns
 * null when the context isn't populated, no extractor was supplied,
 * the record hasn't resolved yet, or the extractor itself returned
 * null. Always calls `useEntityRecord` (with a `null` id when the
 * context is absent) so React's rules-of-hooks invariant holds across
 * renders.
 */
function useLivePostEntityValue(
    extractor: EntityPlaceholderConfig['fromPostEntity'] | null | undefined,
    blockContext: unknown,
): EntityPreviewValue | null {
    const identity = readPostEntityIdentity( blockContext );

    const { record } = useEntityRecord<PostEntityRecord>(
        identity !== null ? 'postType' : undefined,
        identity !== null ? identity.postType : undefined,
        identity !== null ? identity.postId : null,
    );

    if ( extractor === null || extractor === undefined ) {
        return null;
    }

    if ( identity === null || record === null ) {
        return null;
    }

    return extractor( record );
}

/**
 * Subscribes the block's edit component to the singleton site-meta
 * entity record and extracts a preview value through the supplied
 * `extractor`. Returns null when the extractor wasn't supplied, the
 * record hasn't resolved yet, or the extractor itself returned null.
 * Always calls `useEntityRecord` (with a `null` id when no extractor
 * was supplied) so the hook fires unconditionally.
 */
function useLiveSiteEntityValue(
    extractor: EntityPlaceholderConfig['fromSiteEntity'] | null | undefined,
): EntityPreviewValue | null {
    const enabled = extractor !== null && extractor !== undefined;

    const { record } = useEntityRecord<SiteEntityRecord>(
        enabled ? 'root' : undefined,
        enabled ? '__unstableBase' : undefined,
        enabled ? SITE_ENTITY_ID : null,
    );

    if ( ! enabled ) {
        return null;
    }

    if ( record === null ) {
        return null;
    }

    return extractor( record );
}

/**
 * Build an `edit` component for a server-rendered entity display fork.
 */
export function createEntityPlaceholderEdit(
    config: EntityPlaceholderConfig
): ( props: AnyProps ) => ReactElement {
    function EntityPlaceholderEdit( props: AnyProps ): ReactElement {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const blockProps = ( useBlockProps as any )();
        const attributes = props.attributes ?? {};

        // Live entity hooks must fire unconditionally on every render
        // — see React's rules-of-hooks. The hooks return null when
        // their context isn't populated or no extractor was supplied,
        // so the conditional chain below stays cheap on blocks that
        // don't opt into the live-preview paths.
        const livePostEntityValue = useLivePostEntityValue(
            config.fromPostEntity,
            props.context,
        );
        const liveSiteEntityValue = useLiveSiteEntityValue( config.fromSiteEntity );

        // Priority order:
        //   1. stamped `_resolved*` attribute (front-end / saved-tree path)
        //   2. `artisanpack/postPreview` block context (#483 query loop)
        //   3. live post entity from `postId`/`postType` context (#481)
        //   4. live singleton site-meta entity (#481)
        //   5. placeholder label
        const resolvedValue: EntityPreviewValue = readResolvedAttributes( config, attributes );

        if ( hasPreviewValue( config.kind, resolvedValue ) ) {
            return renderResolved( config.kind, resolvedValue, blockProps );
        }

        if ( config.fromQueryPreview !== undefined ) {
            const post = readQueryPreviewPost( props.context );

            if ( post !== null ) {
                const fromContext = config.fromQueryPreview( post );

                if ( fromContext !== null && hasPreviewValue( config.kind, fromContext ) ) {
                    return renderResolved( config.kind, fromContext, blockProps );
                }
            }
        }

        if ( livePostEntityValue !== null && hasPreviewValue( config.kind, livePostEntityValue ) ) {
            return renderResolved( config.kind, livePostEntityValue, blockProps );
        }

        if ( liveSiteEntityValue !== null && hasPreviewValue( config.kind, liveSiteEntityValue ) ) {
            return renderResolved( config.kind, liveSiteEntityValue, blockProps );
        }

        // No resolved data, no query/entity context — render the
        // configured dummy value so authors editing a template (with
        // no specific entity in scope) see what the block will look
        // like styled. Final fallback is the original labelled chip.
        if (
            config.dummyValue !== undefined &&
            hasPreviewValue( config.kind, config.dummyValue )
        ) {
            return renderResolved( config.kind, config.dummyValue, blockProps );
        }

        return (
            <div { ...blockProps }>
                <span style={ placeholderStyle } contentEditable={ false }>
                    { config.label }
                </span>
            </div>
        );
    }

    EntityPlaceholderEdit.displayName = `EntityPlaceholderEdit(${ config.label })`;

    return EntityPlaceholderEdit;
}

function readResolvedAttributes(
    config: EntityPlaceholderConfig,
    attributes: AnyProps
): EntityPreviewValue {
    const resolved = asString( attributes?.[ config.resolvedKey ] );

    if ( config.kind === 'image' ) {
        const alt = config.altKey ? asString( attributes?.[ config.altKey ] ) : '';
        return { imageUrl: resolved, imageAlt: alt };
    }

    return { text: resolved };
}

function hasPreviewValue( kind: EntityPreviewKind, value: EntityPreviewValue ): boolean {
    if ( kind === 'image' ) {
        return typeof value.imageUrl === 'string' && value.imageUrl !== '';
    }
    return typeof value.text === 'string' && value.text !== '';
}

function renderResolved(
    kind: EntityPreviewKind,
    value: EntityPreviewValue,
    blockProps: AnyProps
): ReactElement {
    if ( kind === 'html' ) {
        // Text-only preview — see `htmlToText`. The server-side
        // renderers emit the full formatted markup on the front end.
        return <div { ...blockProps }>{ htmlToText( value.text ?? '' ) }</div>;
    }

    if ( kind === 'image' ) {
        return (
            <div { ...blockProps }>
                { /* eslint-disable-next-line jsx-a11y/alt-text */ }
                <img
                    src={ value.imageUrl ?? '' }
                    alt={ value.imageAlt ?? '' }
                    style={ { maxWidth: '100%' } }
                />
            </div>
        );
    }

    return <div { ...blockProps }>{ value.text ?? '' }</div>;
}

/**
 * Extract a plain string from a `{ raw, rendered }` shaped field or
 * a plain string. Used by `fromSiteEntity` / `fromPostEntity`
 * extractors below to read `title` / `description` regardless of
 * whether the consumer received the unflattened record (object shape)
 * or the flattened edited record (string).
 */
export function readEntityString( value: unknown ): string {
    if ( typeof value === 'string' ) {
        return value;
    }

    if ( value !== null && typeof value === 'object' ) {
        const shape = value as { raw?: unknown; rendered?: unknown };

        if ( typeof shape.raw === 'string' ) {
            return shape.raw;
        }

        if ( typeof shape.rendered === 'string' ) {
            return shape.rendered;
        }
    }

    return '';
}

export default createEntityPlaceholderEdit;
