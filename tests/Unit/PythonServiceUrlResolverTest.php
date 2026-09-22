<?php

namespace Tests\Unit;

use App\Services\PythonServiceUrlResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PythonServiceUrlResolverTest extends TestCase
{
    #[Test]
    public function swap_port_helper_is_covered_via_resolve_candidates(): void
    {
        // Force a configured URL that will fail health; resolver must still return a URL.
        config([
            'services.python.url' => 'http://127.0.0.1:59999',
            'services.python.fallback_url' => '',
        ]);

        $resolver = new PythonServiceUrlResolver();
        $status = $resolver->resolve(true);

        $this->assertSame('http://127.0.0.1:59999', $status['url']);
        $this->assertFalse($status['available']);
        $this->assertContains('http://127.0.0.1:59999', $status['probed']);
    }
}
