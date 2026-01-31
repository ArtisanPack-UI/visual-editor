/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Area::Frontend" ~"Phase::2"

## Problem Statement

**Is your feature request related to a problem?**
The visual editor needs media blocks (Image, Gallery, Video, Audio, File) to allow users to add rich media content to their pages.

## Proposed Solution

**What would you like to happen?**
Implement media blocks with integration to artisanpack-ui/media-library:

### Image Block

```php
'image' => [
    'schema' => [
        'media_id' => ['type' => 'integer', 'nullable' => true],
        'url' => ['type' => 'string', 'nullable' => true],
        'alt' => ['type' => 'string', 'default' => ''],
        'caption' => ['type' => 'richtext', 'default' => ''],
        'link' => ['type' => 'string', 'nullable' => true],
        'size' => ['type' => 'string', 'default' => 'large'],
    ],
    'supports' => ['align', 'spacing', 'border', 'shadow'],
]
```

Features:
- Media library integration for selection
- Direct URL input option
- Alt text field (required for accessibility)
- Caption with rich text
- Link wrapper option
- Size presets (thumbnail, medium, large, full)
- Aspect ratio lock
- Focal point selection

### Gallery Block

```php
'gallery' => [
    'schema' => [
        'images' => ['type' => 'array', 'items' => ['type' => 'object']],
        'columns' => ['type' => 'integer', 'default' => 3],
        'gap' => ['type' => 'string', 'default' => 'medium'],
        'linkTo' => ['type' => 'string', 'enum' => ['none', 'media', 'attachment']],
    ],
    'supports' => ['spacing'],
]
```

Features:
- Multi-image selection from media library
- Drag to reorder images
- Grid layout with column control
- Lightbox integration
- Masonry layout option

### Video Block

```php
'video' => [
    'schema' => [
        'media_id' => ['type' => 'integer', 'nullable' => true],
        'url' => ['type' => 'string', 'nullable' => true],
        'poster' => ['type' => 'string', 'nullable' => true],
        'autoplay' => ['type' => 'boolean', 'default' => false],
        'loop' => ['type' => 'boolean', 'default' => false],
        'muted' => ['type' => 'boolean', 'default' => false],
        'controls' => ['type' => 'boolean', 'default' => true],
    ],
    'supports' => ['align', 'spacing'],
]
```

Features:
- Self-hosted video support
- Poster image selection
- Playback controls (autoplay, loop, muted)
- Aspect ratio options

### Audio Block

Similar to video with audio-specific options.

### File Block

Download link with file icon and metadata display.

## Alternatives Considered

- External media URLs only (rejected: no library integration)
- Single media block for all types (rejected: UX confusion)
- Inline media insertion (rejected: complex implementation)

## Use Cases

1. User adds product images to a page
2. User creates a photo gallery
3. User embeds a video tutorial
4. User provides downloadable resources

## Acceptance Criteria

- [ ] Image block integrates with media library
- [ ] Image block supports direct URL
- [ ] Gallery block allows multi-select
- [ ] Gallery renders in grid layout
- [ ] Video block plays self-hosted video
- [ ] Audio block plays audio files
- [ ] File block shows download link
- [ ] All blocks have proper accessibility attributes
- [ ] Media blocks lazy load for performance

---

**Related Issues:**
- Depends on: Block Registry, artisanpack-ui/media-library
- Related: Embed blocks, Image optimization
