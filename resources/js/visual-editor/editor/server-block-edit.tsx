/**
 * Generic edit component for server-rendered third-party blocks (#766).
 *
 * A block registered from PHP (`VisualEditor::registerServerBlock()`) ships
 * no client `edit`. This factory builds one from the block's block.json
 * `attributes` schema: an inspector panel of inferred controls
 * ({@see resolveAttributeControls}) plus a live canvas preview through the
 * package's existing `<ServerSideRender>` seam, which POSTs the current
 * attributes to `/visual-editor/api/blocks/preview` and paints the returned
 * HTML. The block therefore edits and previews with zero client build.
 */

import type { ComponentType, ReactElement } from 'react';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import {
    AttributeControl,
    resolveAttributeControls,
    type AttributeSchema,
} from './attribute-controls';
import { ServerSideRender } from './server-side-render';
import { TEXT_DOMAIN } from '../vendor/i18n';

interface ServerBlockEditProps {
    readonly attributes: Record<string, unknown>;
    readonly setAttributes: (attrs: Record<string, unknown>) => void;
}

/**
 * Build the generic edit component for one server block.
 *
 * @param name        Fully-qualified block name (e.g. `acme/manage-booking`).
 * @param attributes  The block's block.json `attributes` schema, used to
 *                    generate the inspector controls.
 */
export function createServerBlockEdit(
    name: string,
    attributes: Record<string, AttributeSchema> | undefined
): ComponentType<ServerBlockEditProps> {
    const descriptors = resolveAttributeControls(attributes);

    function ServerBlockEdit({
        attributes: values,
        setAttributes,
    }: ServerBlockEditProps): ReactElement {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const blockProps = (useBlockProps as any)();

        return (
            <>
                {descriptors.length > 0 && (
                    <InspectorControls>
                        <PanelBody title={__('Settings', TEXT_DOMAIN)}>
                            {descriptors.map((descriptor) => (
                                <AttributeControl
                                    key={descriptor.name}
                                    descriptor={descriptor}
                                    value={values[descriptor.name]}
                                    onChange={(next) =>
                                        setAttributes({ [descriptor.name]: next })
                                    }
                                />
                            ))}
                        </PanelBody>
                    </InspectorControls>
                )}
                <div {...blockProps}>
                    <ServerSideRender block={name} attributes={values} />
                </div>
            </>
        );
    }

    ServerBlockEdit.displayName = `ServerBlockEdit(${name})`;

    return ServerBlockEdit;
}
