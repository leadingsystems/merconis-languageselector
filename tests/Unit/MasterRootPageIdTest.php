<?php

declare(strict_types=1);

namespace LeadingSystems\LanguageSelectorBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;

class MasterRootPageIdTest extends TestCase
{
    private \tl_page_ls_cnc_languageSelector $instance;

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['TL_DCA'] = ['tl_page' => ['fields' => [], 'palettes' => []]];
        $GLOBALS['TL_LANG'] = ['tl_page' => []];

        $this->instance = (new \ReflectionClass(\tl_page_ls_cnc_languageSelector::class))
            ->newInstanceWithoutConstructor();
    }

    protected function tearDown(): void
    {
        $GLOBALS['TL_DCA'] = ['tl_page' => ['fields' => [], 'palettes' => [], 'config' => [], 'list' => []]];
        $GLOBALS['TL_LANG'] = [];
        parent::tearDown();
    }

    public function testReturnsLanguageGroupValueWhenSet(): void
    {
        $this->injectDatabase([
            new MasterRootMockResult([
                ['id' => 5, 'dns' => 'example.com', 'ls_cnc_languageSelector_languageGroup' => 2],
            ]),
        ]);

        $result = $this->instance->getMasterRootPageId(5);

        $this->assertSame(2, $result);
    }

    public function testReturnsFallbackRootWhenNoLanguageGroup(): void
    {
        $rootPageResult = new MasterRootMockResult([
            ['id' => 5, 'dns' => 'example.com', 'ls_cnc_languageSelector_languageGroup' => 0],
        ]);
        $fallbackResult = new MasterRootMockResult([
            ['id' => 1],
        ]);

        $this->injectDatabase([$rootPageResult, $fallbackResult]);

        $result = $this->instance->getMasterRootPageId(5);

        $this->assertSame(1, $result);
    }

    public function testReturnsZeroWhenNoFallbackRootFound(): void
    {
        $rootPageResult = new MasterRootMockResult([
            ['id' => 5, 'dns' => 'other.com', 'ls_cnc_languageSelector_languageGroup' => 0],
        ]);
        $fallbackResult = new MasterRootMockResult([]);

        $this->injectDatabase([$rootPageResult, $fallbackResult]);

        $result = $this->instance->getMasterRootPageId(5);

        $this->assertSame(0, $result);
    }

    public function testReturnsZeroWhenRootPageNotFound(): void
    {
        $this->injectDatabase([
            new MasterRootMockResult([]),
        ]);

        $result = $this->instance->getMasterRootPageId(999);

        $this->assertSame(0, $result);
    }

    public function testReturnsMasterIdAsIntegerType(): void
    {
        $this->injectDatabase([
            new MasterRootMockResult([
                ['id' => 7, 'dns' => 'shop.de', 'ls_cnc_languageSelector_languageGroup' => 3],
            ]),
        ]);

        $result = $this->instance->getMasterRootPageId(7);

        $this->assertIsInt($result);
    }

    /**
     * @param MasterRootMockResult[] $results
     */
    private function injectDatabase(array $results): void
    {
        $database = new MasterRootMockDatabase($results);
        $this->instance->setTestDatabase($database);
    }
}

class MasterRootMockDatabase
{
    /** @var MasterRootMockResult[] */
    private array $results;
    private int $callIndex = 0;

    public function __construct(array $results)
    {
        $this->results = $results;
    }

    public function prepare(string $query): MasterRootMockStatement
    {
        $result = $this->results[$this->callIndex] ?? new MasterRootMockResult([]);
        $this->callIndex++;
        return new MasterRootMockStatement($result);
    }
}

class MasterRootMockStatement
{
    private MasterRootMockResult $result;

    public function __construct(MasterRootMockResult $result)
    {
        $this->result = $result;
    }

    public function limit(int $limit): self
    {
        return $this;
    }

    public function execute(...$params): MasterRootMockResult
    {
        return $this->result;
    }
}

class MasterRootMockResult
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
