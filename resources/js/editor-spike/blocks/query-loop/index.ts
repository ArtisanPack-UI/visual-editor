import { registerBlock } from '../../registry';
import QueryLoopEdit from './edit';

export const QUERY_LOOP_BLOCK_NAME = 've/query-loop';

export function registerQueryLoopBlock(): void {
    registerBlock({
        name: QUERY_LOOP_BLOCK_NAME,
        edit: QueryLoopEdit,
    });
}

export { default as QueryLoopEdit } from './edit';
