<?php

declare(strict_types=1);

namespace LeadingSystems\LanguageSelectorBundle\Tests\Unit;

use LeadingSystems\LanguageSelector\LsController;
use PHPUnit\Framework\TestCase;

class AbsoluteUrlTest extends TestCase
{
    private LsController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new LsController();
    }

    public function testBuildAbsoluteUrlWithHttps(): void
    {
        $rootPage = (object) ['dns' => 'example-shop.com', 'rootUseSSL' => true];
        $result = $this->controller->buildAbsoluteUrl($rootPage, '/products/widget');

        $this->assertSame('https://example-shop.com/products/widget', $result);
    }

    public function testBuildAbsoluteUrlWithHttp(): void
    {
        $rootPage = (object) ['dns' => 'example-shop.com', 'rootUseSSL' => false];
        $result = $this->controller->buildAbsoluteUrl($rootPage, '/contact');

        $this->assertSame('http://example-shop.com/contact', $result);
    }

    public function testBuildAbsoluteUrlStripsLeadingSlash(): void
    {
        $rootPage = (object) ['dns' => 'shop.de', 'rootUseSSL' => true];
        $result = $this->controller->buildAbsoluteUrl($rootPage, '///double-slash');

        $this->assertSame('https://shop.de/double-slash', $result);
    }

    public function testBuildAbsoluteUrlWithEmptyPath(): void
    {
        $rootPage = (object) ['dns' => 'shop.de', 'rootUseSSL' => true];
        $result = $this->controller->buildAbsoluteUrl($rootPage, '');

        $this->assertSame('https://shop.de/', $result);
    }

    public function testBuildAbsoluteUrlWithQueryString(): void
    {
        $rootPage = (object) ['dns' => 'example.com', 'rootUseSSL' => true];
        $result = $this->controller->buildAbsoluteUrl($rootPage, '/page?foo=bar');

        $this->assertSame('https://example.com/page?foo=bar', $result);
    }

    public function testBuildAbsoluteUrlWithPathSegments(): void
    {
        $rootPage = (object) ['dns' => 'example.com', 'rootUseSSL' => true];
        $result = $this->controller->buildAbsoluteUrl($rootPage, 'products/category/items');

        $this->assertSame('https://example.com/products/category/items', $result);
    }

    public function testRelativeUrlReturnedForSameDomain(): void
    {
        $rootPage = (object) ['dns' => 'same.com', 'rootUseSSL' => true];
        $relativePath = '/products';
        $currentDomain = 'same.com';

        if ($rootPage->dns === $currentDomain) {
            $result = $relativePath;
        } else {
            $result = $this->controller->buildAbsoluteUrl($rootPage, $relativePath);
        }

        $this->assertSame('/products', $result);
    }

    public function testAbsoluteUrlReturnedForDifferentDomain(): void
    {
        $rootPage = (object) ['dns' => 'other.com', 'rootUseSSL' => true];
        $relativePath = '/products';
        $currentDomain = 'myshop.de';

        if ($rootPage->dns === $currentDomain) {
            $result = $relativePath;
        } else {
            $result = $this->controller->buildAbsoluteUrl($rootPage, $relativePath);
        }

        $this->assertSame('https://other.com/products', $result);
    }
}
