/**
 * Single Content — "Post" picker (#1003).
 *
 * Rendered inside the block's `<InspectorControls>`, and only while the
 * block is selected, so opening the editor on a page full of
 * single-content blocks sends no record requests. Lists and titles come
 * from the shared cache in `records-cache.ts`.
 *
 * @since 1.16.0
 */

import type { ReactElement } from 'react';
import { useEffect, useState } from '@wordpress/element';
import { ComboboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import {
    clampPostId,
    fetchRecordLabel,
    fetchRecordList,
    type PostSummary,
} from './records-cache';

interface RecordPickerProps {
    readonly collection: string;
    readonly postId: number;
    /** Title already resolved for `postId` (e.g. by the canvas preview). */
    readonly knownLabel?: string | null;
    readonly onChange: (postId: number) => void;
}

export default function RecordPicker({
    collection,
    postId,
    knownLabel = null,
    onChange,
}: RecordPickerProps): ReactElement {
    const [search, setSearch] = useState<string>('');
    const [records, setRecords] = useState<ReadonlyArray<PostSummary> | null>(null);
    const [selectedLabel, setSelectedLabel] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        fetchRecordList(collection, search)
            .then((next) => {
                if (!cancelled) {
                    setRecords(next);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setRecords([]);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [collection, search]);

    useEffect(() => {
        if (!postId) {
            setSelectedLabel(null);
            return;
        }

        if (knownLabel !== null) {
            setSelectedLabel(knownLabel);
            return;
        }

        // Wait for the list so a selected record that is already listed
        // doesn't trigger a lookup of its own.
        if (records === null) {
            return;
        }

        const match = records.find((record) => record.id === postId);
        if (match !== undefined) {
            setSelectedLabel(match.label);
            return;
        }

        let cancelled = false;

        fetchRecordLabel(collection, postId)
            .then((label) => {
                if (!cancelled) {
                    setSelectedLabel(label);
                }
            })
            .catch(() => {
                /* swallowed — falls back to the id-only label below */
            });

        return () => {
            cancelled = true;
        };
    }, [collection, postId, records, knownLabel]);

    const options = (records ?? []).map((record) => ({
        label: record.label,
        value: String(record.id),
    }));

    if (postId && !options.some((option) => option.value === String(postId))) {
        options.unshift({
            label: selectedLabel ?? `#${postId}`,
            value: String(postId),
        });
    }

    return (
        <ComboboxControl
            label={__('Post', TEXT_DOMAIN)}
            help={__(
                'Pick the entry to render. Leave empty to render the host post in scope.',
                TEXT_DOMAIN
            )}
            value={postId ? String(postId) : null}
            options={options}
            onChange={(value) => onChange(clampPostId(value ?? 0))}
            onFilterValueChange={(value) => setSearch(value)}
            __nextHasNoMarginBottom
        />
    );
}
