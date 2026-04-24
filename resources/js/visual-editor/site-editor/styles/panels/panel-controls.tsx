/**
 * Shared panel primitives built on `@wordpress/components`.
 *
 * Every style inspector panel renders its controls through Gutenberg's
 * own primitives — the same `PanelBody` + `TextControl` + `SelectControl`
 * + `ColorPalette` + `__experimentalUnitControl` set the block
 * inspector uses — so the Styles section feels like a first-class piece
 * of the editor chrome rather than a bespoke form. Customization
 * tracking and reset-to-default affordances layer on top; anything the
 * user has overridden is decorated + one click away from reverting to
 * the theme base.
 */

import {
    Button,
    PanelBody,
    PanelRow,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { type ReactNode } from 'react';

import { TEXT_DOMAIN } from '../../../vendor/i18n';

import './panel-controls.css';

export interface StylePanelSectionProps {
    title: string;
    customizedCount: number;
    onResetSection: () => void;
    description?: ReactNode;
    /** Stable test hook so panel tests can target this section directly. */
    testId?: string;
    children: ReactNode;
}

/**
 * Top-level section wrapper for each styles panel. Wraps Gutenberg's
 * {@link PanelBody} so the collapse affordance, focus behaviour, and
 * keyboard handling mirror the rest of the editor. The "Customized"
 * chip + "Reset all" action live in the header so the user always has
 * a single click to compare against / revert to the theme defaults.
 */
export function StylePanelSection(
    props: StylePanelSectionProps
): JSX.Element {
    const {
        title,
        customizedCount,
        onResetSection,
        description,
        testId,
        children,
    } = props;

    return (
        <div
            className="ap-site-editor__style-panel-wrapper"
            data-testid={testId}
        >
            <PanelBody title={title} initialOpen={true}>
                <div className="ap-site-editor__style-panel-meta">
                    {customizedCount > 0 ? (
                        <span
                            className="ap-site-editor__style-panel-customized"
                            data-testid="ap-site-editor-style-panel-customized"
                        >
                            {__('Customized', TEXT_DOMAIN)}
                        </span>
                    ) : null}
                    {customizedCount > 0 ? (
                        <Button
                            variant="tertiary"
                            size="small"
                            data-testid="ap-site-editor-style-panel-reset"
                            onClick={onResetSection}
                        >
                            {__('Reset all', TEXT_DOMAIN)}
                        </Button>
                    ) : null}
                </div>
                {description !== undefined ? (
                    <PanelRow>
                        <p className="ap-site-editor__style-panel-description">
                            {description}
                        </p>
                    </PanelRow>
                ) : null}
                {children}
            </PanelBody>
        </div>
    );
}

export interface StyleControlRowProps {
    /** data-testid prefix, applied to the reset button + base-default hint. */
    testId: string;
    isCustomized: boolean;
    onReset: () => void;
    baseValue?: string;
    error?: string | null;
    children: ReactNode;
}

/**
 * Wraps a Gutenberg control with the Styles-panel chrome the bare
 * control doesn't know about: customization highlight, per-row "Reset"
 * button, "Theme default:" hint, inline validation error. Keep this
 * purely presentational so the control inside stays a vanilla
 * `TextControl` / `SelectControl` / etc.
 */
export function StyleControlRow(props: StyleControlRowProps): JSX.Element {
    const { testId, isCustomized, onReset, baseValue, error, children } = props;

    return (
        <PanelRow>
            <div
                className="ap-site-editor__style-control-row"
                data-customized={isCustomized}
                data-testid={`ap-site-editor-style-field-${testId}`}
            >
                <div className="ap-site-editor__style-control-row-control">
                    {children}
                </div>
                {isCustomized ? (
                    <div className="ap-site-editor__style-control-row-aside">
                        <Button
                            variant="tertiary"
                            size="small"
                            data-testid={`ap-site-editor-style-field-reset-${testId}`}
                            onClick={onReset}
                        >
                            {__('Reset', TEXT_DOMAIN)}
                        </Button>
                        {baseValue !== undefined && baseValue !== '' ? (
                            <p
                                className="ap-site-editor__style-control-row-base"
                                data-testid={`ap-site-editor-style-field-base-${testId}`}
                            >
                                {__('Theme default:', TEXT_DOMAIN)}{' '}
                                <code>{baseValue}</code>
                            </p>
                        ) : null}
                    </div>
                ) : null}
                {error !== null && error !== undefined ? (
                    <p
                        role="alert"
                        className="ap-site-editor__style-control-row-error"
                        data-testid={`ap-site-editor-style-field-error-${testId}`}
                    >
                        {error}
                    </p>
                ) : null}
            </div>
        </PanelRow>
    );
}
