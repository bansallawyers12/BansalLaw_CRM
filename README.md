# Bansal Law CRM

A comprehensive Laravel-based Customer Relationship Management (CRM) system for **Australian legal practice**. Core modules cover clients, matters, documents, billing, portal, and access control. Legacy **immigration-agency-only** artefacts (CRM “sheets”, visa-pricing config, visa-sheet checklist automation, English test band calculator, ART listing UI) have been **removed or neutralised** in favour of configurable matter types and neutral legal terminology; some labels (e.g. database table `visa_document_types`) remain as technical debt until a future rename.

## Purpose

- Streamline matter-based client work from intake to closure
- Centralize client information, documents, and communication in one platform
- Automate appointment scheduling and reminders
- Track matters, progress, and important deadlines
- Manage invoices, payments, and financial transactions
- Provide secure client portal access for document submission and status tracking
- Offer comprehensive reporting for business insights and internal controls

## What this codebase provides today

The following is implemented and usable in the current application (see **Features** and **Project Structure** for detail):

- **CRM core**: Multi-role staff access (`Admin` / `Staff` / related roles), dashboard, leads pipeline, client profiles (individual and company), notes/tasks (assignee actions), email logging, broadcasts, office visits.
- **Matters**: Case tracking via `ClientMatter` and matter types—workflow and UI copy are oriented to **visa applications** rather than generic legal matter types.
- **Employer / company track**: Company profiles, trading names, directors, trust entities, nominations, and sponsorship-style fields—aligned to **employer-sponsored migration** scenarios.
- **Money**: Invoices, receipts, quotations; Stripe and PayU hooks; PDF generation (DomPDF); financial stats on dashboard.
- **Documents**: Uploads, checklists, expiry tracking, e-signatures (templates and workflow), optional **DOCX→PDF** via `python_services/`.
- **Scheduling**: Booking/appointments (FullCalendar), Flatpickr-based dates (**DD/MM/YYYY** already matches Australian date convention).
- **Client portal**: Status, documents, invoices, bookings—with staff and legal-practitioner assignee fields on matters.
- **Governance**: Row-level visibility, allocation, and **cross-access grants** (quick/supervisor approval, dashboards, CSV export)—see `docs/CROSS_ACCESS_IMPLEMENTATION_PLAN.md`.
- **Infrastructure**: Laravel 12, PostgreSQL-first, Vite frontend, optional S3, queue-ready, SMS (Twilio / Cellcast), Windows/XAMPP-friendly docs.

## Adapting for an Australian law firm

To meet a **general Australian law firm** (or a firm that mixes migration with other practice areas), plan changes across product copy, configuration, data, and compliance—not only rebranding.

### Terminology and user experience

- Replace **immigration-specific** labels in views, emails, PDFs, and reports (e.g. “visa”, “sponsorship”, “application” where used as the default frame) with **neutral legal language** (“matter”, “stage”, “court/tribunal dates” as appropriate) or **practice-area-specific** labels driven by configuration.
- Clarify **roles**: Australian distinctions (e.g. solicitor, paralegal, Legal Practitioner) may differ from current role names and flags such as `is_solicitor` and `sel_legal_practitioner`; align permissions and portal behaviour with your firm’s structure.
- Ensure **trust account vs general account** language on receipts/invoices if you separate client money (many AU firms require strict trust accounting; the current model may need extension or an external trust system).

### Practice areas and matter model

- **Matter types and stages**: Today they reflect visa workflows. Add or migrate to **matter categories** that match your firm (e.g. property, family, litigation, estates, corporate, migration as one stream).
- **Fields and checklists**: Document checklists and matter fields should map to **Australian forms and matter types**, not only immigration templates.
- **Reporting**: Client/application reports filtered by “visa type” or country should be **generalised or duplicated** for AU-relevant dimensions (matter type, jurisdiction, lawyer, office).

### Financial, tax, and commercial

- Standardise on **AUD** for display, invoices, and gateway configuration; review any legacy currency assumptions in templates or seed data.
- **GST**: Invoices and quotations should support **ABN**, **GST-inclusive / exclusive** lines, and wording consistent with **ATO** expectations (implement in templates and data model if not already present).
- **Payment gateways**: Confirm **Stripe Australia** (and any other providers) match your merchant setup; **PayU** may be less relevant in AU—evaluate **BPAY**, direct debit, or other local methods if required.
- **Professional costs and disclosures**: Cost agreements and engagement letters (including e-sign templates) may need **state-based or uniform law** compliant wording—legal review, not only software.

### Privacy, records, and security

- Map data handling to the **Australian Privacy Act** and **APPs** (privacy policy, consent, overseas disclosure if using offshore hosting or processors).
- Review **retention and destruction** rules: README currently mentions long retention in an immigration context; align **destruction and archive** policy with your firm’s obligations.
- **Hosting**: Prefer AU or approved regions if policy requires; document subprocessors (email, SMS, S3, SendGrid, etc.).

### Addresses, identity, and locale

- **Addresses**: Prefer **Australian states/territories** and **postcodes** in forms and validation (postal “ZIP” fields have been moved toward string storage in migrations—continue normalising labels and validation for AU).
- **Phone numbers**: Validate and format for **Australian** mobiles and landlines where the UI collects them.
- **Time zones**: Default `APP_TIMEZONE` and scheduler behaviour to the firm’s primary state (e.g. `Australia/Melbourne`).

### Operations and integrations

- **Email/SMS**: Sender domains, **SPF/DKIM**, and Australian spam/commercial messaging expectations.
- **Calendar**: Public holidays and court/tribunal calendars are not built-in; integrate or configure as needed.

### Technical and configuration checklist (starter)

- `APP_NAME`, `APP_URL`, mail “from” names, and invoice/quote PDF headers footers.
- Database **seeders**: default matter types, visa types, and demo data—replace with AU firm defaults.
- `config/app.php` **timezone** and **locale** where relevant.
- Any **hard-coded country lists** or default country should favour **AU** for a domestic-first firm.

Together, **what we have** is a full-featured legal-style CRM with a **migration-agency-shaped** domain layer; **what needs to change** is chiefly **domain modelling, labels, financial/compliance fields, and integrations** so day-to-day use matches **Australian legal practice** and your firm’s areas of law.

## Features

- **Client Management**: Complete client profiles with personal information, visa history, and documents
- **Matter & Application Tracking**: Monitor visa applications via matters with workflow stages and status updates
- **Appointment System**: Schedule consultations with calendar integration and automated reminders (Booking system)
- **Invoice & Payment Management**: Generate invoices, track payments, and manage receipts
- **Document Management**: Secure storage, electronic signatures, and organisation of client documents and checklists
- **Lead Management**: Track potential clients from inquiry to conversion with analytics
- **Office Visit Tracking**: Manage walk-in clients and office visit queues
- **Email Integration**: Built-in email management with client correspondence tracking
- **Quotation System**: Create and send professional service quotations
- **Team & Staff Management**: Role-based access control; dedicated Staff model with login analytics
- **Reporting & Analytics**: Comprehensive reports on clients, matters, and revenue
- **Client Portal**: Secure portal for clients to view status and submit documents
- **SMS Notifications**: Integrated SMS via Twilio and Cellcast providers
- **Broadcast Notifications**: In-app broadcasts to staff/agents with history
- **Cross-access & row-level visibility**: Staff see clients/leads they are allocated to (or hold a time-bound grant); exempt roles are fully audited; quick (15 min) and supervisor-approved (24 h) access from search; approver queue, grants dashboard, and CSV export (`/crm/access/*`)
- **Electronic Signatures**: Full signature workflow with templates and dashboard
- **Task Management**: Assignee/action system for tasks related to cases and clients
- **Company & Employer Sponsorship**: Full employer sponsorship management with company profiles, directors, trading names, Trust entities, nominations, and sponsorship tracking
- **Windows Friendly**: Optimized for XAMPP on Windows environments

## Technology Stack

- **Backend**: Laravel 12.x (PHP 8.2+)
- **Frontend**: Bootstrap 4/5, jQuery, DataTables, Select2, Flatpickr, FullCalendar, Alpine.js, Tailwind CSS
- **Build**: Vite 7.x
- **Database**: PostgreSQL (Primary), MySQL (optional for migration), SQLite (Development)
- **PDF Generation**: DomPDF for invoices and reports
- **Document Processing**: Python API service (`python_services/`) for DOCX to PDF conversion
- **Email System**: Laravel Mail with SMTP/IMAP integration
- **Payment Integration**: Stripe, PayU payment gateways
- **File Storage**: Local storage with S3 support for attachments
- **Authentication**: Multi-role authentication (Admin, Staff, Agent, Client)
- **Development Environment**: XAMPP on Windows

## Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js and npm
- Python 3.x (for document conversion, optional)
- PostgreSQL 12+ (primary database)
- XAMPP (optional, for Apache; PostgreSQL must be installed separately on Windows)

## Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/bansallawyers12/BansalLaw_CRM.git
   cd BansalLaw_CRM
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install Node.js dependencies**
   ```bash
   npm install
   ```

4. **Environment setup**
   ```powershell
   copy .env.example .env   # If the repo has no .env.example, create .env manually
   php artisan key:generate
   ```
   - Optional: add **CRM access** variables (see [Configuration](#configuration) → CRM cross-access) for strict allocation, approvers, and grant TTLs.

5. **Database setup**
   - Create a PostgreSQL database
   - Update `.env` file with your database credentials:
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=bansal_law_crm
   DB_USERNAME=postgres
   DB_PASSWORD=
   ```
   - Ensure PHP has the `pdo_pgsql` extension enabled (e.g. `php -m` and confirm `pdo_pgsql` is listed, or enable it in `php.ini`)
   - Run migrations and seeders:
   ```bash
   php artisan migrate --seed
   ```

6. **Storage setup**
   ```bash
   php artisan storage:link
   ```
   - Create necessary directories (PowerShell):
   ```powershell
   New-Item -ItemType Directory -Force -Path storage/app/public/agreements, storage/app/public/checklists, storage/app/public/attachments
   ```

7. **Configure mail settings**
   Update `.env` with SendGrid and sender details:
   ```env
   MAIL_MAILER=sendgrid
   MAIL_FROM_ADDRESS=your_sender@yourdomain.com
   MAIL_FROM_NAME="Your Company Name"
   SENDGRID_API_KEY=SG.your_api_key_here
   SENDGRID_FROM_EMAIL=your_sender@yourdomain.com
   SENDGRID_BASE_URL=https://api.sendgrid.com
   ```

8. **Build frontend assets**
   ```bash
   npm run copy:flatpickr   # Copies Flatpickr assets to public/ (required for date pickers)
   npm run build
   # Or for development:
   npm run dev
   ```

9. **Install Python dependencies** (optional, for DOCX to PDF document conversion)
   - If using document conversion, run from project root:
   ```bash
   cd python_services && pip install -r requirements.txt
   ```
   - LibreOffice may be required for high-quality DOCX conversion

10. **Configure payment gateways** (Optional)
    Add to `.env`:
    ```env
    STRIPE_KEY=your_stripe_publishable_key
    STRIPE_SECRET=your_stripe_secret_key
    
    PAYU_MERCHANT_KEY=your_payu_merchant_key
    PAYU_SALT=your_payu_salt
    ```

11. **Start the application**
    - If using XAMPP:
      - Point your virtual host to the `public` directory
      - Or access via `http://localhost/BansalLaw_CRM/public` (adjust path if your folder name differs)
    
    - Using PHP's built-in server:
      ```bash
      php artisan serve
      ```
    
    - For queue workers (run in separate terminal):
      ```bash
      php artisan queue:work
      ```

## Development

### Running the application

For development with XAMPP:
1. Start Apache from XAMPP Control Panel; ensure PostgreSQL is running (install separately if needed)
2. Access the application at `http://localhost/BansalLaw_CRM/public` (or `http://bansallaw-crm.local` if virtual host is configured)

For development with PHP built-in server:
```bash
php artisan serve
```

Access at: `http://localhost:8000`

### Background Jobs

Start the queue worker for processing background jobs:
```bash
php artisan queue:work
```

### Default Login Credentials

After running migrations with seed, use these credentials:

**Admin:**
- Email: admin@admin.com
- Password: (check `database/seeders/AdminUserSeeder.php`)

### Virtual Host Setup (XAMPP)

Add to `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:
```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/BansalLaw_CRM/public"
    ServerName bansallaw-crm.local
    <Directory "C:/xampp/htdocs/BansalLaw_CRM/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Add to `C:\Windows\System32\drivers\etc\hosts`:
```
127.0.0.1 bansallaw-crm.local
```

## Usage Guide

### 1) Manage Leads
- Navigate to `Leads` to view all potential clients
- Create new leads with inquiry details, source, and interested services (including company/trading names for employer leads)
- Track lead status: New, Follow-up, Converted, Lost
- Convert leads to clients when ready to proceed
- View lead history, notes, and assignee actions

### 2) Client Management
- **Search & access**: Global header search may show records as **locked** if you are not allocated and have no active grant; use **Request access** to open the cross-access modal (quick or supervisor path per role). See `docs/CROSS_ACCESS_IMPLEMENTATION_PLAN.md` for product rules.
- Go to `Clients` to view all active clients
- Create detailed client profiles with personal information (individual or company)
- For company clients: use Company Edit for employer sponsorship details (trading names, directors, Trust, nominations)
- Upload client documents and visa history
- Track client relationships (spouse, children, dependents)
- View client summary with all applications, invoices, and documents
- Access client portal credentials

### 3) Matter & Application Tracking
- Visa applications are tracked via **Matters** (client matters) on each client profile
- Create matters linked to clients; select visa type and upload required documents
- Track workflow stages, important dates (submission, interview, decision)
- Add notes and updates for each matter

### 4) Appointment Scheduling
- Go to `Appointments` to manage client consultations
- Use calendar view to see all scheduled appointments
- Create appointments with date, time, and service type
- Send automated appointment reminders to clients
- Track appointment status: Scheduled, Completed, Cancelled
- Handle walk-in appointments

### 5) Invoice Management
- Navigate to `Invoices` to create and manage invoices
- Generate professional invoices for services
- Track payment status: Paid, Unpaid, Partially Paid
- Send invoices via email to clients
- View invoice history and reports

### 6) Document Management
- Go to `Documents` to manage client documents
- Organize documents by categories and checklists
- Upload and download client documents securely
- Track document expiry dates
- Generate document requests for clients
- Sign documents electronically

### 7) Quotations
- Navigate to `Quotations` to create service quotes
- Use templates for standard services
- Customize quotations with line items and pricing
- Send quotations to potential clients
- Track quotation status: Draft, Sent, Accepted, Rejected
- Convert accepted quotations to invoices

### 8) Reports & Analytics
- Access `Reports` for business insights
- View client reports by country, visa type, and status
- Generate revenue reports and forecasts
- Track application success rates
- Monitor staff performance
- Export reports to PDF or Excel

## Business Workflows

- **Lead to Client Process**: Capture lead inquiry → Follow up and qualify → Send quotation → Convert to client → Create client profile
- **Client Onboarding**: Create client account → Collect personal information → Upload documents → Assign case manager → Set up client portal access
- **Application Process**: Receive client documents → Review checklist → Prepare application → Submit to immigration → Track progress → Receive decision
- **Invoice & Payment**: Generate invoice → Send to client → Process payment → Issue receipt → Update payment records
- **Appointment Management**: Client requests appointment → Schedule consultation → Send reminders → Conduct meeting → Update client notes
- **Document Processing**: Client uploads document → Staff reviews → Convert DOCX to PDF → Store securely → Track expiry dates
- **Reporting**: Generate reports → Filter by criteria → Export data → Analyze trends → Make business decisions

## Key Modules

### Admin Module
- Dashboard with key metrics
- Complete client management
- Matter/case tracking (visa applications)
- Invoice and payment management
- Staff and team management
- System settings and configuration

### Client Portal
- View application status
- Upload documents
- Download receipts and invoices
- Book appointments
- Track visa expiry dates
- Communication with case manager

### Legal Practitioner (staff role)
Staff can be flagged as solicitors (`is_solicitor`) and assigned as matter legal practitioners (`sel_legal_practitioner` on `client_matters`) with role-based permissions (e.g. verifying workflow stages in the client portal). They use the main Admin/CRM interface. A separate external-agent portal is not implemented.

## Project Structure

### Key Components

- **Models**: 
  - `Admin` - Multi-role CRM users (Staff, Agent roles); handles authentication for the CRM
  - `Staff` - Dedicated staff model (separate from Admin)
  - `Lead` - Lead tracking and conversion management
  - `ClientMatter` - Matter/case tracking with visa workflow stages
  - `Matter` - Case/matter categories and types
  - `Document` - Document storage and electronic signatures
  - `BookingAppointment` - Appointment scheduling and calendar
  - `AccountAllInvoiceReceipt` / `AccountClientReceipt` - Invoice and receipt records
  - `Company` - Company profiles for employer sponsorship (with trading names, directors, nominations)
  - `CompanyTradingName` - Multiple trading names per company
  - `CompanyDirector` - Company directors with optional client/lead linking
  - `CompanyNomination` - Employer nomination tracking with nominated person linking
  - `Note` - Client/lead notes and assignee tasks
  - `EmailLog` - Email correspondence tracking
  - `ClientAccessGrant` - Cross-access audit trail (quick, supervisor-approved, exempt rows)
  
- **Controllers**: 
  - `ClientsController` - Client CRUD operations and relationship management
  - `ClientPersonalDetailsController` - Per-section AJAX save for client/company details
  - `ClientPortalController` - Client portal operations including workflow management and portal user management
  - `ClientAccountsController` - Invoice, receipt, and payment management
  - `BookingAppointmentsController` - Appointment scheduling and calendar
  - `DocumentController` / `ClientDocumentsController` - Document upload, download, and signature handling
  - `OfficeVisitController` - Walk-in client management
  - `DashboardController` - Admin dashboard and metrics
  - `LeadController` / `LeadConversionController` / `LeadAnalyticsController` - Lead management
  - `AssigneeController` - Task/action assignment management
  - `ReportController` - Reports and data export
  - `BroadcastController` / `BroadcastNotificationAjaxController` - Broadcast notifications
  - `AccessGrantController` - Cross-access meta, quick/supervisor requests, approver queue, mini-queue API, grants dashboard, CSV export
  
- **Services**:
  - `PythonConverterService` - DOCX to PDF via Python HTTP API
  - `EmailConfigService` - Resolves sender from DB (SendGrid)
  - `StripePaymentService` - Stripe payment gateway integration
  - `SignatureService` / `SignatureTemplateService` - Electronic document signing
  - `Sms/UnifiedSmsManager` - SMS via Twilio or Cellcast providers
  - `BroadcastNotificationService` - In-app broadcast notifications
  - `ClientEditService` - Client/company section save logic
  - `DashboardService` / `FinancialStatsService` - Dashboard and financial metrics
  - `S3AttachmentStorageService` / `S3EmailStorageService` - S3 file storage
  - `CrmAccess\CrmAccessService` - Grant lifecycle (request, approve, reject, revoke, expiry), approver notifications
  
- **Support / visibility** (`app/Support/`):
  - `StaffClientVisibility` - `canAccessClientOrLead`, list/query restrictions (clients, leads, documents, bookings), search enrichment for locked rows, exempt daily logging
  
- **Python Services** (`python_services/`):
  - `docx_converter_service.py` - DOCX to PDF conversion via HTTP API
  - `PythonConverterService` (PHP) calls the Python API at `PYTHON_CONVERTER_URL` (default: `http://localhost:5000`)
  
- **Database Migrations**: 
  - User roles and permissions
  - Client and matter management tables
  - Financial transactions
  - Document storage
  - Appointment scheduling (booking system)
  
- **Policies**: 
  - Role-based access control for Admin, Staff, Agent, and Client
  - Client data privacy and access restrictions

### Storage Structure

The application organizes files in the following structure:

```
storage/app/public/
├── agreements/           # Client service agreements
├── checklists/          # Document checklists
├── attachments/         # Email and document attachments
└── documents/           # Client uploaded documents

public/
├── assets/              # UI assets and images
├── css/                 # Custom stylesheets (includes flatpickr.min.css)
├── js/                  # JavaScript files (flatpickr.min.js, crm-flatpickr.js, scripts.js)
└── img/                 # Public images
```

### Date Picker (Flatpickr)

The CRM uses **Flatpickr** for all date inputs (replacing Bootstrap Datepicker/daterangepicker). A global `CRM_Flatpickr` helper provides:

- **initStandard** - Single date picker (DD/MM/YYYY)
- **initPastDates** - Past-dates-only (max: today) for DOB, address dates, visa dates
- **initDOB** - Date of birth with automatic age calculation
- **initDateTime** - Date + time for appointments
- **initRange** - Date range for report filters

Use `data-flatpickr="standard"`, `data-flatpickr="dob"`, `data-flatpickr="datetime"`, or `data-flatpickr="range"` for auto-initialization. Run `npm run copy:flatpickr` after `npm install` to copy Flatpickr assets to `public/`.

### Background Jobs & Scheduling

- Use Laravel's scheduler for automated tasks:
  - **`access:expire-grants`** (hourly) — marks time-expired active grants and very old pending supervisor requests as expired (`CrmAccessService::expireStaleGrants`)
  - Appointment reminders
  - Visa expiry notifications
  - Follow-up reminders
  - Invoice payment reminders
- Queue workers handle:
  - Email sending
  - Document processing
  - PDF generation
  - Report generation

### Document Conversion

The system includes Python-based document conversion via `python_services/`:

- **Purpose**: Convert DOCX documents to PDF format
- **Usage**: `PythonConverterService` (PHP) calls the Python API; set `PYTHON_CONVERTER_URL` in `.env` (default: `http://localhost:5000`)
- **Setup**: Run `python_services/start_services.py` or equivalent to start the conversion API
- **Integration**: Documents uploaded as DOCX are converted via the API; conversion can run in background queue

## Main Routes

### Public Routes
- `GET /` - Welcome/Landing page
- `GET /login` - Login page
- `POST /login` - Authenticate user
- `GET /register` - Registration page (if enabled)

### CRM Routes (Protected - Staff Access)
- `GET /dashboard` - CRM dashboard with key metrics
- **Clients:**
  - `GET /clients` - List all clients
  - `GET /clients/{id}` - View client details
  - `GET /clients/{id}/edit` - Edit client
  - `DELETE /clients/{id}` - Delete client
  - Note: Clients are created by converting leads (see Lead Conversion below)
  
- **Matters** (visa/case tracking on client detail):
  - Matters are managed within the client detail view; no standalone applications routes
  
- **Invoices:**
  - `GET /invoices` - List invoices
  - `GET /invoices/create` - Create invoice
  - `GET /invoices/{id}` - View invoice
  - `POST /invoices/{id}/send` - Email invoice
  - `POST /invoices/{id}/payment` - Record payment
  
- **Appointments** (Booking system):
  - Uses `BookingAppointmentsController`; see `routes/client_portal.php` for booking routes
  
- **Leads:**
  - `GET /leads` - List leads
  - `POST /leads` - Create lead
  - `PUT /leads/{id}/convert` - Convert to client

- **Cross-access (staff)** — prefix `/crm/access/` (see `routes/clients.php`, `auth:admin`):
  - `GET /crm/access/meta` — branches, teams, quick reasons, UI flags for the request modal
  - `POST /crm/access/quick` — 15-minute quick grant (throttled)
  - `POST /crm/access/supervisor` — supervisor approval request (throttled)
  - `GET /crm/access/queue` — HTML pending queue (approvers / Super Admin)
  - `GET /crm/access/queue/data` | `GET /crm/access/queue/mini` — JSON pending items (mini for header dropdown)
  - `POST /crm/access/{grant}/approve` | `reject` — approve or reject (approvers)
  - `GET /crm/access/my-grants` — staff’s own grants (HTML + JSON data route)
  - `GET /crm/access/dashboard` — grants dashboard (filters, pending section, table, CSV export link)
  - `GET /crm/access/dashboard/data` | `dashboard/export` — JSON and CSV for audits
  
- **Reports:**
  - `GET /reports/clients` - Client reports
  - `GET /reports/applications` - Application reports
  - `GET /reports/revenue` - Financial reports
  - `GET /reports/export` - Export data

### Client Portal Routes (Protected)
- `GET /portal/dashboard` - Client dashboard
- `GET /portal/applications` - View my applications
- `GET /portal/documents` - View and upload documents
- `GET /portal/invoices` - View invoices and payments
- `GET /portal/appointments` - Book appointments
- `POST /portal/documents/upload` - Upload document

## Configuration

### Environment Variables

Key environment variables in `.env`:

```env
APP_NAME="Bansal Law CRM"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database (PostgreSQL)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=bansal_law_crm
DB_USERNAME=postgres
DB_PASSWORD=

# Mail Configuration (SendGrid)
MAIL_MAILER=sendgrid
MAIL_FROM_ADDRESS=your_sender@yourdomain.com
MAIL_FROM_NAME="Your Company Name"
SENDGRID_API_KEY=SG.your_api_key_here
SENDGRID_FROM_EMAIL=your_sender@yourdomain.com
SENDGRID_BASE_URL=https://api.sendgrid.com

# Payment Gateways
STRIPE_KEY=your_stripe_key
STRIPE_SECRET=your_stripe_secret
PAYU_MERCHANT_KEY=your_payu_key
PAYU_SALT=your_payu_salt

# File Storage
FILESYSTEM_DISK=local

# Queue Configuration (database or redis)
QUEUE_CONNECTION=database
QUEUE_RETRY_AFTER=90

# Session & Cache
SESSION_DRIVER=file
CACHE_DRIVER=file

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=debug
```

#### CRM cross-access (`config/crm_access.php`)

Row-level visibility and temporary access grants are controlled in `config/crm_access.php` (parsed lists tolerate empty `.env` values with safe defaults). Add to `.env` as needed:

```env
# Comma-separated role IDs that bypass allocation (default: 1,17 — Super Admin, Admin)
CRM_ACCESS_EXEMPT_ROLE_IDS=1,17

# Optional: staff.id exempt list. Omit or leave blank so exempt mirrors approvers (see config/crm_access.php).
# CRM_ACCESS_EXEMPT_STAFF_IDS=36834,36524,36692,36483,36484,36718,36523,36836,36830

# Comma-separated staff.id values who may approve supervisor requests (plus all active role-1 staff)
CRM_ACCESS_APPROVER_STAFF_IDS=36834,36524,36692,36483,36484,36718,36523,36836,36830

# Roles that may only use quick access, not supervisor path (default: 14 — Calling Team)
CRM_ACCESS_QUICK_ONLY_ROLE_IDS=14

# When true, non-exempt staff only see allocated clients/leads (+ active grants)
CRM_ACCESS_STRICT_ALLOCATION=false

# Grant durations and caps
CRM_ACCESS_QUICK_GRANT_MINUTES=15
CRM_ACCESS_SUPERVISOR_GRANT_HOURS=24
CRM_ACCESS_MAX_PENDING_SUPERVISOR_REQUESTS=5
CRM_ACCESS_PENDING_TTL_DAYS=14
```

Full behaviour, HTTP surface, and QA checklist: **`docs/CROSS_ACCESS_IMPLEMENTATION_PLAN.md`**.

### Database

The application uses **PostgreSQL** as the primary database (default in `config/database.php`). MySQL is supported for legacy migration from existing MySQL installations. For development, you can use SQLite by changing `DB_CONNECTION` to `sqlite` in `.env`.

## Contributing

1. Clone [bansallawyers12/BansalLaw_CRM](https://github.com/bansallawyers12/BansalLaw_CRM) (or fork it if you use a fork workflow)
2. Create a feature branch
3. Make your changes
4. Run tests to ensure everything works
5. Submit a pull request

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Recent Changes

- **PostgreSQL**: Primary database is now PostgreSQL (default in config); MySQL supported for legacy migration.
- **Laravel 12**: Upgraded from Laravel 10; requires PHP 8.2+.
- **Matter-based tracking**: Legacy `applications` table removed; visa tracking is via `client_matters` (Matter model).
- **Flatpickr migration**: All date pickers now use Flatpickr (DD/MM/YYYY format). Bootstrap Datepicker removed.
- **Company Employer Sponsorship**: Full implementation including Trust entities, trading names, directors (with client/lead linking), nominations, sponsorship tracking, and per-section AJAX save.
- **Assignee action view**: Dedicated action page for assigned tasks.
- **CRM layouts**: Updated `crm_client_detail` and `crm_client_detail_dashboard` with Flatpickr components.
- **Vite build**: Frontend built with Vite; includes FullCalendar, Flatpickr, Signature Pad, Alpine.js, Tailwind.
- **Cross-access & allocated visibility**: `StaffClientVisibility`, `CrmAccessService`, `client_access_grants`, header search locked rows + modal, approver bell mini-queue, grants dashboard and CSV, booking/email/document gates when strict allocation is enabled; scheduled `access:expire-grants`.

For detailed Company Employer Sponsorship implementation notes, see `docs/COMPANY_EMPLOYER_SPONSORSHIP_IMPLEMENTATION_PLAN.md`.

For cross-access product rules, routes, and rollout status, see **`docs/CROSS_ACCESS_IMPLEMENTATION_PLAN.md`**.

## Important Notes

- The application is optimized to work with XAMPP (Apache) on Windows; PostgreSQL must be installed separately
- Run `npm run copy:flatpickr` after `npm install` so date pickers work correctly
- Python API service (`python_services/`) handles DOCX to PDF conversion; optional if not using DOCX uploads
- PostgreSQL is the primary database; ensure `pdo_pgsql` PHP extension is enabled
- The application uses Laravel's built-in authentication with multi-role support
- Document storage is handled locally by default, with optional S3 integration
- Comprehensive logging is available in `storage/logs/laravel.log`
- Email integration supports both SMTP and IMAP protocols
- Payment gateways (Stripe, PayU) need to be configured for online payments
- SMS providers (Twilio, Cellcast) need to be configured for SMS notifications
- Client portal provides secure access for clients to track their applications
- **CRM cross-access**: ensure `php artisan schedule:run` (or cron) runs in production so `access:expire-grants` executes; set `CRM_ACCESS_STRICT_ALLOCATION=true` only after UAT (see implementation plan)

## Troubleshooting

### Database Issues
- **Connection refused**: Ensure PostgreSQL is running (XAMPP does not include PostgreSQL; install separately)
- **pdo_pgsql not loaded**: Enable the `pdo_pgsql` extension in `php.ini`; run `php -m` and confirm `pdo_pgsql` appears in the list
- **Access denied**: Verify database credentials in `.env` file
- **Table not found**: Run `php artisan migrate --seed` to create tables

### PDF / DOCX Conversion Issues
- **DOCX conversion fails**: Ensure the Python conversion service is running (`python_services/`); set `PYTHON_CONVERTER_URL` in `.env` if not using default `http://localhost:5000`
- **Permission denied**: Check storage folder permissions (775 or 777)

### Email Issues
- **Emails not sending**: Verify MAIL_* configuration in `.env`
- **SMTP authentication failed**: Use app-specific passwords for Gmail
- **Emails going to spam**: Configure SPF and DKIM records for your domain

### Date Picker (Flatpickr) Issues
- **Date pickers not appearing**: Run `npm run copy:flatpickr` to copy Flatpickr assets to `public/js/` and `public/css/`
- **"flatpickr is not defined"**: Ensure `flatpickr-assets` and `flatpickr-scripts` components are included in the layout (see `layouts/crm_client_detail.blade.php`)

### Performance Issues
- **Slow page loads**: Run `php artisan optimize` and `npm run build`
- **Queue not processing**: Ensure `php artisan queue:work` is running
- **High memory usage**: Increase PHP memory_limit in php.ini

## Security Best Practices

- **Never commit `.env` file** - Contains sensitive credentials
- **Use strong passwords** - Enforce password policies for users
- **Enable HTTPS** - Use SSL certificates in production
- **Regular backups** - Automated daily database backups recommended
- **Update dependencies** - Run `composer update` and `npm update` regularly
- **Role-based access** - Limit admin access to trusted staff only
- **Two-factor authentication** - Consider implementing 2FA for admin accounts
- **Data encryption** - Sensitive client data should be encrypted at rest

## Backup & Data Management

### What to Backup
- **Database**: PostgreSQL database dumps (daily recommended)
- **Storage folder**: `storage/app/public/` containing:
  - Client documents
  - Agreements
  - Attachments
  - Generated PDFs
- **Environment file**: `.env` (store securely, not in repository)
- **Uploaded files**: All content in `public/` except framework files

### Backup Commands
```powershell
# PostgreSQL database backup (PowerShell)
$date = Get-Date -Format "yyyyMMdd"
pg_dump -U postgres bansal_law_crm | Out-File "backup_$date.sql"

# Storage backup (use robocopy or 7-Zip on Windows)
robocopy storage\app\public "backup_storage_$date" /E
```

### Data Retention
- Keep client records for minimum 7 years (immigration regulations)
- Archive old matters after case closure
- Regularly clean up old logs and temporary files

## FAQ

**Q: Nothing appears after login**
- Ensure migrations have been run: `php artisan migrate --seed`
- Check that PostgreSQL is running (and Apache if using XAMPP)
- Verify `.env` database configuration and `pdo_pgsql` extension

**Q: PDF/DOCX conversion not working**
- Ensure the Python conversion service (`python_services/`) is running and reachable at `PYTHON_CONVERTER_URL`
- Check `storage/logs/laravel.log` for conversion errors

**Q: Client portal not accessible**
- Ensure client has portal access enabled in their profile
- Client must use the email address registered in the system
- Check that routes are properly configured in `routes/web.php`

**Q: Payment gateway errors**
- Verify Stripe/PayU credentials in `.env`
- Ensure SSL is enabled for production payments
- Test with sandbox/test keys before going live

**Q: Can I customize invoice templates?**
- Edit the invoice email template at `resources/views/emails/geninvoice.blade.php`
- Receipt email templates: `resources/views/emails/reciept.blade.php`, `genofficereceipt.blade.php`, `genclientfundreceipt.blade.php`

**Q: How to add new visa types?**
- Go to Admin → Settings → Visa Types
- Add new visa type with required documents checklist

**Q: How to export client data?**
- Use Reports section to generate and export data
- Available formats: PDF, Excel, CSV