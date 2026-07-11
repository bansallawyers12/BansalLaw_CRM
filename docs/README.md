# Bansal Law CRM — Documentation Index

## Operations & integration

| Document | Description |
|----------|-------------|
| [AWS_SES_CRM_EMAIL_INSTRUCTIONS.md](AWS_SES_CRM_EMAIL_INSTRUCTIONS.md) | Outbound email: AWS SES + Zoho SMTP setup, `.env`, routing, troubleshooting |
| [CRM_EMAIL_S3_IMPLEMENTATION.md](CRM_EMAIL_S3_IMPLEMENTATION.md) | Archiving sent emails (HTML + attachments) to S3 |
| [../python_services/README.md](../python_services/README.md) | Python microservices (email upload, DOCX→PDF) |
| [../python_services/LINUX_DEPLOYMENT.md](../python_services/LINUX_DEPLOYMENT.md) | Linux deployment for Python services |
| [../python_services/QUICK_REFERENCE.md](../python_services/QUICK_REFERENCE.md) | Python services command reference |

## Product & schema reference

| Document | Description |
|----------|-------------|
| [CROSS_ACCESS_IMPLEMENTATION_PLAN.md](CROSS_ACCESS_IMPLEMENTATION_PLAN.md) | Allocated-only visibility, quick/supervisor access grants |
| [CLIENT_INTAKE_FORM_INSTRUCTIONS.md](CLIENT_INTAKE_FORM_INSTRUCTIONS.md) | Website lead form JSON → CRM import |
| [theme.md](theme.md) | UI colour tokens (Powder Blue & Soft Gold) |
| [FONT_AWESOME_MIGRATION.md](FONT_AWESOME_MIGRATION.md) | Font Awesome 6 migration (complete) |

## Database column guides

| Document | Description |
|----------|-------------|
| [ADMINS_TABLE_COLUMNS.md](ADMINS_TABLE_COLUMNS.md) | `admins` table column removal plan |
| [BOOKING_APPOINTMENTS_TABLE_COLUMNS.md](BOOKING_APPOINTMENTS_TABLE_COLUMNS.md) | `booking_appointments` column guide |
| [DOCUMENTS_TABLE_COLUMNS.md](DOCUMENTS_TABLE_COLUMNS.md) | `documents` table column reference |

## Migration / rename plans (partially complete)

| Document | Status |
|----------|--------|
| [APPLICATION_TO_MATTER_MIGRATION_PLAN.md](APPLICATION_TO_MATTER_MIGRATION_PLAN.md) | Remaining application→matter terminology work |
| [PLAN_USER_TO_CLIENT_STAFF_RENAME.md](PLAN_USER_TO_CLIENT_STAFF_RENAME.md) | Phases 1–3 done; DB renames 4–5 planned |

## Removed / superseded

- ~~`SENDGRID_CRM_EMAIL_INSTRUCTIONS.md`~~ — replaced by **AWS_SES_CRM_EMAIL_INSTRUCTIONS.md** (SendGrid removed from codebase).
