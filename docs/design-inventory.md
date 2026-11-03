# SK ProFund: design inventory and implementation plan

## Sources and limits

Sources: uploaded requirements document, user-provided role outline, Admin recording (23-51-26), Chairperson recording (23-55-45), Treasurer recording (23-56-55), Official recording (23-57-52), and Youth/Resident recording (23-58-31).

The user's updates add Admin and replace receipt-reference-only recording with receipt image uploads. These updates take precedence over the original document.

Recordings establish only the views and interactions they show. They do not establish that every Figma frame has been inspected. Figma tools are currently unavailable. Exact fonts, source assets, design tokens, hidden states, and full prototype connections cannot be extracted from the recordings alone. Displayed people and financial figures are design examples, not seed records.

## Screen inventory

| Area | Observed views and interactions |
| --- | --- |
| Entry | Illustrated welcome page, SK Officials and Youth/Residents choices; staff login, forgot-password link |
| Admin dashboard | User/project/fund totals; recent activity table and View All; project status doughnut |
| Admin users | Search; Users/Roles tabs; Add User/Add Role; edit/delete controls; five named roles |
| Admin settings | Searchable categories and fund sources; add/edit/delete controls; category and fund source dialogs; save confirmation |
| Admin audit | Date range/user/action/module filters; Apply/Reset; date-time, user, action, module, details table |
| Admin reports | Date/category/source filters; totals, allocation chart, top projects; expanded report and project list |
| Chairperson dashboard | Overview, project status, recent activities, pending items |
| Chairperson profile | Personal/account details, photo control, password form, save confirmation |
| Chairperson proposals | Approved/planned budget, total expense, expense details, fund source, payment/reference field, schedule, location, remarks, save/cancel |
| Chairperson monitoring | Project summary, status, progress bar, dated progress log, action control |
| Chairperson fund approval | Pending/approved request sections, request actions |
| Chairperson reports | Year range, summary totals, project-status chart, category allocation chart, recent activity, project-details overlay |
| Treasurer dashboard | Fund totals, disbursement chart, fund-status chart, recent records |
| Treasurer profile | Shared profile/password layout |
| Treasurer allocation | Year range, set/adjusted totals, category budget and realignment table, status; source/destination, amount, reason, supporting document, balance summary, Submit for Approval |
| Treasurer expenses | Expense information and receipt details; record action. User requires receipt image upload in the implementation |
| Treasurer balance | Total balance and per-project balances |
| Treasurer transactions | Search/filter controls, receipt/transaction records and history |
| Official dashboard | Own project totals/status, upcoming dates, recent activity, own project cards and progress |
| Official profile | Shared profile/password layout |
| Official projects | New Proposal and Progress columns, suggested proposals, Add Proposal, project cards |
| Official fund summary | Allocated and spent totals |
| Public dashboard | Project summary, updates, project list and update overlay |
| Public projects | Search, year/status filters, project table, details overlay with image/status/timeline/beneficiaries/location/description |
| Public fund summary | Allocated/spent/remaining overview and project table |
| Exit | Staff logout; public Exit returns to welcome |

## Documented access boundaries

- Admin: accounts/role assignment, categories, fund sources, audit logs, summary reporting. Do not infer unrestricted editing of project or financial records.
- Chairperson: project/financial review, remarks, monitoring, fund approval, reporting, own profile/password.
- Treasurer: allocation and adjustment requests, expenses, receipts, balances, transactions, own profile/password.
- Official: project proposals and progress, own-project dashboard, fund summary, own profile/password.
- Youth/Resident: approved public information and financial summaries. The recording demonstrates public entry without a login screen; whether resident accounts are also required is unresolved.

## Visual observations

Shared white/light header with SK branding, teal left navigation and table headers, pale active navigation row, white content panels, dark primary buttons, outlined secondary buttons, compact status badges, bordered/shadowed cards, charts, tables and modal dialogs. Public/official project views include photographs. Desktop layouts are displayed inside a mobile browser; these recordings do not establish mobile responsive layouts. Some blue highlights are Figma hotspot indicators and are not established interface styling.

## Implementation stages

1. Complete design mapping: resolve unshown dialogs and business rules; obtain original logo/illustrations/photos and exact design specifications where available.
2. Database/security: MySQL import script; users and role permissions; categories and fund sources; project proposals, approved budgets, adjustments, requests, expenses, uploaded receipts, progress history, public visibility and audit events. Final relationships depend on confirmed approval rules. Use PDO prepared statements, password hashing, session regeneration, CSRF protection and server-side authorization.
3. Shared UI: plain PHP templates, HTML/CSS/JavaScript, header/sidebar, tables, forms, accessible dialogs, filters, responsive behavior. No application framework.
4. Role workflows: Admin management; Official proposals/progress; Chairperson review/approval/publication; Treasurer budget/expense/receipt/transaction workflows; public approved-project viewing. Confirm publication authority before implementing it.
5. Financial integrity: decimal database amounts; derive totals from recorded transactions; atomic approvals/realignments with balance checks; prevent repeated approval posting. Confirm relationships between allocations, releases, disbursements and expenses first.
6. Uploads: validate MIME/type/size, randomize filenames, store outside public document root, enforce authorized receipt access; do not expose private receipts to public users by default. Confirm whether structured receipt fields remain alongside images.
7. Validation: actual MySQL integration and permission tests, CRUD/approval/rollback tests, calculations, malicious upload rejection, sessions/CSRF, browser navigation and visual comparison. Document differences and unverified states.
8. Handoff: XAMPP/VS Code/phpMyAdmin installation, private local configuration, SQL import, secure first-admin provisioning, startup, backup and test instructions. No real credentials or financial seed records.

## Unresolved design and business rules

- Add/Edit User and Add/Edit Role form layouts and role permission editing rules are not demonstrated.
- Official Add Proposal action is shown but its form does not open in the sampled recording. Progress-edit and recommendation-details forms are not established.
- Identify who approves a project for public visibility and whether fund approval also approves publication.
- Confirm fund request types and who initiates them. Treasurer realignment submission is visible, but the end-to-end financial posting rules are not documented.
- Confirm whether receipt image uploads supplement or replace manually entered receipt metadata. Expense amounts must remain structured for calculations.
- Forgot-password recovery flow is not demonstrated; email service requirements are unknown.
- Monitoring has a button labeled Add Fund Sources despite being a progress-log panel; confirm intended action rather than implementing a mislabeled operation.
- Some profile/fund-source labels appear reused in the prototype. Confirm intended field semantics before treating them as requirements.

No application code has been created as part of this inventory. This document is a reviewable plan, not a claim of complete Figma inspection or implementation readiness.

## Subsequent user authorization

After this inventory, the user confirmed Figma is unfinished and authorized completing missing screens in code. The user accepted Chairperson publication approval and confirmed receipt images plus entered date, supplier/payee and amount. Development has now proceeded. See implementation-decisions.md and README.md for the implemented behavior; the unresolved list above records the pre-development inspection findings, not outstanding permission requests.
