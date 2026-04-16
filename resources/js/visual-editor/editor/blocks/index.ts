import { registerParagraphBlock } from './paragraph';
import { registerHeadingBlock } from './heading';
import { registerListBlock } from './list';
import { registerQuoteBlock } from './quote';
import { registerCodeBlock } from './code';
import { registerPreformattedBlock } from './preformatted';

export { registerParagraphBlock, PARAGRAPH_BLOCK_NAME, ParagraphEdit } from './paragraph';
export {
    registerHeadingBlock,
    HEADING_BLOCK_NAME,
    HEADING_LEVELS,
    HeadingEdit,
    normalizeHeadingLevel,
} from './heading';
export {
    registerListBlock,
    LIST_BLOCK_NAME,
    ListEdit,
    normalizeOrdered,
} from './list';
export { registerQuoteBlock, QUOTE_BLOCK_NAME, QuoteEdit } from './quote';
export {
    registerCodeBlock,
    CODE_BLOCK_NAME,
    CODE_LANGUAGES,
    CodeEdit,
    normalizeLanguage,
} from './code';
export {
    registerPreformattedBlock,
    PREFORMATTED_BLOCK_NAME,
    PreformattedEdit,
} from './preformatted';

export function registerCoreBlocks(): void {
    registerParagraphBlock();
    registerHeadingBlock();
    registerListBlock();
    registerQuoteBlock();
    registerCodeBlock();
    registerPreformattedBlock();
}
