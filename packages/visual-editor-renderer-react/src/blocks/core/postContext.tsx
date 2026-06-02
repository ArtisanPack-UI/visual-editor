/**
 * Post-context core block renderers: post-title, post-content, post-excerpt,
 * post-date, post-author, post-featured-image. Each block reads the actual
 * post data from `_resolved*` attributes that a host-side resolver stamps
 * onto the block tree before rendering — the renderer itself never reaches
 * out to a data store, so it stays pure and identical to the Blade and Vue
 * counterparts.
 *
 * When a `_resolved*` attribute is missing the block emits a Gutenberg-shaped
 * empty shell (correct wrapper, classes, aria) so the editor preview and the
 * front-end render structure agree even before a resolver is wired up.
 */

import { attrBoolean, attrInt, attrString, classList } from '../../support/attributes';
import { safeUrl } from '../../support/urlSanitizer';
import type { BlockRendererProps } from '../../types';

function buildLinkProps(
    href: string,
    linkTarget: string,
    rel: string,
    linkClass = ''
): Record<string, string> {
    const props: Record<string, string> = { href };

    if (linkClass !== '') {
        props.className = linkClass;
    }

    if (linkTarget === '_blank') {
        props.target = '_blank';
        props.rel = `noopener noreferrer${rel === '' ? '' : ` ${rel}`}`.trim();
    } else if (rel !== '') {
        props.rel = rel;
    }

    return props;
}

export function PostTitleBlock({ attributes }: BlockRendererProps): JSX.Element {
    const rawLevel = attrInt(attributes.level, 2);
    const level = Math.max(1, Math.min(6, rawLevel));
    const align = attrString(attributes.textAlign);
    const isLink = attrBoolean(attributes.isLink);
    const linkTarget = attrString(attributes.linkTarget);
    const rel = attrString(attributes.rel);
    const linkClass = attrString(attributes.linkClass);
    const className = attrString(attributes.className);

    const title = attrString(attributes._resolvedTitle);
    const permalink = safeUrl(attrString(attributes._resolvedPermalink));

    const Tag = `h${level}` as 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6';

    const classes = classList([
        'wp-block-post-title',
        align !== '' ? `has-text-align-${align}` : null,
        className,
    ]);

    if (isLink && permalink !== '') {
        const linkProps = buildLinkProps(permalink, linkTarget, rel, linkClass);

        return (
            <Tag className={classes}>
                <a {...linkProps} dangerouslySetInnerHTML={{ __html: title }} />
            </Tag>
        );
    }

    return <Tag className={classes} dangerouslySetInnerHTML={{ __html: title }} />;
}

export function PostContentBlock({ attributes }: BlockRendererProps): JSX.Element {
    const align = attrString(attributes.textAlign);
    const className = attrString(attributes.className);
    const content = attrString(attributes._resolvedContent);

    const classes = classList([
        'entry-content',
        'wp-block-post-content',
        align !== '' ? `has-text-align-${align}` : null,
        className,
    ]);

    return <div className={classes} dangerouslySetInnerHTML={{ __html: content }} />;
}

export function PostExcerptBlock({ attributes }: BlockRendererProps): JSX.Element {
    const align = attrString(attributes.textAlign);
    const moreText = attrString(attributes.moreText);
    const showMoreOnNewLine = attrBoolean(attributes.showMoreOnNewLine, true);
    const className = attrString(attributes.className);

    const excerpt = attrString(attributes._resolvedExcerpt);
    const permalink = safeUrl(attrString(attributes._resolvedPermalink));

    const classes = classList([
        'wp-block-post-excerpt',
        align !== '' ? `has-text-align-${align}` : null,
        className,
    ]);

    const moreNode = (() => {
        if (moreText === '') {
            return null;
        }

        if (permalink !== '') {
            return (
                <a className="wp-block-post-excerpt__more-link" href={permalink}>
                    {moreText}
                </a>
            );
        }

        return <span className="wp-block-post-excerpt__more-text">{moreText}</span>;
    })();

    return (
        <div className={classes}>
            <p className="wp-block-post-excerpt__excerpt">
                <span dangerouslySetInnerHTML={{ __html: excerpt }} />
                {moreNode !== null && !showMoreOnNewLine ? <> {moreNode}</> : null}
            </p>
            {moreNode !== null && showMoreOnNewLine ? (
                <p className="wp-block-post-excerpt__more-text">{moreNode}</p>
            ) : null}
        </div>
    );
}

export function PostDateBlock({ attributes }: BlockRendererProps): JSX.Element {
    const align = attrString(attributes.textAlign);
    const displayType = attrString(attributes.displayType, 'date') === 'modified' ? 'modified' : 'date';
    const isLink = attrBoolean(attributes.isLink);
    const className = attrString(attributes.className);

    const datetime =
        displayType === 'modified'
            ? attrString(attributes._resolvedModifiedDate)
            : attrString(attributes._resolvedDate);
    const formatted =
        displayType === 'modified'
            ? attrString(attributes._resolvedModifiedDateFormatted, datetime)
            : attrString(attributes._resolvedDateFormatted, datetime);
    const permalink = safeUrl(attrString(attributes._resolvedPermalink));

    const classes = classList([
        'wp-block-post-date',
        align !== '' ? `has-text-align-${align}` : null,
        className,
    ]);

    const timeProps: Record<string, string> = {};

    if (datetime !== '') {
        timeProps.dateTime = datetime;
    }

    const timeNode = <time {...timeProps}>{formatted}</time>;

    return (
        <div className={classes}>
            {isLink && permalink !== '' ? <a href={permalink}>{timeNode}</a> : timeNode}
        </div>
    );
}

export function PostAuthorBlock({ attributes }: BlockRendererProps): JSX.Element {
    const align = attrString(attributes.textAlign);
    const showAvatar = attrBoolean(attributes.showAvatar, true);
    const showBio = attrBoolean(attributes.showBio);
    const avatarSize = Math.max(1, attrInt(attributes.avatarSize, 24));
    const byline = attrString(attributes.byline);
    const isLink = attrBoolean(attributes.isLink);
    const className = attrString(attributes.className);

    const name = attrString(attributes._resolvedAuthorName);
    const bio = attrString(attributes._resolvedAuthorBio);
    const authorUrl = safeUrl(attrString(attributes._resolvedAuthorUrl));
    const avatarUrl = safeUrl(attrString(attributes._resolvedAuthorAvatar));

    const classes = classList([
        'wp-block-post-author',
        align !== '' ? `has-text-align-${align}` : null,
        className,
    ]);

    return (
        <div className={classes}>
            {showAvatar && avatarUrl !== '' ? (
                <div className="wp-block-post-author__avatar">
                    <img alt={name} width={avatarSize} height={avatarSize} src={avatarUrl} />
                </div>
            ) : null}
            <div className="wp-block-post-author__content">
                {byline !== '' ? <p className="wp-block-post-author__byline">{byline}</p> : null}
                <p className="wp-block-post-author__name">
                    {isLink && authorUrl !== '' ? <a href={authorUrl}>{name}</a> : name}
                </p>
                {showBio && bio !== '' ? (
                    <p
                        className="wp-block-post-author__bio"
                        dangerouslySetInnerHTML={{ __html: bio }}
                    />
                ) : null}
            </div>
        </div>
    );
}

export function PostAuthorNameBlock({ attributes }: BlockRendererProps): JSX.Element {
    const isLink = attrBoolean(attributes.isLink);
    const linkTarget = attrString(attributes.linkTarget, '_self');
    const className = attrString(attributes.className);

    const name = attrString(attributes._resolvedAuthorName);
    const authorUrl = safeUrl(attrString(attributes._resolvedAuthorUrl));

    const classes = classList(['wp-block-post-author-name', className]);

    if (isLink && authorUrl !== '') {
        const linkProps = buildLinkProps(authorUrl, linkTarget, '');

        return (
            <div className={classes}>
                <a {...linkProps}>{name}</a>
            </div>
        );
    }

    return <div className={classes}>{name}</div>;
}

export function PostAuthorBiographyBlock({ attributes }: BlockRendererProps): JSX.Element {
    const className = attrString(attributes.className);
    const bio = attrString(attributes._resolvedAuthorBio);

    const classes = classList(['wp-block-post-author-biography', className]);

    return <p className={classes} dangerouslySetInnerHTML={{ __html: bio }} />;
}

export function AvatarBlock({ attributes }: BlockRendererProps): JSX.Element {
    const size = Math.max(1, attrInt(attributes.size, 96));
    const isLink = attrBoolean(attributes.isLink);
    const linkTarget = attrString(attributes.linkTarget, '_self');
    const className = attrString(attributes.className);

    const avatarUrl = safeUrl(attrString(attributes._resolvedAuthorAvatar));
    const alt = attrString(attributes._resolvedAuthorName);
    const authorUrl = safeUrl(attrString(attributes._resolvedAuthorUrl));

    const classes = classList(['wp-block-avatar', className]);

    if (avatarUrl === '') {
        return <div className={classes} />;
    }

    const img = <img alt={alt} width={size} height={size} src={avatarUrl} />;

    if (isLink && authorUrl !== '') {
        const linkProps = buildLinkProps(authorUrl, linkTarget, '');

        return (
            <div className={classes}>
                <a {...linkProps}>{img}</a>
            </div>
        );
    }

    return <div className={classes}>{img}</div>;
}

export function PostFeaturedImageBlock({ attributes }: BlockRendererProps): JSX.Element {
    const isLink = attrBoolean(attributes.isLink);
    const linkTarget = attrString(attributes.linkTarget);
    const rel = attrString(attributes.rel);
    const aspect = attrString(attributes.aspectRatio);
    const scale = attrString(attributes.scale);
    const sizeSlug = attrString(attributes.sizeSlug);
    const className = attrString(attributes.className);

    const imageUrl = safeUrl(attrString(attributes._resolvedImageUrl));
    const alt = attrString(attributes._resolvedImageAlt);
    const width = Math.max(0, attrInt(attributes._resolvedImageWidth));
    const height = Math.max(0, attrInt(attributes._resolvedImageHeight));
    const permalink = safeUrl(attrString(attributes._resolvedPermalink));

    const classes = classList([
        'wp-block-post-featured-image',
        sizeSlug !== '' ? `size-${sizeSlug}` : null,
        className,
    ]);

    if (imageUrl === '') {
        return <figure className={classes} />;
    }

    const style: Record<string, string> = {};

    if (aspect !== '') {
        style.aspectRatio = aspect;
    }

    if (scale !== '') {
        style.objectFit = scale;
    }

    const imgProps: Record<string, unknown> = {
        src: imageUrl,
        alt,
        style: Object.keys(style).length > 0 ? style : undefined,
    };

    if (width > 0) {
        imgProps.width = width;
    }

    if (height > 0) {
        imgProps.height = height;
    }

    const img = <img {...(imgProps as JSX.IntrinsicElements['img'])} />;

    if (isLink && permalink !== '') {
        const linkProps = buildLinkProps(permalink, linkTarget, rel);

        return (
            <figure className={classes}>
                <a {...linkProps}>{img}</a>
            </figure>
        );
    }

    return <figure className={classes}>{img}</figure>;
}
