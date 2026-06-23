<?php

declare(strict_types=1);

namespace LeadingSystems\LanguageSelectorBundle\Tests\Integration;

use PHPUnit\Framework\TestCase;

class CorrespondenceFieldVisibilityTest extends TestCase
{
    private \tl_page_ls_cnc_languageSelector $instance;

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['TL_DCA'] = [
            'tl_page' => [
                'fields' => [
                    'title' => ['eval' => ['tl_class' => 'w50']],
                    'alias' => ['eval' => ['tl_class' => 'w50']],
                    'ls_cnc_languageSelector_correspondingMainLanguagePage' => [
                        'eval' => ['fieldType' => 'radio', 'tl_class' => 'w50'],
                    ],
                ],
                'palettes' => [
                    'regular' => '{title_legend},title,alias;{meta_legend},description;',
                ],
            ],
        ];
        $GLOBALS['TL_LANG'] = ['tl_page' => []];

        $this->instance = (new \ReflectionClass(\tl_page_ls_cnc_languageSelector::class))
            ->newInstanceWithoutConstructor();
    }

    protected function tearDown(): void
    {
        $GLOBALS['TL_DCA'] = ['tl_page' => ['fields' => [], 'palettes' => [], 'config' => [], 'list' => []]];
        $GLOBALS['TL_LANG'] = [];
        unset($_GET['act']);
        parent::tearDown();
    }

    public function testFieldVisibleForSlaveRootPage(): void
    {
        $nonFallbackResult = new VisibilityMockResult([]);
        $slaveRootResult = new VisibilityMockResult([
            ['id' => 10, 'ls_cnc_languageSelector_languageGroup' => 2],
        ]);
        $masterRootResult = new VisibilityMockResult([
            ['id' => 10, 'dns' => 'example.com', 'ls_cnc_languageSelector_languageGroup' => 2],
        ]);

        $this->injectDatabase([$nonFallbackResult, $slaveRootResult, $masterRootResult]);
        $this->setPageModel(42, 'regular', 10);

        $_GET['act'] = 'edit';
        $dc = new \stdClass();
        $dc->id = 42;

        $this->instance->insertSelectorForCorrespondingMainLanguagePage($dc);

        $this->assertStringContainsString(
            'ls_cnc_languageSelector_correspondingMainLanguagePage',
            $GLOBALS['TL_DCA']['tl_page']['palettes']['regular']
        );
    }

    public function testFieldVisibleForNonFallbackRootPage(): void
    {
        $nonFallbackResult = new VisibilityMockResult([
            ['id' => 10, 'fallback' => 0],
        ]);
        $slaveRootResult = new VisibilityMockResult([]);
        $masterRootResult = new VisibilityMockResult([
            ['id' => 10, 'dns' => 'example.com', 'ls_cnc_languageSelector_languageGroup' => 0],
        ]);
        $fallbackResult = new VisibilityMockResult([
            ['id' => 1],
        ]);

        $this->injectDatabase([$nonFallbackResult, $slaveRootResult, $masterRootResult, $fallbackResult]);
        $this->setPageModel(42, 'regular', 10);

        $_GET['act'] = 'edit';
        $dc = new \stdClass();
        $dc->id = 42;

        $this->instance->insertSelectorForCorrespondingMainLanguagePage($dc);

        $this->assertStringContainsString(
            'ls_cnc_languageSelector_correspondingMainLanguagePage',
            $GLOBALS['TL_DCA']['tl_page']['palettes']['regular']
        );
    }

    public function testFieldNotVisibleForFallbackRootPage(): void
    {
        $nonFallbackResult = new VisibilityMockResult([]);
        $slaveRootResult = new VisibilityMockResult([]);

        $this->injectDatabase([$nonFallbackResult, $slaveRootResult]);
        $this->setPageModel(42, 'regular', 10);

        $_GET['act'] = 'edit';
        $dc = new \stdClass();
        $dc->id = 42;

        $this->instance->insertSelectorForCorrespondingMainLanguagePage($dc);

        $this->assertStringNotContainsString(
            'ls_cnc_languageSelector_correspondingMainLanguagePage',
            $GLOBALS['TL_DCA']['tl_page']['palettes']['regular']
        );
    }

    public function testRootNodesSetForSlaveRootPage(): void
    {
        $nonFallbackResult = new VisibilityMockResult([]);
        $slaveRootResult = new VisibilityMockResult([
            ['id' => 10, 'ls_cnc_languageSelector_languageGroup' => 2],
        ]);
        $masterRootResult = new VisibilityMockResult([
            ['id' => 10, 'dns' => 'example.com', 'ls_cnc_languageSelector_languageGroup' => 2],
        ]);

        $this->injectDatabase([$nonFallbackResult, $slaveRootResult, $masterRootResult]);
        $this->setPageModel(42, 'regular', 10);

        $_GET['act'] = 'edit';
        $dc = new \stdClass();
        $dc->id = 42;

        $this->instance->insertSelectorForCorrespondingMainLanguagePage($dc);

        $this->assertSame(
            [2],
            $GLOBALS['TL_DCA']['tl_page']['fields']['ls_cnc_languageSelector_correspondingMainLanguagePage']['eval']['rootNodes']
        );
    }

    public function testEditAllInsertsFieldUnconditionally(): void
    {
        $_GET['act'] = 'editAll';
        $dc = new \stdClass();
        $dc->id = null;

        $this->instance->insertSelectorForCorrespondingMainLanguagePage($dc);

        $this->assertStringContainsString(
            'ls_cnc_languageSelector_correspondingMainLanguagePage',
            $GLOBALS['TL_DCA']['tl_page']['palettes']['regular']
        );
    }

    /**
     * @param VisibilityMockResult[] $results
     */
    private function injectDatabase(array $results): void
    {
        $database = new VisibilityMockDatabase($results);
        $this->instance->setTestDatabase($database);
    }

    private function setPageModel(int $id, string $type, int $rootId): void
    {
        $pageModel = new \stdClass();
        $pageModel->id = $id;
        $pageModel->type = $type;
        $pageModel->rootId = $rootId;

        \Contao\PageModel::$testCallback = function ($findId) use ($pageModel, $id) {
            if ($findId == $id) {
                return $pageModel;
            }
            return null;
        };
    }
}

class VisibilityMockDatabase
{
    /** @var VisibilityMockResult[] */
    private array $results;
    private int $callIndex = 0;

    public function __construct(array $results)
    {
        $this->results = $results;
    }

    public function prepare(string $query): VisibilityMockStatement
    {
        $result = $this->results[$this->callIndex] ?? new VisibilityMockResult([]);
        $this->callIndex++;
        return new VisibilityMockStatement($result);
    }
}

class VisibilityMockStatement
{
    private VisibilityMockResult $result;

    public function __construct(VisibilityMockResult $result)
    {
        $this->result = $result;
    }

    public function limit(int $limit): self
    {
        return $this;
    }

    public function execute(...$params): VisibilityMockResult
    {
        return $this->result;
    }
}

class VisibilityMockResult
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
