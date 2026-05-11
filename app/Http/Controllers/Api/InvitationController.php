<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Invitation\SendInvitationAction;
use App\Actions\Invitation\DispatchPendingInvitationAction;
use App\Actions\Invitation\RevokeInvitationAction; // 🔥 NEW IMPORT
use App\Http\Controllers\Controller;
use App\Http\Requests\Invitation\StoreInvitationRequest;
use App\Models\Company;
use App\Models\Invitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class InvitationController extends Controller
{
    use AuthorizesRequests;

    public function index(Company $company): JsonResponse
    {
        $this->authorize('view', $company);

        $invites = Invitation::where('company_id', $company->id)
            ->whereNull('used_at')
            ->latest()
            ->get()
            ->map(function ($invite) {
                return [
                    'id' => $invite->id,
                    'email' => $invite->email,
                    'role' => $invite->role,
                    'status' => $invite->sent_at ? 'Sent' : 'Pending',
                    'expires_at' => $invite->expires_at->toIso8601String(),
                    
                    // 🔥 UI Hint: Show 'Revoke' button ONLY if expiry is in the future
                    'can_revoke' => $invite->expires_at->isFuture(),
                ];
            });

        return response()->json([
            'data' => $invites
        ]);
    }

    // 1. Naya Invitation banana (Invite Now ya Invite Later)
    public function store(StoreInvitationRequest $request, SendInvitationAction $action): JsonResponse
    {
        $invitation = $action->execute(
            $request->validated(),
            $request->resolveCompanyId()
        );

        $statusMessage = $invitation->sent_at
            ? 'Invitation queued successfully. Email will be delivered shortly.'
            : 'Invitation saved as pending. You can send it later.';

        return response()->json([
            'message' => $statusMessage,
            'invitation' => [
                'email' => $invitation->email,
                'role' => $invitation->role,
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'status' => $invitation->sent_at ? 'Sent' : 'Pending',
            ]
        ], 201);
    }

    // 2. CONSOLIDATED: Pending bhejna ho ya Expired ko Resend karna ho, sab yahan aayega
    public function sendPending(Invitation $invitation, DispatchPendingInvitationAction $action): JsonResponse
    {
        $this->authorize('update', $invitation);

        // N+1 Fix: Action ko call karne se pehle company load kar lo
        $invitation->loadMissing('company');

        try {
            $action->execute($invitation);
            return response()->json(['message' => 'Invitation sent successfully.']);
        } catch (\Exception $e) {
            // Error code 422 set kiya for business logic failures (like 'already used')
            return response()->json(['message' => $e->getMessage()], 422); 
        }
    }

    // 3. 🔥 NEW: The Revoke Endpoint
    public function destroy(Invitation $invitation, RevokeInvitationAction $action): JsonResponse
    {
        // 🔒 Policy Check: Ensure user has permission to delete/revoke
        $this->authorize('delete', $invitation);

        // State Check: Prevent revoking already used or already expired invites
        if ($invitation->expires_at->isPast() || $invitation->used_at !== null) {
            return response()->json([
                'message' => 'This invitation is already expired or used and cannot be revoked.'
            ], 422);
        }

        try {
            $action->execute($invitation);
            
            return response()->json([
                'message' => 'Invitation revoked successfully. The quota seat is now free.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}