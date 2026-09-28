/**
 * Publishes the open site-editor entity to the `core/editor` store.
 *
 * Upstream block-library code (notably `core/navigation`'s
 * `isWithinNavigationOverlay()`) asks `core/editor` which entity is being
 * edited, then reads that record's `area` from the `core` store. Both reads
 * happen once, when the block mounts, without subscribing — so the record
 * has to be in the core-data shim and the editor context has to point at
 * it *before* the entity's blocks reach the canvas (#809).
 *
 * The entity editor loads records through `api-client.ts`, not the shim,
 * so this module primes the shim with the loaded record as well.
 */

import { dispatch } from '@wordpress/data';

import {
    clearEditorCurrentPost,
    setEditorCurrentPost,
} from '../vendor/editor-context-store';

import { type EntityKind, type EntityRecord } from './api-client';

const POST_TYPE_BY_KIND: Record<EntityKind, string> = {
    template: 'wp_template',
    'template-part': 'wp_template_part',
};

interface CoreShimDispatch {
    receiveEntityRecords?: (
        kind: string,
        name: string,
        records: readonly unknown[],
        query?: Record<string, unknown> | null,
        totalItems?: number,
        totalPages?: number,
        invalidateQueries?: boolean,
    ) => void;
}

/**
 * Prime the core-data shim with `record` and point `core/editor` at it.
 */
export function syncEditorContext<K extends EntityKind>(
    kind: K,
    record: EntityRecord<K>,
): void {
    const postType = POST_TYPE_BY_KIND[kind];

    // Single-record receive: pass `invalidateQueries: false` so cached
    // list queries (e.g. the nav block's `{per_page: -1}` template-part
    // list) survive — see the Keystone #57 note in the shim's reducer.
    (dispatch('core') as CoreShimDispatch | undefined)?.receiveEntityRecords?.(
        'postType',
        postType,
        [record],
        undefined,
        undefined,
        undefined,
        false,
    );

    setEditorCurrentPost(postType, record.id);
}

/**
 * Clear the `core/editor` context once no entity is open.
 */
export function clearEditorContext(): void {
    clearEditorCurrentPost();
}
