/**
 * Token Inserter modal.
 *
 * Opened from either the block toolbar button or the `/token` slash
 * command. Lists available Dynamic Content tokens grouped by source,
 * filterable by search, with a live preview of the resolved value.
 * On insert, calls back with the raw `{{token}}` string.
 *
 * Uses inline styles rather than an external stylesheet so the
 * layout is deterministic across host build configurations that may
 * drop transitive CSS imports from the editor bundle.
 *
 * @since 1.4.0
 */

import {
    Button,
    Modal,
    Notice,
    SearchControl,
    Spinner,
} from '@wordpress/components';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { fetchSources, flattenTokens, resolveTokens, type DynamicContentSource } from './api';

interface TokenInserterModalProps {
    isOpen: boolean;
    onClose: () => void;
    onInsert: (token: string) => void;
}

interface FlatToken {
    token: string;
    sourceSlug: string;
    sourceLabel: string;
    fieldSlug: string;
    fieldLabel: string;
    fieldType: string;
    cardinality: 'singleton' | 'collection';
}

const STYLES = {
    body: {
        display: 'flex',
        flexDirection: 'column' as const,
        gap: 12,
        minHeight: 300,
        maxHeight: '60vh',
    },
    list: {
        flex: '1 1 auto',
        overflowY: 'auto' as const,
        border: '1px solid #ddd',
        borderRadius: 4,
        padding: 8,
        background: '#fff',
    },
    group: { marginBottom: 16 },
    groupLabel: {
        margin: '0 0 6px',
        fontSize: 12,
        fontWeight: 600 as const,
        textTransform: 'uppercase' as const,
        letterSpacing: '0.05em',
        color: '#757575',
    },
    ul: { margin: 0, padding: 0, listStyle: 'none' as const },
    option: (isSelected: boolean): React.CSSProperties => ({
        display: 'grid',
        gridTemplateColumns: '1fr auto auto',
        alignItems: 'center' as const,
        gap: 12,
        width: '100%',
        padding: '8px 10px',
        background: isSelected ? 'rgba(0, 124, 186, 0.08)' : 'transparent',
        border: isSelected ? '1px solid #007cba' : '1px solid transparent',
        borderRadius: 4,
        cursor: 'pointer',
        textAlign: 'left' as const,
        color: 'inherit',
        font: 'inherit',
    }),
    optionLabel: { fontWeight: 500 as const },
    optionCode: {
        fontFamily: 'ui-monospace, "SF Mono", Menlo, monospace',
        fontSize: 12,
        padding: '2px 6px',
        background: '#f0f0f1',
        borderRadius: 3,
        color: '#1e1e1e',
        whiteSpace: 'nowrap' as const,
    },
    optionType: (type: string): React.CSSProperties => {
        const palettes: Record<string, { bg: string; fg: string }> = {
            phone: { bg: '#dcf1e2', fg: '#275f36' },
            email: { bg: '#dcf1e2', fg: '#275f36' },
            url: { bg: '#dbe8fb', fg: '#1c3b6b' },
            image: { bg: '#fbe9d0', fg: '#6b3d0c' },
            number: { bg: '#efe0f7', fg: '#5b2a7d' },
        };
        const palette = palettes[type] ?? { bg: '#e5e5e5', fg: '#595959' };
        return {
            fontSize: 11,
            padding: '2px 8px',
            borderRadius: 10,
            background: palette.bg,
            color: palette.fg,
            textTransform: 'uppercase' as const,
            letterSpacing: '0.03em',
        };
    },
    preview: {
        padding: '10px 12px',
        background: '#f6f7f7',
        borderLeft: '3px solid #007cba',
        borderRadius: 2,
        fontSize: 13,
        lineHeight: 1.5,
    },
    actions: {
        display: 'flex',
        justifyContent: 'flex-end',
        gap: 8,
        marginTop: 12,
        paddingTop: 12,
        borderTop: '1px solid #ddd',
    },
};

export default function TokenInserterModal({ isOpen, onClose, onInsert }: TokenInserterModalProps) {
    const [sources, setSources] = useState<DynamicContentSource[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [search, setSearch] = useState('');
    const [selected, setSelected] = useState<FlatToken | null>(null);
    const [preview, setPreview] = useState<string>('');

    useEffect(() => {
        if (!isOpen) return;
        let cancelled = false;
        setLoading(true);
        setError(null);

        fetchSources()
            .then((rows) => {
                if (cancelled) return;
                setSources(rows);
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
    }, [isOpen]);

    const flat = useMemo(() => flattenTokens(sources) as FlatToken[], [sources]);

    const filtered = useMemo(() => {
        const needle = search.trim().toLowerCase();
        if (!needle) return flat;
        return flat.filter((row) => {
            return (
                row.token.toLowerCase().includes(needle) ||
                row.sourceLabel.toLowerCase().includes(needle) ||
                row.fieldLabel.toLowerCase().includes(needle)
            );
        });
    }, [flat, search]);

    const grouped = useMemo(() => {
        const groups = new Map<string, { label: string; rows: FlatToken[] }>();
        for (const row of filtered) {
            if (!groups.has(row.sourceSlug)) {
                groups.set(row.sourceSlug, { label: row.sourceLabel, rows: [] });
            }
            groups.get(row.sourceSlug)!.rows.push(row);
        }
        return Array.from(groups.entries()).map(([slug, g]) => ({ slug, ...g }));
    }, [filtered]);

    useEffect(() => {
        if (!selected) {
            setPreview('');
            return;
        }
        let cancelled = false;
        resolveTokens([selected.token])
            .then((values) => {
                if (cancelled) return;
                const value = values[selected.token];
                if (value === null || value === undefined || value === '') {
                    setPreview(__('(unresolved)', 'artisanpack-visual-editor'));
                } else {
                    setPreview(String(value));
                }
            })
            .catch(() => {
                if (!cancelled) setPreview(__('(preview unavailable)', 'artisanpack-visual-editor'));
            });
        return () => {
            cancelled = true;
        };
    }, [selected]);

    if (!isOpen) return null;

    return (
        <Modal
            title={__('Insert Dynamic Content token', 'artisanpack-visual-editor')}
            onRequestClose={onClose}
            size="medium"
        >
            <div style={STYLES.body}>
                <SearchControl
                    value={search}
                    onChange={setSearch}
                    label={__('Search tokens', 'artisanpack-visual-editor')}
                />
                {loading && <Spinner />}
                {error && (
                    <Notice status="error" isDismissible={false}>
                        {error}
                    </Notice>
                )}
                {!loading && !error && sources.length === 0 && (
                    <Notice status="info" isDismissible={false}>
                        {__(
                            'No Dynamic Content types registered yet. ',
                            'artisanpack-visual-editor'
                        )}
                    </Notice>
                )}
                <div style={STYLES.list} role="listbox" aria-label={__('Available tokens', 'artisanpack-visual-editor')}>
                    {grouped.map((group) => (
                        <div key={group.slug} style={STYLES.group}>
                            <h4 style={STYLES.groupLabel}>{group.label}</h4>
                            <ul style={STYLES.ul}>
                                {group.rows.map((row) => (
                                    <li key={row.token} style={{ marginBottom: 4 }}>
                                        <button
                                            type="button"
                                            style={STYLES.option(selected?.token === row.token)}
                                            onClick={() => setSelected(row)}
                                            onDoubleClick={() => {
                                                onInsert(`{{${row.token}}}`);
                                                onClose();
                                            }}
                                        >
                                            <span style={STYLES.optionLabel}>{row.fieldLabel}</span>
                                            <code style={STYLES.optionCode}>{`{{${row.token}}}`}</code>
                                            <span style={STYLES.optionType(row.fieldType)}>
                                                {row.fieldType}
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>
                {selected && (
                    <div style={STYLES.preview} aria-live="polite">
                        <strong>{__('Preview:', 'artisanpack-visual-editor')}</strong> {preview}
                    </div>
                )}
            </div>
            <div style={STYLES.actions}>
                <Button variant="tertiary" onClick={onClose}>
                    {__('Cancel', 'artisanpack-visual-editor')}
                </Button>
                <Button
                    variant="primary"
                    disabled={!selected}
                    onClick={() => {
                        if (selected) {
                            onInsert(`{{${selected.token}}}`);
                            onClose();
                        }
                    }}
                >
                    {__('Insert', 'artisanpack-visual-editor')}
                </Button>
            </div>
        </Modal>
    );
}
