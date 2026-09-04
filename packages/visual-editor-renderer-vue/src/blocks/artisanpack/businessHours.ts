/**
 * Vue renderer for the `artisanpack/business-hours` block (#761).
 *
 * Mirrors the Blade partial + React renderer so every environment emits
 * identical markup. Reads the server-stamped `_resolvedBusinessInfo`
 * envelope for weekly hours + upcoming special-hours overrides.
 */

import { defineComponent, h, type VNode } from 'vue';

import { attrArray, attrBoolean, attrRecord, attrString, classList } from '../../support/attributes';
import { blockRendererProps } from '../shared';

interface DayEntry {
    open?: string;
    close?: string;
    closed?: boolean;
}

interface SpecialEntry {
    date: string;
    label?: string;
    open?: string;
    close?: string;
    closed?: boolean;
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

    if (Array.isArray(entry)) {
        const parts: string[] = [];
        for (const range of entry as ReadonlyArray<DayEntry>) {
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

    const record = entry as DayEntry;

    if (record.closed === true) {
        return 'Closed';
    }

    if (typeof record.open === 'string' && typeof record.close === 'string') {
        return `${record.open} – ${record.close}`;
    }

    return '';
}

export const BusinessHoursBlock = defineComponent({
    name: 'BusinessHoursBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const attributes = props.attributes;
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
                return h('section', {
                    class: wrapperClasses,
                    'aria-label': ariaLabel,
                });
            }

            const rows: VNode[] = orderedDays.map((day) =>
                h('tr', { key: day, class: 'ap-business-hours__row' }, [
                    h(
                        'th',
                        {
                            scope: 'row',
                            class: 'ap-business-hours__day',
                        },
                        DAY_LABELS[day]
                    ),
                    h(
                        'td',
                        { class: 'ap-business-hours__time' },
                        formatDayEntry(hours[day])
                    ),
                ])
            );

            if (hasSpecial) {
                specialHours.forEach((rawEntry, index) => {
                    if (rawEntry === null || typeof rawEntry !== 'object') {
                        return;
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
                    rows.push(
                        h(
                            'tr',
                            {
                                key: `special-${entry.date}-${index}`,
                                class:
                                    'ap-business-hours__row ap-business-hours__row--special',
                            },
                            [
                                h(
                                    'th',
                                    {
                                        scope: 'row',
                                        class: 'ap-business-hours__day',
                                    },
                                    label
                                ),
                                h(
                                    'td',
                                    { class: 'ap-business-hours__time' },
                                    timeLabel
                                ),
                            ]
                        )
                    );
                });
            }

            return h(
                'section',
                { class: wrapperClasses, 'aria-label': ariaLabel },
                [
                    h('table', { class: 'ap-business-hours__table' }, [
                        h('tbody', rows),
                    ]),
                ]
            );
        };
    },
});
