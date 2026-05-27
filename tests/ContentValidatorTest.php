<?php
use PHPUnit\Framework\TestCase;

final class ContentValidatorTest extends TestCase
{
    private array $meta;
    private array $schemas;
    private array $validSettings;
    private array $validCollections;

    protected function setUp(): void
    {
        $this->meta = [
            'schema_version' => 1,
            'languages' => [
                ['code' => 'en', 'label' => 'English', 'enabled' => true,  'default' => true],
                ['code' => 'th', 'label' => 'ไทย',     'enabled' => false, 'default' => false],
            ],
        ];
        $this->schemas = [
            'settings' => [
                'site_name'     => ['type' => 'string', 'required' => true],
                'logo'          => ['type' => 'image'],
                'brand_primary' => ['type' => 'color', 'default' => '#0CC4B4'],
                'contact_email' => ['type' => 'email'],
                'social' => ['type' => 'object', 'fields' => [
                    'facebook'  => ['type' => 'url'],
                    'instagram' => ['type' => 'url'],
                ]],
            ],
            'pages' => [
                'id'        => ['type' => 'string', 'primary' => true],
                'slug'      => ['type' => 'string', 'required' => true],
                'title_key' => ['type' => 'string_ref'],
                'published' => ['type' => 'boolean', 'default' => false],
            ],
        ];
        $this->validSettings = [
            'site_name'     => 'My Site',
            'logo'          => '',
            'brand_primary' => '#0CC4B4',
            'contact_email' => '',
            'social' => ['facebook' => '', 'instagram' => ''],
        ];
        $this->validCollections = [
            'pages' => [
                ['id' => 'home', 'slug' => '/', 'title_key' => 'home_title', 'published' => true],
            ],
        ];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            '_meta'       => $this->meta,
            '_schemas'    => $this->schemas,
            'settings'    => $this->validSettings,
            'collections' => $this->validCollections,
        ], $overrides);
    }

    public function testValidPayloadPasses(): void
    {
        $r = ContentValidator::validate($this->payload(), $this->schemas, $this->meta);
        $this->assertTrue($r['ok'], 'errors: ' . json_encode($r['errors']));
        $this->assertSame([], $r['errors']);
    }

    public function testRejectsTamperedMeta(): void
    {
        $tampered = $this->meta;
        $tampered['schema_version'] = 99;
        $r = ContentValidator::validate(
            $this->payload(['_meta' => $tampered]),
            $this->schemas,
            $this->meta
        );
        $this->assertFalse($r['ok']);
        $this->assertNotEmpty($r['errors']);
        $this->assertStringContainsString('_meta', implode(' ', $r['errors']));
    }

    public function testRejectsTamperedSchemas(): void
    {
        $tampered = $this->schemas;
        $tampered['settings']['site_name']['required'] = false;
        $r = ContentValidator::validate(
            $this->payload(['_schemas' => $tampered]),
            $this->schemas,
            $this->meta
        );
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('_schemas', implode(' ', $r['errors']));
    }

    public function testRejectsStringTypeMismatch(): void
    {
        $bad = $this->validSettings;
        $bad['site_name'] = 42;
        $r = ContentValidator::validate(
            $this->payload(['settings' => $bad]),
            $this->schemas,
            $this->meta
        );
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('site_name', implode(' ', $r['errors']));
    }

    public function testRejectsBadColorHex(): void
    {
        $bad = $this->validSettings;
        $bad['brand_primary'] = 'not-a-color';
        $r = ContentValidator::validate(
            $this->payload(['settings' => $bad]),
            $this->schemas,
            $this->meta
        );
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('brand_primary', implode(' ', $r['errors']));
    }

    public function testAcceptsValidColorHex(): void
    {
        $ok = $this->validSettings;
        $ok['brand_primary'] = '#abcdef';
        $r = ContentValidator::validate(
            $this->payload(['settings' => $ok]),
            $this->schemas,
            $this->meta
        );
        $this->assertTrue($r['ok'], 'errors: ' . json_encode($r['errors']));
    }

    public function testRejectsBadEmail(): void
    {
        $bad = $this->validSettings;
        $bad['contact_email'] = 'not-an-email';
        $r = ContentValidator::validate(
            $this->payload(['settings' => $bad]),
            $this->schemas,
            $this->meta
        );
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('contact_email', implode(' ', $r['errors']));
    }

    public function testRejectsBadUrlInNestedObject(): void
    {
        $bad = $this->validSettings;
        $bad['social']['facebook'] = 'not a url';
        $r = ContentValidator::validate(
            $this->payload(['settings' => $bad]),
            $this->schemas,
            $this->meta
        );
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('facebook', implode(' ', $r['errors']));
    }

    public function testRejectsCollectionRecordMissingPrimary(): void
    {
        $bad = ['pages' => [['slug' => '/about', 'title_key' => 'about_title', 'published' => true]]];
        $r = ContentValidator::validate(
            $this->payload(['collections' => $bad]),
            $this->schemas,
            $this->meta
        );
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('id', implode(' ', $r['errors']));
    }

    public function testRejectsCollectionRecordWithWrongFieldType(): void
    {
        $bad = ['pages' => [['id' => 'about', 'slug' => '/about', 'title_key' => 'about_title', 'published' => 'yes']]];
        $r = ContentValidator::validate(
            $this->payload(['collections' => $bad]),
            $this->schemas,
            $this->meta
        );
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('published', implode(' ', $r['errors']));
    }

    public function testRejectsUnknownCollection(): void
    {
        $bad = ['unknown_collection' => [['id' => 'x']]];
        $r = ContentValidator::validate(
            $this->payload(['collections' => $bad]),
            $this->schemas,
            $this->meta
        );
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('unknown_collection', implode(' ', $r['errors']));
    }

    public function testEmptyOptionalEmailIsAccepted(): void
    {
        $r = ContentValidator::validate($this->payload(), $this->schemas, $this->meta);
        $this->assertTrue($r['ok']);
    }

    public function testRejectsMissingRequiredSettingsField(): void
    {
        $bad = $this->validSettings;
        unset($bad['site_name']);
        $r = ContentValidator::validate(
            $this->payload(['settings' => $bad]),
            $this->schemas,
            $this->meta
        );
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('site_name', implode(' ', $r['errors']));
    }
}
