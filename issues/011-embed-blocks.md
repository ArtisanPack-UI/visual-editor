/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Area::Frontend" ~"Phase::2"

## Problem Statement

**Is your feature request related to a problem?**
Users need to embed external content (YouTube, Vimeo, Twitter, generic embeds) without writing HTML or dealing with embed codes.

## Proposed Solution

**What would you like to happen?**
Implement embed blocks with automatic oEmbed resolution:

### YouTube Block

```php
'youtube' => [
    'schema' => [
        'url' => ['type' => 'string'],
        'videoId' => ['type' => 'string'],
        'aspectRatio' => ['type' => 'string', 'default' => '16:9'],
        'autoplay' => ['type' => 'boolean', 'default' => false],
        'start' => ['type' => 'integer', 'nullable' => true],
    ],
    'supports' => ['align', 'spacing'],
]
```

Features:
- Paste URL to auto-detect video
- Aspect ratio options (16:9, 4:3, 1:1)
- Start time option
- Privacy-enhanced mode option (youtube-nocookie.com)
- Lazy loading

### Vimeo Block

```php
'vimeo' => [
    'schema' => [
        'url' => ['type' => 'string'],
        'videoId' => ['type' => 'string'],
        'aspectRatio' => ['type' => 'string', 'default' => '16:9'],
        'autoplay' => ['type' => 'boolean', 'default' => false],
        'loop' => ['type' => 'boolean', 'default' => false],
        'color' => ['type' => 'string', 'nullable' => true],
    ],
    'supports' => ['align', 'spacing'],
]
```

Features:
- URL paste detection
- Custom player color
- Loop option
- Responsive embed

### Twitter/X Block

```php
'twitter' => [
    'schema' => [
        'url' => ['type' => 'string'],
        'tweetId' => ['type' => 'string'],
        'theme' => ['type' => 'string', 'enum' => ['light', 'dark']],
        'hideConversation' => ['type' => 'boolean', 'default' => false],
    ],
    'supports' => ['align'],
]
```

Features:
- Paste tweet URL
- Light/dark theme
- Hide conversation thread option

### Generic Embed Block

```php
'embed' => [
    'schema' => [
        'url' => ['type' => 'string'],
        'html' => ['type' => 'string', 'nullable' => true],
        'aspectRatio' => ['type' => 'string', 'nullable' => true],
        'provider' => ['type' => 'string', 'nullable' => true],
    ],
    'supports' => ['align', 'spacing'],
]
```

Features:
- oEmbed auto-detection
- Fallback to iframe embed
- Manual embed code option
- Supported providers: Instagram, TikTok, Spotify, SoundCloud, CodePen, etc.

### oEmbed Service

```php
namespace ArtisanPackUI\VisualEditor\Services;

class OEmbedService
{
    public function resolve(string $url): ?array;
    public function isSupported(string $url): bool;
    public function getProviderName(string $url): ?string;
}
```

## Alternatives Considered

- Raw HTML embed only (rejected: security risk, poor UX)
- Limited to specific platforms (rejected: not flexible)
- Server-side rendering only (rejected: performance)

## Use Cases

1. User embeds a YouTube tutorial video
2. User embeds a tweet for social proof
3. User embeds a Spotify playlist
4. User embeds content from any oEmbed provider

## Acceptance Criteria

- [ ] YouTube block extracts video ID from URL
- [ ] YouTube block renders responsive iframe
- [ ] Vimeo block works similarly
- [ ] Twitter block embeds tweets
- [ ] Generic embed resolves oEmbed URLs
- [ ] Embeds lazy load for performance
- [ ] Embeds are responsive
- [ ] Error handling for invalid URLs
- [ ] Preview shows in editor

---

**Related Issues:**
- Depends on: Block Registry
- Related: Media blocks, Social sharing
