/**
 * Loginout — edit component.
 *
 * Server-rendered auth-state block. The canvas preview mirrors upstream's
 * editor preview (always-on "Log out" placeholder + the two display
 * toggles) because the post editor's core-data shim does not expose a
 * current-viewer selector. The real login or logout link is emitted by
 * the Blade / React / Vue renderers from the `_resolved*` attributes
 * stamped server-side at render time. Phase I-Block-Fork auth (#522).
 */

import type { ReactElement } from 'react';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
    ToggleControl,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalToolsPanel as ToolsPanel,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    __experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

interface LoginoutEditAttributes {
    displayLoginAsForm?: boolean;
    redirectToCurrent?: boolean;
}

interface LoginoutEditProps {
    attributes: LoginoutEditAttributes;
    setAttributes: ( attrs: Partial<LoginoutEditAttributes> ) => void;
}

export default function LoginoutEdit( {
    attributes,
    setAttributes,
}: LoginoutEditProps ): ReactElement {
    const displayLoginAsForm = true === attributes.displayLoginAsForm;
    const redirectToCurrent = false !== attributes.redirectToCurrent;

    const blockProps = useBlockProps( { className: 'logged-in' } );

    return (
        <>
            <InspectorControls>
                <ToolsPanel
                    label={ __( 'Settings', TEXT_DOMAIN ) }
                    resetAll={ () => {
                        setAttributes( {
                            displayLoginAsForm: false,
                            redirectToCurrent: true,
                        } );
                    } }
                >
                    <ToolsPanelItem
                        label={ __( 'Display login as form', TEXT_DOMAIN ) }
                        isShownByDefault
                        hasValue={ () => displayLoginAsForm }
                        onDeselect={ () =>
                            setAttributes( { displayLoginAsForm: false } )
                        }
                    >
                        <ToggleControl
                            label={ __( 'Display login as form', TEXT_DOMAIN ) }
                            checked={ displayLoginAsForm }
                            onChange={ () =>
                                setAttributes( {
                                    displayLoginAsForm: ! displayLoginAsForm,
                                } )
                            }
                        />
                    </ToolsPanelItem>
                    <ToolsPanelItem
                        label={ __( 'Redirect to current URL', TEXT_DOMAIN ) }
                        isShownByDefault
                        hasValue={ () => ! redirectToCurrent }
                        onDeselect={ () =>
                            setAttributes( { redirectToCurrent: true } )
                        }
                    >
                        <ToggleControl
                            label={ __( 'Redirect to current URL', TEXT_DOMAIN ) }
                            checked={ redirectToCurrent }
                            onChange={ () =>
                                setAttributes( {
                                    redirectToCurrent: ! redirectToCurrent,
                                } )
                            }
                        />
                    </ToolsPanelItem>
                </ToolsPanel>
            </InspectorControls>
            <div { ...blockProps }>
                <a href="#login-pseudo-link">{ __( 'Log out', TEXT_DOMAIN ) }</a>
            </div>
        </>
    );
}
