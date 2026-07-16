/**
 * Link picker wrapper with a Dynamic Content tab.
 *
 * Composes Gutenberg's `__experimentalLinkControl` with an alternate
 * pane that lets authors pick a Dynamic Content URL/email/phone/address
 * field. On selection, the URL is prefixed with the appropriate scheme
 * (`mailto:`, `tel:`) and returned to the caller.
 *
 * Consumers that currently import `__experimentalLinkControl` directly
 * (Button block, Navigation link-picker) can swap to this component
 * to gain the DC tab.
 *
 * @since 1.4.0
 */

import { __experimentalLinkControl as LinkControl } from '@wordpress/block-editor';
import { TabPanel } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { fetchSources, flattenTokens, type DynamicContentSource } from './api';

interface LinkValue {
    url?: string;
    opensInNewTab?: boolean;
    [key: string]: unknown;
}

interface ArtisanPackLinkControlProps {
    value?: LinkValue;
    onChange: (next: LinkValue) => void;
    settings?: unknown;
    [key: string]: unknown;
}

const URL_FIELD_TYPES = new Set(['url', 'email', 'phone', 'string', 'address']);

function schemeFor(fieldType: string, rawValue: string): string {
    if (rawValue.startsWith('http://') || rawValue.startsWith('https://') || rawValue.startsWith('mailto:') || rawValue.startsWith('tel:')) {
        return rawValue;
    }
    if (fieldType === 'email') return `mailto:${rawValue}`;
    if (fieldType === 'phone') return `tel:${rawValue.replace(/[^\d+]/g, '')}`;
    return rawValue;
}

function DynamicContentTab({
    onSelect,
}: {
    onSelect: (url: string, meta: { source: string; token: string; type: string }) => void;
}) {
    const [sources, setSources] = useState<DynamicContentSource[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        fetchSources()
            .then((rows) => {
                if (!cancelled) setSources(rows);
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });
        return () => {
            cancelled = true;
        };
    }, []);

    const rows = flattenTokens(sources).filter((row) => URL_FIELD_TYPES.has(row.fieldType));

    if (loading) return <p>{__('Loading…', 'artisanpack-visual-editor')}</p>;
    if (rows.length === 0) return <p>{__('No URL / email / phone fields registered.', 'artisanpack-visual-editor')}</p>;

    return (
        <ul className="ve-dc-link-list">
            {rows.map((row) => (
                <li key={row.token}>
                    <button
                        type="button"
                        onClick={() => {
                            onSelect(schemeFor(row.fieldType, `{{${row.token}}}`), {
                                source: row.sourceSlug,
                                token: row.token,
                                type: row.fieldType,
                            });
                        }}
                    >
                        <strong>{row.fieldLabel}</strong> <code>{`{{${row.token}}}`}</code>
                    </button>
                </li>
            ))}
        </ul>
    );
}

export default function ArtisanPackLinkControl(props: ArtisanPackLinkControlProps) {
    const tabs = [
        { name: 'url', title: __('URL', 'artisanpack-visual-editor'), className: '' },
        { name: 'dynamic', title: __('Dynamic Content', 'artisanpack-visual-editor'), className: '' },
    ];

    return (
        <div className="ve-dc-link-control">
            <TabPanel tabs={tabs}>
                {(tab: { name: string }) =>
                    tab.name === 'dynamic' ? (
                        <DynamicContentTab
                            onSelect={(url, meta) => {
                                props.onChange({
                                    ...(props.value ?? {}),
                                    url,
                                    // Signal to the containing block that this is a bound URL
                                    // — the block can convert it into a `bindings.url` sidecar
                                    // instead of a static value if desired.
                                    _dynamicContent: meta,
                                } as LinkValue);
                            }}
                        />
                    ) : (
                        <LinkControl {...props} />
                    )
                }
            </TabPanel>
        </div>
    );
}
