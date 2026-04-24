/**
 * Templates section wiring.
 *
 * Assembles the templates-specific pieces the shell plugs together at
 * runtime:
 *   - `TemplatesBrowser` — the navigator sub-panel list, with `All /
 *     Theme / Custom` filter chips per design brief §3.4.
 *   - `TemplateDocumentPanel` — the Document tab that drops into the
 *     reused A1 `InspectorSidebar`.
 *   - `TemplateCreateDialog` — slug picker with fallback-chain preview
 *     per P7 (template fallback chains are visible to users).
 *
 * Kept in one file so the shell only imports a single surface; the
 * symmetrical template-parts-section.tsx follows the same shape.
 */

import { __, sprintf } from '@wordpress/i18n';
import { useCallback, useId, useMemo, useState, type ChangeEvent } from 'react';

import { TEXT_DOMAIN } from '../vendor/i18n';

import { CreateEntityDialog } from './create-entity-dialog';
import { EntityBrowser, type FilterChip } from './entity-browser';
import {
    fallbackChainForSlug,
    getTemplateKindOptions,
} from './fallback-chain';
import {
    type EntityRecord,
    type SiteEditorApiConfig,
    type TemplateCreatePayload,
    type TemplateRecord,
    type ValidationErrors,
} from './api-client';
import { normalizeSlugInput } from './slug';

const CUSTOM_KIND_VALUE = '__custom__';

export interface TemplatesBrowserProps {
    apiConfig: SiteEditorApiConfig;
    activeEntityId: string | null;
    onOpen: (entityId: string) => void;
    onRequestCreate: () => void;
    refreshKey?: number;
}

function buildTemplateChips(): readonly FilterChip[] {
    return [
        { id: 'all', label: __('All', TEXT_DOMAIN), filter: {} },
        {
            id: 'publish',
            label: __('Published', TEXT_DOMAIN),
            filter: { status: 'publish' },
        },
        {
            id: 'draft',
            label: __('Drafts', TEXT_DOMAIN),
            filter: { status: 'draft' },
        },
    ];
}

export function TemplatesBrowser(props: TemplatesBrowserProps): JSX.Element {
    const { apiConfig, activeEntityId, onOpen, onRequestCreate, refreshKey } = props;
    const chips = useMemo(() => buildTemplateChips(), []);

    const renderRow = useCallback((entity: EntityRecord<'template'>): JSX.Element => {
        return (
            <>
                <code>{entity.slug}</code>
                <span>{entity.source === 'theme' ? __('Theme', TEXT_DOMAIN) : __('Custom', TEXT_DOMAIN)}</span>
                <span>{entity.status}</span>
            </>
        );
    }, []);

    const openAriaLabel = useCallback((entity: EntityRecord<'template'>): string => {
        const title = entity.title?.rendered?.trim();
        const label = title !== undefined && title !== '' ? title : entity.slug;

        return sprintf(
            /* translators: %s: template title or slug. */
            __('template: %s', TEXT_DOMAIN),
            label
        );
    }, []);

    return (
        <EntityBrowser
            apiConfig={apiConfig}
            kind="template"
            activeEntityId={activeEntityId}
            onOpen={onOpen}
            onRequestCreate={onRequestCreate}
            chips={chips}
            listLabel={__('Templates', TEXT_DOMAIN)}
            emptyTitle={__('No templates yet', TEXT_DOMAIN)}
            emptyBody={__(
                'Create a template to control how a URL or kind of content renders.',
                TEXT_DOMAIN
            )}
            createLabel={__('Add new', TEXT_DOMAIN)}
            openAriaLabel={openAriaLabel}
            renderRow={renderRow}
            refreshKey={refreshKey}
        />
    );
}

export interface TemplateDocumentPanelProps {
    entity: TemplateRecord | null;
    onPatch: (overrides: { slug?: string; title?: string; description?: string }) => void;
}

export function TemplateDocumentPanel(
    props: TemplateDocumentPanelProps
): JSX.Element {
    const { entity, onPatch } = props;
    const titleId = useId();
    const descriptionId = useId();

    if (entity === null) {
        return (
            <p
                className="ap-site-editor__inspector-empty"
                data-testid="ap-site-editor-template-inspector-empty"
            >
                {__('Open a template to edit its settings.', TEXT_DOMAIN)}
            </p>
        );
    }

    const chain = fallbackChainForSlug(entity.slug);

    return (
        <div
            className="ap-site-editor__inspector-panel"
            data-testid="ap-site-editor-template-inspector-panel"
        >
            <div className="ap-site-editor__dialog-field">
                <label
                    className="ap-site-editor__dialog-label"
                    htmlFor={titleId}
                >
                    {__('Title', TEXT_DOMAIN)}
                </label>
                <input
                    id={titleId}
                    type="text"
                    className="ap-site-editor__dialog-input"
                    value={entity.title.rendered}
                    onChange={(event) => onPatch({ title: event.target.value })}
                    data-testid="ap-site-editor-template-inspector-title"
                />
            </div>

            <div className="ap-site-editor__dialog-field">
                <span className="ap-site-editor__dialog-label">
                    {__('Slug', TEXT_DOMAIN)}
                </span>
                <code className="ap-site-editor__inspector-slug">
                    {entity.slug}
                </code>
            </div>

            <div className="ap-site-editor__dialog-field">
                <label
                    className="ap-site-editor__dialog-label"
                    htmlFor={descriptionId}
                >
                    {__('Description', TEXT_DOMAIN)}
                </label>
                <textarea
                    id={descriptionId}
                    className="ap-site-editor__dialog-textarea"
                    rows={3}
                    value={entity.description}
                    onChange={(event) => onPatch({ description: event.target.value })}
                    data-testid="ap-site-editor-template-inspector-description"
                />
            </div>

            <div className="ap-site-editor__dialog-field">
                <span className="ap-site-editor__dialog-label">
                    {__('Fallback chain', TEXT_DOMAIN)}
                </span>
                <div
                    className="ap-site-editor__fallback-chain"
                    aria-label={__('Fallback chain', TEXT_DOMAIN)}
                    data-testid="ap-site-editor-template-inspector-chain"
                >
                    {chain.map((slug, index) => (
                        <span
                            key={slug}
                            className="ap-site-editor__fallback-chain-item"
                        >
                            {slug}
                            {index < chain.length - 1 ? (
                                <span
                                    className="ap-site-editor__fallback-chain-sep"
                                    aria-hidden="true"
                                >
                                    {' ▸'}
                                </span>
                            ) : null}
                        </span>
                    ))}
                </div>
                <p className="ap-site-editor__dialog-field-help">
                    {__(
                        'If this template is empty, the next slug in the chain is rendered instead.',
                        TEXT_DOMAIN
                    )}
                </p>
            </div>

            <div className="ap-site-editor__dialog-field">
                <span className="ap-site-editor__dialog-label">
                    {__('Source', TEXT_DOMAIN)}
                </span>
                <span>
                    {entity.source === 'theme'
                        ? __('Theme', TEXT_DOMAIN)
                        : __('Custom', TEXT_DOMAIN)}
                </span>
            </div>
        </div>
    );
}

export interface TemplateCreateDialogProps {
    apiConfig: SiteEditorApiConfig;
    defaultTheme: string;
    onClose: () => void;
    onCreated: (entity: TemplateRecord) => void;
}

export function TemplateCreateDialog(
    props: TemplateCreateDialogProps
): JSX.Element {
    const { apiConfig, defaultTheme, onClose, onCreated } = props;
    const kindOptions = useMemo(() => getTemplateKindOptions(), []);

    const [kind, setKind] = useState<string>(kindOptions[0]?.slug ?? 'index');
    const [customSlug, setCustomSlug] = useState<string>('');
    const [title, setTitle] = useState<string>('');
    const [description, setDescription] = useState<string>('');
    const [theme] = useState<string>(defaultTheme);

    const activeSlug = kind === CUSTOM_KIND_VALUE ? customSlug.trim() : kind;
    const chain = useMemo(
        () => fallbackChainForSlug(activeSlug),
        [activeSlug]
    );

    const buildPayload = useCallback((): TemplateCreatePayload | null => {
        if (activeSlug === '') {
            return null;
        }

        return {
            slug: activeSlug,
            title: title.trim(),
            description: description.trim() === '' ? null : description.trim(),
            theme,
            status: 'publish',
            source: 'custom',
            content: { raw: '', blocks: [] },
        };
    }, [activeSlug, description, theme, title]);

    const renderFields = useCallback(
        (api: { validationErrors: ValidationErrors | null }): JSX.Element => {
            const slugError = api.validationErrors?.slug?.[0] ?? null;
            const titleError = api.validationErrors?.title?.[0] ?? null;

            return (
                <>
                    <div className="ap-site-editor__dialog-field">
                        <label
                            className="ap-site-editor__dialog-label"
                            htmlFor="ap-site-editor-new-template-kind"
                        >
                            {__('Template kind', TEXT_DOMAIN)}
                        </label>
                        <select
                            id="ap-site-editor-new-template-kind"
                            className="ap-site-editor__dialog-select"
                            value={kind}
                            onChange={(event: ChangeEvent<HTMLSelectElement>) =>
                                setKind(event.target.value)
                            }
                            data-testid="ap-site-editor-new-template-kind"
                        >
                            {kindOptions.map((option) => (
                                <option key={option.slug} value={option.slug}>
                                    {option.label}
                                </option>
                            ))}
                            <option value={CUSTOM_KIND_VALUE}>
                                {__('Custom slug…', TEXT_DOMAIN)}
                            </option>
                        </select>
                    </div>

                    {kind === CUSTOM_KIND_VALUE ? (
                        <div className="ap-site-editor__dialog-field">
                            <label
                                className="ap-site-editor__dialog-label"
                                htmlFor="ap-site-editor-new-template-slug"
                            >
                                {__('Custom slug', TEXT_DOMAIN)}
                            </label>
                            <input
                                id="ap-site-editor-new-template-slug"
                                type="text"
                                className="ap-site-editor__dialog-input"
                                value={customSlug}
                                onChange={(event) =>
                                    setCustomSlug(normalizeSlugInput(event.target.value))
                                }
                                placeholder={__('e.g. single-book', TEXT_DOMAIN)}
                                data-testid="ap-site-editor-new-template-slug"
                            />
                        </div>
                    ) : null}

                    {/*
                      Render slug validation errors unconditionally —
                      backend may reject a predefined kind (e.g. the
                      slug already exists for this theme) and the user
                      needs to see the error whether or not they
                      switched to the custom-slug input.
                    */}
                    {slugError !== null ? (
                        <p
                            className="ap-site-editor__dialog-field-error"
                            role="alert"
                        >
                            {slugError}
                        </p>
                    ) : null}

                    <div className="ap-site-editor__dialog-field">
                        <span className="ap-site-editor__dialog-label">
                            {__('Fallback chain', TEXT_DOMAIN)}
                        </span>
                        <div
                            className="ap-site-editor__fallback-chain"
                            data-testid="ap-site-editor-new-template-chain"
                        >
                            {chain.map((slug, index) => (
                                <span
                                    key={slug}
                                    className="ap-site-editor__fallback-chain-item"
                                >
                                    {slug}
                                    {index < chain.length - 1 ? (
                                        <span
                                            aria-hidden="true"
                                            className="ap-site-editor__fallback-chain-sep"
                                        >
                                            {' ▸'}
                                        </span>
                                    ) : null}
                                </span>
                            ))}
                        </div>
                        <p className="ap-site-editor__dialog-field-help">
                            {__(
                                'When the template above is empty, the next slug in the chain is rendered.',
                                TEXT_DOMAIN
                            )}
                        </p>
                    </div>

                    <div className="ap-site-editor__dialog-field">
                        <label
                            className="ap-site-editor__dialog-label"
                            htmlFor="ap-site-editor-new-template-title"
                        >
                            {__('Title', TEXT_DOMAIN)}
                        </label>
                        <input
                            id="ap-site-editor-new-template-title"
                            type="text"
                            className="ap-site-editor__dialog-input"
                            value={title}
                            onChange={(event) => setTitle(event.target.value)}
                            data-testid="ap-site-editor-new-template-title"
                        />
                        {titleError !== null ? (
                            <p
                                className="ap-site-editor__dialog-field-error"
                                role="alert"
                            >
                                {titleError}
                            </p>
                        ) : null}
                    </div>

                    <div className="ap-site-editor__dialog-field">
                        <label
                            className="ap-site-editor__dialog-label"
                            htmlFor="ap-site-editor-new-template-description"
                        >
                            {__('Description (optional)', TEXT_DOMAIN)}
                        </label>
                        <textarea
                            id="ap-site-editor-new-template-description"
                            className="ap-site-editor__dialog-textarea"
                            rows={2}
                            value={description}
                            onChange={(event) => setDescription(event.target.value)}
                        />
                    </div>
                </>
            );
        },
        [chain, customSlug, description, kind, kindOptions, title]
    );

    return (
        <CreateEntityDialog
            apiConfig={apiConfig}
            kind="template"
            title={__('Add new template', TEXT_DOMAIN)}
            renderFields={renderFields}
            buildPayload={buildPayload}
            onClose={onClose}
            onCreated={onCreated}
        />
    );
}
