import { registerBlockType, type BlockJsonMetadata } from '../../registry';
import rawMetadata from './block.json';
import QuoteEdit, { QUOTE_BLOCK_NAME } from './edit';

const metadata = rawMetadata as unknown as BlockJsonMetadata;

export function registerQuoteBlock(): void {
    registerBlockType(metadata, {
        edit: QuoteEdit,
        factory: () => ({
            name: QUOTE_BLOCK_NAME,
            attributes: { content: '<blockquote><p></p></blockquote>', citation: '' },
            innerBlocks: [],
        }),
    });
}

export { QUOTE_BLOCK_NAME };
export { default as QuoteEdit } from './edit';
