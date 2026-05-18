<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;

// --- AUTHENTICATION CONTROLLERS ---
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\Auth\PasswordResetController;

// --- API CONTROLLERS ---
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\OffplanProjectController;
use App\Http\Controllers\Api\DeveloperController;
use App\Http\Controllers\Api\MarketIntelligenceController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SuperadminDashboardController;
use App\Http\Controllers\Api\ImpersonationController;
use App\Http\Controllers\Api\PropertyFinderListingController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\MarketAnalyticsController;
// --- MIDDLEWARE ---
use App\Http\Middleware\CheckCompanyStatus;
use Illuminate\Session\Middleware\StartSession;

// ==========================================
// 🌐 PUBLIC ROUTES
// ==========================================

// ── Legacy endpoints (kept for backward compatibility) ────────────────────
Route::post('/new_projects_search', [ProjectController::class, 'search']);
Route::get('/locations_search', [ProjectController::class, 'locations']);
Route::get('/dld-transactions', [\App\Http\Controllers\Api\DldProjectController::class, 'index']);

// ==========================================
// 🚀 API V1 — Off-Plan Projects & Market Intelligence
// ==========================================
Route::prefix('v1')->group(function () {

    // ── Projects ─────────────────────────────────────────────────────────
    Route::prefix('projects')->group(function () {
        Route::get('/',               [OffplanProjectController::class, 'search']);    // GET  /api/v1/projects
        Route::post('/search',        [OffplanProjectController::class, 'search']);    // POST /api/v1/projects/search
        Route::get('/filter-options', [OffplanProjectController::class, 'filterOptions']); // GET /api/v1/projects/filter-options
        Route::get('/locations',      [OffplanProjectController::class, 'locations']); // GET  /api/v1/projects/locations
        Route::get('/{id}',           [OffplanProjectController::class, 'show']);      // GET  /api/v1/projects/{id}
    });

    // ── Developers ───────────────────────────────────────────────────────
    Route::prefix('developers')->group(function () {
        Route::get('/',      [DeveloperController::class, 'index']); // GET /api/v1/developers
        Route::get('/{id}',  [DeveloperController::class, 'show']);  // GET /api/v1/developers/{id}
    });

    // ── Market Intelligence / Analytics ───────────────────────────────────
    Route::prefix('analytics')->group(function () {
        Route::get('/market-summary',         [MarketIntelligenceController::class, 'marketSummary']);
        Route::get('/distributions',          [MarketIntelligenceController::class, 'distributions']);
        Route::get('/top-areas',              [MarketIntelligenceController::class, 'topAreas']);
        Route::get('/hottest-projects',       [MarketIntelligenceController::class, 'hottestProjects']);
        Route::get('/active-rera-projects',   [MarketIntelligenceController::class, 'activeReraProjects']);
        Route::get('/registered-developers',  [MarketIntelligenceController::class, 'registeredDevelopers']);
        // Internal cache-clear utility (protect behind IP whitelist or admin auth in prod)
        Route::post('/cache/clear',           [MarketIntelligenceController::class, 'clearCache']);
    });

    // ── Market Page Analytics ─────────────────────────────────────────────
    Route::get('/market-analytics', [MarketAnalyticsController::class, 'getAnalytics']);
    Route::get('/market-analytics/export', [MarketAnalyticsController::class, 'export']);
});

Route::get('/load-test-companies', function () {
    return Cache::remember('load_test_results', 600, function () {
        $companies = \App\Models\Company::withCount('users')->latest()->limit(15)->get();
        return \App\Http\Resources\CompanyResource::collection($companies);
    });
});


// ==========================================
// 🔒 AUTHENTICATION & PLATFORM ROUTES
// ==========================================
Route::middleware(StartSession::class)->prefix('auth')->group(function () {
    
    // --- Public Auth ---
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('check-login-attempts');
    Route::get('/verify-email/{id}/{hash}', [VerificationController::class, 'verify'])->middleware(['signed'])->name('verification.verify');
    Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:5,1');
    Route::post('/reset-password', [PasswordResetController::class, 'reset']);

    // --- Protected Platform Routes ---
    // 🔥 THE FIX: Added CheckCompanyStatus here to instantly kick out suspended users!
    Route::middleware(['auth:sanctum', CheckCompanyStatus::class])->group(function () {
        
        // Session & Profile
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/email/verification-notification', [VerificationController::class, 'resend'])->middleware('throttle:6,1');

        // ■■■ SUPERADMIN: COMPANIES & DASHBOARD ■■■
        Route::middleware('permission:view stats')->get('/superadmin/stats', [SuperadminDashboardController::class, 'stats']);
        
        // 🔐 Role & Permission Management
        Route::middleware('permission:manage roles and permissions')->group(function () {
            Route::get('/permissions', [PermissionController::class, 'index']);
            Route::post('/roles', [PermissionController::class, 'storeRole']);
            Route::post('/roles/{role}/permissions', [PermissionController::class, 'updateRolePermissions']);
            Route::delete('/roles/{role}', [PermissionController::class, 'deleteRole']);
        });

        Route::middleware('permission:view all companies')->get('/companies', [CompanyController::class, 'index']);
        Route::middleware('permission:manage companies')->group(function () {
            Route::post('/companies', [CompanyController::class, 'store']);
            Route::patch('/companies/{company}/toggle-status', [CompanyController::class, 'toggleStatus']);
            Route::patch('/companies/{company}/plan', [CompanyController::class, 'changePlan']);
        });
        
        Route::middleware('permission:manage company profile')->group(function () {
            Route::get('/companies/{company}', [CompanyController::class, 'show']);
            Route::put('/companies/{company}', [CompanyController::class, 'update']);
            Route::get('/companies/{company}/users', [CompanyController::class, 'users']);
        });

        // ■■■ IMPERSONATION ■■■
        // Anyone currently impersonating should be able to leave, 
        // even if the user they are impersonating doesn't have the 'impersonate users' permission.
        Route::post('/impersonate/leave', [ImpersonationController::class, 'leave']);

        Route::middleware('permission:impersonate users')->group(function () {
            Route::post('/impersonate/{user}', [ImpersonationController::class, 'impersonate']);
        });

        // ■■■ INVITATIONS ■■■
        Route::middleware('permission:manage invitations')->group(function () {
            Route::get('/companies/{company}/invitations', [InvitationController::class, 'index']);
            Route::post('/invitations', [InvitationController::class, 'store']);
            Route::post('/invitations/{invitation}/send', [InvitationController::class, 'sendPending']);
            Route::post('/invitations/{invitation}/resend', [InvitationController::class, 'sendPending']);
            Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');
        });

        // ■■■ TEAM MANAGEMENT ■■■
        Route::middleware('permission:manage company users')->group(function () {
            Route::get('/users', [UserController::class, 'index']);
            Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
        });

        // ■■■ SYSTEM CONFIG ■■■
        Route::get('/system/plans', function () {
            return response()->json([
                'data' => config('saas.plans')
            ]);
        });


        Route::prefix('propertyfinder')->name('propertyfinder.')->group(function () {
            // ── Listing CRUD ──────────────────────────────────────────────────
            Route::apiResource('listings', PropertyFinderListingController::class);

            // ── Listing Lifecycle ─────────────────────────────────────────────
            // POST /listings/{id}/publish   — publish draft to PF
            Route::post('listings/{listing}/publish', [PropertyFinderListingController::class, 'publish'])
                ->name('listings.publish');

            // POST /listings/{id}/unpublish — take listing offline (accepts {reason})
            Route::post('listings/{listing}/unpublish', [PropertyFinderListingController::class, 'unpublish'])
                ->name('listings.unpublish');

            // ── Compliance ────────────────────────────────────────────────────
            // GET /compliances/{permitNumber} — fetch compliance data BEFORE creating listing
            Route::get('compliances/{permitNumber}', [PropertyFinderListingController::class, 'fetchCompliance'])
                ->name('compliances.fetch');

            // GET /listings/{id}/compliance — run PF API compliance check
            Route::get('listings/{listing}/compliance', [PropertyFinderListingController::class, 'compliance'])
                ->name('listings.compliance');

            // GET /listings/{id}/validate — run local pre-validation (no API call)
            Route::get('listings/{listing}/validate', [PropertyFinderListingController::class, 'validate'])
                ->name('listings.validate');

            // ── PF API Proxy ──────────────────────────────────────────────────
            // GET /agents   — fetch registered PF agents for this company
            Route::get('agents', [PropertyFinderListingController::class, 'agents'])
                ->name('agents.index');

            // GET /emirates — returns the 7 static emirates (no API auth needed)
            Route::get('emirates', [PropertyFinderListingController::class, 'emirates'])
                ->name('emirates');

            // GET /emirate-rules/{id} — returns dynamic UI rules for the emirate
            Route::get('emirate-rules/{id}', [PropertyFinderListingController::class, 'emirateRules'])
                ->name('emirates.rules');

            // GET /locations — fetch PF location hierarchy filtered by emirate_id
            // Step 1: pick an emirate from /emirates
            // Step 2: call /locations?emirate_id={id} to load areas in that emirate
            // Also supports: ?query=Dubai (search), ?parent_id=5 (sub-areas), ?ids=1,2,3
            Route::get('locations', [PropertyFinderListingController::class, 'locations'])
                ->name('locations.index');
        });
    });
});

Route::post('/webhooks/propertyfinder', [\App\Http\Controllers\Api\PropertyFinderWebhookController::class, 'handle'])
    ->name('webhooks.propertyfinder');

// ■■■ MEDIA UPLOADS ■■■
Route::middleware('auth:sanctum')->post('/media/upload', [\App\Http\Controllers\Api\MediaController::class, 'upload']);

/**
 * ==========================================
 * S3 PUBLIC TEST UPLOAD ENDPOINT
 * ==========================================
 * Allows you to upload a test image directly to verify S3 without authentication.
 */
Route::post('/test-s3-upload', function (\Illuminate\Http\Request $request) {
    try {
        if (!$request->hasFile('file')) {
            return response()->json(['error' => 'No file provided in the request. Use key "file".'], 400);
        }

        $file = $request->file('file');
        $filename = 'test-s3-' . time() . '.' . $file->getClientOriginalExtension();
        
        $uploadedPath = $file->storeAs('test-media', $filename, ['disk' => 's3', 'visibility' => 'public']);
        
        if (!$uploadedPath) {
            return response()->json(['error' => 'S3 upload failed silently.'], 500);
        }
        
        $url = \Illuminate\Support\Facades\Storage::disk('s3')->url($uploadedPath);
        
        return response()->json([
            'success' => true,
            'message' => 'Image successfully uploaded to S3!',
            's3_path' => $uploadedPath,
            'public_url' => $url,
            'instruction' => 'Click the public_url. If it returns AccessDenied, your AWS Bucket Policy is blocking public read access and needs to be updated.'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => 'Exception occurred during S3 upload. Check credentials.',
            'message' => $e->getMessage()
        ], 500);
    }
});
