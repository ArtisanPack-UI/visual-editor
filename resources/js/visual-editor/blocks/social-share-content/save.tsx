/**
 * Social Share Content — save component.
 *
 * Dynamic block: per-platform share URLs are stamped server-side, so
 * returning `null` keeps Gutenberg from serializing wrapper markup into
 * post_content. (#501)
 */

export default function SocialShareContentSave(): null {
    return null;
}
