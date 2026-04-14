import { registerBlock } from '../../registry';
import ParagraphEdit, { PARAGRAPH_BLOCK_NAME } from './edit';

export function registerParagraphBlock(): void {
    registerBlock({
        name: PARAGRAPH_BLOCK_NAME,
        edit: ParagraphEdit,
    });
}

export { PARAGRAPH_BLOCK_NAME };
export { default as ParagraphEdit } from './edit';
