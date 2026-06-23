<?php

declare(strict_types=1);

namespace LeadingSystems\LanguageSelectorBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;

class LanguageKeyHtmlFreeTest extends TestCase
{
    /**
     * @dataProvider languageFileProvider
     */
    public function testInfoTextCurrentLanguageContainsNoHtml(string $languageDir): void
    {
        $file = dirname(__DIR__, 2)
            . '/src/Resources/contao/languages/' . $languageDir . '/default.php';

        $this->assertFileExists($file);

        $GLOBALS['TL_LANG'] = [];
        include $file;

        $value = $GLOBALS['TL_LANG']['MSC']['infoTextCurrentLanguage'];

        $this->assertNotNull($value, 'infoTextCurrentLanguage must be defined');
        $this->assertSame(
            $value,
            strip_tags($value),
            sprintf(
                'Language key infoTextCurrentLanguage in "%s" must not contain HTML tags',
                $languageDir
            )
        );
    }

    public static function languageFileProvider(): array
    {
        return [
            'German' => ['de'],
            'English' => ['en'],
        ];
    }
}
