/**
 * FAQ — editor-side component.
 *
 * Dedicated FAQ block (#758). Stores question/answer pairs as an
 * `items` attribute array — a lighter shape than nesting inner blocks
 * for a semantically fixed structure (question + answer). Users edit
 * each field with `RichText`, and the inspector exposes the heading
 * level used for the question elements plus a toggle that lets a page
 * turn off JSON-LD emission when FAQ schema is already provided
 * elsewhere (avoids double-emission with `artisanpack/accordions`'
 * `faqSchema` toggle or a page-level schema block).
 */

import type { ReactElement } from 'react';
import {
    InspectorControls,
    RichText,
    useBlockProps,
} from '@wordpress/block-editor';
import {
    Button,
    PanelBody,
    SelectControl,
    ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { TEXT_DOMAIN } from '../../vendor/i18n';

const HEADING_LEVELS: ReadonlyArray<2 | 3 | 4 | 5 | 6> = [2, 3, 4, 5, 6];

export interface FaqItem {
    readonly question: string;
    readonly answer: string;
}

interface FaqAttributes {
    readonly items: ReadonlyArray<FaqItem>;
    readonly headingLevel: number;
    readonly emitSchema: boolean;
}

interface FaqEditProps {
    readonly attributes: FaqAttributes;
    readonly setAttributes: (next: Partial<FaqAttributes>) => void;
}

function clampHeadingLevel(value: unknown): 2 | 3 | 4 | 5 | 6 {
    const numeric = typeof value === 'number' ? value : Number(value);

    if (!Number.isFinite(numeric)) {
        return 3;
    }

    const rounded = Math.round(numeric);

    if (rounded <= 2) {
        return 2;
    }

    if (rounded >= 6) {
        return 6;
    }

    return rounded as 2 | 3 | 4 | 5 | 6;
}

export default function FaqEdit({
    attributes,
    setAttributes,
}: FaqEditProps): ReactElement {
    const { items, headingLevel, emitSchema } = attributes;

    const blockProps = useBlockProps({ className: 'ap-faq' });

    const level = clampHeadingLevel(headingLevel);
    const QuestionTag = `h${level}` as 'h2' | 'h3' | 'h4' | 'h5' | 'h6';

    const list: ReadonlyArray<FaqItem> = items.length > 0 ? items : [{ question: '', answer: '' }];

    function updateItem(index: number, patch: Partial<FaqItem>): void {
        const next = list.map((item, i) => (i === index ? { ...item, ...patch } : item));
        setAttributes({ items: next });
    }

    function addItem(): void {
        setAttributes({ items: [...list, { question: '', answer: '' }] });
    }

    function removeItem(index: number): void {
        // Keep at least one editable pair so the block never renders empty.
        if (list.length <= 1) {
            setAttributes({ items: [{ question: '', answer: '' }] });
            return;
        }

        setAttributes({ items: list.filter((_, i) => i !== index) });
    }

    function moveItem(index: number, delta: -1 | 1): void {
        const target = index + delta;

        if (target < 0 || target >= list.length) {
            return;
        }

        const next = list.slice();
        const [moved] = next.splice(index, 1);
        next.splice(target, 0, moved);
        setAttributes({ items: next });
    }

    return (
        <>
            <InspectorControls>
                <PanelBody title={__('FAQ settings', TEXT_DOMAIN)} initialOpen>
                    <SelectControl
                        label={__('Question heading level', TEXT_DOMAIN)}
                        value={String(level)}
                        options={HEADING_LEVELS.map((value) => ({
                            label: `H${value}`,
                            value: String(value),
                        }))}
                        onChange={(value) =>
                            setAttributes({ headingLevel: clampHeadingLevel(value) })
                        }
                        __nextHasNoMarginBottom
                    />
                    <ToggleControl
                        label={__('Emit FAQPage schema', TEXT_DOMAIN)}
                        help={__(
                            'Adds FAQPage JSON-LD built from the question and answer pairs. Turn off if the page already emits FAQ schema elsewhere (e.g. the accordions FAQ toggle) — avoid double-emission.',
                            TEXT_DOMAIN
                        )}
                        checked={emitSchema}
                        onChange={(next) => setAttributes({ emitSchema: next })}
                        __nextHasNoMarginBottom
                    />
                </PanelBody>
            </InspectorControls>
            <div {...blockProps}>
                {list.map((item, index) => (
                    <div key={index} className="ap-faq__item">
                        <RichText
                            tagName={QuestionTag}
                            className="ap-faq__question"
                            value={item.question}
                            onChange={(value: string) => updateItem(index, { question: value })}
                            placeholder={__('Question…', TEXT_DOMAIN)}
                            allowedFormats={['core/bold', 'core/italic', 'core/link']}
                        />
                        <RichText
                            tagName="div"
                            className="ap-faq__answer"
                            value={item.answer}
                            onChange={(value: string) => updateItem(index, { answer: value })}
                            placeholder={__('Answer…', TEXT_DOMAIN)}
                        />
                        <div className="ap-faq__item-controls">
                            <Button
                                size="small"
                                variant="tertiary"
                                onClick={() => moveItem(index, -1)}
                                disabled={index === 0}
                            >
                                {__('Move up', TEXT_DOMAIN)}
                            </Button>
                            <Button
                                size="small"
                                variant="tertiary"
                                onClick={() => moveItem(index, 1)}
                                disabled={index === list.length - 1}
                            >
                                {__('Move down', TEXT_DOMAIN)}
                            </Button>
                            <Button
                                size="small"
                                variant="tertiary"
                                isDestructive
                                onClick={() => removeItem(index)}
                            >
                                {__('Remove', TEXT_DOMAIN)}
                            </Button>
                        </div>
                    </div>
                ))}
                <Button variant="secondary" onClick={addItem}>
                    {__('Add question', TEXT_DOMAIN)}
                </Button>
            </div>
        </>
    );
}
