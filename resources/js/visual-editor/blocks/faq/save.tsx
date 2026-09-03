/**
 * FAQ — saved markup.
 *
 * Persists the frontend DOM shape for the FAQ block (#758). JSON-LD is
 * *not* embedded here; the Blade renderer emits `FAQPage` JSON-LD at
 * request time so authors can toggle `emitSchema` without dirtying the
 * saved markup, and so double-emission with the accordions FAQ toggle
 * can be avoided per-render.
 */

import type { ReactElement } from 'react';
import { RichText, useBlockProps } from '@wordpress/block-editor';

interface FaqItem {
    readonly question: string;
    readonly answer: string;
}

interface FaqAttributes {
    readonly items: ReadonlyArray<FaqItem>;
    readonly headingLevel: number;
    readonly emitSchema: boolean;
}

interface FaqSaveProps {
    readonly attributes: FaqAttributes;
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

export default function FaqSave({ attributes }: FaqSaveProps): ReactElement {
    const { items, headingLevel } = attributes;

    const blockProps = useBlockProps.save({ className: 'ap-faq' });

    const level = clampHeadingLevel(headingLevel);
    const QuestionTag = `h${level}` as 'h2' | 'h3' | 'h4' | 'h5' | 'h6';

    return (
        <div {...blockProps}>
            {items.map((item, index) => (
                <div key={index} className="ap-faq__item">
                    <RichText.Content
                        tagName={QuestionTag}
                        className="ap-faq__question"
                        value={item.question}
                    />
                    <RichText.Content
                        tagName="div"
                        className="ap-faq__answer"
                        value={item.answer}
                    />
                </div>
            ))}
        </div>
    );
}
