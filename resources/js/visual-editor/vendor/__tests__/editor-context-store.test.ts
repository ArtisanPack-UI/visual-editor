import { afterEach, describe, expect, it } from 'vitest';
import { dispatch, select } from '@wordpress/data';

// Registers the `core` shim store the overlay check reads from.
import '../core-data-shim';
import {
    EDITOR_CONTEXT_STORE_NAME,
    clearEditorCurrentPost,
    getEditorCurrentPost,
    setEditorCurrentPost,
} from '../editor-context-store';

type EditorSelect = Record<string, (...args: unknown[]) => unknown>;
type CoreDispatch = Record<string, (...args: unknown[]) => unknown>;

/**
 * Mirror of upstream `isWithinNavigationOverlay()`
 * (`@wordpress/block-library/build-module/utils/is-within-overlay.mjs`).
 * The upstream module can't be imported here — vitest hands
 * `node_modules` to Node's native loader, which can't resolve the
 * core-data shim's extensionless re-export — so this replays the exact
 * reads it makes against the global registry.
 */
function isWithinNavigationOverlay(): boolean {
    const editorStore = select('core/editor') as EditorSelect | undefined;

    if (!editorStore) {
        return false;
    }

    const postType = editorStore.getCurrentPostType?.();
    const postId = editorStore.getCurrentPostId?.();

    if (postType === 'wp_template_part' && postId) {
        const templatePart = (
            select('core') as EditorSelect
        ).getEditedEntityRecord('postType', 'wp_template_part', postId) as
            | { area?: string }
            | null;

        return templatePart?.area === 'navigation-overlay';
    }

    return false;
}

const editorSelect = (): EditorSelect =>
    select(EDITOR_CONTEXT_STORE_NAME) as EditorSelect;

function receiveTemplatePart(record: Record<string, unknown>): void {
    (dispatch('core') as CoreDispatch).receiveEntityRecords(
        'postType',
        'wp_template_part',
        [record],
    );
}

afterEach(() => {
    clearEditorCurrentPost();
});

describe('editor-context-store', () => {
    it('registers as core/editor with an empty initial context', () => {
        expect(editorSelect()).toBeDefined();
        expect(getEditorCurrentPost()).toEqual({ postType: null, postId: null });
    });

    it('tracks and clears the current post', () => {
        setEditorCurrentPost('wp_template_part', 42);

        expect(editorSelect().getCurrentPostType()).toBe('wp_template_part');
        expect(editorSelect().getCurrentPostId()).toBe(42);

        clearEditorCurrentPost();

        expect(getEditorCurrentPost()).toEqual({ postType: null, postId: null });
    });

    it('answers the selectors upstream blocks destructure without throwing', () => {
        setEditorCurrentPost('wp_template', 'artisanpack-base//single');

        expect(editorSelect().getCurrentTemplateId()).toBeNull();
        expect(editorSelect().getPermalink()).toBeNull();
        expect(editorSelect().getEditedPostAttribute('type')).toBe('wp_template');
        expect(editorSelect().getEditedPostAttribute('id')).toBe(
            'artisanpack-base//single',
        );
        expect(editorSelect().getEditedPostAttribute('date')).toBeUndefined();
    });

    it('does not expose save-lifecycle selectors', () => {
        // `StateInspectorSync` treats a missing `isSavingPost` as "not
        // saving" and relies on `flushBeforeSave()` instead.
        expect(editorSelect().isSavingPost).toBeUndefined();
        expect(editorSelect().isAutosavingPost).toBeUndefined();
    });
});

describe('isWithinNavigationOverlay contract against the store', () => {
    it('is false when no entity is open', () => {
        expect(isWithinNavigationOverlay()).toBe(false);
    });

    it('is true while editing a navigation-overlay template part', () => {
        receiveTemplatePart({
            id: 9101,
            slug: 'navigation-overlay',
            theme: 'artisanpack-base',
            area: 'navigation-overlay',
        });

        setEditorCurrentPost('wp_template_part', 9101);

        expect(isWithinNavigationOverlay()).toBe(true);
    });

    it('is false while editing a template part in another area', () => {
        receiveTemplatePart({
            id: 9102,
            slug: 'header',
            theme: 'artisanpack-base',
            area: 'header',
        });

        setEditorCurrentPost('wp_template_part', 9102);

        expect(isWithinNavigationOverlay()).toBe(false);
    });

    it('is false while editing a template', () => {
        setEditorCurrentPost('wp_template', 9103);

        expect(isWithinNavigationOverlay()).toBe(false);
    });
});
