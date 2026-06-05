/**
 * Navigation inspector.
 *
 * Two scopes per design brief §3.9:
 *   - Entity panel: name, slug, location assignment dropdown.
 *   - Item panel: link picker (type + entity / URL), label override,
 *     advanced (CSS class, rel, open in new tab).
 *
 * The shell hands us the active selection — `selectedItemId === null`
 * means the entity panel is active; otherwise we render the item panel
 * for the matching MenuItem. A separate "Locations" panel (rendered by
 * `LocationsPanel`) lives in the navigator outlet for cross-menu views.
 *
 * Field markup uses the shared `.ap-site-editor__dialog-*` classes so
 * inputs / selects / textareas match the templates and template-parts
 * inspector exactly. Nav-specific layout (the scope header, the
 * link-picker results list) lives in `navigation-inspector.css`.
 */

import { __, sprintf } from '@wordpress/i18n';
import { useId, useMemo } from 'react';

import { TEXT_DOMAIN } from '../../vendor/i18n';

import type { SiteEditorApiConfig, ValidationErrors } from '../api-client';
import { LinkPicker } from './link-picker';
import {
    findMenuItem,
    replaceMenuItem,
    type MenuItem,
} from './menu-tree';
import type { MenuLocation } from './api-client';
import type { NavigationEntityFields } from './use-navigation-editor';

import './navigation-inspector.css';

export interface NavigationInspectorProps {
    apiConfig: SiteEditorApiConfig;
    fields: NavigationEntityFields;
    onFieldsChange: (
        update: Partial<NavigationEntityFields>
    ) => void;
    tree: readonly MenuItem[];
    onTreeChange: (next: readonly MenuItem[]) => void;
    selectedItemId: string | null;
    onSelectItem: (localId: string | null) => void;
    locations: readonly MenuLocation[];
    isLocationsLoading: boolean;
    locationsError: string | null;
    validationErrors: ValidationErrors | null;
    saveStatus: 'idle' | 'saving' | 'saved' | 'error';
    saveErrorMessage: string | null;
}

export function NavigationInspector(
    props: NavigationInspectorProps
): JSX.Element {
    const {
        apiConfig,
        fields,
        onFieldsChange,
        tree,
        onTreeChange,
        selectedItemId,
        onSelectItem,
        locations,
        isLocationsLoading,
        locationsError,
        validationErrors,
        saveErrorMessage,
        saveStatus,
    } = props;

    const selectedItem = useMemo(
        () =>
            selectedItemId === null ? null : findMenuItem(tree, selectedItemId),
        [selectedItemId, tree]
    );

    const handleItemPatch = (patch: Partial<MenuItem>): void => {
        if (selectedItem === null) {
            return;
        }

        onTreeChange(
            replaceMenuItem(tree, selectedItem.localId, (item) => ({
                ...item,
                ...patch,
            }))
        );
    };

    return (
        <aside
            className="ap-nav-inspector"
            data-testid="ap-nav-inspector"
            data-scope={selectedItem === null ? 'entity' : 'item'}
            aria-label={__('Navigation inspector', TEXT_DOMAIN)}
        >
            <header className="ap-nav-inspector__header">
                <span className="ap-nav-inspector__scope">
                    {selectedItem === null
                        ? __('Menu', TEXT_DOMAIN)
                        : __('Menu item', TEXT_DOMAIN)}
                </span>
                {selectedItem !== null ? (
                    <button
                        type="button"
                        className="ap-nav-inspector__back"
                        onClick={() => onSelectItem(null)}
                        data-testid="ap-nav-inspector-back"
                    >
                        {__('← Menu settings', TEXT_DOMAIN)}
                    </button>
                ) : null}
            </header>

            {saveStatus === 'error' && saveErrorMessage !== null ? (
                <p
                    className="ap-site-editor__dialog-error"
                    role="alert"
                >
                    {saveErrorMessage}
                </p>
            ) : null}

            {selectedItem === null ? (
                <EntityPanel
                    fields={fields}
                    onFieldsChange={onFieldsChange}
                    locations={locations}
                    isLocationsLoading={isLocationsLoading}
                    locationsError={locationsError}
                    validationErrors={validationErrors}
                />
            ) : (
                <ItemPanel
                    apiConfig={apiConfig}
                    item={selectedItem}
                    onPatch={handleItemPatch}
                />
            )}
        </aside>
    );
}

interface EntityPanelProps {
    fields: NavigationEntityFields;
    onFieldsChange: (update: Partial<NavigationEntityFields>) => void;
    locations: readonly MenuLocation[];
    isLocationsLoading: boolean;
    locationsError: string | null;
    validationErrors: ValidationErrors | null;
}

function EntityPanel(props: EntityPanelProps): JSX.Element {
    const {
        fields,
        onFieldsChange,
        locations,
        isLocationsLoading,
        locationsError,
        validationErrors,
    } = props;

    const titleId = useId();
    const slugId = useId();
    const locationId = useId();
    const titleErrorId = useId();
    const slugErrorId = useId();
    const locationErrorId = useId();
    const locationsErrorId = useId();

    const titleError = validationErrors?.title?.[0];
    const slugError = validationErrors?.slug?.[0];
    const locationError = validationErrors?.location?.[0];

    return (
        <div className="ap-site-editor__inspector-panel">
            <div className="ap-site-editor__dialog-field">
                <label
                    className="ap-site-editor__dialog-label"
                    htmlFor={titleId}
                >
                    {__('Name', TEXT_DOMAIN)}
                </label>
                <input
                    id={titleId}
                    type="text"
                    className="ap-site-editor__dialog-input"
                    value={fields.title}
                    onChange={(event) =>
                        onFieldsChange({ title: event.target.value })
                    }
                    data-testid="ap-nav-inspector-title"
                    aria-invalid={Boolean(titleError) || undefined}
                    aria-describedby={
                        titleError !== undefined ? titleErrorId : undefined
                    }
                />
                {titleError !== undefined ? (
                    <p
                        id={titleErrorId}
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
                    htmlFor={slugId}
                >
                    {__('Slug', TEXT_DOMAIN)}
                </label>
                <input
                    id={slugId}
                    type="text"
                    className="ap-site-editor__dialog-input"
                    value={fields.slug}
                    onChange={(event) =>
                        onFieldsChange({ slug: event.target.value })
                    }
                    data-testid="ap-nav-inspector-slug"
                    aria-invalid={Boolean(slugError) || undefined}
                    aria-describedby={
                        slugError !== undefined ? slugErrorId : undefined
                    }
                />
                {slugError !== undefined ? (
                    <p
                        id={slugErrorId}
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
                    htmlFor={locationId}
                >
                    {__('Location', TEXT_DOMAIN)}
                </label>
                <select
                    id={locationId}
                    className="ap-site-editor__dialog-select"
                    value={fields.location ?? ''}
                    onChange={(event) =>
                        onFieldsChange({
                            location:
                                event.target.value === ''
                                    ? null
                                    : event.target.value,
                        })
                    }
                    disabled={isLocationsLoading}
                    data-testid="ap-nav-inspector-location"
                    aria-invalid={
                        Boolean(locationError) || Boolean(locationsError) ||
                            undefined
                    }
                    aria-describedby={
                        locationError !== undefined
                            ? locationErrorId
                            : locationsError !== null
                                ? locationsErrorId
                                : undefined
                    }
                >
                    <option value="">
                        {__('— No location —', TEXT_DOMAIN)}
                    </option>
                    {locations.map((location) => (
                        <option
                            key={location.slug}
                            value={location.slug}
                        >
                            {location.label}
                        </option>
                    ))}
                </select>
                {locationsError !== null ? (
                    <p
                        id={locationsErrorId}
                        className="ap-site-editor__dialog-field-error"
                        role="alert"
                    >
                        {locationsError}
                    </p>
                ) : null}
                {locationError !== undefined ? (
                    <p
                        id={locationErrorId}
                        className="ap-site-editor__dialog-field-error"
                        role="alert"
                    >
                        {locationError}
                    </p>
                ) : null}
                <p className="ap-site-editor__dialog-field-help">
                    {__(
                        'Locations are declared in `config/artisanpack/visual-editor.php`. Assigning here writes to this menu only.',
                        TEXT_DOMAIN
                    )}
                </p>
            </div>
        </div>
    );
}

interface ItemPanelProps {
    apiConfig: SiteEditorApiConfig;
    item: MenuItem;
    onPatch: (patch: Partial<MenuItem>) => void;
}

function ItemPanel(props: ItemPanelProps): JSX.Element {
    const { apiConfig, item, onPatch } = props;
    const labelId = useId();
    const classId = useId();
    const relId = useId();
    const newTabId = useId();

    const placeholderHelp = sprintf(
        /* translators: %s: auto-derived label. */
        __('Defaults to: %s', TEXT_DOMAIN),
        item.autoLabel === '' ? __('(no label)', TEXT_DOMAIN) : item.autoLabel
    );

    return (
        <div className="ap-site-editor__inspector-panel">
            <LinkPicker apiConfig={apiConfig} item={item} onChange={onPatch} />

            <div className="ap-site-editor__dialog-field">
                <label
                    className="ap-site-editor__dialog-label"
                    htmlFor={labelId}
                >
                    {__('Label', TEXT_DOMAIN)}
                </label>
                <input
                    id={labelId}
                    type="text"
                    className="ap-site-editor__dialog-input"
                    value={item.labelOverride ?? ''}
                    placeholder={item.autoLabel}
                    onChange={(event) =>
                        onPatch({
                            labelOverride:
                                event.target.value === ''
                                    ? null
                                    : event.target.value,
                        })
                    }
                    data-testid="ap-nav-inspector-label"
                />
                <p className="ap-site-editor__dialog-field-help">
                    {placeholderHelp}
                </p>
            </div>

            <div className="ap-site-editor__dialog-field">
                <label className="ap-nav-inspector__field-checkbox">
                    <input
                        id={newTabId}
                        type="checkbox"
                        checked={item.opensInNewTab}
                        onChange={(event) =>
                            onPatch({ opensInNewTab: event.target.checked })
                        }
                        data-testid="ap-nav-inspector-new-tab"
                    />
                    {__('Open in new tab', TEXT_DOMAIN)}
                </label>
            </div>

            <details className="ap-nav-inspector__advanced">
                <summary>{__('Advanced', TEXT_DOMAIN)}</summary>

                <div className="ap-site-editor__dialog-field">
                    <label
                        className="ap-site-editor__dialog-label"
                        htmlFor={classId}
                    >
                        {__('CSS class', TEXT_DOMAIN)}
                    </label>
                    <input
                        id={classId}
                        type="text"
                        className="ap-site-editor__dialog-input"
                        value={item.className ?? ''}
                        onChange={(event) =>
                            onPatch({
                                className:
                                    event.target.value === ''
                                        ? null
                                        : event.target.value,
                            })
                        }
                        data-testid="ap-nav-inspector-class"
                    />
                </div>

                <div className="ap-site-editor__dialog-field">
                    <label
                        className="ap-site-editor__dialog-label"
                        htmlFor={relId}
                    >
                        {__('Rel', TEXT_DOMAIN)}
                    </label>
                    <input
                        id={relId}
                        type="text"
                        className="ap-site-editor__dialog-input"
                        value={item.rel ?? ''}
                        onChange={(event) =>
                            onPatch({
                                rel:
                                    event.target.value === ''
                                        ? null
                                        : event.target.value,
                            })
                        }
                        data-testid="ap-nav-inspector-rel"
                    />
                </div>
            </details>
        </div>
    );
}
