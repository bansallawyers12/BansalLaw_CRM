@foreach ($lists as $list)
@php
	$isStickyReopen = ($list->notification_type ?? '') === \App\Services\MatterReopenNotificationService::TYPE_REQUEST
		&& (int) ($list->receiver_status ?? 0) === 0
		&& app(\App\Services\MatterReopenNotificationService::class)->isStickyPending($list);
	$isUnread = $isStickyReopen || (int) ($list->receiver_status ?? 0) === 0;
	$rowClass = 'crm-notif-item';
	if ($isStickyReopen) {
		$rowClass .= ' crm-notif-item--reopen crm-notif-item--unread';
	} elseif ($isUnread) {
		$rowClass .= ' crm-notif-item--unread';
	} else {
		$rowClass .= ' crm-notif-item--read';
	}
	$href = ($list->url ?? '#')
		. (str_contains((string) ($list->url ?? ''), '?') ? '&' : '?')
		. ($isStickyReopen ? 'show_reopen=1&' : '')
		. 't=' . $list->id;
@endphp
<a href="{{ $href }}"
	id="id_{{ @$list->id }}"
	data-notification-id="{{ $list->id }}"
	class="{{ $rowClass }}">
	<span class="crm-notif-icon" aria-hidden="true">
		@if($isStickyReopen)
			<i class="fa-solid fa-triangle-exclamation"></i>
		@elseif($isUnread)
			<i class="fa-solid fa-bell"></i>
		@else
			<i class="fa-regular fa-bell"></i>
		@endif
	</span>
	<span class="crm-notif-body">
		<span class="crm-notif-message">
			@if($isStickyReopen)
				<span class="crm-reopen-action-badge">Action required</span>
			@endif
			{{ $list->message }}
		</span>
		<span class="crm-notif-meta">
			<time datetime="{{ \Illuminate\Support\Carbon::parse($list->created_at)->toIso8601String() }}">
				{{ date('d M Y · h:i A', strtotime($list->created_at)) }}
			</time>
		</span>
	</span>
	<span class="crm-notif-chevron" aria-hidden="true">
		<i class="fa-solid fa-chevron-right"></i>
	</span>
</a>
@endforeach
