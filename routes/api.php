<?php

use App\Http\Controllers\Api\V1\NoteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('me', fn (\Illuminate\Http\Request $r) => response()->json(['user' => $r->user()]))
        ->middleware('auth:sanctum');

    // موارد تابعة للمستأجر (عزل عبر tenant middleware + TenantScope)
    Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
        Route::apiResource('notes', NoteController::class);
    });

    Route::middleware(['auth:sanctum', 'tenant'])->prefix('billing')->group(function () {
        Route::get('subscription', [\App\Http\Controllers\Api\V1\BillingController::class, 'subscription']);
        Route::get('entitlements', [\App\Http\Controllers\Api\V1\BillingController::class, 'entitlements']);
        Route::get('usage', [\App\Http\Controllers\Api\V1\BillingController::class, 'usage']);
    });

    Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
        Route::get('clients', [\App\Http\Controllers\Api\V1\ClientController::class, 'index']);
        Route::post('clients', [\App\Http\Controllers\Api\V1\ClientController::class, 'store']);
        Route::get('clients/{client}', [\App\Http\Controllers\Api\V1\ClientController::class, 'show']);
        Route::put('clients/{client}', [\App\Http\Controllers\Api\V1\ClientController::class, 'update']);
        Route::delete('clients/{client}', [\App\Http\Controllers\Api\V1\ClientController::class, 'destroy']);
        Route::post('clients/{client}/restore', [\App\Http\Controllers\Api\V1\ClientController::class, 'restore']);

        // مستندات العميل (قرص خاص، تنزيل مُوثّق، لا روابط عامة)
        Route::get('clients/{client}/documents', [\App\Http\Controllers\Api\V1\ClientDocumentController::class, 'index']);
        Route::post('clients/{client}/documents', [\App\Http\Controllers\Api\V1\ClientDocumentController::class, 'store']);
        Route::get('clients/{client}/documents/{document}/download', [\App\Http\Controllers\Api\V1\ClientDocumentController::class, 'download']);
        Route::delete('clients/{client}/documents/{document}', [\App\Http\Controllers\Api\V1\ClientDocumentController::class, 'destroy']);

        // العلامات التجارية
        Route::get('clients/{client}/brands', [\App\Http\Controllers\Api\V1\BrandController::class, 'index']);
        Route::post('clients/{client}/brands', [\App\Http\Controllers\Api\V1\BrandController::class, 'store']);
        Route::get('clients/{client}/brands/{brand}', [\App\Http\Controllers\Api\V1\BrandController::class, 'show']);
        Route::put('clients/{client}/brands/{brand}', [\App\Http\Controllers\Api\V1\BrandController::class, 'update']);
        Route::delete('clients/{client}/brands/{brand}', [\App\Http\Controllers\Api\V1\BrandController::class, 'destroy']);

        // جهات اتصال العميل
        Route::get('clients/{client}/contacts', [\App\Http\Controllers\Api\V1\ClientContactController::class, 'index']);
        Route::post('clients/{client}/contacts', [\App\Http\Controllers\Api\V1\ClientContactController::class, 'store']);
        Route::put('clients/{client}/contacts/{contact}', [\App\Http\Controllers\Api\V1\ClientContactController::class, 'update']);
        Route::delete('clients/{client}/contacts/{contact}', [\App\Http\Controllers\Api\V1\ClientContactController::class, 'destroy']);

        // أعضاء بوابة العميل
        Route::get('clients/{client}/members', [\App\Http\Controllers\Api\V1\ClientMemberController::class, 'index']);
        Route::post('clients/{client}/members/invite', [\App\Http\Controllers\Api\V1\ClientMemberController::class, 'invite']);
        Route::patch('clients/{client}/members/{member}/status', [\App\Http\Controllers\Api\V1\ClientMemberController::class, 'updateStatus']);
        Route::patch('clients/{client}/members/{member}/role', [\App\Http\Controllers\Api\V1\ClientMemberController::class, 'updateRole']);

        // الحقول المخصّصة
        Route::get('custom-fields', [\App\Http\Controllers\Api\V1\CustomFieldController::class, 'index']);
        Route::post('custom-fields', [\App\Http\Controllers\Api\V1\CustomFieldController::class, 'store']);
        Route::put('clients/{client}/custom-fields/{definition}', [\App\Http\Controllers\Api\V1\CustomFieldController::class, 'setValue']);
    });

    // قبول دعوة بوابة العميل: المدعو يقبل برمزه الخام (خارج سياسة المؤسسة لأنه ليس عضوًا فيها)
    Route::middleware(['auth:sanctum'])->post('client-portal/accept', function (\Illuminate\Http\Request $r, \App\Domain\CRM\Actions\AcceptClientMemberInvitation $action) {
        $r->validate(['token' => 'required|string']);
        try {
            $member = $action->handle($r->input('token'), $r->user('sanctum'));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'error' => 'invalid_invitation'], 422);
        }
        return response()->json(['data' => ['member_id' => $member->id, 'status' => $member->status]], 201);
    });

    Route::middleware(['auth:sanctum'])->get('admin/plans', [\App\Http\Controllers\Api\V1\BillingController::class, 'plans']);

});
