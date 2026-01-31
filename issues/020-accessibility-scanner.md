/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Area::Frontend" ~"Phase::6"

## Problem Statement

**Is your feature request related to a problem?**
Content creators need guidance on accessibility issues to ensure their content is usable by everyone, including people using assistive technologies.

## Proposed Solution

**What would you like to happen?**
Implement an accessibility scanner using artisanpack-ui/accessibility package:

### Accessibility Scanner Service

```php
namespace ArtisanPackUI\VisualEditor\Services;

class AccessibilityScanner
{
    public function scan(Content $content): AccessibilityReport;
    public function scanBlock(array $block): array; // Returns issues
    public function scanSection(array $section): array;
    public function getScore(Content $content): int; // 0-100
}
```

### Accessibility Report

```php
class AccessibilityReport
{
    public int $score;
    public array $errors;    // Must fix
    public array $warnings;  // Should fix
    public array $notices;   // Good to know

    public function hasErrors(): bool;
    public function isPassing(): bool; // score >= 80
}
```

### Accessibility Checks

**Image Checks:**
- Missing alt text (error)
- Decorative image without empty alt (warning)
- Alt text too long (warning)
- Alt text is filename (warning)

**Heading Checks:**
- Skipped heading levels (error)
- Multiple H1s (warning)
- Empty headings (error)
- Heading order issues (warning)

**Link Checks:**
- Empty link text (error)
- Generic link text ("click here") (warning)
- Links without href (error)

**Color Checks (using accessibility package):**
- Insufficient color contrast (error)
- Color-only information (warning)

**Form Checks:**
- Missing form labels (error)
- Missing required indicators (warning)

**Content Checks:**
- Very long paragraphs (notice)
- All caps text (notice)
- Tables without headers (error)

### Scanner UI

**Sidebar Panel:**
- Accessibility score badge
- Issue list grouped by severity
- Click issue to highlight block
- Quick fix suggestions
- Rescan button

**Block Indicators:**
- Error icon on blocks with issues
- Warning icon for warnings
- Tooltip with issue description

**Pre-publish Check:**
- Accessibility check in publish checklist
- Option to publish with warnings
- Block publish with errors (configurable)

### Integration with Accessibility Package

```php
use function ArtisanPackUI\Accessibility\a11yCheckContrastColor;
use function ArtisanPackUI\Accessibility\a11yCSSVarBlackOrWhite;

// Check text/background contrast
if (!a11yCheckContrastColor($textColor, $backgroundColor)) {
    $issues[] = [
        'type' => 'error',
        'code' => 'color_contrast',
        'message' => 'Text color does not have sufficient contrast with background',
        'wcag' => '1.4.3',
    ];
}
```

### Quick Fixes

- Add alt text → Opens image settings
- Fix heading level → Suggests correct level
- Improve link text → Opens link editor
- Fix contrast → Suggests accessible colors

### Configuration

```php
'accessibility' => [
    'enabled' => true,
    'minimum_score' => 80,
    'block_publish_on_errors' => false,
    'checks' => [
        'images' => true,
        'headings' => true,
        'links' => true,
        'color_contrast' => true,
        'forms' => true,
    ],
]
```

## Alternatives Considered

- External accessibility tools only (rejected: no editor integration)
- Manual accessibility review (rejected: inconsistent)
- Post-publish scanning only (rejected: too late)

## Use Cases

1. Editor sees accessibility score while editing
2. Editor clicks issue to jump to problem block
3. Editor uses quick fix to add missing alt text
4. Publish is blocked until errors are fixed

## Acceptance Criteria

- [ ] Scanner runs automatically on content change
- [ ] Issues are displayed in sidebar panel
- [ ] Issues highlight affected blocks
- [ ] Image alt text check works
- [ ] Heading level check works
- [ ] Link text check works
- [ ] Color contrast check works
- [ ] Score is calculated correctly
- [ ] Quick fixes open appropriate editors
- [ ] Pre-publish check includes accessibility

---

**Related Issues:**
- Depends on: artisanpack-ui/accessibility, Block System
- Related: AI assistant (alt text generation)
