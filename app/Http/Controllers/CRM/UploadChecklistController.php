<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;

use App\Models\Admin;
use App\Models\UploadChecklist;
use App\Services\CrmDurableStorage;

use Auth;

class UploadChecklistController extends Controller
{
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
     * All Vendors / Checklists.
     *
     * @return \Illuminate\Http\Response 
     */
    public function index(Request $request)
    {
        $query      = UploadChecklist::query(); 
        $totalData  = $query->count();
        $lists      = $query->sortable(['id' => 'desc'])->paginate(config('constants.limit'));
        // Dropdown: Active Matter list
        $matterIds = DB::table('matters')->select('id', 'title', 'nick_name')->where('status', '1')->orderBy('id', 'asc')->get();

        return view('crm.uploadchecklist.index', compact(['lists', 'totalData', 'matterIds'])); 	
    }

    /**
     * Store new matter checklist with durable S3 storage and collision-proof filename.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {		
        if ($request->isMethod('post')) 
        {
            $this->validate($request, [
                'name'       => 'required|max:255',
                'checklists' => 'nullable|file|max:51200', // max 50MB
            ]);

            $requestData    = $request->all();
            $obj            = new UploadChecklist; 
            $obj->matter_id = @$requestData['matter_id'];
            $obj->name      = @$requestData['name'];

            if ($request->hasFile('checklists') && $request->file('checklists')->isValid()) {
                $file      = $request->file('checklists');
                $ext       = strtolower((string) $file->getClientOriginalExtension());
                $origBase  = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $cleanBase = Str::slug(substr($origBase, 0, 50));
                $fileName  = time() . '_' . Str::uuid()->toString() . ($cleanBase !== '' ? '_' . $cleanBase : '') . ($ext !== '' ? '.' . $ext : '');

                $relativePath = 'checklists/' . $fileName;

                // 1. Upload to S3 + durable local mirror via CrmDurableStorage
                $durable = app(CrmDurableStorage::class);
                $durable->putUploadedFile($file, $relativePath);

                // 2. Also mirror to public/checklists for legacy webserver direct file access
                try {
                    $publicDir = public_path('checklists');
                    if (! is_dir($publicDir)) {
                        @mkdir($publicDir, 0755, true);
                    }
                    $sourcePath = $durable->durableLocalPath($relativePath);
                    if (is_file($sourcePath)) {
                        @copy($sourcePath, $publicDir . '/' . $fileName);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Checklist public mirror copy failed: ' . $e->getMessage());
                }

                $obj->file = $fileName;
            } else {
                $obj->file = null;
            }

            $saved = $obj->save();  
            
            if (! $saved) {
                return redirect()->back()->with('error', config('constants.server_error'));
            }

            if (! empty($requestData['matter_id'])) {
                return Redirect::to('/upload-checklists/matter/' . $requestData['matter_id'])->with('success', 'Record Added Successfully');
            }

            return Redirect::to('/upload-checklists')->with('success', 'Record Added Successfully');
        }	
    }

    /**
     * Show checklists for a specific matter.
     *
     * @param int $matterId
     * @return \Illuminate\Http\Response 
     */
    public function showByMatter($matterId)
    {
        $query      = UploadChecklist::where('matter_id', $matterId); 
        $totalData  = $query->count();
        $lists      = $query->sortable(['id' => 'desc'])->paginate(config('constants.limit'));
        
        $matter = DB::table('matters')->select('id', 'title', 'nick_name')->where('id', $matterId)->where('status', '1')->first();
        if (! $matter) {
            return redirect()->back()->with('error', 'Matter not found');
        }
        
        $matterIds = DB::table('matters')->select('id', 'title', 'nick_name')->where('status', '1')->orderBy('id', 'asc')->get();
        
        return view('crm.uploadchecklist.index', compact(['lists', 'totalData', 'matterIds', 'matter'])); 	
    }

    /**
     * Download or view a checklist file securely.
     *
     * @param int $id
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function download($id)
    {
        $checklist = UploadChecklist::find($id);
        if (! $checklist || empty($checklist->file)) {
            abort(404, 'Checklist file not found.');
        }

        $filename    = basename($checklist->file);
        $ext         = pathinfo($filename, PATHINFO_EXTENSION);
        $displayName = $checklist->name ? Str::slug($checklist->name) . ($ext ? '.' . $ext : '') : $filename;

        return $this->streamChecklistFile($filename, $displayName, false);
    }

    /**
     * Direct/fallback view for checklist files by filename.
     *
     * @param string $file
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function viewFile($file)
    {
        $filename = basename($file);
        if (empty($filename)) {
            abort(404, 'File not found.');
        }

        return $this->streamChecklistFile($filename, $filename, true);
    }

    /**
     * Stream checklist file from local mirror or S3.
     *
     * @param string $filename
     * @param string $displayName
     * @param bool $inline
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function streamChecklistFile(string $filename, string $displayName, bool $inline)
    {
        $relativePath = 'checklists/' . $filename;
        $durable = app(CrmDurableStorage::class);

        // 1. If static public file exists, serve it directly
        $publicPath = public_path('checklists/' . $filename);
        if (is_file($publicPath)) {
            return $inline
                ? response()->file($publicPath, ['Content-Disposition' => 'inline; filename="' . str_replace('"', '', $displayName) . '"'])
                : response()->download($publicPath, $displayName);
        }

        // 2. If storage/app local mirror exists, serve it
        $storageAppPath = $durable->durableLocalPath($relativePath);
        if (is_file($storageAppPath)) {
            return $inline
                ? response()->file($storageAppPath, ['Content-Disposition' => 'inline; filename="' . str_replace('"', '', $displayName) . '"'])
                : response()->download($storageAppPath, $displayName);
        }

        // 3. Fallback to CrmDurableStorage / S3 downloadResponse
        if ($durable->exists($relativePath)) {
            return $durable->downloadResponse($relativePath, $displayName, [], ! $inline);
        }

        abort(404, 'Checklist file not found on storage.');
    }

    /**
     * Delete checklist and clean up underlying storage files.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)
    {
        $checklist = UploadChecklist::find($id);
        if (! $checklist) {
            return redirect()->back()->with('error', 'Checklist not found.');
        }

        $matterId = $checklist->matter_id;
        if (! empty($checklist->file)) {
            $filename = basename($checklist->file);
            try {
                app(CrmDurableStorage::class)->delete('checklists/' . $filename);
                $publicFile = public_path('checklists/' . $filename);
                if (is_file($publicFile)) {
                    @unlink($publicFile);
                }
            } catch (\Throwable $e) {
                Log::warning('Checklist file deletion failed: ' . $e->getMessage());
            }
        }

        $checklist->delete();

        $redirectUrl = ! empty($matterId) ? '/upload-checklists/matter/' . $matterId : '/upload-checklists';
        return Redirect::to($redirectUrl)->with('success', 'Checklist deleted successfully.');
    }
}
