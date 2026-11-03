# Validation results

Validated in the cloud workspace using PHP 8.3 CLI with PDO MySQL, MariaDB 11.4, and installed Chromium through Playwright. XAMPP itself was not available in this Linux workspace; the Windows setup instructions have not been executed on a Windows machine.

## Passed

- Schema imported into fresh MariaDB databases, including foreign keys and monetary constraints.
- PHP syntax checks across application, templates, configuration example, CLI utility and test seed.
- JavaScript syntax check with Node.
- **94 HTTP/database integration checks**, rerun successfully after backend corrections: all role pages; permission denials; categories/sources; role template and account creation; first-login password change; proposal ownership; proposal approval separate from publication; actual source funding; allocations/disbursements; insufficient-fund rejection; duplicate approval prevention; receipt image upload/download authorization; fake-image rejection; expense correction and exact balances; progress publication review; realignment of two budgets; CSRF; last-Admin protection; stored-text escaping; password reset session invalidation; audit records.
- CLI first-Admin creation and duplicate-Admin protection, in a separate disposable database.
- Chromium desktop checks at 1440×1000 and 1280×900; Admin, Chairperson, Treasurer and Official-template forms/pages; no JavaScript runtime errors observed.
- Chromium phone checks at 390×844: public navigation, responsive page width, welcome and project pages. Tables scroll inside their containers rather than breaking the page width. Confirmation-dialog cancel was exercised.
- Visual inspection of desktop dashboard, public project page and mobile welcome against recording references. A mobile table button-wrap issue was corrected.
- `git diff --check` and source archive integrity check.

## Limits

- This is functional workflow validation, not an exhaustive security audit or government accounting certification.
- Exact Figma font metrics, illustration assets and unshown frames were unavailable. Missing screens were completed in code as authorized. The welcome landscape/character artwork is approximated; the recorded logo is used.
- No bank or mail-service integration. Password recovery is Admin-assisted. Report PDF output uses browser printing.
- Real production financial data and real receipt records were not used. Test fixtures and uploads are excluded from the source ZIP. The distributed schema contains role definitions only, with no accounts or financial seed records.
- Publication approval temporarily hides edited project information pending review; it does not retain a separate live published revision.

See README.md for the reproducible XAMPP workflow and isolated-test instructions. Browser artifacts are local, ignored test outputs, not production records.

## Recommendation generator and community feedback update

- Existing 94 workflow checks rerun successfully on the updated fresh schema.
- 21 pure recommendation checks: no-history templates, evidence-based ranking, participant-scaled historical estimates, median benchmark, budget exclusions, private/unapproved history exclusion, duplicate penalty, no-match handling, deterministic output, draft assumptions and invalid inputs.
- 34 additional HTTP checks: generation/draft workflow, human submission, no implicit database/financial posting, authorization, cold-start assumptions, insufficient-funding warning, forged selection rejection, guest/resident feedback submission, staff review, stored-text escaping, private inbox boundaries, invalid project rejection, persistent message-rate limits and moderation audit.
- New browser checks cover ranked suggestions, editable unsaved draft, required human-entered schedule/location, mobile feedback layout/privacy and staff inbox navigation.
- Existing installation feedback migration is provided separately; full schema includes the new tables for fresh installs.
