<?php
final class CMS
{
    private adapters\CmsAdapter $adapter;
    private ?string $currentLang = null;

    public function __construct() {
        $this->adapter = match (DATA_SOURCE) {
            'directus' => new adapters\DirectusAdapter(),
            default    => new adapters\JsonAdapter(__DIR__ . '/../data/strings.json', __DIR__ . '/../data/content.json'),
        };
    }

    public function setLang(string $code): void { $this->currentLang = $code; }
    public function lang(): string { return $this->currentLang ?? $this->adapter->defaultLang(); }
    public function langs(): array { return $this->adapter->languages(); }
    public function setting(string $path): mixed { return $this->adapter->setting($path); }
    public function str(string $key, ?string $lang = null, ?string $fallback = null): string {
        return $this->adapter->str($key, $lang ?? $this->lang(), $fallback);
    }
    public function collection(string $name): adapters\CollectionQuery {
        return $this->adapter->collection($name);
    }
    public function image(string $url, array $opts = []): string {
        return ImageHelper::render($url, $opts, dirname(__DIR__));
    }
}
