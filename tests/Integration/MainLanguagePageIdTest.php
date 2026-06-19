<?php

declare(strict_types=1);

namespace LeadingSystems\LanguageSelectorBundle\Tests\Integration;

use Contao\Database;
use Contao\PageModel;
use LeadingSystems\LanguageSelector\LsController;
use PHPUnit\Framework\TestCase;

class MainLanguagePageIdTest extends TestCase
{
    private LsController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new LsController();

        $ref = new \ReflectionClass(LsController::class);
        $cacheProp = $ref->getProperty('cache_getMainlanguagePageIDForPageID');
        $cacheProp->setAccessible(true);
        $cacheProp->setValue(null, []);
    }

    protected function tearDown(): void
    {
        Database::setTestInstance(null);
        Database::$prepareCallback = null;
        PageModel::$testCallback = null;
        parent::tearDown();
    }

    /**
     * Hauptsprachseite in Language Group => eigene ID.
     *
     * Master-Root (id=1, de, shop.de) hat Slaves (id=2, en) und (id=3, fr).
     * Seite 10 liegt unter dem Master-Root => Hauptsprachseite => eigene ID.
     */
    public function testMainLanguagePageInLanguageGroupReturnsOwnId(): void
    {
        $rootPages = [
            1 => ['id' => 1, 'type' => 'root', 'language' => 'de', 'dns' => 'shop.de',
                  'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 0],
            2 => ['id' => 2, 'type' => 'root', 'language' => 'en', 'dns' => 'shop.com',
                  'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 1],
        ];

        $pages = [
            10 => ['id' => 10, 'rootId' => 1, 'type' => 'regular', 'domain' => 'shop.de',
                   'ls_cnc_languageSelector_correspondingMainLanguagePage' => 0],
        ];

        $this->setupMocks($rootPages, $pages);

        $result = $this->controller->getMainlanguagePageIDForPageID(10);

        $this->assertSame(10, $result);
    }

    /**
     * Nebenseite mit Zuordnung in Language Group => zugeordnete ID.
     *
     * Slave-Root (id=2, en) verweist auf Master (id=1, de).
     * Seite 20 liegt unter Slave-Root und hat Korrespondenz zu Seite 10 (unter Master).
     */
    public function testSecondaryPageWithAssignmentInLanguageGroupReturnsAssignedId(): void
    {
        $rootPages = [
            1 => ['id' => 1, 'type' => 'root', 'language' => 'de', 'dns' => 'shop.de',
                  'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 0],
            2 => ['id' => 2, 'type' => 'root', 'language' => 'en', 'dns' => 'shop.com',
                  'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 1],
        ];

        $pages = [
            20 => ['id' => 20, 'rootId' => 2, 'type' => 'regular', 'domain' => 'shop.com',
                   'ls_cnc_languageSelector_correspondingMainLanguagePage' => 10],
            10 => ['id' => 10, 'rootId' => 1, 'type' => 'regular', 'domain' => 'shop.de',
                   'ls_cnc_languageSelector_correspondingMainLanguagePage' => 0],
        ];

        $this->setupMocks($rootPages, $pages);

        $result = $this->controller->getMainlanguagePageIDForPageID(20);

        $this->assertSame(10, $result);
    }

    /**
     * Nebenseite mit Zuordnung ausserhalb der Gruppe => 0.
     *
     * Slave-Root (id=2) gehoert zu Gruppe mit Master (id=1).
     * Seite 20 hat Korrespondenz zu Seite 30, die unter Root 5
     * (einer anderen Gruppe) liegt.
     */
    public function testSecondaryPageWithAssignmentOutsideGroupReturnsZero(): void
    {
        $rootPages = [
            1 => ['id' => 1, 'type' => 'root', 'language' => 'de', 'dns' => 'shop.de',
                  'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 0],
            2 => ['id' => 2, 'type' => 'root', 'language' => 'en', 'dns' => 'shop.com',
                  'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 1],
            5 => ['id' => 5, 'type' => 'root', 'language' => 'de', 'dns' => 'other.de',
                  'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 0],
        ];

        $pages = [
            20 => ['id' => 20, 'rootId' => 2, 'type' => 'regular', 'domain' => 'shop.com',
                   'ls_cnc_languageSelector_correspondingMainLanguagePage' => 30],
            30 => ['id' => 30, 'rootId' => 5, 'type' => 'regular', 'domain' => 'other.de',
                   'ls_cnc_languageSelector_correspondingMainLanguagePage' => 0],
        ];

        $this->setupMocks($rootPages, $pages);

        $result = $this->controller->getMainlanguagePageIDForPageID(20);

        $this->assertSame(0, $result);
    }

    /**
     * Nebenseite ohne Zuordnung => 0.
     *
     * Slave-Root (id=2) gehoert zur Gruppe, aber Seite 20 hat keine Korrespondenz.
     */
    public function testSecondaryPageWithoutAssignmentReturnsZero(): void
    {
        $rootPages = [
            1 => ['id' => 1, 'type' => 'root', 'language' => 'de', 'dns' => 'shop.de',
                  'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 0],
            2 => ['id' => 2, 'type' => 'root', 'language' => 'en', 'dns' => 'shop.com',
                  'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 1],
        ];

        $pages = [
            20 => ['id' => 20, 'rootId' => 2, 'type' => 'regular', 'domain' => 'shop.com',
                   'ls_cnc_languageSelector_correspondingMainLanguagePage' => 0],
        ];

        $this->setupMocks($rootPages, $pages);

        $result = $this->controller->getMainlanguagePageIDForPageID(20);

        $this->assertSame(0, $result);
    }

    /**
     * Bisheriges dns-basiertes Verhalten ohne Language Group.
     *
     * Fallback-Root (id=1, de) und Nicht-Fallback-Root (id=2, en) auf gleicher Domain.
     * Keine Language Group konfiguriert.
     * Seite 20 (en) hat Korrespondenz zu Seite 10 (de) auf gleicher Domain.
     */
    public function testDnsBasedBehaviorWithoutLanguageGroup(): void
    {
        $rootPages = [
            1 => ['id' => 1, 'type' => 'root', 'language' => 'de', 'dns' => 'example.com',
                  'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 0],
            2 => ['id' => 2, 'type' => 'root', 'language' => 'en', 'dns' => 'example.com',
                  'fallback' => 0, 'ls_cnc_languageSelector_languageGroup' => 0],
        ];

        $pages = [
            10 => ['id' => 10, 'rootId' => 1, 'type' => 'regular', 'domain' => 'example.com',
                   'ls_cnc_languageSelector_correspondingMainLanguagePage' => 0],
            20 => ['id' => 20, 'rootId' => 2, 'type' => 'regular', 'domain' => 'example.com',
                   'ls_cnc_languageSelector_correspondingMainLanguagePage' => 10],
        ];

        $this->setupMocks($rootPages, $pages);

        $resultMain = $this->controller->getMainlanguagePageIDForPageID(10);
        $this->assertSame(10, $resultMain, 'Fallback-Root-Seite gibt eigene ID zurueck');

        $ref = new \ReflectionClass(LsController::class);
        $cacheProp = $ref->getProperty('cache_getMainlanguagePageIDForPageID');
        $cacheProp->setAccessible(true);
        $cacheProp->setValue(null, []);

        $resultSecondary = $this->controller->getMainlanguagePageIDForPageID(20);
        $this->assertSame(10, $resultSecondary, 'Nebenseite gibt zugeordnete ID zurueck');
    }

    /**
     * Nebenseite mit Korrespondenz auf andere Domain ohne Language Group => 0.
     */
    public function testDnsBasedRejectsCrossDomainWithoutLanguageGroup(): void
    {
        $rootPages = [
            1 => ['id' => 1, 'type' => 'root', 'language' => 'de', 'dns' => 'site-a.com',
                  'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 0],
            2 => ['id' => 2, 'type' => 'root', 'language' => 'en', 'dns' => 'site-a.com',
                  'fallback' => 0, 'ls_cnc_languageSelector_languageGroup' => 0],
        ];

        $pages = [
            20 => ['id' => 20, 'rootId' => 2, 'type' => 'regular', 'domain' => 'site-a.com',
                   'ls_cnc_languageSelector_correspondingMainLanguagePage' => 30],
            30 => ['id' => 30, 'rootId' => 3, 'type' => 'regular', 'domain' => 'site-b.com',
                   'ls_cnc_languageSelector_correspondingMainLanguagePage' => 0],
        ];

        $this->setupMocks($rootPages, $pages);

        $result = $this->controller->getMainlanguagePageIDForPageID(20);

        $this->assertSame(0, $result);
    }

    /**
     * `false` als Eingabe => 0.
     */
    public function testFalseInputReturnsZero(): void
    {
        $result = $this->controller->getMainlanguagePageIDForPageID(false);

        $this->assertSame(0, $result);
    }

    private function setupMocks(array $rootPages, array $pages): void
    {
        PageModel::$testCallback = function ($id) use ($rootPages, $pages) {
            if (isset($pages[$id])) {
                return (object) $pages[$id];
            }
            if (isset($rootPages[$id])) {
                return (object) $rootPages[$id];
            }
            return null;
        };

        Database::$prepareCallback = function (string $query) use ($rootPages, $pages) {
            return new class($query, $rootPages, $pages) {
                private string $query;
                private array $rootPages;
                private array $pages;

                public function __construct(string $query, array $rootPages, array $pages) {
                    $this->query = $query;
                    $this->rootPages = $rootPages;
                    $this->pages = $pages;
                }

                public function limit($l) { return $this; }

                public function execute(...$params) {
                    if (str_contains($this->query, 'WHERE `id` = ?')
                        && !str_contains($this->query, 'ls_cnc_languageSelector_languageGroup')) {
                        $id = $params[0] ?? 0;
                        if (isset($this->rootPages[$id])) {
                            return new MainLangMockResult([$this->rootPages[$id]]);
                        }
                        return new MainLangMockResult([]);
                    }

                    if (str_contains($this->query, 'ls_cnc_languageSelector_languageGroup')
                        && str_contains($this->query, 'LIMIT 1')) {
                        $id = $params[0] ?? 0;
                        foreach ($this->rootPages as $rp) {
                            if (($rp['ls_cnc_languageSelector_languageGroup'] ?? 0) == $id && $id != 0) {
                                return new MainLangMockResult([['id' => $rp['id']]]);
                            }
                        }
                        return new MainLangMockResult([]);
                    }

                    if (str_contains($this->query, 'ls_cnc_languageSelector_languageGroup FROM')) {
                        $id = $params[0] ?? 0;
                        if (isset($this->rootPages[$id])) {
                            return new MainLangMockResult([[
                                'ls_cnc_languageSelector_languageGroup' =>
                                    $this->rootPages[$id]['ls_cnc_languageSelector_languageGroup'] ?? 0,
                            ]]);
                        }
                        return new MainLangMockResult([]);
                    }

                    return new MainLangMockResult([]);
                }
            };
        };

        $db = Database::getInstance();
        Database::setTestInstance($db);
    }
}

class MainLangMockResult
{
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

    public function row(): array
    {
        if ($this->index >= 0 && $this->index < count($this->rows)) {
            return $this->rows[$this->index];
        }
        return [];
    }
}
