<?php
use PHPUnit\Framework\TestCase;
use adapters\JsonAdapter;

final class JsonAdapterTest extends TestCase
{
    private JsonAdapter $a;
    protected function setUp(): void {
        $this->a = new JsonAdapter(DATA_DIR . '/strings.json', DATA_DIR . '/content.json');
    }
    public function testSettingReturnsTopLevelValue(): void {
        $this->assertSame('My Site', $this->a->setting('site_name'));
    }
    public function testSettingSupportsDotPath(): void {
        $this->assertSame('', $this->a->setting('social.facebook'));
    }
    public function testSettingReturnsNullForMissing(): void {
        $this->assertNull($this->a->setting('does_not_exist'));
    }
    public function testStrReturnsDefaultLang(): void {
        $this->assertSame('Welcome', $this->a->str('home_title'));
    }
    public function testStrReturnsRequestedLang(): void {
        $this->assertSame('ยินดีต้อนรับ', $this->a->str('home_title', 'th'));
    }
    public function testStrFallsBackToFallbackArg(): void {
        $this->assertSame('missing', $this->a->str('nope', null, 'missing'));
    }
    public function testStrFallsBackToKey(): void {
        $this->assertSame('nope', $this->a->str('nope'));
    }
    public function testCollectionGetReturnsAllRows(): void {
        $rows = $this->a->collection('pages')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('home', $rows[0]['id']);
    }
    public function testCollectionFilterByEquality(): void {
        $rows = $this->a->collection('pages')->filter(['published' => true])->get();
        $this->assertCount(1, $rows);
    }
    public function testCollectionFindById(): void {
        $row = $this->a->collection('pages')->find('home');
        $this->assertSame('home', $row['id']);
    }
    public function testTranslateExpandsStringRefs(): void {
        $row = $this->a->collection('pages')->translate()->find('home');
        $this->assertSame('Welcome', $row['title_key']);
    }
}
