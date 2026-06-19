<?php

declare(strict_types=1);

namespace LeadingSystems\LanguageSelectorBundle\Tests\Integration;

use PHPUnit\Framework\TestCase;

class LabelCallbackHintTest extends TestCase
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
            'MSC' => [
                'noMainLanguage' => 'Keine korrespondierende Hauptsprachseite definiert',
            ],
        ];

        $this->instance = (new \ReflectionClass(\tl_page_ls_cnc_languageSelector::class))
            ->newInstanceWithoutConstructor();
    }

    protected function tearDown(): void
    {
        $GLOBALS['TL_DCA'] = ['tl_page' => ['fields' => [], 'palettes' => [], 'config' => [], 'list' => []]];
        $GLOBALS['TL_LANG'] = [];
        \Contao\PageModel::$testCallback = null;
        parent::tearDown();
    }

    public function testHintShownForPageInSlaveTreeWithoutAssignment(): void
    {
        $nonFallbackResult = new HintMockResult([]);
        $slaveRootResult = new HintMockResult([
            ['id' => 10, 'ls_cnc_languageSelector_languageGroup' => 2],
        ]);

        $this->injectDatabase([$nonFallbackResult, $slaveRootResult]);

        $this->setPageModel(42, 'regular', 10);

        $row = [
            'id' => 42,
            'type' => 'regular',
            'ls_cnc_languageSelector_correspondingMainLanguagePage' => 0,
            'ls_cnc_languageSelector_languageGroup' => 0,
        ];

        $result = $this->instance->showMessageIfNoCorrespondingPageSelected(
            $row, 'Testseite', null, ''
        );

        $this->assertStringContainsString('Keine korrespondierende Hauptsprachseite definiert', $result);
    }

    public function testNoHintForPageInMasterTree(): void
    {
        $nonFallbackResult = new HintMockResult([]);
        $slaveRootResult = new HintMockResult([]);

        $this->injectDatabase([$nonFallbackResult, $slaveRootResult]);

        $this->setPageModel(42, 'regular', 1);

        $row = [
            'id' => 42,
            'type' => 'regular',
            'ls_cnc_languageSelector_correspondingMainLanguagePage' => 0,
            'ls_cnc_languageSelector_languageGroup' => 0,
        ];

        $result = $this->instance->showMessageIfNoCorrespondingPageSelected(
            $row, 'Testseite', null, ''
        );

        $this->assertStringNotContainsString('Keine korrespondierende Hauptsprachseite definiert', $result);
    }

    public function testMasterLabelShownOnMasterRootPage(): void
    {
        $slavesQueryResult = new HintMockResult([
            ['id' => 10, 'title' => 'English Shop', 'language' => 'en'],
            ['id' => 11, 'title' => 'French Shop', 'language' => 'fr'],
        ]);

        $this->injectDatabase([$slavesQueryResult]);

        $row = [
            'id' => 1,
            'type' => 'root',
            'ls_cnc_languageSelector_correspondingMainLanguagePage' => 0,
            'ls_cnc_languageSelector_languageGroup' => 0,
        ];

        $result = $this->instance->showMessageIfNoCorrespondingPageSelected(
            $row, 'DE Shop', null, ''
        );

        $this->assertStringContainsString('[Sprachgruppe ←', $result);
        $this->assertStringContainsString('(en) English Shop', $result);
        $this->assertStringContainsString('(fr) French Shop', $result);
    }

    public function testSlaveLabelShownOnSlaveRootPage(): void
    {
        $slavesQueryResult = new HintMockResult([]);
        $masterQueryResult = new HintMockResult([
            ['id' => 1, 'title' => 'DE Shop', 'language' => 'de'],
        ]);

        $this->injectDatabase([$slavesQueryResult, $masterQueryResult]);

        $row = [
            'id' => 10,
            'type' => 'root',
            'ls_cnc_languageSelector_correspondingMainLanguagePage' => 0,
            'ls_cnc_languageSelector_languageGroup' => 1,
        ];

        $result = $this->instance->showMessageIfNoCorrespondingPageSelected(
            $row, 'EN Shop', null, ''
        );

        $this->assertStringContainsString('[Sprachgruppe →', $result);
        $this->assertStringContainsString('(de) DE Shop', $result);
    }

    public function testNoLabelOnRootPageWithoutLanguageGroup(): void
    {
        $slavesQueryResult = new HintMockResult([]);

        $this->injectDatabase([$slavesQueryResult]);

        $row = [
            'id' => 5,
            'type' => 'root',
            'ls_cnc_languageSelector_correspondingMainLanguagePage' => 0,
            'ls_cnc_languageSelector_languageGroup' => 0,
        ];

        $result = $this->instance->showMessageIfNoCorrespondingPageSelected(
            $row, 'Standalone Root', null, ''
        );

        $this->assertStringNotContainsString('Sprachgruppe', $result);
    }

    /**
     * @param HintMockResult[] $results
     */
    private function injectDatabase(array $results): void
    {
        $database = new HintMockDatabase($results);
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

class HintMockDatabase
{
    /** @var HintMockResult[] */
    private array $results;
    private int $callIndex = 0;

    public function __construct(array $results)
    {
        $this->results = $results;
    }

    public function prepare(string $query): HintMockStatement
    {
        $result = $this->results[$this->callIndex] ?? new HintMockResult([]);
        $this->callIndex++;
        return new HintMockStatement($result);
    }
}

class HintMockStatement
{
    private HintMockResult $result;

    public function __construct(HintMockResult $result)
    {
        $this->result = $result;
    }

    public function limit(int $limit): self
    {
        return $this;
    }

    public function execute(...$params): HintMockResult
    {
        return $this->result;
    }
}

class HintMockResult
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
