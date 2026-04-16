import { registerBlock } from '../../registry';
import PreformattedEdit, { PREFORMATTED_BLOCK_NAME } from './edit';

export function registerPreformattedBlock(): void {
    registerBlock({
        name: PREFORMATTED_BLOCK_NAME,
        edit: PreformattedEdit,
    });
}

export { PREFORMATTED_BLOCK_NAME };
export { default as PreformattedEdit } from './edit';
