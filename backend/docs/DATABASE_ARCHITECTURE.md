# HRM Plus — Database architecture

**Version:** 1.0.0 (unreleased) · **Last updated:** 2026-11-12 · **Engine:** MySQL 8 (InnoDB, `utf8mb4`)

The schema is created by the Laravel migrations in `backend/database/migrations` (34 module migrations, the
Phase 11 index migration `2026_11_22_100000_add_performance_indexes`, and `2026_11_23_100000_add_web_portal_check_in` added for portal check-in). At the time of writing it had **176
tables** and **419 foreign keys**. The ER diagrams in this document were generated from the live schema
(`information_schema`) of a fresh install, so they show real table and column names. Diagrams list the primary
key, foreign keys, unique keys and a selection of the most important business columns — not every column.

---

## Contents

1. [Design rules](#1-design-rules)
2. [Module overview](#2-module-overview)
3. [Overview ERD](#3-overview-erd)
4. [Module ERDs](#4-module-erds)
5. [Polymorphic links and cross-module contracts](#5-polymorphic-links-and-cross-module-contracts)
6. [Framework and infrastructure tables](#6-framework-and-infrastructure-tables)
7. [Maintenance notes](#7-maintenance-notes)

---

## 1. Design rules

| Rule | How it is implemented |
|---|---|
| **Referential integrity** | Foreign keys everywhere (419), `restrict` on history-bearing parents; lookup records in use are deactivated (`is_active`) instead of deleted |
| **Two account types** | One `users` table with `account_type` (`admin` / `employee`) and a nullable `employee_id`. Admin RBAC via Spatie Permission (`roles`, `permissions`, `model_has_roles`, `role_has_permissions`) + `role_default_grants` (what `permissions:sync` offered) |
| **Data scope** | `admin_data_scopes` (`user_id`, `scope_type` branch / department / employee_group, `scope_id`). Applied by an Eloquent global scope (`HasDataScope`) on employee-owned models, so web, reports and exports share one rule. History tables carry **snapshot** `branch_id` / `department_id` columns (e.g. `attendance_days`, `payroll_items`, `payslips`, `performance_reviews`, `hr_requests`, `disciplinary_cases`, `training_enrolments`) so that a record stays in the scope it belonged to |
| **Effective dating** | `employee_assignments` (one row per change: `change_type`, `effective_date`, branch, department, team, position, job title, grade, manager, employment type, cost centre, status, reason, `applied_at` / `cancelled_at`), `employee_salaries` (`change_type`, `effective_date`, `basic_salary`, `previous_salary`, `pay_basis`), `attendance_policy_versions` + `attendance_policy_assignments`, `schedule_assignments`, `leave_policies` (`effective_from/to`), `salary_structure_versions` + `salary_structure_assignments`, `statutory_rule_versions`, `attendance_work_location_rules`, `pay_entries` (`start_date/end_date`). The current values on `employees` are a **mirror** for display and filtering; future-dated rows are applied by `employees:apply-scheduled-changes` |
| **Versions are never edited** | Attendance policy versions, salary structure versions and statutory rule versions are closed and superseded by a new version; calculations store the version id they used |
| **Raw events vs processed data** | `attendance_raw_events` (every input from device, mobile, manual, import — unique `idempotency_key`, never edited; voided rows stay with `voided_at` / reason) → `attendance_punches` → `attendance_days` (unique `employee_id + work_date + shift_key`, `calculation` JSON snapshot + `calculation_hash` + `attendance_policy_version_id`). Device-side metadata in `attendance_device_events`, mobile / web-portal check-in metadata in `attendance_mobile_attempts` (1:1 with the raw event; `channel` = `mobile` or `web`) |
| **Locked periods** | `attendance_period_locks`; changes to locked days become `attendance_adjustments` rows instead of editing the day |
| **Immutable ledgers** | `leave_balance_transactions` (balance = sum; idempotent `reference`), `loan_transactions`, `payslip_payments`, `asset_events`, `hr_request_events`, `application_stage_changes`, `performance_goal_updates`, `approval_actions`, `audit_logs` — append-only |
| **Payroll immutability** | `payroll_runs` state machine; `payroll_items` store `inputs` snapshot, quantities, rule versions, totals and a SHA-256 `hash`; `payroll_item_lines` each with `source`, sourceable morph and explanation. Finalised runs are never updated; corrections are `payroll_adjustments`; full reversal = a mirror run with negative amounts. Double pay is prevented by the unique key `(payroll_period_id, employee_id, pay_slot)` |
| **Money** | `DECIMAL` only — 113 decimal columns, **no FLOAT / DOUBLE** in the schema. PHP arithmetic with bcmath on strings (scale 6, rounding once per line) |
| **Encryption of sensitive columns** | Laravel `encrypted` casts (app key): `employees.national_id`, `employees.passport_number` (+ keyed `*_hash` columns for exact search / uniqueness), `employee_bank_accounts.account_number`, `iban` (+ `iban_hash`), `attendance_devices.credentials`, `face_enrolments.provider_reference`, `hr_profile_changes.old_value/new_value`, secret rows in `settings`. Device push tokens stored as SHA-256 hashes; password reset OTPs hashed in the cache |
| **Biometrics** | No face images or templates in the database: `face_enrolments` holds status, dates and an encrypted provider reference; optional files are encrypted on the private `biometric_files` disk; `face_verifications` stores results only |
| **Files** | Only paths in the database (`*_path`, `file_name`, `file_mime`, `file_size`); files live on private local disks under `storage/app/private/*` (employees, recruitment, biometric, training, disciplinary, hr-requests, announcements, payslips) and are served only by authorised, logged controllers |
| **Soft deletes** | Used sparingly: `users`, `employee_documents`, `candidates` (plus the `Auditable` trait). History is preserved by status columns, versions and ledgers rather than soft deletes |
| **Audit** | `audit_logs` (user, event, auditable morph, `old_values` / `new_values` JSON, URL, IP, user agent, meta — identity / bank values redacted) and `login_histories` (area admin / portal / api, success, reason, IP) |
| **Approvals** | One generic engine: `approval_workflows` → `approval_workflow_steps` (`sequence`, approver by permission, role or user) → `approval_requests` (morph to the approvable record, `current_step_id`, status) → `approval_actions` |
| **Bilingual reference data** | `name_ar` / `name_en` (and `title_ar/en`, `description_ar/en`) on reference tables |
| **JSON** | Used for snapshots and flexible payloads only (calculation snapshots, raw device payloads, flags, notification data, report filters) — not as a substitute for relations |
| **Number generation** | `number_sequences` (race-safe counters) for employee numbers and document references (`PR-YYYY-NNNNN`, `PS-YYYY-NNNNN`, …) |

---

## 2. Module overview

| Module | Main tables | Notes |
|---|---|---|
| Access & system | `users`, `roles`, `permissions`, `model_has_roles`, `role_has_permissions`, `model_has_permissions`, `role_default_grants`, `admin_data_scopes`, `login_histories`, `audit_logs`, `settings`, `personal_access_tokens`, `sessions`, `password_reset_tokens` | Settings are key/value rows grouped by `group`, typed by `config/settings.php` |
| Approvals | `approval_workflows`, `approval_workflow_steps`, `approval_requests`, `approval_actions` | 16 workflows seeded |
| Organisation | `companies`, `branches`, `branch_locations` (lat / lng / radius), `departments` (self-referencing `parent_id`), `teams`, `positions` (seats, `reports_to_position_id`), `job_titles`, `job_grades` (salary band), `employment_types`, `cost_centers` | |
| Employees | `employees`, `employee_assignments`, `employee_salaries`, `employee_contracts`, `employee_documents`, `employee_bank_accounts`, `employee_emergency_contacts`, `employee_groups`, `employee_group_members`, `employee_custom_fields` / `_options` / `_values`, `number_sequences` | `employees.manager_id` self-reference; lineage to `candidates` / `job_applications` |
| Recruitment | `vacancies`, `recruitment_stages`, `candidates`, `candidate_tags`, `candidate_tag`, `candidate_notes`, `candidate_attachments`, `job_applications`, `application_stage_changes`, `interviews`, `interview_interviewers`, `interview_scorecards`, `interview_scores`, `evaluation_forms`, `evaluation_criteria`, `job_offers` | |
| Lifecycle | `onboarding_templates`, `onboarding_template_tasks`, `onboardings`, `onboarding_tasks`, `offboarding_cases`, `offboarding_items` | Offboarding items link to asset assignments, loans, settlements (morph) |
| Attendance core | `attendance_raw_events`, `attendance_punches`, `attendance_days`, `attendance_adjustments`, `attendance_corrections`, `attendance_manual_batches`, `attendance_manual_entries`, `attendance_period_locks`, `attendance_statuses`, `attendance_policies`, `attendance_policy_versions`, `attendance_policy_assignments` | |
| Attendance capture | `attendance_devices`, `attendance_device_users`, `attendance_device_sync_logs`, `attendance_device_events`, `attendance_mobile_attempts`, `attendance_work_location_rules`, `face_consents`, `face_enrolments`, `face_verifications` | |
| Shifts & schedules | `shifts`, `shift_breaks`, `work_schedules`, `work_schedule_days`, `schedule_assignments`, `schedule_overrides`, `schedule_change_requests`, `holidays` | |
| Leave | `leave_types`, `leave_policies`, `leave_requests`, `leave_request_days`, `leave_request_attachments`, `leave_balance_transactions` | |
| Payroll components | `salary_components`, `salary_structures`, `salary_structure_versions`, `salary_structure_lines`, `salary_structure_assignments`, `pay_entries`, `pay_entry_batches`, `pay_entry_attachments`, `statutory_rule_sets`, `statutory_rule_versions`, `statutory_rule_brackets` | Allowances and deductions are `pay_entries` with `kind` |
| Loans | `employee_loans`, `loan_instalments`, `loan_transactions`, `loan_changes` | Loans and salary advances (`type`) |
| Payroll runs | `payroll_periods`, `payroll_runs`, `payroll_items`, `payroll_item_lines`, `payroll_adjustments`, `payslips`, `payslip_payments` | |
| Performance | `performance_cycles`, `performance_cycle_competencies`, `performance_reviews`, `performance_review_competencies`, `performance_goals`, `performance_goal_updates`, `competencies`, `rating_scales`, `rating_scale_levels` | |
| Training | `training_providers`, `training_programmes`, `training_courses`, `training_course_skills`, `training_sessions`, `training_enrolments`, `training_attendances`, `training_certificates`, `training_budgets`, `skills`, `employee_skills` | |
| Assets | `asset_categories`, `assets`, `asset_assignments`, `asset_events` | |
| Disciplinary | `violation_types`, `disciplinary_cases`, `disciplinary_case_notes`, `disciplinary_evidence`, `disciplinary_actions` | `disciplinary_actions.pay_entry_id` for salary deductions |
| HR requests | `hr_request_categories`, `hr_request_category_fields`, `hr_requests`, `hr_request_field_values`, `hr_request_messages` (`type`: employee-visible reply or internal note), `hr_request_attachments` (`is_letter`), `hr_request_events`, `hr_profile_changes`, `hr_letter_templates` | |
| Announcements | `announcements`, `announcement_targets`, `announcement_reads`, `announcement_attachments` | |
| Notifications | `notifications` (Laravel), `notification_templates`, `notification_preferences`, `notification_deliveries`, `notification_dispatches`, `device_tokens` | |
| Dashboard & reports | `dashboard_preferences`, `report_exports` | |

---

## 3. Overview ERD

Core entities and the relationships between modules (audit "created_by / decided_by" links to `users` and most
snapshot `branch_id` / `department_id` links are omitted for readability).

```mermaid
erDiagram
    branches ||--o{ attendance_devices : "branch_id"
    branches ||--o{ departments : "branch_id"
    branches ||--o{ employee_assignments : "branch_id"
    branches ||--o{ employees : "branch_id"
    branches ||--o{ payroll_runs : "branch_id"
    candidates ||--o{ employees : "candidate_id"
    candidates ||--o{ job_applications : "candidate_id"
    departments ||--o{ departments : "parent_id"
    departments ||--o{ employee_assignments : "department_id"
    departments ||--o{ employees : "department_id"
    departments ||--o{ positions : "department_id"
    departments ||--o{ vacancies : "department_id"
    employees ||--o{ asset_assignments : "employee_id"
    employees ||--o{ attendance_days : "employee_id"
    employees ||--o{ attendance_raw_events : "employee_id"
    employees ||--o{ candidates : "employee_id"
    employees ||--o{ candidates : "referred_by_employee_id"
    employees ||--o{ disciplinary_cases : "employee_id"
    employees ||--o{ employee_assignments : "employee_id"
    employees ||--o{ employee_loans : "employee_id"
    employees ||--o{ employee_salaries : "employee_id"
    employees ||--o{ employees : "manager_id"
    employees ||--o{ hr_requests : "employee_id"
    employees ||--o{ job_applications : "employee_id"
    employees ||--o{ leave_balance_transactions : "employee_id"
    employees ||--o{ leave_requests : "employee_id"
    employees ||--o{ pay_entries : "employee_id"
    employees ||--o{ payroll_items : "employee_id"
    employees ||--o{ payslips : "employee_id"
    employees ||--o{ performance_reviews : "employee_id"
    employees ||--o{ performance_reviews : "reviewer_id"
    employees ||--o{ training_enrolments : "employee_id"
    employees ||--o{ users : "employee_id"
    job_applications ||--o{ employees : "job_application_id"
    leave_requests ||--o{ leave_balance_transactions : "leave_request_id"
    leave_types ||--o{ leave_balance_transactions : "leave_type_id"
    leave_types ||--o{ leave_requests : "leave_type_id"
    payroll_items ||--o{ payslips : "payroll_item_id"
    payroll_periods ||--o{ payroll_items : "payroll_period_id"
    payroll_periods ||--o{ payroll_runs : "payroll_period_id"
    payroll_periods ||--o{ payslips : "payroll_period_id"
    payroll_runs ||--o{ payroll_items : "payroll_run_id"
    payroll_runs ||--o{ payroll_runs : "corrects_run_id"
    payroll_runs ||--o{ payslips : "payroll_run_id"
    positions ||--o{ employee_assignments : "position_id"
    positions ||--o{ employees : "position_id"
    positions ||--o{ vacancies : "position_id"
    shifts ||--o{ attendance_days : "shift_id"
    shifts ||--o{ attendance_raw_events : "shift_id"
    users ||--o{ approval_requests : "requested_by"
    users ||--o{ audit_logs : "user_id"
    vacancies ||--o{ job_applications : "vacancy_id"
```

---

## 4. Module ERDs

One diagram per module (tables of other modules appear when they are the target of a foreign key). `bigint` ids, `decimal14_2` = DECIMAL(14,2).

### 4.1 Access & system

```mermaid
erDiagram
    approval_requests ||--o{ approval_actions : "approval_request_id"
    approval_workflow_steps ||--o{ approval_actions : "approval_workflow_step_id"
    approval_workflow_steps ||--o{ approval_requests : "current_step_id"
    approval_workflows ||--o{ approval_requests : "approval_workflow_id"
    approval_workflows ||--o{ approval_workflow_steps : "approval_workflow_id"
    permissions ||--o{ role_has_permissions : "permission_id"
    roles ||--o{ approval_workflow_steps : "approver_role_id"
    roles ||--o{ model_has_roles : "role_id"
    roles ||--o{ role_default_grants : "role_id"
    roles ||--o{ role_has_permissions : "role_id"
    users ||--o{ admin_data_scopes : "user_id"
    users ||--o{ approval_workflow_steps : "approver_user_id"
    users ||--o{ audit_logs : "user_id"
    users ||--o{ login_histories : "user_id"
    users {
        bigint id PK
        string email UK
        timestamp email_verified_at
        string account_type
        string status
        bigint employee_id FK
        timestamp last_login_at
        timestamp password_changed_at
    }
    roles {
        bigint id PK
        timestamp created_at
        timestamp updated_at
    }
    permissions {
        bigint id PK
        timestamp created_at
        timestamp updated_at
    }
    model_has_roles {
        bigint role_id FK
        string model_type
    }
    role_has_permissions {
        bigint permission_id FK
        bigint role_id FK
    }
    admin_data_scopes {
        bigint id PK
        bigint user_id FK
        string scope_type
        timestamp created_at
        timestamp updated_at
    }
    login_histories {
        bigint id PK
        bigint user_id FK
        string email
        timestamp created_at
    }
    audit_logs {
        bigint id PK
        bigint user_id FK
        string auditable_type
        timestamp created_at
    }
    settings {
        bigint id PK
        string key UK
        timestamp created_at
        timestamp updated_at
    }
    approval_workflows {
        bigint id PK
        string key UK
        timestamp created_at
        timestamp updated_at
    }
    approval_workflow_steps {
        bigint id PK
        bigint approval_workflow_id FK
        string approver_type
        bigint approver_user_id FK
        bigint approver_role_id FK
        timestamp created_at
        timestamp updated_at
    }
    approval_requests {
        bigint id PK
        bigint approval_workflow_id FK
        string approvable_type
        bigint requested_by FK
        string status
        bigint current_step_id FK
        timestamp completed_at
        timestamp created_at
    }
    approval_actions {
        bigint id PK
        bigint approval_request_id FK
        bigint approval_workflow_step_id FK
        bigint acted_by FK
        timestamp created_at
    }
    role_default_grants {
        bigint role_id FK
        timestamp granted_at
        timestamp manually_granted_at
    }
    personal_access_tokens {
        bigint id PK
        string tokenable_type
        string token UK
        timestamp last_used_at
        timestamp expires_at
        timestamp created_at
        timestamp updated_at
    }
```

### 4.2 Organisation

```mermaid
erDiagram
    branches ||--o{ branch_locations : "branch_id"
    branches ||--o{ departments : "branch_id"
    cost_centers ||--o{ cost_centers : "parent_id"
    cost_centers ||--o{ departments : "cost_center_id"
    departments ||--o{ departments : "parent_id"
    departments ||--o{ positions : "department_id"
    departments ||--o{ teams : "department_id"
    job_grades ||--o{ positions : "job_grade_id"
    job_titles ||--o{ positions : "job_title_id"
    positions ||--o{ branches : "manager_position_id"
    positions ||--o{ departments : "head_position_id"
    positions ||--o{ positions : "reports_to_position_id"
    companies {
        bigint id PK
        string name_en
        string legal_name_en
        string tax_number
        string email
        string country_code
        timestamp created_at
        timestamp updated_at
    }
    branches {
        bigint id PK
        string code UK
        string name_en
        string email
        string country_code
        bigint manager_position_id FK
        timestamp created_at
        timestamp updated_at
    }
    branch_locations {
        bigint id PK
        bigint branch_id FK
        string name_en
        timestamp created_at
        timestamp updated_at
    }
    departments {
        bigint id PK
        string code UK
        string name_en
        bigint parent_id FK
        bigint branch_id FK
        bigint cost_center_id FK
        bigint head_position_id FK
        timestamp created_at
    }
    teams {
        bigint id PK
        string code UK
        string name_en
        bigint department_id FK
        bigint leader_employee_id FK
        timestamp created_at
        timestamp updated_at
    }
    positions {
        bigint id PK
        string code UK
        string name_en
        bigint department_id FK
        bigint job_title_id FK
        bigint job_grade_id FK
        bigint reports_to_position_id FK
        timestamp created_at
    }
    job_titles {
        bigint id PK
        string code UK
        string name_en
        timestamp created_at
        timestamp updated_at
    }
    job_grades {
        bigint id PK
        string code UK
        string name_en
        timestamp created_at
        timestamp updated_at
    }
    employment_types {
        bigint id PK
        string code UK
        string name_en
        timestamp created_at
        timestamp updated_at
    }
    cost_centers {
        bigint id PK
        string code UK
        string name_en
        bigint parent_id FK
        timestamp created_at
        timestamp updated_at
    }
```

### 4.3 Employees

```mermaid
erDiagram
    employee_contracts ||--o{ employee_contracts : "renewal_of_id"
    employee_custom_fields ||--o{ employee_custom_field_values : "custom_field_id"
    employee_groups ||--o{ employee_group_members : "employee_group_id"
    employees ||--o{ employee_assignments : "employee_id"
    employees ||--o{ employee_assignments : "manager_id"
    employees ||--o{ employee_bank_accounts : "employee_id"
    employees ||--o{ employee_contracts : "employee_id"
    employees ||--o{ employee_custom_field_values : "employee_id"
    employees ||--o{ employee_documents : "employee_id"
    employees ||--o{ employee_emergency_contacts : "employee_id"
    employees ||--o{ employee_group_members : "employee_id"
    employees ||--o{ employee_salaries : "employee_id"
    employees ||--o{ employees : "manager_id"
    employees ||--o{ users : "employee_id"
    employees {
        bigint id PK
        string employee_number UK
        bigint candidate_id FK
        bigint job_application_id FK
        string name_en
        date date_of_birth
        string marital_status
        string national_id_hash UK
    }
    employee_assignments {
        bigint id PK
        bigint employee_id FK
        string change_type
        date effective_date
        bigint branch_id FK
        bigint department_id FK
        bigint team_id FK
        bigint position_id FK
    }
    employee_salaries {
        bigint id PK
        bigint employee_id FK
        string change_type
        date effective_date
        decimal14_2 basic_salary
        timestamp applied_at
        timestamp cancelled_at
        bigint created_by FK
    }
    employee_contracts {
        bigint id PK
        bigint employee_id FK
        string reference
        string contract_type
        date start_date
        date end_date
        bigint renewal_of_id FK
        bigint created_by FK
    }
    employee_documents {
        bigint id PK
        bigint employee_id FK
        date issue_date
        date expiry_date
        bigint uploaded_by FK
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }
    employee_bank_accounts {
        bigint id PK
        bigint employee_id FK
        text account_number
        string iban_hash
        string swift_code
        timestamp created_at
        timestamp updated_at
    }
    employee_emergency_contacts {
        bigint id PK
        bigint employee_id FK
        timestamp created_at
        timestamp updated_at
    }
    employee_groups {
        bigint id PK
        string code UK
        string name_en
        timestamp created_at
        timestamp updated_at
    }
    employee_group_members {
        bigint employee_group_id FK
        bigint employee_id FK
        timestamp created_at
    }
    employee_custom_fields {
        bigint id PK
        string code UK
        string name_en
        string type
        timestamp created_at
        timestamp updated_at
    }
    employee_custom_field_values {
        bigint id PK
        bigint employee_id FK
        bigint custom_field_id FK
        decimal18_4 value_number
        date value_date
        timestamp created_at
        timestamp updated_at
    }
    users {
        bigint id PK
        string email UK
        timestamp email_verified_at
        string account_type
        string status
        bigint employee_id FK
        timestamp last_login_at
        timestamp password_changed_at
    }
```

### 4.4 Recruitment & lifecycle

```mermaid
erDiagram
    candidates ||--o{ employees : "candidate_id"
    candidates ||--o{ job_applications : "candidate_id"
    employees ||--o{ candidates : "employee_id"
    employees ||--o{ candidates : "referred_by_employee_id"
    employees ||--o{ job_applications : "employee_id"
    employees ||--o{ offboarding_cases : "employee_id"
    employees ||--o{ onboardings : "employee_id"
    evaluation_forms ||--o{ evaluation_criteria : "evaluation_form_id"
    evaluation_forms ||--o{ interviews : "evaluation_form_id"
    evaluation_forms ||--o{ vacancies : "evaluation_form_id"
    interviews ||--o{ interview_interviewers : "interview_id"
    interviews ||--o{ interview_scorecards : "interview_id"
    job_applications ||--o{ application_stage_changes : "job_application_id"
    job_applications ||--o{ employees : "job_application_id"
    job_applications ||--o{ interviews : "job_application_id"
    job_applications ||--o{ job_offers : "job_application_id"
    job_applications ||--o{ onboardings : "job_application_id"
    offboarding_cases ||--o{ offboarding_items : "offboarding_case_id"
    onboarding_templates ||--o{ onboardings : "onboarding_template_id"
    onboardings ||--o{ onboarding_tasks : "onboarding_id"
    recruitment_stages ||--o{ application_stage_changes : "from_stage_id"
    recruitment_stages ||--o{ application_stage_changes : "to_stage_id"
    recruitment_stages ||--o{ job_applications : "stage_id"
    vacancies ||--o{ job_applications : "vacancy_id"
    vacancies ||--o{ recruitment_stages : "vacancy_id"
    vacancies {
        bigint id PK
        string reference UK
        bigint branch_id FK
        bigint department_id FK
        bigint position_id FK
        bigint job_title_id FK
        bigint employment_type_id FK
        string status
    }
    recruitment_stages {
        bigint id PK
        bigint vacancy_id FK
        string code
        string name_en
        bool notify_candidate
        timestamp created_at
        timestamp updated_at
    }
    candidates {
        bigint id PK
        string name_en
        string email
        date date_of_birth
        string source
        string source_detail
        bigint referred_by_employee_id FK
        timestamp consent_at
    }
    job_applications {
        bigint id PK
        bigint candidate_id FK
        bigint vacancy_id FK
        bigint stage_id FK
        string status
        string source
        timestamp applied_at
        timestamp stage_changed_at
    }
    application_stage_changes {
        bigint id PK
        bigint job_application_id FK
        bigint from_stage_id FK
        bigint to_stage_id FK
        bigint changed_by FK
        timestamp created_at
    }
    interviews {
        bigint id PK
        bigint job_application_id FK
        string type
        datetime scheduled_at
        bigint evaluation_form_id FK
        string status
        timestamp reminder_sent_at
        bigint created_by FK
    }
    interview_interviewers {
        bigint interview_id FK
        bigint user_id FK
    }
    interview_scorecards {
        bigint id PK
        bigint interview_id FK
        bigint user_id FK
        timestamp submitted_at
        timestamp created_at
        timestamp updated_at
    }
    evaluation_forms {
        bigint id PK
        string name_en
        timestamp created_at
        timestamp updated_at
    }
    evaluation_criteria {
        bigint id PK
        bigint evaluation_form_id FK
        string name_en
        timestamp created_at
        timestamp updated_at
    }
    job_offers {
        bigint id PK
        string reference UK
        bigint job_application_id FK
        bigint position_id FK
        bigint job_title_id FK
        bigint employment_type_id FK
        decimal14_2 basic_salary
        date start_date
    }
    onboardings {
        bigint id PK
        bigint employee_id FK
        bigint onboarding_template_id FK
        bigint job_application_id FK
        date start_date
        string status
        timestamp completed_at
        bigint created_by FK
    }
    onboarding_tasks {
        bigint id PK
        bigint onboarding_id FK
        date due_date
        string status
        timestamp completed_at
        bigint completed_by FK
        timestamp created_at
        timestamp updated_at
    }
    onboarding_templates {
        bigint id PK
        string name_en
        bigint department_id FK
        bigint position_id FK
        bigint employment_type_id FK
        timestamp created_at
        timestamp updated_at
    }
    offboarding_cases {
        bigint id PK
        bigint employee_id FK
        string type
        string source
        bigint employee_contract_id FK
        date notice_date
        date last_working_date
        string status
    }
    offboarding_items {
        bigint id PK
        bigint offboarding_case_id FK
        string itemable_type
        string status
        timestamp completed_at
        bigint completed_by FK
        timestamp created_at
        timestamp updated_at
    }
    employees {
        bigint id PK
        string employee_number UK
        bigint candidate_id FK
        bigint job_application_id FK
        string name_en
        date date_of_birth
        string marital_status
        string national_id_hash UK
    }
```

### 4.5 Attendance

```mermaid
erDiagram
    attendance_days ||--o{ attendance_adjustments : "attendance_day_id"
    attendance_days ||--o{ attendance_punches : "attendance_day_id"
    attendance_manual_batches ||--o{ attendance_manual_entries : "attendance_manual_batch_id"
    attendance_period_locks ||--o{ attendance_days : "attendance_period_lock_id"
    attendance_policies ||--o{ attendance_policy_assignments : "attendance_policy_id"
    attendance_policies ||--o{ attendance_policy_versions : "attendance_policy_id"
    attendance_policy_versions ||--o{ attendance_adjustments : "attendance_policy_version_id"
    attendance_policy_versions ||--o{ attendance_days : "attendance_policy_version_id"
    attendance_raw_events ||--o{ attendance_punches : "attendance_raw_event_id"
    attendance_statuses ||--o{ attendance_corrections : "attendance_status_id"
    attendance_statuses ||--o{ attendance_days : "attendance_status_id"
    attendance_statuses ||--o{ attendance_manual_entries : "attendance_status_id"
    attendance_statuses ||--o{ attendance_raw_events : "attendance_status_id"
    employees ||--o{ attendance_adjustments : "employee_id"
    employees ||--o{ attendance_corrections : "employee_id"
    employees ||--o{ attendance_days : "employee_id"
    employees ||--o{ attendance_manual_entries : "employee_id"
    employees ||--o{ attendance_punches : "employee_id"
    employees ||--o{ attendance_raw_events : "employee_id"
    attendance_raw_events {
        bigint id PK
        string kind
        string source
        bigint employee_id FK
        datetime occurred_at
        date work_date
        bigint shift_id FK
        bigint attendance_status_id FK
    }
    attendance_punches {
        bigint id PK
        bigint attendance_raw_event_id FK
        bigint employee_id FK
        datetime punched_at
        string source
        bigint attendance_day_id FK
        timestamp created_at
        timestamp updated_at
    }
    attendance_days {
        bigint id PK
        bigint employee_id FK
        date work_date
        bigint shift_id FK
        bigint branch_id FK
        bigint department_id FK
        string status
        bigint attendance_status_id FK
    }
    attendance_adjustments {
        bigint id PK
        bigint attendance_day_id FK
        bigint employee_id FK
        date work_date
        string status_before
        string status_after
        bigint attendance_policy_version_id FK
        string calculation_hash
    }
    attendance_corrections {
        bigint id PK
        bigint employee_id FK
        date work_date
        bigint shift_id FK
        datetime check_in_at
        datetime check_out_at
        bigint attendance_status_id FK
        string source
    }
    attendance_manual_batches {
        bigint id PK
        string source
        date work_date
        string status
        bigint created_by FK
        timestamp submitted_at
        timestamp applied_at
        timestamp created_at
    }
    attendance_manual_entries {
        bigint id PK
        bigint attendance_manual_batch_id FK
        bigint employee_id FK
        date work_date
        bigint shift_id FK
        datetime check_in_at
        datetime check_out_at
        bigint attendance_status_id FK
    }
    attendance_period_locks {
        bigint id PK
        date start_date
        date end_date
        bigint branch_id FK
        bigint locked_by FK
        timestamp locked_at
        bigint unlocked_by FK
        timestamp unlocked_at
    }
    attendance_statuses {
        bigint id PK
        string code UK
        string name_en
        timestamp created_at
        timestamp updated_at
    }
    attendance_policies {
        bigint id PK
        string code UK
        string name_en
        timestamp created_at
        timestamp updated_at
    }
    attendance_policy_versions {
        bigint id PK
        bigint attendance_policy_id FK
        smallint version
        date effective_from
        bigint created_by FK
        timestamp created_at
        timestamp updated_at
    }
    attendance_policy_assignments {
        bigint id PK
        bigint attendance_policy_id FK
        string scope_type
        date effective_from
        date effective_to
        bigint created_by FK
        timestamp created_at
        timestamp updated_at
    }
    employees {
        bigint id PK
        string employee_number UK
        bigint candidate_id FK
        bigint job_application_id FK
        string name_en
        date date_of_birth
        string marital_status
        string national_id_hash UK
    }
```

### 4.6 Attendance capture (devices, mobile, face)

```mermaid
erDiagram
    attendance_device_sync_logs ||--o{ attendance_device_events : "attendance_device_sync_log_id"
    attendance_devices ||--o{ attendance_device_events : "attendance_device_id"
    attendance_devices ||--o{ attendance_device_sync_logs : "attendance_device_id"
    attendance_devices ||--o{ attendance_device_users : "attendance_device_id"
    attendance_mobile_attempts ||--o{ face_verifications : "attendance_mobile_attempt_id"
    attendance_raw_events ||--o{ attendance_device_events : "attendance_raw_event_id"
    attendance_raw_events ||--o{ attendance_mobile_attempts : "attendance_raw_event_id"
    branch_locations ||--o{ attendance_devices : "branch_location_id"
    branch_locations ||--o{ attendance_mobile_attempts : "branch_location_id"
    branch_locations ||--o{ attendance_work_location_rules : "branch_location_id"
    branch_locations ||--o{ employees : "work_location_id"
    employees ||--o{ attendance_device_users : "employee_id"
    employees ||--o{ attendance_mobile_attempts : "employee_id"
    employees ||--o{ attendance_raw_events : "employee_id"
    employees ||--o{ face_consents : "employee_id"
    employees ||--o{ face_enrolments : "employee_id"
    employees ||--o{ face_verifications : "employee_id"
    face_consents ||--o{ face_enrolments : "face_consent_id"
    face_enrolments ||--o{ face_verifications : "face_enrolment_id"
    attendance_devices {
        bigint id PK
        string serial_number UK
        bigint branch_id FK
        bigint branch_location_id FK
        string push_token_hash UK
        timestamp last_seen_at
        timestamp last_sync_at
        string last_sync_status
    }
    attendance_device_users {
        bigint id PK
        bigint attendance_device_id FK
        bigint employee_id FK
        bigint created_by FK
        timestamp created_at
        timestamp updated_at
    }
    attendance_device_sync_logs {
        bigint id PK
        bigint attendance_device_id FK
        string type
        string status
        timestamp started_at
        timestamp finished_at
        bigint triggered_by FK
        timestamp created_at
    }
    attendance_device_events {
        bigint id PK
        bigint attendance_raw_event_id FK
        bigint attendance_device_id FK
        bigint attendance_device_sync_log_id FK
        timestamp created_at
        timestamp updated_at
    }
    attendance_raw_events {
        bigint id PK
        string kind
        string source
        bigint employee_id FK
        datetime occurred_at
        date work_date
        bigint shift_id FK
        bigint attendance_status_id FK
    }
    attendance_mobile_attempts {
        bigint id PK
        string request_uuid UK
        bigint employee_id FK
        bigint user_id FK
        string review_status
        bigint branch_location_id FK
        string app_version
        date work_date
    }
    attendance_work_location_rules {
        bigint id PK
        string scope_type
        bigint branch_location_id FK
        date effective_from
        date effective_to
        bigint created_by FK
        timestamp created_at
        timestamp updated_at
    }
    branch_locations {
        bigint id PK
        bigint branch_id FK
        string name_en
        timestamp created_at
        timestamp updated_at
    }
    face_consents {
        bigint id PK
        bigint employee_id FK
        string version
        timestamp given_at
        timestamp withdrawn_at
        string source
        bigint user_id FK
        timestamp created_at
    }
    face_enrolments {
        bigint id PK
        bigint employee_id FK
        bigint face_consent_id FK
        string status
        text provider_reference
        timestamp enrolled_at
        bigint enrolled_by FK
        timestamp deleted_at
    }
    face_verifications {
        bigint id PK
        bigint employee_id FK
        bigint face_enrolment_id FK
        bigint attendance_mobile_attempt_id FK
        smallint attempt_number
        string provider_reference
        string error_code
        date work_date
    }
    employees {
        bigint id PK
        string employee_number UK
        bigint candidate_id FK
        bigint job_application_id FK
        string name_en
        date date_of_birth
        string marital_status
        string national_id_hash UK
    }
```

### 4.7 Shifts & schedules

```mermaid
erDiagram
    employees ||--o{ schedule_change_requests : "employee_id"
    employees ||--o{ schedule_change_requests : "swap_employee_id"
    employees ||--o{ schedule_overrides : "employee_id"
    schedule_change_requests ||--o{ schedule_overrides : "schedule_change_request_id"
    shifts ||--o{ schedule_change_requests : "shift_id"
    shifts ||--o{ schedule_overrides : "shift_id"
    shifts ||--o{ shift_breaks : "shift_id"
    shifts ||--o{ work_schedule_days : "shift_id"
    work_schedules ||--o{ schedule_assignments : "work_schedule_id"
    work_schedules ||--o{ work_schedule_days : "work_schedule_id"
    shifts {
        bigint id PK
        string code UK
        string name_en
        string type
        timestamp created_at
        timestamp updated_at
    }
    shift_breaks {
        bigint id PK
        bigint shift_id FK
        string type
        timestamp created_at
        timestamp updated_at
    }
    work_schedules {
        bigint id PK
        string code UK
        string name_en
        string type
        date cycle_start_date
        timestamp created_at
        timestamp updated_at
    }
    work_schedule_days {
        bigint id PK
        bigint work_schedule_id FK
        bigint shift_id FK
        timestamp created_at
        timestamp updated_at
    }
    schedule_assignments {
        bigint id PK
        bigint work_schedule_id FK
        string scope_type
        date effective_from
        date effective_to
        bigint created_by FK
        timestamp created_at
        timestamp updated_at
    }
    schedule_overrides {
        bigint id PK
        bigint employee_id FK
        date work_date
        bigint shift_id FK
        string source
        bigint schedule_change_request_id FK
        bigint created_by FK
        timestamp created_at
    }
    schedule_change_requests {
        bigint id PK
        string type
        bigint employee_id FK
        date work_date
        bigint shift_id FK
        bigint swap_employee_id FK
        string status
        bigint requested_by FK
    }
    holidays {
        bigint id PK
        string name_en
        date start_date
        date end_date
        bigint branch_id FK
        timestamp created_at
        timestamp updated_at
    }
    employees {
        bigint id PK
        string employee_number UK
        bigint candidate_id FK
        bigint job_application_id FK
        string name_en
        date date_of_birth
        string marital_status
        string national_id_hash UK
    }
```

### 4.8 Leave

```mermaid
erDiagram
    employees ||--o{ leave_balance_transactions : "employee_id"
    employees ||--o{ leave_request_days : "employee_id"
    employees ||--o{ leave_requests : "employee_id"
    leave_policies ||--o{ leave_balance_transactions : "leave_policy_id"
    leave_requests ||--o{ leave_balance_transactions : "leave_request_id"
    leave_requests ||--o{ leave_request_attachments : "leave_request_id"
    leave_requests ||--o{ leave_request_days : "leave_request_id"
    leave_types ||--o{ leave_balance_transactions : "leave_type_id"
    leave_types ||--o{ leave_policies : "leave_type_id"
    leave_types ||--o{ leave_requests : "leave_type_id"
    leave_types {
        bigint id PK
        string code UK
        string name_en
        bool allows_past_dates
        bigint attendance_status_id FK
        timestamp created_at
        timestamp updated_at
    }
    leave_policies {
        bigint id PK
        string code UK
        string name_en
        bigint leave_type_id FK
        string scope_type
        date effective_from
        date effective_to
        timestamp created_at
    }
    leave_requests {
        bigint id PK
        bigint employee_id FK
        bigint leave_type_id FK
        date start_date
        date end_date
        string status
        string source
        bigint requested_by FK
    }
    leave_request_days {
        bigint id PK
        bigint leave_request_id FK
        bigint employee_id FK
        date date
        decimal6_2 amount
        string day_kind
    }
    leave_request_attachments {
        bigint id PK
        bigint leave_request_id FK
        bigint uploaded_by FK
        timestamp created_at
    }
    leave_balance_transactions {
        bigint id PK
        bigint employee_id FK
        bigint leave_type_id FK
        string type
        decimal8_2 amount
        date effective_date
        bigint leave_request_id FK
        bigint leave_policy_id FK
    }
    employees {
        bigint id PK
        string employee_number UK
        bigint candidate_id FK
        bigint job_application_id FK
        string name_en
        date date_of_birth
        string marital_status
        string national_id_hash UK
    }
```

### 4.9 Payroll components & loans

```mermaid
erDiagram
    employee_loans ||--o{ loan_changes : "employee_loan_id"
    employee_loans ||--o{ loan_instalments : "employee_loan_id"
    employee_loans ||--o{ loan_transactions : "employee_loan_id"
    employees ||--o{ employee_loans : "employee_id"
    employees ||--o{ loan_instalments : "employee_id"
    employees ||--o{ pay_entries : "employee_id"
    loan_instalments ||--o{ loan_changes : "loan_instalment_id"
    loan_instalments ||--o{ loan_transactions : "loan_instalment_id"
    pay_entries ||--o{ pay_entry_attachments : "pay_entry_id"
    pay_entry_batches ||--o{ pay_entries : "pay_entry_batch_id"
    salary_components ||--o{ pay_entries : "base_component_id"
    salary_components ||--o{ pay_entries : "salary_component_id"
    salary_components ||--o{ salary_components : "base_component_id"
    salary_components ||--o{ salary_structure_lines : "base_component_id"
    salary_components ||--o{ salary_structure_lines : "salary_component_id"
    salary_structure_versions ||--o{ salary_structure_lines : "salary_structure_version_id"
    salary_structures ||--o{ salary_structure_assignments : "salary_structure_id"
    salary_structures ||--o{ salary_structure_versions : "salary_structure_id"
    statutory_rule_sets ||--o{ statutory_rule_versions : "statutory_rule_set_id"
    statutory_rule_versions ||--o{ statutory_rule_brackets : "statutory_rule_version_id"
    salary_components {
        bigint id PK
        string code UK
        string name_en
        string kind
        string calculation_type
        decimal14_4 amount
        bigint base_component_id FK
        string statutory_type
    }
    salary_structures {
        bigint id PK
        string code UK
        string name_en
        timestamp created_at
        timestamp updated_at
    }
    salary_structure_versions {
        bigint id PK
        bigint salary_structure_id FK
        int version
        date effective_from
        date effective_to
        bigint created_by FK
        timestamp created_at
        timestamp updated_at
    }
    salary_structure_lines {
        bigint id PK
        bigint salary_structure_version_id FK
        bigint salary_component_id FK
        decimal14_4 amount
        bigint base_component_id FK
        timestamp created_at
        timestamp updated_at
    }
    salary_structure_assignments {
        bigint id PK
        bigint salary_structure_id FK
        string scope_type
        date effective_from
        date effective_to
        bigint created_by FK
        timestamp created_at
        timestamp updated_at
    }
    pay_entries {
        bigint id PK
        string reference UK
        string kind
        bigint salary_component_id FK
        string target_type
        bigint employee_id FK
        bigint employee_group_id FK
        bigint department_id FK
    }
    pay_entry_batches {
        bigint id PK
        string source
        string kind
        bigint created_by FK
        timestamp created_at
        timestamp updated_at
    }
    pay_entry_attachments {
        bigint id PK
        bigint pay_entry_id FK
        bigint uploaded_by FK
        timestamp created_at
        timestamp updated_at
    }
    statutory_rule_sets {
        bigint id PK
        string code UK
        string name_en
        string country_code
        string type
        timestamp created_at
        timestamp updated_at
    }
    statutory_rule_versions {
        bigint id PK
        bigint statutory_rule_set_id FK
        int version
        date effective_from
        date effective_to
        bigint created_by FK
        timestamp created_at
        timestamp updated_at
    }
    statutory_rule_brackets {
        bigint id PK
        bigint statutory_rule_version_id FK
        timestamp created_at
        timestamp updated_at
    }
    employee_loans {
        bigint id PK
        string reference UK
        bigint employee_id FK
        string type
        string status
        string source
        decimal14_2 requested_amount
        decimal14_2 approved_amount
    }
    loan_instalments {
        bigint id PK
        bigint employee_loan_id FK
        bigint employee_id FK
        date due_date
        decimal14_2 amount
        decimal14_2 paid_amount
        string status
        string settled_by_type
    }
    loan_transactions {
        bigint id PK
        bigint employee_loan_id FK
        bigint loan_instalment_id FK
        string type
        decimal14_2 amount
        date occurred_on
        string reference UK
        bigint created_by FK
    }
    loan_changes {
        bigint id PK
        bigint employee_loan_id FK
        string type
        string status
        bigint loan_instalment_id FK
        date new_start_date
        bigint requested_by FK
        timestamp decided_at
    }
    employees {
        bigint id PK
        string employee_number UK
        bigint candidate_id FK
        bigint job_application_id FK
        string name_en
        date date_of_birth
        string marital_status
        string national_id_hash UK
    }
```

### 4.10 Payroll runs & payslips

```mermaid
erDiagram
    employees ||--o{ payroll_adjustments : "employee_id"
    employees ||--o{ payroll_item_lines : "employee_id"
    employees ||--o{ payroll_items : "employee_id"
    employees ||--o{ payslips : "employee_id"
    payroll_items ||--o{ payroll_adjustments : "original_item_id"
    payroll_items ||--o{ payroll_adjustments : "payroll_item_id"
    payroll_items ||--o{ payroll_item_lines : "payroll_item_id"
    payroll_items ||--o{ payslips : "payroll_item_id"
    payroll_periods ||--o{ payroll_adjustments : "original_period_id"
    payroll_periods ||--o{ payroll_items : "payroll_period_id"
    payroll_periods ||--o{ payroll_runs : "payroll_period_id"
    payroll_periods ||--o{ payslips : "payroll_period_id"
    payroll_runs ||--o{ payroll_item_lines : "payroll_run_id"
    payroll_runs ||--o{ payroll_items : "payroll_run_id"
    payroll_runs ||--o{ payroll_runs : "corrects_run_id"
    payroll_runs ||--o{ payslips : "payroll_run_id"
    payslips ||--o{ payslip_payments : "payslip_id"
    payroll_periods {
        bigint id PK
        string code UK
        string name_en
        string type
        date start_date
        date end_date
        date pay_date
        date cutoff_date
    }
    payroll_runs {
        bigint id PK
        string reference UK
        bigint payroll_period_id FK
        string type
        string status
        bigint branch_id FK
        bigint corrects_run_id FK
        bigint offboarding_case_id FK
    }
    payroll_items {
        bigint id PK
        bigint payroll_run_id FK
        bigint payroll_period_id FK
        bigint employee_id FK
        string pay_slot
        string status
        bigint branch_id FK
        bigint department_id FK
    }
    payroll_item_lines {
        bigint id PK
        bigint payroll_item_id FK
        bigint payroll_run_id FK
        bigint employee_id FK
        bigint salary_component_id FK
        string code
        string name_en
        string kind
    }
    payroll_adjustments {
        bigint id PK
        string reference UK
        bigint employee_id FK
        bigint original_item_id FK
        bigint original_period_id FK
        string source
        string status
        bigint salary_component_id FK
    }
    payslips {
        bigint id PK
        string number UK
        bigint payroll_item_id FK
        bigint payroll_run_id FK
        bigint payroll_period_id FK
        bigint employee_id FK
        bigint branch_id FK
        bigint department_id FK
    }
    payslip_payments {
        bigint id PK
        bigint payslip_id FK
        decimal14_2 amount
        string reference
        bigint recorded_by FK
        timestamp created_at
        timestamp updated_at
    }
    employees {
        bigint id PK
        string employee_number UK
        bigint candidate_id FK
        bigint job_application_id FK
        string name_en
        date date_of_birth
        string marital_status
        string national_id_hash UK
    }
```

### 4.11 Performance & training

```mermaid
erDiagram
    competencies ||--o{ performance_cycle_competencies : "competency_id"
    competencies ||--o{ performance_review_competencies : "competency_id"
    employees ||--o{ employee_skills : "employee_id"
    employees ||--o{ performance_goals : "employee_id"
    employees ||--o{ performance_reviews : "employee_id"
    employees ||--o{ performance_reviews : "reviewer_id"
    employees ||--o{ training_certificates : "employee_id"
    employees ||--o{ training_enrolments : "employee_id"
    performance_cycles ||--o{ performance_cycle_competencies : "performance_cycle_id"
    performance_cycles ||--o{ performance_goals : "performance_cycle_id"
    performance_cycles ||--o{ performance_reviews : "performance_cycle_id"
    performance_goals ||--o{ performance_goal_updates : "performance_goal_id"
    performance_reviews ||--o{ performance_review_competencies : "performance_review_id"
    rating_scale_levels ||--o{ performance_reviews : "final_rating_level_id"
    rating_scales ||--o{ performance_cycles : "rating_scale_id"
    rating_scales ||--o{ rating_scale_levels : "rating_scale_id"
    skills ||--o{ employee_skills : "skill_id"
    training_courses ||--o{ training_certificates : "training_course_id"
    training_courses ||--o{ training_sessions : "training_course_id"
    training_enrolments ||--o{ employee_skills : "training_enrolment_id"
    training_enrolments ||--o{ training_attendances : "training_enrolment_id"
    training_enrolments ||--o{ training_certificates : "training_enrolment_id"
    training_programmes ||--o{ training_courses : "training_programme_id"
    training_providers ||--o{ training_courses : "training_provider_id"
    training_sessions ||--o{ training_enrolments : "training_session_id"
    performance_cycles {
        bigint id PK
        string code UK
        string name_en
        string type
        bigint rating_scale_id FK
        bigint branch_id FK
        bigint department_id FK
        string status
    }
    performance_reviews {
        bigint id PK
        string reference UK
        bigint performance_cycle_id FK
        bigint employee_id FK
        bigint reviewer_id FK
        bigint branch_id FK
        bigint department_id FK
        string status
    }
    performance_goals {
        bigint id PK
        bigint employee_id FK
        bigint performance_cycle_id FK
        string type
        date start_date
        date due_date
        string status
        bigint created_by FK
    }
    performance_goal_updates {
        bigint id PK
        bigint performance_goal_id FK
        bigint created_by FK
        timestamp created_at
        timestamp updated_at
    }
    performance_review_competencies {
        bigint id PK
        bigint performance_review_id FK
        bigint competency_id FK
        timestamp created_at
        timestamp updated_at
    }
    performance_cycle_competencies {
        bigint id PK
        bigint performance_cycle_id FK
        bigint competency_id FK
        timestamp created_at
        timestamp updated_at
    }
    competencies {
        bigint id PK
        string code UK
        string name_en
        timestamp created_at
        timestamp updated_at
    }
    rating_scales {
        bigint id PK
        string code UK
        string name_en
        timestamp created_at
        timestamp updated_at
    }
    rating_scale_levels {
        bigint id PK
        bigint rating_scale_id FK
        timestamp created_at
        timestamp updated_at
    }
    training_providers {
        bigint id PK
        string code UK
        string name_en
        string type
        string email
        timestamp created_at
        timestamp updated_at
    }
    training_programmes {
        bigint id PK
        string code UK
        string name_en
        timestamp created_at
        timestamp updated_at
    }
    training_courses {
        bigint id PK
        string code UK
        bigint training_programme_id FK
        bigint training_provider_id FK
        string name_en
        timestamp created_at
        timestamp updated_at
    }
    training_sessions {
        bigint id PK
        string reference UK
        bigint training_course_id FK
        date start_date
        date end_date
        bigint branch_id FK
        string status
        bigint created_by FK
    }
    training_enrolments {
        bigint id PK
        bigint training_session_id FK
        bigint employee_id FK
        string source
        string status
        bigint branch_id FK
        bigint department_id FK
        bigint requested_by FK
    }
    training_attendances {
        bigint id PK
        bigint training_enrolment_id FK
        date date
        string status
        bigint recorded_by FK
        timestamp created_at
        timestamp updated_at
    }
    training_certificates {
        bigint id PK
        bigint employee_id FK
        bigint training_enrolment_id FK
        bigint training_course_id FK
        string type
        string certificate_number
        timestamp reminder_sent_at
        bigint uploaded_by FK
    }
    training_budgets {
        bigint id PK
        bigint department_id FK
        decimal14_2 amount
        bigint created_by FK
        timestamp created_at
        timestamp updated_at
    }
    skills {
        bigint id PK
        string code UK
        string name_en
        timestamp created_at
        timestamp updated_at
    }
    employee_skills {
        bigint id PK
        bigint employee_id FK
        bigint skill_id FK
        string source
        bigint training_enrolment_id FK
        bigint updated_by FK
        timestamp created_at
        timestamp updated_at
    }
    employees {
        bigint id PK
        string employee_number UK
        bigint candidate_id FK
        bigint job_application_id FK
        string name_en
        date date_of_birth
        string marital_status
        string national_id_hash UK
    }
```

### 4.12 Assets & disciplinary

```mermaid
erDiagram
    asset_assignments ||--o{ asset_events : "asset_assignment_id"
    asset_categories ||--o{ assets : "asset_category_id"
    assets ||--o{ asset_assignments : "asset_id"
    assets ||--o{ asset_events : "asset_id"
    disciplinary_cases ||--o{ disciplinary_actions : "disciplinary_case_id"
    disciplinary_cases ||--o{ disciplinary_case_notes : "disciplinary_case_id"
    disciplinary_cases ||--o{ disciplinary_evidence : "disciplinary_case_id"
    employees ||--o{ asset_assignments : "employee_id"
    employees ||--o{ asset_events : "employee_id"
    employees ||--o{ disciplinary_actions : "employee_id"
    employees ||--o{ disciplinary_cases : "employee_id"
    violation_types ||--o{ disciplinary_cases : "violation_type_id"
    asset_categories {
        bigint id PK
        string code UK
        string name_en
        timestamp created_at
        timestamp updated_at
    }
    assets {
        bigint id PK
        string asset_tag UK
        bigint asset_category_id FK
        string serial_number UK
        date purchase_date
        bigint branch_id FK
        string status
        bigint created_by FK
    }
    asset_assignments {
        bigint id PK
        bigint asset_id FK
        bigint employee_id FK
        bigint issued_by FK
        bigint onboarding_task_id FK
        timestamp acknowledged_at
        bigint received_by FK
        string status
    }
    asset_events {
        bigint id PK
        bigint asset_id FK
        bigint asset_assignment_id FK
        bigint employee_id FK
        string type
        date occurred_on
        bigint created_by FK
        timestamp created_at
    }
    violation_types {
        bigint id PK
        string code UK
        string name_en
        timestamp created_at
        timestamp updated_at
    }
    disciplinary_cases {
        bigint id PK
        string reference UK
        bigint employee_id FK
        bigint violation_type_id FK
        bigint branch_id FK
        bigint department_id FK
        date incident_date
        string status
    }
    disciplinary_case_notes {
        bigint id PK
        bigint disciplinary_case_id FK
        string type
        bigint created_by FK
        timestamp created_at
        timestamp updated_at
    }
    disciplinary_evidence {
        bigint id PK
        bigint disciplinary_case_id FK
        bigint uploaded_by FK
        timestamp created_at
        timestamp updated_at
    }
    disciplinary_actions {
        bigint id PK
        bigint disciplinary_case_id FK
        bigint employee_id FK
        string type
        date effective_date
        decimal14_2 deduction_amount
        bigint pay_entry_id FK
        string status
    }
    employees {
        bigint id PK
        string employee_number UK
        bigint candidate_id FK
        bigint job_application_id FK
        string name_en
        date date_of_birth
        string marital_status
        string national_id_hash UK
    }
```

### 4.13 HR requests, announcements, notifications

```mermaid
erDiagram
    announcements ||--o{ announcement_attachments : "announcement_id"
    announcements ||--o{ announcement_reads : "announcement_id"
    announcements ||--o{ announcement_targets : "announcement_id"
    employees ||--o{ announcement_reads : "employee_id"
    employees ||--o{ hr_requests : "employee_id"
    employees ||--o{ users : "employee_id"
    hr_letter_templates ||--o{ hr_request_categories : "hr_letter_template_id"
    hr_request_categories ||--o{ hr_request_category_fields : "hr_request_category_id"
    hr_request_categories ||--o{ hr_requests : "hr_request_category_id"
    hr_request_messages ||--o{ hr_request_attachments : "hr_request_message_id"
    hr_requests ||--o{ hr_profile_changes : "hr_request_id"
    hr_requests ||--o{ hr_request_attachments : "hr_request_id"
    hr_requests ||--o{ hr_request_events : "hr_request_id"
    hr_requests ||--o{ hr_request_messages : "hr_request_id"
    users ||--o{ device_tokens : "user_id"
    users ||--o{ hr_request_categories : "default_assignee_id"
    users ||--o{ hr_request_categories : "escalation_user_id"
    users ||--o{ hr_request_events : "actor_id"
    users ||--o{ hr_request_messages : "author_id"
    users ||--o{ hr_requests : "assigned_to"
    users ||--o{ notification_preferences : "user_id"
    hr_request_categories {
        bigint id PK
        string code UK
        string name_en
        string type
        bigint hr_letter_template_id FK
        bigint default_assignee_id FK
        bigint escalation_user_id FK
        timestamp created_at
    }
    hr_request_category_fields {
        bigint id PK
        bigint hr_request_category_id FK
        string type
        timestamp created_at
        timestamp updated_at
    }
    hr_requests {
        bigint id PK
        string reference UK
        bigint employee_id FK
        bigint hr_request_category_id FK
        bigint branch_id FK
        bigint department_id FK
        string status
        bigint assigned_to FK
    }
    hr_request_messages {
        bigint id PK
        bigint hr_request_id FK
        string type
        bigint author_id FK
        timestamp created_at
        timestamp updated_at
    }
    hr_request_attachments {
        bigint id PK
        bigint hr_request_id FK
        bigint hr_request_message_id FK
        bigint uploaded_by FK
        timestamp created_at
        timestamp updated_at
    }
    hr_request_events {
        bigint id PK
        bigint hr_request_id FK
        string type
        bigint actor_id FK
        timestamp created_at
    }
    hr_profile_changes {
        bigint id PK
        bigint hr_request_id FK
        string status
        timestamp decided_at
        bigint decided_by FK
        timestamp created_at
        timestamp updated_at
    }
    hr_letter_templates {
        bigint id PK
        string code UK
        string name_en
        timestamp created_at
        timestamp updated_at
    }
    announcements {
        bigint id PK
        string type
        string status
        timestamp publish_at
        timestamp expires_at
        bool notify_email
        timestamp published_at
        bigint published_by FK
    }
    announcement_targets {
        bigint id PK
        bigint announcement_id FK
        string target_type
    }
    announcement_reads {
        bigint id PK
        bigint announcement_id FK
        bigint employee_id FK
        timestamp read_at
        timestamp acknowledged_at
    }
    announcement_attachments {
        bigint id PK
        bigint announcement_id FK
        bigint uploaded_by FK
        timestamp created_at
        timestamp updated_at
    }
    notification_templates {
        bigint id PK
        bigint updated_by FK
        timestamp created_at
        timestamp updated_at
    }
    notification_preferences {
        bigint id PK
        bigint user_id FK
        timestamp created_at
        timestamp updated_at
    }
    notification_deliveries {
        bigint id PK
        bigint user_id FK
        string notification_type
        string status
        timestamp sent_at
        timestamp failed_at
        timestamp created_at
        timestamp updated_at
    }
    notifications {
        string id PK
        string type
        string notifiable_type
        timestamp read_at
        timestamp created_at
        timestamp updated_at
    }
    device_tokens {
        bigint id PK
        bigint user_id FK
        string token_hash UK
        string app_version
        timestamp last_seen_at
        timestamp created_at
        timestamp updated_at
    }
    users {
        bigint id PK
        string email UK
        timestamp email_verified_at
        string account_type
        string status
        bigint employee_id FK
        timestamp last_login_at
        timestamp password_changed_at
    }
    employees {
        bigint id PK
        string employee_number UK
        bigint candidate_id FK
        bigint job_application_id FK
        string name_en
        date date_of_birth
        string marital_status
        string national_id_hash UK
    }
```


---

## 5. Polymorphic links and cross-module contracts

Some relations are polymorphic (`*_type` + `*_id`, using Laravel's morph map) and therefore do not appear as
foreign keys in the diagrams:

| Column | Points to |
|---|---|
| `approval_requests.approvable_*` | leave requests, attendance corrections / manual batches, schedule changes, job offers, pay entries, loans / loan changes, payroll runs, performance reviews, training enrolments, disciplinary actions, HR requests |
| `audit_logs.auditable_*` | any audited model |
| `attendance_raw_events.origin_*` | the manual batch / correction / device event / mobile attempt that created the event |
| `pay_entries.source_*` | e.g. the disciplinary action behind a deduction |
| `payroll_item_lines.sourceable_*` | pay entry, loan instalment, statutory rule version, leave transaction, payroll adjustment |
| `loan_instalments.settled_by_*` | the payroll item that recovered the instalment |
| `offboarding_items.itemable_*` | asset assignment (clearance), loan, final settlement |
| `notifications.notifiable_*` | users |

Services that read across modules (so that precedence and versioning stay in one place):
`ScheduleResolver` (schedules), `AttendanceEngine` (days), `LeavePayrollFeed` (unpaid / encashed leave for payroll),
`PayrollInputs` / `PayrollCalculator` (payroll), `AttendanceQuantities` (attendance quantities for payroll),
`PayrollCorrections` (post-finalisation differences). See `PAYROLL_ENGINE.md`.

---

## 6. Framework and infrastructure tables

`migrations`, `cache`, `cache_locks` (also used for scheduler `onOneServer` locks), `jobs`, `job_batches`,
`failed_jobs` (database queue), `sessions` (database sessions — used by *Account settings* to list signed-in
browsers), `password_reset_tokens` (web reset links), `personal_access_tokens` (Sanctum API tokens with
`expires_at`).

---

## 7. Maintenance notes

- Never edit history tables by hand (raw events, ledgers, finalised payroll); use the application's correction
  flows so that audit trails stay consistent.
- Back up the database **and** `storage/app/private` together, and keep the `APP_KEY`: encrypted columns and
  encrypted biometric files cannot be read without it.
- Indexes: every foreign key is indexed; Phase 11 added composite indexes for the dashboard, reports and the
  schedule resolver (`2026_11_22_100000_add_performance_indexes`).
- Regenerate the diagrams from `information_schema` after schema changes (the generator used for this document
  reads `KEY_COLUMN_USAGE` and `COLUMNS`).
