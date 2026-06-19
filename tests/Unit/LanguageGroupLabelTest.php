<?php

declare(strict_types=1);

namespace LeadingSystems\LanguageSelectorBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;

class LanguageGroupLabelTest extends TestCase
{
    private \tl_page_ls_cnc_languageSelector $instance;

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['TL_DCA'] = ['tl_page' => ['fields' => [], 'palettes' => []]];
        $GLOBALS['TL_LANG'] = [
            'tl_page' => [
                'languageGroupLabelMaster' => '[Sprachgruppe ← %s]',
                'languageGroupLabelSlave' => '[Sprachgruppe → %s]',
            ],
        ];

        $this->instance = (new \ReflectionClass(\tl_page_ls_cnc_languageSelector::class))
            ->newInstanceWithoutConstructor();
    }

    protected function tearDown(): void
    {
        $GLOBALS['TL_DCA'] = ['tl_page' => ['fields' => [], 'palettes' => [], 'config' => [], 'list' => []]];
        $GLOBALS['TL_LANG'] = [];
        parent::tearDown();
    }

    public function testMasterLabelWithMultipleSlaves(): void
    {
        $slaves = [
            ['language' => 'en', 'title' => 'Example Shop'],
            ['language' => 'fr', 'title' => 'Example Shop'],
        ];

        $result = $this->instance->formatLanguageGroupMasterLabel($slaves);

        $this->assertSame('[Sprachgruppe ← (en) Example Shop, (fr) Example Shop]', $result);
    }

    public function testMasterLabelWithSingleSlave(): void
    {
        $slaves = [
            ['language' => 'en', 'title' => 'English Shop'],
        ];

        $result = $this->instance->formatLanguageGroupMasterLabel($slaves);

        $this->assertSame('[Sprachgruppe ← (en) English Shop]', $result);
    }

    public function testMasterLabelReturnsEmptyStringForEmptySlaves(): void
    {
        $result = $this->instance->formatLanguageGroupMasterLabel([]);

        $this->assertSame('', $result);
    }

    public function testSlaveLabelFormat(): void
    {
        $result = $this->instance->formatLanguageGroupSlaveLabel('de', 'Example Shop');

        $this->assertSame('[Sprachgruppe → (de) Example Shop]', $result);
    }

    public function testSlaveLabelIncludesLanguagePrefix(): void
    {
        $result = $this->instance->formatLanguageGroupSlaveLabel('it', 'Negozio');

        $this->assertStringContainsString('(it)', $result);
        $this->assertStringContainsString('Negozio', $result);
    }

    public function testMasterLabelLanguagePrefixPerSlave(): void
    {
        $slaves = [
            ['language' => 'en', 'title' => 'Shop EN'],
            ['language' => 'fr', 'title' => 'Shop FR'],
            ['language' => 'it', 'title' => 'Shop IT'],
        ];

        $result = $this->instance->formatLanguageGroupMasterLabel($slaves);

        $this->assertStringContainsString('(en) Shop EN', $result);
        $this->assertStringContainsString('(fr) Shop FR', $result);
        $this->assertStringContainsString('(it) Shop IT', $result);
    }
}
