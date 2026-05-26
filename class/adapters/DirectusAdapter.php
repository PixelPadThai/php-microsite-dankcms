<?php
namespace adapters;

final class DirectusAdapter implements CmsAdapter
{
    public function setting(string $dotPath): mixed { throw new \RuntimeException('DirectusAdapter not implemented in Phase 1'); }
    public function str(string $key, ?string $lang = null, ?string $fallback = null): string { throw new \RuntimeException('Not implemented'); }
    public function languages(): array { throw new \RuntimeException('Not implemented'); }
    public function defaultLang(): string { throw new \RuntimeException('Not implemented'); }
    public function collection(string $name): CollectionQuery { throw new \RuntimeException('Not implemented'); }
}
