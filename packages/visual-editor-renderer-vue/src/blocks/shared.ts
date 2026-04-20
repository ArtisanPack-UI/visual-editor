/**
 * Shared prop definitions for every core block renderer.
 *
 * Every block component accepts the same `{ name, attributes, innerBlocks }`
 * contract so the {@link BlockTree} walker can hand off rendering by name
 * without knowing which renderer will actually run.
 */

import type { PropType } from 'vue';
import type { Block } from '../types';

export const blockRendererProps = {
    name: {
        type: String,
        required: true as const,
    },
    attributes: {
        type: Object as PropType<Record<string, unknown>>,
        required: true as const,
    },
    innerBlocks: {
        type: Array as PropType<Block[]>,
        required: true as const,
    },
};
