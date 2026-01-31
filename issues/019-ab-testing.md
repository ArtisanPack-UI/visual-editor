/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Low" ~"Area::Backend" ~"Phase::5"

## Problem Statement

**Is your feature request related to a problem?**
Marketers need to test content variations to optimize conversions, but current workflows require separate pages or manual swapping.

## Proposed Solution

**What would you like to happen?**
Implement A/B testing functionality for content variations:

### Experiment Model

```php
namespace ArtisanPackUI\VisualEditor\Models;

class Experiment extends Model
{
    protected $table = 've_experiments';

    protected $casts = [
        'type' => ExperimentType::class,
        'goal_type' => GoalType::class,
        'status' => ExperimentStatus::class,
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function content(): BelongsTo;
    public function variants(): HasMany;
    public function controlVariant(): HasOne;
    public function treatmentVariant(): HasOne;
    public function winner(): BelongsTo;
}
```

### Experiment Types

```php
enum ExperimentType: string
{
    case Headline = 'headline';      // Test different headlines
    case Section = 'section';        // Test entire sections
    case FullPage = 'full_page';     // Test complete page variants
}
```

### Goal Types

```php
enum GoalType: string
{
    case Clicks = 'clicks';              // Click on element
    case Conversions = 'conversions';    // Form submission, purchase
    case TimeOnPage = 'time_on_page';    // Engagement time
    case ScrollDepth = 'scroll_depth';   // How far user scrolls
}
```

### Experiment Variant Model

```php
class ExperimentVariant extends Model
{
    protected $table = 've_experiment_variants';

    protected $casts = [
        'content_data' => 'array',
        'is_control' => 'boolean',
    ];

    public function experiment(): BelongsTo;

    public function getConversionRate(): float
    {
        if ($this->impressions === 0) return 0;
        return ($this->conversions / $this->impressions) * 100;
    }
}
```

### Experiment Service

```php
namespace ArtisanPackUI\VisualEditor\Services;

class ExperimentService
{
    public function create(Content $content, array $data): Experiment;
    public function start(Experiment $experiment): void;
    public function pause(Experiment $experiment): void;
    public function end(Experiment $experiment, ?ExperimentVariant $winner = null): void;
    public function getVariantForVisitor(Experiment $experiment, string $visitorId): ExperimentVariant;
    public function recordImpression(ExperimentVariant $variant): void;
    public function recordConversion(ExperimentVariant $variant): void;
    public function getStatistics(Experiment $experiment): array;
}
```

### Visitor Assignment

```php
public function getVariantForVisitor(Experiment $experiment, string $visitorId): ExperimentVariant
{
    // Consistent assignment based on visitor ID
    $hash = crc32($visitorId . $experiment->id);
    $percentage = $hash % 100;

    return $percentage < $experiment->traffic_split
        ? $experiment->treatmentVariant
        : $experiment->controlVariant;
}
```

### Frontend Tracking

```javascript
// Track impressions
window.veExperiment = {
    experimentId: {{ $experiment->id }},
    variantId: {{ $variant->id }},
};

// Track clicks on goal element
document.querySelector('[data-ab-goal]')?.addEventListener('click', () => {
    fetch('/api/visual-editor/experiments/convert', {
        method: 'POST',
        body: JSON.stringify(window.veExperiment),
    });
});
```

### Experiment UI

- Create experiment from editor
- Define variants (duplicate and modify)
- Set traffic split (default 50/50)
- Configure goal tracking
- View real-time statistics
- Statistical significance indicator
- End experiment and apply winner

## Alternatives Considered

- Third-party A/B tools (rejected: complex integration)
- Manual page duplication (rejected: poor UX)
- External analytics only (rejected: no content integration)

## Use Cases

1. Marketer tests two different headlines
2. Designer tests hero section variations
3. Product team measures CTA button effectiveness
4. Results show which variant converts better

## Acceptance Criteria

- [ ] Experiments can be created from editor
- [ ] Variants store content differences
- [ ] Traffic split works correctly
- [ ] Visitor assignment is consistent
- [ ] Impressions are tracked
- [ ] Conversions are tracked
- [ ] Statistics show conversion rates
- [ ] Statistical significance is calculated
- [ ] Experiment can be ended with winner
- [ ] Winner can be applied to content

---

**Related Issues:**
- Depends on: Database migrations, Content model
- Related: Analytics integration
