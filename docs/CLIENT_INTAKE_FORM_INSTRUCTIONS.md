# Lead Form – Implementation Instructions for Website

This document describes how to build a **lead form** on your website. When a lead submits the form, the website should **generate a JSON file** that can be uploaded into the CRM. The CRM will create a **lead**, fill all mapped fields, and create **activity notes** from the form's "Notes" field and any additional/custom form fields.

---

## 1. Flow Overview

1. **Website:** Lead fills out the form and submits.
2. **Website:** On submit, the front end builds a JSON object from the form fields (no server required for the form itself).
3. **Website:** Trigger download of a `.json` file (e.g. `lead-form-YYYY-MM-DD.json`) containing that JSON.
4. **CRM:** Staff upload the JSON file via **Leads → Import Lead** (on the Lead list page).
5. **CRM:** Import creates a **lead**, fills all supported fields, and creates activity notes from the **Notes** field and any **Additional Fields**.

> **Why import goes to Leads, not Clients:** Enquiries from the website are treated as leads first. Staff can convert a lead to a client once they're engaged.

---

## 2. How the CRM Lead Form Works (and why the import handles it differently)

The normal **Create Lead** page only collects the minimum required fields:

| Create Lead page collects | Edit Lead page adds |
|---------------------------|---------------------|
| First name, Last name | Passport information |
| Date of birth, Age, Gender | Visa information |
| Marital status | Address & Travel |
| **One** phone number | Skills & Education |
| **One** email address | Other information |
| | Family information |
| | EOI Reference |

When you import via JSON, the CRM skips the create form entirely and writes **all fields at once** directly to the database — the same result as if staff had created the lead manually and then filled in all the extra sections via the edit page. The import handles this correctly with no extra steps needed.

**Important detail — Phone Numbers & Email Addresses:** The lead edit page shows Phone Numbers and Email Addresses from dedicated linked tables (`client_contacts` and `client_emails`), **not** from the basic phone/email field on the main record. The CRM import automatically handles this: even if you only provide `client.phone` and `client.email` (the minimum), the import will create the linked records so the phone and email appear correctly on the edit page. You do **not** need to repeat them in the `contacts`/`emails` arrays unless you have additional numbers or addresses to add.

---

## 3. JSON Structure Required by CRM

The CRM import expects a single JSON object with the following top-level keys. Only `client` is required; all others are optional.

| Top-level key       | Description |
|---------------------|-------------|
| `client`            | **Required.** Object with basic personal details. |
| `contacts`          | Optional. Array of **extra** phone numbers beyond the primary. |
| `emails`            | Optional. Array of **extra** email addresses beyond the primary. |
| `passport`          | Optional. Single passport object. |
| `visa_countries`    | Optional. Array of visa entries. |
| `qualifications`    | Optional. Array of education/qualification entries (saved to Skills & Education). |
| `experiences`       | Optional. Array of employment history entries (saved to Work Experience). |
| `occupations`       | Optional. Array of occupation/skill assessment entries (saved to Occupation section). |
| `addresses`         | Optional. Array of address entries. |
| `notes`             | Optional. **Free-text notes.** Creates an activity note with subject *"Lead intake – additional information"*. |
| `additional_fields` | Optional. **Extra custom form fields** not mapped to CRM columns. Creates a formatted activity note (table layout) with subject *"Lead intake – form details"*. |

---

## 4. Form Sections and Field Mapping

### 4.1 Basic Information (inside `client` — required minimum)

| Form label        | JSON key (`client.*`)  | Required | Notes |
|-------------------|------------------------|----------|-------|
| First name        | `first_name`           | **Yes**  | |
| Last name         | `last_name`            | No       | |
| Email             | `email`                | **Yes**  | Used for duplicate check. Must be unique. |
| Phone             | `phone`                | No       | Digits only; CRM also checks for duplicates on this. |
| Country code      | `country_code`         | No       | e.g. `+61`, `+91` |
| Date of birth     | `dob`                  | No       | **YYYY-MM-DD** or **DD/MM/YYYY** |
| Age               | `age`                  | No       | Can be calculated from DOB or omitted |
| Gender            | `gender`               | No       | `Male`, `Female`, or `Other` |
| Marital status    | `marital_status`       | No       | `Never Married`, `Married`, `De Facto`, `Divorced`, `Widowed`, `Separated`, `Engaged` |

**Minimum required:** `client.first_name` and `client.email` must be non-empty.

> The `client.phone` and `client.email` values are automatically saved into the Phone Numbers and Email Addresses sections of the lead edit page. You do **not** need to duplicate them in the `contacts` / `emails` arrays.

### 4.2 Extra Phone Numbers (optional — `contacts` array)

Only needed if the client has **more than one** phone number. The primary phone goes in `client.phone`.

```json
"contacts": [
  {
    "contact_type": "Work",
    "country_code": "+61",
    "phone": "0298765432"
  }
]
```

| Key            | Notes |
|----------------|-------|
| `contact_type` | e.g. `Personal`, `Work`. Defaults to `Personal` if omitted. |
| `country_code` | e.g. `+61` |
| `phone`        | Digits (with or without spaces). Entries with empty `phone` are skipped. |

### 4.3 Extra Email Addresses (optional — `emails` array)

Only needed if the client has **more than one** email address. The primary email goes in `client.email`.

```json
"emails": [
  {
    "email_type": "Work",
    "email": "jane.smith@company.com"
  }
]
```

| Key          | Notes |
|--------------|-------|
| `email_type` | e.g. `Personal`, `Work`. Defaults to `Personal` if omitted. |
| `email`      | Valid email address. Entries with empty `email` are skipped. |

### 4.4 Passport (optional — `passport` object)

One passport per import:

```json
"passport": {
  "passport_country": "India",
  "passport_number": "N1234567",
  "passport_issue_date": "2020-01-15",
  "passport_expiry_date": "2030-01-14"
}
```

| Key                    | Notes |
|------------------------|-------|
| `passport_country`     | Country name (e.g. `India`, `Australia`) |
| `passport_number`      | Passport number. `passport` is also accepted as an alias. |
| `passport_issue_date`  | **YYYY-MM-DD** or **DD/MM/YYYY** |
| `passport_expiry_date` | **YYYY-MM-DD** or **DD/MM/YYYY** |

### 4.5 Visa Information (optional — `visa_countries` array)

```json
"visa_countries": [
  {
    "visa_type_matter_nick_name": "485",
    "visa_expiry_date": "2026-06-30",
    "visa_grant_date": "2024-01-15",
    "visa_description": "Temporary Graduate – Post-study work"
  }
]
```

| Key                          | Notes |
|------------------------------|-------|
| `visa_type_matter_nick_name` | **Preferred.** CRM looks up the Matter by nickname (e.g. `485`, `189`, `190`). |
| `visa_type_matter_title`     | Alternative. CRM looks up the Matter by full title. |
| `visa_expiry_date`           | **YYYY-MM-DD** or **DD/MM/YYYY** |
| `visa_grant_date`            | Optional. Same date formats. |
| `visa_description`           | Optional free text. |

> If `visa_countries` is omitted and you just know the visa type label, you can put it on the client object: `client.visa_type` (text label), `client.visa_expiry` (date), `client.visa_opt` (description). The CRM will still create a visa record.

### 4.6 Qualifications (optional — `qualifications` array)

Use this if you want qualification data to be inserted directly into the CRM's **Skills & Education** section (`client_qualifications` table) during lead import.

```json
"qualifications": [
  {
    "level": "Bachelor Degree",
    "name": "Bachelor of Information Technology",
    "qual_college_name": "Deakin University",
    "qual_campus": "Melbourne",
    "country": "Australia",
    "qual_state": "VIC",
    "start_date": "2020-02-01",
    "finish_date": "2023-11-30",
    "relevant_qualification": 1
  }
]
```

| Key                      | Notes |
|--------------------------|-------|
| `level`                  | Qualification level (e.g. `Diploma`, `Bachelor Degree`, `Masters Degree`) |
| `name`                   | Qualification/course name |
| `qual_college_name`      | Institution/college name |
| `qual_campus`            | Campus name |
| `country`                | Country name |
| `qual_state`             | State/region |
| `start_date`             | Optional. **YYYY-MM-DD** or **DD/MM/YYYY** |
| `finish_date`            | Optional. **YYYY-MM-DD** or **DD/MM/YYYY** |
| `relevant_qualification` | Optional. `1`/`0` (also accepts `true`/`false`, `yes`/`no`) |

> Alias keys accepted by importer: `qualification_level`, `qualification_name`, `college_name`, `campus`, `qual_country`, `state`, `qualification_start_date`, `qualification_finish_date`.

### 4.7 Occupation & Skill Assessment (optional — `occupations` array)

Use this when your lead form asks for nominated occupation / ANZSCO / skill assessment details.  
Each entry creates one row in the CRM occupation table (`client_occupations`) and appears on the edit page.

```json
"occupations": [
  {
    "skill_assessment": "Yes",
    "nomi_occupation": "Software Engineer",
    "occupation_code": "261313",
    "list": "ACS",
    "assessment_date": "2024-03-10",
    "expiry_date": "2027-03-10",
    "relevant_occupation": 1,
    "occ_reference_no": "ACS-123456"
  }
]
```

| Key                   | Notes |
|-----------------------|-------|
| `skill_assessment`    | `Yes` / `No` (also accepts `1`/`0`, `true`/`false`) |
| `nomi_occupation`     | Nominated occupation text |
| `occupation_code`     | ANZSCO code |
| `list`                | Assessing authority (e.g. `ACS`, `VETASSESS`) |
| `assessment_date`     | Optional. **YYYY-MM-DD** or **DD/MM/YYYY** |
| `expiry_date`         | Optional. **YYYY-MM-DD** or **DD/MM/YYYY** |
| `relevant_occupation` | Optional. `1`/`0` |
| `occ_reference_no`    | Optional reference number |
| `visa_subclass`       | Optional subclass label/value |
| `anzsco_occupation_id`| Optional numeric ANZSCO occupation ID (if available) |

> Alias keys accepted by importer: `skill_assessment_yes_no`, `nomination_occupation`, `nominated_occupation`, `assessing_authority`, `dates`, `expiry_dates`, `reference_no`.

### 4.8 Employment History (optional — `experiences` array)

Use this to insert work history directly into CRM **Work Experience** (`client_experiences` table).

```json
"experiences": [
  {
    "job_title": "Software Engineer",
    "job_code": "261313",
    "job_emp_name": "Tech Pty Ltd",
    "job_country": "Australia",
    "job_state": "Melbourne, VIC",
    "job_type": "Full-time",
    "job_start_date": "2021-02-01",
    "job_finish_date": "2024-01-31",
    "relevant_experience": 1
  }
]
```

| Key                   | Notes |
|-----------------------|-------|
| `job_title`           | Job title/position |
| `job_code`            | ANZSCO code |
| `job_emp_name`        | Employer name |
| `job_country`         | Country name |
| `job_state`           | Address/location text |
| `job_type`            | e.g. `Full-time`, `Part-time`, `Contract`, `Casual`, `Internship` |
| `job_start_date`      | Optional. **YYYY-MM-DD** or **DD/MM/YYYY** |
| `job_finish_date`     | Optional. **YYYY-MM-DD** or **DD/MM/YYYY** |
| `relevant_experience` | Optional. `1`/`0` (also accepts `true`/`false`, `yes`/`no`) |
| `fte_multiplier`      | Optional. Numeric value for FTE weighting (if used) |

> Alias keys accepted by importer: `title`, `anzsco_code`, `employer_name`, `country`, `address`, `start_date`, `finish_date`, `end_date`.

### 4.9 Address (optional — `addresses` array)

```json
"addresses": [
  {
    "address_line_1": "123 Main St",
    "suburb": "Sydney",
    "state": "NSW",
    "country": "Australia",
    "zip": "2000",
    "is_current": 1
  }
]
```

| Key              | Notes |
|------------------|-------|
| `address_line_1` | Street address |
| `address_line_2` | Optional second line |
| `suburb`         | Suburb or city. `city` also accepted. |
| `state`          | State or region |
| `country`        | Country name |
| `zip`            | Postcode |
| `is_current`     | `1` = current address, `0` = past |

### 4.10 Notes → Activity Note (strongly recommended)

| Form element       | JSON key (top-level) | CRM behaviour |
|--------------------|----------------------|---------------|
| Notes / textarea   | `notes`              | CRM creates an **activity note** with subject *"Lead intake – additional information"* and the notes text as the body. Staff see it immediately in the activity feed. |

- Use a `<textarea>` on the form labelled "Additional information / notes".
- Trim whitespace before writing to JSON; if the trimmed value is empty, omit the key.
- The note is **always created** when `notes` is present — even if the JSON also contains other activity data.

### 4.11 Additional Fields → Formatted Activity Note (optional)

Use `additional_fields` for **any form question that doesn't map to a specific CRM column** — for example "How did you hear about us?", "Preferred consultation time", "Current visa status", etc.

The CRM will create a second activity note with subject **"Lead intake – form details"**, displaying all the extra fields in a clean table layout in the activity feed.

**Supported formats — choose whichever is easier for your website:**

**Option A – object (recommended, simplest):**

```json
"additional_fields": {
  "How did you hear about us?": "Google Search",
  "Preferred consultation time": "Afternoons",
  "Current immigration status": "Student Visa (subclass 500)",
  "Urgency": "Need to act within 3 months"
}
```

**Option B – array of objects:**

```json
"additional_fields": [
  { "label": "How did you hear about us?", "value": "Google Search" },
  { "label": "Preferred consultation time", "value": "Afternoons" }
]
```

Both formats produce the same activity note in the CRM.

**Rules:**
- Keys/labels are shown exactly as written — use clear, human-readable labels.
- If a field's value is empty or `null`, the table will show a dash (`—`) for that row.
- Fields with empty labels are skipped.
- If `additional_fields` is an empty object `{}` or empty array `[]`, no activity note is created.
- Only include fields the client actually filled in; omit blank optional questions rather than sending empty strings.

---

## 5. Minimal Example

The minimum JSON the CRM requires is `client.first_name` and `client.email`. The example below also includes the fields a typical lead form would collect:

```json
{
  "client": {
    "first_name": "Jane",
    "last_name": "Smith",
    "email": "jane.smith@example.com",
    "phone": "412345678",
    "country_code": "+61",
    "dob": "1990-05-15",
    "gender": "Female",
    "marital_status": "Married"
  },
  "notes": "Client is enquiring about a 485 visa. Prefers contact by email."
}
```

The CRM will create the lead and automatically ensure the phone and email appear in the Phone Numbers and Email Addresses sections of the edit page.

---

## 6. Full Example with All Optional Sections

```json
{
  "client": {
    "first_name": "Jane",
    "last_name": "Smith",
    "email": "jane.smith@example.com",
    "phone": "412345678",
    "country_code": "+61",
    "dob": "1990-05-15",
    "gender": "Female",
    "marital_status": "Married"
  },
  "contacts": [
    {
      "contact_type": "Work",
      "country_code": "+61",
      "phone": "0298765432"
    }
  ],
  "emails": [
    {
      "email_type": "Work",
      "email": "jane.smith@company.com"
    }
  ],
  "passport": {
    "passport_country": "India",
    "passport_number": "N1234567",
    "passport_issue_date": "2020-01-15",
    "passport_expiry_date": "2030-01-14"
  },
  "visa_countries": [
    {
      "visa_type_matter_nick_name": "485",
      "visa_expiry_date": "2026-06-30",
      "visa_grant_date": "2024-01-15",
      "visa_description": "Temporary Graduate"
    }
  ],
  "qualifications": [
    {
      "level": "Bachelor Degree",
      "name": "Bachelor of Information Technology",
      "qual_college_name": "Deakin University",
      "qual_campus": "Melbourne",
      "country": "Australia",
      "qual_state": "VIC",
      "start_date": "2020-02-01",
      "finish_date": "2023-11-30",
      "relevant_qualification": 1
    }
  ],
  "experiences": [
    {
      "job_title": "Software Engineer",
      "job_code": "261313",
      "job_emp_name": "Tech Pty Ltd",
      "job_country": "Australia",
      "job_state": "Melbourne, VIC",
      "job_type": "Full-time",
      "job_start_date": "2021-02-01",
      "job_finish_date": "2024-01-31",
      "relevant_experience": 1
    }
  ],
  "occupations": [
    {
      "skill_assessment": "Yes",
      "nomi_occupation": "Software Engineer",
      "occupation_code": "261313",
      "list": "ACS",
      "assessment_date": "2024-03-10",
      "expiry_date": "2027-03-10",
      "relevant_occupation": 1,
      "occ_reference_no": "ACS-123456"
    }
  ],
  "addresses": [
    {
      "address_line_1": "123 Main St",
      "suburb": "Sydney",
      "state": "NSW",
      "country": "Australia",
      "zip": "2000",
      "is_current": 1
    }
  ],
  "notes": "Client will provide certified qualification copies next week. Enquiring about 485 to 189 pathway.",
  "additional_fields": {
    "How did you hear about us?": "Google Search",
    "Preferred consultation time": "Afternoons (after 2pm)",
    "Current immigration status": "Student Visa (subclass 500)",
    "Urgency": "Need to act within 3 months",
    "Additional comments": "Has dependent spouse also on student visa"
  }
}
```

In this example:
- `contacts` and `emails` contain extra Work entries. The primary phone and email from the `client` object are **also** saved automatically as Personal entries — so the lead edit page shows both.
- The `notes` field creates an activity note titled **"Lead intake – additional information"** with the free-text content.
- The `additional_fields` object creates a second activity note titled **"Lead intake – form details"** with all five extra questions displayed as a formatted table in the activity feed.

---

## 7. Date and Format Rules

- **Dates:** Use **YYYY-MM-DD** or **DD/MM/YYYY**. The CRM parses both.
- **Phone:** Store digits only (e.g. `412345678`); put `+61` in `country_code`.
- **Empty values:** Omit the key or send `null`; the CRM treats both as "not set".
- **File encoding:** UTF-8. No trailing commas. All keys must be quoted strings.

---

## 8. Validation on the Website

Recommended client-side checks before generating the file:

- **Required:** `client.first_name` and `client.email` must be non-empty.
- **Email format:** Basic `@` check for `client.email`.
- **Date format:** Validate or normalise DOB, passport dates, and visa dates.
- **Notes:** Trim whitespace; omit the key if empty.
- **Passport:** Only include the `passport` key if at least `passport_number` or `passport_expiry_date` was entered.
- **Visa:** Only include `visa_countries` if at least one visa entry has a type or expiry date.

---

## 9. How Staff Import in the CRM

1. Go to **Leads** in the CRM.
2. Click **Import Lead** (top-right of the lead list).
3. Select the `.json` file downloaded from the website form.
4. Leave **"Skip if lead with same email or phone already exists"** checked (recommended) to avoid duplicates.
5. Click **Import Lead**.

**What the CRM creates:**
- A new **lead** record with all basic fields filled.
- Phone Numbers and Email Addresses sections populated (visible on the lead edit page).
- Passport, Visa, Address records if those sections were in the JSON.
- An **activity note** titled *"Lead intake – additional information"* from the `notes` field (if provided).
- A **formatted activity note** titled *"Lead intake – form details"* from `additional_fields` (if provided), shown as a table of extra questions and answers in the activity feed.

---

## 10. Summary Checklist for Your Form

- [ ] Collects at least **first name** and **email** (required).
- [ ] Has a **Notes** textarea → written to root-level `"notes"` in JSON.
- [ ] Extra form questions (not mapped to CRM columns) → written to `"additional_fields"` as a key-value object.
- [ ] Primary phone → `client.phone` + `client.country_code`.
- [ ] Primary email → `client.email`.
- [ ] Extra phones (if any) → `contacts` array only (don't repeat the primary).
- [ ] Extra emails (if any) → `emails` array only (don't repeat the primary).
- [ ] Dates in **YYYY-MM-DD** or **DD/MM/YYYY**.
- [ ] On submit, builds JSON and triggers a `.json` file download (no server needed).
- [ ] Staff upload via **Leads → Import Lead**.
