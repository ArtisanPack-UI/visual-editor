/**
 * Menu locations panel.
 *
 * Renders the configured menu locations and the menu currently
 * resolved for each (per `MenuLocationsController::index`). Each row
 * lets the user pick a different menu — that selection writes the
 * `location` field on the chosen menu's record (single-occupant
 * semantics — the controller releases the slug from any prior owner).
 *
 * Sits in the navigator outlet alongside the menu list. The panel is
 * read-mostly: assignments rarely change, but when they do the user
 * shouldn't have to navigate into a specific menu's inspector to make
 * the change.
 */

import { __, sprintf } from '@wordpress/i18n';
import { useState } from 'react';

import { TEXT_DOMAIN } from '../../vendor/i18n';

import type {
    MenuLocation,
    NavigationRecord,
} from './api-client';

export interface LocationsPanelProps {
    locations: readonly MenuLocation[];
    isLoading: boolean;
    errorMessage: string | null;
    navigations: readonly NavigationRecord[];
    onAssign: (
        locationSlug: string,
        navigationId: number | null
    ) => Promise<void>;
}

export function LocationsPanel(props: LocationsPanelProps): JSX.Element {
    const { locations, isLoading, errorMessage, navigations, onAssign } =
        props;

    const [pendingSlug, setPendingSlug] = useState<string | null>(null);

    if (errorMessage !== null) {
        return (
            <section
                className="ap-locations-panel"
                data-testid="ap-locations-panel"
                aria-label={__('Menu locations', TEXT_DOMAIN)}
            >
                <h3 className="ap-locations-panel__title">
                    {__('Locations', TEXT_DOMAIN)}
                </h3>
                <p className="ap-locations-panel__hint" role="alert">
                    {errorMessage}
                </p>
            </section>
        );
    }

    if (isLoading) {
        return (
            <section
                className="ap-locations-panel"
                data-testid="ap-locations-panel"
                aria-label={__('Menu locations', TEXT_DOMAIN)}
                aria-busy="true"
            >
                <h3 className="ap-locations-panel__title">
                    {__('Locations', TEXT_DOMAIN)}
                </h3>
                <p className="ap-locations-panel__hint">
                    {__('Loading locations…', TEXT_DOMAIN)}
                </p>
            </section>
        );
    }

    if (locations.length === 0) {
        return (
            <section
                className="ap-locations-panel"
                data-testid="ap-locations-panel"
                aria-label={__('Menu locations', TEXT_DOMAIN)}
            >
                <h3 className="ap-locations-panel__title">
                    {__('Locations', TEXT_DOMAIN)}
                </h3>
                <p className="ap-locations-panel__empty">
                    {__(
                        'No menu locations configured. Declare them in `config/artisanpack/visual-editor.php` under `navigation.locations`.',
                        TEXT_DOMAIN
                    )}
                </p>
            </section>
        );
    }

    const handleSelectChange = async (
        locationSlug: string,
        rawValue: string
    ): Promise<void> => {
        const next = rawValue === '' ? null : Number(rawValue);

        setPendingSlug(locationSlug);

        try {
            await onAssign(locationSlug, Number.isFinite(next) ? next : null);
        } finally {
            setPendingSlug(null);
        }
    };

    return (
        <section
            className="ap-locations-panel"
            data-testid="ap-locations-panel"
            aria-label={__('Menu locations', TEXT_DOMAIN)}
        >
            <h3 className="ap-locations-panel__title">
                {__('Locations', TEXT_DOMAIN)}
            </h3>
            {locations.map((location) => {
                const selectedId = location.menu?.id ?? '';

                return (
                    <div
                        key={location.slug}
                        className="ap-locations-panel__row"
                        data-testid={`ap-locations-row-${location.slug}`}
                    >
                        <span className="ap-locations-panel__label">
                            {location.label}
                        </span>
                        <select
                            className="ap-locations-panel__select"
                            value={selectedId}
                            onChange={(event) =>
                                void handleSelectChange(
                                    location.slug,
                                    event.target.value
                                )
                            }
                            disabled={pendingSlug === location.slug}
                            data-testid={`ap-locations-select-${location.slug}`}
                            aria-label={sprintf(
                                /* translators: %s: location label. */
                                __('Menu for %s', TEXT_DOMAIN),
                                location.label
                            )}
                        >
                            <option value="">
                                {__('— None —', TEXT_DOMAIN)}
                            </option>
                            {navigations.map((row) => (
                                <option key={row.id} value={row.id}>
                                    {row.title.rendered === ''
                                        ? row.slug
                                        : row.title.rendered}
                                </option>
                            ))}
                        </select>
                        {location.is_fallback && location.menu !== null ? (
                            <p
                                className="ap-locations-panel__hint"
                                data-testid={`ap-locations-fallback-${location.slug}`}
                            >
                                {sprintf(
                                    /* translators: %s: menu title. */
                                    __(
                                        'Falling back to "%s" because no menu is assigned.',
                                        TEXT_DOMAIN
                                    ),
                                    location.menu.title === ''
                                        ? location.menu.slug
                                        : location.menu.title
                                )}
                            </p>
                        ) : null}
                    </div>
                );
            })}
        </section>
    );
}
