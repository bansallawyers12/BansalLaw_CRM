<?php

namespace Tests\Unit;

use App\Support\GlobalSearchPhoneMatcher;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GlobalSearchPhoneMatcherTest extends TestCase
{
    #[Test]
    public function it_builds_variants_for_e164_australian_numbers(): void
    {
        $variants = GlobalSearchPhoneMatcher::searchDigitVariants('+611234567890');

        $this->assertContains('611234567890', $variants);
        $this->assertContains('1234567890', $variants);
    }

    #[Test]
    public function it_builds_variants_for_local_australian_mobile(): void
    {
        $variants = GlobalSearchPhoneMatcher::searchDigitVariants('0412 345 678');

        $this->assertContains('0412345678', $variants);
        $this->assertContains('61412345678', $variants);
        $this->assertContains('412345678', $variants);
    }

    #[Test]
    public function it_ignores_short_non_phone_queries(): void
    {
        $this->assertSame([], GlobalSearchPhoneMatcher::searchDigitVariants('Sonu'));
        $this->assertSame([], GlobalSearchPhoneMatcher::searchDigitVariants('12345'));
    }
}
