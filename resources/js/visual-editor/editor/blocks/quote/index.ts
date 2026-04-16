import { registerBlock } from '../../registry';
import QuoteEdit, { QUOTE_BLOCK_NAME } from './edit';

export function registerQuoteBlock(): void {
    registerBlock({
        name: QUOTE_BLOCK_NAME,
        edit: QuoteEdit,
    });
}

export { QUOTE_BLOCK_NAME };
export { default as QuoteEdit } from './edit';
