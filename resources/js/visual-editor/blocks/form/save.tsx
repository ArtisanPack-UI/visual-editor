/**
 * Form — save component.
 *
 * Dynamic block: the saved markup is just the block delimiter (`<!-- wp:
 * artisanpack/form {"formId":N} /-->`) and the actual HTML is generated
 * server-side by `FormBlock::render()`. Returning `null` from save is
 * the Gutenberg convention for dynamic blocks.
 */

export default function FormSave(): null {
    return null;
}
