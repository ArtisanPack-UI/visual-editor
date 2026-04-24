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

const LAYOUT_FIELDS: readonly LayoutField[] = [
    {
        label: 'Content size',
        path: ['settings', 'layout', 'contentSize'],
        testId: 'content-size',
        help: 'Default width for alignable blocks.',
    },
    {
        label: 'Wide size',
        path: ['settings', 'layout', 'wideSize'],
        testId: 'wide-size',
        help: 'Width used for wide-aligned blocks.',
    },
    {
        label: 'Block spacing',
        path: ['styles', 'spacing', 'blockGap'],
        testId: 'block-gap',
    },
];

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
            LAYOUT_FIELDS.filter((field) =>
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
                LAYOUT_FIELDS.forEach((field) => editor.resetPath(field.path))
            }
        >
            {LAYOUT_FIELDS.map((field) => {
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
                            label={__(field.label, TEXT_DOMAIN)}
                            help={
                                field.help !== undefined
                                    ? __(field.help, TEXT_DOMAIN)
                                    : undefined
                            }
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
