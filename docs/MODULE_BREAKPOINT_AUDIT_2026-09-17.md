# CRM Module Breakpoint Audit (Documentation Only)

**Audit date:** 2026-09-17  
**Scope:** Module-wise break / at-risk pages and functions (staff CRM)  
**Trigger:** 2026-09-16 inability to open / preview Word (`.doc`) from client Personal Documents  
**Method:** Static code review, graphify orientation, Laravel logs (`laravel-2026-09-16.log`), prior session evidence; cross-checked with [Audit docs break points](8530c68a-4da4-4ca6-8991-ce0528d6eeb3)  
**Prior fix session:** [Word .doc preview fix](24640b87-8708-4998-a907-622d9ab8cf5e) (MsDoc / LegacyDoc commits)  
**Action:** **Documentation only — no fixes applied**

---

## Severity key

| Priority | Meaning |
|----------|---------|
| **High** | Core user workflow broken or frequently fails (open/preview/download, billing write, booking) |
| **Medium** | Feature partially works, wrong UX, missing converter, or deferred product gap that blocks some files/flows |
| **Low** | Stub, polish, dead UI, or env/setup-only issues |

| Status | Meaning |
|--------|---------|
| **Broken** | Code path fails for the stated case |
| **At Risk** | Works for some files; fails or degrades for others |
| **Working (caveats)** | Main path OK; known limitations |
| **Needs Runtime Verify** | Logic looks OK; confirm in browser / S3 / prod |

---

## Executive summary

Yesterday’s Personal Documents Word failure was **real and logged**: PhpWord tried the **Word2007 (ZIP/DOCX) reader** on a binary `.doc`, producing `archive ... error code: 19` and the UI message **“Unable to preview this Office file inline.”**

Code now has mitigations (`MsDoc` reader selection + `LegacyDocHtmlPreviewService`), but:

1. **Inline preview is still not true Word** — HTML approximation; complex layouts remain incomplete.  
2. **PowerPoint (`ppt`/`pptx`/`odp`) is advertised as previewable but has no converter** → always fails inline.  
3. **“Open in new tab” does not use HTML convert** (`embed=1` missing) → browser gets raw Office bytes via S3 redirect.  
4. **Email Word attachments cannot preview** (`canPreview()` allows image/PDF only).  
5. Personal / Matter / EDU / Visa / Not-used docs share one preview pipeline — same breakpoints apply to all.  
6. Upload allowlists **include** `.doc`/`.docx` — the open failure was **preview conversion**, not upload blocking.  
7. No mammoth / OnlyOffice / Office Online — preview is PhpWord (or LegacyDoc) → HTML in an iframe.

---

## Module health checklist

| Module | Overall | Notes |
|--------|---------|-------|
| Client Personal Documents | **At Risk** | Same preview stack as matter; Word `.doc` was broken 09-16; fidelity still imperfect |
| Client Matter Documents | **At Risk** | Same `/documents/preview/{id}` + `document-preview.js` (affidavit `.doc` in logs) |
| EDU / Visa Documents | **At Risk** | Same upload/preview pipeline (`uploadedudocument` / matter–visa key fallback) |
| Not-used Documents | **At Risk** | Same preview handlers |
| Document download | **Working (caveats)** | AJAX + S3 + CSRF; `filelink` path can 403 if key unmatched |
| Email attachments | **Broken** (Word preview) | Download OK; `canPreview()` = image/PDF only |
| Note attachments | **Working (caveats)** | Non-images force download / attachment disposition only |
| Legal forms preview | **At Risk** | Parallel PhpWord + LegacyDoc path (`LegalFormPreviewService`) |
| Compose matter docs | **Working (caveats)** | Opens `preview_url` in new tab — same non-embed Word limitation |
| E-signatures | **Working (caveats)** | PDF-only by design for signing; viewer stub still present |
| Accounts / invoices | **Needs Runtime Verify** | Pending migration: widen `trans_date` |
| Dashboard / booking calendar | **Working (caveats)** | Intentionally **ajay/kunal only** (8-calendar restore reverted) |
| Office visits | **Working** | Named routes `office-visits.*` present |
| Leads / tasks / workflow | **Working (caveats)** | Earlier UI audit items largely marked Fixed (see `UI_FUNCTIONALITY_AUDIT.md`) |
| Python services | **Needs Runtime Verify** | Local `start_services.py` running; not re-tested end-to-end this audit |

---

## Findings (priority ordered)

### DOC-BP-01 — Legacy `.doc` inline preview failed (Personal / Matter)

| Field | Detail |
|-------|--------|
| **Module** | Client Documents (Personal + Matter + EDU + Visa) |
| **Priority** | **High** |
| **Status** | **Fixed** (2026-09-17) — magic-byte sniff + ZipArchive-19 LegacyDoc recovery; verify once in browser |
| **Breakpoint** | `ClientDocumentsController::preview_document` → `convertOfficeDocumentToHtml`; JS `previewFile` |
| **Symptom** | Preview pane: “Unable to preview this Office file inline.” Download still possible. |
| **Evidence** | `storage/logs/laravel-2026-09-16.log`: `PhpWord HTML office preview failed` … `Ajaipal_..._Affidavit_....doc` … `error code: 19` (Word2007 reader on OLE `.doc`) |
| **Root cause** | `IOFactory::load()` defaults to Word2007; binary `.doc` is not a ZIP. Also: missing/mislabeled extension skipped LegacyDoc entirely. |
| **Fix** | `App\Support\OfficeDocumentFormat` sniffs OLE/ZIP/RTF; LegacyDoc first for OLE; never Word2007 on non-ZIP; recover via LegacyDoc on archive error 19 |
| **Not the cause** | Upload `accept` / `allowedExtensions` already allow `.doc`/`.docx` |
| **Routes** | `GET /documents/preview/{id}?embed=1` (`clients.documents.preview`) |
| **Word-specific?** | Yes — legacy `.doc` (and mislabeled OLE files). `.docx` uses Word2007 path (password/corrupt DOCX can still 503). |
| **UI entry** | `personal_documents.blade.php` / `matter_documents.blade.php` → `previewFile(...)` |

---

### DOC-BP-02 — `.doc` preview fidelity incomplete (not “real Word”)

| Field | Detail |
|-------|--------|
| **Module** | Client Documents + Legal Forms |
| **Priority** | **High** (user-facing: “still not showing complete docs / not Word format”) |
| **Status** | **At Risk** |
| **Breakpoint** | `LegacyDocHtmlPreviewService` (`app/Services/LegacyDocHtmlPreviewService.php`); fallback PhpWord HTML writer |
| **Symptom** | Preview opens but layout/sections incomplete vs desktop Word; not WYSIWYG. |
| **Root cause** | No LibreOffice/MS Word server conversion; piece-table / PhpWord HTML is lossy (images, complex tables, headers/footers). |
| **Routes** | Same embed preview URL; Legal forms use `LegalFormPreviewService::convertDocxBytesToHtml` |
| **Word-specific?** | Yes |

---

### DOC-BP-03 — PowerPoint / ODP preview always fails

| Field | Detail |
|-------|--------|
| **Module** | Client Documents (all doc tabs) |
| **Priority** | **High** |
| **Status** | **Broken** |
| **Breakpoint** | `isOfficeDocumentPreviewType()` includes `ppt`,`pptx`,`odp` (~L2204–L2209); `convertOfficeDocumentToHtml()` only handles word + spreadsheet (~L2228 returns null for ppt*) |
| **JS** | `document-preview.js` treats `pptx?` / `odp` as `isOfficePreview` → iframe embed → 503 error HTML |
| **Symptom** | “Converting document…” then “Unable to preview this Office file inline.” |
| **Root cause** | Type allowlisted for Office preview; no converter implemented. |
| **Routes** | `GET /documents/preview/{id}?embed=1` |
| **Word-specific?** | No — PowerPoint / ODP |

---

### DOC-BP-04 — “Open in new tab” bypasses HTML Office conversion

| Field | Detail |
|-------|--------|
| **Module** | Client Documents preview toolbar |
| **Priority** | **Medium** |
| **Status** | **Fixed** (2026-09-17) — Open button now appends `embed=1` for Office types |
| **Breakpoint** | `document-preview.js` `buildPreviewHeaderHtml` — Open button uses raw `fileUrl` (no `embed=1`); Download adds `download=1` |
| **Also** | Compose matter docs (`compose-matter-documents.js`) open `preview_url` in a new tab — same non-embed limitation |
| **Server** | Non-embed `preview_document` redirects to S3 `temporaryUrl` with `Content-Disposition: inline` + Office MIME (~L2103–L2120) |
| **Symptom** | New tab downloads file, shows blank/garbled, or browser cannot render Word — feels like “cannot open.” |
| **Root cause** | Browsers do not natively render `.doc`/`.docx`; only `?embed=1` runs HTML conversion. |
| **Routes** | `GET /documents/preview/{id}` (no embed) vs `...?embed=1` |
| **Word-specific?** | Yes for Office; PDF often OK inline |

---

### DOC-BP-05 — Email attachment Word/Office preview blocked

| Field | Detail |
|-------|--------|
| **Module** | Emails (Outlook / client email) |
| **Priority** | **Medium** |
| **Status** | **Broken** for Word preview; download path separate |
| **Breakpoint** | `EmailLogAttachment::canPreview()` (~L144–L153) — only image/PDF |
| **Controller** | `EmailLogAttachmentController::preview` aborts 400 if `!canPreview()` |
| **Symptom** | Preview → **400** “This file type cannot be previewed”; must download. |
| **Root cause** | Intentional allowlist; no Office→HTML path wired for email attachments. |
| **Caveat** | `resolveContentType()` may omit doc/docx map → download can fall back to `application/octet-stream` if DB type empty. |
| **Word-specific?** | Yes (and Excel/PPT) |

---

### DOC-BP-06 — Note attachments: no inline Word preview

| Field | Detail |
|-------|--------|
| **Module** | Client Notes |
| **Priority** | **Low** |
| **Status** | **Working (caveats)** |
| **Breakpoint** | `NoteAttachmentHtml::forNoteCard` — non-images link to **download** only |
| **Symptom** | Word files download; no preview pane. |
| **Word-specific?** | Yes for preview expectation |

---

### DOC-BP-07 — Signature document viewer stub

| Field | Detail |
|-------|--------|
| **Module** | E-Signatures |
| **Priority** | **Low** |
| **Status** | **Broken** (stub) |
| **Breakpoint** | `resources/views/crm/signatures/show.blade.php` ~L1478 — `viewDocument()` → `crmAlert('Document viewer feature coming soon!')` |
| **Symptom** | Viewer never opens. |
| **Word-specific?** | N/A (all types) |

---

### DOC-BP-08 — Spreadsheet preview row cap / fail soft

| Field | Detail |
|-------|--------|
| **Module** | Client Documents |
| **Priority** | **Medium** |
| **Status** | **Working (caveats)** |
| **Breakpoint** | `convertSpreadsheetDocumentToHtml` — truncates after row 500; PhpSpreadsheet failures → 503 HTML |
| **Symptom** | Large sheets incomplete; corrupt files show unable-to-preview. |
| **Word-specific?** | No — Excel/CSV/ODS |

---

### DOC-BP-09 — S3 key resolution / missing object

| Field | Detail |
|-------|--------|
| **Module** | Client Documents |
| **Priority** | **High** when it hits |
| **Status** | **Needs Runtime Verify** (path logic present; fails per-file if object missing) |
| **Breakpoint** | `resolveS3KeyForDocument` (~L2498); matter→visa key fallback; `readDocumentFileContent` (~L2584) |
| **Symptom** | Embed HTML: “File not found in S3” / “could not be loaded from storage.” |
| **Root cause** | Wrong legacy key, deleted S3 object, or client_id metadata gap in `buildLegacyS3KeyForDocument`. |
| **Affects** | All file types including Word |

---

### DOC-BP-10 — Download CSRF / `filelink` auth edges

| Field | Detail |
|-------|--------|
| **Module** | Client Documents download |
| **Priority** | **Medium** |
| **Status** | **Working (caveats)** / **Needs Runtime Verify** |
| **Breakpoint** | `download_document` (~L2665); AJAX in `documents.js` must send CSRF; `filelink` path resolves S3 key then matches `Document` / admin for ACL |
| **Symptom** | 419 if CSRF missing; 403 if key cannot be tied to an accessible client |
| **Word-specific?** | No — all types |

---

### DOC-BP-11 — E-sign upload is PDF-only (by design)

| Field | Detail |
|-------|--------|
| **Module** | E-Signatures |
| **Priority** | **Low** (for Word open complaints) |
| **Status** | **Working (caveats)** |
| **Breakpoint** | `DocumentController` store validation `mimes:pdf`; public/staff signing preview pages |
| **Symptom** | Word cannot be uploaded for e-sign — expected, not a Personal Docs bug |
| **Word-specific?** | Signing is PDF-only |

---

### FIN-BP-01 — Pending DB migration (invoice `trans_date`)

| Field | Detail |
|-------|--------|
| **Module** | Accounts / Billing |
| **Priority** | **Medium** |
| **Status** | **At Risk** until migrated |
| **Breakpoint** | `database/migrations/2026_09_15_210000_widen_invoice_line_trans_date.php` — **Pending** on local (`php artisan migrate:status`) |
| **Symptom** | Inserts/updates with `trans_date` longer than VARCHAR(32) can fail once code expects 128. |
| **Root cause** | Migration not applied on this environment (and possibly others). |

---

### CAL-BP-01 — Multi-calendar types intentionally narrowed

| Field | Detail |
|-------|--------|
| **Module** | Dashboard / Booking calendar |
| **Priority** | **Medium** (product gap, not accidental crash) |
| **Status** | **Working (caveats)** |
| **Breakpoint** | Migrations restore then `2026_09_15_161000_re_narrow_appointment_consultants_to_ajay_kunal`; docs in `DASHBOARD_BOOKING_CALENDAR_GAP_AND_PLAN.md` |
| **Symptom** | Only Ajay/Kunal calendars; legacy type URLs 301 → Ajay. |
| **Note** | Not the Word-doc issue; listed so calendar is not reported as “broken” incorrectly. |

---

### ENV-BP-01 — Local seeder / schema mismatches in recent logs

| Field | Detail |
|-------|--------|
| **Module** | Local setup / seeders |
| **Priority** | **Low** |
| **Status** | **Broken** for those commands only |
| **Evidence** | `laravel-2026-09-15.log`: missing `AppointmentConsultantSeed`; `admins.source` column missing on insert |
| **Symptom** | Seed / factory commands fail locally. |
| **Impact** | Dev tooling — not live Personal Docs open path |

---

## Shared pipeline map (Personal Docs Word open)

```
personal_documents.blade.php
  onclick → previewFile(filetype, /documents/preview/{id}, preview-container-*)
       ↓
document-preview.js
  appends ?embed=1 → iframe
       ↓
GET /documents/preview/{id}?embed=1
  ClientDocumentsController::preview_document
       ↓
  isOfficeDocumentPreviewType?
    yes → readDocumentFileContent → convertOfficeDocumentToHtml
           .doc  → LegacyDocHtmlPreviewService (OLE) then PhpWord MsDoc
           .docx → PhpWord Word2007 → HTML
           .xls* → PhpSpreadsheet HTML
           .ppt* → null → officePreviewErrorHtml (503)
    no  → stream / S3 redirect (PDF, images, etc.)
```

**Download (separate):** `POST /documents/download` → `download_document` (JSON-aware errors).

---

## What looked OK (spot-check)

| Area | Result |
|------|--------|
| Preview route registered | `clients.documents.preview` → `GET /documents/preview/{id}` |
| Personal + Matter click handlers | Both call `previewFile` with same preview URL pattern |
| Upload allowlists | `.doc`/`.docx` accepted — not the open failure |
| MIME map for doc/docx | `mimeTypeForS3Key` maps correctly |
| Office visits routes | `office-visits.waiting` / `.attending` / `.completed` defined |
| Download uses `download=1` on preview URL from toolbar | Present in `downloadDocumentFile()` |
| Legal form preview | Uses same legacy `.doc` + reader-name pattern as client docs |

---

## Technology checklist (documents)

| Capability | Present? |
|------------|----------|
| MIME for doc/docx | Yes |
| Embed HTML Office preview | Yes (PhpWord + LegacyDoc) |
| Inline vs download | Embed HTML; `?download=1` attachment; non-embed S3 inline (weak for Word) |
| Office Online / OnlyOffice / mammoth | **No** |
| S3 + local dual path | Yes |
| CSRF on download POST | Required |
| Extension allowlist blocks Word? | **No** |

---

## Suggested verification checklist (manual — not done in this audit)

1. Personal Docs: open a known **legacy `.doc`** (OLE) — confirm embed HTML, not error page.  
2. Personal Docs: open a normal **`.docx`**.  
3. Same files from **Matter Documents** (and EDU/Visa if used).  
4. Toolbar **Open in new tab** vs **Download** for Word (expect new-tab limitation).  
5. Upload/open a **`.pptx`** — expect current failure (DOC-BP-03).  
6. Email with Word attachment — preview expected 400; download OK.  
7. Confirm no new `PhpWord HTML office preview failed` lines in `storage/logs`.  
8. Confirm pending migration `2026_09_15_210000_widen_invoice_line_trans_date` on each environment.  
9. Spot-check S3 missing-key case (DOC-BP-09) with a deleted object.

---

## Related docs (do not duplicate)

- `docs/UI_FUNCTIONALITY_AUDIT.md` — broader UI audit (many items Fixed as of 2026-09-02)  
- `docs/DASHBOARD_BOOKING_CALENDAR_GAP_AND_PLAN.md` — calendar product scope  
- `docs/PACKAGE_UPGRADE_AUDIT.md` — dependency majors (not runtime breaks)  
- `cmr-bugs.md` — security / ACL (separate from this functional breakpoint list)

---

## Change control

| Item | Value |
|------|-------|
| Fixes applied in this pass | **None** |
| Code modified | **None** |
| Next step | Prioritize High findings (DOC-BP-01/02/03/09) when ready to implement |
