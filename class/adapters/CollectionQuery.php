<?php
namespace adapters;

final class CollectionQuery
{
    private array $rows;
    private bool $translate = false;
    private ?string $translateLang = null;

    public function __construct(private array $source, private array $schema, private JsonAdapter $cms) {
        $this->rows = $source;
    }

    public function filter(array $criteria): self {
        $this->rows = array_values(array_filter($this->rows, function ($r) use ($criteria) {
            foreach ($criteria as $k => $expected) {
                if (is_array($expected) && count($expected) === 2) {
                    [$op, $val] = $expected;
                    $actual = $r[$k] ?? null;
                    $ok = match ($op) {
                        '=','==' => $actual == $val,
                        '!='     => $actual != $val,
                        '>'      => $actual > $val,
                        '>='     => $actual >= $val,
                        '<'      => $actual < $val,
                        '<='     => $actual <= $val,
                        default  => false,
                    };
                    if (!$ok) return false;
                } else {
                    if (($r[$k] ?? null) !== $expected) return false;
                }
            }
            return true;
        }));
        return $this;
    }

    public function sort(string $field): self {
        $desc = str_starts_with($field, '-');
        $field = ltrim($field, '-');
        usort($this->rows, fn($a, $b) => ($desc ? -1 : 1) * (($a[$field] ?? null) <=> ($b[$field] ?? null)));
        return $this;
    }

    public function limit(int $n): self { $this->rows = array_slice($this->rows, 0, $n); return $this; }
    public function offset(int $n): self { $this->rows = array_slice($this->rows, $n); return $this; }
    public function translate(?string $lang = null): self { $this->translate = true; $this->translateLang = $lang; return $this; }

    public function find(string $id): ?array {
        foreach ($this->rows as $r) if (($r['id'] ?? null) === $id) return $this->applyTranslate($r);
        return null;
    }

    public function get(): array { return array_map(fn($r) => $this->applyTranslate($r), $this->rows); }
    public function count(): int { return count($this->rows); }

    private function applyTranslate(array $r): array {
        if (!$this->translate) return $r;
        foreach ($this->schema as $field => $def) {
            if (($def['type'] ?? '') === 'string_ref' && isset($r[$field])) {
                $r[$field] = $this->cms->str($r[$field], $this->translateLang);
            }
        }
        return $r;
    }
}
