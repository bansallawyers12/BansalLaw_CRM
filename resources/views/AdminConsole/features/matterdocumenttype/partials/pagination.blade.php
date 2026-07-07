<div id="mdt-list-pagination">
    @if(($totalData ?? 0) > 0)
        {!! $lists->appends(\Request::except('page'))->render() !!}
    @endif
</div>
