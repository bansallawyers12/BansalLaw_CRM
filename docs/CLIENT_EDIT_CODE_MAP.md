# Client / lead edit — code map (Step 0 baseline)

**Purpose:** Single inventory of who owns what for the law-firm CRM UI refactor. No product behaviour change—reference only.

**Last verified:** 2026-04-03 (repository snapshot).

---

## 1. Primary views (individual person: client or lead)

| Surface | Blade | Form element ID | Notes |
|--------|--------|-----------------|--------|
| Client edit | `resources/views/crm/clients/edit.blade.php` | `#editClientForm` | Extends `layouts.crm_client_detail_dashboard`. |
| Lead edit | `resources/views/crm/leads/edit.blade.php` | `#editLeadForm` | Same section structure as client edit; also has lead-only UI (e.g. pipeline card). |
| Lead create | `resources/views/crm/leads/create.blade.php` | (varies) | Uses **`public/js/leads/lead-form-navigation.js`** for scroll/nav, not `edit-client.js` scroll spy. Currently only `personalSection` in nav map. |

**Company (organisation) client:** separate flow — `resources/views/crm/clients/company_edit.blade.php` with `#editCompanyForm`, shares `edit-client.js` `saveSectionData` (picks first matching form: company → client → lead).

---

## 2. Layout behaviour

| File | Relevance |
|------|-----------|
| `resources/views/layouts/crm_client_detail_dashboard.blade.php` | `@if (!request()->routeIs(['leads.create', 'leads.edit', 'clients.edit']))` — some chrome hidden on these routes. Changing route names or adding aliases requires revisiting this. |

---

## 3. Section DOM IDs (scroll targets)

Used by `onclick="scrollToSection('…')"` on **client** and **lead edit** (must stay in sync if IDs change):

| ID | Rough content |
|----|----------------|
| `personalSection` | Basic info, phones, emails |
| `visaPassportSection` | Passport + visa |
| `addressTravelSection` | Addresses + travel |
| `skillsEducationSection` | Qualifications, work experience, occupation, English tests |
| `otherInformationSection` | Additional info, character, related files |
| `familySection` | Partner, children, parents, siblings, others |

**Inner IDs referenced by JS (examples):** `visaDetailsSection`, `spouseDetailsSection` — grep `edit-client.js` for `getElementById` before renaming.

---

## 4. Navigation & scroll — two implementations

| Mechanism | File | Used on |
|-----------|------|---------|
| `window.scrollToSection`, `initScrollSpy`, `updateActiveTabButton` | `public/js/clients/edit-client.js` | Client edit, lead edit (script included from blades). Scroll spy matches `.content-section` to nav via **`onclick` attribute string contains section id** (fragile). |
| `scrollToSection`, `updateActiveNavItem`, `handleScroll` | `public/js/leads/lead-form-navigation.js` | Lead create. **Hardcoded** `sectionMap` and `sections` array — must match create page section IDs and nav button order. |

**If you rename section IDs:** update both edit blades **and** `edit-client.js` **and** `lead-form-navigation.js` (for any overlapping create sections).

---

## 5. AJAX save pipeline

| Piece | Location |
|-------|----------|
| HTTP endpoint | `POST /clients/save-section` — `routes/clients.php` → `clients.saveSection` |
| Controller entry | `ClientPersonalDetailsController::saveSection` |
| Front-end transport | `saveSectionData(sectionName, formData, …)` in `edit-client.js` — posts to **`/clients/save-section`** (hardcoded URL). Appends `_token`, `id`, `type`, `section`. |
| Form resolution | `document.getElementById('editCompanyForm') \|\| …('editClientForm') \|\| …('editLeadForm')` |

**Special `section` string values (must match PHP switch):**

| `formData` section value | PHP `case` | JS caller (typical) |
|----------------------------|------------|---------------------|
| `basicInfo` | `basicInfo` | `saveBasicInfo` flow |
| `phoneNumbers` | `phoneNumbers` | `savePhoneNumbers` |
| `emailAddresses` | `emailAddresses` | `saveEmailAddresses` |
| `passportInfo` | `passportInfo` | `savePassportInfo` |
| `visaInfo` | `visaInfo` | `saveVisaInfo` |
| `addressInfo` | `addressInfo` | `saveAddressInfo` |
| `travelInfo` | `travelInfo` | `saveTravelInfo` |
| `qualificationsInfo` | `qualificationsInfo` | `saveQualificationsInfo` |
| `experienceInfo` | `experienceInfo` | `saveExperienceInfo` |
| `additionalInfo` | `additionalInfo` | `saveAdditionalInfo` |
| `characterInfo` | `characterInfo` | `saveCharacterInfo` |
| `partnerInfo` | `partnerInfo` | `savePartnerInfo` |
| `childrenInfo` | `childrenInfo` | `saveChildrenInfo` |
| `parentsInfo` | `parentsInfo` | Inline fetch in `edit-client.js` (family) |
| `siblingsInfo` | `siblingsInfo` | Inline fetch |
| `othersInfo` | `othersInfo` | Inline fetch |
| `occupation` | `occupation` | `saveOccupationInfo` — **not** `occupationInfo` |
| `test_scores` | `test_scores` | `saveTestScoreInfo` |
| `relatedFilesInfo` | `relatedFilesInfo` | `saveRelatedFilesInfo` |
| `leadPipeline` | `leadPipeline` | `resources/views/crm/clients/partials/lead_pipeline_card.blade.php` (inline script, `fd.append('section', 'leadPipeline')`) |

**Company-only sections** (same `saveSection` switch): `companyInfo`, `contactPersonInfo`, `trust`, `sponsorship`, `directors`, `financial`, `workforce`, `operations`, `lmt`, `training`, `nominations`.

---

## 6. Shared Blade components

Directory: `resources/views/components/client-edit/`

| Component | Role |
|-----------|------|
| `phone-number-field` | Repeatable phones |
| `email-field` | Repeatable emails |
| `passport-field` | Passport rows |
| `visa-field` | Visa rows |
| `address-section` | Address block + autocomplete wiring |
| `address-field` | Single address row |
| `travel-field` | Travel history row |
| `qualification-field` | Education row |
| `work-experience-field` | Employment row |
| `occupation-field` | ANZSCO / skill assessment UI |
| `test-score-field` | English test row |
| `family-member-field` | Family repeats (multiple `type=` modes) |

---

## 7. Page data assembly

| File | Role |
|------|------|
| `app/Services/ClientEditService.php` | `getClientEditData()` — passports, visas, countries, `visaTypes`, qualifications, etc. |
| `app/Http/Controllers/CRM/ClientsController.php` | `edit($id)` GET — returns `company_edit` or `edit` view with service data |

---

## 8. Supporting routes (AJAX / config)

| Name / path | Controller (typical) | Wired from blade |
|-------------|----------------------|------------------|
| `clients.saveSection` | `ClientPersonalDetailsController@saveSection` | `edit-client.js` fetch URL |
| `getVisaTypes` | `ClientPersonalDetailsController@getVisaTypes` | `window.editClientConfig.visaTypesRoute` |
| `getCountries` | (routed similarly) | `editClientConfig.countriesRoute` |
| `clients.searchPartner` | `ClientPersonalDetailsController@searchPartner` | `editClientConfig.searchPartnerRoute` |
| Address search/details | `clients.searchAddressFull`, `clients.getPlaceDetails` | `x-client-edit.address-section` props |

**Company edit** adds `searchContactPersonRoute` etc. in its own `window.editClientConfig` block.

---

## 9. Assets (CSS / JS) — individual client & lead edit

| Asset | Loaded from (typical) |
|-------|------------------------|
| `public/css/client-forms.css` | Client edit, lead edit, lead create, company edit |
| `public/css/clients/edit-client-components.css` | Same |
| `public/css/anzsco-admin.css` | **Client edit, lead edit** (occupation / ANZSCO styling) |
| `public/css/address-autocomplete.css` | Client edit |
| `public/js/clients/edit-client.js` | Client edit, lead edit, company edit |
| `public/js/clients/english-proficiency.js` | Client edit, lead edit |
| `public/js/address-autocomplete.js` | Client edit |
| `public/js/clients/address-regional-codes.js` | Client edit |

**Comments:** `public/js/address-autocomplete.js` notes that add/remove address behaviour lives in `edit-client.js`.

---

## 10. Lead pipeline (lead edit only)

| Piece | Location |
|-------|----------|
| UI + save | `resources/views/crm/clients/partials/lead_pipeline_card.blade.php` — posts `section: 'leadPipeline'` to same `save-section` endpoint |
| Backend | `ClientPersonalDetailsController::saveLeadPipelineSection` |

Not part of `edit-client.js` save helpers for pipeline (self-contained in partial).

---

## 11. Legacy / adjacent endpoints

| Item | Note |
|------|------|
| `ClientsController::editTestScores` | Route `clients.editTestScores` — legacy full form for some test types; grep before removing English test UI |
| `#editClientForm` `action="{{ route('clients.update') }}"` | Main form wrapper; day-to-day persistence is **section AJAX**, not necessarily this POST |

---

## 12. Cross-links & copy (grep anchors for Step 1+)

Suggested ripgrep patterns when changing product language:

- `Client Details Form`
- `visaPassportSection`, `addressTravelSection`, `skillsEducationSection`, `otherInformationSection`, `familySection`, `personalSection`
- `saveSectionData('`
- `formData.append('section'`

---

## 13. Step index (for staged refactor)

This document is **Step 0**. Subsequent steps in the plan: chrome → optional feature flag → nav IDs → per-section vertical slices (blade + components + `edit-client.js` + controller method + CSS) → company edit track separate.
