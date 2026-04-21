/**
 * Vue wrapper for the React visual editor.
 *
 * Mounts the `@artisanpack-ui/visual-editor` React editor inside a Vue
 * component lifecycle so Inertia+Vue admin apps can embed the editor
 * directly without touching `[data-ap-visual-editor]` data attributes.
 *
 * The wrapper subscribes to the `ve:editor:*` CustomEvents the editor
 * dispatches on `window`, filters them by `resource + id` so multiple
 * editors on the same page stay isolated, and re-emits them as Vue
 * events (`@changed`, `@autosaved`, `@saved`).
 *
 * HMR and StrictMode double-mounts are handled by the shared
 * {@link mountEditor} helper, which guards the host element against
 * double roots.
 */

import {
    defineComponent,
    h,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import type { PropType } from 'vue';

import type {
    AuthorOption,
    DocumentSupports,
    FeaturedImageValue,
} from './document-panels';
import {
    VE_EDITOR_AUTOSAVE,
    VE_EDITOR_CHANGE,
    VE_EDITOR_SAVE,
    type VeEditorAutosaveDetail,
    type VeEditorChangeDetail,
    type VeEditorSaveDetail,
} from './editor-events';
import {
    mountEditor,
    type MountConfig,
    type MountedEditor,
} from './main';

type PostStatus = 'draft' | 'pending' | 'scheduled' | 'published' | 'private';

export interface VisualEditorModel {
    id: number | string;
    title?: string;
    slug?: string;
    status?: PostStatus | string;
    excerpt?: string;
    featuredImage?: FeaturedImageValue | null;
    authorId?: number | string | null;
    commentsOpen?: boolean;
}

export interface VisualEditorProps {
    model: VisualEditorModel;
    apiBase: string;
    resource: string;
    previewUrl?: string | null;
    authorOptions?: ReadonlyArray<AuthorOption>;
    supports?: DocumentSupports;
}

function buildMountConfig(props: VisualEditorProps): MountConfig {
    const { model, authorOptions, supports } = props;

    return {
        apiBase: props.apiBase,
        resource: props.resource,
        id: String(model.id),
        ...(model.title !== undefined ? { initialTitle: model.title } : {}),
        ...(model.slug !== undefined ? { initialSlug: model.slug } : {}),
        ...(model.status !== undefined ? { initialStatus: String(model.status) } : {}),
        ...(model.excerpt !== undefined ? { initialExcerpt: model.excerpt } : {}),
        ...(model.featuredImage !== undefined
            ? { initialFeaturedImage: model.featuredImage }
            : {}),
        ...(model.authorId !== undefined ? { initialAuthorId: model.authorId } : {}),
        ...(model.commentsOpen !== undefined
            ? { initialCommentsOpen: model.commentsOpen }
            : {}),
        ...(authorOptions !== undefined ? { authorOptions } : {}),
        ...(supports !== undefined ? { supports } : {}),
        previewUrl: props.previewUrl ?? null,
    };
}

export const VisualEditor = defineComponent({
    name: 'VisualEditor',
    props: {
        model: {
            type: Object as PropType<VisualEditorModel>,
            required: true,
        },
        apiBase: {
            type: String,
            required: true,
        },
        resource: {
            type: String,
            required: true,
        },
        previewUrl: {
            type: String as PropType<string | null>,
            default: null,
        },
        authorOptions: {
            type: Array as PropType<ReadonlyArray<AuthorOption>>,
            default: undefined,
        },
        supports: {
            type: Object as PropType<DocumentSupports>,
            default: undefined,
        },
    },
    emits: {
        changed: (_detail: VeEditorChangeDetail): boolean => true,
        autosaved: (_detail: VeEditorAutosaveDetail): boolean => true,
        saved: (_detail: VeEditorSaveDetail): boolean => true,
    },
    setup(props, { emit }) {
        const rootEl = ref<HTMLDivElement | null>(null);
        let mounted: MountedEditor | null = null;

        const expectedResource = (): string => props.resource;
        const expectedId = (): string => String(props.model.id);

        const isSelf = (target: { resource: string; id: string }): boolean =>
            target.resource === expectedResource() && target.id === expectedId();

        const onChange = (event: Event): void => {
            const detail = (event as CustomEvent<VeEditorChangeDetail>).detail;

            if (detail !== undefined && isSelf(detail)) {
                emit('changed', detail);
            }
        };

        const onAutosave = (event: Event): void => {
            const detail = (event as CustomEvent<VeEditorAutosaveDetail>).detail;

            if (detail !== undefined && isSelf(detail)) {
                emit('autosaved', detail);
            }
        };

        const onSave = (event: Event): void => {
            const detail = (event as CustomEvent<VeEditorSaveDetail>).detail;

            if (detail !== undefined && isSelf(detail)) {
                emit('saved', detail);
            }
        };

        // `mountEditor().ready` rejects when the dynamic editor-app import
        // fails. The failure is already logged inside `mountEditor`; attach
        // a no-op catch here so the rejection doesn't bubble up as an
        // unhandled promise warning in the host app's console.
        const swallowReadyRejection = (mount: MountedEditor): MountedEditor => {
            mount.ready.catch(() => {
                // Already logged by mountEditor.
            });
            return mount;
        };

        onMounted(() => {
            if (rootEl.value === null) {
                return;
            }

            mounted = swallowReadyRejection(
                mountEditor(rootEl.value, buildMountConfig(props)),
            );

            window.addEventListener(VE_EDITOR_CHANGE, onChange);
            window.addEventListener(VE_EDITOR_AUTOSAVE, onAutosave);
            window.addEventListener(VE_EDITOR_SAVE, onSave);
        });

        // If the caller swaps the underlying model (e.g. Inertia navigation
        // to a different post), tear the React root down and remount against
        // the new config. The editor reads its mount config once at React
        // mount time, so prop changes need a full remount to propagate.
        watch(
            () => [props.resource, String(props.model.id), props.apiBase],
            ([nextResource, nextId, nextApiBase], [prevResource, prevId, prevApiBase]) => {
                if (
                    nextResource === prevResource &&
                    nextId === prevId &&
                    nextApiBase === prevApiBase
                ) {
                    return;
                }

                if (mounted !== null) {
                    mounted.unmount();
                    mounted = null;
                }

                if (rootEl.value !== null) {
                    mounted = swallowReadyRejection(
                        mountEditor(rootEl.value, buildMountConfig(props)),
                    );
                }
            },
        );

        onBeforeUnmount(() => {
            window.removeEventListener(VE_EDITOR_CHANGE, onChange);
            window.removeEventListener(VE_EDITOR_AUTOSAVE, onAutosave);
            window.removeEventListener(VE_EDITOR_SAVE, onSave);

            if (mounted !== null) {
                mounted.unmount();
                mounted = null;
            }
        });

        return () =>
            h('div', {
                ref: rootEl,
                class: 'ap-visual-editor',
            });
    },
});
