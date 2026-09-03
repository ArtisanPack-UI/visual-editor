/**
 * Vue renderer for the `artisanpack/faq` block (#758).
 *
 * Mirrors the visible markup from the Blade partial and React renderer so
 * every rendering environment emits identical DOM for a saved FAQ tree.
 * FAQPage JSON-LD is emitted only by the Blade renderer.
 */

import { defineComponent, h } from 'vue';

import { attrString, classList } from '../../support/attributes';
import { blockRendererProps } from '../shared';

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

export const FaqBlock = defineComponent({
    name: 'FaqBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const level = clampHeadingLevel(props.attributes.headingLevel);
            const items = normalizeItems(props.attributes.items);
            const className = attrString(props.attributes.className);

            const classes = classList(['ap-faq', className]);

            const questionTag = `h${level}`;

            return h(
                'div',
                { class: classes },
                items.map((item, index) =>
                    h('div', { key: index, class: 'ap-faq__item' }, [
                        h(questionTag, {
                            class: 'ap-faq__question',
                            innerHTML: item.question,
                        }),
                        h('div', {
                            class: 'ap-faq__answer',
                            innerHTML: item.answer,
                        }),
                    ]),
                ),
            );
        };
    },
});
