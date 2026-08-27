/**
 * Dynamic Content link-field picker + href helpers (#662).
 *
 * The "Dynamic Content" tab body: a searchable list of link-eligible
 * Dynamic Content fields, plus the pure helpers that turn a picked field
 * into a scheme-appropriate raw-token href.
 *
 * Deliberately free of any `@wordpress/block-editor` import so it can be
 * composed into surfaces that must not pull the block-editor bundle into
 * their module graph (e.g. the site-editor Navigation link picker). The
 * `LinkControl`-composing wrapper lives in `./link-control`.
 *
 * On pick, a scheme-appropriate raw-token href is built:
 *   - email  → `mailto:{{source.field}}`
 *   - phone  → `tel:{{source.field}}`
 *   - other  → `{{source.field}}`
 * The SSR Dynamic Content resolver rewrites the token at render.
 *
 * @since 1.7.0
 */

import { Notice, SearchControl, Spinner } from '@wordpress/components';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { fetchSources, flattenTokens, type DynamicContentSource } from './api';

/**
 * Dynamic Content field types that make sense as a link target. Mirrors
 * the Button block's sidebar binding panel (`button-binding-panel.tsx`)
 * and adds `address` per the issue scope.
 */
export const DC_LINK_FIELD_TYPES: ReadonlySet<string> = new Set([
    'url',
    'email',
    'phone',
    'address',
    'string',
    'text',
]);

/**
 * The URI scheme a field type's href should be prefixed with, or
 * `undefined` for a bare token href.
 *
 * @since 1.7.0
 *
 * @param  string  fieldType  The Dynamic Content field type.
 *
 * @return The scheme (`mailto` / `tel`) or `undefined`.
 */
export function schemeForFieldType(fieldType: string): 'mailto' | 'tel' | undefined {
    if (fieldType === 'email') return 'mailto';
    if (fieldType === 'phone') return 'tel';
    return undefined;
}

/**
 * Build the raw-token href for a Dynamic Content field. Email and phone
 * fields are prefixed with `mailto:` / `tel:`; everything else is a bare
 * `{{token}}` the resolver treats as a URL.
 *
 * @since 1.7.0
 *
 * @param  string  fieldType  The Dynamic Content field type.
 * @param  string  token      The `source.field` token (no braces).
 *
 * @return The href to write to the link's `url`.
 */
export function buildDynamicContentHref(fieldType: string, token: string): string {
    const scheme = schemeForFieldType(fieldType);
    const wrapped = `{{${token}}}`;
    return scheme ? `${scheme}:${wrapped}` : wrapped;
}

export interface DynamicLinkFieldRow {
    token: string;
    sourceLabel: string;
    fieldLabel: string;
    fieldType: string;
}

interface DynamicContentLinkPickerProps {
    /** Called with the built href and the picked row. */
    onSelect: (href: string, row: DynamicLinkFieldRow) => void;
}

const PICKER_STYLES = {
    body: {
        display: 'flex',
        flexDirection: 'column' as const,
        gap: 10,
        minWidth: 300,
        padding: 8,
    },
    list: {
        maxHeight: 260,
        overflowY: 'auto' as const,
        border: '1px solid #ddd',
        borderRadius: 4,
        padding: 6,
        background: '#fff',
        margin: 0,
        listStyle: 'none' as const,
    },
    option: {
        display: 'grid',
        gridTemplateColumns: '1fr auto',
        alignItems: 'center',
        gap: 10,
        width: '100%',
        padding: '8px 10px',
        background: 'transparent',
        border: '1px solid transparent',
        borderRadius: 4,
        cursor: 'pointer',
        textAlign: 'left' as const,
        color: 'inherit',
        font: 'inherit',
    },
    optionLabel: { fontWeight: 500 as const },
    optionSource: { display: 'block', fontSize: 11, color: '#757575' },
    optionCode: {
        fontFamily: 'ui-monospace, "SF Mono", Menlo, monospace',
        fontSize: 11,
        padding: '2px 6px',
        background: '#f0f0f1',
        borderRadius: 3,
        color: '#1e1e1e',
        whiteSpace: 'nowrap' as const,
    },
    help: { margin: 0, fontSize: 12, color: '#757575' },
};

/**
 * Searchable list of link-eligible Dynamic Content fields. Exported for
 * focused testing of the pick → href behavior.
 *
 * @since 1.7.0
 */
export function DynamicContentLinkPicker({ onSelect }: DynamicContentLinkPickerProps): JSX.Element {
    const [sources, setSources] = useState<DynamicContentSource[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [search, setSearch] = useState('');

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError(null);

        fetchSources()
            .then((rows) => {
                if (!cancelled) setSources(rows);
            })
            .catch((e: Error) => {
                if (!cancelled) setError(e.message);
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, []);

    const linkRows = useMemo(
        () => flattenTokens(sources).filter((row) => DC_LINK_FIELD_TYPES.has(row.fieldType)),
        [sources]
    );

    const filtered = useMemo(() => {
        const needle = search.trim().toLowerCase();
        if (!needle) return linkRows;
        return linkRows.filter((row) => {
            return (
                row.token.toLowerCase().includes(needle) ||
                row.sourceLabel.toLowerCase().includes(needle) ||
                row.fieldLabel.toLowerCase().includes(needle)
            );
        });
    }, [linkRows, search]);

    return (
        <div style={PICKER_STYLES.body}>
            <SearchControl
                value={search}
                onChange={setSearch}
                label={__('Search Dynamic Content fields', 'artisanpack-visual-editor')}
            />
            {loading && <Spinner />}
            {error && (
                <Notice status="error" isDismissible={false}>
                    {error}
                </Notice>
            )}
            {!loading && !error && linkRows.length === 0 && (
                <Notice status="info" isDismissible={false}>
                    {__(
                        'No linkable Dynamic Content fields are registered yet.',
                        'artisanpack-visual-editor'
                    )}
                </Notice>
            )}
            {filtered.length > 0 && (
                <ul
                    style={PICKER_STYLES.list}
                    role="listbox"
                    aria-label={__('Dynamic Content fields', 'artisanpack-visual-editor')}
                >
                    {filtered.map((row) => (
                        <li key={row.token}>
                            <button
                                type="button"
                                style={PICKER_STYLES.option}
                                onClick={() =>
                                    onSelect(buildDynamicContentHref(row.fieldType, row.token), {
                                        token: row.token,
                                        sourceLabel: row.sourceLabel,
                                        fieldLabel: row.fieldLabel,
                                        fieldType: row.fieldType,
                                    })
                                }
                            >
                                <span>
                                    <span style={PICKER_STYLES.optionLabel}>{row.fieldLabel}</span>
                                    <span style={PICKER_STYLES.optionSource}>{row.sourceLabel}</span>
                                </span>
                                <code style={PICKER_STYLES.optionCode}>{`{{${row.token}}}`}</code>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
            <p style={PICKER_STYLES.help}>
                {__(
                    'Phone and email fields are prefixed with tel: / mailto: automatically. The token resolves to its value at render.',
                    'artisanpack-visual-editor'
                )}
            </p>
        </div>
    );
}

export default DynamicContentLinkPicker;
