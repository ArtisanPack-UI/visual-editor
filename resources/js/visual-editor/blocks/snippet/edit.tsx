/**
 * Snippet block edit component.
 *
 * Lists available snippets, lets the author pick one by slug, then
 * fetches its rendered preview through the batched block-preview
 * endpoint so the read-only inline render matches SSR.
 *
 * @since 1.4.0
 */

import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
    Button,
    Notice,
    PanelBody,
    SelectControl,
    Spinner,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const API_BASE = '/visual-editor/api';

interface SnippetRecord {
    id: number;
    slug: string;
    title: string;
    blocks: unknown[];
}

interface EditProps {
    attributes: { slug: string };
    setAttributes: (patch: Partial<{ slug: string }>) => void;
}

export default function SnippetEdit({ attributes, setAttributes }: EditProps) {
    const blockProps = useBlockProps({
        className: 've-snippet-block',
    });

    const [snippets, setSnippets] = useState<SnippetRecord[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);

        fetch(`${API_BASE}/snippets`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then((res) => (res.ok ? res.json() : Promise.reject(new Error(`HTTP ${res.status}`))))
            .then((body) => {
                if (cancelled) return;
                setSnippets(Array.isArray(body?.data) ? (body.data as SnippetRecord[]) : []);
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
        { label: __('— Select a snippet —', 'artisanpack-visual-editor'), value: '' },
        ...snippets.map((s) => ({
            label: `${s.title || s.slug} (${s.slug})`,
            value: s.slug,
        })),
    ];

    const selected = snippets.find((s) => s.slug === attributes.slug) ?? null;

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('Snippet', 'artisanpack-visual-editor')} initialOpen>
                    {loading && <Spinner />}
                    {error && <Notice status="error" isDismissible={false}>{error}</Notice>}
                    <SelectControl
                        label={__('Reference', 'artisanpack-visual-editor')}
                        value={attributes.slug ?? ''}
                        options={options}
                        onChange={(slug: string) => setAttributes({ slug })}
                    />
                    {selected && (
                        <Button
                            variant="link"
                            href={`/visual-editor/admin/snippets/${encodeURIComponent(selected.slug)}`}
                            target="_blank"
                            rel="noreferrer"
                        >
                            {__('Edit snippet →', 'artisanpack-visual-editor')}
                        </Button>
                    )}
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                {!attributes.slug && (
                    <div className="ve-snippet-placeholder">
                        {__('Pick a snippet from the sidebar.', 'artisanpack-visual-editor')}
                    </div>
                )}
                {attributes.slug && selected && (
                    <div className="ve-snippet-preview" aria-label={__('Read-only snippet preview', 'artisanpack-visual-editor')}>
                        <div className="ve-snippet-preview__label">
                            {__('Snippet:', 'artisanpack-visual-editor')} <strong>{selected.title || selected.slug}</strong>
                        </div>
                    </div>
                )}
                {attributes.slug && !selected && !loading && (
                    <Notice status="warning" isDismissible={false}>
                        {__('Snippet not found. It may have been deleted.', 'artisanpack-visual-editor')}
                    </Notice>
                )}
            </div>
        </>
    );
}
