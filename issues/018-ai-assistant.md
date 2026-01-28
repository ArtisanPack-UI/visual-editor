/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Low" ~"Area::Backend" ~"Phase::5"

## Problem Statement

**Is your feature request related to a problem?**
Content creators would benefit from AI assistance for generating content, improving writing, translating text, and generating image alt text.

## Proposed Solution

**What would you like to happen?**
Implement an AI assistant with pluggable provider support:

### AI Provider Registry

```php
namespace ArtisanPackUI\VisualEditor\AI;

class AIProviderRegistry
{
    protected array $providers = [];

    public function register(string $name, AIProviderInterface $provider): void;
    public function get(string $name): ?AIProviderInterface;
    public function getDefault(): ?AIProviderInterface;
    public function all(): array;
}
```

### AI Provider Interface

```php
interface AIProviderInterface
{
    public function getName(): string;
    public function generateText(string $prompt, array $options = []): string;
    public function improveText(string $text, string $instruction): string;
    public function translateText(string $text, string $targetLanguage): string;
    public function generateAltText(string $imageUrl): string;
    public function isConfigured(): bool;
}
```

### Built-in Providers

**OpenAI Provider:**
```php
class OpenAIProvider implements AIProviderInterface
{
    public function __construct(
        protected string $apiKey,
        protected string $model = 'gpt-4o-mini',
    ) {}
}
```

**Anthropic Provider:**
```php
class AnthropicProvider implements AIProviderInterface
{
    public function __construct(
        protected string $apiKey,
        protected string $model = 'claude-3-haiku-20240307',
    ) {}
}
```

### AI Assistant Features

**Content Generation:**
- Generate paragraph from topic/outline
- Expand bullet points to paragraphs
- Generate section content from heading
- Create FAQ answers from questions

**Content Improvement:**
- Fix grammar and spelling
- Improve clarity and readability
- Change tone (formal, casual, professional)
- Simplify complex text
- Expand brief content

**Translation:**
- Translate selected block to target language
- Translate entire page
- Language detection

**Image Alt Text:**
- Analyze image and generate descriptive alt text
- Improve existing alt text

### AI Panel UI

```php
// Livewire component for AI panel
class AIAssistant extends Component
{
    public string $prompt = '';
    public ?string $selectedText = null;
    public string $action = 'generate';

    public function generate(): void;
    public function improve(): void;
    public function translate(string $language): void;
}
```

### Extensibility via Hooks

```php
// Register custom provider
addFilter('ve.ai.providers', function (AIProviderRegistry $registry) {
    $registry->register('custom', new CustomAIProvider());
    return $registry;
});

// Modify AI prompts
addFilter('ve.ai.prompt.generate', function (string $prompt, array $context) {
    return "You are a helpful content writer. " . $prompt;
});
```

### Configuration

```php
'ai' => [
    'enabled' => env('VE_AI_ENABLED', false),
    'default_provider' => env('VE_AI_PROVIDER', 'openai'),
    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => 'gpt-4o-mini',
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => 'claude-3-haiku-20240307',
        ],
    ],
]
```

## Alternatives Considered

- Single provider only (rejected: vendor lock-in)
- Client-side AI (rejected: API key exposure)
- No AI features (rejected: user demand)

## Use Cases

1. Writer generates draft paragraph from outline
2. Editor improves grammar in selected text
3. Marketer translates page to Spanish
4. Content manager generates alt text for images

## Acceptance Criteria

- [ ] Provider registry accepts provider implementations
- [ ] OpenAI provider works when configured
- [ ] Anthropic provider works when configured
- [ ] Content generation produces relevant text
- [ ] Text improvement corrects grammar
- [ ] Translation works for supported languages
- [ ] Alt text generation describes images
- [ ] AI panel UI is accessible
- [ ] Errors are handled gracefully
- [ ] Usage is rate-limited appropriately

---

**Related Issues:**
- Related: Accessibility scanner (for alt text)
- Optional: Can be disabled entirely
