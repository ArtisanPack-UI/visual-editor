/**
 * Built-in Document tab panels.
 *
 * Together with `<PluginDocumentSettingPanel>` and the
 * `ap.visual-editor.document-panels` filter, these form the default
 * contents of the inspector sidebar's Document tab. Each panel is
 * independently gated so a host that doesn't support comments, doesn't
 * pass an author list, etc. simply hides that panel — no host config
 * means no empty rows.
 *
 * All panels use `@wordpress/components` primitives so they inherit the
 * same look, keyboard behaviour, and focus outlines as the block
 * inspector. DaisyUI theming is applied at the shell level through the
 * `--ap-visual-editor-*` CSS custom properties.
 */

import {
    MediaUpload,
    MediaUploadCheck,
} from '@wordpress/block-editor';
import {
    Button,
    PanelBody,
    PanelRow,
    SelectControl,
    TextareaControl,
    TextControl,
    ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useCallback } from 'react';
import type { ReactNode } from 'react';

import { TEXT_DOMAIN } from '../vendor/i18n';

import {
    DocumentPanelSlot,
    getFilteredDocumentPanels,
} from './plugin-document-setting-panel';

export type PostStatus =
    | 'draft'
    | 'pending'
    | 'scheduled'
    | 'published'
    | 'private';

export interface FeaturedImageValue {
    id: number;
    url: string;
    alt?: string;
}

export interface AuthorOption {
    value: number | string;
    label: string;
}

export interface DocumentSupports {
    /** Show the Excerpt panel. Default `true`. */
    excerpt?: boolean;
    /** Show the Featured Image panel. Default `true`. */
    featuredImage?: boolean;
    /**
     * Show the Discussion panel. Default `false` because comments are a
     * host-model concern and not every resource exposes them.
     */
    comments?: boolean;
}

/**
 * Discriminator that toggles the post-type-specific document panels.
 *
 * Threaded down from `EditorApp` based on the editor's mount resource —
 * `'posts'` → `'post'`, `'pages'` → `'page'`, anything else (custom
 * resources, the legacy fixture) → `null` so neither extra panel
 * renders.
 *
 * @since 1.0.0
 */
export type DocumentType = 'post' | 'page' | null;

export interface DocumentPanelsProps {
    status: PostStatus;
    slug: string;
    onStatusChange: (status: PostStatus) => void;
    onSlugChange: (slug: string) => void;

    authorId?: number | string | null;
    authorOptions?: ReadonlyArray<AuthorOption>;
    onAuthorChange?: (authorId: number | string | null) => void;

    excerpt?: string;
    onExcerptChange?: (value: string) => void;

    featuredImage?: FeaturedImageValue | null;
    onFeaturedImageChange?: (value: FeaturedImageValue | null) => void;

    commentsOpen?: boolean;
    onCommentsOpenChange?: (value: boolean) => void;

    supports?: DocumentSupports;

    /**
     * When set to `'post'` or `'page'` the inspector renders the matching
     * post-type-specific panel set (taxonomies for posts, page attributes
     * for pages). `null` (the default for non-cms-framework resources)
     * hides both.
     */
    documentType?: DocumentType;

    /** Selected category ids — post only. */
    categories?: ReadonlyArray<number>;
    onCategoriesChange?: (value: ReadonlyArray<number>) => void;

    /** Selected tag ids — post only. */
    tags?: ReadonlyArray<number>;
    onTagsChange?: (value: ReadonlyArray<number>) => void;

    /** Parent page id — page only. `null` clears the parent. */
    parent?: number | null;
    onParentChange?: (value: number | null) => void;

    /** Page menu order — page only. */
    menuOrder?: number;
    onMenuOrderChange?: (value: number) => void;

    /** Theme template slug applied to this page — page only. */
    template?: string;
    onTemplateChange?: (value: string) => void;
}

const STATUS_OPTIONS: ReadonlyArray<{ value: PostStatus; label: string }> = [
    { value: 'draft', label: 'Draft' },
    { value: 'pending', label: 'Pending review' },
    { value: 'scheduled', label: 'Scheduled' },
    { value: 'published', label: 'Published' },
    { value: 'private', label: 'Private' },
];

function statusOptions(): Array<{ value: string; label: string }> {
    return STATUS_OPTIONS.map((option) => ({
        value: option.value,
        label: translateStatus(option.value),
    }));
}

function translateStatus(status: PostStatus): string {
    switch (status) {
        case 'draft':
            return __('Draft', TEXT_DOMAIN);
        case 'pending':
            return __('Pending review', TEXT_DOMAIN);
        case 'scheduled':
            return __('Scheduled', TEXT_DOMAIN);
        case 'published':
            return __('Published', TEXT_DOMAIN);
        case 'private':
            return __('Private', TEXT_DOMAIN);
    }
}

export function DocumentPanels(props: DocumentPanelsProps): JSX.Element {
    const {
        status,
        slug,
        onStatusChange,
        onSlugChange,
        authorId,
        authorOptions,
        onAuthorChange,
        excerpt,
        onExcerptChange,
        featuredImage,
        onFeaturedImageChange,
        commentsOpen,
        onCommentsOpenChange,
        supports,
        documentType,
        categories,
        onCategoriesChange,
        tags,
        onTagsChange,
        parent,
        onParentChange,
        menuOrder,
        onMenuOrderChange,
        template,
        onTemplateChange,
    } = props;

    const showExcerpt = supports?.excerpt !== false && onExcerptChange !== undefined;
    const showFeaturedImage = supports?.featuredImage !== false;
    const showComments = supports?.comments === true && onCommentsOpenChange !== undefined;

    const showTaxonomies =
        documentType === 'post' &&
        (onCategoriesChange !== undefined || onTagsChange !== undefined);

    const showPageAttributes =
        documentType === 'page' &&
        (onParentChange !== undefined ||
            onMenuOrderChange !== undefined ||
            onTemplateChange !== undefined);

    // Recompute on every render — `@wordpress/hooks` filters are global
    // module state with no React-level subscription, so memoizing here
    // would freeze the panel list at mount time and hide any panels a
    // host registers after that point. The cost is one `applyFilters`
    // call per render, which is negligible.
    const filteredPanels = getFilteredDocumentPanels();

    return (
        <div
            className="ap-visual-editor-inspector-sidebar__document-panels"
            data-testid="ap-visual-editor-document-panels"
        >
            <StatusVisibilityPanel
                status={status}
                slug={slug}
                onStatusChange={onStatusChange}
                onSlugChange={onSlugChange}
                authorId={authorId}
                authorOptions={authorOptions}
                onAuthorChange={onAuthorChange}
            />
            {showFeaturedImage ? (
                <FeaturedImagePanel
                    value={featuredImage ?? null}
                    onChange={onFeaturedImageChange}
                />
            ) : null}
            {showExcerpt ? (
                <ExcerptPanel
                    value={excerpt ?? ''}
                    onChange={onExcerptChange!}
                />
            ) : null}
            {showTaxonomies ? (
                <TaxonomiesPanel
                    categories={categories ?? []}
                    tags={tags ?? []}
                    onCategoriesChange={onCategoriesChange}
                    onTagsChange={onTagsChange}
                />
            ) : null}
            {showPageAttributes ? (
                <PageAttributesPanel
                    parent={parent ?? null}
                    menuOrder={menuOrder ?? 0}
                    template={template ?? ''}
                    onParentChange={onParentChange}
                    onMenuOrderChange={onMenuOrderChange}
                    onTemplateChange={onTemplateChange}
                />
            ) : null}
            {showComments ? (
                <DiscussionPanel
                    commentsOpen={commentsOpen ?? true}
                    onCommentsOpenChange={onCommentsOpenChange!}
                />
            ) : null}
            {filteredPanels.map((panel) => (
                <FilteredPanel key={panel.id} panel={panel} />
            ))}
            <DocumentPanelSlot />
        </div>
    );
}

interface StatusVisibilityPanelProps {
    status: PostStatus;
    slug: string;
    onStatusChange: (status: PostStatus) => void;
    onSlugChange: (slug: string) => void;
    authorId?: number | string | null;
    authorOptions?: ReadonlyArray<AuthorOption>;
    onAuthorChange?: (authorId: number | string | null) => void;
}

function StatusVisibilityPanel(props: StatusVisibilityPanelProps): JSX.Element {
    const {
        status,
        slug,
        onStatusChange,
        onSlugChange,
        authorId,
        authorOptions,
        onAuthorChange,
    } = props;

    const hasAuthorPicker =
        authorOptions !== undefined &&
        authorOptions.length > 0 &&
        onAuthorChange !== undefined;

    const handleStatusChange = useCallback(
        (value: string): void => {
            onStatusChange(value as PostStatus);
        },
        [onStatusChange]
    );

    const handleAuthorChange = useCallback(
        (value: string): void => {
            if (onAuthorChange === undefined) {
                return;
            }

            if (value === '') {
                onAuthorChange(null);
                return;
            }

            const option = authorOptions?.find(
                (opt) => String(opt.value) === value
            );

            onAuthorChange(option?.value ?? value);
        },
        [authorOptions, onAuthorChange]
    );

    const authorSelectValue =
        authorId === null || authorId === undefined ? '' : String(authorId);

    return (
        <PanelBody
            title={__('Status & visibility', TEXT_DOMAIN)}
            initialOpen={true}
        >
            <PanelRow>
                <SelectControl
                    label={__('Status', TEXT_DOMAIN)}
                    value={status}
                    options={statusOptions()}
                    onChange={handleStatusChange}
                    __nextHasNoMarginBottom={true}
                    __next40pxDefaultSize={true}
                    data-testid="ap-visual-editor-document-status"
                />
            </PanelRow>
            <PanelRow>
                <TextControl
                    label={__('Slug', TEXT_DOMAIN)}
                    value={slug}
                    onChange={onSlugChange}
                    __nextHasNoMarginBottom={true}
                    __next40pxDefaultSize={true}
                    data-testid="ap-visual-editor-document-slug"
                />
            </PanelRow>
            {hasAuthorPicker ? (
                <PanelRow>
                    <SelectControl
                        label={__('Author', TEXT_DOMAIN)}
                        value={authorSelectValue}
                        options={[
                            { value: '', label: __('— Select author —', TEXT_DOMAIN) },
                            ...authorOptions!.map((option) => ({
                                value: String(option.value),
                                label: option.label,
                            })),
                        ]}
                        onChange={handleAuthorChange}
                        __nextHasNoMarginBottom={true}
                        __next40pxDefaultSize={true}
                        data-testid="ap-visual-editor-document-author"
                    />
                </PanelRow>
            ) : null}
        </PanelBody>
    );
}

interface FeaturedImagePanelProps {
    value: FeaturedImageValue | null;
    onChange?: (value: FeaturedImageValue | null) => void;
}

function FeaturedImagePanel(props: FeaturedImagePanelProps): JSX.Element {
    const { value, onChange } = props;

    const handleSelect = useCallback(
        (media: unknown): void => {
            if (onChange === undefined) {
                return;
            }

            if (media === null || typeof media !== 'object') {
                return;
            }

            const candidate = media as Partial<FeaturedImageValue>;

            if (typeof candidate.id !== 'number' || typeof candidate.url !== 'string') {
                return;
            }

            onChange({
                id: candidate.id,
                url: candidate.url,
                alt: typeof candidate.alt === 'string' ? candidate.alt : undefined,
            });
        },
        [onChange]
    );

    const handleRemove = useCallback((): void => {
        onChange?.(null);
    }, [onChange]);

    return (
        <PanelBody
            title={__('Featured image', TEXT_DOMAIN)}
            initialOpen={false}
        >
            <MediaUploadCheck>
                <MediaUpload
                    onSelect={handleSelect}
                    allowedTypes={['image']}
                    value={value?.id}
                    render={({ open }: { open: () => void }) => (
                        <div
                            className="ap-visual-editor-inspector-sidebar__featured-image"
                            data-testid="ap-visual-editor-document-featured-image"
                        >
                            {value !== null ? (
                                <img
                                    src={value.url}
                                    alt={value.alt ?? ''}
                                    className="ap-visual-editor-inspector-sidebar__featured-image-preview"
                                />
                            ) : null}
                            <div className="ap-visual-editor-inspector-sidebar__featured-image-actions">
                                <Button
                                    variant={value === null ? 'secondary' : 'tertiary'}
                                    onClick={open}
                                >
                                    {value === null
                                        ? __('Set featured image', TEXT_DOMAIN)
                                        : __('Replace image', TEXT_DOMAIN)}
                                </Button>
                                {value !== null ? (
                                    <Button
                                        variant="tertiary"
                                        isDestructive={true}
                                        onClick={handleRemove}
                                    >
                                        {__('Remove image', TEXT_DOMAIN)}
                                    </Button>
                                ) : null}
                            </div>
                        </div>
                    )}
                />
            </MediaUploadCheck>
        </PanelBody>
    );
}

interface ExcerptPanelProps {
    value: string;
    onChange: (value: string) => void;
}

function ExcerptPanel(props: ExcerptPanelProps): JSX.Element {
    const { value, onChange } = props;

    return (
        <PanelBody title={__('Excerpt', TEXT_DOMAIN)} initialOpen={false}>
            <TextareaControl
                label={__('Write an excerpt (optional)', TEXT_DOMAIN)}
                help={__(
                    'Excerpts are short summaries shown in listings, feeds, and search results.',
                    TEXT_DOMAIN
                )}
                value={value}
                onChange={onChange}
                __nextHasNoMarginBottom={true}
                data-testid="ap-visual-editor-document-excerpt"
            />
        </PanelBody>
    );
}

interface DiscussionPanelProps {
    commentsOpen: boolean;
    onCommentsOpenChange: (value: boolean) => void;
}

function DiscussionPanel(props: DiscussionPanelProps): JSX.Element {
    const { commentsOpen, onCommentsOpenChange } = props;

    return (
        <PanelBody title={__('Discussion', TEXT_DOMAIN)} initialOpen={false}>
            <ToggleControl
                label={__('Allow comments', TEXT_DOMAIN)}
                checked={commentsOpen}
                onChange={onCommentsOpenChange}
                __nextHasNoMarginBottom={true}
                data-testid="ap-visual-editor-document-comments-open"
            />
        </PanelBody>
    );
}

interface TaxonomiesPanelProps {
    categories: ReadonlyArray<number>;
    tags: ReadonlyArray<number>;
    onCategoriesChange?: (value: ReadonlyArray<number>) => void;
    onTagsChange?: (value: ReadonlyArray<number>) => void;
}

/**
 * Post-type-specific panel surfacing category + tag id selection.
 *
 * V1 ships a comma-separated id list rather than a real term-picker
 * dropdown — building the picker requires the cms-framework term REST
 * endpoints to be paginated and searchable, which is V1.1 scope. The
 * id-list contract still round-trips correctly through the WP-shape
 * `categories` / `tags` arrays in {@link PostResource}, so a host
 * that wires up its own picker on top can swap in richer UI without
 * the underlying data shape changing.
 */
function TaxonomiesPanel(props: TaxonomiesPanelProps): JSX.Element {
    const { categories, tags, onCategoriesChange, onTagsChange } = props;

    const handleCategoriesChange = useCallback(
        (raw: string): void => {
            onCategoriesChange?.(parseIdList(raw));
        },
        [onCategoriesChange]
    );

    const handleTagsChange = useCallback(
        (raw: string): void => {
            onTagsChange?.(parseIdList(raw));
        },
        [onTagsChange]
    );

    return (
        <PanelBody title={__('Categories & Tags', TEXT_DOMAIN)} initialOpen={false}>
            {onCategoriesChange !== undefined ? (
                <PanelRow>
                    <TextControl
                        label={__('Categories', TEXT_DOMAIN)}
                        help={__(
                            'Comma-separated category ids (e.g. 1, 4, 7). A richer picker lands in 1.1.',
                            TEXT_DOMAIN
                        )}
                        value={formatIdList(categories)}
                        onChange={handleCategoriesChange}
                        __nextHasNoMarginBottom={true}
                        __next40pxDefaultSize={true}
                        data-testid="ap-visual-editor-document-categories"
                    />
                </PanelRow>
            ) : null}
            {onTagsChange !== undefined ? (
                <PanelRow>
                    <TextControl
                        label={__('Tags', TEXT_DOMAIN)}
                        help={__(
                            'Comma-separated tag ids (e.g. 2, 5).',
                            TEXT_DOMAIN
                        )}
                        value={formatIdList(tags)}
                        onChange={handleTagsChange}
                        __nextHasNoMarginBottom={true}
                        __next40pxDefaultSize={true}
                        data-testid="ap-visual-editor-document-tags"
                    />
                </PanelRow>
            ) : null}
        </PanelBody>
    );
}

interface PageAttributesPanelProps {
    parent: number | null;
    menuOrder: number;
    template: string;
    onParentChange?: (value: number | null) => void;
    onMenuOrderChange?: (value: number) => void;
    onTemplateChange?: (value: string) => void;
}

/**
 * Page-only panel surfacing parent / menu_order / template fields.
 *
 * `parent` accepts a numeric page id; an empty input clears the parent
 * relationship. `menu_order` is a non-negative integer used by the host
 * to sort pages in navigation menus. `template` is a free-form theme
 * template slug — the editor doesn't enumerate available templates
 * (that needs a theme-registry integration we punt to 1.1), so the
 * input is a plain text control.
 */
function PageAttributesPanel(props: PageAttributesPanelProps): JSX.Element {
    const {
        parent,
        menuOrder,
        template,
        onParentChange,
        onMenuOrderChange,
        onTemplateChange,
    } = props;

    const handleParentChange = useCallback(
        (raw: string): void => {
            if (onParentChange === undefined) {
                return;
            }

            const trimmed = raw.trim();

            if (trimmed === '') {
                onParentChange(null);
                return;
            }

            const parsed = Number.parseInt(trimmed, 10);
            onParentChange(Number.isFinite(parsed) ? parsed : null);
        },
        [onParentChange]
    );

    const handleMenuOrderChange = useCallback(
        (raw: string): void => {
            if (onMenuOrderChange === undefined) {
                return;
            }

            const trimmed = raw.trim();
            const parsed = Number.parseInt(trimmed, 10);
            onMenuOrderChange(Number.isFinite(parsed) && parsed >= 0 ? parsed : 0);
        },
        [onMenuOrderChange]
    );

    return (
        <PanelBody title={__('Page attributes', TEXT_DOMAIN)} initialOpen={false}>
            {onParentChange !== undefined ? (
                <PanelRow>
                    <TextControl
                        label={__('Parent page', TEXT_DOMAIN)}
                        help={__('Numeric id of the parent page; leave blank for top-level.', TEXT_DOMAIN)}
                        value={parent === null ? '' : String(parent)}
                        onChange={handleParentChange}
                        __nextHasNoMarginBottom={true}
                        __next40pxDefaultSize={true}
                        data-testid="ap-visual-editor-document-parent"
                    />
                </PanelRow>
            ) : null}
            {onMenuOrderChange !== undefined ? (
                <PanelRow>
                    <TextControl
                        label={__('Order', TEXT_DOMAIN)}
                        type="number"
                        value={String(menuOrder)}
                        onChange={handleMenuOrderChange}
                        __nextHasNoMarginBottom={true}
                        __next40pxDefaultSize={true}
                        data-testid="ap-visual-editor-document-menu-order"
                    />
                </PanelRow>
            ) : null}
            {onTemplateChange !== undefined ? (
                <PanelRow>
                    <TextControl
                        label={__('Template', TEXT_DOMAIN)}
                        help={__('Theme template slug applied to this page.', TEXT_DOMAIN)}
                        value={template}
                        onChange={onTemplateChange}
                        __nextHasNoMarginBottom={true}
                        __next40pxDefaultSize={true}
                        data-testid="ap-visual-editor-document-template"
                    />
                </PanelRow>
            ) : null}
        </PanelBody>
    );
}

/**
 * Parses a user-typed comma-separated id list into a deduplicated
 * array of positive integers. Each token is validated against a
 * whole-positive-integer regex *before* parsing so malformed input
 * like `"1.5"` (truncated by `parseInt` to `1`) or `"12abc"`
 * (truncated to `12`) is rejected outright instead of silently
 * promoted into the saved id list.
 *
 * @since 1.0.0
 */
function parseIdList(raw: string): ReadonlyArray<number> {
    const ids = raw
        .split(',')
        .map((piece) => piece.trim())
        .filter((piece) => /^[1-9]\d*$/.test(piece))
        .map((piece) => Number.parseInt(piece, 10));

    return Array.from(new Set(ids));
}

/**
 * Renders a numeric id list back into the comma-separated form the
 * `TaxonomiesPanel` text inputs accept.
 *
 * @since 1.0.0
 */
function formatIdList(ids: ReadonlyArray<number>): string {
    return ids.join(', ');
}

function FilteredPanel({
    panel,
}: {
    panel: { id: string; title: string; initialOpen?: boolean; render: () => ReactNode };
}): JSX.Element {
    return (
        <PanelBody
            title={panel.title}
            initialOpen={panel.initialOpen ?? false}
        >
            <div data-panel-name={panel.id}>{panel.render()}</div>
        </PanelBody>
    );
}
