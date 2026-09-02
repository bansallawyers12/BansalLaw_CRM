@foreach ($lists as $list)
    @include('AdminConsole.features.matter.partials.row', [
        'list' => $list,
        'hasStreamColumn' => $hasStreamColumn ?? \Illuminate\Support\Facades\Schema::hasColumn('matters', 'stream'),
    ])
@endforeach
