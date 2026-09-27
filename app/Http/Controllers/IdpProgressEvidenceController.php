<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class IdpProgressEvidenceController extends Controller
{
    public function show(Request $request, string $evidence): BinaryFileResponse
    {
        $record = DB::table('idp_activity_update_evidences')
            ->join('idp_activity_updates', 'idp_activity_update_evidences.activity_update_id', '=', 'idp_activity_updates.id')
            ->join('idp_activities', 'idp_activity_updates.activity_id', '=', 'idp_activities.id')
            ->join('idp_items', 'idp_activities.idp_item_id', '=', 'idp_items.id')
            ->join('idps', 'idp_items.idp_id', '=', 'idps.id')
            ->where('idp_activity_update_evidences.public_id', $evidence)
            ->select(
                'idp_activity_update_evidences.storage_path',
                'idp_activity_update_evidences.original_name',
                'idp_activity_update_evidences.mime_type',
                'idp_activity_updates.status as update_status',
                'idps.user_id'
            )
            ->firstOrFail();

        $isOwner = (int) $record->user_id === (int) $request->user()->id;
        $isReviewer = DB::table('user_reviewer_steps')
            ->where('user_id', $record->user_id)
            ->where('reviewer_id', $request->user()->id)
            ->where('chain_type', 'idp')
            ->exists();
        $viewerRole = $request->user()->loadMissing('role')->role?->key;
        $canViewFacultyAnalytics = in_array($viewerRole, ['hr', 'dean', 'manager'], true);
        $canViewSubmittedEvidence = $record->update_status === 'submitted'
            && ($isReviewer || $canViewFacultyAnalytics);
        abort_unless($isOwner || $canViewSubmittedEvidence, 403);
        abort_unless($record->storage_path && Storage::disk('local')->exists($record->storage_path), 404);

        return response()->download(
            Storage::disk('local')->path($record->storage_path),
            $record->original_name ?: 'evidence',
            ['X-Content-Type-Options' => 'nosniff']
        );
    }
}
