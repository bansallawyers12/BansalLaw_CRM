<div id="staff-list-pagination">
    @if(isset($lists) && method_exists($lists, 'links'))
        {!! $lists->links() !!}
    @endif
</div>
