<?php

namespace Tests\Unit;

use App\Services\CrmDurableStorage;
use Tests\TestCase;

class CrmDurableStorageTest extends TestCase
{
    public function test_normalize_strips_leading_slashes_and_backslashes(): void
    {
        $storage = new CrmDurableStorage;

        $this->assertSame('legal_forms/1/a.pdf', $storage->normalize('\\legal_forms/1/a.pdf'));
        $this->assertSame('note_attachments/2/3/x.png', $storage->normalize('/note_attachments/2/3/x.png'));
    }

    public function test_local_myfile_marker_round_trip(): void
    {
        $key = 'CLIENT1/matter/file.pdf';
        $myfile = CrmDurableStorage::localMyfile($key);

        $this->assertTrue(CrmDurableStorage::isLocalMyfile($myfile));
        $this->assertSame($key, CrmDurableStorage::parseLocalMyfileKey($myfile));
    }
}
