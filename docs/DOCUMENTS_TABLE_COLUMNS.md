# Documents Table – Column Reference

**Table:** `documents`  
**Database:** `migration_manager_crm` (PostgreSQL)  
**Last Updated:** February 2026

This document describes every column in the `documents` table, how it is used in the codebase, and its current status.

---

## Table of Contents

1. [Primary Key & Timestamps](#1-primary-key--timestamps)
2. [Signature Dashboard Fields](#2-signature-dashboard-fields)
3. [File Storage Fields](#3-file-storage-fields)
4. [Document Classification](#4-document-classification)
5. [Relationships & References](#5-relationships--references)
6. [Checklist & Verification](#6-checklist--verification)
7. [Signature Workflow](#7-signature-workflow)
8. [Hash & Integrity](#8-hash--integrity)
9. [Client Portal](#9-client-portal)
10. [Legacy/Unused Columns](#10-legacyunused-columns)

---

## 1. Primary Key & Timestamps

### `id` (integer, NOT NULL, PK)
- **Type:** Integer, auto-increment via `documents_id_seq`
- **Usage:** Primary key for all document records
- **Used in:** Every document query, relationships, foreign keys (e.g. `signature_activities.document_id`, `signers.document_id`)
- **Status:** ✅ **Actively used**

---

### `created_at` (timestamp)
- **Type:** Timestamp without time zone, nullable
- **Usage:** Standard Laravel created timestamp
- **Used in:** Sorting, filtering, display of upload time
- **Status:** ✅ **Actively used**

---

### `updated_at` (timestamp)
- **Type:** Timestamp without time zone, nullable
- **Usage:** Standard Laravel updated timestamp
- **Used in:** Sorting by last modified, activity tracking
- **Status:** ✅ **Actively used**

---

## 2. Signature Dashboard Fields

*Added by migration `2025_10_20_191713_add_signature_dashboard_fields_to_documents_table.php` for the e-signature workflow.*

### `created_by` (integer, nullable)
- **Type:** Integer, FK to `staff.id` (or `admins.id`)
- **Usage:** ID of the staff member who created the document (for signature workflow docs)
- **Used in:**
  - `Document::creator()` relationship
  - `SignatureDashboardController`, `SignatureService`, `DocumentController` – set on upload
  - `DocumentPolicy`, `DocumentVisibilityTest` – visibility rules
  - `Admin::documentsCreatedByMe()`, `scopeForUser()`, `scopeForSignatureWorkflow()`
- **Status:** ✅ **Actively used**

---

### `origin` (varchar, nullable)
- **Type:** String, values: `ad_hoc`, `client`, `lead`
- **Usage:** Source of the document (ad-hoc upload, from client, from lead)
- **Used in:**
  - `SignatureDashboardController`, `SignatureService` – set when creating signature docs
  - `DocumentController` – `origin => 'ad_hoc'` for ad-hoc uploads
  - `scopeAdhoc()`, `scopeAssociated()` logic
- **Status:** ✅ **Actively used**

---

### `documentable_type` (varchar, nullable)
- **Type:** Polymorphic type (e.g. `App\Models\Admin`, `App\Models\Lead`)
- **Usage:** Polymorphic relation – links document to client or lead
- **Used in:**
  - `Document::documentable()` morphTo relationship
  - `SignatureService`, `SignatureDashboardController` – set when document is linked to client/lead
  - `DocumentVisibilityTest`, `DocumentPolicy` – visibility checks
- **Status:** ✅ **Actively used**

---

### `documentable_id` (integer, nullable)
- **Type:** Integer, ID of polymorphic entity
- **Usage:** Polymorphic relation – ID of linked client or lead
- **Used in:** Same as `documentable_type`; both used together for morph relation
- **Status:** ✅ **Actively used**

---

### `title` (varchar, nullable)
- **Type:** String
- **Usage:** Display title for signature documents (e.g. "Employment Agreement")
- **Used in:**
  - `Document::getDisplayTitleAttribute()` – falls back to `file_name` if null
  - Signature dashboard UI, document listing
  - Sortable in `Document` model
- **Status:** ✅ **Actively used**

---

### `document_type` (varchar, default: 'general')
- **Type:** String, values: `agreement`, `nda`, `general`, `contract`
- **Usage:** Type of signature document for categorization
- **Used in:**
  - `SignatureService` – email template selection for `agreement`
  - `SignatureDashboardController`, `ESignatureController` – validation and display
  - `SignatureAnalyticsService` – grouping by `document_type`
  - `esignature/index.blade.php`, `signatures/show.blade.php`
- **Status:** ✅ **Actively used**

---

### `labels` (text/json, nullable)
- **Type:** JSON array (cast in model)
- **Usage:** Intended for signature document tags/labels
- **Used in:** Model `$fillable` and `$casts` only – **never written or read** in application code
- **Status:** ⚠️ **UNUSED** – schema present, no application usage

---

### `due_at` (timestamp, nullable)
- **Type:** Timestamp
- **Usage:** Due date for signature completion
- **Used in:**
  - `SignatureDashboardController` – overdue filter, validation
  - `SignatureAnalyticsService` – overdue analytics
  - `Document::getIsOverdueAttribute()`
  - `ESignatureController`, `DocumentFactory`
- **Status:** ✅ **Actively used**

---

### `priority` (varchar, default: 'normal')
- **Type:** String, values: `low`, `normal`, `high`
- **Usage:** Priority level for signature documents
- **Used in:**
  - `SignatureDashboardController` – validation
  - `ESignatureController`
  - Sortable in `Document` model
  - `DocumentFactory`
- **Status:** ✅ **Actively used**

---

### `primary_signer_email` (varchar, nullable)
- **Type:** String (email)
- **Usage:** Email of first/primary signer for display
- **Used in:**
  - `SignatureService` – set from signers array
  - `DocumentController` – set when adding signer
  - `SignatureDashboardController` – search filter
  - `signatures/dashboard.blade.php`, `SignatureAnalyticsService`
- **Status:** ✅ **Actively used**

---

### `signer_count` (integer, NOT NULL)
- **Type:** Integer, default 1
- **Usage:** Number of signers on the document (PostgreSQL NOT NULL)
- **Used in:** All document creation paths (ClientAccountsController, ClientDocumentsController, ClientPortalDocumentController, DocumentController, Form956Controller, etc.) to satisfy NOT NULL
- **Status:** ✅ **Actively used**

---

### `last_activity_at` (timestamp, nullable)
- **Type:** Timestamp
- **Usage:** Last activity on document (e.g. signer opened, signed)
- **Used in:**
  - `DocumentController` – set to `now()` when signer signs
  - `SignatureAnalyticsService` – avg time to sign, trending
  - `SignatureServiceTest`
- **Status:** ✅ **Actively used**

---

### `archived_at` (timestamp, nullable)
- **Type:** Timestamp
- **Usage:** When document was archived (soft archive)
- **Used in:**
  - `Document::scopeNotArchived()` – excludes archived docs
  - `SignatureAnalyticsService` – filter out archived
  - `Document::getStatusInfo()` – "Archived" status
  - `SignatureServiceTest`
- **Status:** ✅ **Actively used**

---

## 3. File Storage Fields

### `file_name` (varchar, nullable)
- **Type:** String
- **Usage:** Original or display file name
- **Used in:** Document preview/download URLs, display in UI, sortable
- **Status:** ✅ **Actively used**

---

### `filetype` (varchar, nullable)
- **Type:** String (e.g. `pdf`, `docx`, `png`)
- **Usage:** File extension/type for preview and download handling
- **Used in:** `ClientDocumentsController`, `ClientsController`, `document preview` logic
- **Status:** ✅ **Actively used**

---

### `myfile` (text, nullable)
- **Type:** Text (often S3 key or path)
- **Usage:** File path or S3 key for document content
- **Used in:** S3 storage, file retrieval, migration/sync scripts
- **Status:** ✅ **Actively used**

---

### `myfile_key` (text, nullable)
- **Type:** Text
- **Usage:** Alternative key/path for S3 or storage backend
- **Used in:** ClientsController email preview, document URL construction
- **Status:** ✅ **Actively used**

---

### `file_size` (varchar, nullable)
- **Type:** String (stores size, often as "12345" bytes)
- **Usage:** File size for display or validation
- **Status:** ✅ **Actively used**

---

## 4. Document Classification

*Note: The table has three overlapping "type" concepts – `type`, `doc_type`, and `document_type` – each serving different purposes.*

### `type` (varchar, nullable)
- **Type:** String
- **Usage:** Document category for client/lead context – e.g. `client`, `invoice`, `client_fund_receipt`, `office_receipt`
- **Used in:**
  - `ClientDocumentsController` – `where('type', $request->type)` filtering
  - `ClientEoiRoiController`, `EoiRoiSheetController` – `where('type', 'client')` for visa docs
  - `ClientAccountsController`, `Form956Controller`, `DocumentController`, `EmailUploadController` – set on creation
  - `DocumentController` – returned in response
- **Status:** ✅ **Actively used**

---

### `doc_type` (varchar, nullable)
- **Type:** String
- **Usage:** Document type category – e.g. `personal`, `visa`, `migration`, `education`, `agreement`, `invoices`
- **Used in:**
  - `ClientDocumentsController`, `ClientEoiRoiController`, `EoiRoiSheetController` – filter by `doc_type`
  - `ClientAccountsController`, `DocumentChecklistController`, `Form956Controller`, `CRMUtilityController`
  - Path construction: `client_id/doc_type/mail_type/filename`
- **Status:** ✅ **Actively used**

---

### `folder_name` (varchar, nullable)
- **Type:** String (often stores category ID as string)
- **Usage:** Document category ID – references `personal_document_types.id` or `visa_document_types.id`
- **Used in:**
  - `ClientDocumentsController` – filtering, moving docs between categories
  - `ClientEoiRoiController`, `EoiRoiSheetController` – category display via `VisaDocumentType::find($doc->folder_name)`
  - `Form956Controller`, `ClientPortalDocumentController` – set as `docCategoryId`
  - Path construction in ClientsController
- **Status:** ✅ **Actively used**

---

### `mail_type` (varchar, nullable)
- **Type:** String – e.g. `1` (mail doc), `inbox`, `sent`
- **Usage:** For documents that are email attachments – inbox vs sent
- **Used in:**
  - `ClientsController` – `where('mail_type', 1)`, path: `doc_type/mail_type/filename`
  - `CRMUtilityController` – set on mail conversion
  - `DocumentController` – set for email-fetched docs
- **Status:** ✅ **Actively used**

---

## 5. Relationships & References

### `user_id` (integer, nullable)
- **Type:** Integer, FK to `staff.id` (or `admins.id`)
- **Usage:** Staff member who uploaded/manages the document
- **Used in:** `Document::user()` relationship, document listing, ClientPortalDocumentController
- **Status:** ✅ **Actively used**

---

### `client_id` (integer, nullable)
- **Type:** Integer, FK to `admins.id` (clients are in admins table)
- **Usage:** Client the document belongs to
- **Used in:** `Document::client()` relationship, almost every document query
- **Status:** ✅ **Actively used**

---

### `client_matter_id` (varchar, nullable)
- **Type:** String (matter ID)
- **Usage:** Matter the document is linked to
- **Used in:** `Document::clientMatter()` relationship, filtering by matter
- **Status:** ✅ **Actively used**

---

### `office_id` (integer, nullable)
- **Type:** Integer, FK to `branches.id`
- **Usage:** Office for ad-hoc documents not linked to a matter
- **Used in:**
  - `Document::office()` relationship
  - `Document::scopeByOffice()`, `getResolvedOfficeAttribute()`
  - `ClientsController` – set when creating ad-hoc matter docs
- **Added by:** `2025_12_17_145310_add_office_to_client_matters_and_documents.php`
- **Status:** ✅ **Actively used**

---

### `form956_id` (bigint, nullable)
- **Type:** Bigint, FK to Form 956
- **Usage:** Link to Form 956 when document is a generated Form 956
- **Used in:**
  - `Form956Controller` – set when creating Form 956 doc
  - `matter_documents.blade.php` – if `form956_id` present, use form preview/download routes
- **Added by:** `2026_02_18_201513_add_form956_id_to_documents_table.php`
- **Status:** ✅ **Actively used**

---

## 6. Checklist & Verification

### `checklist` (varchar, nullable)
- **Type:** String
- **Usage:** Checklist item name (e.g. "Passport Copy", "Degree Certificate")
- **Used in:** Document checklist display, filtering, ClientPortalDocumentController
- **Status:** ✅ **Actively used**

---

### `checklist_verified_by` (integer, nullable)
- **Type:** Integer, FK to `staff.id`
- **Usage:** Staff who verified the checklist item
- **Used in:** `Document::verifiedBy()` relationship, ClientDocumentsController – displays "Verified by" and date
- **Status:** ✅ **Actively used**

---

### `checklist_verified_at` (timestamp, nullable)
- **Type:** Timestamp
- **Usage:** When the checklist item was verified
- **Used in:** ClientDocumentsController – displays verification date
- **Status:** ✅ **Actively used**

---

### `not_used_doc` (integer, nullable)
- **Type:** Integer (1 = marked not used, NULL = in use)
- **Usage:** Soft exclude – documents marked "Not Used" are filtered out from normal lists
- **Used in:**
  - `ClientDocumentsController` – `whereNull('not_used_doc')`, `update(array('not_used_doc' => 1))` to mark, `update(array('not_used_doc' => null))` to unmark
  - `ClientPortalDashboardController`, `ClientEoiRoiController`, `EoiRoiSheetController`, `matter_documents.blade.php`, `personal_documents.blade.php`
  - `not_used_documents.blade.php` – shows docs where `not_used_doc = 1`
- **Status:** ✅ **Actively used**

---

## 7. Signature Workflow

### `status` (varchar, nullable)
- **Type:** String – e.g. `draft`, `signature_placed`, `sent`, `signed`, `void`, `archived`
- **Usage:** Current status in the signature workflow
- **Used in:** `Document::getStatusInfo()`, `getStatusBadgeAttribute()`, `getIsOverdueAttribute()`, UI badges, filtering
- **Status:** ✅ **Actively used**

---

### `signature_doc_link` (text, nullable)
- **Type:** Text (URL)
- **Usage:** Link to document sent for signature (e.g. S3 URL)
- **Used in:** Signature workflow, sending to signers
- **Status:** ✅ **Actively used**

---

### `signed_doc_link` (text, nullable)
- **Type:** Text (URL)
- **Usage:** Link to signed/final document (e.g. S3 URL)
- **Used in:** `Document::verifySignedHash()` – downloads from S3 for hash verification
- **Status:** ✅ **Actively used**

---

## 8. Hash & Integrity

*Added by migration `2025_10_21_225122_add_signed_hash_to_documents_table.php` for tamper detection.*

### `signed_hash` (varchar, nullable)
- **Type:** String (SHA-256 hash)
- **Usage:** Hash of signed PDF for integrity verification
- **Used in:** `Document::generateSignedHash()`, `verifySignedHash()`, `getIsTamperedAttribute()`, `getHashDisplayAttribute()`
- **Status:** ✅ **Actively used**

---

### `hash_generated_at` (timestamp, nullable)
- **Type:** Timestamp
- **Usage:** When the signed hash was generated
- **Used in:** `Document::generateSignedHash()`, `PublicDocumentController`, `DocumentController`
- **Status:** ✅ **Actively used**

---

### `certificate_path` (varchar, nullable)
- **Type:** String (intended S3 path)
- **Usage:** Migration comment: "S3 path to completion certificate" – intended for completion certificate storage
- **Used in:** Model `$fillable` only – **never written or read** in application code
- **Status:** ⚠️ **UNUSED** – schema present, no application usage

---

## 9. Client Portal

### `is_client_portal_verify` (integer, nullable)
- **Type:** Integer (e.g. 2 = unverified/pending from portal)
- **Usage:** Indicates document checklist status from client portal (uploaded by client, pending verification)
- **Used in:** `ClientPortalDocumentController` – set to `2` when adding checklist via API, returned in response
- **Status:** ✅ **Actively used**

---

### `client_portal_verified_by` (integer, nullable)
- **Type:** Integer (intended FK to client/admin)
- **Usage:** Intended to store who verified from client portal – **never implemented**
- **Used in:** Model `$fillable` only
- **Status:** ⚠️ **UNUSED**

---

### `client_portal_verified_at` (timestamp, nullable)
- **Type:** Timestamp
- **Usage:** Intended to store when client portal verification occurred – **never implemented**
- **Used in:** Model `$fillable` and `$casts` only
- **Status:** ⚠️ **UNUSED**

---

## 10. Legacy/Unused Columns

### Summary of Unused Columns

| Column | Status | Notes |
|--------|--------|-------|
| `labels` | ⚠️ UNUSED | JSON column; no read/write in app |
| `certificate_path` | ⚠️ UNUSED | S3 path for completion cert; never populated |
| `client_portal_verified_by` | ⚠️ UNUSED | Audit field for client verification; never implemented |
| `client_portal_verified_at` | ⚠️ UNUSED | Timestamp for client verification; never implemented |

---

## Column Type Quick Reference

| Column | DB Type | Nullable | Default |
|--------|---------|----------|---------|
| id | integer | NO | nextval('documents_id_seq') |
| created_by | integer | YES | |
| origin | varchar | YES | |
| documentable_type | varchar | YES | |
| documentable_id | integer | YES | |
| title | varchar | YES | |
| document_type | varchar | YES | |
| labels | text | YES | |
| due_at | timestamp | YES | |
| priority | varchar | YES | |
| primary_signer_email | varchar | YES | |
| signer_count | integer | NO | |
| last_activity_at | timestamp | YES | |
| archived_at | timestamp | YES | |
| file_name | varchar | YES | |
| filetype | varchar | YES | |
| myfile | text | YES | |
| myfile_key | text | YES | |
| user_id | integer | YES | |
| client_id | integer | YES | |
| file_size | varchar | YES | |
| type | varchar | YES | |
| doc_type | varchar | YES | |
| folder_name | varchar | YES | |
| mail_type | varchar | YES | |
| client_matter_id | varchar | YES | |
| checklist | varchar | YES | |
| checklist_verified_by | integer | YES | |
| checklist_verified_at | timestamp | YES | |
| not_used_doc | integer | YES | |
| status | varchar | YES | |
| signature_doc_link | text | YES | |
| signed_doc_link | text | YES | |
| signed_hash | varchar | YES | |
| hash_generated_at | timestamp | YES | |
| certificate_path | varchar | YES | |
| is_client_portal_verify | integer | YES | |
| client_portal_verified_by | integer | YES | |
| client_portal_verified_at | timestamp | YES | |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |
| office_id | integer | YES | |
| form956_id | bigint | YES | |

---

## Related Tables

- **signers** – document signers (FK: `document_id`)
- **signature_fields** – signature field placements (FK: `document_id`)
- **signature_activities** – signature workflow audit trail (FK: `document_id`)
- **cp_doc_checklist** – checklist definitions (different table; documents link via checklist/folder_name)
- **personal_document_types** – personal doc categories (documents.folder_name)
- **visa_document_types** – visa doc categories (documents.folder_name)

---

## Removed Columns (Migration 2026_02_20_150000)

The following columns have been **removed**:

| Column | Replacement |
|--------|-------------|
| `documentable_type`, `documentable_id` | `client_id` + `lead_id` (direct FKs) |
| `title` | `file_name` (display_title accessor) |
| `document_type`, `due_at`, `priority` | Removed; use defaults in templates |
| `archived_at` | `status = 'archived'` |
| `checklist_verified_by`, `checklist_verified_at` | Removed (verification workflow simplified) |
| `origin`, `labels`, `certificate_path`, `signed_hash`, `hash_generated_at` | Previously removed |
| `primary_signer_email`, `signer_count`, `last_activity_at` | Previously removed (use accessors) |
| `client_portal_verified_by`, `client_portal_verified_at` | Previously removed (unused) |
