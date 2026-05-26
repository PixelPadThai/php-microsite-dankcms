<?php
namespace adapters;

interface CmsAdapter
{
    public function setting(string $dotPath): mixed;
    public function str(string $key, ?string $lang = null, ?string $fallback = null): string;
    public function languages(): array;
    public function defaultLang(): string;
    public function collection(string $name): CollectionQuery;
}
