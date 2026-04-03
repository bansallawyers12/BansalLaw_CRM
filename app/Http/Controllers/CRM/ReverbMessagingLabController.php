<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Standalone Reverb (Pusher-protocol) lab for staff to test real-time messaging.
 * Does not alter Client Portal or broadcasting behaviour.
 */
class ReverbMessagingLabController extends Controller
{
    public function index()
    {
        $user = Auth::guard('admin')->user();

        return view('crm.reverb-messaging-lab', [
            'currentUserId' => $user ? (int) $user->id : null,
            'staffName' => $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : '',
            'reverbAppKey' => config('broadcasting.connections.reverb.key') ?: config('broadcasting.connections.pusher.key'),
            'reverbHost' => config('broadcasting.connections.reverb.options.host', '127.0.0.1'),
            'reverbPort' => (int) config('broadcasting.connections.reverb.options.port', 8080),
            'reverbScheme' => config('broadcasting.connections.reverb.options.scheme', 'http'),
            'broadcastDriver' => config('broadcasting.default'),
        ]);
    }

    /**
     * Resolve portal client + matter ref to client_matters.id; enforce same access as private-matter channel.
     */
    public function resolveMatter(Request $request)
    {
        $request->validate([
            'client_matter_id' => 'nullable|integer|min:1',
            'portal_client_id' => 'nullable|integer|min:1',
            'matter_ref' => 'nullable|string|max:64',
        ]);

        $hasNumericMatter = $request->filled('client_matter_id');
        $hasPair = $request->filled('portal_client_id') && $request->filled('matter_ref');

        if (!$hasNumericMatter && !$hasPair) {
            return response()->json([
                'success' => false,
                'message' => 'Provide client_matter_id, or portal_client_id + matter_ref (e.g. BA_1).',
            ], 422);
        }

        $user = Auth::guard('admin')->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $matterRow = null;

        if ($hasNumericMatter) {
            $matterRow = DB::table('client_matters')
                ->where('id', (int) $request->input('client_matter_id'))
                ->first();
        } else {
            $portalClientId = (int) $request->input('portal_client_id');
            $matterRef = trim((string) $request->input('matter_ref'));

            $matterRow = DB::table('client_matters')
                ->where('client_id', $portalClientId)
                ->where('client_unique_matter_no', $matterRef)
                ->first();

            if (!$matterRow && ctype_digit($matterRef)) {
                $matterRow = DB::table('client_matters')
                    ->where('client_id', $portalClientId)
                    ->where('id', (int) $matterRef)
                    ->first();
            }
        }

        if (!$matterRow) {
            return response()->json([
                'success' => false,
                'message' => 'Matter not found for the details you entered.',
            ], 404);
        }

        $matterId = (int) $matterRow->id;

        if (!$this->staffCanAccessMatter($user, $matterId)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to access this matter.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'client_matter_id' => $matterId,
            'portal_client_id' => (int) $matterRow->client_id,
            'matter_label' => $matterRow->client_unique_matter_no ?? ('ID:' . $matterId),
        ]);
    }

    /**
     * Aligns with routes/channels.php private-matter.{matterId} authorization.
     */
    private function staffCanAccessMatter($user, int $matterId): bool
    {
        if ((int) ($user->role ?? 0) === 1) {
            return true;
        }

        return DB::table('client_matters')
            ->where('id', $matterId)
            ->where(function ($query) use ($user) {
                $uid = (int) $user->id;
                $query->where('client_id', $uid)
                    ->orWhere('sel_migration_agent', $uid)
                    ->orWhere('sel_person_responsible', $uid)
                    ->orWhere('sel_person_assisting', $uid);
            })
            ->exists();
    }
}
