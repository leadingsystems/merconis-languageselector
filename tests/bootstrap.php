<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$GLOBALS['TL_LANG'] = [];
$GLOBALS['TL_DCA'] = ['tl_page' => ['fields' => [], 'palettes' => [], 'config' => [], 'list' => []]];

if (!class_exists('Contao\Database', false)) {
    eval('namespace Contao; class Database {
        /** @var Database|null */
        private static ?Database $testInstance = null;
        /** @var callable|null */
        public static $prepareCallback = null;

        public static function setTestInstance(?Database $instance): void {
            self::$testInstance = $instance;
        }

        public static function getInstance(): Database {
            if (self::$testInstance) {
                return self::$testInstance;
            }
            return new self();
        }

        public function prepare(string $query) {
            if (self::$prepareCallback) {
                return (self::$prepareCallback)($query);
            }
            return new class { public function limit($l) { return $this; } public function execute(...$p) { return new class { public int $numRows = 0; public function next() { return false; } public function __get($n) { return null; } public function row() { return []; } }; } };
        }
    }');
}

if (!class_exists('Contao\Backend', false)) {
    eval('namespace Contao; class Backend {
        protected $Database;
        public function __construct() {}
        public function setTestDatabase($db): void { $this->Database = $db; }
    }');
}

if (!class_exists('Contao\Input', false)) {
    eval('namespace Contao; class Input {
        public static function get(string $key): ?string {
            return $_GET[$key] ?? null;
        }
    }');
}

if (!class_exists('Contao\Environment', false)) {
    eval('namespace Contao; class Environment {
        public static function get(string $key): string {
            return $_SERVER[$key] ?? "";
        }
    }');
}

if (!class_exists('Contao\System', false)) {
    eval('namespace Contao; class System {
        /** @var array<string, array<string, string>> */
        public static array $testLocales = [];

        public static function loadLanguageFile($name, $language = null, $blnNoCache = false): void {}
        public static function getContainer() {
            $locales = self::$testLocales;
            return new class($locales) {
                private array $locales;
                public function __construct(array $locales) { $this->locales = $locales; }
                public function get(string $id) {
                    $locales = $this->locales;
                    return new class($locales) {
                        private array $locales;
                        public function __construct(array $locales) { $this->locales = $locales; }
                        public function getLocales(?string $displayLocale = null): array {
                            if ($displayLocale !== null && isset($this->locales[$displayLocale])) {
                                return $this->locales[$displayLocale];
                            }
                            $merged = [];
                            foreach ($this->locales as $entries) { $merged = array_merge($merged, $entries); }
                            return $merged;
                        }
                    };
                }
            };
        }
        public static function importStatic(string $class) { return new $class(); }
    }');
}

if (!class_exists('Contao\PageModel', false)) {
    eval('namespace Contao; class PageModel {
        /** @var callable|null */
        public static $testCallback = null;
        /** @var callable|null */
        public static $findByIdCallback = null;
        /** @var callable|null */
        public static $findByAliasCallback = null;

        public static function findWithDetails($id) {
            if (self::$testCallback) {
                return (self::$testCallback)($id);
            }
            return null;
        }

        public static function findById($id) {
            if (self::$findByIdCallback) {
                return (self::$findByIdCallback)($id);
            }
            return null;
        }

        public static function findByAlias($alias) {
            if (self::$findByAliasCallback) {
                return (self::$findByAliasCallback)($alias);
            }
            return new class { public function current() { return new class { public function getFrontendUrl($s = "") { return "/page" . $s; } public string $type = "regular"; }; } };
        }
    }');
}

if (!class_exists('tl_page', false)) {
    class tl_page {
        public function addIcon($row, $label, $dc = null, $imageAttribute = '', $blnReturnImage = false) {
            return $label;
        }
    }
}

require_once dirname(__DIR__) . '/src/Resources/contao/dca/tl_page.php';
