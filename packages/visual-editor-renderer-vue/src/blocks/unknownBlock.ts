/**
 * Fallback markup for blocks that have no registered Vue renderer and that
 * the dynamic-block endpoint does not recognize either. Mirrors the
 * `<!-- visual-editor: no partial for ... -->` comment + `<div>` wrapper the
 * Blade renderer emits and the React renderer's unknown-block output.
 */

import { defineComponent, h } from 'vue';

export const UnknownBlock = defineComponent({
    name: 'UnknownBlock',
    props: {
        name: {
            type: String,
            required: true,
        },
    },
    setup(props, { slots }) {
        return () =>
            h(
                'div',
                { 'data-ve-unknown-block': props.name },
                slots.default ? slots.default() : undefined
            );
    },
});
