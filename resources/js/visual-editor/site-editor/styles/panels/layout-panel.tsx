/**
 * Layout panel.
 *
 * Schema v3 layout covers content / wide widths (`settings.layout.contentSize`,
 * `wideSize`) and the default block gap. All three render through
 * Gutenberg's `__experimentalUnitControl` so the number-and-unit
 * interaction matches every other editor surface.
 */

// `UnitControl` is still marked experimental upstream — its export name,
// props, and emitted value shape may shift between Gutenberg releases.
// Keep the alias centralized so the inevitable rename is a single diff.
import { __experimentalUnitControl as UnitControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useMemo } from 'react';

import { TEXT_DOMAIN } from '../../../vendor/i18n';
import type { ValidationErrors } from '../../api-client';
import type { UseGlobalStylesEditorResult } from '../use-global-styles-editor';
import { StyleControlRow, StylePanelSection } from './panel-controls';

export interface LayoutPanelProps {
    editor: UseGlobalStylesEditorResult;
    validationErrors: ValidationErrors | null;
}

interface LayoutField {
    label: string;
    path: readonly string[];
    testId: string;
    help?: string;
}

/**
 * Built per call so the labels pick up translations loaded after import.
 */
function getLayoutFields(): readonly LayoutField[] {
    return [
        {
            label: __('Content size', TEXT_DOMAIN),
            path: ['settings', 'layout', 'contentSize'],
            testId: 'content-size',
            help: __('Default width for alignable blocks.', TEXT_DOMAIN),
        },
        {
            label: __('Wide size', TEXT_DOMAIN),
            path: ['settings', 'layout', 'wideSize'],
            testId: 'wide-size',
            help: __('Width used for wide-aligned blocks.', TEXT_DOMAIN),
        },
        {
            label: __('Block spacing', TEXT_DOMAIN),
            path: ['styles', 'spacing', 'blockGap'],
            testId: 'block-gap',
        },
    ];
}

const LAYOUT_UNITS = [
    { value: 'px', label: 'px', default: 16 },
    { value: 'rem', label: 'rem', default: 1 },
    { value: 'em', label: 'em', default: 1 },
    { value: '%', label: '%', default: 100 },
    { value: 'vw', label: 'vw', default: 50 },
    { value: 'vh', label: 'vh', default: 50 },
];

function stringOrEmpty(value: unknown): string {
    return typeof value === 'string' ? value : '';
}

export function LayoutPanel(props: LayoutPanelProps): JSX.Element {
    const { editor, validationErrors } = props;

    const customizedCount = useMemo(
        () =>
            getLayoutFields().filter((field) =>
                editor.isPathCustomized(field.path)
            ).length,
        [editor]
    );

    return (
        <StylePanelSection
            testId="ap-site-editor-style-panel-layout"
            title={__('Layout', TEXT_DOMAIN)}
            customizedCount={customizedCount}
            onResetSection={() =>
                getLayoutFields().forEach((field) => editor.resetPath(field.path))
            }
        >
            {getLayoutFields().map((field) => {
                const value = stringOrEmpty(editor.readValue(field.path));
                const baseValue = stringOrEmpty(
                    editor.readBaseValue(field.path)
                );
                const error =
                    validationErrors?.[field.path.join('.')]?.[0] ?? null;

                return (
                    <StyleControlRow
                        key={field.testId}
                        testId={field.testId}
                        isCustomized={editor.isPathCustomized(field.path)}
                        onReset={() => editor.resetPath(field.path)}
                        baseValue={baseValue === '' ? undefined : baseValue}
                        error={error}
                    >
                        <UnitControl
                            label={field.label}
                            help={field.help}
                            value={value}
                            units={LAYOUT_UNITS}
                            data-testid={`ap-site-editor-style-field-input-${field.testId}`}
                            __nextHasNoMarginBottom={true}
                            onChange={(next) =>
                                editor.setValue(field.path, next ?? '')
                            }
                        />
                    </StyleControlRow>
                );
            })}
        </StylePanelSection>
    );
}
