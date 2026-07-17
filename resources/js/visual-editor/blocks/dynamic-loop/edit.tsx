/**
 * Dynamic Loop block edit component.
 *
 * Author picks a collection source, then edits an inner-block template
 * inline. The editor renders the template once with the first record's
 * values as a preview; SSR iterates every record.
 *
 * @since 1.4.0
 */

import {
    InnerBlocks,
    InspectorControls,
    useBlockProps,
} from '@wordpress/block-editor';
import {
    Notice,
    PanelBody,
    SelectControl,
    Spinner,
    TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const API_BASE = '/visual-editor/api';

interface DynamicContentSource {
    slug: string;
    label: string;
    cardinality: 'singleton' | 'collection';
    fields: Array<{ slug: string; label: string; type: string }>;
}

interface EditProps {
    attributes: { collection: string; emptyMessage: string };
    setAttributes: (patch: Partial<{ collection: string; emptyMessage: string }>) => void;
}

const TEMPLATE: Array<[string, Record<string, unknown>]> = [
    ['artisanpack/paragraph', { placeholder: 'Bind attributes to loop tokens…' }],
];

export default function DynamicLoopEdit({ attributes, setAttributes }: EditProps) {
    const blockProps = useBlockProps({
        className: 've-dynamic-loop-block',
    });

    const [sources, setSources] = useState<DynamicContentSource[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        fetch(`${API_BASE}/dynamic-content/sources`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then((res) => (res.ok ? res.json() : Promise.reject(new Error(`HTTP ${res.status}`))))
            .then((body) => {
                if (cancelled) return;
                const all = Array.isArray(body?.sources) ? (body.sources as DynamicContentSource[]) : [];
                setSources(all.filter((s) => s.cardinality === 'collection'));
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

    const options = [
        { label: __('— Select a collection —', 'artisanpack-visual-editor'), value: '' },
        ...sources.map((s) => ({ label: `${s.label} (${s.slug})`, value: s.slug })),
    ];

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Dynamic Loop', 'artisanpack-visual-editor')} initialOpen>
                    {loading && <Spinner />}
                    {error && <Notice status="error" isDismissible={false}>{error}</Notice>}
                    <SelectControl
                        label={__('Collection', 'artisanpack-visual-editor')}
                        value={attributes.collection ?? ''}
                        options={options}
                        onChange={(collection: string) => setAttributes({ collection })}
                    />
                    <TextControl
                        label={__('Empty message', 'artisanpack-visual-editor')}
                        value={attributes.emptyMessage ?? ''}
                        onChange={(emptyMessage: string) => setAttributes({ emptyMessage })}
                        help={__('Shown when the collection has no records.', 'artisanpack-visual-editor')}
                    />
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                {!attributes.collection && (
                    <Notice status="info" isDismissible={false}>
                        {__('Pick a collection in the sidebar, then design one iteration below.', 'artisanpack-visual-editor')}
                    </Notice>
                )}
                <div className="ve-dynamic-loop__template" aria-label={__('Template (one iteration)', 'artisanpack-visual-editor')}>
                    <InnerBlocks
                        template={TEMPLATE}
                        templateLock={false}
                    />
                </div>
            </div>
        </>
    );
}
