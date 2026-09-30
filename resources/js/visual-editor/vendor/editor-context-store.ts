/**
 * Minimal `core/editor` store.
 *
 * The package does not ship `@wordpress/editor`, so `select('core/editor')`
 * returns `undefined` everywhere. Upstream `@wordpress/block-library` uses
 * that store to learn which entity the canvas is editing — most notably
 * `isWithinNavigationOverlay()`, which `core/navigation` calls to:
 *
 * - hide its Overlay panel when the block sits inside a
 *   `navigation-overlay` template part (an overlay can't pick an overlay);
 * - default a freshly-inserted nav inside an overlay to a non-responsive,
 *   always-open-submenus layout;
 * - restrict `core/navigation-overlay-close` to overlay template parts.
 *
 * Without the store, every one of those checks reads `false`, so the
 * overlay editor shows the nav block's own Overlay panel recursively
 * (#809).
 *
 * ## Scope
 *
 * Only the "current post" identity is tracked — the site editor sets it
 * when a template or template part loads and clears it on close. The
 * remaining selectors exist so upstream blocks that destructure them off
 * `select('core/editor')` (query-title, term-name, calendar, table of
 * contents) read empty values instead of throwing now that the store is
 * present. Keep the surface as narrow as that — this is not a port of
 * `@wordpress/editor`.
 *
 * `isSavingPost` / `isAutosavingPost` are deliberately absent: hosts
 * without a real editor store rely on `flushBeforeSave()` in
 * `states/StateInspectorSync.tsx`, and that sync treats missing selectors
 * as "not saving".
 *
 * Registered on the default `@wordpress/data` registry because
 * `isWithinNavigationOverlay()` reads through the global `select`, not a
 * scoped registry.
 */

import { createReduxStore, dispatch, register, select } from '@wordpress/data';

export const EDITOR_CONTEXT_STORE_NAME = 'core/editor';

export interface EditorContextState {
    postType: string | null;
    postId: number | string | null;
}

type EditorContextAction =
    | {
          type: 'SET_CURRENT_POST';
          postType: string;
          postId: number | string;
      }
    | { type: 'CLEAR_CURRENT_POST' };

const INITIAL_STATE: EditorContextState = Object.freeze({
    postType: null,
    postId: null,
}) as EditorContextState;

function reducer(
    state: EditorContextState = INITIAL_STATE,
    action: EditorContextAction,
): EditorContextState {
    switch (action.type) {
        case 'SET_CURRENT_POST':
            if (state.postType === action.postType && state.postId === action.postId) {
                return state;
            }

            return { postType: action.postType, postId: action.postId };

        case 'CLEAR_CURRENT_POST':
            return state.postType === null && state.postId === null
                ? state
                : INITIAL_STATE;

        default:
            return state;
    }
}

const actions = {
    setCurrentPost: (
        postType: string,
        postId: number | string,
    ): EditorContextAction => ({
        type: 'SET_CURRENT_POST',
        postType,
        postId,
    }),

    clearCurrentPost: (): EditorContextAction => ({
        type: 'CLEAR_CURRENT_POST',
    }),
};

const selectors = {
    getCurrentPostType: (state: EditorContextState): string | null =>
        state.postType,

    getCurrentPostId: (state: EditorContextState): number | string | null =>
        state.postId,

    // Upstream returns the template assigned to the current post. Site-
    // editor entities *are* the template, so there is never a separate
    // one to report; callers fall back to `getCurrentPostId()` when the
    // current post type is `wp_template`.
    getCurrentTemplateId: (): null => null,

    getEditedPostAttribute: (
        state: EditorContextState,
        attributeName: string,
    ): unknown => {
        if (attributeName === 'type') {
            return state.postType ?? undefined;
        }

        if (attributeName === 'id') {
            return state.postId ?? undefined;
        }

        return undefined;
    },

    getPermalink: (): null => null,
};

export const editorContextStore = createReduxStore(EDITOR_CONTEXT_STORE_NAME, {
    reducer: reducer as unknown as (
        state: EditorContextState,
        action: { type: string },
    ) => EditorContextState,
    actions,
    selectors,
});

// Same duplicate-registration guard as `core-data-shim.ts`: Vite's dev
// server can evaluate this module twice, and `@wordpress/data` throws on
// the second `register()`. A host that ships the real `@wordpress/editor`
// also lands here — its store wins and our helpers below no-op.
try {
    register(editorContextStore);
} catch (error) {
    if (
        !(error instanceof Error) ||
        !error.message.includes('already registered')
    ) {
        throw error;
    }
}

interface EditorContextDispatch {
    setCurrentPost?: (postType: string, postId: number | string) => void;
    clearCurrentPost?: () => void;
}

/**
 * Point the `core/editor` store at the entity the canvas is editing.
 *
 * Call before the entity's blocks reach the canvas — upstream reads the
 * context once, on block mount, without subscribing to it.
 */
export function setEditorCurrentPost(
    postType: string,
    postId: number | string,
): void {
    (dispatch(EDITOR_CONTEXT_STORE_NAME) as EditorContextDispatch | undefined)
        ?.setCurrentPost?.(postType, postId);
}

/**
 * Reset the `core/editor` store once no entity is open.
 */
export function clearEditorCurrentPost(): void {
    (dispatch(EDITOR_CONTEXT_STORE_NAME) as EditorContextDispatch | undefined)
        ?.clearCurrentPost?.();
}

/**
 * Read the current post identity. Exposed for tests and diagnostics.
 */
export function getEditorCurrentPost(): EditorContextState {
    const store = select(EDITOR_CONTEXT_STORE_NAME) as
        | {
              getCurrentPostType?: () => string | null;
              getCurrentPostId?: () => number | string | null;
          }
        | undefined;

    return {
        postType: store?.getCurrentPostType?.() ?? null,
        postId: store?.getCurrentPostId?.() ?? null,
    };
}
