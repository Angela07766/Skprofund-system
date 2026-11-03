# SK ProFund

SK Project and Fund Monitoring System for Barangay Pag-Asa, Mabitac, Laguna.
Built with plain PHP, MySQL/MariaDB, HTML, CSS and JavaScript. No application framework or frontend build step is required.

## Run locally with XAMPP and VS Code

1. Install XAMPP with PHP **8.2 or later**, Apache and MySQL/MariaDB. Open this folder in VS Code.
2. Place the checkout at `C:\xampp\htdocs\Skprofund-system`.
3. Start **Apache** and **MySQL** in the XAMPP Control Panel.
4. Open phpMyAdmin (`http://localhost/phpmyadmin`), choose **Import**, and import [database/schema.sql](database/schema.sql). This creates `skprofund`, tables and the five role definitions. Import once into a new database; it deliberately does not overwrite existing tables.
5. Copy `config/example.php` to `config/local.php`. Set the database username/password for your local MySQL installation. Keep `charset=utf8mb4`. Configure a private upload directory **outside htdocs**, for example:

   ```php
   <?php
   return [
       'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=skprofund;charset=utf8mb4',
       'user' => 'your_local_database_user',
       'password' => 'your_local_database_password',
       'storage' => 'C:/xampp/skprofund-private',
   ];
   ```

   These are placeholders, not working credentials. The Apache process must be able to create/write this directory. `config/local.php` is ignored by Git.
6. In a VS Code terminal, create your initial Admin:

   ```powershell
   cd C:\xampp\htdocs\Skprofund-system
   C:\xampp\php\php.exe bin\create-admin.php
   ```

   Enter your name, email and a password of at least 12 characters. Input can be visible in the terminal: run it privately. The password is hashed; it is not saved in source code. This command refuses to create another Admin if an active Admin exists.
7. Open **`http://localhost/Skprofund-system/public/`**, select **SK Officials**, and sign in. No default account/password is supplied.
8. In **Settings**, add your actual project categories and fund sources. In **Manage Users**, create the Chairperson, Treasurer and Official accounts. Give each user their temporary password privately; first login requires a password change.

For PHP's development server instead of Apache:

```sh
php -S 127.0.0.1:8080 -t public
```

Keep MySQL running. Open `http://127.0.0.1:8080`. PHP must have PDO MySQL and Fileinfo enabled (included in normal XAMPP installations). Set `upload_max_filesize = 5M` or higher and `post_max_size = 12M` or higher in `php.ini`, then restart Apache. Images are limited to 5 MB and 24 megapixels by the application.

For a hosted installation, point Apache's document root directly to **public/**. Root `.htaccess` blocks private directories as defense in depth for XAMPP; do not deploy using a web server that exposes the entire checkout. Use HTTPS for deployment. XAMPP is intended here for local development, not Internet hosting.

## Working workflow

1. **Official:** create a project proposal with category, fund source, planned amount, schedule, committee, location and beneficiaries. Attach a project photo if available.
2. **Chairperson → Proposals:** approve or reject the proposal, with remarks. Approval does **not** publish it or release money.
3. **Chairperson → Proposals:** choose **Approve Public Viewing** separately when its information is ready. Youth/Residents can browse published projects without signing in. Public pages exclude private proposals and receipt files.
4. **Treasurer → Allocation:** record actual funding received with an authorized reference. Submit a budget allocation request against those funds.
5. **Chairperson → Fund Approval:** review the request and approve it. The source must have enough unallocated funds.
6. **Treasurer:** request a **Release Fund** disbursement against the approved budget. The Chairperson approves it before expenses can be recorded.
7. **Treasurer → Expenses:** record the expense amount, date, description, supplier/payee and receipt date/reference; upload a JPEG, PNG or WebP receipt image. The image is stored privately and linked to the expense. Replacing/editing an expense recalculates balances and creates an audit event.
8. **Official:** update project status/progress. Public project changes are held for Chairperson publication review. The current implementation temporarily hides the project while review is pending; it does not maintain a separate published revision.
9. **Reports / Fund Summary:** see database-derived totals; filter by year, category or source. Staff reports support browser **Print / Save PDF**. Historical records are retained, with no automatic three-year deletion.

### Roles

- **Admin:** user accounts, role assignment, categories, sources, audit filters, summary reports. Admin does not edit project finances or approve publication.
- **Chairperson:** review proposals, remarks, publication, monitoring, financial approvals and reports; can view supporting receipts.
- **Treasurer:** record authorized funding, request allocation/realignment/disbursement, record/edit expenses and receipts, view balances and history.
- **Official:** create/edit own proposals and record own progress; see own project fund totals and references from completed public project history.
- **Youth/Residents:** read published project information and financial summaries. The public entry requires no account. Admin-created resident accounts are read-only and can maintain their own profiles.

Admin can add named roles using one of these five permission templates. Template permissions cannot be arbitrarily changed from the UI. System roles are retained; unused custom roles may be deleted. Deactivate users instead of deleting financial/audit history. The last active Admin is protected.

Forgot password uses an **Admin-assisted reset**, with identity verification outside the app. No email delivery service has been configured. All signed-in users can open their profile from their role name in the header. Password resets invalidate existing sessions.

### Financial definitions

All monetary values are stored as **integer centavos**; formatted totals are not hardcoded records.

- Planned amount: the Official's proposal estimate; does not reserve funds.
- Source funding received: authorized amounts entered by the Treasurer; no bank integration.
- Approved budget: approved allocations plus/minus approved realignments.
- Released fund: approved disbursement requests.
- Actual spent: sum of recorded project expenses.
- Remaining budget: approved budget minus actual spent.
- Released but unspent: released fund minus actual spent.
- Unallocated source funds: funding received minus reserved project budgets.

Expenses cannot exceed released funds. Releases cannot exceed approved budgets. Realignment moves **unreleased** budget between different projects under the same source. Pending requests do not post money. Approval locks records, checks current balances, and posts once in a transaction. Rejection has no financial effect. Historical expense/funding deletion is intentionally unavailable; expense corrections are audited. Automatic bank reconciliation, tax/accounting classifications and government form compliance are outside the documented scope.

## Files

```text
app/                 Backend helpers, POST actions, reusable layout and PHP pages
bin/create-admin.php Command-line initial account provisioning
config/example.php   Local configuration template
config/local.php     Private local configuration (not tracked)
database/schema.sql  phpMyAdmin/MySQL import
public/index.php     Web entry point and route authorization
public/assets/       CSS, JavaScript and extracted design logo
docs/                Design mapping, decisions and validation notes
tests/               Isolated database/HTTP/browser test tools
```

The private upload location is configured separately. Files are served through authorization checks, never arbitrary filesystem paths. Receipt files are available only to Treasurer and Chairperson; project photos follow project visibility. Receipts and real financial data must not be committed.

## Design fidelity

Based on the five supplied role recordings and the requirements document, with the user's approval to implement unfinished screens in the existing style. See [design-inventory.md](docs/design-inventory.md). The logo was extracted from the supplied Admin recording. The welcome page uses a CSS background and symbolic illustrations: original landscape/character assets, precise font files and full Figma access were not available. Dashboards use responsive cards, teal sidebars/tables, white panels and role navigation. Exact pixel matching to unseen Figma frames is not claimed. The prototype's misleading reused field labels have been corrected in functional forms.

## Testing

PHP syntax:

```sh
find app public bin config tests -name '*.php' -exec php -l {} \;
node --check public/assets/js/app.js
```

HTTP integration checks use only the Python standard library. Never seed a real database. Create an isolated **skprofund_test** database using a copy of the SQL with `skprofund` replaced by `skprofund_test`. Point a separate development server at it using `SK_DB_DSN`, `SK_DB_USER`, `SK_DB_PASSWORD`, and a private test `SK_STORAGE` directory. Generate a disposable `SK_TEST_PASSWORD` environment variable of at least 16 characters (do not store it in Git), then run:

```sh
php tests/seed.php
python3 tests/workflow.py
```

The seed refuses databases whose names do not end in `_test`. Tests expect a freshly imported schema and mutate disposable records. They cover role pages, authorization, account activation, proposals, publication, allocations/releases, duplicate approvals, overspending, realignment, receipt upload validation/access, CSRF, escaping, password resets and audit events. Delete the test database and its upload directory when finished.

The optional Dockerfile builds PHP with PDO MySQL for development/testing; XAMPP does not require Docker. See [validation.md](docs/validation.md) for results from this workspace.

## Backup and operations

Back up MySQL **and** private uploaded files together. Keep backups and database credentials outside the public directory. Use a dedicated database account for the application, protect local config, and provision TLS/session storage on a real host. Audit records are append-only through the application. A database administrator still has direct database access; this is not a tamper-proof external audit service.
