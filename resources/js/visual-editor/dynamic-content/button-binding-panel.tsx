/**
 * Button block Dynamic Content link-binding panel.
 *
 * The inline `__experimentalLinkControl` popover on the Button block
 * doesn't have a built-in extension seam for adding a "Dynamic Content"
 * tab. Rather than fork the button block wholesale, this HOC surfaces
 * the DC binding through an InspectorControls panel — the same pattern
 * the Image block uses for its `url`/`alt` bindings.
 *
 * Selection binds the `url` attribute; the source prefixes the value
 * with `mailto:`/`tel:` for email/phone fields via `args.scheme`.
 *
 * Rendering is gated on `useSelect(getSelectedBlockClientId)` so the
 * fill only mounts once even when the button lives inside a
 * `core/buttons`/`artisanpack/buttons` container that also mounts each
 * child's edit component. Without the gate, multiple mounts of the
 * button's edit tree stack duplicate InspectorControls fills.
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

const BUTTON_BLOCKS = new Set([ 'artisanpack/button', 'core/button' ]);
const LINK_FIELD_TYPES = new Set([ 'url', 'email', 'phone', 'string' ]);

interface BindingSidecar {
    [attr: string]: { source: string; args: { token: string; scheme?: string } };
}

interface EditProps {
    name?: string;
    clientId?: string;
    isSelected?: boolean;
    attributes?: Record<string, unknown> & { bindings?: BindingSidecar };
    setAttributes?: (patch: Record<string, unknown>) => void;
}

function ButtonBindingPanel({ attributes, setAttributes }: EditProps) {
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

    const linkTokens = flattenTokens(sources).filter((row) => LINK_FIELD_TYPES.has(row.fieldType));

    const currentBinding = attributes?.bindings?.url?.args?.token ?? '';

    const bindToToken = (token: string) => {
        if (typeof setAttributes !== 'function') return;

        if (!token) {
            const { bindings, ...rest } = attributes ?? {};
            const nextBindings = { ...(bindings ?? {}) };
            delete nextBindings.url;
            setAttributes({
                bindings: Object.keys(nextBindings).length === 0 ? undefined : nextBindings,
                ...rest,
            });
            return;
        }

        const row = linkTokens.find((r) => r.token === token);
        const scheme =
            row?.fieldType === 'email' ? 'mailto' :
            row?.fieldType === 'phone' ? 'tel' :
            undefined;

        const args: { token: string; scheme?: string } = { token };
        if (scheme) args.scheme = scheme;

        setAttributes({
            bindings: {
                ...(attributes?.bindings ?? {}),
                url: { source: 'dynamic_content', args },
            } as BindingSidecar,
        });
    };

    const options = [
        { label: __('— Static URL (no binding) —', 'artisanpack-visual-editor'), value: '' },
        ...linkTokens.map((row) => ({
            label: `${row.sourceLabel} → ${row.fieldLabel} (${row.fieldType})`,
            value: row.token,
        })),
    ];

    return (
        <InspectorControls>
            <PanelBody
                title={__('Dynamic Content', 'artisanpack-visual-editor')}
                initialOpen={Boolean(currentBinding)}
            >
                {loading && <Spinner />}
                {error && <Notice status="error" isDismissible={false}>{error}</Notice>}
                <SelectControl
                    label={__('Bind link to', 'artisanpack-visual-editor')}
                    value={currentBinding}
                    options={options}
                    onChange={bindToToken}
                    help={__(
                        'Phone and email fields are prefixed with tel: / mailto: automatically at render.',
                        'artisanpack-visual-editor'
                    )}
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

const withButtonBindingPanel = createHigherOrderComponent(
    (BlockEdit: React.ComponentType<EditProps>) => {
        return function ButtonBindingPanelWrapper(props: EditProps) {
            const name = typeof props.name === 'string' ? props.name : '';
            if (!BUTTON_BLOCKS.has(name)) return <BlockEdit {...props} />;

            // Gate the fill on this block being the *currently selected*
            // block — buttons often live inside a `buttons` container
            // that mounts each child's edit; without this gate, every
            // mounted instance would stack an InspectorControls fill and
            // the sidebar would show a duplicate panel per instance.
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
                    {isCurrentlySelected && <ButtonBindingPanel {...props} />}
                </>
            );
        };
    },
    'withButtonBindingPanel'
);

let registered = false;

export function registerButtonBindingPanel(): void {
    if (registered) return;
    registered = true;

    addFilter(
        'editor.BlockEdit',
        'artisanpack-ui/visual-editor/dynamic-content-button-binding',
        withButtonBindingPanel
    );
}
