/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Low" ~"Area::Frontend" ~"Phase::4"

## Problem Statement

**Is your feature request related to a problem?**
Users with specialized content needs require blocks for footnotes, mathematical equations, media playlists, reusable content, and RSS feeds.

## Proposed Solution

**What would you like to happen?**
Implement specialized blocks for advanced use cases:

### Footnotes Block

```php
'footnotes' => [
    'schema' => [
        'title' => ['type' => 'string', 'default' => 'Footnotes'],
        'show_title' => ['type' => 'boolean', 'default' => true],
        'number_style' => ['type' => 'string', 'enum' => ['numeric', 'alpha', 'roman'], 'default' => 'numeric'],
        'return_link' => ['type' => 'boolean', 'default' => true],
    ],
    'supports' => ['spacing', 'typography', 'background', 'border'],
]
```

Features:
- Automatic footnote numbering
- Reference markers in text
- Footnote list at bottom
- Return links to references
- Multiple numbering styles
- Academic citation support

Usage Pattern:
```blade
{{-- In rich text editor, use [^1] syntax --}}
<p>
    This is a statement that needs citation.[^1]
    Another statement requiring a source.[^2]
</p>

{{-- Footnotes block auto-collects and renders --}}
<x-artisanpack-footnotes />

{{-- Renders as: --}}
<div class="footnotes">
    <h3>Footnotes</h3>
    <ol>
        <li id="fn1">
            Citation text here. <a href="#ref1">↩</a>
        </li>
        <li id="fn2">
            Another citation. <a href="#ref2">↩</a>
        </li>
    </ol>
</div>
```

Implementation:
- Parse content for footnote markers `[^1]`, `[^2]`, etc.
- Extract footnote definitions
- Generate reference links and footnote list
- Support backlinks to references

### Math Block

```php
'math' => [
    'schema' => [
        'expression' => ['type' => 'string', 'default' => ''],
        'display_mode' => ['type' => 'string', 'enum' => ['inline', 'block'], 'default' => 'block'],
        'numbering' => ['type' => 'boolean', 'default' => false],
    ],
    'supports' => ['spacing'],
]
```

Features:
- LaTeX/KaTeX rendering
- Inline or block display
- Equation numbering
- Syntax highlighting in editor
- Preview in editor
- Copy LaTeX source

Supported Libraries:
- **KaTeX** (default, faster)
- **MathJax** (alternative, more features)

Examples:
```latex
// Block equation
x = \frac{-b \pm \sqrt{b^2-4ac}}{2a}

// Inline equation
The formula $E=mc^2$ shows...

// Matrix
\begin{bmatrix}
a & b \\
c & d
\end{bmatrix}
```

Editor Interface:
```blade
<div class="math-block-editor">
    <textarea wire:model.debounce.500ms="expression">{{ $expression }}</textarea>

    <div class="math-preview">
        <span class="katex-render">{!! $rendered !!}</span>
    </div>

    <button wire:click="copyLatex">Copy LaTeX</button>
</div>
```

### Media Playlist Block

```php
'media-playlist' => [
    'schema' => [
        'type' => ['type' => 'string', 'enum' => ['audio', 'video'], 'default' => 'audio'],
        'items' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'media_id' => ['type' => 'integer'],
                    'title' => ['type' => 'string'],
                    'artist' => ['type' => 'string', 'nullable' => true],
                ],
            ],
        ],
        'autoplay' => ['type' => 'boolean', 'default' => false],
        'show_list' => ['type' => 'boolean', 'default' => true],
        'show_controls' => ['type' => 'boolean', 'default' => true],
    ],
    'supports' => ['spacing'],
]
```

Features:
- Audio or video playlist
- Track list display
- Sequential playback
- Shuffle and repeat
- Track titles and metadata
- Thumbnail display (video)
- Progress tracking

Use Cases:
```blade
<!-- Audio album -->
<x-artisanpack-media-playlist type="audio">
    <track media-id="1" title="Song 1" artist="Artist Name" />
    <track media-id="2" title="Song 2" artist="Artist Name" />
</x-artisanpack-media-playlist>

<!-- Video course -->
<x-artisanpack-media-playlist type="video">
    <track media-id="5" title="Lesson 1: Introduction" />
    <track media-id="6" title="Lesson 2: Basics" />
</x-artisanpack-media-playlist>
```

### Reusable Section Block

```php
'reusable-section' => [
    'schema' => [
        'section_id' => ['type' => 'integer'],
        'override_styles' => ['type' => 'boolean', 'default' => false],
    ],
    'supports' => ['spacing'],
]
```

Features:
- Reference saved sections/patterns
- Global edits propagate everywhere
- Optional local style overrides
- Version control integration
- Preview before insertion

Integration:
- Connects to existing Section Registry (Issue #005)
- Allows placing saved sections as blocks
- Updates when source section is edited

### RSS Feed Block

```php
'rss-feed' => [
    'schema' => [
        'url' => ['type' => 'string'],
        'limit' => ['type' => 'integer', 'default' => 5],
        'show_thumbnail' => ['type' => 'boolean', 'default' => true],
        'show_date' => ['type' => 'boolean', 'default' => true],
        'show_excerpt' => ['type' => 'boolean', 'default' => true],
        'excerpt_length' => ['type' => 'integer', 'default' => 100],
        'cache_duration' => ['type' => 'integer', 'default' => 3600],
    ],
    'supports' => ['spacing', 'background', 'border'],
]
```

Features:
- Display external RSS feeds
- Thumbnail/image extraction
- Date display
- Excerpt with length control
- Caching for performance
- Error handling for unavailable feeds

Implementation:
```php
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

public function getFeedItems(): array
{
    return Cache::remember(
        "rss_feed_{$this->url}",
        $this->cache_duration,
        function () {
            $response = Http::get($this->url);
            $xml = simplexml_load_string($response->body());

            return collect($xml->channel->item)
                ->take($this->limit)
                ->map(function ($item) {
                    return [
                        'title' => (string) $item->title,
                        'link' => (string) $item->link,
                        'description' => (string) $item->description,
                        'date' => (string) $item->pubDate,
                        'thumbnail' => $this->extractThumbnail($item),
                    ];
                })
                ->toArray();
        }
    );
}
```

## Alternatives Considered

- Third-party plugins for each feature (rejected: want integrated solution)
- Markdown-only for footnotes (rejected: not visual)
- No math support (rejected: academic users need it)
- External playlist services (rejected: want self-hosted)

## Use Cases

1. Academic writer adds footnotes to research article
2. Math teacher creates lesson with equations
3. Podcast creator builds episode playlist
4. Musician shares album tracks
5. Content curator displays external blog feed
6. Designer creates reusable CTA section across site

## Acceptance Criteria

- [ ] Footnotes block collects and displays footnotes
- [ ] Footnotes auto-number correctly
- [ ] Footnotes include return links
- [ ] Math block renders LaTeX equations
- [ ] Math block supports inline and block modes
- [ ] Math block editor has preview
- [ ] Playlist block plays sequential media
- [ ] Playlist shows track list
- [ ] Playlist supports audio and video
- [ ] Reusable section references saved sections
- [ ] Reusable section updates propagate
- [ ] RSS feed displays external feed items
- [ ] RSS feed caches for performance
- [ ] RSS feed handles errors gracefully

---

**Related Issues:**
- Depends on: Block Registry, Media Library, Section Registry (#005)
- Related: Rich Text Editor (footnotes), Advanced Features
