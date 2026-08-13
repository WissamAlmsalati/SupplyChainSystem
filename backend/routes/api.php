<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BootstrapController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ComplianceController;
use App\Http\Controllers\DeploymentController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\AuxiliaryController;

// Bootstrap initial dataset
Route::get('/bootstrap', [BootstrapController::class, 'index']);

// Dynamic Settings CRUD
Route::post('/settings/{table}', [SettingController::class, 'store']);
Route::put('/settings/{table}/{id}', [SettingController::class, 'update']);
Route::delete('/settings/{table}/{id}', [SettingController::class, 'destroy']);

// Employee Management
Route::post('/employees', [EmployeeController::class, 'store']);
Route::put('/employees/{id}', [EmployeeController::class, 'update']);
Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);

// Documents & Certificates Compliance
Route::post('/documents', [ComplianceController::class, 'storeDocument']);
Route::delete('/documents/{id}', [ComplianceController::class, 'destroyDocument']);
Route::post('/certificates', [ComplianceController::class, 'storeCertificate']);
Route::delete('/certificates/{id}', [ComplianceController::class, 'destroyCertificate']);
Route::post('/position-required-certificates', [ComplianceController::class, 'storePositionRequiredCertificate']);
Route::delete('/position-required-certificates', [ComplianceController::class, 'destroyPositionRequiredCertificate']);

// Deployments & Operations & Leave
Route::post('/deployments', [DeploymentController::class, 'storeDeployment']);
Route::put('/deployments/{id}', [DeploymentController::class, 'updateDeployment']);
Route::delete('/deployments/{id}', [DeploymentController::class, 'destroyDeployment']);
Route::put('/b2b/{id}', [DeploymentController::class, 'updateB2B']);
Route::post('/leave', [DeploymentController::class, 'storeLeave']);
Route::delete('/leave/{id}', [DeploymentController::class, 'destroyLeave']);

// Appraisals / Performance Evaluations
Route::post('/evaluations', [EvaluationController::class, 'store']);
Route::delete('/evaluations/{id}', [EvaluationController::class, 'destroy']);

// Payroll
Route::get('/payroll/{period}', [PayrollController::class, 'getPeriod']);
Route::put('/payroll/{period}/{entryId}', [PayrollController::class, 'updateEntry']);
Route::post('/payroll/{period}/lock', [PayrollController::class, 'lockPeriod']);
Route::post('/allowances', [PayrollController::class, 'storeAllowance']);
Route::post('/bonuses', [PayrollController::class, 'storeBonus']);

// Equipment, Notes & Attachments
Route::post('/equipment', [AuxiliaryController::class, 'storeEquipment']);
Route::put('/equipment/{id}', [AuxiliaryController::class, 'updateEquipment']);
Route::delete('/equipment/{id}', [AuxiliaryController::class, 'destroyEquipment']);

Route::post('/notes', [AuxiliaryController::class, 'storeNote']);
Route::delete('/notes/{id}', [AuxiliaryController::class, 'destroyNote']);

Route::post('/attachments', [AuxiliaryController::class, 'storeAttachment']);
Route::delete('/attachments/{id}', [AuxiliaryController::class, 'destroyAttachment']);

// AI Diagnostics & Gemini Chat
Route::post('/ai/scan', [AuxiliaryController::class, 'aiScan']);
Route::post('/ai/recommendations/{id}/status', [AuxiliaryController::class, 'updateAiRecommendationStatus']);
Route::post('/ai/chat', [AuxiliaryController::class, 'aiChat']);
