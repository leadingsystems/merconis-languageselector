<?php

declare(strict_types=1);

namespace LeadingSystems\LanguageSelectorBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase
{
    public function testPhpUnitIsWorking(): void
    {
        $this->assertTrue(true);
    }

    public function testAutoloadingWorks(): void
    {
        $this->assertTrue(
            class_exists(\LeadingSystems\LanguageSelectorBundle\LeadingSystemsLanguageSelectorBundle::class)
        );
    }

    public function testGlobalsAreInitialized(): void
    {
        $this->assertIsArray($GLOBALS['TL_LANG']);
        $this->assertIsArray($GLOBALS['TL_DCA']);
    }
}
