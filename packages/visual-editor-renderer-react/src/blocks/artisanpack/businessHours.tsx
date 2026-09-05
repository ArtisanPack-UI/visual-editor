/**
 * React renderer for the `artisanpack/business-hours` block (#761).
 *
 * Mirrors the Blade partial + Vue renderer so every environment emits
 * identical markup. The hours + special-hours envelope is server-resolved
 * and arrives on the `_resolvedBusinessInfo` attribute (populated by
 * BusinessInfoResolver from the host's `ap.visualEditor.businessInfo`
 * filter). This renderer is responsible for the wrapper, weekly table,
 * and (optionally) the upcoming-overrides list.
 */

import type { ReactElement } from 'react';

import { attrArray, attrBoolean, attrRecord, attrString, classList } from '../../support/attributes';
import type { BlockRendererProps } from '../../types';

interface DayEntry {
    readonly open?: string;
    readonly close?: string;
    readonly closed?: boolean;
}

interface SpecialEntry {
    readonly date: string;
    readonly label?: string;
    readonly open?: string;
    readonly close?: string;
    readonly closed?: boolean;
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

function formatDayEntry(entry: unknown): string {
    if (entry === null || typeof entry !== 'object') {
        return '';
    }

    const record = entry as DayEntry | ReadonlyArray<DayEntry>;

    if (Array.isArray(record)) {
        // Split shifts — array of ranges.
        const parts: string[] = [];
        for (const range of record) {
            if (
                range !== null &&
                typeof range === 'object' &&
                typeof range.open === 'string' &&
                typeof range.close === 'string'
            ) {
                parts.push(`${range.open} – ${range.close}`);
            }
        }
        return parts.join(', ');
    }

    if (record.closed === true) {
        return 'Closed';
    }

    if (typeof record.open === 'string' && typeof record.close === 'string') {
        return `${record.open} – ${record.close}`;
    }

    return '';
}

export function BusinessHoursBlock({ attributes }: BlockRendererProps): ReactElement {
    const info = attrRecord(attributes._resolvedBusinessInfo);
    const showSpecialHours = attrBoolean(attributes.showSpecialHours, true);

    const hours = attrRecord(info.hours);
    const specialHours = attrArray(info.specialHours);

    const className = attrString(attributes.className);
    const ariaLabel = attrString(attributes.ariaLabel, 'Business hours');
    const wrapperClasses = classList(['ap-business-hours', className]);

    const orderedDays = DAY_ORDER.filter((day) =>
        Object.prototype.hasOwnProperty.call(hours, day)
    );

    const hasHours = orderedDays.length > 0;
    const hasSpecial = showSpecialHours && specialHours.length > 0;

    if (!hasHours && !hasSpecial) {
        return <section className={wrapperClasses} aria-label={ariaLabel} />;
    }

    return (
        <section className={wrapperClasses} aria-label={ariaLabel}>
            <table className="ap-business-hours__table">
                <tbody>
                    {orderedDays.map((day) => (
                        <tr key={day} className="ap-business-hours__row">
                            <th scope="row" className="ap-business-hours__day">
                                {DAY_LABELS[day]}
                            </th>
                            <td className="ap-business-hours__time">
                                {formatDayEntry(hours[day])}
                            </td>
                        </tr>
                    ))}
                    {hasSpecial &&
                        specialHours.map((rawEntry, index) => {
                            if (rawEntry === null || typeof rawEntry !== 'object') {
                                return null;
                            }
                            const entry = rawEntry as SpecialEntry;
                            const label =
                                typeof entry.label === 'string' && entry.label !== ''
                                    ? entry.label
                                    : entry.date;
                            const timeLabel =
                                entry.closed === true
                                    ? 'Closed'
                                    : typeof entry.open === 'string' &&
                                        typeof entry.close === 'string'
                                      ? `${entry.open} – ${entry.close}`
                                      : '';
                            return (
                                <tr
                                    key={`special-${entry.date}-${index}`}
                                    className="ap-business-hours__row ap-business-hours__row--special"
                                >
                                    <th scope="row" className="ap-business-hours__day">
                                        {label}
                                    </th>
                                    <td className="ap-business-hours__time">
                                        {timeLabel}
                                    </td>
                                </tr>
                            );
                        })}
                </tbody>
            </table>
        </section>
    );
}
