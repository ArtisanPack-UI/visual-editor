/**
 * Business Address — editor-side WYSIWYG preview (#761).
 *
 * Renders the real address + map iframe from the host's
 * `ap.visualEditor.businessInfo` filter so the canvas matches the
 * public front end. Resolution priority:
 *
 *   1. `_resolvedBusinessInfo` attribute (front-end / saved-tree path).
 *   2. Envelope fetched from `/visual-editor/api/business-info`
 *      (editor path, shared across all business-* blocks on the page).
 *   3. Hardcoded placeholder chip so a block placed before the filter
 *      is wired up still looks intentional in the canvas.
 *
 * The iframe reuses the resolver's composed `mapEmbedUrl` verbatim —
 * OSM by default, Google when the host has configured
 * `artisanpack.visual-editor.business.google_maps_api_key`.
 */

import type { ReactElement } from 'react';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
    PanelBody,
    RangeControl,
    SelectControl,
    ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { safeIframeUrl } from '../_shared/safe-url';
import {
    useBusinessInfo,
    type BusinessInfoAddress,
    type BusinessInfoEnvelope,
} from '../_shared/use-business-info';

type MapProvider = 'osm' | 'google' | 'none';

const MAP_PROVIDERS: ReadonlyArray<MapProvider> = ['osm', 'google', 'none'];

interface BusinessAddressAttributes {
    readonly mapProvider: MapProvider;
    readonly showMap: boolean;
    readonly zoom: number;
    readonly _resolvedBusinessInfo?: BusinessInfoEnvelope;
}

interface BusinessAddressEditProps {
    readonly attributes: BusinessAddressAttributes;
    readonly setAttributes: (next: Partial<BusinessAddressAttributes>) => void;
}

function isMapProvider(value: string): value is MapProvider {
    return (MAP_PROVIDERS as ReadonlyArray<string>).includes(value);
}

function hasAddressContent(address: BusinessInfoAddress | undefined): boolean {
    if (!address) {
        return false;
    }
    return (
        '' !== (address.street ?? '') ||
        '' !== (address.street2 ?? '') ||
        '' !== (address.city ?? '') ||
        '' !== (address.region ?? '') ||
        '' !== (address.postal_code ?? '') ||
        '' !== (address.country ?? '')
    );
}

function formatCityLine(address: BusinessInfoAddress): string {
    const city   = address.city ?? '';
    const region = address.region ?? '';
    const postal = address.postal_code ?? '';

    const cityAndRegion =
        '' !== region ? ('' !== city ? `${city}, ${region}` : region) : city;

    return '' !== postal ? `${cityAndRegion} ${postal}`.trim() : cityAndRegion;
}

function renderAddress(address: BusinessInfoAddress): ReactElement {
    const cityLine = formatCityLine(address);
    return (
        <address className="ap-business-address__address">
            {'' !== (address.street ?? '') && <>{address.street}</>}
            {'' !== (address.street2 ?? '') && (
                <>
                    <br />
                    {address.street2}
                </>
            )}
            {'' !== cityLine && (
                <>
                    <br />
                    {cityLine}
                </>
            )}
            {'' !== (address.country ?? '') && (
                <>
                    <br />
                    {address.country}
                </>
            )}
        </address>
    );
}

function renderPlaceholder(showMap: boolean, mapProvider: MapProvider): ReactElement {
    return (
        <>
            <p className="ap-business-address__hint">
                <em>
                    {__(
                        'Business address (preview) — populate through the ap.visualEditor.businessInfo filter.',
                        TEXT_DOMAIN
                    )}
                </em>
            </p>
            <address className="ap-business-address__address">
                123 Example Street
                <br />
                Suite 100
                <br />
                Springfield, IL 62701
                <br />
                United States
            </address>
            {showMap && 'none' !== mapProvider && (
                <div
                    className="ap-business-address__map-placeholder"
                    aria-hidden="true"
                >
                    {__('Map preview shown on the public site.', TEXT_DOMAIN)}
                </div>
            )}
        </>
    );
}

export default function BusinessAddressEdit({
    attributes,
    setAttributes,
}: BusinessAddressEditProps): ReactElement {
    const { mapProvider, showMap, zoom } = attributes;

    const blockProps = useBlockProps({ className: 'ap-business-address' });

    const stamped = attributes._resolvedBusinessInfo;
    const { envelope: fetched } = useBusinessInfo({
        mapProvider,
        showMap,
        zoom,
    });

    const envelope: BusinessInfoEnvelope | null = stamped ?? fetched;
    const address = envelope?.address;
    const showAddress = hasAddressContent(address);

    const mapEmbedUrl =
        showMap && 'none' !== mapProvider
            ? typeof envelope?.mapEmbedUrl === 'string' && '' !== envelope.mapEmbedUrl
                ? safeIframeUrl(envelope.mapEmbedUrl) || null
                : null
            : null;

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Business address settings', TEXT_DOMAIN)} initialOpen>
                    <ToggleControl
                        label={__('Show map embed', TEXT_DOMAIN)}
                        checked={showMap}
                        onChange={(next) => setAttributes({ showMap: next })}
                        __nextHasNoMarginBottom
                    />
                    <SelectControl
                        label={__('Map provider', TEXT_DOMAIN)}
                        help={__(
                            'Google Maps is used only when a Maps API key is configured on the host; otherwise the block silently falls back to OpenStreetMap.',
                            TEXT_DOMAIN
                        )}
                        value={mapProvider}
                        options={[
                            { label: __('OpenStreetMap', TEXT_DOMAIN), value: 'osm' },
                            { label: __('Google Maps', TEXT_DOMAIN), value: 'google' },
                            { label: __('None', TEXT_DOMAIN), value: 'none' },
                        ]}
                        onChange={(value) => {
                            if (isMapProvider(value)) {
                                setAttributes({ mapProvider: value });
                            }
                        }}
                        __nextHasNoMarginBottom
                    />
                    <RangeControl
                        label={__('Map zoom level', TEXT_DOMAIN)}
                        value={zoom}
                        min={1}
                        max={20}
                        onChange={(next) =>
                            setAttributes({ zoom: typeof next === 'number' ? next : 15 })
                        }
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                {showAddress && address ? (
                    <>
                        {renderAddress(address)}
                        {null !== mapEmbedUrl && (
                            <div className="ap-business-address__map">
                                <iframe
                                    src={mapEmbedUrl}
                                    title={__('Map', TEXT_DOMAIN)}
                                    loading="lazy"
                                    referrerPolicy="no-referrer"
                                    sandbox="allow-scripts allow-same-origin allow-popups"
                                    style={{
                                        border: 0,
                                        width: '100%',
                                        height: '100%',
                                        minHeight: '300px',
                                    }}
                                    allowFullScreen
                                />
                            </div>
                        )}
                    </>
                ) : (
                    renderPlaceholder(showMap, mapProvider)
                )}
            </div>
        </>
    );
}
