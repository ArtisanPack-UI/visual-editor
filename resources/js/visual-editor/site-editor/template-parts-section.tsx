/**
 * Template-parts section wiring.
 *
 * Mirror of `templates-section.tsx` scoped to `wp_template_part`. The
 * browser surfaces area (header / footer / sidebar / uncategorized) as
 * both a filter chip and a per-row meta badge — design brief §3.5 calls
 * out typed areas as a first-class concept so the UX needs to make them
 * visible at both the filter and the row levels.
 *
 * The create dialog requires an area selection up-front: a part with no
 * area can't be swapped into anything at render time, and the C2 request
 * validation rejects a blank area anyway. We surface the set from
 * {@link VisualEditorTemplatePart::AREAS} — adding a new area in PHP
 * means adding it here as well.
 */

import { __, sprintf } from '@wordpress/i18n';
import { useCallback, useMemo, useState, type ChangeEvent } from 'react';

import { TEXT_DOMAIN } from '../vendor/i18n';

import { CreateEntityDialog } from './create-entity-dialog';
import { EntityBrowser, type FilterChip } from './entity-browser';
import {
    type EntityRecord,
    type SiteEditorApiConfig,
    type TemplatePartCreatePayload,
    type TemplatePartRecord,
    type ValidationErrors,
} from './api-client';
import { normalizeSlugInput } from './slug';

export interface TemplatePartArea {
    slug: string;
    label: string;
}

/**
 * Returns the template-part area options for the area picker / filter
 * chips. A function (not a top-level constant) so the `__()` calls run
 * after `bootI18n()` has initialized the text domain — matches the
 * same pattern as the section registry in `sections.tsx`.
 */
export function getTemplatePartAreas(): readonly TemplatePartArea[] {
    return [
        { slug: 'header', label: __('Header', TEXT_DOMAIN) },
        { slug: 'footer', label: __('Footer', TEXT_DOMAIN) },
        { slug: 'sidebar', label: __('Sidebar', TEXT_DOMAIN) },
        { slug: 'uncategorized', label: __('Uncategorized', TEXT_DOMAIN) },
    ];
}

export interface TemplatePartsBrowserProps {
    apiConfig: SiteEditorApiConfig;
    activeEntityId: string | null;
    onOpen: (entityId: string) => void;
    onRequestCreate: () => void;
    refreshKey?: number;
}

function buildPartChips(): readonly FilterChip[] {
    return [
        { id: 'all', label: __('All areas', TEXT_DOMAIN), filter: {} },
        ...getTemplatePartAreas().map((area) => ({
            id: area.slug,
            label: area.label,
            filter: { area: area.slug },
        })),
    ];
}

export function TemplatePartsBrowser(
    props: TemplatePartsBrowserProps
): JSX.Element {
    const { apiConfig, activeEntityId, onOpen, onRequestCreate, refreshKey } = props;
    const chips = useMemo(() => buildPartChips(), []);

    // Index areas by slug so the row renderer can swap the raw machine
    // slug for its localized label without walking the list on every
    // render.
    const areaLabelBySlug = useMemo(() => {
        const map: Record<string, string> = {};
        for (const area of getTemplatePartAreas()) {
            map[area.slug] = area.label;
        }

        return map;
    }, []);

    const renderRow = useCallback(
        (entity: EntityRecord<'template-part'>): JSX.Element => (
            <>
                <code>{entity.slug}</code>
                <span data-testid={`ap-site-editor-template-part-area-${entity.id}`}>
                    {areaLabelBySlug[entity.area] ?? entity.area}
                </span>
            </>
        ),
        [areaLabelBySlug]
    );

    const openAriaLabel = useCallback(
        (entity: EntityRecord<'template-part'>): string => {
            const title = entity.title?.rendered?.trim();
            const label = title !== undefined && title !== '' ? title : entity.slug;

            return sprintf(
                /* translators: %s: template-part title or slug. */
                __('template part: %s', TEXT_DOMAIN),
                label
            );
        },
        []
    );

    return (
        <EntityBrowser
            apiConfig={apiConfig}
            kind="template-part"
            activeEntityId={activeEntityId}
            onOpen={onOpen}
            onRequestCreate={onRequestCreate}
            chips={chips}
            listLabel={__('Template Parts', TEXT_DOMAIN)}
            emptyTitle={__('No template parts yet', TEXT_DOMAIN)}
            emptyBody={__(
                'Create a header, footer, sidebar, or other reusable region.',
                TEXT_DOMAIN
            )}
            createLabel={__('Add new', TEXT_DOMAIN)}
            openAriaLabel={openAriaLabel}
            renderRow={renderRow}
            refreshKey={refreshKey}
        />
    );
}

export interface TemplatePartDocumentPanelProps {
    entity: TemplatePartRecord | null;
    onPatch: (overrides: { slug?: string; title?: string; area?: string }) => void;
}

export function TemplatePartDocumentPanel(
    props: TemplatePartDocumentPanelProps
): JSX.Element {
    const { entity, onPatch } = props;

    if (entity === null) {
        return (
            <p
                className="ap-site-editor__inspector-empty"
                data-testid="ap-site-editor-template-part-inspector-empty"
            >
                {__('Open a template part to edit its settings.', TEXT_DOMAIN)}
            </p>
        );
    }

    return (
        <div
            className="ap-site-editor__inspector-panel"
            data-testid="ap-site-editor-template-part-inspector-panel"
        >
            <div className="ap-site-editor__dialog-field">
                <label
                    className="ap-site-editor__dialog-label"
                    htmlFor="ap-site-editor-template-part-title"
                >
                    {__('Title', TEXT_DOMAIN)}
                </label>
                <input
                    id="ap-site-editor-template-part-title"
                    type="text"
                    className="ap-site-editor__dialog-input"
                    value={entity.title.rendered}
                    onChange={(event) => onPatch({ title: event.target.value })}
                    data-testid="ap-site-editor-template-part-inspector-title"
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
                    htmlFor="ap-site-editor-template-part-area"
                >
                    {__('Area', TEXT_DOMAIN)}
                </label>
                <select
                    id="ap-site-editor-template-part-area"
                    className="ap-site-editor__dialog-select"
                    value={entity.area}
                    onChange={(event) => onPatch({ area: event.target.value })}
                    data-testid="ap-site-editor-template-part-inspector-area"
                >
                    {getTemplatePartAreas().map((area) => (
                        <option key={area.slug} value={area.slug}>
                            {area.label}
                        </option>
                    ))}
                </select>
                <p className="ap-site-editor__dialog-field-help">
                    {__(
                        'Determines which template slots this part can fill.',
                        TEXT_DOMAIN
                    )}
                </p>
            </div>

            {entity.referenced_by !== undefined && entity.referenced_by.length > 0 ? (
                <div className="ap-site-editor__dialog-field">
                    <span className="ap-site-editor__dialog-label">
                        {__('Used by', TEXT_DOMAIN)}
                    </span>
                    <ul
                        className="ap-site-editor__inspector-refs"
                        data-testid="ap-site-editor-template-part-inspector-refs"
                    >
                        {entity.referenced_by.map((slug) => (
                            <li key={slug}>
                                <code>{slug}</code>
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}
        </div>
    );
}

export interface TemplatePartCreateDialogProps {
    apiConfig: SiteEditorApiConfig;
    defaultTheme: string;
    onClose: () => void;
    onCreated: (entity: TemplatePartRecord) => void;
}

export function TemplatePartCreateDialog(
    props: TemplatePartCreateDialogProps
): JSX.Element {
    const { apiConfig, defaultTheme, onClose, onCreated } = props;

    const areaOptions = useMemo(() => getTemplatePartAreas(), []);
    const [slug, setSlug] = useState<string>('');
    const [title, setTitle] = useState<string>('');
    const [area, setArea] = useState<string>(areaOptions[0]?.slug ?? 'header');

    const buildPayload = useCallback((): TemplatePartCreatePayload | null => {
        if (slug.trim() === '') {
            return null;
        }

        return {
            slug: slug.trim(),
            title: title.trim(),
            area,
            theme: defaultTheme,
            content: { raw: '', blocks: [] },
        };
    }, [area, defaultTheme, slug, title]);

    const renderFields = useCallback(
        (api: { validationErrors: ValidationErrors | null }): JSX.Element => {
            const slugError = api.validationErrors?.slug?.[0] ?? null;
            const areaError = api.validationErrors?.area?.[0] ?? null;

            return (
                <>
                    <div className="ap-site-editor__dialog-field">
                        <label
                            className="ap-site-editor__dialog-label"
                            htmlFor="ap-site-editor-new-part-slug"
                        >
                            {__('Slug', TEXT_DOMAIN)}
                        </label>
                        <input
                            id="ap-site-editor-new-part-slug"
                            type="text"
                            className="ap-site-editor__dialog-input"
                            value={slug}
                            onChange={(event) =>
                                setSlug(normalizeSlugInput(event.target.value))
                            }
                            placeholder={__('e.g. site-header', TEXT_DOMAIN)}
                            data-testid="ap-site-editor-new-part-slug"
                            required
                        />
                        {slugError !== null ? (
                            <p
                                className="ap-site-editor__dialog-field-error"
                                role="alert"
                            >
                                {slugError}
                            </p>
                        ) : null}
                    </div>

                    <div className="ap-site-editor__dialog-field">
                        <label
                            className="ap-site-editor__dialog-label"
                            htmlFor="ap-site-editor-new-part-title"
                        >
                            {__('Title', TEXT_DOMAIN)}
                        </label>
                        <input
                            id="ap-site-editor-new-part-title"
                            type="text"
                            className="ap-site-editor__dialog-input"
                            value={title}
                            onChange={(event) => setTitle(event.target.value)}
                            data-testid="ap-site-editor-new-part-title"
                        />
                    </div>

                    <div className="ap-site-editor__dialog-field">
                        <label
                            className="ap-site-editor__dialog-label"
                            htmlFor="ap-site-editor-new-part-area"
                        >
                            {__('Area', TEXT_DOMAIN)}
                        </label>
                        <select
                            id="ap-site-editor-new-part-area"
                            className="ap-site-editor__dialog-select"
                            value={area}
                            onChange={(event: ChangeEvent<HTMLSelectElement>) =>
                                setArea(event.target.value)
                            }
                            data-testid="ap-site-editor-new-part-area"
                        >
                            {areaOptions.map((option) => (
                                <option key={option.slug} value={option.slug}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        {areaError !== null ? (
                            <p
                                className="ap-site-editor__dialog-field-error"
                                role="alert"
                            >
                                {areaError}
                            </p>
                        ) : null}
                    </div>
                </>
            );
        },
        [area, areaOptions, slug, title]
    );

    return (
        <CreateEntityDialog
            apiConfig={apiConfig}
            kind="template-part"
            title={__('Add new template part', TEXT_DOMAIN)}
            renderFields={renderFields}
            buildPayload={buildPayload}
            onClose={onClose}
            onCreated={onCreated}
        />
    );
}
