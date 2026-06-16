/**
 * "Post Variants" panel for the Query Loop inspector (#591).
 *
 * - Lists every `artisanpack/post-variant` block under the query's
 *   nested `post-template`.
 * - Add / select / delete / reorder via `core/block-editor` dispatch.
 * - Side-effect: writes the precompiled `position → variantId` map
 *   onto the parent `post-template`'s `_compiledVariantMap` attribute
 *   whenever variants change. The map is consumed by the server-side
 *   inliner as the O(1) lookup for static (position / pattern) rules.
 */

import { useEffect, useMemo, type ReactElement } from 'react';
import { Button, PanelBody } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import {
    type Matcher,
    type VariantDescriptor,
    compileStaticMap,
} from '../../editor/variant-matcher';

import './post-variants-panel.css';

interface BlockEditorBlock {
    clientId: string;
    name: string;
    attributes: Record<string, unknown>;
    innerBlocks?: BlockEditorBlock[];
}

interface CoreBlockEditorSelect {
    getBlocks: ( clientId?: string ) => BlockEditorBlock[];
}

interface CoreBlockEditorDispatch {
    insertBlock: ( block: unknown, index?: number, rootClientId?: string ) => void;
    moveBlockToPosition: (
        clientId: string,
        fromRootClientId: string,
        toRootClientId: string,
        index: number
    ) => void;
    removeBlock: ( clientId: string ) => void;
    selectBlock: ( clientId: string ) => void;
    updateBlockAttributes: ( clientId: string, attributes: Record<string, unknown> ) => void;
}

interface PostVariantsPanelProps {
    readonly queryClientId: string;
    readonly previewTotal: number;
}

interface VariantRow {
    readonly clientId: string;
    readonly descriptor: VariantDescriptor;
}

function readMatcher( attrs: Record<string, unknown> ): Matcher {
    const raw = attrs.matcher;
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

export default function PostVariantsPanel( {
    queryClientId,
    previewTotal,
}: PostVariantsPanelProps ): ReactElement {
    // Subscribe via a stable string signature so the panel only
    // re-renders when something it actually cares about changes — NOT
    // every time the parent dispatches an unrelated attribute update.
    // Without this, writing `_compiledVariantMap` back to the
    // post-template (the effect below) would feed back through the
    // store, produce a new `variantRows` reference, recompute the map,
    // and re-run the effect forever (#591 — manual-test infinite loop).
    const subscription = useSelect(
        ( select ) => {
            const store = select( 'core/block-editor' ) as unknown as CoreBlockEditorSelect;
            const queryChildren = store.getBlocks( queryClientId );
            const postTemplate = queryChildren.find(
                ( child ) =>
                    child.name === 'artisanpack/post-template' ||
                    child.name === 'core/post-template'
            );
            if ( ! postTemplate ) {
                return {
                    postTemplateClientId: null as string | null,
                    variantRows: [] as VariantRow[],
                    storedMapSignature: '',
                };
            }
            const variantBlocks = ( postTemplate.innerBlocks ?? [] ).filter(
                ( child ) => child.name === 'artisanpack/post-variant'
            );
            const rows: VariantRow[] = variantBlocks.map( ( block, idx ) => {
                const attrs = block.attributes ?? {};
                const matcher = readMatcher( attrs );
                const priority =
                    typeof attrs.priority === 'number' ? ( attrs.priority as number ) : 10;
                const label =
                    typeof attrs.label === 'string' ? ( attrs.label as string ) : undefined;
                return {
                    clientId: block.clientId,
                    descriptor: {
                        order: idx,
                        matcher,
                        priority,
                        label,
                    },
                };
            } );
            const storedMap =
                ( postTemplate.attributes as { _compiledVariantMap?: unknown } )
                    ._compiledVariantMap;
            return {
                postTemplateClientId: postTemplate.clientId,
                variantRows: rows,
                storedMapSignature: JSON.stringify( storedMap ?? {} ),
            };
        },
        [ queryClientId ]
    );

    const { postTemplateClientId, variantRows, storedMapSignature } = subscription;

    const {
        insertBlock,
        moveBlockToPosition,
        removeBlock,
        selectBlock,
        updateBlockAttributes,
    } = useDispatch( 'core/block-editor' ) as unknown as CoreBlockEditorDispatch;

    // Compile static rules into a position → variantOrder map and
    // write it onto the post-template. Indexed by 0-based loop
    // position to match the server-side inliner's `foreach (
    // $results as $index => $post )`.
    //
    // We compare the compiled signature to what's already stored on
    // the post-template before dispatching — otherwise the dispatched
    // attribute change re-runs `useSelect`, produces a new
    // `variantRows` reference, recompiles the map (identical bytes,
    // new object identity), and the effect fires again forever.
    const { compiledMap, compiledSignature } = useMemo( () => {
        const total = Math.max( previewTotal, 1 );
        const map = compileStaticMap(
            variantRows.map( ( row ) => row.descriptor ),
            total
        );
        return { compiledMap: map, compiledSignature: JSON.stringify( map ) };
    }, [ variantRows, previewTotal ] );

    useEffect( () => {
        if ( postTemplateClientId === null ) {
            return;
        }
        if ( compiledSignature === storedMapSignature ) {
            return;
        }
        updateBlockAttributes( postTemplateClientId, {
            _compiledVariantMap: compiledMap,
        } );
    }, [
        compiledMap,
        compiledSignature,
        storedMapSignature,
        postTemplateClientId,
        updateBlockAttributes,
    ] );

    const handleAdd = ( kind: Matcher[ 'kind' ] ): void => {
        if ( postTemplateClientId === null ) {
            return;
        }
        const block = createBlock( 'artisanpack/post-variant', {
            matcher: defaultMatcherFor( kind ),
            priority: 10,
        } );
        insertBlock( block, variantRows.length, postTemplateClientId );
    };

    const handleMove = ( clientId: string, currentIndex: number, delta: number ): void => {
        if ( postTemplateClientId === null ) {
            return;
        }
        const target = currentIndex + delta;
        if ( target < 0 || target >= variantRows.length ) {
            return;
        }
        moveBlockToPosition(
            clientId,
            postTemplateClientId,
            postTemplateClientId,
            target
        );
    };

    return (
        <PanelBody title={ __( 'Post Variants', TEXT_DOMAIN ) } initialOpen={ false }>
            { variantRows.length === 0 && (
                <p className="ap-post-variants-panel__hint">
                    { __(
                        'Variants let specific posts in the loop render with a different template (e.g. make the first post a hero card while the rest render as a list).',
                        TEXT_DOMAIN
                    ) }
                </p>
            ) }
            <ul className="ap-post-variants-panel__list">
                { variantRows.map( ( row, idx ) => (
                    <li key={ row.clientId } className="ap-post-variants-panel__row">
                        <button
                            type="button"
                            className="ap-post-variants-panel__row-label"
                            onClick={ () => selectBlock( row.clientId ) }
                        >
                            <strong>
                                { row.descriptor.label && row.descriptor.label !== ''
                                    ? row.descriptor.label
                                    : __( 'Untitled variant', TEXT_DOMAIN ) }
                            </strong>
                            <span className="ap-post-variants-panel__row-rule">
                                { ' · ' }
                                { row.descriptor.matcher.kind }:
                                { row.descriptor.matcher.value }
                            </span>
                        </button>
                        <div className="ap-post-variants-panel__row-actions">
                            <Button
                                size="small"
                                variant="tertiary"
                                onClick={ () => handleMove( row.clientId, idx, -1 ) }
                                disabled={ idx === 0 }
                                aria-label={ __( 'Move variant up', TEXT_DOMAIN ) }
                            >
                                ↑
                            </Button>
                            <Button
                                size="small"
                                variant="tertiary"
                                onClick={ () => handleMove( row.clientId, idx, 1 ) }
                                disabled={ idx === variantRows.length - 1 }
                                aria-label={ __( 'Move variant down', TEXT_DOMAIN ) }
                            >
                                ↓
                            </Button>
                            <Button
                                size="small"
                                variant="tertiary"
                                isDestructive
                                onClick={ () => removeBlock( row.clientId ) }
                                aria-label={ __( 'Delete variant', TEXT_DOMAIN ) }
                            >
                                ×
                            </Button>
                        </div>
                    </li>
                ) ) }
            </ul>
            <div className="ap-post-variants-panel__add">
                <Button size="small" variant="secondary" onClick={ () => handleAdd( 'position' ) }>
                    { __( 'Add position variant', TEXT_DOMAIN ) }
                </Button>
                <Button size="small" variant="secondary" onClick={ () => handleAdd( 'pattern' ) }>
                    { __( 'Add pattern variant', TEXT_DOMAIN ) }
                </Button>
                <Button size="small" variant="secondary" onClick={ () => handleAdd( 'meta' ) }>
                    { __( 'Add metadata variant', TEXT_DOMAIN ) }
                </Button>
                <Button size="small" variant="secondary" onClick={ () => handleAdd( 'custom' ) }>
                    { __( 'Add custom variant', TEXT_DOMAIN ) }
                </Button>
            </div>
        </PanelBody>
    );
}

function defaultMatcherFor( kind: Matcher[ 'kind' ] ): Matcher {
    switch ( kind ) {
        case 'position':
            return { kind: 'position', value: 'first' };
        case 'pattern':
            return { kind: 'pattern', value: 'odd' };
        case 'meta':
            return { kind: 'meta', value: 'sticky' };
        case 'custom':
            return { kind: 'custom', value: 'callback:' };
    }
}
