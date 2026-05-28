/**
 * Paragraph — editor-side render.
 *
 * Ported from `@wordpress/block-library/src/paragraph/edit.js` (v9.43.0).
 * Behaviour parity is the goal: drop-cap toggle, RTL toolbar control, the
 * empty-paragraph Enter escape hatch, and the prefix-transform/embed-on-paste
 * affordances are preserved verbatim. The only intentional divergence is the
 * `'wp-block-paragraph'` class added to `useBlockProps` — upstream relies on
 * `__experimentalSelector` to inject it on the saved markup, but we add it
 * explicitly here so the editor canvas and frontend match without depending
 * on private block-editor internals.
 */

import type { ReactElement } from 'react';
import clsx from 'clsx';
import { __, _x, isRTL } from '@wordpress/i18n';
import {
    ToolbarButton,
    ToggleControl,
    __experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import {
    BlockControls,
    InspectorControls,
    RichText,
    useBlockProps,
    useSettings,
    useBlockEditingMode,
} from '@wordpress/block-editor';
import { getBlockSupport } from '@wordpress/blocks';
import { formatLTR } from '@wordpress/icons';

import useDeprecatedAlign from './use-deprecated-align';
import { useOnEnter } from './use-enter';

interface ParagraphStyle {
    readonly typography?: {
        readonly textAlign?: string;
    } & Record<string, unknown>;
}

interface ParagraphAttributes {
    readonly content: string;
    readonly direction?: 'ltr' | 'rtl';
    readonly dropCap: boolean;
    readonly placeholder?: string;
    readonly align?: string;
    readonly style?: ParagraphStyle;
}

interface ParagraphEditProps {
    readonly attributes: ParagraphAttributes;
    readonly setAttributes: (next: Partial<ParagraphAttributes>) => void;
    readonly mergeBlocks?: (forward?: boolean) => void;
    readonly onReplace?: (...args: unknown[]) => void;
    readonly onRemove?: () => void;
    readonly clientId: string;
    readonly isSelected: boolean;
    readonly name: string;
}

interface ParagraphRTLControlProps {
    readonly direction: string | undefined;
    readonly setDirection: (next: string | undefined) => void;
}

function ParagraphRTLControl({ direction, setDirection }: ParagraphRTLControlProps): ReactElement | false {
    return (
        isRTL() && (
            <ToolbarButton
                icon={formatLTR}
                title={_x('Left to right', 'editor button')}
                isActive={direction === 'ltr'}
                onClick={() => {
                    setDirection(direction === 'ltr' ? undefined : 'ltr');
                }}
            />
        )
    );
}

function hasDropCapDisabled(
    align: string | undefined,
    direction: 'ltr' | 'rtl' | undefined
): boolean {
    const effectiveIsRtl = direction ? direction === 'rtl' : isRTL();
    return align === (effectiveIsRtl ? 'left' : 'right') || align === 'center';
}

interface DropCapControlProps {
    readonly clientId: string;
    readonly attributes: ParagraphAttributes;
    readonly setAttributes: (next: Partial<ParagraphAttributes>) => void;
    readonly name: string;
}

function DropCapControl({
    clientId,
    attributes,
    setAttributes,
    name,
}: DropCapControlProps): ReactElement | null {
    // Per upstream guidance: keep this `useSettings` scoped to the inspector
    // so the subscription is only attached for the selected paragraph(s).
    const [isDropCapFeatureEnabled] = useSettings('typography.dropCap');

    if (!isDropCapFeatureEnabled) {
        return null;
    }

    const { style, dropCap, direction } = attributes;
    const textAlign = style?.typography?.textAlign;

    let helpText;
    if (hasDropCapDisabled(textAlign, direction)) {
        helpText = __('Not available for aligned text.');
    } else if (dropCap) {
        helpText = __('Showing large initial letter.');
    } else {
        helpText = __('Show a large initial letter.');
    }

    const isDropCapControlEnabledByDefault = getBlockSupport(
        name,
        'typography.defaultControls.dropCap',
        false
    );

    return (
        <InspectorControls group="typography">
            <ToolsPanelItem
                hasValue={() => !!dropCap}
                label={__('Drop cap')}
                isShownByDefault={isDropCapControlEnabledByDefault}
                onDeselect={() => setAttributes({ dropCap: false })}
                resetAllFilter={() => ({ dropCap: false })}
                panelId={clientId}
            >
                <ToggleControl
                    label={__('Drop cap')}
                    checked={!!dropCap}
                    onChange={() => setAttributes({ dropCap: !dropCap })}
                    help={helpText}
                    disabled={hasDropCapDisabled(textAlign, direction)}
                />
            </ToolsPanelItem>
        </InspectorControls>
    );
}

export default function ParagraphEdit({
    attributes,
    mergeBlocks,
    onReplace,
    onRemove,
    setAttributes,
    clientId,
    isSelected: isSingleSelected,
    name,
}: ParagraphEditProps): ReactElement {
    const { content, direction, dropCap, placeholder, style } = attributes;
    const textAlign = style?.typography?.textAlign;
    useDeprecatedAlign(attributes.align, style, setAttributes as (a: Record<string, unknown>) => void);
    const blockProps = useBlockProps({
        ref: useOnEnter({ clientId, content }),
        className: clsx('wp-block-paragraph', {
            'has-drop-cap': hasDropCapDisabled(textAlign, direction) ? false : dropCap,
        }),
        style: { direction },
    });
    const blockEditingMode = useBlockEditingMode();

    return (
        <>
            {blockEditingMode === 'default' && (
                <BlockControls group="block">
                    <ParagraphRTLControl
                        direction={direction}
                        setDirection={(newDirection) =>
                            setAttributes({
                                direction: newDirection as 'ltr' | 'rtl' | undefined,
                            })
                        }
                    />
                </BlockControls>
            )}
            {isSingleSelected && (
                <DropCapControl
                    name={name}
                    clientId={clientId}
                    attributes={attributes}
                    setAttributes={setAttributes}
                />
            )}
            <RichText
                identifier="content"
                tagName="p"
                {...blockProps}
                value={content}
                onChange={(newContent: string) =>
                    setAttributes({ content: newContent })
                }
                onMerge={mergeBlocks}
                onReplace={onReplace}
                onRemove={onRemove}
                aria-label={
                    RichText.isEmpty(content)
                        ? __(
                              'Empty block; start writing or type forward slash to choose a block'
                          )
                        : __('Block: Paragraph')
                }
                data-empty={RichText.isEmpty(content)}
                placeholder={placeholder || __('Type / to choose a block')}
                data-custom-placeholder={placeholder ? true : undefined}
                __unstableEmbedURLOnPaste
                __unstableAllowPrefixTransformations
            />
        </>
    );
}
