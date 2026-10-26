<?php

use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CallController;
use App\Http\Controllers\Api\V1\CommissionController;
use App\Http\Controllers\Api\V1\ContractController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DockerController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\NicheController;
use App\Http\Controllers\Api\V1\ObjectionController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PlaybookController;
use App\Http\Controllers\Api\V1\ProposalController;
use App\Http\Controllers\Api\V1\ReminderController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ScriptController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class)->name('health');

    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('auth.login');
        Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('throttle:20,1')->name('auth.refresh');
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('throttle:20,1')->name('auth.logout');

        Route::middleware('jwt')->group(function () {
            Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
        });
    });

    Route::middleware('jwt')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
        Route::get('/dashboard/losses', [DashboardController::class, 'losses'])->name('dashboard.losses');

        Route::get('/leads/kanban', [LeadController::class, 'kanban'])->name('leads.kanban');
        Route::apiResource('leads', LeadController::class);
        Route::patch('/leads/{lead}/stage', [LeadController::class, 'updateStage'])->name('leads.stage');
        Route::get('/leads/{lead}/interactions', [LeadController::class, 'interactions'])->name('leads.interactions.index');
        Route::post('/leads/{lead}/interactions', [LeadController::class, 'storeInteraction'])->name('leads.interactions.store');
        Route::get('/leads/{lead}/attachments', [LeadController::class, 'attachments'])->name('leads.attachments.index');
        Route::post('/leads/{lead}/attachments', [LeadController::class, 'uploadAttachment'])->name('leads.attachments.store');
        Route::get('/leads/{lead}/attachments/{attachment}/download', [LeadController::class, 'downloadAttachment'])->name('leads.attachments.download');
        Route::delete('/leads/{lead}/attachments/{attachment}', [LeadController::class, 'destroyAttachment'])->name('leads.attachments.destroy');
        Route::post('/leads/{lead}/proposals', [ProposalController::class, 'store'])->name('leads.proposals.store');
        Route::post('/leads/{lead}/contracts', [ContractController::class, 'store'])->name('leads.contracts.store');
        Route::post('/leads/{lead}/payments/installments', [PaymentController::class, 'createInstallments'])->name('leads.payments.installments');

        Route::get('/reminders', [ReminderController::class, 'index'])->name('reminders.index');
        Route::post('/reminders', [ReminderController::class, 'store'])->name('reminders.store');
        Route::post('/reminders/automation/run', [ReminderController::class, 'runAutomation'])->name('reminders.automation.run');
        Route::patch('/reminders/{reminder}', [ReminderController::class, 'update'])->name('reminders.update');
        Route::post('/reminders/{reminder}/complete', [ReminderController::class, 'complete'])->name('reminders.complete');
        Route::post('/reminders/{reminder}/snooze', [ReminderController::class, 'snooze'])->name('reminders.snooze');
        Route::post('/reminders/{reminder}/cancel', [ReminderController::class, 'cancel'])->name('reminders.cancel');
        Route::post('/reminders/{reminder}/reassign', [ReminderController::class, 'reassign'])->name('reminders.reassign');

        Route::get('/calls', [CallController::class, 'index'])->name('calls.index');
        Route::post('/calls', [CallController::class, 'store'])->name('calls.store');
        Route::get('/calls/{call}', [CallController::class, 'show'])->name('calls.show');
        Route::put('/calls/{call}', [CallController::class, 'update'])->name('calls.update');
        Route::post('/calls/{call}/analyze', [CallController::class, 'analyze'])->name('calls.analyze');
        Route::post('/calls/{call}/next-steps', [CallController::class, 'createNextSteps'])->name('calls.next_steps');

        Route::get('/proposals', [ProposalController::class, 'index'])->name('proposals.index');
        Route::get('/proposals/{proposal}', [ProposalController::class, 'show'])->name('proposals.show');
        Route::put('/proposals/{proposal}', [ProposalController::class, 'update'])->name('proposals.update');
        Route::patch('/proposals/{proposal}/status', [ProposalController::class, 'updateStatus'])->name('proposals.status');
        Route::get('/proposals/{proposal}/pdf', [ProposalController::class, 'pdf'])->name('proposals.pdf');
        Route::post('/proposals/{proposal}/contract', [ContractController::class, 'fromProposal'])->name('proposals.contract');
        Route::delete('/proposals/{proposal}', [ProposalController::class, 'destroy'])->name('proposals.destroy');

        Route::get('/contracts', [ContractController::class, 'index'])->name('contracts.index');
        Route::get('/contracts/{contract}', [ContractController::class, 'show'])->name('contracts.show');
        Route::patch('/contracts/{contract}/status', [ContractController::class, 'updateStatus'])->name('contracts.status');
        Route::get('/contracts/{contract}/pdf', [ContractController::class, 'pdf'])->name('contracts.pdf');

        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::patch('/payments/{payment}/status', [PaymentController::class, 'updateStatus'])->name('payments.status');
        Route::delete('/payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');

        Route::get('/commissions', [CommissionController::class, 'index'])->name('commissions.index');
        Route::post('/commissions/calculate', [CommissionController::class, 'calculate'])->name('commissions.calculate');
        Route::post('/commissions/{commission}/approve', [CommissionController::class, 'approve'])->name('commissions.approve');
        Route::post('/commissions/{commission}/pay', [CommissionController::class, 'markPaid'])->name('commissions.pay');
        Route::post('/commissions/{commission}/reverse', [CommissionController::class, 'reverse'])->name('commissions.reverse');
        Route::get('/commission-rules', [CommissionController::class, 'rules'])->name('commissions.rules.index');
        Route::post('/commission-rules', [CommissionController::class, 'storeRule'])->name('commissions.rules.store');
        Route::put('/commission-rules/{rule}', [CommissionController::class, 'updateRule'])->name('commissions.rules.update');
        Route::delete('/commission-rules/{rule}', [CommissionController::class, 'destroyRule'])->name('commissions.rules.destroy');

        Route::get('/scripts', [ScriptController::class, 'index'])->name('scripts.index');
        Route::post('/scripts', [ScriptController::class, 'store'])->name('scripts.store');
        Route::get('/scripts/{script}', [ScriptController::class, 'show'])->name('scripts.show');
        Route::put('/scripts/{script}', [ScriptController::class, 'update'])->name('scripts.update');
        Route::delete('/scripts/{script}', [ScriptController::class, 'destroy'])->name('scripts.destroy');

        Route::get('/playbooks', [PlaybookController::class, 'index'])->name('playbooks.index');
        Route::post('/playbooks', [PlaybookController::class, 'store'])->name('playbooks.store');
        Route::put('/playbooks/{playbook}', [PlaybookController::class, 'update'])->name('playbooks.update');

        Route::get('/objections', [ObjectionController::class, 'index'])->name('objections.index');
        Route::post('/objections', [ObjectionController::class, 'store'])->name('objections.store');
        Route::put('/objections/{objection}', [ObjectionController::class, 'update'])->name('objections.update');
        Route::delete('/objections/{objection}', [ObjectionController::class, 'destroy'])->name('objections.destroy');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/options', [UserController::class, 'options'])->name('users.options');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/me', [UserController::class, 'updateMe'])->name('users.me');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
        Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
        Route::put('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');

        Route::get('/niches', [NicheController::class, 'index'])->name('niches.index');
        Route::post('/niches', [NicheController::class, 'store'])->name('niches.store');
        Route::put('/niches/{niche}', [NicheController::class, 'update'])->name('niches.update');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export/{type}', [ReportController::class, 'export'])->name('reports.export');
        Route::post('/reports/weekly', [ReportController::class, 'weekly'])->name('reports.weekly');
        Route::get('/reports/runs', [ReportController::class, 'runs'])->name('reports.runs');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.index');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings/integrations/{provider}', [SettingsController::class, 'updateIntegration'])->name('settings.integrations.update');
        Route::post('/settings/integrations/{provider}/test', [SettingsController::class, 'testIntegration'])->name('settings.integrations.test');

        Route::middleware('local-admin')->prefix('system/docker')->group(function () {
            Route::get('/', [DockerController::class, 'status'])->name('system.docker.status');
            Route::post('/start', [DockerController::class, 'start'])->middleware('throttle:docker-control')->name('system.docker.start');
        });
    });
});
