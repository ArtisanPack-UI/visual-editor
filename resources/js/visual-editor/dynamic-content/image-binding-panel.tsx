/**
 * Image block Dynamic Content binding panel.
 *
 * Rendered inside the Image block's InspectorControls via
 * `editor.BlockEdit` HOC filter. Lists Dynamic Content image fields
 * and, on pick, writes bindings for `url`, `alt`, `id` onto the block's
 * `bindings` sidecar so SSR resolves the values through the
 * DynamicContentSource.
 *
 * @since 1.4.0
 */

import { InspectorControls } from '@wordpress/block-editor';
import {
    Button,
    Notice,
    PanelBody,
    SelectControl,
    Spinner,
} from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

import { fetchSources, flattenTokens, type DynamicContentSource } from './api';

const IMAGE_BLOCKS = new Set([ 'artisanpack/image', 'core/image' ]);

interface BindingSidecar {
    [attr: string]: { source: string; args: { token: string } };
}

interface EditProps {
    name?: string;
    attributes?: Record<string, unknown> & { bindings?: BindingSidecar };
    setAttributes?: (patch: Record<string, unknown>) => void;
}

function ImageBindingPanel({ attributes, setAttributes }: EditProps) {
    const [sources, setSources] = useState<DynamicContentSource[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
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

    const imageTokens = flattenTokens(sources).filter((row) => row.fieldType === 'image');

    const currentBinding = attributes?.bindings?.url?.args?.token ?? '';

    const bindToToken = (token: string) => {
        if (typeof setAttributes !== 'function') return;

        if (!token) {
            const { bindings, ...rest } = attributes ?? {};
            const nextBindings = { ...(bindings ?? {}) };
            delete nextBindings.url;
            delete nextBindings.alt;
            delete nextBindings.id;
            setAttributes({ bindings: Object.keys(nextBindings).length === 0 ? undefined : nextBindings, ...rest });
            return;
        }

        const [sourceSlug] = token.split('.');
        const altToken = `${sourceSlug}.${token.split('.')[1]}_alt`;

        setAttributes({
            bindings: {
                ...(attributes?.bindings ?? {}),
                url: { source: 'dynamic_content', args: { token } },
                alt: { source: 'dynamic_content', args: { token: altToken } },
            } as BindingSidecar,
        });
    };

    const options = [
        { label: __('— Static image (no binding) —', 'artisanpack-visual-editor'), value: '' },
        ...imageTokens.map((row) => ({ label: `${row.sourceLabel} → ${row.fieldLabel}`, value: row.token })),
    ];

    return (
        <InspectorControls>
            <PanelBody title={__('Dynamic Content', 'artisanpack-visual-editor')} initialOpen={Boolean(currentBinding)}>
                {loading && <Spinner />}
                {error && <Notice status="error" isDismissible={false}>{error}</Notice>}
                <SelectControl
                    label={__('Bind image to', 'artisanpack-visual-editor')}
                    value={currentBinding}
                    options={options}
                    onChange={bindToToken}
                    help={__('Binds the image URL, alt text, and id to a Dynamic Content image field.', 'artisanpack-visual-editor')}
                />
                {currentBinding && (
                    <Button variant="tertiary" isDestructive onClick={() => bindToToken('')}>
                        {__('Clear binding', 'artisanpack-visual-editor')}
                    </Button>
                )}
            </PanelBody>
        </InspectorControls>
    );
}

const withImageBindingPanel = createHigherOrderComponent(
    (BlockEdit: React.ComponentType<EditProps>) => {
        return function ImageBindingPanelWrapper(props: EditProps & { clientId?: string }) {
            const name = typeof props.name === 'string' ? props.name : '';
            if (!IMAGE_BLOCKS.has(name)) return <BlockEdit {...props} />;

            // Gate on selection so container blocks that mount inner
            // instances don't stack duplicate fills.
            const isCurrentlySelected = useSelect(
                (select: (store: string) => { getSelectedBlockClientId?: () => string | null }) => {
                    const store = select('core/block-editor');
                    return typeof store?.getSelectedBlockClientId === 'function'
                        && store.getSelectedBlockClientId() === props.clientId;
                },
                [ props.clientId ]
            );

            return (
                <>
                    <BlockEdit {...props} />
                    {isCurrentlySelected && <ImageBindingPanel {...props} />}
                </>
            );
        };
    },
    'withImageBindingPanel'
);

let registered = false;

export function registerImageBindingPanel(): void {
    if (registered) return;
    registered = true;

    addFilter(
        'editor.BlockEdit',
        'artisanpack-ui/visual-editor/dynamic-content-image-binding',
        withImageBindingPanel
    );
}
