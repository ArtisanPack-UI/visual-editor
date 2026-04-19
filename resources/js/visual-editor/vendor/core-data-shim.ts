/**
 * Minimal empty-state shim for `@wordpress/core-data`.
 *
 * Gutenberg's block-editor and block-library import from `@wordpress/core-data`
 * whether we want them to or not. Without a shim, importing any of those
 * modules crashes because there is no WordPress backend to feed the real
 * package's REST resolvers. This module is aliased in `vite.config.ts` so that
 * every `import … from '@wordpress/core-data'` in the editor bundle resolves
 * here instead of the upstream package.
 *
 * The shim intentionally returns empty/no-op values for every surface it
 * implements. Blocks that depend on WordPress-specific data (navigation,
 * query, post-*) will gracefully render empty; those blocks are slated to
 * land in the `disabled_blocks` list during M5.
 *
 * **This shim is temporary.** The `artisanpack-ui/cms-framework` package will
 * replace it with a real Laravel-backed `core` store. Every selector we
 * implement here is a selector we have to re-verify against Gutenberg
 * upgrades — keep the surface as small as observed crashes allow.
 *
 * Tracked by issue #312 (M2 of the Gutenberg adoption, umbrella #309).
 */

import {
    createContext,
    createElement,
    useContext,
    useMemo,
    type PropsWithChildren,
    type ReactElement,
} from 'react';
import { createReduxStore, register } from '@wordpress/data';

type EntityRecord = Record<string, unknown> | null;
type EntityKey = number | string;

interface EntityIdentity {
    readonly kind: string;
    readonly name: string;
    readonly id: EntityKey | undefined;
}

const EMPTY_RECORDS: readonly never[] = Object.freeze([]);
const STORE_NAME = 'core';

const selectors = {
    getEntityRecord: (): EntityRecord => null,
    getEntityRecords: (): readonly never[] => EMPTY_RECORDS,
    getEditedEntityRecord: (): EntityRecord => null,
    getRawEntityRecord: (): EntityRecord => null,
    getCurrentUser: (): EntityRecord => null,
    getUsers: (): readonly never[] => EMPTY_RECORDS,
    getMedia: (): EntityRecord => null,
    getMediaItems: (): readonly never[] => EMPTY_RECORDS,
    getEntityRecordsTotalItems: (): number => 0,
    getEntityRecordsTotalPages: (): number => 0,
    hasFinishedResolution: (): boolean => true,
    hasStartedResolution: (): boolean => true,
    isResolving: (): boolean => false,
    hasEditsForEntityRecord: (): boolean => false,
    getEntityRecordEdits: (): EntityRecord => null,
    getEntityRecordNonTransientEdits: (): EntityRecord => null,
    canUser: (): boolean => false,
    canUserEditEntityRecord: (): boolean => false,
    getAutosaves: (): readonly never[] => EMPTY_RECORDS,
    getAutosave: (): EntityRecord => null,
    getReferenceByDistinctEdits: (): number[] => [],
    isSavingEntityRecord: (): boolean => false,
    isDeletingEntityRecord: (): boolean => false,
    getLastEntitySaveError: (): null => null,
    getLastEntityDeleteError: (): null => null,
};

const actions = {
    receiveEntityRecords:
        () =>
        ({ dispatch }: { dispatch: () => void }) => {
            dispatch();
        },
    receiveUserQuery: () => ({ type: 'SHIM_NOOP' }) as const,
    receiveCurrentUser: () => ({ type: 'SHIM_NOOP' }) as const,
    saveEntityRecord: () => async (): Promise<null> => null,
    saveEditedEntityRecord: () => async (): Promise<null> => null,
    deleteEntityRecord: () => async (): Promise<boolean> => true,
    editEntityRecord: () => ({ type: 'SHIM_NOOP' }) as const,
    undo: () => ({ type: 'SHIM_NOOP' }) as const,
    redo: () => ({ type: 'SHIM_NOOP' }) as const,
    __unstableCreateUndoLevel: () => ({ type: 'SHIM_NOOP' }) as const,
};

const reducer = (state: Record<string, never> = {}): Record<string, never> =>
    state;

export const store = createReduxStore(STORE_NAME, {
    reducer,
    actions,
    selectors,
});

register(store);

/**
 * `EntityProvider` in upstream `@wordpress/core-data` supplies the ambient
 * entity identity (kind/name/id) to hooks like `useEntityProp`. The shim
 * preserves that contract so blocks reading the context don't throw, but the
 * default value is an empty identity since there is no backing store yet.
 */
const EntityContext = createContext<EntityIdentity>({
    kind: '',
    name: '',
    id: undefined,
});

export function EntityProvider(
    props: PropsWithChildren<EntityIdentity>
): ReactElement {
    const { kind, name, id, children } = props;
    const value = useMemo<EntityIdentity>(
        () => ({ kind, name, id }),
        [kind, name, id]
    );

    return createElement(EntityContext.Provider, { value }, children);
}

export function useEntityId(): EntityKey | undefined {
    return useContext(EntityContext).id;
}

/**
 * No-op setter returned by write-capable hooks. Kept as a module-level
 * singleton so React memoization downstream doesn't see a fresh function
 * identity on every render.
 */
const noopSetter = (): void => {};

export function useEntityProp<T = unknown>(): [T | undefined, typeof noopSetter, T | undefined] {
    return [undefined, noopSetter, undefined];
}

export function useEntityRecord<T = unknown>(): {
    record: T | null;
    editedRecord: T | null;
    hasEdits: boolean;
    hasResolved: boolean;
    isResolving: boolean;
    edit: typeof noopSetter;
    save: () => Promise<null>;
} {
    return {
        record: null,
        editedRecord: null,
        hasEdits: false,
        hasResolved: true,
        isResolving: false,
        edit: noopSetter,
        save: async () => null,
    };
}

export function useEntityRecords<T = unknown>(): {
    records: readonly T[];
    hasResolved: boolean;
    isResolving: boolean;
    status: 'IDLE';
    totalItems: number;
    totalPages: number;
} {
    return {
        records: EMPTY_RECORDS as readonly T[],
        hasResolved: true,
        isResolving: false,
        status: 'IDLE',
        totalItems: 0,
        totalPages: 0,
    };
}

export function useEntityBlockEditor(): [
    readonly never[],
    typeof noopSetter,
    typeof noopSetter,
] {
    return [EMPTY_RECORDS, noopSetter, noopSetter];
}

export function useResourcePermissions(): {
    canCreate: boolean;
    canUpdate: boolean;
    canDelete: boolean;
    isResolving: boolean;
} {
    return {
        canCreate: false,
        canUpdate: false,
        canDelete: false,
        isResolving: false,
    };
}
