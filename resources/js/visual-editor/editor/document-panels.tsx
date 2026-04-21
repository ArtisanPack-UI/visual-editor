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
import { useCallback, useMemo } from 'react';
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
    } = props;

    const showExcerpt = supports?.excerpt !== false && onExcerptChange !== undefined;
    const showFeaturedImage = supports?.featuredImage !== false;
    const showComments = supports?.comments === true && onCommentsOpenChange !== undefined;

    const filteredPanels = useMemo(() => getFilteredDocumentPanels(), []);

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
