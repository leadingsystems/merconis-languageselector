<?php

declare(strict_types=1);

namespace LeadingSystems\LanguageSelectorBundle\Tests\Integration;

use PHPUnit\Framework\TestCase;

class LanguageGroupFieldLockTest extends TestCase
{
    private \tl_page_ls_cnc_languageSelector $instance;

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['TL_DCA'] = [
            'tl_page' => [
                'fields' => [
                    'ls_cnc_languageSelector_languageGroup' => [
                        'label' => ['Sprachgruppe', 'Beschreibung'],
                        'eval' => [
                            'includeBlankOption' => true,
                            'tl_class' => 'w50',
                        ],
                    ],
                ],
                'palettes' => [
                    'root' => '{title_legend},title;{dns_legend},dns;{language_legend},language,fallback;',
                    'rootfallback' => '{title_legend},title;{dns_legend},dns;{language_legend},language,fallback;',
                ],
            ],
        ];
        $GLOBALS['TL_LANG'] = [
            'tl_page' => [
                'languageGroupMasterInfo' => 'Hauptsprache einer Sprachgruppe. Zugehörige Nebensprachen: %s',
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

    public function testFieldIsDisabledWhenSlavesExist(): void
    {
        $pageResult = new IntegrationMockResult([
            ['id' => 1, 'type' => 'root', 'fallback' => 1],
        ]);
        $slavesResult = new IntegrationMockResult([
            ['id' => 2, 'title' => 'English Shop', 'language' => 'en'],
            ['id' => 3, 'title' => 'French Shop', 'language' => 'fr'],
        ]);

        $this->injectDatabase([$pageResult, $slavesResult]);

        $dc = new \stdClass();
        $dc->id = 1;

        $_GET['act'] = 'edit';
        $this->instance->handleLanguageGroupField($dc);
        unset($_GET['act']);

        $this->assertTrue(
            $GLOBALS['TL_DCA']['tl_page']['fields']['ls_cnc_languageSelector_languageGroup']['eval']['disabled']
        );
    }

    public function testFieldShowsSlaveListInDescription(): void
    {
        $pageResult = new IntegrationMockResult([
            ['id' => 1, 'type' => 'root', 'fallback' => 1],
        ]);
        $slavesResult = new IntegrationMockResult([
            ['id' => 2, 'title' => 'English Shop', 'language' => 'en'],
        ]);

        $this->injectDatabase([$pageResult, $slavesResult]);

        $dc = new \stdClass();
        $dc->id = 1;

        $_GET['act'] = 'edit';
        $this->instance->handleLanguageGroupField($dc);
        unset($_GET['act']);

        $description = $GLOBALS['TL_DCA']['tl_page']['fields']['ls_cnc_languageSelector_languageGroup']['label'][1];
        $this->assertStringContainsString('(en) English Shop', $description);
    }

    public function testFieldIsNotDisabledWhenNoSlavesExist(): void
    {
        $pageResult = new IntegrationMockResult([
            ['id' => 5, 'type' => 'root', 'fallback' => 1],
        ]);
        $slavesResult = new IntegrationMockResult([]);

        $this->injectDatabase([$pageResult, $slavesResult]);

        $dc = new \stdClass();
        $dc->id = 5;

        $_GET['act'] = 'edit';
        $this->instance->handleLanguageGroupField($dc);
        unset($_GET['act']);

        $this->assertArrayNotHasKey(
            'disabled',
            $GLOBALS['TL_DCA']['tl_page']['fields']['ls_cnc_languageSelector_languageGroup']['eval']
        );
    }

    public function testPaletteInsertionOnFallbackRootPage(): void
    {
        $pageResult = new IntegrationMockResult([
            ['id' => 1, 'type' => 'root', 'fallback' => 1],
        ]);
        $slavesResult = new IntegrationMockResult([]);

        $this->injectDatabase([$pageResult, $slavesResult]);

        $dc = new \stdClass();
        $dc->id = 1;

        $_GET['act'] = 'edit';
        $this->instance->handleLanguageGroupField($dc);
        unset($_GET['act']);

        $this->assertStringContainsString(
            'ls_cnc_languageSelector_languageGroup',
            $GLOBALS['TL_DCA']['tl_page']['palettes']['rootfallback']
        );
    }

    public function testNoPaletteInsertionOnNonFallbackRootPage(): void
    {
        $pageResult = new IntegrationMockResult([
            ['id' => 1, 'type' => 'root', 'fallback' => 0],
        ]);

        $this->injectDatabase([$pageResult]);

        $dc = new \stdClass();
        $dc->id = 1;

        $_GET['act'] = 'edit';
        $this->instance->handleLanguageGroupField($dc);
        unset($_GET['act']);

        $this->assertStringNotContainsString(
            'ls_cnc_languageSelector_languageGroup',
            $GLOBALS['TL_DCA']['tl_page']['palettes']['root']
        );
    }

    public function testNoPaletteInsertionOnNonRootPage(): void
    {
        $pageResult = new IntegrationMockResult([
            ['id' => 1, 'type' => 'regular', 'fallback' => 0],
        ]);

        $this->injectDatabase([$pageResult]);

        $dc = new \stdClass();
        $dc->id = 1;

        $_GET['act'] = 'edit';
        $this->instance->handleLanguageGroupField($dc);
        unset($_GET['act']);

        $this->assertStringNotContainsString(
            'ls_cnc_languageSelector_languageGroup',
            $GLOBALS['TL_DCA']['tl_page']['palettes']['root']
        );
    }

    /**
     * @param IntegrationMockResult[] $results
     */
    private function injectDatabase(array $results): void
    {
        $database = new IntegrationMockDatabase($results);
        $this->instance->setTestDatabase($database);
    }
}

class IntegrationMockDatabase
{
    /** @var IntegrationMockResult[] */
    private array $results;
    private int $callIndex = 0;

    public function __construct(array $results)
    {
        $this->results = $results;
    }

    public function prepare(string $query): IntegrationMockStatement
    {
        $result = $this->results[$this->callIndex] ?? new IntegrationMockResult([]);
        $this->callIndex++;
        return new IntegrationMockStatement($result);
    }
}

class IntegrationMockStatement
{
    private IntegrationMockResult $result;

    public function __construct(IntegrationMockResult $result)
    {
        $this->result = $result;
    }

    public function limit(int $limit): self
    {
        return $this;
    }

    public function execute(...$params): IntegrationMockResult
    {
        return $this->result;
    }
}

class IntegrationMockResult
{
    /** @var array<int, array<string, mixed>> */
    private array $rows;
    private int $index = -1;
    private bool $fetched = false;
    public int $numRows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
        $this->numRows = count($rows);
    }

    public function next(): bool
    {
        $this->index++;
        $this->fetched = true;
        return $this->index < count($this->rows);
    }

    public function __get(string $name): mixed
    {
        if (!$this->fetched && $this->numRows > 0) {
            $this->index = 0;
            $this->fetched = true;
        }
        if ($this->index >= 0 && $this->index < count($this->rows)) {
            return $this->rows[$this->index][$name] ?? null;
        }
        return null;
    }
}
