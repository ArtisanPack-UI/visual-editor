import { registerBlock } from '../../registry';
import ParagraphEdit from './edit';

export const PARAGRAPH_BLOCK_NAME = 've/paragraph';

export function registerParagraphBlock(): void {
    registerBlock({
        name: PARAGRAPH_BLOCK_NAME,
        edit: ParagraphEdit,
    });
}

export { default as ParagraphEdit } from './edit';
