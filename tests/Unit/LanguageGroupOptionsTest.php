<?php

declare(strict_types=1);

namespace LeadingSystems\LanguageSelectorBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;

class LanguageGroupOptionsTest extends TestCase
{
    private \tl_page_ls_cnc_languageSelector $instance;

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['TL_DCA'] = ['tl_page' => ['fields' => [], 'palettes' => []]];
        $GLOBALS['TL_LANG'] = ['tl_page' => []];

        $this->instance = $this->createTestableInstance();
    }

    protected function tearDown(): void
    {
        $GLOBALS['TL_DCA'] = ['tl_page' => ['fields' => [], 'palettes' => [], 'config' => [], 'list' => []]];
        $GLOBALS['TL_LANG'] = [];
        parent::tearDown();
    }

    public function testReturnsEmptyArrayWhenNoRootPagesExist(): void
    {
        $this->setDatabaseResult([]);

        $result = $this->instance->buildLanguageGroupOptions(1);

        $this->assertSame([], $result);
    }

    public function testReturnsSingleMasterCandidate(): void
    {
        $this->setDatabaseResult([
            ['id' => 2, 'title' => 'Example Shop', 'language' => 'de'],
        ]);

        $result = $this->instance->buildLanguageGroupOptions(1);

        $this->assertSame([2 => '[de] Example Shop'], $result);
    }

    public function testReturnsMultipleMasterCandidates(): void
    {
        $this->setDatabaseResult([
            ['id' => 2, 'title' => 'Example Shop DE', 'language' => 'de'],
            ['id' => 3, 'title' => 'Example Shop EN', 'language' => 'en'],
            ['id' => 4, 'title' => 'Example Shop FR', 'language' => 'fr'],
        ]);

        $result = $this->instance->buildLanguageGroupOptions(1);

        $this->assertCount(3, $result);
        $this->assertSame('[de] Example Shop DE', $result[2]);
        $this->assertSame('[en] Example Shop EN', $result[3]);
        $this->assertSame('[fr] Example Shop FR', $result[4]);
    }

    public function testExcludesCurrentPage(): void
    {
        $this->setDatabaseResult([
            ['id' => 2, 'title' => 'Other Root', 'language' => 'en'],
        ]);

        $result = $this->instance->buildLanguageGroupOptions(5);

        $this->assertArrayNotHasKey(5, $result);
        $this->assertSame([2 => '[en] Other Root'], $result);
    }

    public function testLabelFormatIncludesLanguagePrefix(): void
    {
        $this->setDatabaseResult([
            ['id' => 10, 'title' => 'Shop', 'language' => 'it'],
        ]);

        $result = $this->instance->buildLanguageGroupOptions(1);

        $this->assertMatchesRegularExpression('/^\[it\] Shop$/', $result[10]);
    }

    public function testCurrentPageIsAlreadyMasterReturnsOnlyNonSlaves(): void
    {
        // Mock liefert nur Ergebnisse, die das SQL nach Filterung (`id != ?`) zurückgäbe
        $this->setDatabaseResult([
            ['id' => 7, 'title' => 'Root B', 'language' => 'en'],
            ['id' => 9, 'title' => 'Root C', 'language' => 'fr'],
        ]);

        $result = $this->instance->buildLanguageGroupOptions(3);

        $this->assertArrayNotHasKey(3, $result);
        $this->assertCount(2, $result);
        $this->assertSame('[en] Root B', $result[7]);
        $this->assertSame('[fr] Root C', $result[9]);
    }

    /**
     * @param array<int, array{id: int, title: string, language: string}> $rows
     */
    private function setDatabaseResult(array $rows): void
    {
        $resultMock = new MockDatabaseResult($rows);
        $statementMock = new MockDatabaseStatement($resultMock);
        $databaseMock = new MockDatabase($statementMock);

        $this->instance->setTestDatabase($databaseMock);
    }

    private function createTestableInstance(): \tl_page_ls_cnc_languageSelector
    {
        $instance = (new \ReflectionClass(\tl_page_ls_cnc_languageSelector::class))
            ->newInstanceWithoutConstructor();

        return $instance;
    }
}

class MockDatabase
{
    private MockDatabaseStatement $statement;

    public function __construct(MockDatabaseStatement $statement)
    {
        $this->statement = $statement;
    }

    public function prepare(string $query): MockDatabaseStatement
    {
        return $this->statement;
    }
}

class MockDatabaseStatement
{
    private MockDatabaseResult $result;

    public function __construct(MockDatabaseResult $result)
    {
        $this->result = $result;
    }

    public function limit(int $limit): self
    {
        return $this;
    }

    public function execute(...$params): MockDatabaseResult
    {
        return $this->result;
    }
}

class MockDatabaseResult
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
