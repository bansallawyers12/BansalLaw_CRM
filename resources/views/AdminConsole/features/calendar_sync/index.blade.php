@extends('layouts.crm_client_detail')
@section('title', 'Calendar Sync')

@section('content')

<div class="main-content adminconsole-features adminconsole-calendar-sync-index">
	<section class="section">
		<div class="section-body">
			<div class="server-error">
				@include('../Elements/flash-message')
			</div>
			<div class="row">
				<div class="col-3 col-md-3 col-lg-3">
					@include('../Elements/CRM/setting')
				</div>
				<div class="col-9 col-md-9 col-lg-9">
					@if(!empty($canControl))
					@php
						$masterOn = !empty($master['enabled']);
						$anyConnected = $staffAccounts->contains(fn ($a) => $a->isConnected());
					@endphp

					<div class="card mb-3 border {{ $masterOn ? 'border-success' : 'border-danger' }}">
						<div class="card-body py-3">
							<div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
								<div>
									<h5 class="mb-1">
										<i class="fa-solid fa-toggle-{{ $masterOn ? 'on text-success' : 'off text-danger' }}"></i>
										Zoho calendar sync master switch
									</h5>
									<p class="mb-0 text-muted small">
										When ON, CRM push/pull runs for each staff who has <strong>credentials + Connect + Sync ON</strong>
										(same idea as inbox auto-sync per mailbox).
									</p>
								</div>
								<div class="d-flex align-items-center gap-2 flex-wrap">
									<span class="badge {{ $masterOn ? 'bg-success' : 'bg-danger' }}">{{ $masterOn ? 'ON' : 'OFF' }}</span>
									@if($masterOn)
									<form method="POST" action="{{ route('adminconsole.features.calendarsync.master') }}" onsubmit="return confirm('Turn OFF calendar sync for everyone?');">
										@csrf
										<input type="hidden" name="enabled" value="0">
										<button type="submit" class="btn btn-outline-danger btn-sm">Turn off</button>
									</form>
									@else
									<form method="POST" action="{{ route('adminconsole.features.calendarsync.master') }}">
										@csrf
										<input type="hidden" name="enabled" value="1">
										<button type="submit" class="btn btn-success btn-sm">Turn on</button>
									</form>
									@endif
									<form method="POST" action="{{ route('adminconsole.features.calendarsync.sync-now') }}">
										@csrf
										<button type="submit" class="btn btn-primary btn-sm" @disabled(!$masterOn || !$anyConnected)>
											<i class="fa-solid fa-rotate"></i> Sync from Zoho now
										</button>
									</form>
								</div>
							</div>
						</div>
					</div>

					@if(empty($oauthConfigured))
					<div class="alert alert-warning">
						<strong>Org API app required once (like Zoho IMAP host is shared):</strong>
						set <code>ZOHO_CALENDAR_CLIENT_ID</code> and <code>ZOHO_CALENDAR_CLIENT_SECRET</code> in local <code>.env</code>.
						Redirect URI: <code>{{ config('zoho_calendar.redirect_uri') }}</code>
						<br>Each staff then gets their own Connect (tokens), not a shared password field.
					</div>
					@endif

					{{-- Staff credentials (parallel to Email accounts list) --}}
					<div class="card mb-3">
						<div class="card-header d-flex justify-content-between align-items-center">
							<h4 class="mb-0">Staff calendar credentials</h4>
							<span class="badge bg-secondary">{{ $staffAccounts->count() }} account(s)</span>
						</div>
						<div class="card-body">
							<p class="small text-muted">
								Like <strong>Admin Console → Email</strong>: add a row per staff, turn <em>Sync</em> on, then
								<strong>Connect Zoho</strong> while signing in as that person’s Zoho Mail / Outlook-via-Zoho account.
								Their events push/pull only on their calendar (unless marked org default).
							</p>

							{{-- Add form --}}
							<form method="POST" action="{{ route('adminconsole.features.calendarsync.staff-credentials.store') }}" class="border rounded p-3 mb-4 bg-light">
								@csrf
								<h6 class="mb-3">Add staff calendar account</h6>
								<div class="row g-2 align-items-end">
									<div class="col-md-4">
										<label class="form-label">Staff <span class="text-danger">*</span></label>
										<select name="staff_id" class="form-control" required>
											<option value="">Select staff…</option>
											@foreach($staffWithoutAccount as $s)
												<option value="{{ $s->id }}">
													{{ trim($s->first_name . ' ' . $s->last_name) }}
													@if($s->email) ({{ $s->email }}) @endif
												</option>
											@endforeach
											@if($staffWithoutAccount->isEmpty())
												<option value="" disabled>All active staff already have a row</option>
											@endif
										</select>
									</div>
									<div class="col-md-3">
										<label class="form-label">Zoho email</label>
										<input type="email" name="zoho_email" class="form-control" placeholder="optional (defaults to staff email)">
									</div>
									<div class="col-md-2">
										<label class="form-label">Display name</label>
										<input type="text" name="display_name" class="form-control" placeholder="optional">
									</div>
									<div class="col-md-2">
										<label class="form-label d-block">Options</label>
										<label class="me-2"><input type="checkbox" name="sync_enabled" value="1" checked> Sync ON</label>
										<label><input type="checkbox" name="is_org_default" value="1"> Org default</label>
									</div>
									<div class="col-md-1">
										<button type="submit" class="btn btn-primary btn-sm w-100">Add</button>
									</div>
								</div>
							</form>

							@if($staffAccounts->isEmpty())
								<p class="mb-0 text-muted">No staff calendar credentials yet. Add staff above.</p>
							@else
							<div class="table-responsive common_table">
								<table class="table text_wrap align-middle">
									<thead>
										<tr>
											<th>Staff</th>
											<th>Zoho email</th>
											<th>Connected</th>
											<th>Calendar</th>
											<th>Sync</th>
											<th>Org default</th>
											<th>Last pull</th>
											<th>Actions</th>
										</tr>
									</thead>
									<tbody>
										@foreach($staffAccounts as $account)
										@php
											$staffName = trim(($account->staff->first_name ?? '') . ' ' . ($account->staff->last_name ?? '')) ?: ('#' . $account->staff_id);
											$connected = $account->isConnected();
											$cals = $calendarsByStaff[$account->staff_id] ?? [];
										@endphp
										<tr>
											<td>
												<strong>{{ $staffName }}</strong>
												@if($account->display_name)
													<br><span class="text-muted small">{{ $account->display_name }}</span>
												@endif
											</td>
											<td>{{ $account->zoho_email ?: ($account->staff->email ?? '—') }}</td>
											<td>
												@if($connected)
													<span class="text-success">Yes</span>
													@if($account->connection?->connected_at)
														<br><span class="text-muted small">{{ $account->connection->connected_at->timezone(config('app.timezone'))->format('d/m/Y') }}</span>
													@endif
												@else
													<span class="text-warning">Not connected</span>
												@endif
												@if($account->last_error)
													<br><span class="text-danger small">{{ \Illuminate\Support\Str::limit($account->last_error, 60) }}</span>
												@endif
											</td>
											<td style="min-width: 200px;">
												<form method="POST" action="{{ route('adminconsole.features.calendarsync.staff-credentials.update', $account->staff_id) }}" class="d-flex flex-column gap-1">
													@csrf
													@method('PUT')
													@if(!empty($cals))
														<select name="zoho_calendar_uid" class="form-control form-control-sm">
															@foreach($cals as $cal)
																<option value="{{ $cal['uid'] }}" @selected(($account->zoho_calendar_uid ?: $account->connection?->default_calendar_uid) === $cal['uid'])>
																	{{ $cal['name'] }}
																</option>
															@endforeach
														</select>
													@else
														<input type="text" name="zoho_calendar_uid" class="form-control form-control-sm" value="{{ $account->zoho_calendar_uid }}" placeholder="Connect first, or paste UID">
													@endif
													<input type="hidden" name="zoho_email" value="{{ $account->zoho_email }}">
													<input type="hidden" name="display_name" value="{{ $account->display_name }}">
													@if($account->sync_enabled)
														<input type="hidden" name="sync_enabled" value="1">
													@endif
													@if($account->is_org_default)
														<input type="hidden" name="is_org_default" value="1">
													@endif
													<button type="submit" class="btn btn-outline-secondary btn-sm">Save calendar</button>
												</form>
											</td>
											<td>
												<form method="POST" action="{{ route('adminconsole.features.calendarsync.staff-credentials.update', $account->staff_id) }}">
													@csrf
													@method('PUT')
													<input type="hidden" name="zoho_calendar_uid" value="{{ $account->zoho_calendar_uid }}">
													<input type="hidden" name="zoho_email" value="{{ $account->zoho_email }}">
													@if($account->is_org_default)<input type="hidden" name="is_org_default" value="1">@endif
													@if($account->sync_enabled)
														<input type="hidden" name="sync_enabled" value="0">
														<button type="submit" class="btn btn-outline-danger btn-sm">Turn off</button>
													@else
														<input type="hidden" name="sync_enabled" value="1">
														<button type="submit" class="btn btn-success btn-sm">Turn on</button>
													@endif
												</form>
											</td>
											<td>
												@if($account->is_org_default)
													<span class="badge bg-info">Default</span>
												@else
													<form method="POST" action="{{ route('adminconsole.features.calendarsync.staff-credentials.update', $account->staff_id) }}">
														@csrf
														@method('PUT')
														<input type="hidden" name="zoho_calendar_uid" value="{{ $account->zoho_calendar_uid }}">
														<input type="hidden" name="zoho_email" value="{{ $account->zoho_email }}">
														@if($account->sync_enabled)<input type="hidden" name="sync_enabled" value="1">@endif
														<input type="hidden" name="is_org_default" value="1">
														<button type="submit" class="btn btn-link btn-sm p-0">Set default</button>
													</form>
												@endif
											</td>
											<td class="small">
												{{ $account->last_synced_at?->timezone(config('app.timezone'))->format('d/m/Y h:i a') ?: '—' }}
											</td>
											<td class="text-nowrap">
												<a href="{{ route('adminconsole.features.calendarsync.connect', ['staff_id' => $account->staff_id]) }}" class="btn btn-primary btn-sm mb-1"
												   onclick="return confirm('You will be sent to Zoho. Sign in as {{ addslashes($account->zoho_email ?: $staffName) }}\'s Zoho account so their calendar tokens are stored for this staff member.');">
													{{ $connected ? 'Reconnect' : 'Connect Zoho' }}
												</a>
												@if($connected)
												<form method="POST" action="{{ route('adminconsole.features.calendarsync.staff-credentials.disconnect', $account->staff_id) }}" class="d-inline" onsubmit="return confirm('Remove Zoho tokens for this staff?');">
													@csrf
													<button type="submit" class="btn btn-outline-warning btn-sm mb-1">Disconnect</button>
												</form>
												@endif
												<form method="POST" action="{{ route('adminconsole.features.calendarsync.staff-credentials.delete', $account->staff_id) }}" class="d-inline" onsubmit="return confirm('Delete credential row and tokens for this staff?');">
													@csrf
													@method('DELETE')
													<button type="submit" class="btn btn-outline-danger btn-sm mb-1">Remove</button>
												</form>
											</td>
										</tr>
										@endforeach
									</tbody>
								</table>
							</div>
							@endif
						</div>
					</div>

					{{-- Unlinked queue --}}
					<div class="card mb-3 border border-warning">
						<div class="card-header">
							<h4 class="mb-0">
								Unlinked calendar queue
								@if(($unlinkedOpenCount ?? 0) > 0)
									<span class="badge bg-danger">{{ $unlinkedOpenCount }}</span>
								@endif
							</h4>
						</div>
						<div class="card-body small">
							@if($unlinkedEvents->isEmpty())
								<p class="mb-0 text-success">No open unlinked events.</p>
							@else
							<div class="table-responsive common_table">
								<table class="table text_wrap align-middle">
									<thead>
										<tr>
											<th>When</th>
											<th>Title</th>
											<th>File / matter</th>
											<th>Assign</th>
											<th></th>
										</tr>
									</thead>
									<tbody>
										@foreach($unlinkedEvents as $row)
										<tr>
											<td class="text-nowrap">{{ $row->starts_at?->timezone(config('app.timezone'))->format('d/m/Y h:i a') ?: '—' }}</td>
											<td>{{ $row->title ?: '—' }}</td>
											<td>{{ $row->parsed_file_ref ?: '—' }}@if($row->parsed_matter_ref) / {{ $row->parsed_matter_ref }}@endif</td>
											<td style="min-width: 200px;">
												<form method="POST" action="{{ route('adminconsole.features.calendarsync.unlinked.assign') }}" class="row g-1">
													@csrf
													<input type="hidden" name="unlinked_id" value="{{ $row->id }}">
													<div class="col-7">
														<input type="number" name="client_id" class="form-control form-control-sm" placeholder="Client id" value="{{ $row->parsed_file_ref && ctype_digit((string)$row->parsed_file_ref) ? $row->parsed_file_ref : '' }}" required>
													</div>
													<div class="col-5">
														<button type="submit" class="btn btn-success btn-sm w-100">Assign</button>
													</div>
												</form>
											</td>
											<td>
												<form method="POST" action="{{ route('adminconsole.features.calendarsync.unlinked.dismiss') }}" onsubmit="return confirm('Dismiss?');">
													@csrf
													<input type="hidden" name="unlinked_id" value="{{ $row->id }}">
													<button type="submit" class="btn btn-outline-secondary btn-sm">Dismiss</button>
												</form>
											</td>
										</tr>
										@endforeach
									</tbody>
								</table>
							</div>
							@endif
						</div>
					</div>
					@else
					<div class="alert alert-info">Only Super Admin can manage staff calendar credentials.</div>
					@endif

					@if(!empty($canControl) && ($recentLinks ?? collect())->isNotEmpty())
					<div class="card mb-3">
						<div class="card-header"><h4>Recent event links</h4></div>
						<div class="card-body">
							<div class="table-responsive common_table">
								<table class="table text_wrap">
									<thead>
										<tr>
											<th>Local</th>
											<th>Staff</th>
											<th>Status</th>
											<th>Direction</th>
											<th>Updated</th>
										</tr>
									</thead>
									<tbody>
										@foreach($recentLinks as $link)
										<tr>
											<td>{{ $link->local_type }} #{{ $link->local_id }}</td>
											<td>{{ $link->staff_id ?: '—' }}</td>
											<td>{{ $link->sync_status }}</td>
											<td>{{ $link->direction }}</td>
											<td>{{ $link->updated_at?->timezone(config('app.timezone'))->format('d/m/Y h:i a') }}</td>
										</tr>
										@endforeach
									</tbody>
								</table>
							</div>
						</div>
					</div>
					@endif
				</div>
			</div>
		</div>
	</section>
</div>

@endsection
