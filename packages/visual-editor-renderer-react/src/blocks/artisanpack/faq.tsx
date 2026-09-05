/**
 * React renderer for the `artisanpack/faq` block (#758).
 *
 * Mirrors the visible markup from the Blade partial in
 * `packages/visual-editor-renderer-blade/resources/views/blocks/artisanpack/faq.blade.php`
 * and the edit/save components in `resources/js/visual-editor/blocks/faq/`
 * so all three renderers ship identical DOM. FAQPage JSON-LD is emitted
 * only by the Blade renderer — React/Vue callers can layer their own
 * schema story (e.g. head managers) without fighting a duplicate script.
 */

import { attrString, classList } from '../../support/attributes';
import type { BlockRendererProps } from '../../types';

interface FaqItem {
    question: string;
    answer: string;
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

function normalizeItems(value: unknown): ReadonlyArray<FaqItem> {
    if (!Array.isArray(value)) {
        return [];
    }

    const items: FaqItem[] = [];

    for (const entry of value) {
        if (entry === null || typeof entry !== 'object') {
            continue;
        }

        const raw = entry as Record<string, unknown>;
        const question = attrString(raw.question);
        const answer = attrString(raw.answer);

        if (question.trim() === '' && answer.trim() === '') {
            continue;
        }

        items.push({ question, answer });
    }

    return items;
}

export function FaqBlock({ attributes }: BlockRendererProps): JSX.Element {
    const level = clampHeadingLevel(attributes.headingLevel);
    const items = normalizeItems(attributes.items);
    const className = attrString(attributes.className);

    const classes = classList(['ap-faq', className]);

    const QuestionTag = `h${level}` as 'h2' | 'h3' | 'h4' | 'h5' | 'h6';

    return (
        <div className={classes}>
            {items.map((item, index) => (
                <div key={index} className="ap-faq__item">
                    <QuestionTag
                        className="ap-faq__question"
                        dangerouslySetInnerHTML={{ __html: item.question }}
                    />
                    <div
                        className="ap-faq__answer"
                        dangerouslySetInnerHTML={{ __html: item.answer }}
                    />
                </div>
            ))}
        </div>
    );
}
