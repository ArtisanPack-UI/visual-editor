/**
 * Vue renderer for the `artisanpack/comments-number` block (#500).
 *
 * Mirrors the Blade partial and the React renderer so every
 * environment emits identical markup.
 */

import { defineComponent, h } from 'vue';

import { attrInt, attrString, classList } from '../../support/attributes';
import { blockRendererProps } from '../shared';

export const CommentsNumberBlock = defineComponent({
    name: 'CommentsNumberBlock',
    props: blockRendererProps,
    setup(props) {
        return () => {
            const count = Math.max(
                0,
                attrInt(props.attributes._resolvedCommentCount, 0)
            );
            const singular = attrString(props.attributes.singularCommentText, 'Comment');
            const plural = attrString(props.attributes.pluralCommentText, 'Comments');
            const label = count === 1 ? singular : plural;
            const className = attrString(props.attributes.className);

            const classes = classList(['ap-comments-number', className]);
            const line = `${count} ${label}`.trim();

            return h('p', { class: classes }, line);
        };
    },
});
