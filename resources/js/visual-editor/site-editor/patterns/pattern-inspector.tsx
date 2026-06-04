/**
 * Pattern document panel — drops into the reused A1 inspector sidebar
 * as the "Document" tab when a pattern is open in the canvas.
 *
 * Per design brief §3.6, the Pattern tab shows:
 *   - name (editable)
 *   - slug (editable, normalized)
 *   - sync status (read-only — conversion is destructive, not a toggle)
 *   - categories (multi-select, comma-input for V1)
 *   - "Convert to unsynced copy" button (synced patterns only)
 */

import { __ } from '@wordpress/i18n';
import { useCallback, useId } from 'react';

import { TEXT_DOMAIN } from '../../vendor/i18n';
import { normalizeSlugInput } from '../slug';

import type { PatternEditorFields } from './use-pattern-editor';
import type { PatternRecord, ValidationErrors } from './api-client';

import './pattern-inspector.css';

export interface PatternDocumentPanelProps {
    pattern: PatternRecord | null;
    fields: PatternEditorFields;
    onFieldsChange: (fields: Partial<PatternEditorFields>) => void;
    validationErrors: ValidationErrors | null;
    onConvertToUnsynced: () => void;
}

/**
 * Lowercases the input, collapses any non-alphanumeric run to a single
 * hyphen, and trims leading/trailing hyphens. Used for the categories
 * field so what the user types (`Featured Hero`, `featured hero`,
 * `Featured-Hero!`) all converge on the same `featured-hero` slug.
 */
function slugifyCategory(value: string): string {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export function PatternDocumentPanel(
    props: PatternDocumentPanelProps
): JSX.Element {
    const {
        pattern,
        fields,
        onFieldsChange,
        validationErrors,
        onConvertToUnsynced,
    } = props;

    const titleId = useId();
    const slugId = useId();
    const categoriesId = useId();
    const statusId = useId();
    const titleErrorId = useId();
    const slugErrorId = useId();
    const statusErrorId = useId();

    const handleCategoriesChange = useCallback(
        (value: string): void => {
            // Normalize each comma-delimited token to a slug so the
            // backend's `firstOrCreate` lookup (which keys on slug)
            // sees consistent values regardless of how the user types.
            // Drops anything that empties out after normalization.
            const slugs = value
                .split(',')
                .map((token) => slugifyCategory(token))
                .filter((slug) => slug !== '');

            onFieldsChange({ categories: slugs });
        },
        [onFieldsChange]
    );

    if (pattern === null) {
        return (
            <p
                className="ap-pattern-inspector__empty"
                data-testid="ap-pattern-inspector-empty"
            >
                {__('Open a pattern to edit its settings.', TEXT_DOMAIN)}
            </p>
        );
    }

    const titleError = validationErrors?.title?.[0] ?? null;
    const slugError = validationErrors?.slug?.[0] ?? null;
    const statusError = validationErrors?.status?.[0] ?? null;

    return (
        <div
            className="ap-pattern-inspector"
            data-testid="ap-pattern-inspector"
        >
            <div className="ap-pattern-inspector__field">
                <label
                    className="ap-pattern-inspector__label"
                    htmlFor={titleId}
                >
                    {__('Name', TEXT_DOMAIN)}
                </label>
                <input
                    id={titleId}
                    type="text"
                    className="ap-pattern-inspector__input"
                    value={fields.title}
                    onChange={(event) =>
                        onFieldsChange({ title: event.target.value })
                    }
                    data-testid="ap-pattern-inspector-title"
                    aria-invalid={Boolean(titleError) || undefined}
                    aria-describedby={
                        titleError !== null ? titleErrorId : undefined
                    }
                />
                {titleError !== null ? (
                    <p
                        id={titleErrorId}
                        className="ap-pattern-inspector__error"
                        role="alert"
                    >
                        {titleError}
                    </p>
                ) : null}
            </div>

            <div className="ap-pattern-inspector__field">
                <label
                    className="ap-pattern-inspector__label"
                    htmlFor={slugId}
                >
                    {__('Slug', TEXT_DOMAIN)}
                </label>
                <input
                    id={slugId}
                    type="text"
                    className="ap-pattern-inspector__input ap-pattern-inspector__input--slug"
                    value={fields.slug}
                    onChange={(event) =>
                        onFieldsChange({
                            slug: normalizeSlugInput(event.target.value),
                        })
                    }
                    data-testid="ap-pattern-inspector-slug"
                    aria-invalid={Boolean(slugError) || undefined}
                    aria-describedby={
                        slugError !== null ? slugErrorId : undefined
                    }
                />
                {slugError !== null ? (
                    <p
                        id={slugErrorId}
                        className="ap-pattern-inspector__error"
                        role="alert"
                    >
                        {slugError}
                    </p>
                ) : null}
            </div>

            <div className="ap-pattern-inspector__field">
                <span className="ap-pattern-inspector__label">
                    {__('Sync status', TEXT_DOMAIN)}
                </span>
                <span
                    className="ap-pattern-inspector__sync-status"
                    data-synced={pattern.synced}
                    data-testid="ap-pattern-inspector-sync"
                >
                    {pattern.synced
                        ? __('Synced', TEXT_DOMAIN)
                        : __('Unsynced', TEXT_DOMAIN)}
                </span>
                <p className="ap-pattern-inspector__help">
                    {pattern.synced
                        ? __(
                              'Sync status is set at creation time. To get an unsynced version, use the action below to make a new copy.',
                              TEXT_DOMAIN
                          )
                        : __(
                              'Sync status is set at creation time. Each insertion is an independent copy of the block tree.',
                              TEXT_DOMAIN
                          )}
                </p>
            </div>

            <div className="ap-pattern-inspector__field">
                <label
                    className="ap-pattern-inspector__label"
                    htmlFor={categoriesId}
                >
                    {__('Categories', TEXT_DOMAIN)}
                </label>
                <input
                    id={categoriesId}
                    type="text"
                    className="ap-pattern-inspector__input"
                    value={fields.categories.join(', ')}
                    onChange={(event) =>
                        handleCategoriesChange(event.target.value)
                    }
                    placeholder={__(
                        'Comma-separated, e.g. featured, hero',
                        TEXT_DOMAIN
                    )}
                    data-testid="ap-pattern-inspector-categories"
                />
            </div>

            <div className="ap-pattern-inspector__field">
                <label
                    className="ap-pattern-inspector__label"
                    htmlFor={statusId}
                >
                    {__('Status', TEXT_DOMAIN)}
                </label>
                <select
                    id={statusId}
                    className="ap-pattern-inspector__select"
                    value={fields.status}
                    onChange={(event) =>
                        onFieldsChange({
                            status: event.target.value as PatternEditorFields['status'],
                        })
                    }
                    data-testid="ap-pattern-inspector-status"
                    aria-invalid={Boolean(statusError) || undefined}
                    aria-describedby={
                        statusError !== null ? statusErrorId : undefined
                    }
                >
                    <option value="publish">
                        {__('Published', TEXT_DOMAIN)}
                    </option>
                    <option value="draft">{__('Draft', TEXT_DOMAIN)}</option>
                    <option value="private">
                        {__('Private', TEXT_DOMAIN)}
                    </option>
                </select>
                {statusError !== null ? (
                    <p
                        id={statusErrorId}
                        className="ap-pattern-inspector__error"
                        role="alert"
                    >
                        {statusError}
                    </p>
                ) : null}
            </div>

            {pattern.synced ? (
                <div className="ap-pattern-inspector__field ap-pattern-inspector__field--destructive">
                    <button
                        type="button"
                        className="ap-pattern-inspector__convert"
                        onClick={onConvertToUnsynced}
                        data-testid="ap-pattern-inspector-convert"
                    >
                        {__('Convert to unsynced copy', TEXT_DOMAIN)}
                    </button>
                    <p className="ap-pattern-inspector__help">
                        {__(
                            'Creates a new unsynced pattern with this content. Existing insertions of the synced pattern keep their reference and continue to update when you edit it.',
                            TEXT_DOMAIN
                        )}
                    </p>
                </div>
            ) : null}
        </div>
    );
}
