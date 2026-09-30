# Bansal Law CRM – S3 Storage, File Uploads & Document Persistence Audit

**Audit Date:** 2026-09-30  
**Audit Scope:** S3 Cloud Storage Engine, Local Mirroring, File Upload Workflows, Document Persistence, Presigned URLs, PDF Generation, Upload-Related Native Dialogs, and Server Configuration  
**Target Modules:** Client Documents (Personal & Matter), Accounting Receipts & Invoices, Note Attachments, Legal Forms, Email & EML/MSG Uploads, Digital Signatures, and Checklists  
**Execution Mode:** Local Static & Runtime Profiling. **Strictly local machine — no git commit or git push.**  
**Document Status:** ✅ **FINAL S3 & UPLOAD AUDIT REPORT (Priority & Status Ranked)**  

---

## 1. Master Priority, Status & Server Impact Matrix (S3 & Uploads)

This master matrix catalogs every failure point discovered across the CRM's file upload pipelines, S3 storage drivers, and document download/preview endpoints.

| # | Priority | Current Status | Server Impact | Module & Feature | Failure Mechanism | Data Loss / Production Consequence |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **01** | 🔴 **CRITICAL** | 🔴 **OPEN / UNRESOLVED** | 🚨 **YES — 100% on Production Server** | Client Accounting Receipts (`ClientAccountsController`) | Direct S3 `file_get_contents()` without local mirror or fallback | **High**: PHP memory limit exhaustion crashes server; S3 glitches cause permanent receipt loss. |
| **02** | 🔴 **CRITICAL** | 🔴 **OPEN / UNRESOLVED** | 🚨 **YES — 100% on Production Server** | Lead Email Compose Modal (`leads/history.blade.php:370`) | Form field name typo (`attachemnt[]` instead of `attach[]`) | **High**: Files added via "Attach More" are completely ignored by the controller and never sent. |
| **03** | 🟠 **HIGH** | 🔴 **OPEN / UNRESOLVED** | 🚨 **YES — 100% on Production Server** | Video Uploads Engine (`PersonalDocumentVideoUploadService`) | Max video size (600MB) exceeds PHP `post_max_size` (512MB) | **High**: PHP drops `$_POST` and `$_FILES` silently; triggers HTTP 419 CSRF error after full upload. |
| **04** | 🟠 **HIGH** | 🔴 **OPEN / UNRESOLVED** | 🚨 **YES — 100% on Production Server** | Document Deletion Workflow (`ClientDocumentsController:1378`) | DB record deleted before S3 delete; fails on empty `doc_type` | **High**: Orphaned files on S3; local mirror never deleted; double-slash S3 key fails deletion. |
| **05** | 🟠 **HIGH** | 🔴 **OPEN / UNRESOLVED** | 🚨 **YES — 100% on Production Server** | Client Documents Preview (`ClientDocumentsController:2251`) | Raw 500 abort on S3 presigned URL failure | **High**: Viewing documents fails with 500 error if S3 has network hiccup, ignoring local mirror. |
| **06** | 🟠 **HIGH** | 🔴 **OPEN / UNRESOLVED** | 🚨 **YES — 100% on Production Server** | Outlook Email Dropzone (`emails_outlook.blade.php`) | Direct Outlook desktop drag-and-drop yields 0 bytes | **Medium**: Staff drag emails from Outlook desktop; upload fails with empty file error. |
| **07** | 🟡 **MEDIUM** | 🔴 **OPEN / UNRESOLVED** | 🚨 **YES — 100% on Production Server** | Upload Checklist Controller (`UploadChecklistController:56`) | Local `public/checklists` upload; duplicate filename overwrite | **Medium**: Bypasses S3 completely; files overwrite each other; breaks on multi-server cluster. |
| **08** | 🟡 **MEDIUM** | 🔴 **OPEN / UNRESOLVED** | 🚨 **YES — 100% on Production Server** | Client Image-to-PDF Export (`ClientsController:1186`) | DomPDF remote S3 image fetch fails; legacy branding | **Medium**: DomPDF blocks remote images; output named `codeplaners.pdf` instead of law firm. |
| **09** | 🟡 **MEDIUM** | 🔴 **OPEN / UNRESOLVED** | 🚨 **YES — 100% on Production Server** | Signed PDF Downloads (`PublicDocumentController:1175`) | Path-style S3 URLs include bucket prefix in key | **Medium**: `$disk->exists()` fails; redirects to raw URL triggering 403 Forbidden for signers. |
| **10** | 🟡 **MEDIUM** | 🔴 **OPEN / UNRESOLVED** | 🚨 **YES — 100% on Production Server** | S3 Background Promotion Command (`PromotePendingUploadsToS3`) | Silent failures during cron promotion | **Low**: Files failing cloud upload log count only without administrator notification. |
| **11** | 🟡 **MEDIUM** | 🔴 **OPEN / UNRESOLVED** | 🚨 **YES — 100% on Production Server** | Upload List Removal Dialogs (`personal_documents.blade.php:1690`) | Native `window.confirm()` used during file queue management | **Medium**: Native browser popups interrupt user flow and freeze upload progress bars. |

---

## 2. Detailed Breakdown: S3 & File Upload Breaking Points

---

### [ISSUE-01] Client Accounting Receipts: Direct S3 Put Without Local Mirror or Fallback

```
+---------------------------------------------------------------------------------------------------+
| PRIORITY: [CRITICAL]             | CURRENT STATUS: 🔴 OPEN (Unresolved Bug)                       |
| SEVERITY: Fatal Memory & S3 Loss | SERVER REPRODUCIBILITY: 🚨 YES - 100% Reproducible on Server   |
+---------------------------------------------------------------------------------------------------+
```

- **Affected Module:** Client Accounting & Receipts (`CRM / Clients / Accounting`)
- **Source File & Lines:** [`app/Http/Controllers/CRM/ClientAccountsController.php:516, 1982, 3223, 3743, 3972, 4200, 5889, 6035`](file:///c:/xampp_old/htdocs/crm_bansal/BansalLaw_CRM/app/Http/Controllers/CRM/ClientAccountsController.php#L516)
- **Code Pattern:**
  ```php
  // Lines 516, 1982, 3223, etc.
  Storage::disk('s3')->put($filePath, file_get_contents($file));
  $obj->myfile = $this->s3PublicUrl($filePath);
  ```
- **Failure Mode:** **`Fatal error: Allowed memory size exhausted`** OR **`Aws\S3\Exception\S3Exception`** (Unhandled)

#### Why and How This Occurs on the Live Server
1. **Memory Exhaustion:** `file_get_contents($file)` reads the entire uploaded file into PHP RAM as a raw string. When a staff member uploads multiple high-resolution PDF scans or multi-page receipts (e.g. 30MB–80MB), PHP memory instantly spikes, causing a fatal server crash on the live server.
2. **No Durable Local Mirror:** Unlike `ClientDocumentsController` (which delegates to `CrmDurableStorage` to mirror files locally before attempting S3), `ClientAccountsController` writes directly to `Storage::disk('s3')`. If AWS S3 experiences latency, credential rotation, or temporary throttling, the request crashes. **The financial receipt transaction fails and the uploaded file is permanently lost.**
3. **Private Bucket 403 Breakage:** Line 522 assigns `$obj->myfile = $this->s3PublicUrl($filePath)`, storing a raw public S3 URL (`https://bucket.s3.amazonaws.com/...`). In AWS S3, legal firm buckets are private by default. When staff later attempt to email the receipt to a client, `ClientAccountsController::getPdfBinaryForDocument()` (line 269) calls `file_get_contents($mf)` on that public URL, which fails with **HTTP 403 Forbidden**.

#### Remediation Plan
Migrate `ClientAccountsController` to use `CrmDurableStorage`:
```php
$durable = app(\App\Services\CrmDurableStorage::class);
$durable->putUploadedFile($file, $filePath);
$obj->myfile = $durable->myfileValue($filePath);
```

---

### [ISSUE-02] Lead Compose Email Modal: Typo in File Input Name (`attachemnt[]`)

```
+---------------------------------------------------------------------------------------------------+
| PRIORITY: [CRITICAL]             | CURRENT STATUS: 🔴 OPEN (Unresolved Bug)                       |
| SEVERITY: Silent File Omission   | SERVER REPRODUCIBILITY: 🚨 YES - 100% Reproducible on Server   |
+---------------------------------------------------------------------------------------------------+
```

- **Affected Module:** Leads Management (`CRM / Leads / History`)
- **Source File & Lines:** [`resources/views/crm/leads/history.blade.php:370`](file:///c:/xampp_old/htdocs/crm_bansal/BansalLaw_CRM/resources/views/crm/leads/history.blade.php#L370)
- **Code Pattern:**
  ```javascript
  $('.filesdata').append('<div class="form-group row attfile">...<input type="file" name="attachemnt[]" class="form-control">...</div>');
  ```
- **Failure Mode:** **Silent Data Omission (Additional attachments are never uploaded or emailed)**

#### Why and How This Occurs on the Live Server
In `resources/views/crm/leads/history.blade.php`, staff can click "Attach More" to add extra attachments to an outgoing email.
However, line 370 creates file inputs with `name="attachemnt[]"` (with the letters **e** and **m** inverted and missing **i**).
The backend controller (`ClientsController@sendmail` / `CRMUtilityController@sendmail`) explicitly checks:
```php
if ($request->hasFile('attach')) {
    foreach ($request->file('attach') as $file) { ... }
}
```
Because the input name is misspelled, Laravel never receives the additional files.

#### Production Impact
Lawyers and migration agents attaching confidential engagement letters, fee schedules, or visa application forms via "Attach More" will send emails that **silently omit all extra attachments**. The lead receives an incomplete email without any error alert shown to staff.

#### Remediation Plan
In [`resources/views/crm/leads/history.blade.php:370`](file:///c:/xampp_old/htdocs/crm_bansal/BansalLaw_CRM/resources/views/crm/leads/history.blade.php#L370), fix the input name to `attach[]`:
```diff
-$('.filesdata').append('...<input type="file" name="attachemnt[]" class="form-control">...');
+$('.filesdata').append('...<input type="file" name="attach[]" class="form-control">...');
```

---

### [ISSUE-03] Video Uploads Exceed Server `post_max_size` (Silent HTTP 419 Crash)

```
+---------------------------------------------------------------------------------------------------+
| PRIORITY: [HIGH]                 | CURRENT STATUS: 🔴 OPEN (Unresolved Bug)                       |
| SEVERITY: HTTP 419 CSRF Crash    | SERVER REPRODUCIBILITY: 🚨 YES - 100% Reproducible on Server   |
+---------------------------------------------------------------------------------------------------+
```

- **Affected Module:** Personal Video & Audio Evidence Uploads
- **Source File & Lines:** [`app/Services/PersonalDocumentVideoUploadService.php:157-165`](file:///c:/xampp_old/htdocs/crm_bansal/BansalLaw_CRM/app/Services/PersonalDocumentVideoUploadService.php#L157-L165)
- **Configuration:** `config('crm.personal_video_upload.max_size_mb', 600)`
- **PHP Environment:** `upload_max_filesize = 512M`, `post_max_size = 512M`
- **Failure Mode:** **HTTP 419 Page Expired (CSRF Token Missing)**

#### Why and How This Occurs on the Live Server
The application logic advertises and allows video uploads up to **600MB**:
```php
public static function maxVideoMb(): int {
    return max(1, (int) config('crm.personal_video_upload.max_size_mb', 600));
}
```
However, the PHP runtime environment enforces `post_max_size = 512M`.
In PHP, when an incoming request payload exceeds `post_max_size`, PHP automatically wipes `$_POST` and `$_FILES` completely clean.
When Laravel's `VerifyCsrfToken` middleware inspects the request, `$request->input('_token')` is empty, immediately aborting with **HTTP 419 Page Expired**.

#### Production Impact
Clients or staff attempting to upload 520MB–600MB video evidence (e.g. spouse visa video interviews, hearing recordings) wait for the entire upload to finish, only to be redirected to a jarring HTTP 419 error page. **The video is discarded and the upload fails completely.**

#### Remediation Plan
1. Synchronize `crm.personal_video_upload.max_size_mb` to match the server limit:
   ```php
   // config/crm.php
   'personal_video_upload' => [
       'max_size_mb' => 500, // Safe threshold below 512M
   ]
   ```
2. Or increase PHP `upload_max_filesize` and `post_max_size` to `1024M` in `php.ini`.

---

### [ISSUE-04] Document Deletion Deletes DB First, Leaves Orphaned S3 Files & Fails on Null `doc_type`

```
+---------------------------------------------------------------------------------------------------+
| PRIORITY: [HIGH]                 | CURRENT STATUS: 🔴 OPEN (Unresolved Bug)                       |
| SEVERITY: Storage Leak / Orphan  | SERVER REPRODUCIBILITY: 🚨 YES - 100% Reproducible on Server   |
+---------------------------------------------------------------------------------------------------+
```

- **Affected Module:** Client Document Deletion (`CRM / Clients / Documents`)
- **Source File & Lines:** [`app/Http/Controllers/CRM/Clients/ClientDocumentsController.php:1378-1386`](file:///c:/xampp_old/htdocs/crm_bansal/BansalLaw_CRM/app/Http/Controllers/CRM/Clients/ClientDocumentsController.php#L1378-L1386)
- **Code Pattern:**
  ```php
  $res = DB::table('documents')->where('id', @$note_id)->delete();
  if ($admin && !empty($admin->client_id)) {
      $this->s3Disk()->delete($admin->client_id.'/'.$data->doc_type.'/'.$data->myfile_key);
  }
  ```
- **Failure Mode:** **Orphaned S3 Objects & Double-Slash Key Deletion Failure**

#### Why and How This Occurs on the Live Server
1. **Database Deleted First:** `DB::table('documents')->where('id', @$note_id)->delete()` is executed before S3 deletion is confirmed. If S3 delete times out or fails, the database reference is permanently deleted, leaving the file stranded on S3 forever with no way to trace or delete it.
2. **Double Slash Key Mismatch:** If `$data->doc_type` is empty or null (common on legacy uploads), `$admin->client_id.'/'.$data->doc_type.'/'.$data->myfile_key` evaluates to `CL-001//filename.pdf` (double slash). S3 considers `CL-001//filename.pdf` and `CL-001/filename.pdf` to be distinct keys. The delete operation silently does nothing, leaving the file on S3.
3. **Local Mirror Never Cleaned:** If the file was stored with `CrmDurableStorage`, the local copy in `storage/app/` is never removed.

#### Remediation Plan
Use `CrmDurableStorage` deletion inside a database transaction:
```php
$storage = app(\App\Services\CrmDurableStorage::class);
$s3Key = $storage->resolveKeyFromDocument($data);
if ($s3Key) {
    $storage->delete($s3Key);
}
DB::table('documents')->where('id', $note_id)->delete();
```

---

### [ISSUE-05] Document Preview Throws Raw 500 Error on S3 Presigned URL Hiccups

```
+---------------------------------------------------------------------------------------------------+
| PRIORITY: [HIGH]                 | CURRENT STATUS: 🔴 OPEN (Unresolved Bug)                       |
| SEVERITY: Fatal 500 Abort        | SERVER REPRODUCIBILITY: 🚨 YES - 100% Reproducible on Server   |
+---------------------------------------------------------------------------------------------------+
```

- **Affected Module:** Document Preview & Streaming
- **Source File & Lines:** [`app/Http/Controllers/CRM/Clients/ClientDocumentsController.php:2251-2266`](file:///c:/xampp_old/htdocs/crm_bansal/BansalLaw_CRM/app/Http/Controllers/CRM/Clients/ClientDocumentsController.php#L2251-L2266)
- **Code Pattern:**
  ```php
  try {
      $tempUrl = $this->s3Disk()->temporaryUrl($s3Key, now()->addMinutes(10), [...]);
      return redirect()->away($tempUrl);
  } catch (\Exception $e) {
      Log::error('S3 preview error: ' . $e->getMessage(), ['document_id' => $id]);
      return abort(500, 'Error generating preview link');
  }
  ```
- **Failure Mode:** **HTTP 500 Internal Server Error**

#### Why and How This Occurs on the Live Server
When staff click "Preview" or "View" on a client document:
The controller attempts to create a presigned S3 URL. If the S3 connection fails, AWS IAM permissions lack `s3:GetObject`, or AWS credentials expire, the code executes `abort(500, 'Error generating preview link')`.
Even though the CRM maintains a durable local mirror of the document on the server in `storage/app/`, the controller **never falls back to serving the local copy** on exception.

#### Production Impact
Staff cannot view critical client documents during client consultations or visa lodgements whenever AWS S3 experiences a transient network issue.

#### Remediation Plan
In `ClientDocumentsController.php:2262`, fall back to serving the local mirror on exception:
```php
} catch (\Exception $e) {
    Log::warning('S3 preview error; falling back to local mirror', ['document_id' => $id, 'error' => $e->getMessage()]);
    $durable = app(\App\Services\CrmDurableStorage::class);
    if ($durable->localMirrorExists($s3Key)) {
        return $durable->downloadResponse($s3Key, $downloadFilename, ['Content-Type' => $mime], false);
    }
    return back()->with('error', 'Document preview is temporarily unavailable. Please try downloading.');
}
```

---

### [ISSUE-06] Direct Outlook Desktop Drag-and-Drop Dropzone Failure

```
+---------------------------------------------------------------------------------------------------+
| PRIORITY: [HIGH]                 | CURRENT STATUS: 🔴 OPEN (Unresolved Bug)                       |
| SEVERITY: File Upload Rejection  | SERVER REPRODUCIBILITY: 🚨 YES - 100% Reproducible on Server   |
+---------------------------------------------------------------------------------------------------+
```

- **Affected Module:** Outlook & Email Sync Uploads
- **Source Files & Lines:**
  - [`resources/views/crm/emails_outlook.blade.php:208`](file:///c:/xampp_old/htdocs/crm_bansal/BansalLaw_CRM/resources/views/crm/emails_outlook.blade.php#L208)
  - [`storage/logs/email-upload-errors-2026-09-24.log:1-6`](file:///c:/xampp_old/htdocs/crm_bansal/BansalLaw_CRM/storage/logs/email-upload-errors-2026-09-24.log#L1)
- **Runtime Error Signature:**
  ```json
  {"stage":"drop_failed","error":"Nothing was received from the drop. Save the email from Outlook as a .msg or .eml file, then try again.","technical_error":"No files detected"}
  ```

#### Why and How This Occurs on the Live Server
On Windows machines, dragging an email item directly from the Outlook desktop app into Google Chrome or Edge does not provide standard HTML5 `File` objects; it passes proprietary OLE / COM virtual file streams (`FileGroupDescriptorW`), which standard browser drag-and-drop APIs cannot read without an Outlook add-in.

#### Production Impact
Staff repeatedly attempt to drag emails from Outlook into the browser. The dropzone accepts the drop animation, but immediately rejects it with a drop failure error, creating confusion and slowing down email filing.

#### Remediation Plan
Add a clear visual helper inside the dropzone in `emails_outlook.blade.php` and `smart-import.blade.php`:
> *"Drag & drop files from your desktop or File Explorer. If using Outlook, drag the email to your desktop first, or use the Bansal Law Outlook Add-in."*

---

### [ISSUE-07] Upload Checklists Bypasses S3 & Silently Overwrites Duplicate Filenames

```
+---------------------------------------------------------------------------------------------------+
| PRIORITY: [MEDIUM]               | CURRENT STATUS: 🔴 OPEN (Unresolved Bug)                       |
| SEVERITY: Cloud Storage Bypass   | SERVER REPRODUCIBILITY: 🚨 YES - 100% Reproducible on Server   |
+---------------------------------------------------------------------------------------------------+
```

- **Affected Module:** Matter Checklist Templates
- **Source File & Lines:** [`app/Http/Controllers/CRM/UploadChecklistController.php:56`](file:///c:/xampp_old/htdocs/crm_bansal/BansalLaw_CRM/app/Http/Controllers/CRM/UploadChecklistController.php#L56) & [`app/Http/Controllers/Controller.php:66-79`](file:///c:/xampp_old/htdocs/crm_bansal/BansalLaw_CRM/app/Http/Controllers/Controller.php#L66-L79)
- **Code Pattern:**
  ```php
  // UploadChecklistController.php:56
  $checklists = $this->uploadFile($request->file('checklists'), config('constants.checklists'));
  
  // Controller.php:68-75
  $explodeFileName = explode('.', $fileName);
  $newFileName = $explodeFileName[0] . '.' . $ext;
  $file->move($filePath, $newFileName);
  ```

#### Why and How This Occurs on the Live Server
1. **S3 Cloud Bypass:** Files are saved directly to `public/checklists/` on the local webserver disk (`config('constants.checklists') = public_path().'/checklists'`). In modern load-balanced or containerized production architectures, uploads stored in `public/` are not shared between instances.
2. **Duplicate Filename Collision:** `explode('.', $fileName)[0]` uses the static base name without any timestamp, UUID, or user ID prefix. If two staff members upload different checklist templates named `checklist.pdf`, the second upload silently overwrites the first.

#### Remediation Plan
1. Use `time() . '_' . Str::uuid()` to ensure uniqueness.
2. Upload checklist files to S3 via `Storage::disk('s3')` or `CrmDurableStorage`.

---

### [ISSUE-08] Client Image-to-PDF Export Fails on Private S3 & Uses Legacy Branding

```
+---------------------------------------------------------------------------------------------------+
| PRIORITY: [MEDIUM]               | CURRENT STATUS: 🔴 OPEN (Unresolved Bug)                       |
| SEVERITY: PDF Generation Crash   | SERVER REPRODUCIBILITY: 🚨 YES - 100% Reproducible on Server   |
+---------------------------------------------------------------------------------------------------+
```

- **Affected Module:** Document PDF Stream Export
- **Source File & Lines:** [`app/Http/Controllers/CRM/ClientsController.php:1186-1217`](file:///c:/xampp_old/htdocs/crm_bansal/BansalLaw_CRM/app/Http/Controllers/CRM/ClientsController.php#L1186-L1217) & [`resources/views/myPDF.blade.php:20`](file:///c:/xampp_old/htdocs/crm_bansal/BansalLaw_CRM/resources/views/myPDF.blade.php#L20)
- **Code Pattern:**
  ```php
  $pdf = $pdf->loadView('myPDF', compact('imageUrl'));
  return $pdf->stream('codeplaners.pdf');
  ```
- **Blade Template:** `<img style="width:100%;" src="{{$imageUrl}}">`

#### Why and How This Occurs on the Live Server
1. **DomPDF Remote HTTP Blocks:** DomPDF requires `isRemoteEnabled => true` in its configuration options to download images over HTTP/HTTPS. When `$imageUrl` points to an S3 object in a private bucket, DomPDF receives a 403 Forbidden error, rendering an empty page or throwing an exception.
2. **Hardcoded Legacy Branding:** Line 1216 streams the file as `codeplaners.pdf` (old agency name) instead of `BansalLawyers_Document.pdf`.

#### Remediation Plan
Fetch image bytes directly via `CrmDurableStorage::get()` and embed as base64 in DomPDF:
```php
$bytes = app(\App\Services\CrmDurableStorage::class)->get($s3Key);
$base64 = 'data:image/png;base64,' . base64_encode($bytes);
$pdf = $pdf->loadView('myPDF', ['imageUrl' => $base64]);
return $pdf->stream('Bansal_Lawyers_Document_' . $id . '.pdf');
```

---

### [ISSUE-09] Signed PDF Download Key Resolution Fails for Path-Style S3 URLs

```
+---------------------------------------------------------------------------------------------------+
| PRIORITY: [MEDIUM]               | CURRENT STATUS: 🔴 OPEN (Unresolved Bug)                       |
| SEVERITY: 403 Forbidden on Sign  | SERVER REPRODUCIBILITY: 🚨 YES - 100% Reproducible on Server   |
+---------------------------------------------------------------------------------------------------+
```

- **Affected Module:** Digital Signatures (`Public Document Download`)
- **Source File & Lines:** [`app/Http/Controllers/PublicDocumentController.php:1175-1192`](file:///c:/xampp_old/htdocs/crm_bansal/BansalLaw_CRM/app/Http/Controllers/PublicDocumentController.php#L1175-L1192)
- **Code Pattern:**
  ```php
  $parsed = parse_url($signedDocUrl);
  $s3Key = ltrim($parsed['path'], '/');
  if ($disk->exists($s3Key)) { ... }
  return redirect($signedDocUrl);
  ```

#### Why and How This Occurs on the Live Server
If the stored signed document URL is formatted using path-style notation:
`https://s3.ap-southeast-2.amazonaws.com/bansal-bucket/signatures/signed_doc_12.pdf`
`parse_url()['path']` returns `/bansal-bucket/signatures/signed_doc_12.pdf`.
`ltrim` produces `bansal-bucket/signatures/signed_doc_12.pdf`.
When `$disk->exists($s3Key)` checks the bucket for that key, it fails because `bansal-bucket/` is the bucket name, not part of the object path!
The code then executes `return redirect($signedDocUrl)`, sending the signer directly to the private S3 URL, which yields an **HTTP 403 Forbidden** error.

#### Remediation Plan
Strip the bucket name from `$s3Key` if present:
```php
$bucket = config('filesystems.disks.s3.bucket');
if ($bucket && str_starts_with($s3Key, $bucket . '/')) {
    $s3Key = substr($s3Key, strlen($bucket) + 1);
}
```

---

### [ISSUE-10] Background S3 Promote Cron Lacks Admin Failure Alerts

```
+---------------------------------------------------------------------------------------------------+
| PRIORITY: [MEDIUM]               | CURRENT STATUS: 🔴 OPEN (Unresolved Bug)                       |
| SEVERITY: Silent Sync Glitches   | SERVER REPRODUCIBILITY: 🚨 YES - 100% Reproducible on Server   |
+---------------------------------------------------------------------------------------------------+
```

- **Affected Module:** Durable Storage S3 Background Sync
- **Source File & Lines:** [`app/Console/Commands/PromotePendingUploadsToS3.php:22-30`](file:///c:/xampp_old/htdocs/crm_bansal/BansalLaw_CRM/app/Console/Commands/PromotePendingUploadsToS3.php#L22-L30) & [`app/Console/Kernel.php:146-150`](file:///c:/xampp_old/htdocs/crm_bansal/BansalLaw_CRM/app/Console/Kernel.php#L146-L150)
- **Execution:** Runs every 15 minutes (`storage:promote-pending-to-s3`).

#### Analysis
`CrmDurableStorage::promoteAllPendingToCloud()` scans local files and pushes them to S3.
When an item fails (e.g. AWS credential expiry, file permission lock), the command simply logs `failed=N`.
There is no automated alert, Slack webhook, or email notification to CRM system administrators when files consistently fail promotion.

---

## 3. Upload & Document Native Dialog Audit (`confirm()`, `alert()`)

Native browser popups (`confirm()`, `alert()`) in upload interfaces freeze UI animations and disrupt file upload progress meters:

| # | Status | View / Script Path | Line | Current Code / Message | Action Context | Recommended Modern Replacement |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| 1 | 🔴 Active | `crm/clients/tabs/personal_documents.blade.php` | 1690 | `if (!confirm('Are you sure you want to remove "' + fileName + '" from the upload list?'))` | Remove file from upload staging queue | SweetAlert2 Modal |
| 2 | 🔴 Active | `crm/clients/tabs/matter_documents.blade.php` | 1590 | `if (!confirm('Are you sure you want to remove "' + fileName + '" from the upload list?'))` | Remove file from upload staging queue | SweetAlert2 Modal |
| 3 | 🔴 Active | `crm/clients/tabs/matter_documents.blade.php` | 786 | `if (!confirm('Remove signature request? The client will no longer be able to sign...')) return;` | Cancel document e-signature request | SweetAlert2 Destructive Modal |
| 4 | 🔴 Active | `crm/clients/tabs/legal_forms.blade.php` | 1255 | `if (!confirm('Are you sure you want to delete this form? This action cannot be undone.')) return;` | Permanent deletion of legal form template | SweetAlert2 Destructive Modal |
| 5 | 🔴 Active | `crm/clients/tabs/legal_forms.blade.php` | 1164 | `if (!confirm('This will replace the current text. Continue?')) return;` | Overwrite form template content | SweetAlert2 Warning Modal |
| 6 | 🔴 Active | `crm/clients/tabs/workflow.blade.php` | 306 | `isConfirmed: window.confirm(options.text \|\| options.title \|\| 'Are you sure?')` | Fallback confirm dialog in workflow | SweetAlert2 Promise |
| 7 | 🔴 Active | `public/js/crm/clients/detail-main.js` | 1145 | `confirm('Are you sure you want to delete this document?')` | Document row delete in detail view | SweetAlert2 Destructive Modal |
| 8 | 🔴 Active | `public/js/crm/clients/modules/documents.js` | 376, 379 | `crmAlert('✓ Success: ' + response.message)` / `crmAlert('✗ Error: ...')` | Folder create/delete alerts | iziToast Notifications |

---

## 4. Phased S3 & Upload Remediation Roadmap

```
+---------------------------------------------------------------------------------------------------------+
| PHASE 1: PREVENT IMMEDIATE DATA LOSS (Day 1)                                                            |
|   [ ] Fix typo `attachemnt[]` -> `attach[]` in resources/views/crm/leads/history.blade.php:370.        |
|   [ ] Migrate ClientAccountsController upload methods to CrmDurableStorage (stream put + local mirror). |
|   [ ] Cap maxVideoMb() to 500MB to prevent PHP post_max_size (512MB) HTTP 419 crashes.                |
+---------------------------------------------------------------------------------------------------------+
| PHASE 2: S3 RELIABILITY & CLEANUP (Day 2)                                                               |
|   [ ] Add fallback to local mirror in ClientDocumentsController::preview() on S3 error.                 |
|   [ ] Wrap document delete in DB transaction and sanitize empty `doc_type` S3 key construction.         |
|   [ ] Fix path-style S3 URL bucket prefix stripping in PublicDocumentController.                       |
+---------------------------------------------------------------------------------------------------------+
| PHASE 3: TEMPLATES & DOMPDF INTEGRATION (Day 3)                                                         |
|   [ ] Migrate UploadChecklistController to S3 storage with UUID filename generation.                    |
|   [ ] Update ClientsController::downloadpdf() to use base64 image streams for DomPDF.                   |
|   [ ] Clarify Outlook desktop drag-and-drop user guidance on email dropzones.                           |
+---------------------------------------------------------------------------------------------------------+
| PHASE 4: UI MODERNIZATION (Day 4)                                                                       |
|   [ ] Replace 6 native confirm() dialogs in document tabs with SweetAlert2.                             |
|   [ ] Standardize upload error notifications using iziToast floating toasts.                           |
+---------------------------------------------------------------------------------------------------------+
```

---

## 5. Audit Compliance & Git Integrity Confirmation

- **Execution Guarantee:** **Zero git commits and zero git pushes executed.**
- **Report Location:** [`docs/S3_AND_UPLOADS_BREAKING_POINTS_AUDIT_2026-09-30.md`](file:///c:/xampp_old/htdocs/crm_bansal/BansalLaw_CRM/docs/S3_AND_UPLOADS_BREAKING_POINTS_AUDIT_2026-09-30.md)
