import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount as vueMount } from '@vue/test-utils';

import {
    VE_EDITOR_AUTOSAVE,
    VE_EDITOR_CHANGE,
    VE_EDITOR_SAVE,
    type VeEditorAutosaveDetail,
    type VeEditorChangeDetail,
    type VeEditorSaveDetail,
} from '../editor-events';

// The Gutenberg-using editor bundle pulls in `@wordpress/block-editor` etc.,
// which is heavy and not under test here. Mock `editor-app` so `mountEditor`
// resolves its dynamic import to a trivial React component and the wrapper's
// lifecycle becomes the only thing under test.
vi.mock('../editor-app', () => ({
    EditorApp: (): null => null,
}));

import { VisualEditor } from '../vue';

function dispatchChange(detail: VeEditorChangeDetail): void {
    window.dispatchEvent(new CustomEvent(VE_EDITOR_CHANGE, { detail }));
}

function dispatchAutosave(detail: VeEditorAutosaveDetail): void {
    window.dispatchEvent(new CustomEvent(VE_EDITOR_AUTOSAVE, { detail }));
}

function dispatchSave(detail: VeEditorSaveDetail): void {
    window.dispatchEvent(new CustomEvent(VE_EDITOR_SAVE, { detail }));
}

const BASE_PROPS = {
    model: { id: 42, title: 'Hello', slug: 'hello', status: 'draft' as const },
    apiBase: '/visual-editor/api',
    resource: 'posts',
};

describe('<VisualEditor /> Vue wrapper', () => {
    let addSpy: ReturnType<typeof vi.spyOn>;
    let removeSpy: ReturnType<typeof vi.spyOn>;

    beforeEach(() => {
        addSpy = vi.spyOn(window, 'addEventListener');
        removeSpy = vi.spyOn(window, 'removeEventListener');
    });

    afterEach(() => {
        addSpy.mockRestore();
        removeSpy.mockRestore();
    });

    it('mounts a host element into the DOM', async () => {
        const wrapper = vueMount(VisualEditor, { props: BASE_PROPS });

        await flushPromises();

        expect(wrapper.element).toBeInstanceOf(HTMLDivElement);
        expect(wrapper.element.classList.contains('ap-visual-editor')).toBe(true);

        wrapper.unmount();
    });

    it('registers ve:editor:* listeners on mount and removes them on unmount', async () => {
        const wrapper = vueMount(VisualEditor, { props: BASE_PROPS });

        await flushPromises();

        const registered = addSpy.mock.calls
            .map(([name]) => name)
            .filter((name): name is string => typeof name === 'string');

        expect(registered).toContain(VE_EDITOR_CHANGE);
        expect(registered).toContain(VE_EDITOR_AUTOSAVE);
        expect(registered).toContain(VE_EDITOR_SAVE);

        wrapper.unmount();

        const removed = removeSpy.mock.calls
            .map(([name]) => name)
            .filter((name): name is string => typeof name === 'string');

        expect(removed).toContain(VE_EDITOR_CHANGE);
        expect(removed).toContain(VE_EDITOR_AUTOSAVE);
        expect(removed).toContain(VE_EDITOR_SAVE);
    });

    it('re-emits ve:editor:change as @changed with the detail payload', async () => {
        const wrapper = vueMount(VisualEditor, { props: BASE_PROPS });

        await flushPromises();

        const detail: VeEditorChangeDetail = {
            resource: 'posts',
            id: '42',
            blocks: [],
        };

        dispatchChange(detail);

        const emitted = wrapper.emitted('changed');
        expect(emitted).toBeDefined();
        expect(emitted?.[0]).toEqual([detail]);

        wrapper.unmount();
    });

    it('re-emits ve:editor:autosave as @autosaved with the detail payload', async () => {
        const wrapper = vueMount(VisualEditor, { props: BASE_PROPS });

        await flushPromises();

        const detail: VeEditorAutosaveDetail = {
            resource: 'posts',
            id: '42',
            blocks: [],
            updatedAt: '2026-04-20T12:00:00Z',
        };

        dispatchAutosave(detail);

        expect(wrapper.emitted('autosaved')?.[0]).toEqual([detail]);

        wrapper.unmount();
    });

    it('re-emits ve:editor:save as @saved with the detail payload', async () => {
        const wrapper = vueMount(VisualEditor, { props: BASE_PROPS });

        await flushPromises();

        const detail: VeEditorSaveDetail = {
            resource: 'posts',
            id: '42',
            blocks: [],
            updatedAt: '2026-04-20T13:00:00Z',
        };

        dispatchSave(detail);

        expect(wrapper.emitted('saved')?.[0]).toEqual([detail]);

        wrapper.unmount();
    });

    it('ignores events targeting a different resource or id', async () => {
        const wrapper = vueMount(VisualEditor, { props: BASE_PROPS });

        await flushPromises();

        // Different resource — should be ignored.
        dispatchSave({
            resource: 'pages',
            id: '42',
            blocks: [],
            updatedAt: '2026-04-20T13:00:00Z',
        });

        // Different id — should be ignored.
        dispatchSave({
            resource: 'posts',
            id: '99',
            blocks: [],
            updatedAt: '2026-04-20T13:00:00Z',
        });

        expect(wrapper.emitted('saved')).toBeUndefined();

        // Matching target — should be emitted.
        const detail: VeEditorSaveDetail = {
            resource: 'posts',
            id: '42',
            blocks: [],
            updatedAt: '2026-04-20T13:00:00Z',
        };

        dispatchSave(detail);

        expect(wrapper.emitted('saved')?.[0]).toEqual([detail]);

        wrapper.unmount();
    });

    it('stops emitting after the component unmounts', async () => {
        const wrapper = vueMount(VisualEditor, { props: BASE_PROPS });

        await flushPromises();
        wrapper.unmount();

        dispatchSave({
            resource: 'posts',
            id: '42',
            blocks: [],
            updatedAt: '2026-04-20T14:00:00Z',
        });

        expect(wrapper.emitted('saved')).toBeUndefined();
    });

    it('accepts string ids on the model and matches string event targets', async () => {
        const wrapper = vueMount(VisualEditor, {
            props: { ...BASE_PROPS, model: { id: 'abc-123' } },
        });

        await flushPromises();

        const detail: VeEditorSaveDetail = {
            resource: 'posts',
            id: 'abc-123',
            blocks: [],
            updatedAt: null,
        };

        dispatchSave(detail);

        expect(wrapper.emitted('saved')?.[0]).toEqual([detail]);

        wrapper.unmount();
    });
});
