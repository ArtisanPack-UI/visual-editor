import { registerBlock } from '../../registry';
import ListEdit, { LIST_BLOCK_NAME, normalizeOrdered } from './edit';

export function registerListBlock(): void {
    registerBlock({
        name: LIST_BLOCK_NAME,
        edit: ListEdit,
    });
}

export { LIST_BLOCK_NAME, normalizeOrdered };
export { default as ListEdit } from './edit';
