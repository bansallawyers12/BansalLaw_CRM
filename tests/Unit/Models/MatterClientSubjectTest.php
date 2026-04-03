<?php

namespace Tests\Unit\Models;

use App\Models\Matter;
use Tests\TestCase;

class MatterClientSubjectTest extends TestCase
{
    public function test_general_matter_id_one_allowed_for_company_and_personal(): void
    {
        $this->assertTrue(Matter::allowedForClientIsCompany(1, true));
        $this->assertTrue(Matter::allowedForClientIsCompany(1, false));
    }

    public function test_non_positive_matter_ids_rejected(): void
    {
        $this->assertFalse(Matter::allowedForClientIsCompany(0, true));
        $this->assertFalse(Matter::allowedForClientIsCompany(-3, false));
    }

    public function test_missing_matter_row_rejected(): void
    {
        $this->assertFalse(Matter::allowedForClientIsCompany(2147483640, true));
        $this->assertFalse(Matter::allowedForClientIsCompany(2147483640, false));
    }
}
