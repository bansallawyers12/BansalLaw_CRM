<div class="ov-table-panel">
	<div class="table-responsive ov-table-wrap">
		<table class="table ov-table">
			<thead>
				<tr>
					<th>ID</th>
					<th>Date</th>
					<th>Start</th>
					<th>Contact</th>
					<th>Type</th>
					<th>Purpose</th>
					<th>Assignee</th>
					<th>Wait</th>
					<th>Action</th>
				</tr>
			</thead>
			@if(@$totalData !== 0)
			<tbody class="tdata checindata">
				@foreach (@$lists as $list)
				<tr did="{{ @$list->id }}" id="id_{{ @$list->id }}" data-status="{{ (int) $list->status }}">
					<td>
						<a href="javascript:void(0)" class="opencheckindetail ov-id-link" data-checkin-id="{{ $list->id }}" role="button">#{{ $list->id }}</a>
					</td>
					<td>
						<span class="ov-date-day">{{ date('l', strtotime($list->created_at)) }}</span>
						<span class="ov-date-val">{{ date('d/m/Y', strtotime($list->created_at)) }}</span>
					</td>
					<td>
						@if($list->sesion_start != '')
							{{ date('h:i A', strtotime($list->sesion_start)) }}
						@else
							<span class="text-muted">—</span>
						@endif
					</td>
					<td>
						@php
							$isWalkIn = ($list->contact_type === 'Walk-in') || empty($list->client_id);
							$ovContact = $isWalkIn ? null : $list->resolveCrmContact();
							$ovName = $ovContact ? trim(($ovContact->first_name ?? '') . ' ' . ($ovContact->last_name ?? '')) : '';
						@endphp
						@if($isWalkIn)
							<span class="ov-contact-name text-muted">Walk-in</span>
							@if(!empty($list->walk_in_phone))<span class="ov-contact-sub">{{ $list->walk_in_phone }}</span>@endif
							@if(!empty($list->walk_in_email))<span class="ov-contact-sub">{{ $list->walk_in_email }}</span>@endif
						@elseif($ovContact)
							<a class="ov-contact-name" target="_blank" href="{{ URL::to('/clients/detail/'.base64_encode(convert_uuencode($ovContact->id))) }}">{{ \App\Models\CheckinLog::labelForCrmContact($ovContact) }}</a>
							@if($ovName !== '' && !empty($ovContact->email))
								<span class="ov-contact-sub">{{ $ovContact->email }}</span>
							@endif
						@else
							<span class="text-muted">—</span>
						@endif
					</td>
					<td>{{ $list->contact_type }}</td>
					<td>{{ $list->visit_purpose }}</td>
					<td>
						@php $admin = \App\Models\Staff::find($list->user_id); @endphp
						@if($admin)
							<a class="ov-contact-name" href="{{ route('adminconsole.staff.view', $admin->id) }}">{{ $admin->first_name }} {{ $admin->last_name }}</a>
							<span class="ov-contact-sub">{{ $admin->email }}</span>
						@else
							<span class="text-muted">Not assigned</span>
						@endif
					</td>
					<td id="count{{ $list->id }}" data-checkintime="{{ date('Y-m-d H:i:s', strtotime($list->created_at)) }}">
						@if($list->status == 0)
							<span class="ov-wait-timer" id="waitcount">00h:00m:00s</span>
						@elseif($list->status == 2)
							<span>{{ $list->wait_time }}</span>
						@elseif($list->status == 1)
							<span>{{ $list->wait_time ?? '—' }}</span>
						@else
							<span>—</span>
						@endif
					</td>
					<td class="ov-action-cell">
						@if($list->status == 0)
							@if($list->wait_type == 1)
								<a href="javascript:;" data-id="{{ $list->id }}" data-waitingtype="{{ $list->wait_type }}" class="btn btn-sm ov-btn-send attendsessionforclient">Pls Send</a>
							@else
								<a href="javascript:;" data-id="{{ $list->id }}" data-waitingtype="{{ $list->wait_type }}" class="btn btn-sm ov-btn-wait attendsessionforclient">Waiting</a>
							@endif
						@elseif($list->status == 2)
							<span class="ov-status-pill ov-status-pill--attending">Attending</span>
						@elseif($list->status == 1)
							<span class="ov-status-pill ov-status-pill--completed">Completed</span>
						@endif
						<input type="hidden" value="0-6h:0-24m:0-7s" id="lwaitcountdata{{ $list->id }}">
					</td>
				</tr>
				@endforeach
			</tbody>
			@else
			<tbody>
				<tr>
					<td colspan="9" class="ov-empty">No visits in this list</td>
				</tr>
			</tbody>
			@endif
		</table>
	</div>
	@if(@$totalData !== 0)
	<div class="ov-pagination">
		{!! $lists->appends(\Request::except('page'))->render() !!}
	</div>
	@endif
</div>
