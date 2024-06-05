<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TeamspaceController;
use App\Http\Controllers\AccountsController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ProjectPlaningController;
use App\Http\Controllers\TestCaseController;
use App\Http\Controllers\TraceabilityController;
use App\Http\Controllers\UatController;
use App\Http\Controllers\StatementOfWorkController;
use App\Http\Controllers\SoftwareUserDocController;
use App\Http\Controllers\AcceptanceController;
use App\Http\Controllers\TodoController;
use App\Http\Controllers\ProductOperationGuildController;
use App\Http\Controllers\MaintenanceDocController;
use App\Http\Controllers\SoftwareController;
use App\Http\Controllers\ApprovedController;
use App\Http\Controllers\ScoreController;
use App\Http\Controllers\SoftwareReqController;
use App\Http\Controllers\SoftwareDesignController;
use App\Http\Controllers\SoftwareComponentController;
use App\Http\Controllers\TestReportController;


//! Auth controller
Route::prefix('auth')->controller(AuthController::class)->group(function () {
    Route::post('/sign-in', 'login');
    Route::post('/sign-up', 'signUp');
    Route::post('/change-password', 'changePassword');
    Route::post('/forgot-password', 'checkAuth');
});

//! Accounts controller
Route::prefix('accounts')->controller(AccountsController::class)->group(function () {
    Route::get('/get-user', 'getUserInSystem');
    Route::get('/get-list-role-response', 'getListPosition');
    Route::get('/get-accounts-list', 'accountsList');
    Route::post('/add-accounts', 'addAccounts');
    Route::put('/edit-accounts', 'editAccounts');
    Route::delete('/delete-accounts', 'deleteAccounts');

    Route::get('/list-position-id', 'listPositionID');
    Route::get('/list-team-id', 'listTeamID');

    // Route::post('/template-accounts', 'templateAccounts');

    Route::post('/upload-document', 'uploadDocument');
    Route::post('/edit-document', 'editDocument');

    Route::post('/upload-picture', 'uploadPicture');
    Route::post('/edit-picture', 'editPicture');
});

//! setting controller
Route::prefix('setting')->controller(SettingController::class)->group(function () {
    Route::get('/get-product-type', 'getProductType');
    Route::post('/add-product-type', 'addProductType');
    Route::put('/edit-product-type', 'editProductType');
    Route::delete('/delete-product-type', 'deleteProductType');

    Route::get('/get-label', 'getLabel');
    Route::post('/add-label', 'addLabel');
    Route::put('/edit-label', 'editLabel');
    Route::delete('/delete-label', 'deleteLabel');

    Route::post('/add-teamspace-member', 'addMember');
    Route::put('/delete-teamspace-member', 'deleteMember');

    Route::post('/add-invite-user', 'addInviteUser');
    Route::put('/delete-invite-user', 'deleteInviteUser');

    Route::post('/add-new-status', 'addNewStatusGroup');
    Route::get('/get-status-group', 'getStatusGroup');
    Route::post('/new-status', 'newStatus');
    Route::post('/delete-status', 'deleteStatus');
});

//! Teamspace controller
Route::prefix('team-space')->controller(TeamspaceController::class)->group(function () {
    Route::get('/get', 'getTeamSpace');
    Route::post('/create', 'createTeamSpace');
    Route::put('/edit', 'editTeamSpace');
    Route::delete('/delete', 'deleteTeamSpace');
});


//! Statement Of Work controller
Route::prefix('statement-of-work')->controller(StatementOfWorkController::class)->group(function () {
    Route::get('/list', 'statementList');
    Route::post('/new', 'newStatement');
    Route::put('/edit', 'editStatement');
    Route::get('/get-sow-doc', 'GetSoWDoc');
    Route::post('/get-individual-doc', 'GetIndividualDoc');
    // Route::delete('/delete','deleteStatement');
    // Route::put('/update-scopes','updateScopes');
    // Route::put('/update-objectives','updateObjectives');

});

//! Project planing controller
Route::prefix('project-plan')->controller(ProjectPlaningController::class)->group(function () {
    Route::get('/get-planing', 'getProjectPlaning');
    Route::post('/add-planing', 'newProjectPlaning');
    Route::put('/edit-planing', 'editProjectPlaning');
    Route::delete('/delete-planing', 'deleteProjectPlaning');

    Route::post('/get-planing-req', 'requirementPlaning');
    Route::get('/get-software-req', 'getSoftwareRequirement');

    Route::get('/last-version', 'lastPlaningVersion');
    Route::get('/get-doc', 'GetPlaningDoc');
    Route::post('/get-individual-doc', 'GetIndividualDoc');

    Route::get('/project-planing-show-latest-version', 'ProjectPlaningShowLatestVersion');
    Route::post('/get-planing-detail', 'GetProjectPlaningDetails');

    Route::post('/updated-job-order', 'updatedJobOrder');
});

Route::prefix('project')->controller(StatementOfWorkController::class)->group(function () {
    Route::get('/projects-list', 'projectsList');
    Route::get('/projects-all', 'projectsAll');
    Route::post('/evidence-list', 'evidencesList');
    Route::post('/close-project', 'closeProject');
});

//! Task controller
Route::prefix('task')->controller(TaskController::class)->group(function () {
    Route::get('/get-project-issue', 'getProjectIssue');
    Route::post('/add-project-issue', 'addProjectIssue');
    Route::put('/edit-project-issue', 'editProjectIssue');
    Route::delete('/delete-project-issue', 'deleteProjectIssue');

    //March
    Route::get('/get-project-main-issue', 'getProjectMainIssue'); ##getmainissue only
    Route::put('/change-status-task', 'changeStatusTask'); ##change status task
    Route::get('/get-waiting-review', 'getWaitingReview'); ##get waiting for review for notification
    Route::put('/interviewer-approval', 'interviewerApproval'); ##interviewer approval
    Route::put('/comment-task', 'commentTask'); ##comment task
    Route::post('/add-template', 'addTemplate'); ##add template
    Route::get('/get-task-by-user', 'getTaskByUser'); ##get task by user

});

//! TestCases Controller
Route::prefix('test-cases')->controller(TestCaseController::class)->group(function () {
    Route::post('/combine-project-case', 'CombineCaseProject');
    Route::post('/new-add-testcase', 'createTestCase');
    Route::put('/edit-testcase', 'editTestCase');
    Route::delete('/delete-testcase', 'deleteTestCase');

    Route::post('/get-test-cases-detail', 'GetTestCaseDetails');
    // Route::get('/get-doc','GetTestCaseDoc');

    Route::get('/get-by-id', 'getTestcase');
    Route::get('/get-list', 'getListTestCase');
    Route::get('/get-info-by-id', 'getTestcaseInfoByID');
    Route::get('/get-repositories', 'getTestCaseRepositories');
});

//! TestReport Controller
Route::prefix('test-reports')->controller(TestReportController::class)->group(function () {
    Route::post('/add-report', 'createTestReport');
    Route::put('/edit-report', 'editTestReport');
    Route::delete('/delete-report', 'deleteTestReport');

    Route::get('/get-list', 'getListTestReport');
    Route::get('/get-by-id', 'getTestReportByID');
    Route::get('/get-info-by-id', 'getTestReportInfoByID');
});


//! Traceability controller
Route::prefix('traceability')->controller(TraceabilityController::class)->group(function () {
    Route::post('/new', 'addTraceability');
    Route::put('/edit-delete', 'editDeleteTraceability');
    Route::get('/list-trace', 'listTraceability');
    Route::get('/get-all-status', 'getTraceabilityStatus');
    Route::get('/get-details-by-id', 'getListReq');
    // Route::get('/get-recored','getTraceabilityRecored');

    Route::post('/get-individual-doc', 'GetIndividualDoc');
    Route::get('/get-doc', 'GetTraceabilityDoc');       //! new
});

//! SoftwareReq controller
Route::prefix('software-req')->controller(SoftwareReqController::class)->group(function () {
    Route::post('/new-specification', 'NewReqSpecification');
    Route::get('/get-specification', 'GetReqSpecification');
    Route::post('/get-srs-detials', 'GetDetails');
    Route::put('/edit-specification', 'EditReqSpecification');
});


//! Software controller
Route::prefix('software')->controller(SoftwareController::class)->group(function () {
    Route::post('/add-software', 'create');
    Route::post('/update-software', 'update');
});

//! Software Component controller
Route::prefix('software-component')->controller(SoftwareComponentController::class)->group(function () {
    Route::post('/add-software-component', 'create');
    Route::post('/update-software-component', 'update');
});


//! Approved Controller
Route::prefix('approved')->controller(ApprovedController::class)->group(function () {
    Route::get('/comment-verified', 'getCommet');
    Route::post('/add-comment-verified', 'addCommetVerified');
    Route::post('/send-verified', 'sendVerifiedStatement');
    Route::put('/verification', 'Verification');
    Route::get('/verification-list', 'VerificationList');
    Route::put('/validation', 'Validation');
    Route::post('/project-documentation', 'ProjectDocumentation');
    Route::post('/project-print', 'ProjectPrint');
    Route::post('/print-vv-document', 'printVandV');
    Route::post('/get-document-email', 'GetDocAtEmail');
});

//! production operation guild
//! maintenance doc
Route::prefix('maintenance')->controller(MaintenanceDocController::class)->group(function () {
    Route::post('/upload', 'UploadMaintenaceDoc');
    Route::get('/get-doc', 'GetMaintenanceDoc');
    Route::post('/get-individual-doc', 'GetIndividualDoc');
    Route::put('/edit-doc', 'EditMaintenanceDoc');
});

//? production operation guild
//! operation-guild
Route::prefix('operation-guild')->controller(ProductOperationGuildController::class)->group(function () {
    Route::post('/upload', 'UploadOperationGuild');
    Route::get('/get-doc', 'GetOperationGuild');
    Route::post('/get-individual-doc', 'GetIndividualDoc');
    Route::put('/edit-doc', 'EditOperationGuild');
});

//! Software User Document Controller
Route::prefix('software-user-doc')->controller(SoftwareUserDocController::class)->group(function () {
    Route::post('/upload', 'UploadSoftwareUserDoc');
    Route::get('/get-doc', 'GetSoftwareUserDoc');
    Route::post('/get-individual-doc', 'GetIndividualDoc');
    Route::put('/edit-doc', 'EditSoftwareUserDoc');
});


//! Score
Route::prefix('score')->controller(ScoreController::class)->group(function () {
    Route::get('/get-conclusion-all', 'getConclusionAll');
});

//! Todo controller
Route::prefix('todo')->controller(TodoController::class)->group(function () {
    //March
    Route::get('/get-todo', 'getToDo');
    Route::post('/add-todo', 'addToDoList');
    Route::put('/edit-todo', 'editToDo');
    Route::delete('/delete-todo', 'deleteToDo');
    Route::put('/change-status-todo', 'changeStatusToDo');
    Route::get('/get-waiting-review', 'getWaitingReview');
    Route::put('/inspection-approval', 'inpectionApproval');
    Route::get('/get-todo-by-user', 'getToDoByUser');
});

//! Acceptance controller
Route::prefix('acceptance-record')->controller(AcceptanceController::class)->group(function () {
    Route::post('/upload', 'uploadAcceptance');
    Route::post('/get-doc', 'GetAcceptance');
    Route::post('/get-individual-doc', 'GetIndividualDoc');
    Route::put('/edit-doc', 'EditAcceptance');
});

//! SoftwareDesign controller
Route::prefix('software-design')->controller(SoftwareDesignController::class)->group(function () {
    Route::post('/upload', 'UploadDesign');
    Route::post('/get-doc', 'GetSoftwareDesign');
    Route::post('/get-individual-doc', 'GetIndividualDoc');
    Route::put('/edit-doc', 'EditSoftwareDesign');
    Route::get('/get-list', 'GetAllSoftwareDesign');
});

//! UAT controller
Route::prefix('uat')->controller(UatController::class)->group(function () {
    Route::post('/add-uat', 'create');
    Route::get('/get-uat', 'getUAT');
    Route::put('/edit-uat', 'editUAT');
    Route::delete('/delete-uat', 'deleteUAT');
});
