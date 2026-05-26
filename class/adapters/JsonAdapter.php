<?php
namespace adapters;

final class JsonAdapter implements CmsAdapter
{
    private array $strings;
    private array $content;

    public function __construct(string $stringsPath, string $contentPath) {
        $this->strings = json_decode(file_get_contents($stringsPath), true) ?? [];
        $this->content = json_decode(file_get_contents($contentPath), true) ?? [];
    }

    public function setting(string $dotPath): mixed {
        $parts = explode('.', $dotPath);
        $node = $this->content['settings'] ?? [];
        foreach ($parts as $p) {
            if (!is_array($node) || !array_key_exists($p, $node)) return null;
            $node = $node[$p];
        }
        return $node;
    }

    public function str(string $key, ?string $lang = null, ?string $fallback = null): string {
        $lang = $lang ?? $this->defaultLang();
        return $this->strings[$key][$lang] ?? $fallback ?? $key;
    }

    public function languages(): array {
        return array_values(array_filter($this->content['_meta']['languages'] ?? [], fn($l) => $l['enabled']));
    }

    public function defaultLang(): string {
        foreach ($this->content['_meta']['languages'] ?? [] as $l) {
            if (!empty($l['default'])) return $l['code'];
        }
        return 'en';
    }

    public function collection(string $name): CollectionQuery {
        $rows = $this->content['collections'][$name] ?? [];
        $schema = $this->content['_schemas'][$name] ?? [];
        return new CollectionQuery($rows, $schema, $this);
    }
}
