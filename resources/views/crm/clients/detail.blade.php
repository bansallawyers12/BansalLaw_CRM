@extends('layouts.crm_client_detail')
@section('title', 'Client Detail')

@section('content')
@php \App\Support\EnsureDummyMatterStaff::ensure(); @endphp
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="stylesheet" href="{{ URL::asset('css/client-detail.css') }}">
<style>
.lead-actions-bar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 10px 18px; background: #f0f4ff; border-bottom: 1px solid #c7d7fa; }
.lead-actions-bar__history { color: #fff; }
</style>

<?php
use App\Http\Controllers\Controller;
?>
<div class="crm-container" data-client-id="{{ $fetchedData->id }}">
    <!-- Collapsed Toggle Button (shown when sidebar is collapsed) -->
    <button id="collapsed-toggle" class="collapsed-toggle-btn" title="Show Sidebar">
        ☰
    </button>
    
    <!-- Client Navigation Sidebar -->
    <aside class="client-navigation-sidebar" id="client-sidebar">
        @php
            $clientDetailBackTabSlugs = ['personaldetails', 'overview', 'activityfeed', 'clientaction', 'noteterm', 'personaldocuments', 'matterdocuments', 'documents', 'nominationdocuments', 'emails', 'legalforms', 'formgenerations', 'formgenerationsl', 'application', 'account', 'notuseddocuments', 'companydetails'];

            $cdnFn = trim((string) ($fetchedData->first_name ?? ''));
            $cdnLn = trim((string) ($fetchedData->last_name ?? ''));
            $cdnInitials = strtoupper(mb_substr($cdnFn, 0, 1) . mb_substr($cdnLn, 0, 1));
            if ($cdnInitials === '') {
                $cid = (string) ($fetchedData->client_id ?? '');
                $cdnInitials = strtoupper(mb_substr($cid !== '' ? $cid : 'C', 0, 2));
            }

            $cdnAssigneeName = null;
            if (! empty($fetchedData->user_id)) {
                $cdnStaffRow = ($assignableStaff ?? collect())->firstWhere('id', (int) $fetchedData->user_id)
                    ?? \App\Models\Staff::find($fetchedData->user_id);
                if ($cdnStaffRow) {
                    $cdnAssigneeName = trim(($cdnStaffRow->first_name ?? '') . ' ' . ($cdnStaffRow->last_name ?? ''));
                }
            }

            $cdnUpdatedHuman = null;
            if (! empty($fetchedData->updated_at)) {
                try {
                    $cdnUpdatedHuman = \Carbon\Carbon::parse($fetchedData->updated_at)->diffForHumans();
                } catch (\Throwable $e) {
                }
            }

            $cdnMatterRow = null;
            $cdnMatterRefLabel = null;
            if (! empty($id1) && ! in_array(strtolower((string) $id1), array_map('strtolower', $clientDetailBackTabSlugs), true)) {
                $cdnMatterRefLabel = (string) $id1;
                $cdnMatterRow = \App\Models\ClientMatter::where('client_id', $fetchedData->id)
                    ->where('client_unique_matter_no', $id1)
                    ->where('matter_status', 1)
                    ->with('matter')
                    ->first();
            } else {
                $cdnMatterRow = \App\Models\ClientMatter::where('client_id', $fetchedData->id)
                    ->where('matter_status', 1)
                    ->orderByDesc('id')
                    ->with('matter')
                    ->first();
                $cdnMatterRefLabel = $cdnMatterRow?->client_unique_matter_no;
            }
            $cdnMatterChipTitle = 'General Matter';
            if ($cdnMatterRow && (int) $cdnMatterRow->sel_matter_id !== 1 && $cdnMatterRow->matter && ! empty($cdnMatterRow->matter->title)) {
                $cdnMatterChipTitle = $cdnMatterRow->matter->title;
            }

            $cdnWorkflowStageLabel = null;
            if ($cdnMatterRefLabel) {
                $cdnWs = DB::table('client_matters')
                    ->leftJoin('workflow_stages', 'client_matters.workflow_stage_id', '=', 'workflow_stages.id')
                    ->select('workflow_stages.name')
                    ->where('client_matters.client_id', $fetchedData->id)
                    ->where('client_matters.client_unique_matter_no', $cdnMatterRefLabel)
                    ->first();
                $cdnWorkflowStageLabel = $cdnWs->name ?? null;
            }

            [$cdnHeroNormal, $cdnHeroRedIgnored] = \App\Support\ClientTagStorage::decode($fetchedData->tagname ?? '');
            $cdnHeroTagNames = collect($cdnHeroNormal)->values();
            $cdnHeroTagMore = 0;
            if ($cdnHeroTagNames->count() > 4) {
                $cdnHeroTagMore = $cdnHeroTagNames->count() - 4;
                $cdnHeroTagNames = $cdnHeroTagNames->take(4);
            }

            $cdnClientMatterKey = (string) $fetchedData->client_id;
            if ($cdnMatterRefLabel) {
                $cdnClientMatterKey .= ' / ' . $cdnMatterRefLabel;
            }
        @endphp

        <section class="cdn-client-hero" aria-label="Client summary">
            <div class="cdn-client-hero__inner">
                <div class="cdn-client-hero__identity">
                    <div class="cdn-client-hero__avatar" aria-hidden="true">{{ $cdnInitials }}</div>
                    <div class="cdn-client-hero__text">
                        <h1 class="cdn-client-hero__name">
                            {{ $cdnFn }} {{ $cdnLn }}
                            <a href="{{ route('clients.edit', base64_encode(convert_uuencode(@$fetchedData->id))) }}" class="cdn-client-hero__edit" title="Client Details Form"><i class="fas fa-id-card" aria-hidden="true"></i></a>
                        </h1>
                        <div class="cdn-client-hero__meta">
                            <span class="cdn-client-hero__meta-item">{{ $cdnClientMatterKey }}</span>
                            @if($cdnAssigneeName)
                                <span class="cdn-client-hero__meta-item">{{ $cdnAssigneeName }}</span>
                            @endif
                            @if($cdnUpdatedHuman)
                                <span class="cdn-client-hero__meta-item">Last update {{ $cdnUpdatedHuman }}</span>
                            @endif
                            @if($cdnWorkflowStageLabel)
                                <span class="cdn-client-hero__meta-item">Stage: {{ $cdnWorkflowStageLabel }}</span>
                            @endif
                        </div>
                        <div class="cdn-client-hero__matter-row">
                            @if($cdnMatterRefLabel)
                                <span class="cdn-client-hero__matter-chip">{{ $cdnMatterChipTitle }} ({{ $cdnMatterRefLabel }})</span>
                            @else
                                <span class="cdn-client-hero__matter-chip">No active matter</span>
                            @endif
                            <button type="button" class="btn cdn-client-hero__matter-btn" id="cdn-focus-matter-select" title="Change matter">Change Matter</button>
                        </div>
                        <div class="cdn-client-hero__tags" aria-label="Tags">
                            @foreach($cdnHeroTagNames as $tname)
                                <span class="cdn-client-hero__tag">{{ $tname }}</span>
                            @endforeach
                            @if($cdnHeroTagMore > 0)
                                <span class="cdn-client-hero__tag cdn-client-hero__tag--more">+{{ $cdnHeroTagMore }} more</span>
                            @endif
                            <span class="cdn-client-hero__tag-actions">
                                <button type="button" class="cdn-client-hero__tag-add cdn-client-hero__tag-add--red openredtagspopup" data-id="{{ $fetchedData->id }}" title="Add red tag (hidden by default on profile)" aria-label="Add red tag">+</button>
                                <button type="button" class="cdn-client-hero__tag-add cdn-client-hero__tag-add--blue opentagspopup" data-id="{{ $fetchedData->id }}" title="Add or edit tags" aria-label="Add or edit tags">+</button>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="cdn-client-hero__actions">
                    <button type="button" class="btn cdn-client-hero__action-btn create_note_d" datatype="note" title="Add a note">Add Notes</button>
                    <a href="javascript:;" class="btn cdn-client-hero__action-btn clientemail" data-id="{{ @$fetchedData->id }}" data-email="{{ @$fetchedData->email }}" data-name="{{ @$fetchedData->first_name }} {{ @$fetchedData->last_name }}" title="Compose Mail">Send Email</a>
                    <a href="javascript:;" class="btn cdn-client-hero__action-btn send-sms-btn" data-client-id="{{ @$fetchedData->id }}" data-client-name="{{ @$fetchedData->first_name }} {{ @$fetchedData->last_name }}" title="Send SMS">Send SMS</a>
                    <a href="javascript:;" class="btn cdn-client-hero__action-btn" data-bs-toggle="modal" data-bs-target="#create_appoint" title="Schedule appointment">Appointment</a>
                    <button type="button" class="btn cdn-client-hero__action-btn cdn-client-hero__action-btn--primary" id="cdn-open-update-stage" data-bs-toggle="modal" data-bs-target="#cdn-update-stage-modal" title="Update workflow stage">Update Stage</button>
                </div>
            </div>
        </section>

        {{-- Off-screen matter select for detail-main.js (sidebar chrome removed on demo). --}}
        <div class="client-detail-demo-hidden-matter">
            <div class="sidebar-matter-selection">
                <?php
                $assign_info_arr = \App\Models\Admin::select('type')->where('id',@$fetchedData->id)->first();
                ?>
                @if($assign_info_arr && $assign_info_arr->type)
                    <?php 
                    if($id1)
                    {
                        //if client_unique_matter_no is present in url
                        $matter_cnt = DB::table('client_matters')
                        ->select('client_matters.id')
                        ->where('client_matters.client_id',@$fetchedData->id)
                        ->where('client_matters.client_unique_matter_no',$id1)
                        ->where('client_matters.matter_status',1)
                        ->whereNotNull('client_matters.sel_matter_id')
                        ->count();  
                        if( $matter_cnt >0 )
                        {
                            // Fetch all matters, but we'll sort them in Blade to prioritize the URL matter
                            $matter_list_arr = DB::table('client_matters')
                            ->leftJoin('matters', 'client_matters.sel_matter_id', '=', 'matters.id')
                            ->select('client_matters.id','client_matters.client_unique_matter_no','matters.title','client_matters.sel_matter_id')
                            ->where('client_matters.client_id',@$fetchedData->id)
                            ->where('client_matters.matter_status',1)
                            ->get();
                            $clientmatter_info_arr = \App\Models\ClientMatter::select('id')->where('client_id',$fetchedData->id)->where('client_unique_matter_no',$id1)->first();
                            $latestClientMatterId = $clientmatter_info_arr ? $clientmatter_info_arr->id : null;

                            // Convert matter_list_arr to an array for sorting
                            $matter_list_arr = $matter_list_arr->toArray();
                            // Sort matters: URL matter ($id1) comes first, others follow
                            usort($matter_list_arr, function($a, $b) use ($id1) {
                                if ($a->client_unique_matter_no == $id1 && $b->client_unique_matter_no != $id1) {
                                    return -1; // $a (URL matter) comes first
                                } elseif ($a->client_unique_matter_no != $id1 && $b->client_unique_matter_no == $id1) {
                                    return 1; // $b (URL matter) comes first
                                }
                                return 0; // Maintain original order for other matters
                            });
                            ?>
                            <select name="matter_id" id="sel_matter_id_client_detail" class="form-control select2 visa-dropdown" data-valid="required">
                                <option value="">Select Matters</option>
                                @foreach($matter_list_arr as $matterlist)
                                    @php
                                        // If sel_matter_id is 1 or title is null, use "General Matter"
                                        $matterName = 'General Matter';
                                        if ($matterlist->sel_matter_id != 1 && !empty($matterlist->title)) {
                                            $matterName = $matterlist->title;
                                        }
                                    @endphp
                                    <option value="{{$matterlist->id}}" {{ $matterlist->id == $latestClientMatterId ? 'selected' : '' }} data-clientuniquematterno="{{@$matterlist->client_unique_matter_no}}" data-sel-matter-id="{{@$matterlist->sel_matter_id}}">{{$matterName}}({{@$matterlist->client_unique_matter_no}})</option>
                                @endforeach
                            </select>
                        <?php
                        }  
                        else 
                        {
                            $matter_cnt = DB::table('client_matters')
                            ->select('client_matters.id')
                            ->where('client_matters.client_id',@$fetchedData->id)
                            ->where('client_matters.matter_status',1)
                            ->whereNotNull('client_matters.sel_matter_id')
                            ->count();
                            if( $matter_cnt >0 )
                            {
                                $matter_list_arr = DB::table('client_matters')
                                ->leftJoin('matters', 'client_matters.sel_matter_id', '=', 'matters.id')
                                ->select('client_matters.id','client_matters.client_unique_matter_no','matters.title','client_matters.sel_matter_id')
                                ->where('client_matters.client_id',@$fetchedData->id)
                                ->where('client_matters.matter_status',1)
                                ->orderBy('client_matters.created_at', 'desc')
                                ->get();
                                $latestClientMatter = \App\Models\ClientMatter::where('client_id',$fetchedData->id)->where('matter_status',1)->latest()->first();
                                $latestClientMatterId = $latestClientMatter ? $latestClientMatter->id : null;
                                ?>
                                <select name="matter_id" id="sel_matter_id_client_detail" class="form-control select2 visa-dropdown" data-valid="required">
                                    <option value="">Select Matters</option>
                                    @foreach($matter_list_arr as $matterlist)
                                        @php
                                            // If sel_matter_id is 1 or title is null, use "General Matter"
                                            $matterName = 'General Matter';
                                            if ($matterlist->sel_matter_id != 1 && !empty($matterlist->title)) {
                                                $matterName = $matterlist->title;
                                            }
                                        @endphp
                                        <option value="{{$matterlist->id}}" {{ $matterlist->id == $latestClientMatterId ? 'selected' : '' }} data-clientuniquematterno="{{@$matterlist->client_unique_matter_no}}" data-sel-matter-id="{{@$matterlist->sel_matter_id}}">{{$matterName}}({{@$matterlist->client_unique_matter_no}})</option>
                                    @endforeach
                                </select>
                            <?php
                            }
                        } 
                    }
                    else
                    {
                        $matter_cnt = DB::table('client_matters')
                        ->select('client_matters.id')
                        ->where('client_matters.client_id',@$fetchedData->id)
                        ->where('client_matters.matter_status',1)
                        ->whereNotNull('client_matters.sel_matter_id')
                        ->count();
                        if( $matter_cnt >0 )
                        {
                            $matter_list_arr = DB::table('client_matters')
                            ->leftJoin('matters', 'client_matters.sel_matter_id', '=', 'matters.id')
                            ->select('client_matters.id','client_matters.client_unique_matter_no','matters.title','client_matters.sel_matter_id')
                            ->where('client_matters.client_id',@$fetchedData->id)
                            ->where('client_matters.matter_status',1)
                            ->orderBy('client_matters.created_at', 'desc')
                            ->get();
                            $latestClientMatter = \App\Models\ClientMatter::where('client_id',$fetchedData->id)->where('matter_status',1)->latest()->first();
                            $latestClientMatterId = $latestClientMatter ? $latestClientMatter->id : null;
                            ?>
                            <select name="matter_id" id="sel_matter_id_client_detail" class="form-control select2 visa-dropdown" data-valid="required">
                                <option value="">Select Matters</option>
                                @foreach($matter_list_arr as $matterlist)
                                    @php
                                        // If sel_matter_id is 1 or title is null, use "General Matter"
                                        $matterName = 'General Matter';
                                        if ($matterlist->sel_matter_id != 1 && !empty($matterlist->title)) {
                                            $matterName = $matterlist->title;
                                        }
                                    @endphp
                                    <option value="{{$matterlist->id}}" {{ $matterlist->id == $latestClientMatterId ? 'selected' : '' }} data-clientuniquematterno="{{@$matterlist->client_unique_matter_no}}" data-sel-matter-id="{{@$matterlist->sel_matter_id}}">{{$matterName}}({{@$matterlist->client_unique_matter_no}})</option>
                                @endforeach
                            </select>
                        <?php
                        }
                    }
                    ?>
                @endif
            </div>
        </div>

        {{-- Tab strip lives in the aside (cdn-topbar) so it is NEVER hidden when activityfeed tab fires setMainColumnForTab(). --}}
        <div class="cdn-tabs-strip cdn-main-tab-bar">
        <nav class="client-sidebar-nav" role="tablist" aria-label="Client record sections">
            <?php
            $matter_cnt = \App\Models\ClientMatter::select('id')->where('client_id',$fetchedData->id)->where('matter_status',1)->count();
            
            // Match ClientsController::detail() known tab slugs so $id1 is not misclassified as a matter ref.
            $validTabNames = [
                'personaldetails', 'overview', 'companydetails', 'activityfeed', 'clientaction', 'noteterm', 'personaldocuments', 'matterdocuments', 'documents', 'nominationdocuments',
                'emails', 'client_portal', 'legalforms',
                'formgenerations', 'formgenerationsl',
                'application', 'workflow', 'checklists', 'account', 'notuseddocuments',
                'visadocuments',
            ];
            
            // Check if $id1 is a valid matter ID (not a tab name)
            $isMatterIdInUrl = isset($id1) && $id1 != "" && !in_array(strtolower($id1), array_map('strtolower', $validTabNames));

            $hideMatterDocumentsForBankMatter = isset($id1) && $id1 !== ''
                && preg_match('/^bank_/i', (string) $id1) === 1;

            $cdnShowMattersDocSubtab = ($matter_cnt > 0) && !($hideMatterDocumentsForBankMatter ?? false);
            
            // Show client menu if: valid matter ID in URL OR client has any matters
            if( $isMatterIdInUrl || $matter_cnt > 0 )
            {  //if client unique reference id is present in url
            ?>
                <button type="button" role="tab" id="cdn-tab-personaldetails" class="client-nav-button active" data-tab="personaldetails" aria-selected="true" aria-controls="personaldetails-tab">
                    <i class="fas fa-th-large" aria-hidden="true"></i>
                    <span>Overview</span>
                </button>
                <button type="button" role="tab" id="cdn-tab-activityfeed" class="client-nav-button" data-tab="activityfeed" aria-selected="false" aria-controls="activityfeed-tab">
                    <i class="fas fa-history" aria-hidden="true"></i>
                    <span>Timeline</span>
                </button>
                <button type="button" role="tab" id="cdn-tab-clientaction" class="client-nav-button" data-tab="clientaction" aria-selected="false" aria-controls="clientaction-tab">
                    <i class="fas fa-tasks" aria-hidden="true"></i>
                    <span>Tasks</span>
                </button>
                <button type="button" role="tab" id="cdn-tab-noteterm" class="client-nav-button" data-tab="noteterm" aria-selected="false" aria-controls="noteterm-tab">
                    <i class="fas fa-sticky-note" aria-hidden="true"></i>
                    <span>Notes</span>
                </button>
                <button type="button" role="tab" id="cdn-tab-personaldocuments" class="client-nav-button cdn-demo-doc-nav" data-tab="personaldocuments" aria-selected="false" aria-controls="personaldocuments-tab">
                    <i class="fas fa-folder-open" aria-hidden="true"></i>
                    <span>Documents</span>
                </button>
                <button type="button" role="tab" id="cdn-tab-legalforms" class="client-nav-button" data-tab="legalforms" aria-selected="false" aria-controls="legalforms-tab">
                    <i class="fas fa-file-signature" aria-hidden="true"></i>
                    <span>Legal Forms</span>
                </button>
                <button type="button" role="tab" id="cdn-tab-emails" class="client-nav-button" data-tab="emails" aria-selected="false" aria-controls="emails-tab">
                    <i class="fas fa-inbox" aria-hidden="true"></i>
                    <span>Emails</span>
                </button>
                <button type="button" role="tab" id="cdn-tab-account" class="client-nav-button" data-tab="account" aria-selected="false" aria-controls="account-tab">
                    <i class="fas fa-file-invoice-dollar" aria-hidden="true"></i>
                    <span>Billing</span>
                </button>
            <?php
            }
            else
            {  //If no matter is exist
                $cdnShowMattersDocSubtab = false;
            ?>
                <button type="button" role="tab" id="cdn-tab-personaldetails" class="client-nav-button active" data-tab="personaldetails" aria-selected="true" aria-controls="personaldetails-tab">
                    <i class="fas fa-th-large" aria-hidden="true"></i>
                    <span>Overview</span>
                </button>
                <button type="button" role="tab" id="cdn-tab-activityfeed" class="client-nav-button" data-tab="activityfeed" aria-selected="false" aria-controls="activityfeed-tab">
                    <i class="fas fa-history" aria-hidden="true"></i>
                    <span>Timeline</span>
                </button>
                <button type="button" role="tab" id="cdn-tab-clientaction" class="client-nav-button" data-tab="clientaction" aria-selected="false" aria-controls="clientaction-tab">
                    <i class="fas fa-tasks" aria-hidden="true"></i>
                    <span>Tasks</span>
                </button>
                <button type="button" role="tab" id="cdn-tab-noteterm" class="client-nav-button" data-tab="noteterm" aria-selected="false" aria-controls="noteterm-tab">
                    <i class="fas fa-sticky-note" aria-hidden="true"></i>
                    <span>Notes</span>
                </button>
                <button type="button" role="tab" id="cdn-tab-personaldocuments" class="client-nav-button cdn-demo-doc-nav" data-tab="personaldocuments" aria-selected="false" aria-controls="personaldocuments-tab">
                    <i class="fas fa-folder-open" aria-hidden="true"></i>
                    <span>Documents</span>
                </button>
            <?php
            }
            ?>
        </nav>
        @if(($isMatterIdInUrl || $matter_cnt > 0) && !empty($fetchedData->updated_at))
            @php
                $cdnMainTabLastUpdated = null;
                try {
                    $cdnMainTabLastUpdated = \Carbon\Carbon::parse($fetchedData->updated_at)->format('d/m/Y');
                } catch (\Throwable $e) {
                }
            @endphp
            @if($cdnMainTabLastUpdated)
                <p class="cdn-tab-last-updated">Last update on {{ $cdnMainTabLastUpdated }}</p>
            @endif
        @endif
        </div>
    </aside>

    <main class="main-content" id="main-content">
        <div class="server-error">
            @include('../Elements/flash-message')
        </div>
        <div class="custom-error-msg">
        </div>
        <div class="main-content-with-tabs">
            @php
                $cdnDocStripTab = strtolower((string) ($activeTab ?? ''));
                $cdnDocStripVisibleDemo = in_array($cdnDocStripTab, ['personaldocuments', 'matterdocuments'], true);
            @endphp
            <div id="cdn-doc-subtab-strip" class="cdn-doc-subtab-strip{{ $cdnDocStripVisibleDemo ? ' is-visible' : '' }}" role="tablist" aria-label="Personal documents or matter documents">
                <button type="button" class="cdn-doc-subtab-btn{{ $cdnDocStripTab === 'matterdocuments' ? '' : ' active' }}" data-doc-sub="personaldocuments">Personal documents</button>
                @if(!empty($cdnShowMattersDocSubtab))
                <button type="button" class="cdn-doc-subtab-btn{{ $cdnDocStripTab === 'matterdocuments' ? ' active' : '' }}" data-doc-sub="matterdocuments">Matter documents</button>
                @endif
            </div>
            <!-- Tab Contents -->
            <div class="tab-content" id="tab-content">
            @if(($fetchedData->type ?? '') === 'lead')
            <div class="lead-actions-bar">
                <a href="{{ route('leads.edit', base64_encode(convert_uuencode($fetchedData->id))) }}" class="btn btn-sm btn-secondary">
                    <i class="fa fa-edit"></i> Edit Lead
                </a>
                <a href="{{ route('leads.history', base64_encode(convert_uuencode($fetchedData->id))) }}" class="btn btn-sm btn-warning lead-actions-bar__history">
                    <i class="fa fa-history"></i> View History
                </a>
                @if(($fetchedData->converted ?? 0) == 0)
                <form method="POST" action="{{ route('leads.convert_single') }}" style="display:inline;">
                    @csrf
                    <input type="hidden" name="lead_id" value="{{ base64_encode(convert_uuencode($fetchedData->id)) }}">
                    <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Are you sure you want to convert this lead to a client?')">
                        <i class="fa fa-user"></i> Convert To Client
                    </button>
                </form>
                @else
                <span class="btn btn-sm btn-secondary disabled">
                    <i class="fa fa-check"></i> Converted to Client
                </span>
                @endif
            </div>
            @endif
            @include('crm.clients.tabs.personal_details', ['suppressPersonalDetailsTagCard' => true])
            
            @include('crm.clients.tabs.activityfeed_tab')

            @include('crm.clients.tabs.client_action_tab')
            
            @include('crm.clients.tabs.notes')
            
            @include('crm.clients.tabs.personal_documents')
            
            <?php
            // Mirror the same condition used to render sidebar buttons so that
            // only panes for visible tabs are included (prevents duplicates)
            $matter_cnt = \App\Models\ClientMatter::select('id')
                ->where('client_id',$fetchedData->id)
                ->where('matter_status',1)
                ->count();
            ?>
            @if(((isset($id1) && $id1 != "") || $matter_cnt > 0) && !($hideMatterDocumentsForBankMatter ?? false))
                @include('crm.clients.tabs.matter_documents')
            @endif
            @if((isset($id1) && $id1 != "") || $matter_cnt > 0)
                @include('crm.clients.tabs.legal_forms')
                @include('crm.clients.tabs.account')
                @include('crm.clients.tabs.emails')
            @endif
            
            @include('crm.clients.tabs.not_used_documents')
            
            </div>
        </div>
    </main>

    <!-- Activity Feed (Personal Details, Activity nav, etc.) -->
    @include('crm.clients.tabs.activity_feed')
</div>
</div>

@include('crm.clients.addclientmodal')
@include('crm.clients.editclientmodal')
@include('crm.clients.modals.edit-matter-office')
@include('crm.clients.modals.client-management')

{{-- Update Stage: same workflow UI + routes as production workflow tab (Admin Console–defined stages). --}}
<div class="modal fade" id="cdn-update-stage-modal" tabindex="-1" aria-labelledby="cdnUpdateStageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cdnUpdateStageModalLabel">Update stage</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body cdn-update-stage-modal-body p-2">
                @include('crm.clients.tabs.workflow')
            </div>
        </div>
    </div>
</div>





<div id="emailmodal"  data-backdrop="static" data-keyboard="false" class="modal fade custom_modal" tabindex="-1" role="dialog" aria-labelledby="clientModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="clientModalLabel">Compose Email</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" name="sendmail" action="{{route('clients.sendmail')}}" autocomplete="off" enctype="multipart/form-data">
				@csrf
                    <input type="hidden" name="client_id" value="{{$fetchedData->id}}">
                    <input type="hidden" name="type" value="client">
                    <input type="hidden" name="mail_type" value="1">
                    <input type="hidden" name="mail_body_type" value="sent">
                    <input type="hidden" name="compose_client_matter_id" id="compose_client_matter_id" value="">
					<div class="row">
						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="email_from">From <span class="span_req">*</span></label>
								@include('partials.email-from-sendgrid')
								@if ($errors->has('email_from'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('email_from') }}</strong>
									</span>
								@endif
							</div>
						</div>
						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="email_to">To <span class="span_req">*</span></label>
								<select data-valid="required" class="js-data-example-ajax" name="email_to[]"></select>

								@if ($errors->has('email_to'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('email_to') }}</strong>
									</span>
								@endif
							</div>
						</div>
						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="email_cc">CC </label>
								<select data-valid="" class="js-data-example-ajaxccd" name="email_cc[]"></select>

								@if ($errors->has('email_cc'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('email_cc') }}</strong>
									</span>
								@endif
							</div>
						</div>

                        <div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="template">Templates </label>
                                <?php
                                $clientAssigneeName = ''; // assignee column removed
                                if(false){
                                } else {
                                    $clientAssigneeName = 'NA';
                                }
                                ?>
								<select data-valid="" class="form-control select2 selecttemplate" name="template" data-clientid="{{@$fetchedData->id}}" data-clientfirstname="{{@$fetchedData->first_name}}" data-clientvisaExpiry="{{@$fetchedData->visaExpiry}}" data-clientreference_number="{{@$fetchedData->client_id}}" data-clientassignee_name="{{@$clientAssigneeName}}">
									<option value="">Select</option>
									@foreach( \App\Models\EmailTemplate::crm()->orderBy('id', 'desc')->get() as $list)
										<option value="{{$list->id}}">{{$list->name}}</option>
									@endforeach
								</select>
                            </div>
						</div>


						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="subject">Subject <span class="span_req">*</span></label>
								<input type="text" name="subject" id="compose_email_subject" class="form-control selectedsubject" data-valid="required" autocomplete="off" placeholder="Enter Subject" value="" />
								@if ($errors->has('subject'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('subject') }}</strong>
									</span>
								@endif
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="message">Message <span class="span_req">*</span></label>
								<textarea class="tinymce-editor selectedmessage" id="compose_email_message" name="message" data-valid="required"></textarea>
								@if ($errors->has('message'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('message') }}</strong>
									</span>
								@endif
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
						     <div class="form-group">
						        <label>Attachment</label>
						        <input type="file" name="attach[]" class="form-control" multiple>
						     </div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
						    <div class="table-responsive uploadchecklists">
							<table id="mychecklist-datatable" class="table text_wrap table-2">
							    <thead>
							        <tr>
							            <th></th>
							            <th>File Name</th>
							            <th>File</th>
							        </tr>
							    </thead>
							    <tbody>
							        @php
							            $__matterChecklistRows = \Illuminate\Support\Facades\Schema::hasTable('matter_checklists')
							                ? \App\Models\UploadChecklist::orderBy('id')->get()
							                : collect();
							        @endphp
							        @foreach($__matterChecklistRows as $uclist)
							        <tr data-matter-id="{{ $uclist->matter_id ?? '' }}" data-checklist-id="{{ $uclist->id }}">
							            <td><input type="checkbox" name="checklistfile[]" value="<?php echo $uclist->id; ?>" class="checklistfile-cb"></td>
							            <td><?php echo $uclist->name; ?></td>
							             <td><a target="_blank" href="<?php echo URL::to('/checklists/'.$uclist->file); ?>"><?php echo $uclist->name; ?></a></td>
							        </tr>
							        @endforeach
							    </tbody>
							</table>
						</div>
							</div>
						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="saveComposeEmail()" type="button" class="btn btn-primary">Send</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>


<!-- Send Message-->
<div id="sendmsgmodal"  data-backdrop="static" data-keyboard="false" class="modal fade custom_modal" tabindex="-1" role="dialog" aria-labelledby="messageModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="messageModalLabel">Send Message</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" name="sendmsg" id="sendmsg" action="{{route('clients.sendmail')}}" autocomplete="off" enctype="multipart/form-data">
				    @csrf
                    <input type="hidden" name="client_id" id="sendmsg_client_id" value="">
                    <input type="hidden" name="vtype" value="client">
					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="message">Message <span class="span_req">*</span></label>
								<textarea id="sendmsg_message" class="tinymce-editor selectedmessage" name="message" data-valid="required"></textarea>
								@if ($errors->has('message'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('message') }}</strong>
									</span>
								@endif
							</div>
						</div>
                        <div class="col-12 col-md-12 col-lg-12">
							<button onclick="saveSendMessage()" type="button" class="btn btn-primary">Send</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<!-- Send SMS Modal -->
<div id="sendSmsModal" data-backdrop="static" data-keyboard="false" class="modal fade custom_modal" tabindex="-1" role="dialog" aria-labelledby="smsModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="smsModalLabel">
					<i class="fas fa-sms"></i> Send SMS
				</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form id="sendSmsForm">
				    @csrf
                    <input type="hidden" name="client_id" id="sms_client_id" value="">
                    
					<div class="row">
						<!-- Phone Number Selection -->
						<div class="col-12">
							<div class="form-group">
								<label for="sms_phone">Send To <span class="span_req">*</span></label>
								<select class="form-control" id="sms_phone" name="phone" required>
									<option value="">Select phone number...</option>
								</select>
								<small class="form-text text-muted">
									<i class="fas fa-info-circle"></i> 
									Australian numbers will use Cellcast, international numbers will use Twilio
								</small>
							</div>
						</div>
						
						<!-- Template Selection -->
						<div class="col-12">
							<div class="form-group">
								<label for="sms_template">Quick Template (Optional)</label>
								<select class="form-control" id="sms_template">
									<option value="">Type your own message or select a template...</option>
								</select>
							</div>
						</div>
						
						<!-- Message -->
						<div class="col-12">
							<div class="form-group">
								<label for="sms_message">Message <span class="span_req">*</span></label>
								<textarea class="form-control" id="sms_message" name="message" rows="5" maxlength="1600" required></textarea>
								<div class="d-flex justify-content-between">
									<small class="form-text text-muted">
										<span id="sms_char_count">0</span> / 1600 characters
									</small>
									<small class="form-text text-muted">
										<span id="sms_parts_count">1</span> SMS part(s)
									</small>
								</div>
							</div>
						</div>
						
						<!-- Buttons -->
                        <div class="col-12">
							<button type="submit" class="btn btn-primary" id="sendSmsBtn">
								<i class="fas fa-paper-plane"></i> Send SMS
							</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

{{-- interest_service_view modal REMOVED - Interested Services feature deprecated (no UI triggers) --}}

<div id="confirmModal" tabindex="-1" role="dialog" aria-labelledby="confirmModalLabel" aria-hidden="false" class="modal fade" >
	<div class="modal-dialog">
		<div class="modal-content popUp">
			<div class="modal-body text-center">
				<button type="button" data-bs-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title text-center message col-v-5">Do you want to delete this note?</h4>
				<button type="submit" style="margin-top: 40px;" class="button btn btn-danger accept">Delete</button>
				<button type="button" style="margin-top: 40px;" data-bs-dismiss="modal" class="button btn btn-secondary cancel">Cancel</button>
			</div>
		</div>
	</div>
</div>

<div id="confirmNotUseDocModal" tabindex="-1" role="dialog" aria-labelledby="confirmModalLabel" aria-hidden="false" class="modal fade" >
	<div class="modal-dialog">
		<div class="modal-content popUp">
			<div class="modal-body text-center">
				<button type="button" data-bs-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title text-center message col-v-5">Do you want to send this document in Not Use Tab?</h4>
				<button type="submit" style="margin-top: 40px;" class="button btn btn-danger accept">Send</button>
				<button type="button" style="margin-top: 40px;" data-bs-dismiss="modal" class="button btn btn-secondary cancel">Cancel</button>
			</div>
		</div>
	</div>
</div>

<div id="confirmBackToDocModal" tabindex="-1" role="dialog" aria-labelledby="confirmModalLabel" aria-hidden="false" class="modal fade" >
	<div class="modal-dialog">
		<div class="modal-content popUp">
			<div class="modal-body text-center">
				<button type="button" data-bs-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title text-center message col-v-5">Do you want to send this in related document Tab again?</h4>
				<button type="submit" style="margin-top: 40px;" class="button btn btn-danger accept">Send</button>
				<button type="button" style="margin-top: 40px;" data-bs-dismiss="modal" class="button btn btn-secondary cancel">Cancel</button>
			</div>
		</div>
	</div>
</div>

<div id="confirmDocModal" tabindex="-1" role="dialog" aria-labelledby="confirmModalLabel" aria-hidden="false" class="modal fade" >
	<div class="modal-dialog">
		<div class="modal-content popUp">
			<div class="modal-body text-center">
				<button type="button" data-bs-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title text-center message col-v-5">Do you want to verify this doc?</h4>
				<button type="submit" style="margin-top: 40px;" class="button btn btn-danger accept">Verify</button>
				<button type="button" style="margin-top: 40px;" data-bs-dismiss="modal" class="button btn btn-secondary cancel">Cancel</button>
			</div>
		</div>
	</div>
</div>


<div id="confirmLogModal" tabindex="-1" role="dialog" aria-labelledby="confirmLogModalLabel" aria-hidden="false" class="modal fade" >
	<div class="modal-dialog">
		<div class="modal-content popUp">
			<div class="modal-body text-center">
				<button type="button" data-bs-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title text-center message col-v-5">Do you want to delete this log?</h4>
				<button type="submit" style="margin-top: 40px;" class="button btn btn-danger accept">Delete</button>
				<button type="button" style="margin-top: 40px;" data-bs-dismiss="modal" class="button btn btn-secondary cancel">Cancel</button>
			</div>
		</div>
	</div>
</div>

<!-- confirmEducationModal removed - education system deprecated (replaced by ClientQualification) -->

<div id="confirmcompleteModal" tabindex="-1" role="dialog" aria-labelledby="confirmModalLabel" aria-hidden="false" class="modal fade" >
	<div class="modal-dialog">
		<div class="modal-content popUp">
			<div class="modal-body text-center">
				<button type="button" data-bs-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title text-center message col-v-5">Do you want to complete the Application?</h4>
				<button  data-id="" type="submit" style="margin-top: 40px;" class="button btn btn-danger acceptapplication">Complete</button>
				<button type="button" style="margin-top: 40px;" data-bs-dismiss="modal" class="button btn btn-secondary cancel">Cancel</button>
			</div>
		</div>
	</div>
</div>

<div id="confirmCostAgreementModal" tabindex="-1" role="dialog" aria-labelledby="confirmCostAgreementModalLabel" aria-hidden="false" class="modal fade" >
	<div class="modal-dialog">
		<div class="modal-content popUp">
			<div class="modal-body text-center">
				<button type="button" data-bs-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title text-center message col-v-5">Do you want to delete this Cost Agreement?</h4>
				<button data-id="" type="submit" style="margin-top: 40px;" class="button btn btn-danger acceptCostAgreementDelete">Yes, Delete</button>
				<button type="button" style="margin-top: 40px;" data-bs-dismiss="modal" class="button btn btn-secondary cancel">Cancel</button>
			</div>
		</div>
	</div>
</div>

{{-- confirmpublishdocModal REMOVED - workflow checklist unused --}}

<div class="modal fade custom_modal" id="matter_ownership" tabindex="-1" role="dialog" aria-labelledby="matterModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appliationModalLabel">Matter Ownership Ratio</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" action="{{url('/client-portal/ownership')}}" name="xmatter_ownership" id="xmatter_ownership" autocomplete="off" enctype="multipart/form-data">
				@csrf
				<input type="hidden" name="mapp_id" id="mapp_id" value="">
					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="sus_agent"> </label>
								<input type="number" max="100" min="0" step="0.01" class="form-control ration" name="ratio">
								<span class="custom-error workflow_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="customValidate('xmatter_ownership')" type="button" class="btn btn-primary">Save</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<div class="modal fade custom_modal" id="tags_clients" tabindex="-1" role="dialog" aria-labelledby="matterModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appliationModalLabel">Tags</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
                <form method="post" action="{{url('/save_tag')}}" name="stags_matter" id="stags_matter" autocomplete="off" enctype="multipart/form-data">
				@csrf
				<input type="hidden" name="client_id" id="client_id" value="">
				<input type="hidden" name="create_new_as_red" id="create_new_as_red" value="0">
					<div id="tags_red_mode_hint" class="alert alert-warning py-2 mb-2" style="display: none;">
						<i class="fas fa-exclamation-triangle text-danger"></i> <strong>Red Tag mode:</strong> Any new tags you add will be created as Red tags (hidden by default).
					</div>
					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="tags_modal_container">Tags</label>
								@php
									[$__modalNormal, $__modalRed] = \App\Support\ClientTagStorage::decode($fetchedData->tagname ?? '');
								@endphp
								<div id="tags_modal_container" class="tags-modal-container form-control">
									<div class="tags-pills-inner">
										@foreach($__modalNormal as $tagName)
										<span class="tag-pill" data-tag-name="{{ $tagName }}" data-tag-red="0">
											<span class="tag-pill-text">{{ $tagName }}</span>
											<button type="button" class="tag-pill-remove" aria-label="Remove tag">&times;</button>
										</span>
										@endforeach
										@foreach($__modalRed as $tagName)
										<span class="tag-pill tag-pill--red" data-tag-name="{{ $tagName }}" data-tag-red="1">
											<span class="tag-pill-text">{{ $tagName }}</span>
											<button type="button" class="tag-pill-remove" aria-label="Remove tag">&times;</button>
										</span>
										@endforeach
										<input type="text" id="tag_input" class="tag-input-inline" placeholder="Type and press comma or Enter to add" autocomplete="off">
									</div>
								</div>
								<input type="hidden" id="tags_validation" value="{{ (count($__modalNormal) + count($__modalRed)) > 0 ? '1' : '' }}" aria-hidden="true">
								<small class="form-text text-muted">Separate tags with commas or press Enter to add.</small>
							</div>
						</div>

						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="customValidate('stags_matter')" type="button" class="btn btn-primary">Save</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<style>
.tags-modal-container { min-height: 42px; padding: 6px 10px; display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
.tags-pills-inner { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; flex: 1; }
.tag-pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background-color: #6A60E3; color: #fff; border-radius: 6px; font-size: 13px; }
.tag-pill.tag-pill--red { background-color: #dc3545; }
.tag-pill-text { white-space: nowrap; }
.tag-pill-remove { background: none; border: none; color: #fff; cursor: pointer; font-size: 16px; line-height: 1; padding: 0 2px; opacity: 0.8; }
.tag-pill-remove:hover { opacity: 1; }
.tag-input-inline { flex: 1; min-width: 120px; border: none; outline: none; font-size: 14px; background: transparent; }
</style>

{{-- Service Taken Modal - REMOVED --}}
{{-- Feature deprecated - client_service_takens table does not exist --}}
{{-- Table was for tracking Migration/Education services taken by clients --}}
{{-- Model clientServiceTaken.php deleted - no database backing --}}
{{-- Routes still exist but will fail: createservicetaken, removeservicetaken, getservicetaken --}}

<div class="modal fade" id="inbox_reassignemail_modal">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				  <h4 class="modal-title">Re-assign Inbox Email</h4>
				  <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				  </button>
			</div>
			<form method="POST" action="{{ url('/reassiginboxemail') }}" name="inbox-email-reassign-to-client-matter" autocomplete="off" enctype="multipart/form-data" id="inbox-email-reassign-to-client-matter">
			@csrf
			<div class="modal-body">
				<div class="form-group row">
					<div class="col-sm-12">
						<input id="memail_id" name="memail_id" type="hidden" value="">
                        <input id="mail_type" name="mail_type" type="hidden" value="inbox">
                        <input id="staff_mail" name="staff_mail" type="hidden" value="">
                        <input id="uploaded_doc_id" name="uploaded_doc_id" type="hidden" value="">
						<select id="reassign_client_id" name="reassign_client_id" class="form-control select2" style="width: 100%;" data-select2-id="1" tabindex="-1" aria-hidden="true" data-valid="required">
							<option value="">Select Client</option>
							@foreach(\App\Models\Admin::where('type','client')->get() as $clientItem)
							<option value="{{@$clientItem->id}}">{{@$clientItem->first_name}} {{@$clientItem->last_name}}({{@$clientItem->client_id}})</option>
							@endforeach
						</select>
					</div>
				</div>

                <div class="form-group row">
					<div class="col-sm-12">
						<select id="reassign_client_matter_id" name="reassign_client_matter_id" class="form-control select2 " style="width: 100%;" data-select2-id="1" tabindex="-1" aria-hidden="true" disabled>
						</select>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-primary" onclick="customValidate('inbox-email-reassign-to-client-matter')">
					<i class="fa fa-save"></i> Re-assign Inbox Email
				</button>
			</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" id="sent_reassignemail_modal">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				  <h4 class="modal-title">Re-assign Sent Email</h4>
				  <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				  </button>
			</div>
			<form method="POST" action="{{ url('/reassigsentemail') }}" name="sent-email-reassign-to-client-matter" autocomplete="off" enctype="multipart/form-data" id="sent-email-reassign-to-client-matter">
			@csrf
			<div class="modal-body">
				<div class="form-group row">
					<div class="col-sm-12">
						<input id="memail_id" name="memail_id" type="hidden" value="">
                        <input id="mail_type" name="mail_type" type="hidden" value="sent">
                        <input id="staff_mail" name="staff_mail" type="hidden" value="">
                        <input id="uploaded_doc_id" name="uploaded_doc_id" type="hidden" value="">
						<select id="reassign_sent_client_id" name="reassign_sent_client_id" class="form-control select2" style="width: 100%;" data-select2-id="1" tabindex="-1" aria-hidden="true" data-valid="required">
							<option value="">Select Client</option>
							@foreach(\App\Models\Admin::where('type','client')->get() as $clientItem)
							<option value="{{@$clientItem->id}}">{{@$clientItem->first_name}} {{@$clientItem->last_name}}({{@$clientItem->client_id}})</option>
							@endforeach
						</select>
					</div>
				</div>

                <div class="form-group row">
					<div class="col-sm-12">
						<select id="reassign_sent_client_matter_id" name="reassign_sent_client_matter_id" class="form-control select2 " style="width: 100%;" data-select2-id="1" tabindex="-1" aria-hidden="true" disabled>
						</select>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-primary" onclick="customValidate('sent-email-reassign-to-client-matter')">
					<i class="fa fa-save"></i> Re-assign Sent Email
				</button>
		</div>
		</form>
		</div>
	</div>

<div class="modal fade" id="sent_mail_preview_modal">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				  <h4 class="modal-title" id="memail_subject"></h4>
				  <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				  </button>
			</div>
			<div class="modal-body">
				<div class="form-group row">
					<div class="col-sm-12" id="memail_message">
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

@if($showGoogleReviewReminderModal ?? false)
<div class="modal fade custom_modal google-review-reminder-modal" id="googleReviewReminderModal" tabindex="-1" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="googleReviewReminderModalLabel" aria-describedby="googleReviewReminderModalDesc googleReviewReminderModalHint" data-backdrop="static" data-keyboard="false" data-auto-open="1">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="googleReviewReminderModalLabel"><i class="fab fa-google mr-2" aria-hidden="true"></i>Google review reminder</h5>
				<button type="button" class="close grr-modal-close-btn js-google-review-reminder" data-action="snooze_one_day" aria-label="Close and remind again tomorrow" title="Close — ask again tomorrow">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<p class="mb-2 grr-modal-text" id="googleReviewReminderModalDesc">Has this contact been asked to leave a Google review? Choose an option so we know whether to remind you next time you open their record.</p>
				<p class="mb-0 small grr-modal-hint" id="googleReviewReminderModalHint">Closing with the × button above hides this until tomorrow (one-day snooze).</p>
			</div>
			<div class="modal-footer flex-wrap justify-content-stretch gap-2 grr-modal-footer">
				<button type="button" class="btn w-100 m-0 js-google-review-send-sms grr-btn grr-btn-sms">
					<i class="fas fa-sms mr-1" aria-hidden="true"></i>Send SMS with review link
				</button>
				<button type="button" class="btn flex-grow-1 m-0 js-google-review-reminder grr-btn grr-btn-not-interested" data-action="not_interested">Not interested</button>
				<button type="button" class="btn flex-grow-1 m-0 js-google-review-reminder grr-btn grr-btn-snooze" data-action="snooze">Remind me in 1 week</button>
				<button type="button" class="btn flex-grow-1 m-0 js-google-review-reminder grr-btn grr-btn-received" data-action="review_received">Review received</button>
			</div>
		</div>
	</div>
</div>
@endif

@endsection
@push('scripts')
<!-- TinyMCE Editor -->
<script src="{{asset('js/tinymce/js/tinymce/tinymce.min.js')}}"></script>
<script>
// TinyMCE Configuration for Email Modals
var tinymceEmailConfig = {
    license_key: 'gpl',
    height: 300,
    menubar: false,
    plugins: ['lists', 'link', 'autolink'],
    toolbar: 'bold italic underline strikethrough | forecolor | bullist numlist | link',
    content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif; font-size: 14px; }',
    branding: false,
    promotion: false,
    color_map: [
        "000000", "Black", "333333", "Dark Gray", "666666", "Medium Gray",
        "999999", "Light Gray", "CCCCCC", "Very Light Gray", "E0E0E0", "Pale Gray",
        "F5F5F5", "Off White", "FFFFFF", "White", "DC2626", "Red",
        "EA580C", "Orange", "D97706", "Amber", "059669", "Green",
        "0891B2", "Cyan", "2563EB", "Blue", "7C3AED", "Purple",
        "DB2777", "Pink", "EF4444", "Light Red", "F97316", "Light Orange",
        "F59E0B", "Light Amber", "10B981", "Light Green", "06B6D4", "Light Cyan",
        "3B82F6", "Light Blue", "8B5CF6", "Light Purple", "EC4899", "Light Pink"
    ],
    setup: function(editor) {
        editor.on('change', function() {
            editor.save();
        });
    }
};

// Initialize TinyMCE for all email modals
function initTinyMCEForModals() {
    // Compose Email Modal
    if ($('#compose_email_message').length && !tinymce.get('compose_email_message')) {
        tinymce.init({
            ...tinymceEmailConfig,
            selector: '#compose_email_message',
            init_instance_callback: function(editor) {
                // Handle modal show event
                $('#emailmodal').on('shown.bs.modal', function() {
                    editor.focus();
                });
            }
        });
    }
    
    // Send Message Modal
    if ($('#sendmsg_message').length && !tinymce.get('sendmsg_message')) {
        tinymce.init({
            ...tinymceEmailConfig,
            selector: '#sendmsg_message',
            init_instance_callback: function(editor) {
                $('#sendmsgmodal').on('shown.bs.modal', function() {
                    editor.focus();
                });
            }
        });
    }
    
    // Application Email Modal
    if ($('#matter_email_message').length && !tinymce.get('matter_email_message')) {
        tinymce.init({
            ...tinymceEmailConfig,
            selector: '#matter_email_message',
            init_instance_callback: function(editor) {
                $('#matteremailmodal').on('shown.bs.modal', function() {
                    editor.focus();
                });
            }
        });
    }
    
    // Upload Mail Modal
    if ($('#uploadmail_message').length && !tinymce.get('uploadmail_message')) {
        tinymce.init({
            ...tinymceEmailConfig,
            selector: '#uploadmail_message',
            init_instance_callback: function(editor) {
                $('#uploadmail').on('shown.bs.modal', function() {
                    editor.focus();
                });
            }
        });
    }
}

// Helper functions to save TinyMCE content before form validation
window.saveComposeEmail = function() {
    if (tinymce.get('compose_email_message')) {
        tinymce.get('compose_email_message').save();
    }
    customValidate('sendmail');
};

window.saveSendMessage = function() {
    if (tinymce.get('sendmsg_message')) {
        tinymce.get('sendmsg_message').save();
    }
    customValidate('sendmsg');
};

window.saveApplicationEmail = function() {
    if (tinymce.get('matter_email_message')) {
        tinymce.get('matter_email_message').save();
    }
    customValidate('appkicationsendmail');
};

window.saveUploadMail = function() {
    if (tinymce.get('uploadmail_message')) {
        tinymce.get('uploadmail_message').save();
    }
    customValidate('uploadmail');
};

// Helper function to set TinyMCE content (can be called from anywhere)
window.setTinyMCEContent = function(editorId, content) {
    if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
        tinymce.get(editorId).setContent(content || '');
    } else {
        $('#' + editorId).val(content || '');
        // Try to initialize if not already initialized
        setTimeout(function() {
            initTinyMCEForModals();
            if (tinymce.get(editorId)) {
                tinymce.get(editorId).setContent(content || '');
            }
        }, 200);
    }
};

// Initialize TinyMCE when DOM is ready
$(document).ready(function() {
    // Call getallactivities after page load if pending (from receipt save)
    var pendingClientId = localStorage.getItem('pendingGetActivities');
    if (pendingClientId && typeof getallactivities === 'function') {
        // Wait for page to fully load and account tab to be active
        setTimeout(function() {
            var activeTab = localStorage.getItem('activeTab');
            
            if (activeTab === 'accounts' || activeTab === 'account') {
                getallactivities(pendingClientId);
                localStorage.removeItem('pendingGetActivities');
            } else {
                // Retry after tab activation
                setTimeout(function() {
                    if (typeof getallactivities === 'function') {
                        getallactivities(pendingClientId);
                        localStorage.removeItem('pendingGetActivities');
                    }
                }, 1000);
            }
        }, 500);
    }
    
    initTinyMCEForModals();
    
    // Re-initialize when modals are shown (in case they're dynamically loaded)
    $('#emailmodal, #sendmsgmodal, #matteremailmodal, #uploadmail').on('shown.bs.modal', function() {
        setTimeout(function() {
            initTinyMCEForModals();
        }, 100);
    });
    
    // Auto-select matter first email and dedicated checklists when compose modal opens
    // When matter is selected: filter checklist table by matter (DataTables API); otherwise show all
    $('#emailmodal').on('shown.bs.modal', function() {
        var clientMatterId = $('#compose_client_matter_id').val();
        if (!clientMatterId || !window.ClientDetailConfig || !window.ClientDetailConfig.urls || !window.ClientDetailConfig.urls.getComposeDefaults) {
            window.composeChecklistFilterIds = null;
            if ($('#mychecklist-datatable').length && $.fn.DataTable && $.fn.DataTable.isDataTable('#mychecklist-datatable')) {
                $('#mychecklist-datatable').DataTable().draw();
            }
            $('#emailmodal').removeData('composeMacroValues').removeData('pdfUrlForSign').removeData('fromSignatureSend');
            return;
        }
        $.get(window.ClientDetailConfig.urls.getComposeDefaults, { client_matter_id: clientMatterId })
            .done(function(res) {
                var $templateSelect = $('#emailmodal select.selecttemplate');
                var $checklistCbs = $('#emailmodal .checklistfile-cb');
                if (res.macro_values) {
                    var macroVals = res.macro_values;
                    var pdfUrl = $('#emailmodal').data('pdfUrlForSign');
                    if (pdfUrl) {
                        macroVals = Object.assign({}, macroVals, { PDF_url_for_sign: pdfUrl });
                    }
                    $('#emailmodal').data('composeMacroValues', macroVals);
                } else {
                    $('#emailmodal').removeData('composeMacroValues');
                }
                if (res.matter_templates !== undefined && $templateSelect.length) {
                    // Replace dropdown with matter-specific options only: First Email first, then Matter Other Email Templates
                    $templateSelect.empty().append($('<option value="">Select</option>'));
                    (res.matter_templates || []).forEach(function(t) {
                        $templateSelect.append($('<option></option>').attr('value', t.id).text(t.name || 'Template'));
                    });
                    var fromSignature = $('#emailmodal').data('fromSignatureSend');
                    var toSelect = res.template ? res.template.id : (res.matter_templates && res.matter_templates[0] ? res.matter_templates[0].id : null);
                    if (toSelect) {
                        $templateSelect.val(toSelect).trigger('change');
                        if (fromSignature) $('#emailmodal').removeData('fromSignatureSend');
                    }
                }
                // Filter checklist table by matter using DataTables API
                window.composeChecklistFilterIds = (res.checklist_ids && res.checklist_ids.length) ? res.checklist_ids : [];
                if ($('#mychecklist-datatable').length && $.fn.DataTable && $.fn.DataTable.isDataTable('#mychecklist-datatable')) {
                    $('#mychecklist-datatable').DataTable().draw();
                }
                $checklistCbs.prop('checked', false);
                if (res.checklist_ids && res.checklist_ids.length) {
                    res.checklist_ids.forEach(function(id) {
                        $('#emailmodal input.checklistfile-cb[value="' + id + '"]').prop('checked', true);
                    });
                }
            })
            .fail(function() {
                window.composeChecklistFilterIds = null;
                if ($('#mychecklist-datatable').length && $.fn.DataTable && $.fn.DataTable.isDataTable('#mychecklist-datatable')) {
                    $('#mychecklist-datatable').DataTable().draw();
                }
            });
    });
});
</script>
<script src="{{URL::to('/')}}/js/popover.js"></script>
{{-- Bootstrap-datepicker removed - already loaded in layout, migrating to Flatpickr --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-datetimepicker/2.5.20/jquery.datetimepicker.full.min.js"></script>

{{-- Activity Feed Functionality --}}
<script src="{{ URL::asset('js/crm/clients/tabs/activity-feed.js') }}"></script>

{{-- Sidebar Tabs Management - Dedicated file for sidebar navigation --}}
<script src="{{URL::asset('js/crm/clients/sidebar-tabs.js')}}"></script>

{{-- Pass Blade variables to JavaScript --}}
<script>
    // Configuration object with all Blade variables needed for JavaScript
    window.ClientDetailConfig = {
        clientId: @json(($fetchedData->id ?? '')),
        encodeId: @json(($encodeId ?? '')),
        matterId: @json(($id1 ?? '')),
        activeTab: @json(($activeTab ?? 'personaldetails')),
        cdnShowMattersDocSubtab: @json(!empty($cdnShowMattersDocSubtab)),
        matterRefNo: @json(($id1 ?? '')),
        clientFirstName: @json(($fetchedData->first_name ?? 'client')),
        notPickedCallSmsDefault: @json($notPickedCallSmsDefault ?? ''),
        detailBaseUrl: '{{ url("/clients/detail") }}',
        // SMS Template Variables
        staffName: @json(($staffName ?? '')),
        matterNumber: @json(($matterNumber ?? '')),
        officePhone: @json(($officePhone ?? '')),
        officeCountryCode: @json(($officeCountryCode ?? '+61')),
        csrfToken: @json(csrf_token()),
        currentDate: @json(date('Y-m-d')),
        appId: @json(($_GET['appid'] ?? '')),
        // AWS Configuration for document URLs
        aws: {
            bucket: @json(env('AWS_BUCKET', '')),
            region: @json(env('AWS_DEFAULT_REGION', 'ap-southeast-2'))
        },
        urls: {
            base: '{{ URL::to("/") }}',
            admin: '{{ URL::to("/") }}',
            downloadDocument: '{{ url("/documents/download") }}',
            getTopInvoiceNo: '{{ URL::to("/clients/getTopInvoiceNoFromDB") }}',
            getTopReceiptVal: '{{ URL::to("/clients/getTopReceiptValInDB") }}',
            listOfInvoice: '{{ URL::to("/clients/listOfInvoice") }}',
            clientLedgerBalance: '{{ URL::to("/clients/clientLedgerBalanceAmount") }}',
            getInvoicesByMatter: '{{ URL::to("/get-invoices-by-matter") }}',
            updateNoteDatetime: '{{ URL::to("/update-note-datetime") }}',
            updateClientFundsLedger: '{{ route("clients.update-client-funds-ledger") }}',
            createIntakeUrl: '{{ url("/clients/store-application-doc-via-form") }}',
            enhanceMail: '{{ route("mail.enhance") }}',
            composeEmail: '{{ URL::to("/sendmail") }}',
            createNote: '{{ URL::to("/create-note") }}',
            getNoteDetail: '{{ URL::to("/getnotedetail") }}',
            deleteNote: '{{ URL::to("/deletenote") }}',
            checkStarClient: '{{ route("check.star.client") }}',
            getInfoByReceiptId: '{{ URL::to("/clients/getInfoByReceiptId") }}',
            notPickedCall: '{{ URL::to("/not-picked-call") }}',
            getDateTimeBackend: '{{ URL::to("/getdatetimebackend") }}',
            getDisabledDateTime: '{{ URL::to("/getdisableddatetime") }}',
            checkCostAssignment: '{{ URL::to("/clients/check-cost-assignment") }}',
            getVisaAgreementLegalPractitioner: '{{ URL::to("/clients/getVisaAgreementLegalPractitionerDetail") }}',
            generateAgreement: '{{ route("clients.generateagreement") }}',
            getCostAssignmentLegalPractitioner: '{{ URL::to("/clients/getCostAssignmentLegalPractitionerDetail") }}',
            getCostAssignmentLegalPractitionerLead: '{{ URL::to("/clients/getCostAssignmentLegalPractitionerDetailLead") }}',
            uploadAgreement: '{{ route("clients.uploadAgreement", $fetchedData->id) }}',
            fetchClientContactNo: '{{ URL::to("/clients/fetchClientContactNo") }}',
            followupStore: '{{ URL::to("/clients/action/store") }}',
            // publishDoc, deleteClientPortalDoc REMOVED - workflow checklist unused
            deleteCostagreement: '{{ URL::to("/deletecostagreement") }}',
            deleteAction: '{{ URL::to("/delete_action") }}',
            pinNote: '{{ URL::to("/pinnote") }}',
            pinActivityLog: '{{ URL::to("/pinactivitylog") }}',
            getRecipients: '{{ URL::to("/clients/get-recipients") }}',
            updateSessionCompleted: '{{ URL::to("/clients/update-session-completed") }}',
            viewNoteDetail: '{{ URL::to("/viewnotedetail") }}',
            viewMatterNote: '{{ URL::to("/viewmatternote") }}',
            changeClientStatus: '{{ URL::to("/change-client-status") }}',
            getTemplates: '{{ URL::to("/get-templates") }}',
            getComposeDefaults: '{{ URL::to("/get-compose-defaults") }}',
            getPartner: '{{ URL::to("/getpartner") }}',
            renameDoc: '{{ URL::to("/documents/rename") }}',
            renameChecklistDoc: '{{ URL::to("/documents/rename-checklist") }}',
            deleteChecklist: '{{ route("clients.documents.deleteChecklist") }}',
            getInterestedService: '{{ URL::to("/getintrestedservice") }}',
            getInterestedServiceEdit: '{{ URL::to("/getintrestedserviceedit") }}',
            fetchClientMatterAssignee: '{{ URL::to("/clients/fetchClientMatterAssignee") }}',
            updateStage: '{{ URL::to("/updatestage") }}',
            completeStage: '{{ URL::to("/completestage") }}',
            updateBackStage: '{{ URL::to("/updatebackstage") }}',
            sendToHubdoc: '{{ url("/clients/sendToHubdoc") }}',
            checkHubdocStatus: '{{ url("/clients/checkHubdocStatus") }}',
            updateMailReadBit: '{{ URL::to("/clients/updatemailreadbit") }}',
            listAllMatters: '{{ URL::to("/clients/listAllMattersWRTSelClient") }}',
            getActivities: '{{ route("clients.activities") }}',
            getNotes: '{{ URL::to("/get-notes") }}',
            matterTaskIndex: '{{ route("clients.matterTask.index") }}',
            matterTaskStore: '{{ route("clients.matterTask.store") }}',
            matterTaskBase: '{{ url("/clients/matter-tasks") }}',
            updatePersonalCategory: '{{ route("clients.documents.updatePersonalDocCategory") }}',
            updateVisaCategory: '{{ route("clients.documents.updateVisaDocCategory") }}',
            updateNominationCategory: '{{ route("clients.documents.updateNominationDocCategory") }}',
            deletePersonalCategory: '{{ route("clients.documents.deletePersonalDocCategory") }}',
            sendInvoiceToClient: '{{ url("/clients/send-invoice-to-client") }}',
            sendClientFundReceiptToClient: '{{ url("/clients/send-client-fund-receipt-to-client") }}',
            sendOfficeReceiptToClient: '{{ url("/clients/send-office-receipt-to-client") }}',
        }
    };
    
    // Global function to load activities feed
    window.loadActivities = function() {
        $.ajax({
            url: window.ClientDetailConfig.urls.getActivities,
            type: 'GET',
            dataType: 'json',
            data: { id: window.ClientDetailConfig.clientId },
            success: function(response) {
                if (response.status && response.data) {
                    // Escape template literal special characters to prevent syntax errors
                    function escapeTemplateLiteral(str) {
                        if (!str) return '';
                        return String(str)
                            .replace(/\\/g, '\\\\')
                            .replace(/`/g, '\\`')
                            .replace(/\$\{/g, '\\${');
                    }
                    
                    var html = '';
                    
                    $.each(response.data, function (k, v) {
                        // Determine icon based on activity type
                        var activityType = v.activity_type ?? 'note';
                        var subjectIcon;
                        var iconClass = '';
                        var subject = escapeTemplateLiteral(v.subject ?? '');
                        var subjectLower = subject.toLowerCase();
                        
                        if (activityType === 'sms') {
                            subjectIcon = '<i class="fas fa-sms"></i>';
                            iconClass = 'feed-icon-sms';
                        } else if (activityType === 'activity') {
                            subjectIcon = '<i class="fas fa-bolt"></i>';
                            iconClass = 'feed-icon-activity';
                        } else if (activityType === 'stage') {
                            subjectIcon = '<i class="fas fa-route"></i>';
                            iconClass = 'feed-icon-stage';
                        } else if (activityType === 'financial' || 
                                   subjectLower.includes('invoice') || 
                                   subjectLower.includes('receipt') || 
                                   subjectLower.includes('ledger') || 
                                   subjectLower.includes('payment') ||
                                   subjectLower.includes('account')) {
                            subjectIcon = '<i class="fas fa-dollar-sign"></i>';
                            iconClass = activityType === 'financial' ? 'feed-icon-accounting' : '';
                        } else if (subjectLower.includes('document')) {
                            subjectIcon = '<i class="fas fa-file-alt"></i>';
                        } else {
                            subjectIcon = '<i class="fas fa-sticky-note"></i>';
                        }
                        
                        var description = escapeTemplateLiteral(v.message ?? '');
                        var taskGroup = escapeTemplateLiteral(v.task_group ?? '');
                        var followupDate = escapeTemplateLiteral(v.followup_date ?? '');
                        var date = escapeTemplateLiteral(v.date ?? '');
                        var fullName = escapeTemplateLiteral(v.name ?? '');
                        var activityTypeClass = activityType ? 'activity-type-' + activityType : '';

                        // Build HTML parts to avoid nested template literal issues
                        var descriptionHtml = description !== '' ? '<p>' + description + '</p>' : '';
                        var taskGroupHtml = taskGroup !== '' ? '<p>' + taskGroup + '</p>' : '';
                        var followupDateHtml = followupDate !== '' ? '<p>' + followupDate + '</p>' : '';

                        var feedItemClass = activityType === 'stage' ? 'feed-item--stage' : 'feed-item--email';
                        var contentHtml;
                        if (activityType === 'stage') {
                            contentHtml = '<div class="feed-item-stage">' +
                                '<div class="feed-item-stage-header">' +
                                    '<span class="feed-item-staff">' + fullName + '</span>' +
                                    '<span class="feed-timestamp">' + date + '</span>' +
                                '</div>' +
                                '<div class="feed-item-stage-body">' + (v.message ? v.message : '') + '</div>' +
                            '</div>';
                        } else {
                            var subjectOnly = v.subject_without_staff_prefix === true;
                            var headline = subjectOnly ? subject : (fullName + ' ' + subject);
                            contentHtml = '<p><strong>' + headline + '</strong></p>' +
                                descriptionHtml +
                                taskGroupHtml +
                                followupDateHtml +
                                '<span class="feed-timestamp">' + date + '</span>';
                        }

                        var createdAtYmd = v.created_at_ymd || '';
                        html += '<li class="feed-item ' + feedItemClass + ' activity ' + activityTypeClass + '" id="activity_' + v.activity_id + '" data-created-at="' + createdAtYmd + '">' +
                            '<span class="feed-icon ' + iconClass + '">' +
                                subjectIcon +
                            '</span>' +
                            '<div class="feed-content">' + contentHtml + '</div>' +
                        '</li>';
                    });

                    $('.feed-list').html(html);
                    
                    // Adjust Activity Feed height after content update
                    if (typeof adjustActivityFeedHeight === 'function') {
                        adjustActivityFeedHeight();
                    }
                } else {
                    console.error('Failed to load activities:', response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading activities:', error);
            }
        });
    };
</script>

{{-- Newly added external JS placeholders for progressive migration --}}
<script src="{{ URL::asset('js/crm/clients/shared.js') }}" defer></script>
<script src="{{ URL::asset('js/crm/clients/detail.js') }}" defer></script>

{{-- Client detail utilities (must load before detail-main.js) --}}
<script src="{{ URL::asset('js/crm/clients/utils/flatpickr-helpers.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/utils/editor-helpers.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/utils/dom-helpers.js') }}"></script>
{{-- Phase 3 modules --}}
<script src="{{ URL::asset('js/crm/clients/modules/send-to-client.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/notes.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/matter-tasks.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/checklist.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/documents.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/accounts.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/invoices.js') }}"></script>
{{-- Bootstrap Datepicker required by Schedule Appointment modal (appointments.js) --}}
<script src="{{ URL::asset('js/bootstrap-datepicker.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/appointments.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/subtabs.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/ledger-dragdrop.js') }}"></script>
<script>
(function () {
    try {
        var t = localStorage.getItem('activeTab');
        if (t === 'workflow' || t === 'checklists') {
            localStorage.removeItem('activeTab');
        }
    } catch (e) {}
})();
</script>
{{-- Edit matter details modal (must load before detail-main.js) --}}
<script src="{{ URL::asset('js/crm/clients/matter-assignee-modal.js') }}?v={{ time() }}"></script>
{{-- Main detail page JavaScript --}}
<script src="{{ URL::asset('js/crm/clients/detail-main.js') }}?v={{ time() }}"></script>
<script>
(function ($) {
    $(function () {
        function applyDocStripVisibility(tabId) {
            var $strip = $('#cdn-doc-subtab-strip');
            if (!$strip.length) {
                return;
            }
            if (tabId === 'personaldocuments' || tabId === 'matterdocuments') {
                $strip.addClass('is-visible');
                $('.client-nav-button').removeClass('active');
                $('.cdn-demo-doc-nav').addClass('active');
                $strip.find('.cdn-doc-subtab-btn').removeClass('active');
                var $match = $strip.find('.cdn-doc-subtab-btn[data-doc-sub="' + tabId + '"]');
                if (!$match.length && tabId === 'matterdocuments') {
                    window.SidebarTabs.activateTab('personaldocuments');
                    return;
                }
                $match.addClass('active');
            } else {
                $strip.removeClass('is-visible');
            }
        }

        /* Wrap activateTab immediately — detail-main.js $(ready) runs first,
           so SidebarTabs is already initialised by the time we get here. */
        if (window.SidebarTabs && typeof window.SidebarTabs.activateTab === 'function') {
            var orig = window.SidebarTabs.activateTab;
            window.SidebarTabs.activateTab = function (tabId) {
                orig.call(window.SidebarTabs, tabId);
                applyDocStripVisibility(tabId);
            };
        }
        /* Apply correct strip state for whichever tab was loaded from the URL. */
        var initial = (window.ClientDetailConfig && window.ClientDetailConfig.activeTab) || '';
        applyDocStripVisibility(initial);
    });
    $(document).on('click', '.cdn-doc-subtab-btn', function () {
        var sub = $(this).data('doc-sub');
        if (!sub || !window.SidebarTabs) {
            return;
        }
        window.SidebarTabs.activateTab(sub);
    });
})(window.jQuery);
</script>

{{-- Sidebar Toggle JavaScript --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const collapsedToggle = document.getElementById('collapsed-toggle');
    const sidebar = document.getElementById('client-sidebar');
    const container = document.querySelector('.crm-container');
    if (! sidebar || ! container || ! collapsedToggle) {
        return;
    }
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed) {
        sidebar.classList.add('collapsed');
        container.classList.add('sidebar-collapsed');
    }
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.add('collapsed');
            container.classList.add('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', 'true');
        });
    }
    collapsedToggle.addEventListener('click', function() {
        sidebar.classList.remove('collapsed');
        container.classList.remove('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', 'false');
    });
});

// SMS Modal Functionality
// Declare global variables for SMS functionality
let smsClientId = null;
let smsClientName = null;

$('.send-sms-btn').on('click', function() {
    smsClientId = $(this).data('client-id');
    smsClientName = $(this).data('client-name');
    
    $('#sms_client_id').val(smsClientId);
    $('#smsModalLabel').text(`Send SMS to ${smsClientName}`);
    
    // Show loading state
    const phoneSelect = $('#sms_phone');
    phoneSelect.empty();
    phoneSelect.append('<option value="">Loading phone numbers...</option>');
    
    // Load client phone numbers
    $.ajax({
        url: '{{ URL::to("/clients/fetchClientContactNo") }}',
        type: 'POST',
        dataType: 'json',
        data: {
            _token: '{{ csrf_token() }}',
            client_id: smsClientId
        },
        success: function(response) {
            console.log('Phone numbers response:', response);
            phoneSelect.empty();
            phoneSelect.append('<option value="">Select phone number...</option>');
            
            // Parse response if it's a string (fallback; guard empty to prevent "Unexpected end of input")
            var data;
            try {
                data = (typeof response === 'string' && response.trim()) ? (typeof $.parseJSON === 'function' ? $.parseJSON(response) : JSON.parse(response)) : (response || {});
            } catch (e) {
                data = {};
            }
            
            if (data && data.clientContacts && data.clientContacts.length > 0) {
                data.clientContacts.forEach(function(contact) {
                    console.log('Processing contact:', contact);
                    // Handle missing fields gracefully
                    const countryCode = contact.country_code || '';
                    const phone = contact.phone || '';
                    const contactType = contact.contact_type || 'Phone';
                    const fullPhone = countryCode + phone;
                    const label = contactType + ': ' + fullPhone;
                    phoneSelect.append(`<option value="${fullPhone}">${label}</option>`);
                });
            } else {
                phoneSelect.append('<option value="">No phone numbers found</option>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Failed to fetch phone numbers:', error);
            phoneSelect.empty();
            phoneSelect.append('<option value="">Error loading phone numbers</option>');
            iziToast.error({
                title: 'Error',
                message: 'Failed to load phone numbers. Please try again.',
                position: 'topRight'
            });
        }
    });
    
    // Load SMS templates
    $.ajax({
        url: '{{ route("adminconsole.features.sms.templates.active") }}',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            const templateSelect = $('#sms_template');
            templateSelect.empty();
            templateSelect.append('<option value="">Type your own message or select a template...</option>');
            
            if (response.success && response.data && response.data.length > 0) {
                response.data.forEach(function(template) {
                    templateSelect.append(`<option value="${template.id}" data-message="${template.message}">${template.title}</option>`);
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('Failed to fetch SMS templates:', error);
            const templateSelect = $('#sms_template');
            templateSelect.empty();
            templateSelect.append('<option value="">Error loading templates</option>');
            iziToast.error({
                title: 'Error',
                message: 'Failed to load SMS templates. Please try again.',
                position: 'topRight'
            });
        }
    });
    
    // Reset form
    $('#sms_message').val('');
    $('#sms_char_count').text('0');
    $('#sms_parts_count').text('1');
    
    $('#sendSmsModal').modal('show');
});

// Template selection
$('#sms_template').on('change', function() {
    const selectedOption = $(this).find('option:selected');
    const message = selectedOption.data('message');
    if (message && smsClientName) {
        // Replace placeholders with actual client data
        let processedMessage = message;
        
        // Basic client variables
        processedMessage = processedMessage.replace(/\{first_name\}/g, smsClientName.split(' ')[0] || '');
        processedMessage = processedMessage.replace(/\{last_name\}/g, smsClientName.split(' ').slice(1).join(' ') || '');
        processedMessage = processedMessage.replace(/\{client_name\}/g, smsClientName);
        processedMessage = processedMessage.replace(/\{full_name\}/g, smsClientName);
        
        // New variables from ClientDetailConfig
        processedMessage = processedMessage.replace(/\{staff_name\}/g, window.ClientDetailConfig.staffName || '');
        processedMessage = processedMessage.replace(/\{matter_number\}/g, window.ClientDetailConfig.matterNumber || '');
        
        // Format office phone with country code
        const officePhone = window.ClientDetailConfig.officeCountryCode + window.ClientDetailConfig.officePhone;
        processedMessage = processedMessage.replace(/\{office_phone\}/g, officePhone || '');
        
        $('#sms_message').val(processedMessage).trigger('input');
    }
});

// Character counter
$('#sms_message').on('input', function() {
    const length = $(this).val().length;
    $('#sms_char_count').text(length);
    
    const parts = Math.ceil(length / 160) || 1;
    $('#sms_parts_count').text(parts);
});

// Form submission
$('#sendSmsForm').on('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = $('#sendSmsBtn');
    const originalText = submitBtn.html();
    
    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');
    
    const formData = {
        _token: '{{ csrf_token() }}',
        client_id: $('#sms_client_id').val(),
        phone: $('#sms_phone').val(),
        message: $('#sms_message').val()
    };
    
    $.ajax({
        url: '{{ route("adminconsole.features.sms.send") }}',
        type: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                iziToast.success({
                    title: 'Success',
                    message: 'SMS sent successfully!',
                    position: 'topRight'
                });
                $('#sendSmsModal').modal('hide');
                
                // Reload activity feed if exists
                if (typeof loadActivities === 'function') {
                    loadActivities();
                }
            } else {
                iziToast.error({
                    title: 'Error',
                    message: response.message || 'Failed to send SMS',
                    position: 'topRight'
                });
            }
        },
        error: function(xhr) {
            let errorMessage = 'An error occurred while sending SMS';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
            }
            iziToast.error({
                title: 'Error',
                message: errorMessage,
                position: 'topRight'
            });
        },
        complete: function() {
            submitBtn.prop('disabled', false).html(originalText);
        }
    });
});
</script>

@if($showGoogleReviewReminderModal ?? false)
<script>
$(function () {
    var $modal = $('#googleReviewReminderModal');
    if (!$modal.length) { return; }
    var clientId = parseInt($('.crm-container').data('client-id'), 10);
    if (!clientId || clientId < 1) { return; }
    var token = $('meta[name="csrf-token"]').attr('content');
    var postUrl = @json(route('clients.google-review-reminder'));
    var postSmsUrl = @json(route('clients.google-review-reminder.sms'));
    var submitting = false;

    function grrAllControls() {
        return $modal.find('.js-google-review-reminder, .js-google-review-send-sms');
    }
    var reminderDelayMs = @json((int) config('crm.google_review_reminder_modal_delay_ms', 60000));
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        reminderDelayMs = Math.min(reminderDelayMs, 400);
    }
    var grrShowTimer = setTimeout(function () {
        $modal.modal('show');
    }, reminderDelayMs);
    $(window).on('pagehide.grr', function () {
        clearTimeout(grrShowTimer);
    });
    $modal.on('shown.bs.modal', function () {
        var $sms = $modal.find('.grr-modal-footer .js-google-review-send-sms');
        var $first = $sms.length ? $sms : $modal.find('.grr-modal-footer .js-google-review-reminder').first();
        if ($first.length) {
            $first.trigger('focus');
        }
    });
    $modal.off('click.grr', '.js-google-review-reminder').on('click.grr', '.js-google-review-reminder', function () {
        if (submitting) { return; }
        var action = $(this).data('action');
        var $btns = grrAllControls();
        submitting = true;
        $btns.prop('disabled', true);
        $.ajax({
            url: postUrl,
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json'
            },
            data: { client_id: clientId, action: action, _token: token },
            success: function (res) {
                if (res && res.ok) {
                    $modal.modal('hide');
                    if (typeof iziToast !== 'undefined') {
                        var toastMessages = {
                            snooze_one_day: 'Reminder snoozed until tomorrow',
                            snooze: 'Reminder snoozed for 1 week',
                            not_interested: 'Noted — won\'t be reminded again',
                            review_received: 'Great! Review marked as received'
                        };
                        iziToast.success({ message: toastMessages[action] || 'Saved', position: 'topRight' });
                    }
                } else {
                    if (typeof iziToast !== 'undefined') {
                        iziToast.error({ message: (res && res.message) ? res.message : 'Could not save', position: 'topRight' });
                    }
                }
            },
            error: function (xhr) {
                var msg = 'Could not save';
                var j = xhr.responseJSON;
                if (j) {
                    if (j.message) { msg = j.message; }
                    if (j.errors && typeof j.errors === 'object') {
                        var keys = Object.keys(j.errors);
                        if (keys.length && j.errors[keys[0]] && j.errors[keys[0]][0]) {
                            msg = j.errors[keys[0]][0];
                        }
                    }
                }
                if (typeof iziToast !== 'undefined') {
                    iziToast.error({ message: msg, position: 'topRight' });
                }
            },
            complete: function () {
                submitting = false;
                $btns.prop('disabled', false);
            }
        });
    });

    $modal.off('click.grr-sms', '.js-google-review-send-sms').on('click.grr-sms', '.js-google-review-send-sms', function () {
        if (submitting) { return; }
        var $btns = grrAllControls();
        submitting = true;
        $btns.prop('disabled', true);
        $.ajax({
            url: postSmsUrl,
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json'
            },
            data: { client_id: clientId, _token: token },
            success: function (res) {
                if (res && res.ok) {
                    if (typeof iziToast !== 'undefined') {
                        iziToast.success({ message: res.message || 'SMS sent successfully', position: 'topRight' });
                    }
                    if (typeof loadActivities === 'function') {
                        loadActivities();
                    }
                } else {
                    if (typeof iziToast !== 'undefined') {
                        iziToast.error({ message: (res && res.message) ? res.message : 'SMS failed', position: 'topRight' });
                    }
                }
            },
            error: function (xhr) {
                var msg = 'SMS failed';
                var j = xhr.responseJSON;
                if (j && j.message) { msg = j.message; }
                if (typeof iziToast !== 'undefined') {
                    iziToast.error({ message: msg, position: 'topRight' });
                }
            },
            complete: function () {
                submitting = false;
                grrAllControls().prop('disabled', false);
            }
        });
    });
});
</script>
@endif

<script>
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var matterBtn = document.getElementById('cdn-focus-matter-select');
        if (matterBtn) {
            matterBtn.addEventListener('click', function () {
                var $el = window.jQuery && window.jQuery('#sel_matter_id_client_detail');
                if ($el && $el.length) {
                    if ($el.hasClass('select2-hidden-accessible')) {
                        $el.select2('open');
                    } else {
                        $el.trigger('focus');
                    }
                }
            });
        }
        /* Update Stage opens #cdn-update-stage-modal (data-bs-toggle); no JS needed */
    });
})();
</script>
@endpush
