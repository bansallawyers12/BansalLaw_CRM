# Section 2: Payment Schedule & Invoice Forms – DEPRECATED

**Status:** OBSOLETE – All features removed  
**Date deprecated:** 2026-02

---

## Summary

The following features were **removed** and no longer exist:

- **Payment Schedule Setup** (`/setup-paymentschedule`)
- **Create Invoice from Schedule** (`/create-invoice`)
- **Commission Invoice** (noteinvform modal)
- **General Invoice from Financial modal** (notegetinvform)
- **Edit/Add Payment Schedule** (editinvpaymentschedule, addinvpaymentschedule)
- **Workflow checklist upload** (opendocnote, openfileupload, checklistupload)

The payment schedule modal (`payment-schedules.blade.php`) is not included in the app. The Create Invoice flow uses `createreceiptmodal` → `saveinvoicereport` via the Account tab.

---

## What Remains Active

- **Create Invoice** – Account tab → Create Entry → Invoice (`createreceiptmodal` → `/clients/saveinvoicereport`)
- **Personal Documents / Visa Documents checklist upload** – Add Checklist, Bulk Upload, per-item upload
- **Payment Details modal** – Record payments against invoices (`modals/financial.blade.php`)

---

## Historical Reference

The original plan (application_id → client_matter_id migration) is no longer applicable. This file is retained only as a record of what was removed.
