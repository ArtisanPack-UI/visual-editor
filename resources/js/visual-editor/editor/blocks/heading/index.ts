import { registerBlock } from '../../registry';
import HeadingEdit, {
    HEADING_BLOCK_NAME,
    HEADING_LEVELS,
    normalizeHeadingLevel,
} from './edit';

export function registerHeadingBlock(): void {
    registerBlock({
        name: HEADING_BLOCK_NAME,
        edit: HeadingEdit,
    });
}

export { HEADING_BLOCK_NAME, HEADING_LEVELS, normalizeHeadingLevel };
export { default as HeadingEdit } from './edit';
