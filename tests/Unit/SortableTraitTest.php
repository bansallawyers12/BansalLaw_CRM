<?php

namespace Tests\Unit;

use App\Helpers\SortableHelper;
use App\Models\Admin;
use Illuminate\Http\Request;
use Tests\TestCase;

class SortableTraitTest extends TestCase
{
    public function test_sortable_helper_toggles_to_descending_when_ascending(): void
    {
        $this->app->instance('request', Request::create('/clients', 'GET', ['sort' => 'first_name']));

        $html = SortableHelper::linkWithIcon('first_name', 'Name');

        $this->assertStringContainsString('sort=-first_name', $html);
        $this->assertStringContainsString('fa-sort-up', $html);
    }

    public function test_sortable_helper_toggles_to_ascending_when_descending(): void
    {
        $this->app->instance('request', Request::create('/clients', 'GET', ['sort' => '-first_name']));

        $html = SortableHelper::linkWithIcon('first_name', 'Name');

        $this->assertStringContainsString('sort=first_name', $html);
        $this->assertStringContainsString('fa-sort-down', $html);
        $this->assertStringNotContainsString('sort=-first_name', html_entity_decode($html));
    }

    public function test_scope_sortable_applies_request_sort_via_spatie(): void
    {
        $this->app->instance('request', Request::create('/clients', 'GET', ['sort' => '-first_name']));

        $sql = Admin::query()->sortable(['id' => 'desc'])->toSql();

        $this->assertMatchesRegularExpression('/order by ["`]?first_name["`]? desc/i', $sql);
    }

    public function test_scope_sortable_uses_default_when_request_has_no_sort(): void
    {
        $this->app->instance('request', Request::create('/clients', 'GET'));

        $sql = Admin::query()->sortable(['id' => 'desc'])->toSql();

        $this->assertMatchesRegularExpression('/order by ["`]?id["`]? desc/i', $sql);
    }

    public function test_scope_sortable_ignores_invalid_sort_and_uses_default(): void
    {
        $this->app->instance('request', Request::create('/clients', 'GET', ['sort' => 'not_a_column']));

        $sql = Admin::query()->sortable(['id' => 'desc'])->toSql();

        $this->assertMatchesRegularExpression('/order by ["`]?id["`]? desc/i', $sql);
        $this->assertDoesNotMatchRegularExpression('/not_a_column/i', $sql);
    }
}
