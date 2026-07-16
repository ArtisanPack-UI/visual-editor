/**
 * Visual editor React app.
 *
 * Mounts a `BlockEditorProvider` for a single resource+id pair and wires the
 * persistence loop so keystrokes hit Laravel via debounced PUTs. M7 (#317)
 * added the top bar chrome; A1 (#343) moved the title input into the canvas
 * and introduced the real inspector sidebar (block + document tabs) in place
 * of the M7 placeholder.
 */

import {
    useCallback,
    useEffect,
    useId,
    useMemo,
    useRef,
    useState,
} from 'react';
import { Alert, ToastProvider } from '@artisanpack-ui/react/feedback';
import { BlockEditorProvider } from '@wordpress/block-editor';
import { Popover, SlotFillProvider } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { EntityProvider } from '@wordpress/core-data';
// Importing `@wordpress/format-library` is a side-effect — it registers
// the core rich-text formats (bold, italic, link, inline code, etc.) so
// the block toolbar's inline formatting controls render inside RichText
// blocks. Without this import the toolbar renders but the formats are
// empty (#343).
import '@wordpress/format-library';
import { __, sprintf } from '@wordpress/i18n';
import type { BlockInstance } from '@wordpress/blocks';

import { addFilter } from '@wordpress/hooks';

import { bootI18n, TEXT_DOMAIN } from '../vendor/i18n';
import { ensureMediaBridgeFilter } from '../media-bridge';
import { useThemedEditorSettings } from '../use-themed-editor-settings';

import { registerArtisanPackBlocks } from '../blocks';

import { BlockLibrarySidebar } from './block-library-sidebar';
import { registerContrastWarning } from './contrast-warning';
import { registerResponsiveAttribute } from '../responsive/register-attribute';
import { registerResponsiveAttributesFilter } from '../responsive/with-responsive-attributes';
import { registerStateAttribute } from '../states/register-attribute';
import { registerStateAttributesFilter } from '../states/with-state-attributes';
import { registerBackgroundControls } from '../background-controls';
import { registerBoxShadows } from '../box-shadows/register';
import { registerGradientBorders } from '../gradient-borders/register';
import { registerPositioning } from '../positioning/register';
import { registerStateStylesFilters } from '../states/with-state-styles';
import { registerAnimationsAttribute } from '../animations/register-attribute';
import { registerAnimationsPanel } from '../animations/with-animations-panel';
import { registerVisibilityAttribute } from '../visibility/register-attribute';
import { registerVisibilityPanel, setVisibilityBreakpoints } from '../visibility/with-visibility-panel';
import { registryFromSnapshot } from '../responsive/registry';
import type { BreakpointRegistrySnapshot } from '../responsive/types';
import { useCanvasPreviewWidth } from '../responsive/use-canvas-preview-width';
import {
    setBindingsApiConfig,
    setBindingsResourceContext,
} from '../bindings';
import { registerBindingsAttribute } from '../bindings/register-attribute';
import { registerBindingsPanel } from '../bindings/with-bindings-panel';
import { StateInspectorSync } from '../states/StateInspectorSync';
import { StateWriteInterceptor } from '../states/state-write-interceptor';
import { ConvertToPatternControl } from './convert-to-pattern-control';
import { EditorCanvas } from './editor-canvas';
import {
    PagePatternModal,
    type TemplateOption,
} from './page-pattern-modal/page-pattern-modal';
import { useFreshContentDetection } from './page-pattern-modal/use-fresh-content-detection';
import { filterModalPatterns } from './page-pattern-modal/filter-modal-patterns';
import { registerSyncedPatternIndicator } from './synced-pattern-indicator';
import {
    listPatterns,
    SiteEditorApiError as PatternsApiError,
    type PatternRecord,
} from '../site-editor/patterns/api-client';
import { listEntities } from '../site-editor/api-client';
import {
    DocumentPanels,
    type AuthorOption,
    type DocumentSupports,
    type DocumentType,
    type FeaturedImageValue,
    type PostStatus,
} from './document-panels';
import { entityTypeForResource } from './entity-type';
import { InspectorSidebar } from './inspector-sidebar';
import { KeyboardShortcutsModal } from './keyboard-shortcuts-modal';
import { useSaveNotifications } from './save-notifications';
import { TopBar } from './top-bar';
import { usePersistence } from './use-persistence';

// Side-effect imports for the editor *chrome* — the top bar, block
// toolbar, and inspector sidebar that render in the parent document,
// outside the `BlockCanvas` iframe. The same stylesheets are injected
// *into* the iframe separately via `canvas-styles.ts` (#347), because
// the iframe document doesn't inherit the parent's Vite-injected
// `<style>` tags.
import '@wordpress/components/build-style/style.css';
import '@wordpress/block-editor/build-style/style.css';
import '@wordpress/block-editor/build-style/content.css';
import '@wordpress/block-library/build-style/style.css';
import '@wordpress/block-library/build-style/editor.css';

import './editor-app.css';
// Loaded last so DaisyUI-driven custom properties win the cascade against
// the `@wordpress/*` stylesheets above (M8 · #318).
import './visual-editor-theme.css';

export type { PostStatus, FeaturedImageValue, AuthorOption, DocumentSupports };

let blocksRegistered = false;

/**
 * Filter namespace used by `disableContrastCheckerOnBlocks` below so the
 * registration is idempotent across HMR reloads.
 */
const CONTRAST_CHECKER_FILTER_NAMESPACE =
    'artisanpack-ui/visual-editor/disable-contrast-checker';

/**
 * Turn off `BlockColorContrastChecker` for every block that opts into
 * color support. The checker (block-editor v15.x) has a deps-less
 * `useLayoutEffect` that schedules a double-`requestAnimationFrame`
 * setColors chain on every render. During a color-picker drag those
 * RAF callbacks pile up faster than React can settle, tripping its
 * "Maximum update depth exceeded" guard and crashing the block via
 * `BlockCrashBoundary`. Disabling the checker via block supports stops
 * the component from mounting at all, which sidesteps the bug until
 * Gutenberg lands the upstream fix.
 */
function disableContrastCheckerOnBlocks(): void {
    addFilter(
        'blocks.registerBlockType',
        CONTRAST_CHECKER_FILTER_NAMESPACE,
        (settings: { supports?: Record<string, unknown> } | null | undefined) => {
            if (
                settings === null ||
                settings === undefined ||
                typeof settings !== 'object'
            ) {
                return settings;
            }

            const supports = settings.supports;

            if (
                supports === null ||
                supports === undefined ||
                typeof supports !== 'object' ||
                !('color' in supports)
            ) {
                return settings;
            }

            const color = (supports as { color: unknown }).color;
            const normalizedColor =
                color === true ? {} : color === null || color === undefined
                    ? null
                    : typeof color === 'object' ? { ...color } : null;

            if (normalizedColor === null) {
                return settings;
            }

            return {
                ...settings,
                supports: {
                    ...supports,
                    color: {
                        ...normalizedColor,
                        enableContrastChecker: false,
                    },
                },
            };
        }
    );
}

function registerOnce(): void {
    if (blocksRegistered) {
        return;
    }

    bootI18n();
    ensureMediaBridgeFilter();
    // Install the contrast-checker suppression filter before blocks
    // register so it reaches every block at registration time.
    disableContrastCheckerOnBlocks();
    registerContrastWarning();
    registerSyncedPatternIndicator();
    // #649 — register the background-controls BlockEdit HOC FIRST so
    // it wraps innermost. `@wordpress/hooks` composes filters in
    // registration order, so the LAST-registered HOC wraps outermost;
    // registering background-controls before the responsive + state
    // HOCs below makes those wrap around it, which means the
    // `context.attributes` this HOC hands to filter callbacks is the
    // breakpoint-merged / state-resolved view — not the raw idle
    // attributes — and `context.setAttributes` routes writes through
    // the responsive/state wrappers.
    registerBackgroundControls();
    // #487 — register the responsive feature filters BEFORE blocks load
    // so opted-in blocks pick up the auto-injected `responsive`
    // attribute at registration time and the BlockEdit HOC wraps every
    // edit component on first render.
    registerResponsiveAttribute();
    registerResponsiveAttributesFilter();
    // #488 — register the state feature filters BEFORE blocks load so
    // opted-in blocks pick up the auto-injected `states` attribute at
    // registration time and the BlockEdit HOC wraps every edit
    // component on first render.
    registerStateAttribute();
    registerStateAttributesFilter();
    registerStateStylesFilters();
    // #489 — register the animation feature filters BEFORE blocks load
    // so opted-in blocks pick up the auto-injected
    // `artisanpackAnimations` attribute at registration time and the
    // BlockEdit HOC drops the AnimationPanel into the inspector on
    // first render.
    registerAnimationsAttribute();
    registerAnimationsPanel();
    // #491 · #492 · #493 — register the block-visibility feature
    // filters BEFORE blocks load so opted-in blocks pick up the
    // auto-injected `artisanpackVisibility` attribute at registration
    // time and the BlockEdit HOC drops the VisibilityPanel into the
    // inspector on first render. Every block opts in by default;
    // blocks that must not be conditionally hidden declare
    // `supports.artisanpackVisibility: false` in their block.json.
    registerVisibilityAttribute();
    registerVisibilityPanel();
    // #504 — register the bindings sidecar attribute on every block
    // and inject the inspector panel. Runs at editor-bootstrap time so
    // the `bindings` storage key is in every block's schema by the time
    // `registerArtisanPackBlocks()` runs below.
    registerBindingsAttribute();
    registerBindingsPanel();
    // #490 — register the gradient-border feature filters BEFORE
    // blocks load so the supports-extension fires at registration
    // time (extending opted-in blocks' state/responsive routing lists
    // automatically) and the BlockEdit HOC wraps every edit component
    // on first render.
    registerGradientBorders();
    registerBoxShadows();
    registerPositioning();
    // I7 (#415): register all artisanpack/* blocks and set the default
    // block to artisanpack/paragraph. Core blocks are no longer loaded.
    registerArtisanPackBlocks();
    blocksRegistered = true;
}

const VALID_STATUSES: readonly PostStatus[] = [
    'draft',
    'pending',
    'scheduled',
    'published',
    'private',
];

function normalizeStatus(value: string | undefined): PostStatus {
    if (value !== undefined) {
        const match = VALID_STATUSES.find((status) => status === value);

        if (match !== undefined) {
            return match;
        }
    }

    return 'draft';
}

export interface MetadataChange {
    title: string;
    slug: string;
    status: PostStatus;
    excerpt: string;
    featuredImage: FeaturedImageValue | null;
    authorId: number | string | null;
    commentsOpen: boolean;
    /** Selected category ids — surfaced for `documentType === 'post'`. */
    categories: ReadonlyArray<number>;
    /** Selected tag ids — surfaced for `documentType === 'post'`. */
    tags: ReadonlyArray<number>;
    /** Parent page id; `null` clears the relationship. Page-only. */
    parent: number | null;
    /** Page menu_order. Page-only. */
    menuOrder: number;
    /** Theme template slug applied to this page. Page-only. */
    template: string;
}

export interface EditorAppProps {
    apiBase: string;
    resource: string;
    id: string;
    initialTitle?: string;
    initialSlug?: string;
    initialStatus?: PostStatus | string;
    initialExcerpt?: string;
    initialFeaturedImage?: FeaturedImageValue | null;
    initialAuthorId?: number | string | null;
    initialCommentsOpen?: boolean;
    initialCategories?: ReadonlyArray<number>;
    initialTags?: ReadonlyArray<number>;
    initialParent?: number | null;
    initialMenuOrder?: number;
    initialTemplate?: string;
    /**
     * ISO-8601 timestamp when the record was created (#639). Passed
     * verbatim to the fresh-content detection hook that gates the
     * page-pattern modal from auto-opening on already-saved pages.
     */
    initialCreatedAt?: string;
    /**
     * ISO-8601 timestamp when the record was last updated (#639).
     * See {@link initialCreatedAt}.
     */
    initialUpdatedAt?: string;
    authorOptions?: ReadonlyArray<AuthorOption>;
    supports?: DocumentSupports;
    previewUrl?: string | null;
    onMetadataChange?: (change: MetadataChange) => void;
    /**
     * Serialised breakpoint registry (#617). When present, hydrated
     * via `registryFromSnapshot()` and passed to `TopBar` as
     * `viewportRegistry` so host-configured `label` and
     * `previewWidthPx` overrides reach the viewport switcher.
     */
    breakpoints?: BreakpointRegistrySnapshot | null;
}

export { entityTypeForResource } from './entity-type';

interface HistoryState {
    past: BlockInstance[][];
    future: BlockInstance[][];
}

const EMPTY_HISTORY: HistoryState = { past: [], future: [] };

export function EditorApp(props: EditorAppProps): JSX.Element {
    registerOnce();

    // #504 — push the current editor's apiBase + resource + record id
    // into the bindings module so the inspector panel resolves its
    // API calls, its picker, and the live canvas overlay against the
    // right parent record.
    setBindingsApiConfig({ apiBase: props.apiBase });
    setBindingsResourceContext(props.resource ?? null, props.id ?? null);

    return (
        <ToastProvider>
            <EditorAppShell {...props} />
        </ToastProvider>
    );
}

function EditorAppShell(props: EditorAppProps): JSX.Element {
    const {
        initialTitle = '',
        initialSlug = '',
        initialStatus,
        initialExcerpt = '',
        initialFeaturedImage = null,
        initialAuthorId = null,
        initialCommentsOpen = true,
        initialCategories,
        initialTags,
        initialParent = null,
        initialMenuOrder = 0,
        initialTemplate = '',
        authorOptions,
        supports,
        previewUrl = null,
        onMetadataChange,
    } = props;

    const themedSettings = useThemedEditorSettings({ apiBase: props.apiBase });

    const documentType = entityTypeForResource(props.resource);
    // Validate against a whole-digit regex *before* parsing so malformed
    // ids like "42abc" don't silently promote to 42 — the EntityProvider
    // wrap below would otherwise fetch the wrong entity record.
    const numericId = useMemo<number | null>(
        () => (/^\d+$/.test(props.id) ? Number.parseInt(props.id, 10) : null),
        [props.id]
    );

    // G4a: thread the entity identity (kind/name/id) through the
    // persistence layer so sidebar metadata edits and post-title block
    // edits round-trip via the G3 entity endpoint after the block save.
    // Bypasses for non-cms-framework resources (custom HasBlockContent
    // models without a shim entity registration) — the persistence loop
    // skips the metadata flush when `entity` is null.
    const entityIdentity = useMemo(
        () =>
            documentType !== null && numericId !== null
                ? { kind: 'postType', name: documentType, id: numericId }
                : null,
        [documentType, numericId]
    );

    // Memoized block context value. Stable reference across editor-app
    // re-renders (e.g. on entity-edit dispatches) so `BlockContextProvider`
    // doesn't fan out a new context object to every block on every render
    // — the cascading `Edit` re-renders that follows clobbers the
    // block-editor's selection state, leaving the inspector's "Block" tab
    // stuck on its empty placeholder mid-edit.
    const blockContextValue = useMemo(
        () =>
            documentType !== null && numericId !== null
                ? { postType: documentType, postId: numericId }
                : null,
        [documentType, numericId]
    );

    const {
        blocks,
        loadStatus,
        saveStatus,
        loadError,
        saveError,
        lastSavedAt,
        onBlocksChange,
        queueBlocksForSave,
        queueMetadataForSave,
        flush,
    } = usePersistence({ ...props, entity: entityIdentity });

    const { editEntityRecord } = useDispatch('core') as {
        editEntityRecord?: (
            kind: string,
            name: string,
            id: number,
            edits: Record<string, unknown>
        ) => void;
    };

    /**
     * Stages a metadata edit on the core-data entity record (when the
     * editor is mounted against a cms-framework Post/Page) and schedules
     * the debounced metadata flush. Sidebar handlers below call this
     * alongside their `setLocalState` mirror so the canvas's
     * `useEntityProp`-driven blocks (e.g. `core/post-title`) and the
     * inspector share a single source of truth on save.
     */
    const stageEntityEdit = useCallback(
        (field: string, value: unknown): void => {
            if (entityIdentity === null || editEntityRecord === undefined) {
                return;
            }

            editEntityRecord(
                entityIdentity.kind,
                entityIdentity.name,
                entityIdentity.id,
                { [field]: value }
            );
            queueMetadataForSave();
        },
        [entityIdentity, editEntityRecord, queueMetadataForSave]
    );

    // Watches the entity edits bag. Blocks rendered inside the canvas
    // (e.g. `core/post-title` typing into a `PlainText`) call
    // `editEntityRecord` directly through `useEntityProp`'s setter — they
    // never reach `stageEntityEdit`. Subscribing here picks up those
    // edits and re-arms the metadata-save debounce so block-level
    // metadata edits round-trip on the same cycle as sidebar edits.
    const stagedEntityEdits = useSelect(
        (select) => {
            if (entityIdentity === null) {
                return null;
            }

            const store = select('core') as
                | {
                      getEntityRecordEdits?: (
                          kind: string,
                          name: string,
                          id: number
                      ) => Record<string, unknown> | null;
                  }
                | undefined;

            return (
                store?.getEntityRecordEdits?.(
                    entityIdentity.kind,
                    entityIdentity.name,
                    entityIdentity.id
                ) ?? null
            );
        },
        [entityIdentity]
    );

    useEffect(() => {
        if (
            stagedEntityEdits !== null
            && Object.keys(stagedEntityEdits).length > 0
        ) {
            queueMetadataForSave();
        }
    }, [stagedEntityEdits, queueMetadataForSave]);

    useSaveNotifications({
        saveStatus,
        saveErrorMessage: saveError?.message ?? null,
    });

    const mainHeadingId = useId();
    const [title, setTitle] = useState<string>(initialTitle);
    const [slug, setSlug] = useState<string>(initialSlug);
    const [status, setStatus] = useState<PostStatus>(
        normalizeStatus(typeof initialStatus === 'string' ? initialStatus : undefined)
    );
    const [excerpt, setExcerpt] = useState<string>(initialExcerpt);
    const [featuredImage, setFeaturedImage] = useState<FeaturedImageValue | null>(
        initialFeaturedImage
    );
    const [authorId, setAuthorId] = useState<number | string | null>(initialAuthorId);
    const [commentsOpen, setCommentsOpen] = useState<boolean>(initialCommentsOpen);
    const [categories, setCategories] = useState<ReadonlyArray<number>>(
        initialCategories ?? []
    );
    const [tags, setTags] = useState<ReadonlyArray<number>>(initialTags ?? []);
    const [parent, setParent] = useState<number | null>(initialParent);
    const [menuOrder, setMenuOrder] = useState<number>(initialMenuOrder);
    const [template, setTemplate] = useState<string>(initialTemplate);
    const [inserterOpen, setInserterOpen] = useState<boolean>(false);
    const [inspectorOpen, setInspectorOpen] = useState<boolean>(true);
    const [shortcutsOpen, setShortcutsOpen] = useState<boolean>(false);
    // #639 — page-pattern modal state. Auto-opens on first-load for
    // fresh records with no persisted content and at least one
    // applicable pattern; the user can also open it manually via the
    // top-bar button. Dismissing clears both `patternModalOpen` and
    // `patternModalDismissed` so the auto-open path doesn't re-fire
    // within a single session.
    const [patternModalOpen, setPatternModalOpen] = useState<boolean>(false);
    const [patternModalDismissed, setPatternModalDismissed] = useState<boolean>(false);
    const [pagePatterns, setPagePatterns] = useState<readonly PatternRecord[]>([]);
    const [pagePatternsLoading, setPagePatternsLoading] = useState<boolean>(true);
    const [pagePatternsError, setPagePatternsError] = useState<string | null>(null);
    const [templateOptions, setTemplateOptions] = useState<readonly TemplateOption[]>([]);
    // #617 — viewport preset selection resizes the canvas frame.
    // The shared hook holds the `null | positive-int` slot and the
    // `onViewportChange` callback so post + site editors can't
    // drift on the base-vs-named-preset contract.
    const { canvasPreviewWidthPx, handleViewportChange } = useCanvasPreviewWidth();
    // #617 — hydrate the viewport switcher's registry from the
    // Blade-stamped `data-breakpoints` payload so host-configured
    // `label` / `previewWidthPx` overrides reach the UI. When the
    // host omits the snapshot, `registryFromSnapshot` falls back to
    // the ship defaults.
    const viewportRegistry = useMemo(
        () => registryFromSnapshot(props.breakpoints ?? undefined),
        [props.breakpoints]
    );
    // Publish the hydrated breakpoint list to the Visibility panel so
    // its Screen Size subsection lists every registered viewport
    // (sm / md / lg / xl / 2xl + host overrides), not just the three
    // fallback entries baked into with-visibility-panel.tsx.
    useEffect(() => {
        setVisibilityBreakpoints(
            viewportRegistry.prefixes().map((key: string) => ({
                key,
                label: viewportRegistry.label(key),
            })),
        );
    }, [viewportRegistry]);
    const [history, setHistory] = useState<HistoryState>(EMPTY_HISTORY);
    // Mirror history in a ref so undo/redo handlers can read the latest
    // snapshot without taking a dep on `history` (which would re-memoize the
    // TopBar on every edit). Writing side effects inside the setHistory
    // updater is unsafe under StrictMode double-invocation; keeping the reads
    // ref-based lets us compute the next state purely.
    const historyRef = useRef<HistoryState>(EMPTY_HISTORY);
    historyRef.current = history;

    // `onInput` fires on every keystroke; `onChange` fires on logical commits.
    // Track the last committed tree so history only records meaningful edits.
    const lastCommittedRef = useRef<BlockInstance[] | null>(null);

    const currentMetadata = useMemo(
        (): MetadataChange => ({
            title,
            slug,
            status,
            excerpt,
            featuredImage,
            authorId,
            commentsOpen,
            categories,
            tags,
            parent,
            menuOrder,
            template,
        }),
        [
            authorId,
            categories,
            commentsOpen,
            excerpt,
            featuredImage,
            menuOrder,
            parent,
            slug,
            status,
            tags,
            template,
            title,
        ]
    );

    const emitMetadata = useCallback(
        (next: Partial<MetadataChange>): void => {
            if (onMetadataChange === undefined) {
                return;
            }

            onMetadataChange({
                ...currentMetadata,
                ...next,
            });
        },
        [currentMetadata, onMetadataChange]
    );

    const handleTitleChange = useCallback(
        (value: string): void => {
            setTitle(value);
            emitMetadata({ title: value });
            stageEntityEdit('title', value);
        },
        [emitMetadata, stageEntityEdit]
    );

    const handleSlugChange = useCallback(
        (value: string): void => {
            setSlug(value);
            emitMetadata({ slug: value });
            stageEntityEdit('slug', value);
        },
        [emitMetadata, stageEntityEdit]
    );

    const handleStatusChange = useCallback(
        (value: PostStatus): void => {
            setStatus(value);
            emitMetadata({ status: value });
            stageEntityEdit('status', value);
        },
        [emitMetadata, stageEntityEdit]
    );

    const handleExcerptChange = useCallback(
        (value: string): void => {
            setExcerpt(value);
            emitMetadata({ excerpt: value });
            stageEntityEdit('excerpt', value);
        },
        [emitMetadata, stageEntityEdit]
    );

    const handleFeaturedImageChange = useCallback(
        (value: FeaturedImageValue | null): void => {
            setFeaturedImage(value);
            emitMetadata({ featuredImage: value });
            // The G3 endpoint's WP-shape envelope expects
            // `featured_media: <id|null>`, not the host's
            // `{id, url, alt}` blob.
            stageEntityEdit('featured_media', value === null ? null : value.id);
        },
        [emitMetadata, stageEntityEdit]
    );

    const handleAuthorChange = useCallback(
        (value: number | string | null): void => {
            setAuthorId(value);
            emitMetadata({ authorId: value });
            // WP-shape entity field is `author`, scalar id.
            stageEntityEdit(
                'author',
                typeof value === 'string' && value !== '' && /^\d+$/.test(value)
                    ? Number.parseInt(value, 10)
                    : (value as number | null)
            );
        },
        [emitMetadata, stageEntityEdit]
    );

    const handleCommentsOpenChange = useCallback(
        (value: boolean): void => {
            setCommentsOpen(value);
            emitMetadata({ commentsOpen: value });
            // `comments_open` isn't part of the V1 UpdatePostRequest
            // surface, so we don't stage it on the entity record. Hosts
            // that wire a comments column persist it through
            // `onMetadataChange` until the entity adapter formalizes it.
        },
        [emitMetadata]
    );

    const handleCategoriesChange = useCallback(
        (value: ReadonlyArray<number>): void => {
            setCategories(value);
            emitMetadata({ categories: value });
            stageEntityEdit('categories', [...value]);
        },
        [emitMetadata, stageEntityEdit]
    );

    const handleTagsChange = useCallback(
        (value: ReadonlyArray<number>): void => {
            setTags(value);
            emitMetadata({ tags: value });
            stageEntityEdit('tags', [...value]);
        },
        [emitMetadata, stageEntityEdit]
    );

    const handleParentChange = useCallback(
        (value: number | null): void => {
            setParent(value);
            emitMetadata({ parent: value });
            stageEntityEdit('parent', value);
        },
        [emitMetadata, stageEntityEdit]
    );

    const handleMenuOrderChange = useCallback(
        (value: number): void => {
            setMenuOrder(value);
            emitMetadata({ menuOrder: value });
            stageEntityEdit('menu_order', value);
        },
        [emitMetadata, stageEntityEdit]
    );

    const handleTemplateChange = useCallback(
        (value: string): void => {
            setTemplate(value);
            emitMetadata({ template: value });
            stageEntityEdit('template', value);
        },
        [emitMetadata, stageEntityEdit]
    );

    const handleInput = useCallback(
        (next: BlockInstance[]): void => {
            // `onInput` fires on intermediate, non-persistent changes —
            // color-picker drag frames, mid-typing rich-text updates, etc.
            // Calling `setBlocks(next)` here re-renders the entire editor
            // tree (TopBar, InspectorSidebar, BlockInspector panels) on
            // every frame, which triggers a runaway layout-effect cascade
            // inside Gutenberg's block-support hooks and crashes the
            // block with "Maximum update depth exceeded" (#343 A1, #347).
            //
            // The block-editor store already has the latest tree from
            // the dispatch that triggered this callback, so the canvas
            // stays in sync without our mirror. We only need to queue a
            // debounced save — undo/redo still works because `onChange`
            // (fired on the persistent commit at the end of the drag)
            // does the React state update and history push.
            //
            // Invariant: letting the `value` prop go stale between
            // commits is SAFE. `use-block-sync` only reacts to
            // reference-changes of `controlledBlocks` (its effect deps
            // are `[controlledBlocks, clientId]`); when `value` doesn't
            // change, the sync effect is a no-op and the internal
            // store keeps itself consistent. Any attempt to "mirror"
            // per-frame into a transient state reintroduces the
            // cascade we're explicitly avoiding — there's no React
            // pattern that feeds a reactive `value` prop without
            // triggering a render.
            queueBlocksForSave(next);
        },
        [queueBlocksForSave]
    );

    const handleChange = useCallback(
        (next: BlockInstance[]): void => {
            // Capture `previous` before mutating the ref so the setHistory
            // updater (which may be deferred past the mutation below) reads
            // the right value. Otherwise the deferred updater sees
            // `lastCommittedRef.current === next` and never commits history.
            const previous = lastCommittedRef.current;
            lastCommittedRef.current = next;

            if (previous !== null && previous !== next) {
                const nextHistory: HistoryState = {
                    past: [...historyRef.current.past, previous],
                    future: [],
                };
                historyRef.current = nextHistory;
                setHistory(nextHistory);
            }

            onBlocksChange(next);
        },
        [onBlocksChange]
    );

    const handleUndo = useCallback((): void => {
        const current = historyRef.current;

        if (current.past.length === 0) {
            return;
        }

        const previous = current.past[current.past.length - 1];

        if (previous === undefined) {
            return;
        }

        const snapshot = lastCommittedRef.current ?? blocks;
        const nextHistory: HistoryState = {
            past: current.past.slice(0, -1),
            future: [...current.future, snapshot],
        };

        lastCommittedRef.current = previous;
        historyRef.current = nextHistory;
        setHistory(nextHistory);
        onBlocksChange(previous);
    }, [blocks, onBlocksChange]);

    const handleRedo = useCallback((): void => {
        const current = historyRef.current;

        if (current.future.length === 0) {
            return;
        }

        const next = current.future[current.future.length - 1];

        if (next === undefined) {
            return;
        }

        const snapshot = lastCommittedRef.current ?? blocks;
        const nextHistory: HistoryState = {
            past: [...current.past, snapshot],
            future: current.future.slice(0, -1),
        };

        lastCommittedRef.current = next;
        historyRef.current = nextHistory;
        setHistory(nextHistory);
        onBlocksChange(next);
    }, [blocks, onBlocksChange]);

    // #639 — fetch page-scope patterns whenever the mounted post-type
    // context changes. `documentType` is the WP-style singular slug
    // (`page`, `post`, …); when it's null (custom HasBlockContent
    // models without a shim registration), we skip the fetch and the
    // modal stays closed.
    useEffect(() => {
        if (documentType === null) {
            setPagePatterns([]);
            setPagePatternsLoading(false);
            return;
        }

        let cancelled = false;

        setPagePatternsLoading(true);
        setPagePatternsError(null);

        // Scope the modal fetch to theme-source patterns only. The
        // page-pattern-inserter is a page-starter picker; user-created
        // snippet patterns (`source: 'user'`, saved via "Convert to
        // pattern" in the sidebar) belong to the block inserter panel,
        // not the whole-page modal. Filtering server-side keeps the
        // modal grid focused on layouts a developer or theme deliberately
        // shipped as starters.
        listPatterns(
            { apiBase: props.apiBase },
            { postType: documentType, source: 'theme', perPage: 100 }
        )
            .then((records) => {
                if (cancelled) {
                    return;
                }

                // Tighten the server's permissive `?post_type=` filter
                // client-side so unscoped patterns (which the sidebar
                // block-inserter treats as "library of everything")
                // don't leak into the whole-page modal. See the helper
                // for the seed carve-out.
                setPagePatterns(filterModalPatterns(records, documentType));
                setPagePatternsLoading(false);
            })
            .catch((error: unknown) => {
                if (cancelled) {
                    return;
                }

                setPagePatterns([]);
                setPagePatternsLoading(false);
                setPagePatternsError(
                    error instanceof PatternsApiError
                        ? error.message
                        : __('Failed to load patterns.', TEXT_DOMAIN)
                );
            });

        return () => {
            cancelled = true;
        };
    }, [documentType, props.apiBase]);

    // #639 — fetch the site-editor template list once per mount so the
    // modal's template selector has entries to render. Templates are
    // free-form theme templates; when the fetch fails or returns nothing,
    // the selector row is suppressed (falsy `templateOptions`).
    useEffect(() => {
        if (documentType !== 'page') {
            // Only pages carry a `template` field today; skip the fetch
            // for other post types so we don't ship a selector the caller
            // has no persistence path for.
            setTemplateOptions([]);
            return;
        }

        let cancelled = false;

        listEntities({ apiBase: props.apiBase }, 'template', { perPage: 100 })
            .then((records) => {
                if (cancelled) {
                    return;
                }

                const options: TemplateOption[] = [
                    {
                        slug: '',
                        label: __('Default template', TEXT_DOMAIN),
                    },
                ];

                for (const record of records) {
                    options.push({
                        slug: record.slug,
                        label:
                            record.title.rendered && record.title.rendered.trim() !== ''
                                ? record.title.rendered
                                : record.slug,
                        source: __('Theme', TEXT_DOMAIN),
                    });
                }

                setTemplateOptions(options);
            })
            .catch(() => {
                if (cancelled) {
                    return;
                }

                // A failed template fetch shouldn't block the pattern
                // grid from rendering — silently drop back to "no
                // selector" so the modal still works.
                setTemplateOptions([]);
            });

        return () => {
            cancelled = true;
        };
    }, [documentType, props.apiBase]);

    // #639 — the auto-open trigger. Fires exactly once when all of the
    // following hold: the initial load has completed, the record has
    // never been saved AND has no content, at least one pattern
    // applies to the current post-type context, and the user hasn't
    // already dismissed the modal in this session.
    const isFreshCanvas = useFreshContentDetection({
        blocks,
        createdAt: props.initialCreatedAt,
        updatedAt: props.initialUpdatedAt,
        loadStatus,
    });

    useEffect(() => {
        if (patternModalDismissed) {
            return;
        }

        if (!isFreshCanvas) {
            return;
        }

        if (pagePatternsLoading) {
            return;
        }

        if (pagePatterns.length === 0) {
            // Zero patterns registered for this post type — the auto-open
            // path is suppressed so users aren't invited into an empty
            // modal. Toolbar re-open still works.
            return;
        }

        setPatternModalOpen(true);
    }, [
        isFreshCanvas,
        pagePatterns.length,
        pagePatternsLoading,
        patternModalDismissed,
    ]);

    const handleCloseModal = useCallback((): void => {
        setPatternModalOpen(false);
        setPatternModalDismissed(true);
    }, []);

    const handleOpenModal = useCallback((): void => {
        setPatternModalOpen(true);
    }, []);

    // Insert the pattern's block tree into the canvas. An empty tree
    // (e.g. the `Blank` seed) is a no-op — same semantics as WordPress's
    // "Start blank" affordance.
    //
    // Gated on `loadStatus === 'ready'` so users can't drop pattern
    // blocks into an entity whose initial fetch is still in flight or
    // has errored — the debounced save would otherwise race the load
    // and clobber the real content once it lands.
    const handleInsertPatternBlocks = useCallback(
        (patternBlocks: readonly BlockInstance[]): void => {
            if (patternBlocks.length === 0) {
                return;
            }

            if (loadStatus !== 'ready') {
                return;
            }

            // Push through the same `onChange` path a real edit would
            // take so history + persistence pick up the insertion.
            handleChange([...patternBlocks]);
        },
        [handleChange, loadStatus]
    );

    // Expose the manual-open button when the fetch has settled AND
    // either at least one pattern applies to this post type OR the
    // fetch itself failed (the modal surfaces the error so users get
    // a chance to retry / diagnose instead of the feature silently
    // disappearing). Zero-pattern success case still suppresses the
    // button, matching the auto-open suppression for zero-pattern
    // contexts. Also gated on `loadStatus === 'ready'` so users can't
    // insert pattern blocks into an entity that hasn't loaded — that
    // would race the persistence loop's initial fetch and clobber the
    // real content once it lands.
    const patternModalTriggerAvailable =
        loadStatus === 'ready'
        && !pagePatternsLoading
        && (pagePatterns.length > 0 || pagePatternsError !== null);

    const handleToggleInserter = useCallback((): void => {
        setInserterOpen((open) => !open);
    }, []);

    const handleToggleInspector = useCallback((): void => {
        setInspectorOpen((open) => !open);
    }, []);

    const handleShowShortcuts = useCallback((): void => {
        setShortcutsOpen(true);
    }, []);

    const handleCloseShortcuts = useCallback((): void => {
        setShortcutsOpen(false);
    }, []);

    const topBar = useMemo(
        () => (
            <TopBar
                saveStatus={saveStatus}
                lastSavedAt={lastSavedAt}
                saveErrorMessage={saveError?.message ?? null}
                canUndo={history.past.length > 0}
                canRedo={history.future.length > 0}
                onUndo={handleUndo}
                onRedo={handleRedo}
                isInserterOpen={inserterOpen}
                isInspectorOpen={inspectorOpen}
                onToggleInserter={handleToggleInserter}
                onToggleInspector={handleToggleInspector}
                previewUrl={previewUrl}
                onSave={flush}
                onShowKeyboardShortcuts={handleShowShortcuts}
                onViewportChange={handleViewportChange}
                viewportRegistry={viewportRegistry}
                onOpenPatternModal={
                    patternModalTriggerAvailable ? handleOpenModal : undefined
                }
            />
        ),
        [
            flush,
            handleOpenModal,
            handleRedo,
            handleShowShortcuts,
            handleToggleInserter,
            handleToggleInspector,
            handleUndo,
            handleViewportChange,
            history.future.length,
            history.past.length,
            inserterOpen,
            inspectorOpen,
            lastSavedAt,
            patternModalTriggerAvailable,
            previewUrl,
            saveError,
            saveStatus,
            viewportRegistry,
        ]
    );

    const shortcutsModal = (
        <KeyboardShortcutsModal
            open={shortcutsOpen}
            onClose={handleCloseShortcuts}
        />
    );

    // #639 — the page-pattern-inserter modal itself. Rendered at the
    // shell level so it overlays the whole editor (including the load
    // / error states below), not just the block-editor tree.
    const pagePatternModal = (
        <PagePatternModal
            open={patternModalOpen}
            onClose={handleCloseModal}
            patterns={pagePatterns}
            onInsertBlocks={handleInsertPatternBlocks}
            templateOptions={templateOptions.length > 0 ? templateOptions : undefined}
            initialTemplate={template}
            onTemplateChange={
                documentType === 'page' ? handleTemplateChange : undefined
            }
            loading={pagePatternsLoading}
            errorMessage={pagePatternsError}
        />
    );

    const documentPanels = (
        <DocumentPanels
            status={status}
            slug={slug}
            onStatusChange={handleStatusChange}
            onSlugChange={handleSlugChange}
            authorId={authorId}
            authorOptions={authorOptions}
            onAuthorChange={handleAuthorChange}
            excerpt={excerpt}
            onExcerptChange={handleExcerptChange}
            featuredImage={featuredImage}
            onFeaturedImageChange={handleFeaturedImageChange}
            commentsOpen={commentsOpen}
            onCommentsOpenChange={handleCommentsOpenChange}
            supports={supports}
            documentType={documentType}
            categories={categories}
            onCategoriesChange={
                documentType === 'post' ? handleCategoriesChange : undefined
            }
            tags={tags}
            onTagsChange={documentType === 'post' ? handleTagsChange : undefined}
            parent={parent}
            onParentChange={
                documentType === 'page' ? handleParentChange : undefined
            }
            menuOrder={menuOrder}
            onMenuOrderChange={
                documentType === 'page' ? handleMenuOrderChange : undefined
            }
            template={template}
            onTemplateChange={
                documentType === 'page' ? handleTemplateChange : undefined
            }
        />
    );

    if (loadStatus === 'loading') {
        return (
            <div className="ap-visual-editor__shell" data-state="loading">
                {topBar}
                <p className="ap-visual-editor__status ap-visual-editor__status--loading">
                    {__('Loading content…', TEXT_DOMAIN)}
                </p>
                {shortcutsModal}
                {/*
                 * #639 — pattern modal is intentionally NOT rendered in
                 * the load-in-progress / load-error branches. The
                 * insert handler is gated on `loadStatus === 'ready'`,
                 * but suppressing the whole modal here also removes
                 * the visual affordance so users don't see a modal
                 * whose actions silently no-op.
                 */}
            </div>
        );
    }

    if (loadStatus === 'error') {
        return (
            <div className="ap-visual-editor__shell" data-state="error">
                {topBar}
                <div
                    className="ap-visual-editor__status ap-visual-editor__status--error"
                    data-testid="ap-visual-editor-load-error"
                >
                    <Alert color="error">
                        {loadError?.message ??
                            __('Unable to load content.', TEXT_DOMAIN)}
                    </Alert>
                </div>
                {shortcutsModal}
            </div>
        );
    }

    const editorBody = (
        <BlockEditorProvider
            value={blocks}
            settings={themedSettings}
            onInput={handleInput}
            onChange={handleChange}
        >
            {inserterOpen ? (
                <div
                    className="ap-visual-editor__sidebar ap-visual-editor__sidebar--inserter"
                    data-testid="ap-visual-editor-inserter-panel"
                >
                    <BlockLibrarySidebar apiBase={props.apiBase} />
                </div>
            ) : null}
            {/*
             * #347: the block list renders inside `EditorCanvas`'s
             * `BlockCanvas` iframe. `blockContextValue` is non-null
             * exactly for cms-framework Post/Page edits — it stamps
             * `postType`/`postId` into block context so `core/post-*`
             * blocks (which declare `usesContext: ["postId",
             * "postType", "queryId"]`) see the active entity. Outside
             * an entity-mounted canvas it's null and the blocks render
             * their placeholder shell.
             */}
            <EditorCanvas
                showTitle={supports?.title !== false}
                title={title}
                onTitleChange={handleTitleChange}
                blockContext={blockContextValue}
                apiBase={props.apiBase}
                previewWidthPx={canvasPreviewWidthPx}
            />
            {/*
             * #488 — watch the selected block's attributes and re-route
             * writes from WP's color/border panels (which dispatch
             * directly to the block-editor store, bypassing the
             * editor.BlockEdit prop chain) into `attributes.states`
             * when the active state is non-idle.
             */}
            <StateWriteInterceptor />
            <StateInspectorSync />
            <Popover.Slot />
            <ConvertToPatternControl apiBase={props.apiBase} />
            {inspectorOpen ? (
                <div
                    className="ap-visual-editor__sidebar ap-visual-editor__sidebar--inspector"
                    data-testid="ap-visual-editor-inspector-panel"
                >
                    <InspectorSidebar
                        documentContent={documentPanels}
                        showDocumentTab={supports?.document !== false}
                    />
                </div>
            ) : null}
        </BlockEditorProvider>
    );

    // G3: when the editor mounts for a cms-framework Post or Page, wrap
    // the canvas in `EntityProvider` so `core/post-*` blocks pick up
    // their entity context (kind, name, id) and resolve through the
    // core-data shim's `useEntityRecord` / `useEntityProp` hooks. Other
    // resources (custom HasBlockContent models, legacy fixtures) skip
    // the wrap and the blocks render the placeholder shell — same
    // behavior as before this phase.
    const wrappedBody =
        documentType !== null && numericId !== null ? (
            <EntityProvider
                kind="postType"
                name={documentType}
                id={numericId}
            >
                {editorBody}
            </EntityProvider>
        ) : (
            editorBody
        );

    const mainHeadingText = sprintf(
        /* translators: %s: post title or fallback. */
        __('Editing: %s', TEXT_DOMAIN),
        title === '' ? __('Untitled', TEXT_DOMAIN) : title
    );

    return (
        <SlotFillProvider>
            <div
                className="ap-visual-editor__shell"
                data-inserter-open={inserterOpen}
                data-inspector-open={inspectorOpen}
                data-document-type={documentType ?? 'unset'}
            >
                {topBar}
                <main
                    className="ap-visual-editor__body"
                    aria-labelledby={mainHeadingId}
                >
                    <h1 id={mainHeadingId} className="screen-reader-text">
                        {mainHeadingText}
                    </h1>
                    {wrappedBody}
                </main>
                {shortcutsModal}
                {pagePatternModal}
            </div>
        </SlotFillProvider>
    );
}
