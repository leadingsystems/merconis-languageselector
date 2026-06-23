<?php

declare(strict_types=1);

namespace LeadingSystems\LanguageSelectorBundle\Tests\Integration;

use Contao\Database;
use Contao\PageModel;
use LeadingSystems\LanguageSelector\LsController;
use PHPUnit\Framework\TestCase;

class LanguageResolutionTest extends TestCase
{
    private LsController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        \Contao\System::$testLocales = [
            'de' => ['de' => 'Deutsch', 'en' => 'Englisch', 'fr' => 'Französisch', 'de_AT' => 'Deutsch (Österreich)'],
            'en' => ['de' => 'German', 'en' => 'English', 'fr' => 'French', 'de_AT' => 'Austrian German'],
            'fr' => ['de' => 'allemand', 'en' => 'anglais', 'fr' => 'français', 'de_AT' => 'allemand (Autriche)'],
            'de_AT' => ['de' => 'Deutsch', 'en' => 'Englisch', 'fr' => 'Französisch', 'de_AT' => 'Deutsch (Österreich)'],
        ];

        $GLOBALS['merconis-languageselector_globals'] = [];
        $GLOBALS['LS_LANGUAGESELECTOR_HOOKS'] = [];
        $_GET = [];
        $_SERVER['request'] = '';

        $this->controller = new LsController();

        $ref = new \ReflectionClass(LsController::class);
        $cacheProp = $ref->getProperty('cache_getCorrespondingLanguagesForCurrentRootPage');
        $cacheProp->setAccessible(true);
        $cacheProp->setValue(null, []);

        $cacheProp2 = $ref->getProperty('cache_getMainlanguagePageIDForPageID');
        $cacheProp2->setAccessible(true);
        $cacheProp2->setValue(null, []);
    }

    protected function tearDown(): void
    {
        Database::setTestInstance(null);
        Database::$prepareCallback = null;
        PageModel::$testCallback = null;
        PageModel::$findByIdCallback = null;
        PageModel::$findByAliasCallback = null;
        \Contao\System::$testLocales = [];
        $GLOBALS['merconis-languageselector_globals'] = [];
        $_GET = [];
        parent::tearDown();
    }

    /**
     * Szenario: Master (de, shop.de) + 2 Slaves (en, shop.com) und (fr, shop.fr)
     * Language Group ist konfiguriert.
     */
    public function testLanguageGroupWithMultipleDomainsReturnsAllGroupLanguages(): void
    {
        $rootPages = [
            1 => ['id' => 1, 'type' => 'root', 'language' => 'de', 'dns' => 'shop.de', 'published' => 1, 'sorting' => 10, 'alias' => 'de-root', 'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 0, 'rootUseSSL' => true, 'rootId' => 1],
            2 => ['id' => 2, 'type' => 'root', 'language' => 'en', 'dns' => 'shop.com', 'published' => 1, 'sorting' => 20, 'alias' => 'en-root', 'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 1, 'rootUseSSL' => true, 'rootId' => 2],
            3 => ['id' => 3, 'type' => 'root', 'language' => 'fr', 'dns' => 'shop.fr', 'published' => 1, 'sorting' => 30, 'alias' => 'fr-root', 'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 1, 'rootUseSSL' => true, 'rootId' => 3],
        ];

        $currentPage = (object) [
            'id' => 10, 'rootId' => 1, 'language' => 'de', 'type' => 'regular',
            'alias' => 'produkte', 'ls_cnc_languageSelector_correspondingMainLanguagePage' => 0,
        ];

        $this->setupDatabaseForLanguageGroup($rootPages, $currentPage);
        $this->setupPageModels($rootPages, $currentPage);

        $GLOBALS['objPage'] = $currentPage;

        $result = $this->controller->getCorrespondingLanguagesForCurrentRootPage();

        $this->assertArrayHasKey('de', $result);
        $this->assertArrayHasKey('en', $result);
        $this->assertArrayHasKey('fr', $result);
        $this->assertCount(3, $result);

        $this->assertSame('de', $result['de']['languageCode']);
        $this->assertSame('en', $result['en']['languageCode']);
        $this->assertSame('fr', $result['fr']['languageCode']);

        $this->assertSame('Deutsch', $result['de']['languageTitle']);
        $this->assertSame('English', $result['en']['languageTitle']);
        $this->assertSame('français', $result['fr']['languageTitle']);
    }

    /**
     * Szenario: Domain-Gleichheit (de + en auf gleicher Domain, kein Language Group)
     */
    public function testDomainEqualityReturnsOnlySameDomainLanguages(): void
    {
        $rootPages = [
            1 => ['id' => 1, 'type' => 'root', 'language' => 'de', 'dns' => 'example.com', 'published' => 1, 'sorting' => 10, 'alias' => 'de-root', 'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 0, 'rootUseSSL' => true, 'rootId' => 1],
            2 => ['id' => 2, 'type' => 'root', 'language' => 'en', 'dns' => 'example.com', 'published' => 1, 'sorting' => 20, 'alias' => 'en-root', 'fallback' => 0, 'ls_cnc_languageSelector_languageGroup' => 0, 'rootUseSSL' => true, 'rootId' => 2],
            3 => ['id' => 3, 'type' => 'root', 'language' => 'fr', 'dns' => 'other.com', 'published' => 1, 'sorting' => 30, 'alias' => 'fr-root', 'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 0, 'rootUseSSL' => true, 'rootId' => 3],
        ];

        $currentPage = (object) [
            'id' => 10, 'rootId' => 1, 'language' => 'de', 'type' => 'regular',
            'alias' => 'produkte', 'ls_cnc_languageSelector_correspondingMainLanguagePage' => 0,
        ];

        $this->setupDatabaseForDomainEquality($rootPages, $currentPage, 'example.com');
        $this->setupPageModels($rootPages, $currentPage);

        $GLOBALS['objPage'] = $currentPage;

        $result = $this->controller->getCorrespondingLanguagesForCurrentRootPage();

        $this->assertArrayHasKey('de', $result);
        $this->assertArrayHasKey('en', $result);
        $this->assertArrayNotHasKey('fr', $result);
        $this->assertCount(2, $result);

        $this->assertSame('de', $result['de']['languageCode']);
        $this->assertSame('en', $result['en']['languageCode']);
    }

    /**
     * Szenario: Gemischtes Setup -- eine Language Group (de+en) und
     * eine dns-basierte Website (fr, andere Domain ohne Language Group)
     */
    public function testMixedSetupOnlyReturnsLanguageGroupMembers(): void
    {
        $rootPages = [
            1 => ['id' => 1, 'type' => 'root', 'language' => 'de', 'dns' => 'shop.de', 'published' => 1, 'sorting' => 10, 'alias' => 'de-root', 'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 0, 'rootUseSSL' => true, 'rootId' => 1],
            2 => ['id' => 2, 'type' => 'root', 'language' => 'en', 'dns' => 'shop.com', 'published' => 1, 'sorting' => 20, 'alias' => 'en-root', 'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 1, 'rootUseSSL' => true, 'rootId' => 2],
            4 => ['id' => 4, 'type' => 'root', 'language' => 'fr', 'dns' => 'shop.de', 'published' => 1, 'sorting' => 40, 'alias' => 'fr-root', 'fallback' => 0, 'ls_cnc_languageSelector_languageGroup' => 0, 'rootUseSSL' => true, 'rootId' => 4],
        ];

        $currentPage = (object) [
            'id' => 10, 'rootId' => 1, 'language' => 'de', 'type' => 'regular',
            'alias' => 'produkte', 'ls_cnc_languageSelector_correspondingMainLanguagePage' => 0,
        ];

        $this->setupDatabaseForLanguageGroup($rootPages, $currentPage);
        $this->setupPageModels($rootPages, $currentPage);

        $GLOBALS['objPage'] = $currentPage;

        $result = $this->controller->getCorrespondingLanguagesForCurrentRootPage();

        $this->assertArrayHasKey('de', $result);
        $this->assertArrayHasKey('en', $result);
        $this->assertArrayNotHasKey('fr', $result);
    }

    /**
     * Domainübergreifende Links verwenden absolute URLs
     */
    public function testCrossDomainLinksUseAbsoluteUrls(): void
    {
        $rootPages = [
            1 => ['id' => 1, 'type' => 'root', 'language' => 'de', 'dns' => 'shop.de', 'published' => 1, 'sorting' => 10, 'alias' => 'de-root', 'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 0, 'rootUseSSL' => true, 'rootId' => 1],
            2 => ['id' => 2, 'type' => 'root', 'language' => 'en', 'dns' => 'shop.com', 'published' => 1, 'sorting' => 20, 'alias' => 'en-root', 'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 1, 'rootUseSSL' => true, 'rootId' => 2],
        ];

        $currentPage = (object) [
            'id' => 10, 'rootId' => 1, 'language' => 'de', 'type' => 'regular',
            'alias' => 'produkte', 'ls_cnc_languageSelector_correspondingMainLanguagePage' => 0,
        ];

        $this->setupDatabaseForLanguageGroup($rootPages, $currentPage);
        $this->setupPageModels($rootPages, $currentPage);

        $GLOBALS['objPage'] = $currentPage;

        $result = $this->controller->getCorrespondingLanguagesForCurrentRootPage();

        $this->assertStringStartsWith('https://shop.com/', $result['en']['href']);
    }

    /**
     * Innerhalb derselben Domain bleiben URLs relativ
     */
    public function testSameDomainLinksStayRelative(): void
    {
        $rootPages = [
            1 => ['id' => 1, 'type' => 'root', 'language' => 'de', 'dns' => 'example.com', 'published' => 1, 'sorting' => 10, 'alias' => 'de-root', 'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 0, 'rootUseSSL' => true, 'rootId' => 1],
            2 => ['id' => 2, 'type' => 'root', 'language' => 'en', 'dns' => 'example.com', 'published' => 1, 'sorting' => 20, 'alias' => 'en-root', 'fallback' => 0, 'ls_cnc_languageSelector_languageGroup' => 0, 'rootUseSSL' => true, 'rootId' => 2],
        ];

        $currentPage = (object) [
            'id' => 10, 'rootId' => 1, 'language' => 'de', 'type' => 'regular',
            'alias' => 'produkte', 'ls_cnc_languageSelector_correspondingMainLanguagePage' => 0,
        ];

        $this->setupDatabaseForDomainEquality($rootPages, $currentPage, 'example.com');
        $this->setupPageModels($rootPages, $currentPage);

        $GLOBALS['objPage'] = $currentPage;

        $result = $this->controller->getCorrespondingLanguagesForCurrentRootPage();

        $this->assertStringStartsNotWith('http', $result['en']['href']);
    }

    /**
     * Korrespondierende Seiten in anderer Domain verwenden absolute URLs.
     */
    public function testCorrespondingCrossDomainPagesUseAbsoluteUrls(): void
    {
        $rootPages = [
            1 => ['id' => 1, 'type' => 'root', 'language' => 'de', 'dns' => 'shop.de', 'published' => 1, 'sorting' => 10, 'alias' => 'de-root', 'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 0, 'rootUseSSL' => true, 'rootId' => 1],
            2 => ['id' => 2, 'type' => 'root', 'language' => 'en', 'dns' => 'shop.com', 'published' => 1, 'sorting' => 20, 'alias' => 'en-root', 'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 1, 'rootUseSSL' => true, 'rootId' => 2],
        ];

        $currentPage = (object) [
            'id' => 10, 'rootId' => 1, 'language' => 'de', 'type' => 'regular',
            'alias' => 'produkte', 'domain' => 'shop.de', 'pid' => 110, 'ls_cnc_languageSelector_correspondingMainLanguagePage' => 0,
        ];

        $pages = [
            10 => ['id' => 10, 'rootId' => 1, 'language' => 'de', 'type' => 'regular', 'alias' => 'produkte', 'domain' => 'shop.de', 'published' => 1, 'ls_cnc_languageSelector_correspondingMainLanguagePage' => 0],
            20 => ['id' => 20, 'rootId' => 2, 'language' => 'en', 'type' => 'regular', 'alias' => 'products', 'domain' => 'shop.com', 'published' => 1, 'ls_cnc_languageSelector_correspondingMainLanguagePage' => 10],
        ];

        $this->setupDatabaseForLanguageGroup($rootPages, $currentPage, $pages);
        $this->setupPageModels($rootPages, $currentPage, $pages);

        $GLOBALS['objPage'] = $currentPage;

        $result = $this->controller->getCorrespondingLanguagesForCurrentRootPage();

        $this->assertSame('https://shop.com/products', $result['en']['href']);
    }

    /**
     * Korrespondierende Seiten in derselben Domain bleiben relativ.
     */
    public function testCorrespondingSameDomainPagesStayRelative(): void
    {
        $rootPages = [
            1 => ['id' => 1, 'type' => 'root', 'language' => 'de', 'dns' => 'example.com', 'published' => 1, 'sorting' => 10, 'alias' => 'de-root', 'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 0, 'rootUseSSL' => true, 'rootId' => 1],
            2 => ['id' => 2, 'type' => 'root', 'language' => 'en', 'dns' => 'example.com', 'published' => 1, 'sorting' => 20, 'alias' => 'en-root', 'fallback' => 0, 'ls_cnc_languageSelector_languageGroup' => 0, 'rootUseSSL' => true, 'rootId' => 2],
        ];

        $currentPage = (object) [
            'id' => 10, 'rootId' => 1, 'language' => 'de', 'type' => 'regular',
            'alias' => 'produkte', 'domain' => 'example.com', 'ls_cnc_languageSelector_correspondingMainLanguagePage' => 0,
        ];

        $pages = [
            10 => ['id' => 10, 'rootId' => 1, 'language' => 'de', 'type' => 'regular', 'alias' => 'produkte', 'domain' => 'example.com', 'published' => 1, 'ls_cnc_languageSelector_correspondingMainLanguagePage' => 0],
            20 => ['id' => 20, 'rootId' => 2, 'language' => 'en', 'type' => 'regular', 'alias' => 'products', 'domain' => 'example.com', 'published' => 1, 'ls_cnc_languageSelector_correspondingMainLanguagePage' => 10],
        ];

        $this->setupDatabaseForDomainEquality($rootPages, $currentPage, 'example.com', $pages);
        $this->setupPageModels($rootPages, $currentPage, $pages);

        $GLOBALS['objPage'] = $currentPage;

        $result = $this->controller->getCorrespondingLanguagesForCurrentRootPage();

        $this->assertSame('/products', $result['en']['href']);
    }

    /**
     * `auto_item` nutzt bei Domainwechsel die Elternseite als absolute URL.
     */
    public function testAutoItemCrossDomainUsesAbsoluteParentUrl(): void
    {
        $rootPages = [
            1 => ['id' => 1, 'type' => 'root', 'language' => 'de', 'dns' => 'shop.de', 'published' => 1, 'sorting' => 10, 'alias' => 'de-root', 'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 0, 'rootUseSSL' => true, 'rootId' => 1],
            2 => ['id' => 2, 'type' => 'root', 'language' => 'en', 'dns' => 'shop.com', 'published' => 1, 'sorting' => 20, 'alias' => 'en-root', 'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 1, 'rootUseSSL' => true, 'rootId' => 2],
        ];

        $currentPage = (object) [
            'id' => 10, 'rootId' => 1, 'language' => 'de', 'type' => 'regular',
            'alias' => 'produkte', 'domain' => 'shop.de', 'pid' => 110, 'ls_cnc_languageSelector_correspondingMainLanguagePage' => 0,
        ];

        $pages = [
            10 => ['id' => 10, 'rootId' => 1, 'language' => 'de', 'type' => 'regular', 'alias' => 'produkte', 'domain' => 'shop.de', 'published' => 1, 'pid' => 110, 'ls_cnc_languageSelector_correspondingMainLanguagePage' => 0],
            20 => ['id' => 20, 'rootId' => 2, 'language' => 'en', 'type' => 'regular', 'alias' => 'products', 'domain' => 'shop.com', 'published' => 1, 'pid' => 120, 'ls_cnc_languageSelector_correspondingMainLanguagePage' => 10],
            110 => ['id' => 110, 'rootId' => 1, 'language' => 'de', 'type' => 'regular', 'alias' => 'katalog', 'domain' => 'shop.de', 'published' => 1, 'ls_cnc_languageSelector_correspondingMainLanguagePage' => 0],
            120 => ['id' => 120, 'rootId' => 2, 'language' => 'en', 'type' => 'regular', 'alias' => 'catalog', 'domain' => 'shop.com', 'published' => 1, 'ls_cnc_languageSelector_correspondingMainLanguagePage' => 0],
        ];

        $_GET['auto_item'] = 'produkt-a';

        $this->setupDatabaseForLanguageGroup($rootPages, $currentPage, $pages);
        $this->setupPageModels($rootPages, $currentPage, $pages);

        $GLOBALS['objPage'] = $currentPage;

        $result = $this->controller->getCorrespondingLanguagesForCurrentRootPage();

        $this->assertSame('https://shop.com/catalog', $result['en']['href']);
    }

    /**
     * Regionale Locale-Codes (`de_AT`) liefern BCP-47-Format (`de-AT`) als `languageCode`
     * und die qualifizierte Eigenbezeichnung als `languageTitle`.
     */
    public function testRegionalLocaleCodeReturnsBcp47FormatAndEndonym(): void
    {
        $rootPages = [
            1 => ['id' => 1, 'type' => 'root', 'language' => 'de', 'dns' => 'example.com', 'published' => 1, 'sorting' => 10, 'alias' => 'de-root', 'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 0, 'rootUseSSL' => true, 'rootId' => 1],
            2 => ['id' => 2, 'type' => 'root', 'language' => 'de_AT', 'dns' => 'example.com', 'published' => 1, 'sorting' => 20, 'alias' => 'at-root', 'fallback' => 0, 'ls_cnc_languageSelector_languageGroup' => 0, 'rootUseSSL' => true, 'rootId' => 2],
        ];

        $currentPage = (object) [
            'id' => 10, 'rootId' => 1, 'language' => 'de', 'type' => 'regular',
            'alias' => 'produkte', 'ls_cnc_languageSelector_correspondingMainLanguagePage' => 0,
        ];

        $this->setupDatabaseForDomainEquality($rootPages, $currentPage, 'example.com');
        $this->setupPageModels($rootPages, $currentPage);

        $GLOBALS['objPage'] = $currentPage;

        $result = $this->controller->getCorrespondingLanguagesForCurrentRootPage();

        $this->assertArrayHasKey('de', $result);
        $this->assertArrayHasKey('de_AT', $result);
        $this->assertCount(2, $result);

        $this->assertSame('de', $result['de']['languageCode']);
        $this->assertSame('de-AT', $result['de_AT']['languageCode']);

        $this->assertSame('Deutsch', $result['de']['languageTitle']);
        $this->assertSame('Deutsch (Österreich)', $result['de_AT']['languageTitle']);
    }

    /**
     * Mehrere regionale Locale-Codes in einer Language Group liefern
     * jeweils das korrekte Endonym und BCP-47-Format.
     */
    public function testMultipleRegionalLocaleCodesInLanguageGroup(): void
    {
        \Contao\System::$testLocales = array_merge(\Contao\System::$testLocales, [
            'en_US' => ['en_US' => 'English (United States)', 'de' => 'Deutsch', 'en' => 'Englisch'],
        ]);

        $rootPages = [
            1 => ['id' => 1, 'type' => 'root', 'language' => 'de', 'dns' => 'shop.de', 'published' => 1, 'sorting' => 10, 'alias' => 'de-root', 'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 0, 'rootUseSSL' => true, 'rootId' => 1],
            2 => ['id' => 2, 'type' => 'root', 'language' => 'de_AT', 'dns' => 'shop.at', 'published' => 1, 'sorting' => 20, 'alias' => 'at-root', 'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 1, 'rootUseSSL' => true, 'rootId' => 2],
            3 => ['id' => 3, 'type' => 'root', 'language' => 'en_US', 'dns' => 'shop.com', 'published' => 1, 'sorting' => 30, 'alias' => 'us-root', 'fallback' => 1, 'ls_cnc_languageSelector_languageGroup' => 1, 'rootUseSSL' => true, 'rootId' => 3],
        ];

        $currentPage = (object) [
            'id' => 10, 'rootId' => 1, 'language' => 'de', 'type' => 'regular',
            'alias' => 'produkte', 'ls_cnc_languageSelector_correspondingMainLanguagePage' => 0,
        ];

        $this->setupDatabaseForLanguageGroup($rootPages, $currentPage);
        $this->setupPageModels($rootPages, $currentPage);

        $GLOBALS['objPage'] = $currentPage;

        $result = $this->controller->getCorrespondingLanguagesForCurrentRootPage();

        $this->assertArrayHasKey('de', $result);
        $this->assertArrayHasKey('de_AT', $result);
        $this->assertArrayHasKey('en_US', $result);

        $this->assertSame('de', $result['de']['languageCode']);
        $this->assertSame('de-AT', $result['de_AT']['languageCode']);
        $this->assertSame('en-US', $result['en_US']['languageCode']);

        $this->assertSame('Deutsch', $result['de']['languageTitle']);
        $this->assertSame('Deutsch (Österreich)', $result['de_AT']['languageTitle']);
        $this->assertSame('English (United States)', $result['en_US']['languageTitle']);
    }

    private function setupDatabaseForLanguageGroup(array $rootPages, object $currentPage, array $pages = []): void
    {
        $currentRootId = $currentPage->rootId;
        $currentRoot = $rootPages[$currentRootId];
        $masterRootId = 0;

        if ($currentRoot['ls_cnc_languageSelector_languageGroup'] != 0) {
            $masterRootId = $currentRoot['ls_cnc_languageSelector_languageGroup'];
        } else {
            foreach ($rootPages as $rp) {
                if ($rp['ls_cnc_languageSelector_languageGroup'] == $currentRootId) {
                    $masterRootId = $currentRootId;
                    break;
                }
            }
        }

        $groupMembers = [];
        foreach ($rootPages as $rp) {
            if ($rp['id'] == $masterRootId || $rp['ls_cnc_languageSelector_languageGroup'] == $masterRootId) {
                $groupMembers[] = $rp;
            }
        }

        usort($groupMembers, fn($a, $b) => $a['sorting'] <=> $b['sorting']);

        $callIndex = 0;
        Database::$prepareCallback = function (string $query) use (&$callIndex, $rootPages, $currentRootId, $masterRootId, $groupMembers, $pages) {
            $callIndex++;
            return new class($query, $rootPages, $currentRootId, $masterRootId, $groupMembers, $pages) {
                private string $query;
                private array $rootPages;
                private int $currentRootId;
                private int $masterRootId;
                private array $groupMembers;
                private array $pages;

                public function __construct($query, $rootPages, $currentRootId, $masterRootId, $groupMembers, $pages) {
                    $this->query = $query;
                    $this->rootPages = $rootPages;
                    $this->currentRootId = $currentRootId;
                    $this->masterRootId = $masterRootId;
                    $this->groupMembers = $groupMembers;
                    $this->pages = $pages;
                }

                public function limit($l) { return $this; }

                public function execute(...$params) {
                    if (str_contains($this->query, 'WHERE `id` = ?') && !str_contains($this->query, 'ls_cnc_languageSelector_languageGroup')) {
                        $id = $params[0] ?? 0;
                        if (isset($this->rootPages[$id])) {
                            return new MockDbResult([$this->rootPages[$id]]);
                        }
                        return new MockDbResult([]);
                    }

                    if (str_contains($this->query, 'ls_cnc_languageSelector_languageGroup') && str_contains($this->query, 'LIMIT 1')) {
                        $id = $params[0] ?? 0;
                        foreach ($this->rootPages as $rp) {
                            if ($rp['ls_cnc_languageSelector_languageGroup'] == $id) {
                                return new MockDbResult([['id' => $rp['id']]]);
                            }
                        }
                        return new MockDbResult([]);
                    }

                    if (str_contains($this->query, '(`id` = ? OR `ls_cnc_languageSelector_languageGroup` = ?)')) {
                        return new MockDbResult($this->groupMembers);
                    }

                    if (str_contains($this->query, 'correspondingMainLanguagePage')) {
                        $mainLanguageId = $params[0] ?? 0;
                        $matchingPages = array_values(array_filter(
                            $this->pages,
                            static fn(array $page): bool => ((int) ($page['published'] ?? 1) === 1)
                                && (
                                    (int) ($page['ls_cnc_languageSelector_correspondingMainLanguagePage'] ?? 0) === (int) $mainLanguageId
                                    || (int) $page['id'] === (int) $mainLanguageId
                                )
                        ));

                        return new MockDbResult($matchingPages);
                    }

                    if (str_contains($this->query, 'ls_cnc_languageSelector_languageGroup FROM')) {
                        $id = $params[0] ?? 0;
                        if (isset($this->rootPages[$id])) {
                            return new MockDbResult([['ls_cnc_languageSelector_languageGroup' => $this->rootPages[$id]['ls_cnc_languageSelector_languageGroup']]]);
                        }
                        return new MockDbResult([]);
                    }

                    return new MockDbResult([]);
                }
            };
        };

        $db = Database::getInstance();
        Database::setTestInstance($db);
    }

    private function setupDatabaseForDomainEquality(array $rootPages, object $currentPage, string $domain, array $pages = []): void
    {
        $currentRootId = $currentPage->rootId;

        $sameDomainRoots = [];
        foreach ($rootPages as $rp) {
            if ($rp['dns'] === $domain) {
                $sameDomainRoots[] = $rp;
            }
        }
        usort($sameDomainRoots, fn($a, $b) => $a['sorting'] <=> $b['sorting']);

        Database::$prepareCallback = function (string $query) use ($rootPages, $currentRootId, $sameDomainRoots, $pages) {
            return new class($query, $rootPages, $currentRootId, $sameDomainRoots, $pages) {
                private string $query;
                private array $rootPages;
                private int $currentRootId;
                private array $sameDomainRoots;
                private array $pages;

                public function __construct($query, $rootPages, $currentRootId, $sameDomainRoots, $pages) {
                    $this->query = $query;
                    $this->rootPages = $rootPages;
                    $this->currentRootId = $currentRootId;
                    $this->sameDomainRoots = $sameDomainRoots;
                    $this->pages = $pages;
                }

                public function limit($l) { return $this; }

                public function execute(...$params) {
                    if (str_contains($this->query, 'WHERE `id` = ?') && !str_contains($this->query, 'ls_cnc_languageSelector_languageGroup')) {
                        $id = $params[0] ?? 0;
                        if (isset($this->rootPages[$id])) {
                            return new MockDbResult([$this->rootPages[$id]]);
                        }
                        return new MockDbResult([]);
                    }

                    if (str_contains($this->query, 'ls_cnc_languageSelector_languageGroup') && str_contains($this->query, 'LIMIT 1')) {
                        return new MockDbResult([]);
                    }

                    if (str_contains($this->query, "AND `dns` = ?")) {
                        return new MockDbResult($this->sameDomainRoots);
                    }

                    if (str_contains($this->query, 'correspondingMainLanguagePage')) {
                        $mainLanguageId = $params[0] ?? 0;
                        $matchingPages = array_values(array_filter(
                            $this->pages,
                            static fn(array $page): bool => ((int) ($page['published'] ?? 1) === 1)
                                && (
                                    (int) ($page['ls_cnc_languageSelector_correspondingMainLanguagePage'] ?? 0) === (int) $mainLanguageId
                                    || (int) $page['id'] === (int) $mainLanguageId
                                )
                        ));

                        return new MockDbResult($matchingPages);
                    }

                    return new MockDbResult([]);
                }
            };
        };

        $db = Database::getInstance();
        Database::setTestInstance($db);
    }

    private function setupPageModels(array $rootPages, object $currentPage, array $pages = []): void
    {
        PageModel::$testCallback = function ($id) use ($rootPages, $currentPage, $pages) {
            if ($id == $currentPage->id) {
                return $currentPage;
            }
            if (isset($pages[$id])) {
                return (object) $pages[$id];
            }
            if (isset($rootPages[$id])) {
                return (object) $rootPages[$id];
            }
            return (object) ['id' => $id, 'rootId' => 0, 'language' => '', 'domain' => '', 'type' => 'regular'];
        };

        PageModel::$findByIdCallback = function ($id) use ($rootPages, $pages) {
            if (isset($pages[$id])) {
                return $this->buildPageCollection($pages[$id]);
            }

            if (isset($rootPages[$id])) {
                return $this->buildPageCollection($rootPages[$id]);
            }

            return null;
        };

        PageModel::$findByAliasCallback = function ($alias) use ($rootPages, $currentPage, $pages) {
            if ($currentPage->alias === $alias) {
                $currentPageData = (array) $currentPage;

                if (!isset($currentPageData['domain']) && isset($rootPages[$currentPage->rootId]['dns'])) {
                    $currentPageData['domain'] = $rootPages[$currentPage->rootId]['dns'];
                }

                return $this->buildPageCollection($currentPageData);
            }

            foreach ($pages as $page) {
                if (($page['alias'] ?? null) === $alias) {
                    return $this->buildPageCollection($page);
                }
            }

            foreach ($rootPages as $rootPage) {
                if (($rootPage['alias'] ?? null) === $alias) {
                    return $this->buildPageCollection($rootPage);
                }
            }

            return null;
        };
    }

    private function buildPageCollection(array $pageData): object
    {
        return new class($pageData) {
            private array $pageData;

            public function __construct(array $pageData)
            {
                $this->pageData = $pageData;
            }

            public function current(): object
            {
                $pageData = $this->pageData;

                return new class($pageData) {
                    public string $type;
                    private array $pageData;

                    public function __construct(array $pageData)
                    {
                        $this->pageData = $pageData;
                        $this->type = (string) ($pageData['type'] ?? 'regular');
                    }

                    public function getFrontendUrl(string $suffix = ''): string
                    {
                        return '/' . $this->pageData['alias'] . $suffix;
                    }

                    public function getAbsoluteUrl(string $suffix = ''): string
                    {
                        $domain = $this->pageData['dns'] ?? $this->pageData['domain'] ?? 'example.com';

                        return 'https://' . $domain . '/' . $this->pageData['alias'] . $suffix;
                    }
                };
            }
        };
    }
}

class MockDbResult
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
