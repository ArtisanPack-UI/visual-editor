/**
 * Embed — toolbar + inspector controls.
 *
 * Ported from `@wordpress/block-library/src/embed/embed-controls.js`
 * (v9.43.0). The upstream `useToolsPanelDropdownMenuProps` hook from
 * `block-library/src/utils/hooks` is omitted because it is not exposed
 * via the `@wordpress/block-library` package's `exports` field; the
 * fork passes no `dropdownMenuProps` and relies on the ToolsPanel
 * default behaviour.
 */

import type { ReactElement } from 'react';
import { __ } from '@wordpress/i18n';
import {
    ToolbarButton,
    ToggleControl,
    ToolbarGroup,
    __experimentalToolsPanel as ToolsPanel,
    __experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { BlockControls, InspectorControls } from '@wordpress/block-editor';
import { pencil } from '@wordpress/icons';

interface EmbedControlsProps {
    readonly blockSupportsResponsive: boolean;
    readonly showEditButton: boolean;
    readonly themeSupportsResponsive: boolean;
    readonly allowResponsive: boolean;
    readonly toggleResponsive: (next: boolean) => void;
    readonly switchBackToURLInput: () => void;
}

function getResponsiveHelp(checked: boolean): string {
    return checked
        ? __(
              'This embed will preserve its aspect ratio when the browser is resized.'
          )
        : __(
              'This embed may not preserve its aspect ratio when the browser is resized.'
          );
}

export default function EmbedControls({
    blockSupportsResponsive,
    showEditButton,
    themeSupportsResponsive,
    allowResponsive,
    toggleResponsive,
    switchBackToURLInput,
}: EmbedControlsProps): ReactElement {
    return (
        <>
            <BlockControls>
                <ToolbarGroup>
                    {showEditButton && (
                        <ToolbarButton
                            className="components-toolbar__control"
                            label={__('Edit URL')}
                            icon={pencil}
                            onClick={switchBackToURLInput}
                        />
                    )}
                </ToolbarGroup>
            </BlockControls>
            {themeSupportsResponsive && blockSupportsResponsive && (
                <InspectorControls>
                    <ToolsPanel
                        label={__('Media settings')}
                        resetAll={() => {
                            toggleResponsive(true);
                        }}
                    >
                        <ToolsPanelItem
                            label={__('Media settings')}
                            isShownByDefault
                            hasValue={() => !allowResponsive}
                            onDeselect={() => {
                                toggleResponsive(!allowResponsive);
                            }}
                        >
                            <ToggleControl
                                label={__('Resize for smaller devices')}
                                checked={allowResponsive}
                                help={getResponsiveHelp}
                                onChange={toggleResponsive}
                            />
                        </ToolsPanelItem>
                    </ToolsPanel>
                </InspectorControls>
            )}
        </>
    );
}
