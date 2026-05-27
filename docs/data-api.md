# Data API — the `CMS` facade

Pages access data exclusively through `$cms`, an instance of `CMS` constructed in `index.php` before route dispatch. `$cms` is in scope inside any page file.

The facade delegates to an adapter (`JsonAdapter` by default, `DirectusAdapter` when `DATA_SOURCE = 'directus'` in `config.php`). Pages don't need to know which adapter is active.

## Settings — language-neutral values

```php
$cms->setting('site_name');               // "My Site"
$cms->setting('social.facebook');          // dot-path supported
$cms->setting('does_not_exist');           // null
```

## Translated strings

```php
$cms->str('home_title');                   // default lang
$cms->str('home_title', 'th');             // explicit lang
$cms->str('missing_key');                  // returns the key as fallback
$cms->str('missing_key', null, 'Hello');   // returns "Hello" fallback
```

## Languages

```php
$cms->lang();                              // current active language code
$cms->langs();                             // [{code,label,default}, ...] — enabled only
$cms->setLang('th');                       // override for this request (router does this from /th/ prefix)
```

## Collections — chainable query

```php
$cms->collection('pages')->get();          // array of all rows
$cms->collection('pages')->find('home');   // single row by primary key
$cms->collection('pages')->count();

$cms->collection('pages')
    ->filter(['published' => true])
    ->filter(['rating' => ['>=', 4]])
    ->sort('-rating')                       // leading - = descending
    ->limit(10)
    ->offset(0)
    ->translate()                           // expands *_key fields to strings in current lang
    ->get();
```

Supported filter operators: `=`, `==`, `!=`, `>`, `>=`, `<`, `<=`.

`translate()` walks the collection's schema and replaces any field with `type: string_ref` with the corresponding string from `strings.json`.

## Adapter swap (Phase 4)

In `config.php`:

```php
define('DATA_SOURCE', 'directus');    // 'json' (default) | 'directus'
define('DIRECTUS_URL',   'https://...');
define('DIRECTUS_TOKEN', '...');
```

Page code does not change. The `DirectusAdapter` is a stub in Phase 1 (throws `RuntimeException`); it's implemented in Phase 4.
