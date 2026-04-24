/**
 * Elements panels.
 *
 * Schema v3's `styles.elements.*` key contains per-element overrides
 * (link, button, heading, h1–h6, caption). The navigator lists each
 * scope; picking one opens a detail panel scoped to that element's
 * color / typography leaves rendered through the same Gutenberg
 * primitives the rest of the editor uses.
 */

import { Button, PanelRow } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useMemo } from 'react';

import { TEXT_DOMAIN } from '../../../vendor/i18n';
import type { ValidationErrors } from '../../api-client';
import {
    ELEMENT_ORDER,
    getElementLabel,
    type ElementScope,
} from '../styles-navigator-tree';
import type { UseGlobalStylesEditorResult } from '../use-global-styles-editor';
import { StylePanelSection } from './panel-controls';
import {
    renderStyleField,
    type StyleFieldDescriptor,
} from './styles-fields';
import { useStylePresets } from './use-preset-data';

export interface ElementsIndexPanelProps {
    editor: UseGlobalStylesEditorResult;
    onSelectElement: (element: ElementScope) => void;
}

interface ElementField extends StyleFieldDescriptor {
    key: readonly string[];
}

const ELEMENT_FIELDS: readonly ElementField[] = [
    {
        label: 'Text color',
        key: ['color', 'text'],
        testId: 'element-color-text',
        kind: 'color',
    },
    {
        label: 'Background color',
        key: ['color', 'background'],
        testId: 'element-color-background',
        kind: 'color',
    },
    {
        label: 'Font family',
        key: ['typography', 'fontFamily'],
        testId: 'element-font-family',
        kind: 'font-family',
    },
    {
        label: 'Font size',
        key: ['typography', 'fontSize'],
        testId: 'element-font-size',
        kind: 'font-size',
    },
    {
        label: 'Font weight',
        key: ['typography', 'fontWeight'],
        testId: 'element-font-weight',
        kind: 'font-weight',
    },
];

export function ElementsIndexPanel(
    props: ElementsIndexPanelProps
): JSX.Element {
    const { editor, onSelectElement } = props;

    const customizedScopes = useMemo(() => {
        const customized: ElementScope[] = [];

        for (const element of ELEMENT_ORDER) {
            if (
                editor.isPathCustomized([
                    'styles',
                    'elements',
                    element,
                ])
            ) {
                customized.push(element);
            }
        }

        return customized;
    }, [editor]);

    return (
        <StylePanelSection
            testId="ap-site-editor-style-panel-elements-index"
            title={__('Elements', TEXT_DOMAIN)}
            customizedCount={customizedScopes.length}
            onResetSection={() => editor.resetPath(['styles', 'elements'])}
            description={__(
                'Override typography and color for the built-in HTML elements — links, buttons, headings, captions.',
                TEXT_DOMAIN
            )}
        >
            <PanelRow>
                <ul
                    className="ap-site-editor__style-listing-list"
                    data-testid="ap-site-editor-style-panel-elements-list"
                >
                    {ELEMENT_ORDER.map((element) => {
                        const isCustomized = customizedScopes.includes(
                            element
                        );

                        return (
                            <li
                                key={element}
                                className="ap-site-editor__style-listing-item"
                            >
                                <Button
                                    variant="tertiary"
                                    className="ap-site-editor__style-listing-link"
                                    data-customized={isCustomized}
                                    data-testid={`ap-site-editor-style-panel-element-${element}`}
                                    onClick={() => onSelectElement(element)}
                                >
                                    <span className="ap-site-editor__style-listing-label">
                                        {getElementLabel(element)}
                                    </span>
                                    {isCustomized ? (
                                        <span className="ap-site-editor__style-panel-customized">
                                            {__('Customized', TEXT_DOMAIN)}
                                        </span>
                                    ) : null}
                                </Button>
                            </li>
                        );
                    })}
                </ul>
            </PanelRow>
        </StylePanelSection>
    );
}

export interface ElementDetailPanelProps {
    editor: UseGlobalStylesEditorResult;
    validationErrors: ValidationErrors | null;
    element: ElementScope;
    onBack: () => void;
}

export function ElementDetailPanel(
    props: ElementDetailPanelProps
): JSX.Element {
    const { editor, validationErrors, element, onBack } = props;
    const presets = useStylePresets(editor);

    const customizedCount = useMemo(
        () =>
            ELEMENT_FIELDS.filter((field) =>
                editor.isPathCustomized([
                    'styles',
                    'elements',
                    element,
                    ...field.key,
                ])
            ).length,
        [editor, element]
    );

    return (
        <div
            className="ap-site-editor__style-panel-wrapper"
            data-testid="ap-site-editor-style-panel-element-detail"
            data-element={element}
        >
            <PanelRow>
                <Button
                    variant="tertiary"
                    size="small"
                    data-testid="ap-site-editor-style-panel-element-back"
                    onClick={onBack}
                >
                    {__('← Back to elements list', TEXT_DOMAIN)}
                </Button>
            </PanelRow>
            <StylePanelSection
                title={sprintf(
                    /* translators: %s: element label (e.g. "Link"). */
                    __('Element: %s', TEXT_DOMAIN),
                    getElementLabel(element)
                )}
                customizedCount={customizedCount}
                onResetSection={() =>
                    editor.resetPath(['styles', 'elements', element])
                }
            >
                {ELEMENT_FIELDS.map((field) =>
                    renderStyleField({
                        editor,
                        validationErrors,
                        presets,
                        descriptor: field,
                        path: [
                            'styles',
                            'elements',
                            element,
                            ...field.key,
                        ],
                    })
                )}
            </StylePanelSection>
        </div>
    );
}
