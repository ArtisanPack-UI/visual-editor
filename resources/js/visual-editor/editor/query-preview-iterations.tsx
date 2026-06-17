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
 * The ghosts use `__experimentalUseBlockPreview` from
 * `@wordpress/block-editor` — the same hook upstream Gutenberg's
 * `core/post-template` reaches for. It renders the inner-block tree
 * via a nested `ExperimentalBlockEditorProvider` scope with
 * `useDisabled` applied, so dynamic display blocks (`post-title`,
 * `post-excerpt`, `post-date`, …) run their actual Edit components
 * against the per-iteration block context — no serialization, no
 * iframe, no save-element fallback. The iframe-based `<BlockPreview>`
 * component is explicitly avoided here for the same reason the
 * inserter-patterns panel documents: multiple iframes collide with
 * the M2 CSP shim. `useBlockPreview` is the iframe-free counterpart.
 *
 * Active-iteration UX: clicking (or pressing Enter / Space on) any
 * ghost iteration promotes it to the editable iteration, matching
 * upstream Gutenberg's `core/post-template` behavior. The shared
 * inner-block tree is what gets edited — `BlockContextProvider`
 * around the active iteration just changes which post's data the
 * descendant `post-*` blocks resolve against.
 */

import type { ReactElement, KeyboardEvent } from 'react';
import { useEffect, useMemo, useState } from 'react';
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
     * > li`). Callers can override to `div` etc. if they're styling
     * with a custom layout.
     */
    readonly tag?: 'div' | 'ul' | 'ol';
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
    readonly innerBlocks?: ReadonlyArray<BlockInstance>;
}

function selectInnerBlocks(
    select: ( storeName: typeof blockEditorStore ) => unknown,
    clientId: string
): ReadonlyArray<BlockInstance> {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const store = ( select as any )( blockEditorStore ) as { getBlock?: ( id: string ) => PostTemplateBlockShape | null };
    const block = store.getBlock?.( clientId ) ?? null;

    if ( block === null || block.innerBlocks === undefined ) {
        return [];
    }

    return block.innerBlocks;
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

    // Subscribe to the post-template's own inner blocks so the ghost
    // preview stays in sync with edits to the editable iteration.
    const innerBlocks = useSelect(
        ( select ) => selectInnerBlocks( select as never, clientId ),
        [ clientId ]
    );

    const ghostBlocks = useMemo<ReadonlyArray<BlockInstance>>(
        () => innerBlocks,
        [ innerBlocks ]
    );

    const posts = preview?.posts ?? [];
    const cap = getQueryPreviewIterationCount( posts, preview?.perPage );
    const visiblePosts = posts.slice( 0, cap );

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

    const editableTemplate = defaultTemplate !== undefined ? [ ...defaultTemplate ] : undefined;

    if ( visiblePosts.length === 0 ) {
        const Tag = tag;
        return (
            <Tag {...outerProps as Record<string, unknown>}>
                <BlockContextProvider value={{ postType }}>
                    <InnerBlocks template={ editableTemplate } />
                </BlockContextProvider>
                { preview?.status === 'ready' && (
                    <Notice status="info" isDismissible={ false }>
                        { __(
                            'No posts matched the current query. The template above will render when posts match.',
                            TEXT_DOMAIN
                        ) }
                    </Notice>
                ) }
            </Tag>
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
        <Tag {...outerProps as Record<string, unknown>}>
            { visiblePosts.map( ( post ) => {
                const iterationContext = { postType, postId: post.id, 'artisanpack/postPreview': post };
                const isActive = post.id === effectiveActiveId;

                return (
                    <BlockContextProvider key={ post.id } value={ iterationContext }>
                        { isActive ? (
                            <EditableIteration
                                className={ itemClassName }
                                template={ editableTemplate }
                            />
                        ) : (
                            <PreviewIteration
                                className={ itemClassName }
                                blocks={ ghostBlocks }
                                onActivate={ () => setActiveId( post.id ) }
                            />
                        ) }
                    </BlockContextProvider>
                );
            } ) }
            { ghostNotice }
        </Tag>
    );
}

interface EditableIterationProps {
    readonly className: string;
    readonly template: ReadonlyArray<[ string ]> | undefined;
}

function EditableIteration( { className, template }: EditableIterationProps ): ReactElement {
    return (
        <li className={ className } data-query-iteration="editable">
            <InnerBlocks template={ template !== undefined ? [ ...template ] : undefined } />
        </li>
    );
}

interface PreviewIterationProps {
    readonly className: string;
    readonly blocks: ReadonlyArray<BlockInstance>;
    readonly onActivate: () => void;
}

function PreviewIteration( {
    className,
    blocks,
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
                aria-hidden={ true }
            />
        );
    }

    return (
        <PreviewIterationLive
            className={ className }
            blocks={ blocks }
            onActivate={ onActivate }
        />
    );
}

function PreviewIterationLive( {
    className,
    blocks,
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
            tabIndex={ 0 }
            // eslint-disable-next-line jsx-a11y/no-noninteractive-element-to-interactive-role
            role="button"
            onClick={ onActivate }
            onKeyDown={ handleKeyDown }
        />
    );
}
