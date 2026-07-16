/**
 * Token Inserter modal.
 *
 * Opened from either the block toolbar button or the `/token` slash
 * command. Lists available Dynamic Content tokens grouped by source,
 * filterable by search, with a live preview of the resolved value.
 * On insert, calls back with the raw `{{token}}` string.
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
            className="ve-dc-token-inserter"
            size="medium"
        >
            <div className="ve-dc-token-inserter__body">
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
                        <a href="/admin/dynamic-content" target="_blank" rel="noreferrer">
                            {__('Create one →', 'artisanpack-visual-editor')}
                        </a>
                    </Notice>
                )}
                <div className="ve-dc-token-inserter__list" role="listbox" aria-label={__('Available tokens', 'artisanpack-visual-editor')}>
                    {grouped.map((group) => (
                        <div key={group.slug} className="ve-dc-token-group">
                            <h4 className="ve-dc-token-group__label">{group.label}</h4>
                            <ul>
                                {group.rows.map((row) => (
                                    <li key={row.token}>
                                        <button
                                            type="button"
                                            className={`ve-dc-token-option${selected?.token === row.token ? ' is-selected' : ''}`}
                                            onClick={() => setSelected(row)}
                                            onDoubleClick={() => {
                                                onInsert(`{{${row.token}}}`);
                                                onClose();
                                            }}
                                        >
                                            <span className="ve-dc-token-option__label">{row.fieldLabel}</span>
                                            <code className="ve-dc-token-option__code">{`{{${row.token}}}`}</code>
                                            <span className={`ve-dc-token-option__type ve-dc-token-option__type--${row.fieldType}`}>
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
                    <div className="ve-dc-token-inserter__preview" aria-live="polite">
                        <strong>{__('Preview:', 'artisanpack-visual-editor')}</strong> {preview}
                    </div>
                )}
            </div>
            <div className="ve-dc-token-inserter__actions">
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
