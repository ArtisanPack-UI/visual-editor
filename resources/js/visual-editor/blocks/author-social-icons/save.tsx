/**
 * Author Social Icons — save component.
 *
 * Dynamic block: the resolved author profile URLs are stamped server-
 * side, so returning `null` keeps Gutenberg from serializing wrapper
 * markup into post_content. (#501)
 */

export default function AuthorSocialIconsSave(): null {
    return null;
}
