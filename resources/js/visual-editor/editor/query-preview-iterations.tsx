/**
 * Shared multi-post iteration renderer for `post-template` editor
 * previews (both the `core/post-template` override and the first-party
 * `artisanpack/post-template`). Issue #599.
 *
 * Renders one editable iteration (`<InnerBlocks />`) plus N read-only
 * "ghost" iterations for the remaining resolved posts. Each iteration
 * is wrapped in its own `BlockContextProvider` keyed by `postId` +
 * `postType` so descendant `post-*` block edits resolve against the
 * right post (#520 entity-adapter mechanism).
 *
 * Per-iteration variant resolution (#604): the post-template's
 * `innerBlocks` are partitioned into base children and
 * `artisanpack/post-variant` children. Each iteration resolves to
 * either a specific variant or the base set via the shared
 * `variant-matcher` engine (parity with server-side `VariantResolver`).
 * Ghost iterations render the resolved set via `useBlockPreview`. The
 * editable iteration still renders the post-template's full inner
 * blocks via `<InnerBlocks />` — variant blocks are kept in the tree so
 * authors can edit them via the list view / inspector — but the
 * resolved variant for the active post is signalled via
 * `data-resolved-variant-order` so styling (and the post-variant
 * block's own visibility CSS) can collapse the other variants out of
 * the way.
 *
 * Auto-jump (#604): selecting a `artisanpack/post-variant` block (or
 * any descendant thereof) bumps `activeId` to the first preview post
 * the variant matches, so the canvas WYSIWYGs to the variant the
 * author is editing.
 */

import type { ReactElement, KeyboardEvent } from 'react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    BlockContextProvider,
    InnerBlocks,
    store as blockEditorStore,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalUseBlockPreview as rawUseBlockPreview,
} from '@wordpress/block-editor';
import type { BlockInstance } from '@wordpress/blocks';
import { useSelect } from '@wordpress/data';
import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../vendor/i18n';
import type { QueryPreviewContextValue } from './query-preview-context';
import {
    getQueryPreviewIterationCount,
    QUERY_PREVIEW_ITERATION_CAP,
} from './query-preview-context';
import type { QueryPreviewPost } from './use-query-preview';
import {
    resolveVariant,
    type Matcher,
    type PreviewPostMeta,
    type VariantDescriptor,
} from './variant-matcher';

const POST_VARIANT_BLOCK_NAME = 'artisanpack/post-variant';

interface UseBlockPreviewOptions {
    readonly blocks: ReadonlyArray<BlockInstance>;
    readonly props?: Record<string, unknown>;
}
type UseBlockPreviewResult = Record<string, unknown> & { children?: unknown };
const useBlockPreview = rawUseBlockPreview as
    ( ( options: UseBlockPreviewOptions ) => UseBlockPreviewResult ) | undefined;

export interface QueryPreviewIterationsProps {
    /** clientId of the `post-template` block driving the iteration. */
    readonly clientId: string;
    /** Query preview state from the surrounding query block. */
    readonly preview: QueryPreviewContextValue | null;
    /** Post type for the BlockContextProvider — defaults to `post`. */
    readonly postType: string;
    /** Default template applied to the editable iteration's InnerBlocks. */
    readonly defaultTemplate?: ReadonlyArray<[ string ]>;
    /**
     * Wrapper element name. Defaults to `ul` to match upstream
     * Gutenberg's grid CSS (`.wp-block-post-template.is-flex-container
     * > li`). Restricted to list-shaped tags because every iteration
     * renders as a `<li>` — a `<div>` wrapper would produce invalid
     * HTML (`<div> > <li>`).
     */
    readonly tag?: 'ul' | 'ol';
    /**
     * Class name applied to every iteration's wrapper element.
     * Defaults to `wp-block-post` — the class upstream Gutenberg uses
     * on each post iteration and the one the grid CSS in
     * `@wordpress/block-library/build-style/style.css` already targets.
     */
    readonly itemClassName?: string;
    /** Optional extra props (style, className) for the outer wrapper. */
    readonly outerProps?: Record<string, unknown>;
}

interface PostTemplateBlockShape {
    readonly clientId?: string;
    readonly innerBlocks?: ReadonlyArray<BlockInstance>;
    readonly attributes?: Record<string, unknown>;
}

interface BlockEditorStoreShape {
    readonly getBlock?: ( id: string ) => PostTemplateBlockShape | null;
    readonly getSelectedBlockClientId?: () => string | null;
    readonly getBlockParents?: ( clientId: string ) => ReadonlyArray<string>;
    readonly getBlockName?: ( clientId: string ) => string | undefined;
}

interface PostTemplateSnapshot {
    readonly innerBlocks: ReadonlyArray<BlockInstance>;
    readonly compiledVariantMap: Record<number, number>;
    readonly selectedVariantClientId: string | null;
}

function readMatcher( attrs: Record<string, unknown> | undefined ): Matcher {
    const raw = attrs?.matcher;
    if (
        raw !== null &&
        typeof raw === 'object' &&
        ! Array.isArray( raw ) &&
        typeof ( raw as { kind?: unknown } ).kind === 'string' &&
        typeof ( raw as { value?: unknown } ).value === 'string'
    ) {
        return raw as Matcher;
    }
    return { kind: 'position', value: 'first' };
}

function readCompiledMap( attrs: Record<string, unknown> | undefined ): Record<number, number> {
    const raw = attrs?._compiledVariantMap;
    if ( raw === null || raw === undefined || typeof raw !== 'object' || Array.isArray( raw ) ) {
        return {};
    }
    const out: Record<number, number> = {};
    for ( const [ key, value ] of Object.entries( raw as Record<string, unknown> ) ) {
        const idx = Number.parseInt( key, 10 );
        if ( Number.isFinite( idx ) && typeof value === 'number' && Number.isFinite( value ) ) {
            out[ idx ] = value;
        }
    }
    return out;
}

function buildDescriptors(
    variantBlocks: ReadonlyArray<BlockInstance>
): VariantDescriptor[] {
    return variantBlocks.map( ( block, idx ) => {
        const attrs = block.attributes ?? {};
        const matcher = readMatcher( attrs );
        const priority =
            typeof attrs.priority === 'number' ? ( attrs.priority as number ) : 10;
        const label =
            typeof attrs.label === 'string' ? ( attrs.label as string ) : undefined;
        return {
            order: idx,
            matcher,
            priority,
            label,
        };
    } );
}

function toPreviewMeta( post: QueryPreviewPost ): PreviewPostMeta {
    const taxonomies: Record<string, ReadonlyArray<string>> = {};
    if ( post.terms ) {
        for ( const [ tax, terms ] of Object.entries( post.terms ) ) {
            const slugs = terms
                .map( ( term ) => term.slug )
                .filter( ( slug ): slug is string => typeof slug === 'string' && slug !== '' );
            if ( slugs.length > 0 ) {
                taxonomies[ tax ] = slugs;
            }
        }
    }
    return {
        hasFeaturedImage:
            post.featuredImage !== null &&
            post.featuredImage !== undefined &&
            typeof post.featuredImage.url === 'string' &&
            post.featuredImage.url !== '',
        taxonomies,
    };
}

function selectPostTemplateSnapshot(
    select: ( storeName: typeof blockEditorStore ) => unknown,
    clientId: string
): PostTemplateSnapshot {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const store = ( select as any )( blockEditorStore ) as BlockEditorStoreShape;
    const block = store.getBlock?.( clientId ) ?? null;

    const innerBlocks = block?.innerBlocks ?? [];
    const compiledVariantMap = readCompiledMap( block?.attributes );

    let selectedVariantClientId: string | null = null;
    const selectedId = store.getSelectedBlockClientId?.() ?? null;
    if ( selectedId !== null && selectedId !== undefined ) {
        const selectedName = store.getBlockName?.( selectedId );
        if ( selectedName === POST_VARIANT_BLOCK_NAME ) {
            selectedVariantClientId = selectedId;
        } else {
            const parents = store.getBlockParents?.( selectedId ) ?? [];
            for ( const parentId of parents ) {
                if ( store.getBlockName?.( parentId ) === POST_VARIANT_BLOCK_NAME ) {
                    selectedVariantClientId = parentId;
                    break;
                }
            }
        }
    }

    return {
        innerBlocks,
        compiledVariantMap,
        selectedVariantClientId,
    };
}

/**
 * Render the editable iteration + N read-only ghosts for a resolved
 * query loop in the editor canvas. Caller is responsible for the
 * outer wrapper / block props — this component only renders the
 * iterations themselves.
 */
export function QueryPreviewIterations(
    props: QueryPreviewIterationsProps
): ReactElement {
    const {
        clientId,
        preview,
        postType,
        defaultTemplate,
        tag = 'ul',
        itemClassName = 'wp-block-post',
        outerProps = {},
    } = props;

    const snapshot = useSelect(
        ( select ) => selectPostTemplateSnapshot( select as never, clientId ),
        [ clientId ]
    );
    const { innerBlocks, compiledVariantMap, selectedVariantClientId } = snapshot;

    const { baseChildren, variantBlocks, descriptors } = useMemo( () => {
        const base: BlockInstance[] = [];
        const variants: BlockInstance[] = [];
        for ( const child of innerBlocks ) {
            if ( child.name === POST_VARIANT_BLOCK_NAME ) {
                variants.push( child );
            } else {
                base.push( child );
            }
        }
        return {
            baseChildren: base,
            variantBlocks: variants,
            descriptors: buildDescriptors( variants ),
        };
    }, [ innerBlocks ] );

    const posts = preview?.posts ?? [];
    const cap = getQueryPreviewIterationCount( posts, preview?.perPage );
    const visiblePosts = posts.slice( 0, cap );
    const total = visiblePosts.length;

    // Resolve each visible iteration to a variant order (or null = base).
    const resolvedOrders = useMemo<ReadonlyArray<number | null>>(
        () =>
            visiblePosts.map( ( post, idx ) =>
                resolveVariant(
                    idx,
                    total,
                    toPreviewMeta( post ),
                    descriptors,
                    compiledVariantMap
                )
            ),
        [ visiblePosts, total, descriptors, compiledVariantMap ]
    );

    // Track which iteration is the editable one. Clicking on a ghost
    // promotes it. Defaults to the first post; resets when the posts
    // array no longer contains the active id (e.g. after a query
    // attribute change).
    const [ activeId, setActiveId ] = useState<number | undefined>( undefined );
    const firstPostId = visiblePosts[ 0 ]?.id;
    const activeIdInList = activeId !== undefined && visiblePosts.some( ( post ) => post.id === activeId );
    const effectiveActiveId = activeIdInList ? activeId : firstPostId;

    useEffect( () => {
        if ( activeId !== undefined && ! activeIdInList ) {
            setActiveId( undefined );
        }
    }, [ activeId, activeIdInList ] );

    // Auto-jump to the first iteration that resolves to the selected
    // variant. Tracked via a ref so re-renders driven by other state
    // changes (e.g. inner-block edits) don't keep stealing focus from
    // the user's most recent ghost click.
    const lastHandledSelectionRef = useRef<string | null>( null );
    useEffect( () => {
        if ( selectedVariantClientId === null ) {
            lastHandledSelectionRef.current = null;
            return;
        }
        if ( lastHandledSelectionRef.current === selectedVariantClientId ) {
            return;
        }
        const variantIdx = variantBlocks.findIndex(
            ( block ) => block.clientId === selectedVariantClientId
        );
        if ( variantIdx === -1 ) {
            return;
        }
        const matchPos = resolvedOrders.findIndex( ( order ) => order === variantIdx );
        if ( matchPos === -1 ) {
            // The variant doesn't match any visible iteration — leave
            // the active iteration alone rather than blanking the canvas.
            lastHandledSelectionRef.current = selectedVariantClientId;
            return;
        }
        const targetId = visiblePosts[ matchPos ]?.id;
        if ( targetId !== undefined && targetId !== effectiveActiveId ) {
            setActiveId( targetId );
        }
        lastHandledSelectionRef.current = selectedVariantClientId;
    }, [
        selectedVariantClientId,
        variantBlocks,
        resolvedOrders,
        visiblePosts,
        effectiveActiveId,
    ] );

    const editableTemplate = defaultTemplate !== undefined ? [ ...defaultTemplate ] : undefined;

    // CSS rules that collapse the unmatched children of the editable
    // iteration. `<InnerBlocks />` always renders every direct child
    // of the post-template (base + every variant), so without this the
    // canvas would stack the resolved variant on top of the base
    // template for the active post. The rules are scoped by the
    // editable iteration's `data-resolved-variant-order` and by each
    // variant block's stable `data-block` (clientId), so toggling the
    // active iteration to a different variant just flips which child
    // is visible — no React tree churn required.
    //
    // Computed unconditionally (before the early-return below) so the
    // hook count is stable across renders, regardless of whether the
    // preview has resolved any posts yet.
    //
    // The rules are instance-scoped via `data-query-preview-root="<clientId>"`
    // on the outer wrapper so a second Query Loop block on the same
    // canvas can't accidentally collapse children in this one.
    const scopedRootSelector = '[data-query-preview-root="' + clientId + '"]';
    const variantCollapseStyles = useMemo( () => {
        if ( variantBlocks.length === 0 ) {
            return '';
        }
        const rules: string[] = [];
        // When resolved=base: hide every post-variant child of the
        // editable iteration.
        rules.push(
            scopedRootSelector +
                ' li[data-query-iteration="editable"][data-resolved-variant-order="base"] [data-type="' +
                POST_VARIANT_BLOCK_NAME +
                '"]{display:none!important;}'
        );
        // When resolved=<order>: hide every non-matching child of the
        // editable iteration's inner-blocks layout (both base blocks
        // and the other variants).
        variantBlocks.forEach( ( variant, order ) => {
            const cid = variant.clientId;
            rules.push(
                scopedRootSelector +
                    ' li[data-query-iteration="editable"][data-resolved-variant-order="' +
                    String( order ) +
                    '"] > .block-editor-inner-blocks > .block-editor-block-list__layout > :not([data-block="' +
                    cid +
                    '"]){display:none!important;}'
            );
        } );
        return rules.join( '' );
    }, [ variantBlocks, scopedRootSelector ] );

    if ( visiblePosts.length === 0 ) {
        const Tag = tag;
        // Keep the list-element invariant: the only valid child of
        // `<ul>` / `<ol>` is `<li>`, so wrap the placeholder iteration
        // in an `<li>` and render the explanatory `Notice` as a
        // sibling of the list rather than as a direct child.
        return (
            <>
                <Tag
                    {...outerProps as Record<string, unknown>}
                    data-query-preview-root={ clientId }
                >
                    <BlockContextProvider value={{ postType }}>
                        <li className={ itemClassName } data-query-iteration="editable">
                            <InnerBlocks template={ editableTemplate } />
                        </li>
                    </BlockContextProvider>
                </Tag>
                { preview?.status === 'ready' && (
                    <Notice status="info" isDismissible={ false }>
                        { __(
                            'No posts matched the current query. The template above will render when posts match.',
                            TEXT_DOMAIN
                        ) }
                    </Notice>
                ) }
            </>
        );
    }

    const ghostNotice = posts.length > cap || ( preview?.perPage ?? 0 ) > QUERY_PREVIEW_ITERATION_CAP
        ? (
            <Notice status="info" isDismissible={ false }>
                { __(
                    'Canvas previews the first iterations only. The saved page renders the full result set.',
                    TEXT_DOMAIN
                ) }
            </Notice>
        )
        : null;

    const Tag = tag;
    return (
        <>
            { variantCollapseStyles !== '' && (
                <style data-source="query-preview-iterations-variant-collapse">
                    { variantCollapseStyles }
                </style>
            ) }
            <Tag
                {...outerProps as Record<string, unknown>}
                data-query-preview-root={ clientId }
            >
                { visiblePosts.map( ( post, idx ) => {
                    const iterationContext = { postType, postId: post.id, 'artisanpack/postPreview': post };
                    const isActive = post.id === effectiveActiveId;
                    const resolvedOrder = resolvedOrders[ idx ] ?? null;
                    const resolvedBlocks: ReadonlyArray<BlockInstance> =
                        resolvedOrder !== null && variantBlocks[ resolvedOrder ] !== undefined
                            ? variantBlocks[ resolvedOrder ].innerBlocks ?? []
                            : baseChildren;
                    const resolvedAttr = resolvedOrder === null ? 'base' : String( resolvedOrder );

                    return (
                        <BlockContextProvider key={ post.id } value={ iterationContext }>
                            { isActive ? (
                                <EditableIteration
                                    className={ itemClassName }
                                    template={ editableTemplate }
                                    resolvedVariantOrder={ resolvedAttr }
                                />
                            ) : (
                                <PreviewIteration
                                    className={ itemClassName }
                                    blocks={ resolvedBlocks }
                                    resolvedVariantOrder={ resolvedAttr }
                                    onActivate={ () => setActiveId( post.id ) }
                                />
                            ) }
                        </BlockContextProvider>
                    );
                } ) }
            </Tag>
            { /* Notices live outside the list so the ul/ol only
                 contains `<li>` children — direct text/non-list
                 nodes in a `<ul>` are invalid markup. */ }
            { ghostNotice }
        </>
    );
}

interface EditableIterationProps {
    readonly className: string;
    readonly template: ReadonlyArray<[ string ]> | undefined;
    readonly resolvedVariantOrder: string;
}

function EditableIteration( {
    className,
    template,
    resolvedVariantOrder,
}: EditableIterationProps ): ReactElement {
    return (
        <li
            className={ className }
            data-query-iteration="editable"
            data-resolved-variant-order={ resolvedVariantOrder }
        >
            <InnerBlocks template={ template !== undefined ? [ ...template ] : undefined } />
        </li>
    );
}

interface PreviewIterationProps {
    readonly className: string;
    readonly blocks: ReadonlyArray<BlockInstance>;
    readonly resolvedVariantOrder: string;
    readonly onActivate: () => void;
}

function PreviewIteration( {
    className,
    blocks,
    resolvedVariantOrder,
    onActivate,
}: PreviewIterationProps ): ReactElement {
    // Defensive fallback when the block-editor build doesn't expose
    // the live preview hook (older builds / mocked test envs). The
    // iteration still takes up grid space and the per-post block
    // context above still establishes the right scope; descendants
    // that don't use the preview tree (e.g. server-rendered display
    // forks reading `_resolved*`) keep working.
    if ( useBlockPreview === undefined ) {
        return (
            <li
                className={ className }
                data-query-iteration="preview"
                data-resolved-variant-order={ resolvedVariantOrder }
                aria-hidden={ true }
            />
        );
    }

    return (
        <PreviewIterationLive
            className={ className }
            blocks={ blocks }
            resolvedVariantOrder={ resolvedVariantOrder }
            onActivate={ onActivate }
        />
    );
}

function PreviewIterationLive( {
    className,
    blocks,
    resolvedVariantOrder,
    onActivate,
}: PreviewIterationProps ): ReactElement {
    const previewProps = ( useBlockPreview as ( opts: UseBlockPreviewOptions ) => UseBlockPreviewResult )( {
        blocks,
        props: { className },
    } );

    const handleKeyDown = ( event: KeyboardEvent<HTMLLIElement> ): void => {
        if ( event.key === 'Enter' || event.key === ' ' ) {
            event.preventDefault();
            onActivate();
        }
    };

    return (
        <li
            { ...previewProps as Record<string, unknown> }
            data-query-iteration="preview"
            data-resolved-variant-order={ resolvedVariantOrder }
            tabIndex={ 0 }
            // eslint-disable-next-line jsx-a11y/no-noninteractive-element-to-interactive-role
            role="button"
            onClick={ onActivate }
            onKeyDown={ handleKeyDown }
        />
    );
}
