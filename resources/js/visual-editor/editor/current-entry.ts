/**
 * Current-entry entity support for content resources outside the
 * built-in `posts` / `pages` mapping (#992).
 *
 * cms-framework Posts and Pages resolve through real G3 entity
 * endpoints, so the `core/post-*` blocks read and write them through
 * the core-data shim. Custom content types (a host `packages` resource,
 * for example) have no entity endpoint, which left every post-* block on
 * its placeholder chip. Instead of inventing a REST surface, the editor
 * registers a client-only `postType` entity for the entry being edited
 * and seeds it from the mount's dataset props (`data-title`,
 * `data-excerpt`). The seeded record never fetches: its resolution is
 * marked finished up front, so the shim's resolver can't replace it
 * with a 404 "missing" placeholder.
 *
 * {@see useEntryFieldMirror} keeps the document title / excerpt fields
 * in sync with edits the canvas blocks stage on the entity record, for
 * both the seeded custom entry and the server-backed post/page entity.
 *
 * Lives in its own module so its tests can import it without pulling
 * the `@wordpress/*` editor bundle through `editor-app.tsx`.
 */

import { useCallback, useEffect, useRef } from 'react';
import { dispatch, select, useSelect } from '@wordpress/data';

import { DEFAULT_ENTITIES } from '../vendor/core-data-shim';
import { getContentTypes } from './content-type-registry';

export interface CurrentEntryIdentity {
    readonly kind: 'postType';
    readonly name: string;
    readonly id: number;
}

export interface CurrentEntrySeed {
    readonly title: string;
    readonly excerpt: string;
}

/** Entry fields the canvas blocks edit inline and the sidebar mirrors. */
export type MirroredEntryField = 'title' | 'excerpt';

const MIRRORED_FIELDS: readonly MirroredEntryField[] = ['title', 'excerpt'];

const SAFE_POST_TYPE_PATTERN = /^[a-z0-9_-]{1,64}$/;

// Built-in `postType` entities (posts, pages, attachments, templates,
// menus, patterns) the shim already wires to real endpoints. A custom
// resource that derives one of these names must not seed into it — it
// would share a record cache with unrelated rows of that entity.
const RESERVED_POST_TYPES: ReadonlySet<string> = new Set(
    DEFAULT_ENTITIES.filter((config) => config.kind === 'postType').map(
        (config) => config.name
    )
);

type CoreStore = Record<string, ((...args: unknown[]) => unknown) | undefined>;

/**
 * Resolves the `postType` name for a custom content resource — the
 * value the canvas stamps into the `postType` block context.
 *
 * Preference order: the host's `data-pattern-post-type` override, the
 * singular slug of the matching `data-content-types` entry, then the
 * resource slug itself. Returns null for the built-in `posts` / `pages`
 * slugs (they use the server-backed entity), for names that collide
 * with a built-in entity, and for anything outside the safe slug
 * character set.
 *
 * @since 1.15.3
 */
export function customEntryPostTypeForResource(
    resource: string,
    override?: string | null
): string | null {
    if (resource === 'posts' || resource === 'pages' || resource.trim() === '') {
        return null;
    }

    const normalizedOverride = override?.trim().toLowerCase() ?? '';
    let postType = normalizedOverride;

    if (postType === '') {
        const match = getContentTypes().find(
            (type) => type.plural === resource || type.slug === resource
        );

        postType = match !== undefined ? match.slug : resource.trim().toLowerCase();
    }

    if (!SAFE_POST_TYPE_PATTERN.test(postType) || RESERVED_POST_TYPES.has(postType)) {
        return null;
    }

    return postType;
}

/**
 * Registers the client-only entity for a custom content entry and
 * seeds its record from the mount's dataset props. Idempotent: an
 * already-seeded record (which may carry staged edits) is left alone.
 *
 * Runs synchronously so the record is in the store before the first
 * block render reads it — a resolver that fired first would fetch the
 * (non-existent) endpoint and cache a "missing" placeholder instead.
 *
 * @since 1.15.3
 */
export function seedCurrentEntryRecord(
    identity: CurrentEntryIdentity,
    seed: CurrentEntrySeed
): void {
    const coreSelect = select('core') as CoreStore | undefined;
    const coreDispatch = dispatch('core') as CoreStore | undefined;

    if (coreSelect === undefined || coreDispatch === undefined) {
        return;
    }

    const { kind, name, id } = identity;

    if (coreSelect.getEntityConfig?.(kind, name) == null) {
        coreDispatch.addEntities?.([
            {
                kind,
                name,
                // Never fetched — resolution is marked finished below —
                // but the shim's URL builder still needs a base.
                baseURL: `/${name}`,
                key: 'id',
                label: name,
            },
        ]);
    }

    // Mark the record resolved before seeding so neither
    // `getEntityRecord` nor `getEditedEntityRecord` ever hits the
    // network for it.
    for (const selectorName of ['getEntityRecord', 'getEditedEntityRecord']) {
        coreDispatch.startResolution?.(selectorName, [kind, name, id]);
        coreDispatch.finishResolution?.(selectorName, [kind, name, id]);
    }

    if (coreSelect.getEntityRecord?.(kind, name, id) != null) {
        return;
    }

    coreDispatch.receiveEntityRecords?.(
        kind,
        name,
        [
            {
                id,
                type: name,
                title: { raw: seed.title, rendered: seed.title },
                excerpt: { raw: seed.excerpt, rendered: seed.excerpt },
                content: { raw: '', rendered: '' },
            },
        ],
        undefined,
        undefined,
        undefined,
        false
    );
}

/**
 * Mirrors `title` / `excerpt` edits staged on the current entry's
 * entity record (by the Post Title / Post Excerpt blocks, through
 * `useEntityProp`) back into the editor's local document fields.
 *
 * `onChange` receives only the staged values that differ from the
 * fields' current values, batched so a title and excerpt staged
 * together arrive in one call. The document fields stage their own
 * edits onto the record too (the other sync direction); call the
 * returned `noteFieldEdit` with that value first so the mirror skips
 * its echo even when the store update commits before the field's state
 * update does.
 *
 * @since 1.15.3
 *
 * @return Callback the document fields invoke before staging an edit.
 */
export function useEntryFieldMirror(
    identity: CurrentEntryIdentity | null,
    values: Readonly<Record<MirroredEntryField, string>>,
    onChange: (changes: Partial<Record<MirroredEntryField, string>>) => void
): (field: MirroredEntryField, value: string) => void {
    const valuesRef = useRef(values);
    valuesRef.current = values;
    const onChangeRef = useRef(onChange);
    onChangeRef.current = onChange;
    const fieldEditsRef = useRef<Partial<Record<MirroredEntryField, string>>>({});

    const noteFieldEdit = useCallback(
        (field: MirroredEntryField, value: string): void => {
            fieldEditsRef.current[field] = value;
        },
        []
    );

    const edits = useSelect(
        (selectStore) => {
            if (identity === null) {
                return null;
            }

            const store = selectStore('core') as CoreStore | undefined;

            return (
                (store?.getEntityRecordEdits?.(
                    identity.kind,
                    identity.name,
                    identity.id
                ) as Record<string, unknown> | null | undefined) ?? null
            );
        },
        [identity]
    );

    useEffect(() => {
        if (edits === null) {
            return;
        }

        const changes: Partial<Record<MirroredEntryField, string>> = {};

        for (const field of MIRRORED_FIELDS) {
            const staged = edits[field];

            if (typeof staged !== 'string') {
                continue;
            }

            // A noted field edit is consumed by the first staged value
            // that reaches the mirror — its own echo is skipped, any
            // other value is a block edit made since.
            const fieldEdit = fieldEditsRef.current[field];
            delete fieldEditsRef.current[field];

            if (staged === fieldEdit || staged === valuesRef.current[field]) {
                continue;
            }

            changes[field] = staged;
        }

        if (Object.keys(changes).length > 0) {
            onChangeRef.current(changes);
        }
    }, [edits]);

    return noteFieldEdit;
}
