/**
 * Inline SVG path definitions for the social-icons block family (#501).
 *
 * Both `artisanpack/author-social-icons` and
 * `artisanpack/social-share-content` render the same visual chip per
 * platform — author-side links to the author profile, share-side links
 * to the platform share URL. The path map lives here so the editor edit
 * components and the renderer parity tests stay in sync without each
 * block redeclaring its own icon set.
 */

export interface SocialIconDefinition {
    readonly slug: string;
    readonly label: string;
    readonly path: string;
}

const AUTHOR_PLATFORMS: ReadonlyArray<SocialIconDefinition> = [
    {
        slug: 'facebook',
        label: 'Facebook',
        path: 'M13 7h3V4h-3a4 4 0 0 0-4 4v2H6v3h3v7h3v-7h3l1-3h-4V8a1 1 0 0 1 1-1Z',
    },
    {
        slug: 'twitter',
        label: 'Twitter',
        path: 'M18 4h2.5l-5.5 6.3L21 20h-5.1l-4-5.2L7.3 20H4.8l5.9-6.7L4 4h5.2l3.6 4.8L18 4Z',
    },
    {
        slug: 'mastodon',
        label: 'Mastodon',
        path: 'M12 3c5 0 8 2 8 6v5c0 3-2 5-5 5h-3l-2-1v-2l2 1h2c2 0 3-1 3-3v-1h-2v-4a3 3 0 0 0-6 0v4H7v1c0 2 1 3 3 3l3-1v2l-3 1h-2c-3 0-5-2-5-5V9c0-4 3-6 8-6Z',
    },
    {
        slug: 'instagram',
        label: 'Instagram',
        path: 'M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4Zm0 2a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H7Zm5 3a4 4 0 1 1 0 8 4 4 0 0 1 0-8Zm0 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm5-3a1 1 0 1 1 0 2 1 1 0 0 1 0-2Z',
    },
    {
        slug: 'tumblr',
        label: 'Tumblr',
        path: 'M13 3h3v3h3v3h-3v6c0 1 1 2 2 2h1v3h-2c-3 0-5-2-5-5V9h-2V7c2 0 3-1 3-3v-1Z',
    },
    {
        slug: 'email',
        label: 'Email',
        path: 'M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm1 3v10h14V8l-7 5-7-5Zm0-1 7 5 7-5H5Z',
    },
    {
        slug: 'website',
        label: 'Website',
        path: 'M12 3a9 9 0 1 1 0 18 9 9 0 0 1 0-18Zm-2 2a7 7 0 0 0-5 4h3a13 13 0 0 1 2-4Zm4 0a13 13 0 0 1 2 4h3a7 7 0 0 0-5-4Zm-2 0a11 11 0 0 0-2 4h4a11 11 0 0 0-2-4ZM5 11a7 7 0 0 0 0 2h3a13 13 0 0 1 0-2H5Zm5 0a11 11 0 0 0 0 2h4a11 11 0 0 0 0-2h-4Zm6 0a13 13 0 0 1 0 2h3a7 7 0 0 0 0-2h-3Zm-11 4a7 7 0 0 0 5 4 13 13 0 0 1-2-4H5Zm5 0a11 11 0 0 0 2 4 11 11 0 0 0 2-4h-4Zm6 0a13 13 0 0 1-2 4 7 7 0 0 0 5-4h-3Z',
    },
];

const SHARE_PLATFORMS: ReadonlyArray<SocialIconDefinition> = [
    AUTHOR_PLATFORMS[0], // facebook
    AUTHOR_PLATFORMS[1], // twitter
    AUTHOR_PLATFORMS[2], // mastodon
    {
        slug: 'reddit',
        label: 'Reddit',
        path: 'M14 3a2 2 0 1 1 2 2v1c3 0 5 1 5 3l-1 1c1 1 1 2 1 3 0 3-3 5-7 5s-7-2-7-5c0-1 0-2 1-3l-1-1c0-2 2-3 5-3V5h2Zm-3 8a1 1 0 1 0 0 2 1 1 0 0 0 0-2Zm6 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2Zm-7 3 1 1c2 2 6 2 8 0l1-1c-3 1-7 1-10 0Z',
    },
    {
        slug: 'pinterest',
        label: 'Pinterest',
        path: 'M12 3a8 8 0 0 0-3 15v-2c0-1 1-3 1-3l-1-3c0-2 1-3 2-3 2 0 3 2 2 4l-1 2c0 1 1 2 2 2 2 0 3-2 3-5 0-3-2-5-5-5-3 0-5 2-5 5l1 2-1 1c-2-1-3-3-3-5 0-4 3-7 8-7s7 3 7 7-2 7-5 7c-1 0-3-1-3-2l-1 4-1 3a8 8 0 1 0 3-15Z',
    },
    AUTHOR_PLATFORMS[5], // email
];

export const AUTHOR_SOCIAL_PLATFORM_SLUGS = AUTHOR_PLATFORMS.map(
    (platform) => platform.slug
);

export const SHARE_SOCIAL_PLATFORM_SLUGS = SHARE_PLATFORMS.map(
    (platform) => platform.slug
);

const AUTHOR_PLATFORM_MAP: ReadonlyMap<string, SocialIconDefinition> = new Map(
    AUTHOR_PLATFORMS.map((platform) => [platform.slug, platform])
);

const SHARE_PLATFORM_MAP: ReadonlyMap<string, SocialIconDefinition> = new Map(
    SHARE_PLATFORMS.map((platform) => [platform.slug, platform])
);

export function getAuthorSocialIcon(
    slug: string
): SocialIconDefinition | undefined {
    return AUTHOR_PLATFORM_MAP.get(slug);
}

export function getShareSocialIcon(
    slug: string
): SocialIconDefinition | undefined {
    return SHARE_PLATFORM_MAP.get(slug);
}

export function authorSocialPlatforms(): ReadonlyArray<SocialIconDefinition> {
    return AUTHOR_PLATFORMS;
}

export function shareSocialPlatforms(): ReadonlyArray<SocialIconDefinition> {
    return SHARE_PLATFORMS;
}
