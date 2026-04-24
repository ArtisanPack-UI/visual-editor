/**
 * Blocks panels.
 *
 * The "Blocks" navigator node exposes two distinct panels — an index
 * that lists the registered block types, and a per-block detail panel
 * the user opens by picking a block. Only blocks registered through the
 * package's BlockTypeRegistry are shown (per issue #370's out-of-scope
 * note: we don't surface every core block if it's disabled).
 */

import { Button, PanelRow } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useMemo } from 'react';

import { TEXT_DOMAIN } from '../../../vendor/i18n';
import type { ValidationErrors } from '../../api-client';
import type { StyleBlock } from '../styles-navigator';
import type { UseGlobalStylesEditorResult } from '../use-global-styles-editor';
import { StylePanelSection } from './panel-controls';
import {
    renderStyleField,
    type StyleFieldDescriptor,
} from './styles-fields';
import { useStylePresets } from './use-preset-data';

export interface BlocksPanelProps {
    editor: UseGlobalStylesEditorResult;
    validationErrors: ValidationErrors | null;
    blocks: readonly StyleBlock[];
    selectedBlockName: string | null;
    onSelectBlock: (blockName: string | null) => void;
}

interface BlockField extends StyleFieldDescriptor {
    key: readonly string[];
}

const BLOCK_FIELDS: readonly BlockField[] = [
    {
        label: 'Background color',
        key: ['color', 'background'],
        testId: 'block-color-background',
        kind: 'color',
    },
    {
        label: 'Text color',
        key: ['color', 'text'],
        testId: 'block-color-text',
        kind: 'color',
    },
    {
        label: 'Font family',
        key: ['typography', 'fontFamily'],
        testId: 'block-font-family',
        kind: 'font-family',
    },
    {
        label: 'Font size',
        key: ['typography', 'fontSize'],
        testId: 'block-font-size',
        kind: 'font-size',
    },
    {
        label: 'Border radius',
        key: ['border', 'radius'],
        testId: 'block-border-radius',
        kind: 'size',
    },
];

export function BlocksIndexPanel(
    props: BlocksPanelProps
): JSX.Element {
    const { editor, blocks, onSelectBlock } = props;

    const customizedBlockNames = useMemo(() => {
        const customized: string[] = [];

        for (const block of blocks) {
            if (
                editor.isPathCustomized([
                    'styles',
                    'blocks',
                    block.name,
                ])
            ) {
                customized.push(block.name);
            }
        }

        return customized;
    }, [blocks, editor]);

    return (
        <StylePanelSection
            testId="ap-site-editor-style-panel-blocks-index"
            title={__('Blocks', TEXT_DOMAIN)}
            customizedCount={customizedBlockNames.length}
            onResetSection={() => editor.resetPath(['styles', 'blocks'])}
            description={__(
                'Override styles per block. Pick a block from the navigator to edit its overrides.',
                TEXT_DOMAIN
            )}
        >
            <PanelRow>
                <div
                    className="ap-site-editor__style-listing"
                    data-testid={
                        blocks.length === 0
                            ? 'ap-site-editor-style-panel-blocks-empty'
                            : 'ap-site-editor-style-panel-blocks-list'
                    }
                >
                    {blocks.length === 0 ? (
                        <p className="ap-site-editor__style-panel-description">
                            {__(
                                'No blocks are registered through the package registry.',
                                TEXT_DOMAIN
                            )}
                        </p>
                    ) : (
                        <ul className="ap-site-editor__style-listing-list">
                            {blocks.map((block) => {
                                const isCustomized = customizedBlockNames.includes(
                                    block.name
                                );
                                const label = block.title ?? block.name;

                                return (
                                    <li
                                        key={block.name}
                                        className="ap-site-editor__style-listing-item"
                                    >
                                        <Button
                                            variant="tertiary"
                                            className="ap-site-editor__style-listing-link"
                                            data-customized={isCustomized}
                                            data-testid={`ap-site-editor-style-panel-block-${block.name}`}
                                            onClick={() =>
                                                onSelectBlock(block.name)
                                            }
                                        >
                                            <span className="ap-site-editor__style-listing-label">
                                                {label}
                                            </span>
                                            <code className="ap-site-editor__style-listing-code">
                                                {block.name}
                                            </code>
                                            {isCustomized ? (
                                                <span className="ap-site-editor__style-panel-customized">
                                                    {__(
                                                        'Customized',
                                                        TEXT_DOMAIN
                                                    )}
                                                </span>
                                            ) : null}
                                        </Button>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </div>
            </PanelRow>
        </StylePanelSection>
    );
}

export interface BlockDetailPanelProps
    extends Omit<BlocksPanelProps, 'selectedBlockName'> {
    selectedBlockName: string;
}

export function BlockDetailPanel(
    props: BlockDetailPanelProps
): JSX.Element {
    const {
        editor,
        validationErrors,
        blocks,
        selectedBlockName,
        onSelectBlock,
    } = props;
    const presets = useStylePresets(editor);

    const block = useMemo(
        () => blocks.find((entry) => entry.name === selectedBlockName),
        [blocks, selectedBlockName]
    );

    const customizedCount = useMemo(
        () =>
            BLOCK_FIELDS.filter((field) =>
                editor.isPathCustomized([
                    'styles',
                    'blocks',
                    selectedBlockName,
                    ...field.key,
                ])
            ).length,
        [editor, selectedBlockName]
    );

    const label = block?.title ?? selectedBlockName;

    return (
        <div
            className="ap-site-editor__style-panel-wrapper"
            data-testid="ap-site-editor-style-panel-block-detail"
            data-block={selectedBlockName}
        >
            <PanelRow>
                <Button
                    variant="tertiary"
                    size="small"
                    data-testid="ap-site-editor-style-panel-block-back"
                    onClick={() => onSelectBlock(null)}
                >
                    {__('← Back to blocks list', TEXT_DOMAIN)}
                </Button>
            </PanelRow>
            <StylePanelSection
                title={sprintf(
                    /* translators: %s: block title or name. */
                    __('Block: %s', TEXT_DOMAIN),
                    label
                )}
                customizedCount={customizedCount}
                onResetSection={() =>
                    editor.resetPath([
                        'styles',
                        'blocks',
                        selectedBlockName,
                    ])
                }
            >
                {BLOCK_FIELDS.map((field) =>
                    renderStyleField({
                        editor,
                        validationErrors,
                        presets,
                        descriptor: field,
                        path: [
                            'styles',
                            'blocks',
                            selectedBlockName,
                            ...field.key,
                        ],
                    })
                )}
            </StylePanelSection>
        </div>
    );
}

// Not every panel uses the index's index-only props; re-export so the
// `styles-index` panel keeps a single prop shape.
export type { BlocksPanelProps as BlocksIndexPanelProps };
