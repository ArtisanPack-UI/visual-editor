/**
 * Business Hours — editor-side WYSIWYG preview (#761).
 *
 * Renders the real weekly hours + upcoming special-hours entries from
 * the host's `ap.visualEditor.businessInfo` filter. Resolution
 * priority mirrors the front-end resolver:
 *
 *   1. `_resolvedBusinessInfo` attribute (front-end / saved-tree path).
 *   2. Envelope fetched from `/visual-editor/api/business-info`
 *      (editor path, shared across all business-* blocks on the page).
 *   3. Hardcoded weekly stub so a block placed before the filter is
 *      wired up still looks intentional in the canvas.
 */

import type { ReactElement } from 'react';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import {
    useBusinessInfo,
    type BusinessInfoEnvelope,
    type BusinessInfoSpecialHours,
} from '../_shared/use-business-info';

interface BusinessHoursAttributes {
    readonly showSpecialHours: boolean;
    readonly specialHoursWindowDays: number;
    readonly _resolvedBusinessInfo?: BusinessInfoEnvelope;
}

interface BusinessHoursEditProps {
    readonly attributes: BusinessHoursAttributes;
    readonly setAttributes: (next: Partial<BusinessHoursAttributes>) => void;
}

const DAY_ORDER: ReadonlyArray<string> = [
    'monday',
    'tuesday',
    'wednesday',
    'thursday',
    'friday',
    'saturday',
    'sunday',
];

const DAY_LABELS: Readonly<Record<string, string>> = {
    monday: 'Monday',
    tuesday: 'Tuesday',
    wednesday: 'Wednesday',
    thursday: 'Thursday',
    friday: 'Friday',
    saturday: 'Saturday',
    sunday: 'Sunday',
};

const STUB_DAYS: ReadonlyArray<{ readonly label: string; readonly hours: string }> = [
    { label: 'Monday', hours: '9:00 AM – 5:00 PM' },
    { label: 'Tuesday', hours: '9:00 AM – 5:00 PM' },
    { label: 'Wednesday', hours: '9:00 AM – 5:00 PM' },
    { label: 'Thursday', hours: '9:00 AM – 5:00 PM' },
    { label: 'Friday', hours: '9:00 AM – 5:00 PM' },
    { label: 'Saturday', hours: '10:00 AM – 2:00 PM' },
    { label: 'Sunday', hours: 'Closed' },
];

/**
 * Format a single day's hours entry. Mirrors the Blade partial:
 *   - `closed: true`               → "Closed"
 *   - `[{open, close}, ...]`       → "9:00 – 12:00, 13:00 – 17:00"
 *   - `{open, close}`              → "9:00 – 17:00"
 */
function formatHours(entry: unknown): string {
    if (!entry || typeof entry !== 'object') {
        return '';
    }

    const record = entry as Record<string, unknown>;

    if (true === record.closed) {
        return __('Closed', TEXT_DOMAIN);
    }

    if (Array.isArray(entry)) {
        const parts: string[] = [];
        for (const range of entry) {
            if (range && typeof range === 'object') {
                const r = range as Record<string, unknown>;
                if (typeof r.open === 'string' && typeof r.close === 'string') {
                    parts.push(`${r.open} – ${r.close}`);
                }
            }
        }
        return parts.join(', ');
    }

    if (typeof record.open === 'string' && typeof record.close === 'string') {
        return `${record.open} – ${record.close}`;
    }

    return '';
}

function formatSpecialHoursTime(entry: BusinessInfoSpecialHours): string {
    if (true === entry.closed) {
        return __('Closed', TEXT_DOMAIN);
    }

    if (typeof entry.open === 'string' && typeof entry.close === 'string') {
        return `${entry.open} – ${entry.close}`;
    }

    return '';
}

function renderResolvedHours(
    envelope: BusinessInfoEnvelope,
    showSpecialHours: boolean
): ReactElement | null {
    const hours = envelope.hours ?? {};
    const dayKeys = DAY_ORDER.filter((day) =>
        Object.prototype.hasOwnProperty.call(hours, day)
    );
    const special = showSpecialHours ? envelope.specialHours ?? [] : [];

    if (0 === dayKeys.length && 0 === special.length) {
        return null;
    }

    return (
        <table className="ap-business-hours__table">
            <tbody>
                {dayKeys.map((day) => (
                    <tr key={day} className="ap-business-hours__row">
                        <th scope="row" className="ap-business-hours__day">
                            {__(DAY_LABELS[day] ?? day, TEXT_DOMAIN)}
                        </th>
                        <td className="ap-business-hours__time">
                            {formatHours(
                                (hours as Record<string, unknown>)[day]
                            )}
                        </td>
                    </tr>
                ))}
                {showSpecialHours &&
                    special.map((entry, idx) => {
                        const label =
                            typeof entry.label === 'string' && '' !== entry.label
                                ? entry.label
                                : entry.date;
                        const time = formatSpecialHoursTime(entry);
                        return (
                            <tr
                                key={`special-${entry.date}-${idx}`}
                                className="ap-business-hours__row ap-business-hours__row--special"
                            >
                                <th scope="row" className="ap-business-hours__day">
                                    {label}
                                </th>
                                <td className="ap-business-hours__time">
                                    {time}
                                </td>
                            </tr>
                        );
                    })}
            </tbody>
        </table>
    );
}

function renderPlaceholder(): ReactElement {
    return (
        <>
            <p className="ap-business-hours__hint">
                <em>
                    {__(
                        'Business hours (preview) — populate through the ap.visualEditor.businessInfo filter.',
                        TEXT_DOMAIN
                    )}
                </em>
            </p>
            <table className="ap-business-hours__table">
                <tbody>
                    {STUB_DAYS.map((row) => (
                        <tr key={row.label} className="ap-business-hours__row">
                            <th scope="row" className="ap-business-hours__day">
                                {__(row.label, TEXT_DOMAIN)}
                            </th>
                            <td className="ap-business-hours__time">
                                {__(row.hours, TEXT_DOMAIN)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </>
    );
}

export default function BusinessHoursEdit({
    attributes,
    setAttributes,
}: BusinessHoursEditProps): ReactElement {
    const { showSpecialHours, specialHoursWindowDays } = attributes;

    const blockProps = useBlockProps({ className: 'ap-business-hours' });

    const stamped = attributes._resolvedBusinessInfo;
    const { envelope: fetched } = useBusinessInfo({ specialHoursWindowDays });

    const envelope: BusinessInfoEnvelope | null = stamped ?? fetched;
    const resolvedContent =
        envelope !== null ? renderResolvedHours(envelope, showSpecialHours) : null;

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Business hours settings', TEXT_DOMAIN)} initialOpen>
                    <ToggleControl
                        label={__('Show upcoming special hours', TEXT_DOMAIN)}
                        help={__(
                            'When enabled, holiday and one-off hour overrides supplied by the host are listed below the weekly hours.',
                            TEXT_DOMAIN
                        )}
                        checked={showSpecialHours}
                        onChange={(next) => setAttributes({ showSpecialHours: next })}
                        __nextHasNoMarginBottom
                    />
                    <RangeControl
                        label={__('Special hours window (days)', TEXT_DOMAIN)}
                        help={__(
                            'Only show special-hours overrides within this many days from today.',
                            TEXT_DOMAIN
                        )}
                        value={specialHoursWindowDays}
                        min={1}
                        max={365}
                        onChange={(next) =>
                            setAttributes({
                                specialHoursWindowDays:
                                    typeof next === 'number' ? next : 30,
                            })
                        }
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                {resolvedContent !== null ? resolvedContent : renderPlaceholder()}
            </div>
        </>
    );
}
