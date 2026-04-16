<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CRM\ClientsController;
use App\Http\Controllers\CRM\ClientAccountsController;
use App\Http\Controllers\CRM\Clients\ClientNotesController;
use App\Http\Controllers\CRM\Clients\ClientDocumentsController;
use App\Http\Controllers\CRM\ClientPersonalDetailsController;
use App\Http\Controllers\CRM\PhoneVerificationController;
use App\Http\Controllers\CRM\EmailVerificationController;
use App\Http\Controllers\CRM\CRMUtilityController;
use App\Http\Controllers\CRM\EmailUploadController;
use App\Http\Controllers\CRM\EmailLabelController;
use App\Http\Controllers\CRM\EmailLogAttachmentController;
use App\Http\Controllers\CRM\ClientMatterHubController;
use App\Http\Controllers\CRM\UploadChecklistController;
use App\Http\Controllers\CRM\SendGridSendersController;
use App\Http\Controllers\CRM\AccessGrantController;

/*
|--------------------------------------------------------------------------
| Client Management Routes
|--------------------------------------------------------------------------
|
| All routes for client CRUD operations, documents, verification, invoices,
| notes, agreements, and related functionality.
|
| Prefix: None (routes at root level)
| Middleware: auth:admin (inherited from web.php)
|
*/

/*---------- Client CRUD Operations ----------*/
Route::get('/clients', [ClientsController::class, 'index'])->name('clients.index');
Route::get('/clientsmatterslist', [ClientsController::class, 'clientsmatterslist'])->name('clients.clientsmatterslist');
Route::get('/clientsclosedmatterslist', [ClientsController::class, 'closedmatterslist'])->name('clients.closedmatterslist');
Route::get('/clientsemaillist', [ClientsController::class, 'clientsemaillist'])->name('clients.clientsemaillist');
Route::post('/clients/store', [ClientsController::class, 'store'])->name('clients.store');
Route::get('/clients/edit/{id}', [ClientsController::class, 'edit'])->name('clients.edit');
Route::post('/clients/edit', [ClientsController::class, 'edit'])->name('clients.update');
Route::get('/clients/export/{id}', [ClientsController::class, 'export'])->name('clients.export');
Route::post('/clients/import', [ClientsController::class, 'import'])->name('clients.import');
Route::post('/clients/save-section', [ClientPersonalDetailsController::class, 'saveSection'])->name('clients.saveSection');
Route::post('/edit-test-scores', [ClientsController::class, 'editTestScores'])->name('clients.editTestScores');
/*---------- Phone & Email Verification ----------*/
Route::prefix('clients/phone')->name('clients.phone.')->group(function () {
    Route::post('/send-otp', [PhoneVerificationController::class, 'sendOTP'])->name('sendOTP');
    Route::post('/verify-otp', [PhoneVerificationController::class, 'verifyOTP'])->name('verifyOTP');
    Route::post('/resend-otp', [PhoneVerificationController::class, 'resendOTP'])->name('resendOTP');
    Route::get('/status/{contactId}', [PhoneVerificationController::class, 'getStatus'])->name('status');
});

Route::prefix('clients/email')->name('clients.email.')->group(function () {
    Route::post('/send-verification', [EmailVerificationController::class, 'sendVerificationEmail'])->name('sendVerification');
    Route::post('/resend-verification', [EmailVerificationController::class, 'resendVerificationEmail'])->name('resendVerification');
    Route::get('/status/{emailId}', [EmailVerificationController::class, 'getStatus'])->name('status');
});

/*---------- Client Actions & Activities ----------*/
Route::post('/clients/action/store', [ClientsController::class, 'actionStore']);
Route::post('/clients/followup/retagfollowup', [ClientsController::class, 'retagfollowup']);
Route::get('/clients/changetype/{id}/{type}', [ClientsController::class, 'changetype']);
Route::post('/clients/convert-lead-only', [ClientsController::class, 'convertLeadOnly'])->name('clients.convertLeadOnly');
Route::get('/document/download/pdf/{id}', [ClientsController::class, 'downloadpdf']);
Route::get('/clients/removetag', [ClientsController::class, 'removetag']);
Route::get('/clients/detail/{client_id}/{client_unique_matter_ref_no?}/{tab?}', [ClientsController::class, 'detail'])->name('clients.detail');
Route::get('/clients/detail-demo-newdesign/{client_id}/{client_unique_matter_ref_no?}/{tab?}', [ClientsController::class, 'detailNewDesignDemo'])->name('clients.detail.demo_newdesign');
Route::post('/clients/google-review-reminder', [ClientsController::class, 'updateGoogleReviewReminder'])->name('clients.google-review-reminder');
Route::post('/clients/google-review-reminder/sms', [ClientsController::class, 'sendGoogleReviewReminderSms'])->name('clients.google-review-reminder.sms');

/*---------- Client Communication ----------*/
Route::get('/clients/get-recipients', [ClientsController::class, 'getrecipients'])->name('clients.getrecipients');
Route::get('/clients/get-onlyclientrecipients', [ClientsController::class, 'getonlyclientrecipients'])->name('clients.getonlyclientrecipients');
Route::get('/clients/get-allclients', [ClientsController::class, 'getallclients'])->name('clients.getallclients');
Route::get('/clients/change_assignee', [ClientsController::class, 'change_assignee']);
Route::get('/get-templates', [CRMUtilityController::class, 'gettemplates'])->name('clients.gettemplates');
Route::get('/get-compose-defaults', [CRMUtilityController::class, 'getComposeDefaults'])->name('clients.getComposeDefaults');
Route::get('/crm/sendgrid-senders', [SendGridSendersController::class, 'senders'])->name('crm.sendgrid.senders');
Route::post('/sendmail', [CRMUtilityController::class, 'sendmail'])->name('clients.sendmail');

Route::post('/upload-mail', [ClientsController::class, 'uploadmail']);

// LEGACY ROUTES (using PEAR - deprecated)
// Route::post('/upload-fetch-mail', [ClientsController::class, 'uploadfetchmail']); //upload inbox email
// Route::post('/upload-sent-fetch-mail', [ClientsController::class, 'uploadsentfetchmail']); //upload sent email

// MODERN ROUTES (using Python microservice - recommended)
Route::post('/upload-fetch-mail', [EmailUploadController::class, 'uploadInboxEmails'])->name('email.upload.inbox');
Route::post('/upload-sent-fetch-mail', [EmailUploadController::class, 'uploadSentEmails'])->name('email.upload.sent');
Route::get('/email/check-service', [EmailUploadController::class, 'checkPythonService'])->name('email.check.service');

Route::post('/reassiginboxemail', [ClientsController::class, 'reassiginboxemail'])->name('clients.reassiginboxemail');
Route::post('/reassigsentemail', [ClientsController::class, 'reassigsentemail'])->name('clients.reassigsentemail');
Route::post('/listAllMattersWRTSelClient', [ClientsController::class, 'listAllMattersWRTSelClient'])->name('clients.listAllMattersWRTSelClient');
Route::post('/updatemailreadbit', [ClientsController::class, 'updatemailreadbit'])->name('clients.updatemailreadbit');

Route::post('/clients/filter-emails', [ClientsController::class, 'filterEmails'])->name('clients.filter.emails');
Route::post('/clients/filter-sentemails', [ClientsController::class, 'filterSentEmails'])->name('clients.filter.sentmails');
Route::post('/clients/filter-lead-emails', [ClientsController::class, 'filterLeadEmails'])->name('clients.filter.leademails');
Route::delete('/email-logs/{id}', [ClientsController::class, 'deleteEmailLog'])->name('email-logs.delete');
// POST alias: some hosts/WAFs block HTTP DELETE; UI uses this route.
Route::post('/email-logs/{id}/delete', [ClientsController::class, 'deleteEmailLog'])->name('email-logs.delete-post');
Route::post('/mail/enhance', [ClientsController::class, 'enhanceMessage'])->name('mail.enhance');

/*---------- Email Labels Management ----------*/
Route::prefix('email-labels')->name('email-labels.')->group(function () {
    Route::get('/', [EmailLabelController::class, 'index'])->name('index');
    Route::post('/', [EmailLabelController::class, 'store'])->name('store');
    Route::post('/apply', [EmailLabelController::class, 'apply'])->name('apply');
    Route::delete('/remove', [EmailLabelController::class, 'remove'])->name('remove');
});

/*---------- Email Log Attachments ----------*/
Route::prefix('mail-attachments')->name('mail-attachments.')->group(function () {
    Route::get('/{id}/download', [EmailLogAttachmentController::class, 'download'])->name('download');
    Route::get('/{id}/preview', [EmailLogAttachmentController::class, 'preview'])->name('preview');
    Route::get('/email/{emailLogId}/download-all', [EmailLogAttachmentController::class, 'downloadAll'])->name('download-all');
});

/*---------- Client Notes ----------*/
Route::post('/create-note', [ClientNotesController::class, 'createnote'])->name('clients.createnote');
Route::post('/update-note-datetime', [ClientNotesController::class, 'updateNoteDatetime'])->name('clients.updateNoteDatetime');
Route::get('/getnotedetail', [ClientNotesController::class, 'getnotedetail'])->name('clients.getnotedetail');
Route::get('/deletenote', [ClientNotesController::class, 'deletenote'])->name('clients.deletenote');
Route::get('/viewnotedetail', [ClientNotesController::class, 'viewnotedetail']);
Route::get('/viewmatternote', [ClientNotesController::class, 'viewapplicationnote'])->name('clients.viewmatternote');
Route::get('/viewapplicationnote', [ClientNotesController::class, 'viewapplicationnote']); // backward compat
// REMOVED Phase 4: prev_visa column dropped - Route::post('/saveprevvisa', [ClientNotesController::class, 'saveprevvisa']);
// REMOVED: saveonlineform routes - OnlineForm model deleted, no frontend calls these routes
Route::get('/get-notes', [ClientNotesController::class, 'getnotes'])->name('clients.getnotes');
Route::get('/pinnote', [ClientNotesController::class, 'pinnote']);

Route::post('/convert-activity-to-note', [ClientsController::class, 'convertActivityToNote'])->name('clients.convertActivityToNote');

/*---------- Client Status & Archive ----------*/
Route::get('/archived', [ClientsController::class, 'archived'])->name('clients.archived');
Route::post('/archive/{id}', [ClientsController::class, 'archive'])->name('clients.archive');
Route::post('/unarchive/{id}', [ClientsController::class, 'unarchive'])->name('clients.unarchive');
Route::get('/change-client-status', [ClientsController::class, 'updateclientstatus'])->name('clients.updateclientstatus');
Route::get('/get-activities', [ClientsController::class, 'activities'])->name('clients.activities');
Route::get('/deletecostagreement', [ClientsController::class, 'deletecostagreement'])->name('clients.deletecostagreement');
Route::get('/deleteactivitylog', [ClientsController::class, 'deleteactivitylog'])->name('clients.deleteactivitylog');
Route::post('/not-picked-call', [ClientsController::class, 'notpickedcall'])->name('clients.notpickedcall');
Route::get('/pinactivitylog', [ClientsController::class, 'pinactivitylog']);

/*---------- Client Services ----------*/
// Interested Services routes REMOVED - feature deprecated (no UI access, modals deleted, controllers don't exist)
// Routes removed: interested-service, edit-interested-service, get-services, getintrestedservice, getintrestedserviceedit
// servicesavefee, deleteservices, savetoapplication REMOVED - controller methods never existed in ClientsController; servicefeeform modal no longer in any view

// Service Taken routes REMOVED - client_service_takens table does not exist
// Model clientServiceTaken.php deleted, controller methods removed
// Routes were: createservicetaken, removeservicetaken, getservicetaken

/*---------- Client Documents Management ----------*/
Route::post('/documents/add-edu-checklist', [ClientDocumentsController::class, 'addedudocchecklist'])->name('clients.documents.addedudocchecklist');
Route::post('/documents/upload-edu-document', [ClientDocumentsController::class, 'uploadedudocument'])->name('clients.documents.uploadedudocument');
Route::post('/documents/add-matter-checklist', [ClientDocumentsController::class, 'addvisadocchecklist'])->name('clients.documents.addMatterDocChecklist');
Route::post('/documents/add-visa-checklist', [ClientDocumentsController::class, 'addvisadocchecklist']);
Route::post('/documents/add-nomination-checklist', [ClientDocumentsController::class, 'addNominationDocChecklist'])->name('clients.documents.addNominationDocChecklist');
Route::post('/documents/upload-matter-document', [ClientDocumentsController::class, 'uploadvisadocument'])->name('clients.documents.uploadMatterDocument');
Route::post('/documents/upload-visa-document', [ClientDocumentsController::class, 'uploadvisadocument']);
Route::post('/documents/upload-nomination-document', [ClientDocumentsController::class, 'uploadNominationDocument'])->name('clients.documents.uploadNominationDocument');
Route::post('/documents/rename', [ClientDocumentsController::class, 'renamedoc'])->name('clients.documents.renamedoc');
Route::get('/documents/delete', [ClientDocumentsController::class, 'deletedocs'])->name('clients.documents.deletedocs');
// BUGFIX #3: Add move document feature
Route::post('/documents/move', [ClientDocumentsController::class, 'moveDocument'])->name('clients.documents.moveDocument');
// BUGFIX #3: Get visa categories for a specific matter
Route::get('/get-visa-categories', [ClientDocumentsController::class, 'getVisaCategories'])->name('clients.documents.getVisaCategories');
Route::get('/get-nomination-categories', [ClientDocumentsController::class, 'getNominationCategories'])->name('clients.documents.getNominationCategories');
// REMOVED: get-visa-checklist route - VisaDocChecklist model deleted, no frontend calls this route
Route::post('/documents/not-used', [ClientDocumentsController::class, 'notuseddoc'])->name('clients.documents.notuseddoc');
Route::post('/documents/rename-checklist', [ClientDocumentsController::class, 'renamechecklistdoc'])->name('clients.documents.renamechecklistdoc');
Route::post('/documents/delete-checklist', [ClientDocumentsController::class, 'deleteChecklist'])->name('clients.documents.deleteChecklist');
Route::post('/documents/back-to-doc', [ClientDocumentsController::class, 'backtodoc'])->name('clients.documents.backtodoc');
Route::post('/documents/download', [ClientDocumentsController::class, 'download_document'])->name('clients.documents.download');
Route::post('/documents/add-personal-category', [ClientDocumentsController::class, 'addPersonalDocCategory'])->name('clients.documents.addPersonalDocCategory');
Route::post('/documents/update-personal-category', [ClientDocumentsController::class, 'updatePersonalDocCategory'])->name('clients.documents.updatePersonalDocCategory');
Route::post('/documents/delete-personal-category', [ClientDocumentsController::class, 'deletePersonalDocCategory'])->name('clients.documents.deletePersonalDocCategory');
Route::post('/documents/add-visa-category', [ClientDocumentsController::class, 'addVisaDocCategory'])->name('clients.documents.addVisaDocCategory');
Route::post('/documents/add-nomination-category', [ClientDocumentsController::class, 'addNominationDocCategory'])->name('clients.documents.addNominationDocCategory');
Route::post('/documents/update-visa-category', [ClientDocumentsController::class, 'updateVisaDocCategory'])->name('clients.documents.updateVisaDocCategory');
Route::post('/documents/update-nomination-category', [ClientDocumentsController::class, 'updateNominationDocCategory'])->name('clients.documents.updateNominationDocCategory');
Route::post('/documents/get-auto-checklist-matches', [ClientDocumentsController::class, 'getAutoChecklistMatches'])->name('clients.documents.getAutoChecklistMatches');
Route::post('/documents/bulk-upload-personal', [ClientDocumentsController::class, 'bulkUploadPersonalDocuments'])->name('clients.documents.bulkUploadPersonalDocuments');
Route::post('/documents/bulk-upload-matter', [ClientDocumentsController::class, 'bulkUploadMatterDocuments'])->name('clients.documents.bulkUploadMatterDocuments');
Route::post('/documents/bulk-upload-visa', [ClientDocumentsController::class, 'bulkUploadMatterDocuments']);
Route::post('/documents/bulk-upload-nomination', [ClientDocumentsController::class, 'bulkUploadNominationDocuments'])->name('clients.documents.bulkUploadNominationDocuments');

/*---------- Client Invoices & Receipts ----------*/
Route::get('/clients/saveaccountreport/{id}', [ClientAccountsController::class, 'saveaccountreport'])->name('clients.saveaccountreport');
Route::post('/clients/saveaccountreport', [ClientAccountsController::class, 'saveaccountreport'])->name('clients.saveaccountreport.update');

/* Test Route for Python Processing */
Route::post('/clients/test-python-accounting', [ClientsController::class, 'testPythonAccounting'])->name('clients.test-python-accounting');

Route::get('/clients/saveinvoicereport/{id}', [ClientAccountsController::class, 'saveinvoicereport'])->name('clients.saveinvoicereport');
Route::post('/clients/saveinvoicereport', [ClientAccountsController::class, 'saveinvoicereport'])->name('clients.saveinvoicereport.update');

Route::get('/clients/saveadjustinvoicereport/{id}', [ClientAccountsController::class, 'saveadjustinvoicereport'])->name('clients.saveadjustinvoicereport');
Route::post('/clients/saveadjustinvoicereport', [ClientAccountsController::class, 'saveadjustinvoicereport'])->name('clients.saveadjustinvoicereport.update');

Route::get('/clients/saveofficereport/{id}', [ClientAccountsController::class, 'saveofficereport'])->name('clients.saveofficereport');
Route::post('/clients/saveofficereport', [ClientAccountsController::class, 'saveofficereport'])->name('clients.saveofficereport.update');

Route::get('/clients/savejournalreport/{id}', [ClientAccountsController::class, 'savejournalreport'])->name('clients.savejournalreport');
Route::post('/clients/savejournalreport', [ClientAccountsController::class, 'savejournalreport'])->name('clients.savejournalreport.update');

Route::post('/clients/isAnyInvoiceNoExistInDB', [ClientAccountsController::class, 'isAnyInvoiceNoExistInDB'])->name('clients.isAnyInvoiceNoExistInDB');
Route::post('/clients/listOfInvoice', [ClientAccountsController::class, 'listOfInvoice'])->name('clients.listOfInvoice');
Route::post('/clients/getTopReceiptValInDB', [ClientAccountsController::class, 'getTopReceiptValInDB'])->name('clients.getTopReceiptValInDB');
Route::post('/clients/getInfoByReceiptId', [ClientAccountsController::class, 'getInfoByReceiptId'])->name('clients.getInfoByReceiptId');
Route::get('/clients/genInvoice/{id}/{client_id?}', [ClientAccountsController::class, 'genInvoice']);
Route::post('/clients/sendToHubdoc/{id}', [ClientAccountsController::class, 'sendToHubdoc'])->name('clients.sendToHubdoc');
Route::get('/clients/checkHubdocStatus/{id}', [ClientAccountsController::class, 'checkHubdocStatus'])->name('clients.checkHubdocStatus');
Route::get('/clients/printPreview/{id}', [ClientAccountsController::class, 'printPreview']);
Route::post('/clients/getTopInvoiceNoFromDB', [ClientAccountsController::class, 'getTopInvoiceNoFromDB'])->name('clients.getTopInvoiceNoFromDB');
Route::post('/clients/clientLedgerBalanceAmount', [ClientAccountsController::class, 'clientLedgerBalanceAmount'])->name('clients.clientLedgerBalanceAmount');

Route::get('/clients/analytics-dashboard', [ClientAccountsController::class, 'analyticsDashboard'])->name('clients.analytics-dashboard');
Route::get('/clients/insights', [ClientsController::class, 'insights'])->name('clients.insights');
Route::get('/clients/invoicelist', [ClientAccountsController::class, 'invoicelist'])->name('clients.invoicelist');
Route::post('/void_invoice', [ClientAccountsController::class, 'void_invoice'])->name('client.void_invoice');
Route::get('/clients/clientreceiptlist', [ClientAccountsController::class, 'clientreceiptlist'])->name('clients.clientreceiptlist');
Route::get('/clients/officereceiptlist', [ClientAccountsController::class, 'officereceiptlist'])->name('clients.officereceiptlist');
Route::get('/clients/journalreceiptlist', [ClientAccountsController::class, 'journalreceiptlist'])->name('clients.journalreceiptlist');
Route::post('/validate_receipt', [ClientAccountsController::class, 'validate_receipt'])->name('client.validate_receipt');
Route::post('/delete_receipt', [ClientAccountsController::class, 'delete_receipt']);

Route::get('/clients/genClientFundReceipt/{id}', [ClientAccountsController::class, 'genClientFundReceipt']);
/** Fix CFL receipt matter + regenerate PDF (auth:admin). Matter: matter=PSA_1 or client_matter_id (id or short code). */
Route::get('/clients/fix-client-fund-receipt-matter/{id}', [ClientAccountsController::class, 'fixClientFundReceiptMatterAndRegenerate'])->whereNumber('id');
Route::get('/clients/fix-client-fund-receipt-matter', [ClientAccountsController::class, 'fixClientFundReceiptMatterAndRegenerate']);
Route::get('/clients/genOfficeReceipt/{id}', [ClientAccountsController::class, 'genofficereceiptInvoice']);

// Send to client routes
Route::post('/clients/send-invoice-to-client/{id}', [ClientAccountsController::class, 'sendInvoiceToClient'])->name('clients.sendInvoiceToClient');
Route::post('/clients/send-client-fund-receipt-to-client/{id}', [ClientAccountsController::class, 'sendClientFundReceiptToClient'])->name('clients.sendClientFundReceiptToClient');
Route::post('/clients/send-office-receipt-to-client/{id}', [ClientAccountsController::class, 'sendOfficeReceiptToClient'])->name('clients.sendOfficeReceiptToClient');

Route::post('/update-client-funds-ledger', [ClientAccountsController::class, 'updateClientFundsLedger'])->name('clients.update-client-funds-ledger');
Route::post('/update-office-receipt', [ClientAccountsController::class, 'updateOfficeReceipt'])->name('clients.updateOfficeReceipt');
Route::post('/get-invoices-by-matter', [ClientAccountsController::class, 'getInvoicesByMatter'])->name('clients.getInvoicesByMatter');
Route::post('/update-client-fund-ledger', [ClientAccountsController::class, 'updateClientFundLedger'])->name('clients.updateClientFundLedger');
Route::post('/clients/invoiceamount', [ClientAccountsController::class, 'getInvoiceAmount'])->name('clients.invoiceamount');

// Receipt document uploads
Route::post('/clients/upload-clientreceipt-document', [ClientAccountsController::class, 'uploadclientreceiptdocument'])->name('clients.uploadclientreceiptdocument');
Route::post('/clients/upload-officereceipt-document', [ClientAccountsController::class, 'uploadofficereceiptdocument'])->name('clients.uploadofficereceiptdocument');
Route::post('/clients/upload-journalreceipt-document', [ClientAccountsController::class, 'uploadjournalreceiptdocument'])->name('clients.uploadjournalreceiptdocument');

/*---------- Client Personal Details & Address ----------*/
Route::post('/clients/update-address', [ClientPersonalDetailsController::class, 'updateAddress'])->name('clients.updateAddress');
Route::post('/clients/search-address-full', [ClientPersonalDetailsController::class, 'searchAddressFull'])->name('clients.searchAddressFull');
Route::post('/clients/get-place-details', [ClientPersonalDetailsController::class, 'getPlaceDetails'])->name('clients.getPlaceDetails');
Route::post('/address_auto_populate', [ClientsController::class, 'address_auto_populate']);

Route::post('/clients/fetchClientContactNo', [ClientPersonalDetailsController::class, 'fetchClientContactNo']);
Route::post('/clients/clientdetailsinfo/{id}', [ClientPersonalDetailsController::class, 'clientdetailsinfo'])->name('clients.clientdetailsinfo');
Route::post('/clients/clientdetailsinfo', [ClientPersonalDetailsController::class, 'clientdetailsinfo'])->name('clients.clientdetailsinfo.update');

Route::get('/get-visa-types', [ClientPersonalDetailsController::class, 'getVisaTypes'])->name('getVisaTypes');
Route::get('/get-countries', [ClientPersonalDetailsController::class, 'getCountries'])->name('getCountries');
Route::post('/updateOccupation', [ClientPersonalDetailsController::class, 'updateOccupation'])->name('clients.updateOccupation');
Route::post('/leads/updateOccupation', [ClientPersonalDetailsController::class, 'updateOccupation'])->name('leads.updateOccupation');

/*---------- Client Relationships ----------*/
Route::post('/clients/search-partner', [ClientPersonalDetailsController::class, 'searchPartner'])->name('clients.searchPartner');
Route::get('/clients/search-partner-test', [ClientPersonalDetailsController::class, 'searchPartnerTest'])->name('clients.searchPartnerTest');
Route::get('/clients/test-bidirectional', [ClientPersonalDetailsController::class, 'testBidirectionalRemoval'])->name('clients.testBidirectional');
Route::post('/clients/save-relationship', [ClientPersonalDetailsController::class, 'saveRelationship'])->name('clients.saveRelationship');

/*---------- Client Agreements & Forms ----------*/
Route::post('/clients/generateagreement', [ClientsController::class, 'generateagreement'])->name('clients.generateagreement');
Route::post('/clients/getVisaAgreementLegalPractitionerDetail', [ClientsController::class, 'getVisaAgreementLegalPractitionerDetail'])->name('clients.getVisaAgreementLegalPractitionerDetail');
Route::post('/clients/getCostAssignmentLegalPractitionerDetail', [ClientsController::class, 'getCostAssignmentLegalPractitionerDetail'])->name('clients.getCostAssignmentLegalPractitionerDetail');
Route::post('/clients/savecostassignment', [ClientsController::class, 'savecostassignment'])->name('clients.savecostassignment');
Route::post('/clients/check-cost-assignment', [ClientsController::class, 'checkCostAssignment']);

// Lead cost assignment
Route::post('/clients/savecostassignmentlead', [ClientsController::class, 'savecostassignmentlead'])->name('clients.savecostassignmentlead');
Route::post('/clients/getCostAssignmentLegalPractitionerDetailLead', [ClientsController::class, 'getCostAssignmentLegalPractitionerDetailLead'])->name('clients.getCostAssignmentLegalPractitionerDetailLead');

Route::post('/clients/{admin}/upload-agreement', [ClientsController::class, 'uploadAgreement'])->name('clients.uploadAgreement');

// Legal Forms (Short Costs Disclosure, Cost Agreement, Authority to Act)
Route::post('/legal-forms', [\App\Http\Controllers\CRM\LegalFormsController::class, 'store'])->name('legal-forms.store');
Route::post('/legal-forms/generate-scope-ai', [\App\Http\Controllers\CRM\LegalFormsController::class, 'generateScopeAI'])->name('legal-forms.generate-scope-ai');
Route::get('/legal-forms/client-forms', [\App\Http\Controllers\CRM\LegalFormsController::class, 'getClientForms'])->name('legal-forms.client-forms');
Route::get('/legal-forms/{legalForm}', [\App\Http\Controllers\CRM\LegalFormsController::class, 'show'])->name('legal-forms.show');
Route::put('/legal-forms/{legalForm}', [\App\Http\Controllers\CRM\LegalFormsController::class, 'update'])->name('legal-forms.update');
Route::delete('/legal-forms/{legalForm}', [\App\Http\Controllers\CRM\LegalFormsController::class, 'destroy'])->name('legal-forms.destroy');
Route::get('/legal-forms/{legalForm}/preview', [\App\Http\Controllers\CRM\LegalFormsController::class, 'previewDocx'])->name('legal-forms.preview');
Route::get('/legal-forms/{legalForm}/download', [\App\Http\Controllers\CRM\LegalFormsController::class, 'downloadDocx'])->name('legal-forms.download');

/*---------- Court Dates & Hearings ----------*/
Route::post('/clients/court-hearings/store', [ClientsController::class, 'storeCourtHearing'])->name('clients.courtHearings.store');
Route::post('/clients/court-hearings/{id}/update', [ClientsController::class, 'updateCourtHearing'])->name('clients.courtHearings.update');
Route::post('/clients/court-hearings/{id}/delete', [ClientsController::class, 'deleteCourtHearing'])->name('clients.courtHearings.delete');

/*---------- Client Matter Management ----------*/
Route::get('/get-matter-templates', [CRMUtilityController::class, 'getmattertemplates'])->name('clients.getmattertemplates');
Route::get('/get-client-matters/{clientId}', [ClientsController::class, 'getClientMatters'])->name('clients.getClientMatters');
Route::post('/clients/store-lead-matter', [ClientsController::class, 'storeLeadMatterFromEdit'])->name('clients.storeLeadMatterFromEdit');
Route::post('/clients/fetchClientMatterAssignee', [ClientPersonalDetailsController::class, 'fetchClientMatterAssignee']);
Route::post('/clients/updateClientMatterAssignee', [ClientPersonalDetailsController::class, 'updateClientMatterAssignee']);

//matter checklist
Route::get('/upload-checklists', [UploadChecklistController::class, 'index'])->name('upload_checklists.index');
Route::get('/upload-checklists/matter/{matterId}', [UploadChecklistController::class, 'showByMatter'])->name('upload_checklists.matter');
Route::post('/upload-checklists/store', [UploadChecklistController::class, 'store'])->name('upload_checklistsupload');

/*---------- Client Sessions & Actions ----------*/
Route::post('/clients/action/personal/store', [ClientsController::class, 'storePersonalAction']);
Route::post('/clients/action/update', [ClientsController::class, 'updateAction']);
Route::post('/clients/action/reassign', [ClientsController::class, 'reassignAction']);
Route::post('/clients/update-session-completed', [ClientsController::class, 'updatesessioncompleted'])->name('clients.updatesessioncompleted');
Route::post('/clients/getAllStaff', [ClientsController::class, 'getAllStaff'])->name('clients.getAllStaff');
Route::post('/clients/getAllUser', [ClientsController::class, 'getAllStaff'])->name('clients.getAllUser'); // deprecated, use getAllStaff

/*---------- Appointments ----------*/
Route::post('/add-appointment', [ClientsController::class, 'addAppointment']);
Route::post('/add-appointment-book', [ClientsController::class, 'addAppointmentBook']);
Route::get('/get-appointments', [ClientsController::class, 'getAppointments']);

/*---------- CRM matter checklist documents (workflow checklist type) ----------*/
Route::get('/api/crm/matter-checklist-documents', [ClientMatterHubController::class, 'getChecklistDocuments'])->name('clients.getChecklistDocuments');
Route::post('/api/crm/matter-checklist-delete-document', [ClientMatterHubController::class, 'deleteChecklistDocument'])->name('clients.deleteChecklistDocument');
Route::post('/api/crm/matter-checklist-update-document-status', [ClientMatterHubController::class, 'updateChecklistDocumentStatus'])->name('clients.updateChecklistDocumentStatus');

/*---------- Client Validation & Utilities ----------*/
Route::post('/check-email', [ClientsController::class, 'checkEmail'])->name('check.email');
Route::post('/check.phone', [ClientsController::class, 'checkContact'])->name('check.phone');
Route::post('/save_tag', [ClientsController::class, 'save_tag']);
Route::post('/check-star-client', [ClientsController::class, 'checkStarClient'])->name('check.star.client');
Route::post('/merge_records', [ClientsController::class, 'merge_records'])->name('client.merge_records');

/*---------- Contact Person Search (for Company Leads) ----------*/
Route::get('/api/search-contact-person', [ClientsController::class, 'searchContactPerson'])
    ->name('api.search.contact.person');

/*---------- CRM cross-access grants ----------*/
Route::prefix('crm/access')->name('crm.access.')->group(function () {
    Route::get('/meta', [AccessGrantController::class, 'meta'])->name('meta');
    Route::post('/quick', [AccessGrantController::class, 'quick'])->middleware('throttle:30,1')->name('quick');
    Route::post('/supervisor', [AccessGrantController::class, 'supervisor'])->middleware('throttle:10,1')->name('supervisor');
    Route::get('/queue', [AccessGrantController::class, 'queuePage'])->name('queue');
    Route::get('/queue/data', [AccessGrantController::class, 'queueData'])->name('queue.data');
    Route::post('/{grant}/approve', [AccessGrantController::class, 'approve'])->whereNumber('grant')->name('approve');
    Route::post('/{grant}/reject', [AccessGrantController::class, 'reject'])->whereNumber('grant')->name('reject');
    Route::get('/my-grants', [AccessGrantController::class, 'myGrantsPage'])->name('my-grants');
    Route::get('/my-grants/data', [AccessGrantController::class, 'myGrantsData'])->name('my-grants.data');
    Route::get('/dashboard', [AccessGrantController::class, 'dashboardPage'])->name('dashboard');
    Route::get('/dashboard/stats', [AccessGrantController::class, 'dashboardStats'])->name('dashboard.stats');
    Route::get('/dashboard/summary', [AccessGrantController::class, 'dashboardSummary'])->name('dashboard.summary');
    Route::get('/dashboard/data', [AccessGrantController::class, 'dashboardData'])->name('dashboard.data');
    Route::get('/dashboard/export', [AccessGrantController::class, 'dashboardExport'])->name('dashboard.export');
    Route::get('/queue/mini', [AccessGrantController::class, 'queueMini'])->name('queue.mini');
});
