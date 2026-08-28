/**
 * Icon — saved markup.
 *
 * `artisanpack/icon` is a dynamic (server-rendered) block. Its public
 * markup is produced by {@see IconBlock::render()} on the server, and the
 * Blade / React / Vue frontend renderers re-render it from the saved
 * attributes when hydrating a saved block tree. Nothing consumes the
 * block's saved inner HTML on the front end.
 *
 * Following the package's dynamic-block convention (`read-more`,
 * `site-logo`, `post-author`, …), `save` therefore returns `null` so only
 * the block delimiter + attributes are persisted. Emitting real markup
 * here — as earlier builds did — forced that markup to round-trip through
 * the parser forever, and any drift (support-injected classes, bundle
 * skew, a group parent re-serializing its children) surfaced as "Block
 * contains unexpected or invalid content" (#749). Icons saved by those
 * builds are migrated by `deprecated.tsx`.
 */

export default function IconSave(): null {
    return null;
}
