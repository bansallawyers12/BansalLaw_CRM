<?php

namespace App\Http\Controllers\CRM\Clients;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use App\Models\Admin;
use App\Models\Note;
use App\Models\NoteAttachment;
use App\Models\Staff;
use App\Models\ActivitiesLog;
// use App\Models\OnlineForm; // REMOVED: OnlineForm model has been deleted
use App\Models\ClientMatter;
use App\Services\ClientNotesListService;
use App\Services\NoteAttachmentService;
use App\Support\NoteAttachmentHtml;
use App\Traits\LogsClientActivity;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
| * ClientNotesController
| * 
| * Handles all note-related operations including creating, updating,
| * viewing, deleting, and pinning notes.
| * 
| * Maps to: resources/views/Admin/clients/tabs/notes.blade.php
| */
use App\Http\Controllers\Concerns\EnsuresCrmRecordAccess;

class ClientNotesController extends Controller
{
    use LogsClientActivity, EnsuresCrmRecordAccess;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * Create or update a note
     * 
     * @param Request $request
     * @return json
     */
    public function createnote(Request $request)
    { 
        $response = []; // Initialize response array
        $isUpdate = isset($request->noteid) && $request->noteid != '';
        $changedFields = [];
        $oldNote = null;
        
        if($isUpdate){
            $obj = Note::find($request->noteid);
            if (!$obj) {
                return response()->json(['status' => false, 'message' => 'Note not found'], 404);
            }
            $this->ensureCrmRecordAccess((int) $obj->client_id);
            $oldNote = $obj->replicate(); // Keep a copy of old values for tracking changes
        }else{
            $this->ensureCrmRecordAccess((int) $request->client_id);
            $obj = new Note;
            // Title field may not exist in simple form, use default if not provided
            $obj->title = $request->title ?? '';
            $obj->matter_id = $request->matter_id;
        }

        // Track changes for updates
        if($isUpdate && $oldNote) {
            // Only track title changes if title is provided in request
            if(isset($request->title) && $oldNote->title !== $request->title) {
                $changedFields['Title'] = [
                    'old' => $oldNote->title,
                    'new' => $request->title
                ];
            }
            if($oldNote->description !== $request->description) {
                $changedFields['Description'] = [
                    'old' => $oldNote->description ? substr(strip_tags($oldNote->description), 0, 50) . '...' : '(empty)',
                    'new' => $request->description ? substr(strip_tags($request->description), 0, 50) . '...' : '(empty)'
                ];
            }
            if($oldNote->task_group !== $request->task_group) {
                $changedFields['Note Type'] = [
                    'old' => $oldNote->task_group ?? 'Others',
                    'new' => $request->task_group ?? 'Others'
                ];
            }
        }

        $obj->client_id = $request->client_id;
        $obj->user_id = Auth::user()->id;
        $obj->description = $request->description;
        $obj->mail_id = $request->mailid;
        $obj->type = $request->vtype;
        /*if(isset($request->note_deadline_checkbox) && $request->note_deadline_checkbox != ''){
            if($request->note_deadline_checkbox == 1){
                $obj->note_deadline = $request->note_deadline;
            } else {
                $obj->note_deadline = NULL;
            }
        } else {
            $obj->note_deadline = NULL;
        }*/
        $obj->mobile_number = $request->mobileNumber ?? null; // Handle case when mobileNumber is not provided
        $obj->task_group = $request->task_group;
        if ($request->has('spend_mins') || $request->has('spend_hours')) {
            $spendMins = trim((string) ($request->input('spend_mins') ?? $request->input('spend_hours')));
            $obj->spend_mins = ($spendMins !== '' && is_numeric($spendMins))
                ? max(0, (int) round((float) $spendMins))
                : null;
        }

        $uploadError = NoteAttachmentService::validateUploads($request->file('attachments'));
        if ($uploadError) {
            return response()->json(['status' => false, 'message' => $uploadError], 200, [], JSON_UNESCAPED_UNICODE);
        }
        
        // PostgreSQL NOT NULL constraints - must set these fields
        if(!$isUpdate) {
            $obj->pin = 0; // Default to not pinned
            $obj->is_action = 0; // Default to not an action
            $obj->status = '0'; // Default status
        }
        
        try {
            $saved = $obj->save();
            $matterDocumentRefresh = null;

            if ($saved) {
                $removeIds = $request->input('remove_attachment_ids', []);
                if (! is_array($removeIds)) {
                    $removeIds = $removeIds !== null && $removeIds !== '' ? [$removeIds] : [];
                }
                if ($isUpdate && $removeIds !== []) {
                    $toRemove = NoteAttachment::where('note_id', $obj->id)
                        ->whereIn('id', array_map('intval', $removeIds))
                        ->get();
                    foreach ($toRemove as $attachment) {
                        NoteAttachmentService::deleteAttachment($attachment);
                    }
                }
                $matterDocumentRefresh = NoteAttachmentService::storeForNote($obj, $request->file('attachments'));
            }
            
            if($saved){
                // BUGFIX #5: Log activity for BOTH client and lead notes (not just client)
                if($request->vtype == 'client' || $request->vtype == 'lead'){
                    try {
                        // Get note type for enhanced subject line with proper formatting
                        $taskGroup = $request->task_group ?? 'General';
                        $noteTypeFormatted = ucfirst(strtolower($taskGroup));
                        
                        // Determine the entity ID to use (client_id for client, lead_id for lead)
                        $entityId = $request->vtype == 'client' ? $request->client_id : ($request->lead_id ?? $request->client_id);
                        
                        // Get matter reference (like TGV_1) - only for clients
                        // IMPORTANT: Only include matter reference if a specific matter was explicitly selected
                        $matterReference = '';
                        if($request->vtype == 'client') {
                            if(isset($request->matter_id) && $request->matter_id != "" && $request->matter_id != null) {
                                $matter = ClientMatter::find($request->matter_id);
                                if($matter && $matter->client_unique_matter_no) {
                                    $matterReference = $matter->client_unique_matter_no;
                                }
                            }
                            // DO NOT fetch latest matter automatically if no matter was selected
                            // This was causing confusion in Activity Feed for general client notes
                        }
                        
                        // Format subject line with action word
                        $entityType = $request->vtype == 'client' ? 'Client' : 'Lead';
                        $callNumber = trim((string) ($request->mobileNumber ?? ''));
                        $activityPhonePrefix = $callNumber !== ''
                            ? '<p class="activity-note-call-number"><strong>Number:</strong> ' . htmlspecialchars($callNumber, ENT_QUOTES, 'UTF-8') . '</p>'
                            : '';
                        if($isUpdate) {
                            // "updated Call Notes - TGV_1" or "updated Lead Call Notes"
                            $subjectLine = !empty($matterReference) 
                                ? "updated {$noteTypeFormatted} Notes - {$matterReference}"
                                : "updated {$entityType} {$noteTypeFormatted} Notes";
                                
                            // Enhanced update logging with change tracking
                            if(!empty($changedFields)) {
                                $this->logClientActivityWithChanges(
                                    $entityId,
                                    $subjectLine,
                                    $changedFields,
                                    'note',
                                    $activityPhonePrefix
                                );
                            } else {
                                // Log full description without truncation
                                $description = $activityPhonePrefix . $request->description;
                                $this->logClientActivity(
                                    $entityId,
                                    $subjectLine,
                                    $description,
                                    'note'
                                );
                            }
                        } else {
                            // "added Call Notes - TGV_1" or "added Lead Call Notes"
                            $subjectLine = !empty($matterReference) 
                                ? "added {$noteTypeFormatted} Notes - {$matterReference}"
                                : "added {$entityType} {$noteTypeFormatted} Notes";
                                
                            // Enhanced create logging - Log full description without truncation
                            $description = $activityPhonePrefix . $request->description;
                            $this->logClientActivity(
                                $entityId,
                                $subjectLine,
                                $description,
                                'note'
                            );
                        }
                    } catch (\Exception $logError) {
                        // Log the error but don't fail the note creation
                        Log::warning('Error logging note activity: ' . $logError->getMessage(), [
                            'note_id' => $obj->id ?? null,
                            'entity_id' => $entityId ?? null,
                            'vtype' => $request->vtype,
                            'trace' => $logError->getTraceAsString()
                        ]);
                    }

                    //Update date in client matter table
                    if( isset($request->matter_id) && $request->matter_id != ""){
                        try {
                            ClientMatter::touchRecentActivity((int) $request->matter_id, 'note');
                        } catch (\Exception $matterError) {
                            // Log but don't fail
                            Log::warning('Error updating matter timestamp: ' . $matterError->getMessage());
                        }
                    }
                }
                $response['status'] 	= 	true;
                $response['note_id'] = (int) $obj->id;
                if ($matterDocumentRefresh) {
                    $response['matter_document_refresh'] = $matterDocumentRefresh;
                }
                if($isUpdate){
                    $response['message']	=	'You have successfully updated Note';
                }else{
                    $response['message']	=	'You have successfully added Note';
                }
            } else {
                $response['status'] 	= 	false;
                $response['message']	=	'Please try again';
            }
        } catch (\Exception $e) {
            Log::error('Error saving note: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            $response['status'] = false;
            $response['message'] = 'Error saving note. Please try again.';
        }
        
        // Use proper Laravel response to prevent HTML error output
        return response()->json($response, 200, [], JSON_UNESCAPED_UNICODE);
	}

    /**
     * Update note datetime
     * 
     * @param Request $request
     * @return json
     */
    public function updateNoteDatetime(Request $request)
    {
        $note_id = $request->note_id;
        $datetime = $request->datetime;
        
        try {
            $carbonDateTime = Carbon::parse($datetime);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid date and time format'
            ]);
        }
        
        // Find note with specific conditions
        $note = Note::where('id', $note_id)
            ->whereNull('assigned_to')
            ->whereNull('unique_group_id')
            ->first();
        
        if($note){
            $this->ensureCrmRecordAccess((int) $note->client_id);
            $note->updated_at = $carbonDateTime; // Carbon instance
            $saved = $note->save();
            
            if($saved){
                $response['status'] = true;
                $response['message'] = 'Date and time updated successfully';
            } else {
                $response['status'] = false;
                $response['message'] = 'Failed to update date and time';
            }
        } else {
            $response['status'] = false;
            $response['message'] = 'Note not found or does not meet the criteria';
        }
        
        return response()->json($response);
    }

    /**
     * Get note details for editing
     * 
     * @param Request $request
     * @return json
     */
    public function getnotedetail(Request $request)
    {
		$note_id = $request->note_id;
		$note = Note::find($note_id);
		if (!$note) {
			return response()->json(['status' => false, 'message' => 'Note not found'], 404);
		}
		$this->ensureCrmRecordAccess((int) $note->client_id);
		$detailColumns = ['title', 'description', 'task_group', 'mobile_number', 'matter_id'];
		if (\Illuminate\Support\Facades\Schema::hasColumn('notes', 'spend_mins')) {
			$detailColumns[] = 'spend_mins';
		} elseif (\Illuminate\Support\Facades\Schema::hasColumn('notes', 'spend_hours')) {
			$detailColumns[] = 'spend_hours';
		}
		$data = Note::select($detailColumns)->where('id', $note_id)->first();
		if ($data && ! isset($data->spend_mins) && isset($data->spend_hours) && $data->spend_hours !== null && $data->spend_hours !== '') {
			$data->spend_mins = (int) round(((float) $data->spend_hours) * 60);
		}
        $attachments = NoteAttachment::where('note_id', $note_id)->orderBy('id')->get()->map(function (NoteAttachment $a) {
            return [
                'id' => $a->id,
                'name' => $a->original_name,
                'size' => NoteAttachmentHtml::humanSize((int) $a->file_size),
                'is_image' => $a->isImage(),
                'extension' => $a->extension,
                'download_url' => url('/note-attachments/' . $a->id . '/download'),
                'preview_url' => url('/note-attachments/' . $a->id . '/preview'),
            ];
        })->values();
		
		return response()->json([
			'status' => true,
			'data' => $data,
            'attachments' => $attachments,
		]);
	}

    /**
     * View note details
     * 
     * @param Request $request
     * @return json
     */
    public function viewnotedetail(Request $request)
    {
		$note_id = $request->note_id;
		$note = Note::find($note_id);
		if (!$note) {
			return response()->json(['status' => false, 'message' => 'Note not found'], 404);
		}
		$this->ensureCrmRecordAccess((int) $note->client_id);
		$data = Note::select('title','description','user_id','updated_at')->where('id',$note_id)->first();
		$admin = Admin::where('id', $data->user_id)->first();
		$s = substr(@$admin->first_name, 0, 1);
		$data->admin = $s;

		return response()->json([
			'status' => true,
			'data' => $data
		]);
	}

    /**
     * View application note details
     * 
     * @param Request $request
     * @return json
     */
    public function viewapplicationnote(Request $request)
    {
		$note_id = $request->note_id;
		$act = \App\Models\ActivitiesLog::where('activity_type','note')->where('use_for','matter')->where('id',$note_id)->first();
		if (!$act) {
			return response()->json(['status' => false, 'message' => 'Application note not found'], 404);
		}
		$this->ensureCrmRecordAccess((int) $act->client_id);
		$data = \App\Models\ActivitiesLog::select('subject as title','description','created_by as user_id','updated_at')->where('activity_type','note')->where('use_for','matter')->where('id',$note_id)->first();
		$admin = Admin::where('id', $data->user_id)->first();
		$s = substr(@$admin->first_name, 0, 1);
		$data->admin = $s;

		return response()->json([
			'status' => true,
			'data' => $data
		]);
	}

    /**
     * Get notes list for Notes Tab (redesigned).
     * Paginated + eager-loaded authors (avoids Staff N+1).
     * Default response remains HTML for legacy callers; pass format=json for metadata.
     */
    public function getnotes(Request $request, ClientNotesListService $notesList)
    {
        $clientId = (int) $request->input('clientid', $request->query('clientid'));
        $this->ensureCrmRecordAccess($clientId);

        $type = (string) ($request->input('type', $request->query('type', 'client')) ?: 'client');
        $offset = max(0, (int) $request->input('offset', $request->query('offset', 0)));
        $limit = $request->filled('per_page') || $request->filled('limit')
            ? (int) ($request->input('per_page', $request->input('limit')))
            : null;

        $actor = Auth::user();
        $actorStaff = $actor instanceof Staff ? $actor : null;
        $payload = $notesList->fetchAndRender($clientId, $type, $offset, $limit, $actorStaff);

        $wantsJson = $request->boolean('format_json')
            || $request->query('format') === 'json'
            || $request->wantsJson();

        if ($wantsJson) {
            return response()->json([
                'status' => true,
                'html' => $payload['html'],
                'has_more' => $payload['has_more'],
                'total' => $payload['total'],
                'next_offset' => $payload['next_offset'],
                'limit' => $payload['limit'],
            ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        return response($payload['html'], 200)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('X-Notes-Has-More', $payload['has_more'] ? '1' : '0')
            ->header('X-Notes-Total', (string) $payload['total'])
            ->header('X-Notes-Next-Offset', (string) $payload['next_offset']);
    }

    /**
     * Delete a note
     * 
     * @param Request $request
     * @return json
     */
    public function deletenote(Request $request)
    {
        if (!$request->isMethod('post')) {
            return response()->json(['status' => false, 'message' => 'Method not allowed'], 405);
        }

		$note_id = $request->note_id;
		$note = Note::find($note_id);
		if (!$note) {
			return response()->json(['status' => false, 'message' => 'Note not found'], 404);
		}
		$this->ensureCrmRecordAccess((int) $note->client_id);
		$data = Note::select('client_id','title','description','task_group','type','mobile_number')->where('id',$note_id)->first();
        NoteAttachmentService::deleteAllForNote($note);
		$res = DB::table('notes')->where('id', @$note_id)->delete();
		if ($res) {
			if ($data->type == 'client') {
				// Enhanced delete logging with note type
				$taskGroup = $data->task_group ?? 'General';
				$noteTypeFormatted = ucfirst(strtolower($taskGroup));
				
				$mnDel = trim((string) ($data->mobile_number ?? ''));
				$deletePhonePrefix = $mnDel !== ''
					? '<p class="activity-note-call-number"><strong>Number:</strong> ' . htmlspecialchars($mnDel, ENT_QUOTES, 'UTF-8') . '</p>'
					: '';
				$description = $deletePhonePrefix . $data->description;
				
				// Format as "deleted Call Notes"
				$this->logClientActivity(
					$data->client_id,
					"deleted {$noteTypeFormatted} Notes",
					$description,
					'note'
				);
			}
			return response()->json([
				'status' => true,
				'data' => $data
			]);
		}

		return response()->json([
			'status' => false,
			'message' => 'Please try again'
		]);
	}

    /**
     * Pin or unpin a note
     * 
     * @param Request $request
     * @return json
     */
    public function pinnote(Request $request)
    {
        if (!$request->isMethod('post')) {
            return response()->json(['status' => false, 'message' => 'Method not allowed'], 405);
        }

		$noteId = $request->input('note_id');

		$note = Note::find($noteId);
		if (!$note) {
			return response()->json(['status' => false, 'message' => 'Record not found'], 404);
		}
		$this->ensureCrmRecordAccess((int) $note->client_id);

		$currentPin = (int) $note->pin;
		$newPin = $currentPin ? 0 : 1;

		$note->pin = $newPin;
		$note->save();

		return response()->json([
			'status' => true,
			'message' => $newPin ? 'Note pinned successfully' : 'Note unpinned successfully'
		]);
	}

    /**
     * Download a note attachment (auth + CRM access required).
     */
    public function downloadAttachment($id)
    {
        return $this->streamAttachment($id, false);
    }

    /**
     * Preview images / PDFs inline; other types download.
     */
    public function previewAttachment($id)
    {
        return $this->streamAttachment($id, true);
    }

    private function streamAttachment($id, bool $inline)
    {
        $attachment = NoteAttachment::with('note')->find($id);
        if (! $attachment) {
            abort(404, 'Attachment not found');
        }

        $clientId = (int) ($attachment->client_id ?: 0);
        if ($clientId <= 0 && $attachment->note) {
            $clientId = (int) $attachment->note->client_id;
        }
        if ($clientId <= 0) {
            abort(404, 'Attachment not found');
        }
        $this->ensureCrmRecordAccess($clientId);

        $abs = NoteAttachmentService::absolutePath($attachment);
        if (! $abs) {
            abort(404, 'Attachment file not found');
        }

        $name = $attachment->original_name ?: 'attachment';
        $mime = $attachment->mime_type ?: 'application/octet-stream';
        $disposition = ($inline && $attachment->isImage()) || ($inline && strtolower((string) $attachment->extension) === 'pdf')
            ? 'inline'
            : 'attachment';

        return response()->file($abs, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition . '; filename="' . str_replace('"', '', $name) . '"',
        ]);
    }
}

