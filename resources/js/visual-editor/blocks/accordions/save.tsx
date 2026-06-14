/**
 * Accordions — save component.
 *
 * The accordion family ships as dynamic (server-rendered) blocks: each
 * renderer reads the persisted inner-block tree and emits the final
 * markup. Returning `null` keeps Gutenberg from serializing redundant
 * wrapper markup into post_content.
 */

export default function AccordionsSave(): null {
    return null;
}
