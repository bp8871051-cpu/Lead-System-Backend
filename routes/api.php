<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\ScrapingController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\ImportExportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SettingController;

/*
|--------------------------------------------------------------------------
| Enterprise Lead System REST API Routes
|--------------------------------------------------------------------------
*/

// Public Auth Routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login'])->name('login');
});

// Protected Routes (Sanctum)
Route::middleware('auth:sanctum')->group(function () {

    // User & Profile
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::put('password', [AuthController::class, 'changePassword']);
    });

    // Company Profile
    Route::get('company-profile', [CompanyProfileController::class, 'show']);
    Route::put('company-profile', [CompanyProfileController::class, 'update']);

    // Executive Dashboard Stats
    Route::get('dashboard/stats', [DashboardController::class, 'stats']);

    // Leads & Trash Management
    Route::get('leads/trash', [LeadController::class, 'trash']);
    Route::post('leads/bulk-delete', [LeadController::class, 'bulkDelete']);
    Route::post('leads/bulk-enrich-emails', [LeadController::class, 'bulkEnrichEmails']);
    Route::post('leads/{id}/enrich-email', [LeadController::class, 'enrichEmail']);
    Route::post('leads/{id}/restore', [LeadController::class, 'restore']);
    Route::delete('leads/{id}/force', [LeadController::class, 'forceDelete']);
    Route::post('leads/{id}/notes', [LeadController::class, 'addNote']);
    Route::apiResource('leads', LeadController::class);

    // Apify Lead Generation & Scraping
    Route::post('scraping/start', [ScrapingController::class, 'start']);
    Route::get('scraping/jobs', [ScrapingController::class, 'index']);
    Route::get('scraping/jobs/{id}', [ScrapingController::class, 'show']);

    // AI Email Generation & Sending
    Route::post('emails/generate', [EmailController::class, 'generate']);
    Route::post('emails/render', [EmailController::class, 'render']);
    Route::post('emails/send', [EmailController::class, 'send']);
    Route::post('emails/bulk-send', [EmailController::class, 'bulkSend']);
    Route::get('emails/logs', [EmailController::class, 'logs']);

    // Outreach Campaigns
    Route::post('campaigns/{id}/start', [CampaignController::class, 'start']);
    Route::post('campaigns/{id}/pause', [CampaignController::class, 'pause']);
    Route::apiResource('campaigns', CampaignController::class);

    // Email Templates
    Route::apiResource('email-templates', EmailTemplateController::class);

    // Import / Export Wizard
    Route::post('import/preview', [ImportExportController::class, 'previewImport']);
    Route::post('import/process', [ImportExportController::class, 'processImport']);
    Route::get('export', [ImportExportController::class, 'export']);

    // Admin Settings
    Route::get('settings', [SettingController::class, 'show']);
    Route::put('settings', [SettingController::class, 'update']);
    Route::post('settings/test-email', [SettingController::class, 'testEmail']);
});
