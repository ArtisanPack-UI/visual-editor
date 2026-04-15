import { registerParagraphBlock } from './paragraph';
import { registerHeadingBlock } from './heading';

export { registerParagraphBlock, PARAGRAPH_BLOCK_NAME, ParagraphEdit } from './paragraph';
export {
    registerHeadingBlock,
    HEADING_BLOCK_NAME,
    HEADING_LEVELS,
    HeadingEdit,
    normalizeHeadingLevel,
} from './heading';

export function registerCoreBlocks(): void {
    registerParagraphBlock();
    registerHeadingBlock();
}
