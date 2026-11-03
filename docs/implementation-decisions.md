# Implementation decisions

The user authorized completing unfinished Figma screens in code and accepted the Chairperson publication recommendation. Receipt images supplement entered date, supplier/payee and amount.

- Publication and financial approval are separate. Officials own proposals/progress; Chairperson approves publication. Editing a published project temporarily hides it pending reapproval.
- Realignment, allocation and disbursement use the same reviewed request mechanism with distinct monetary effects. Amounts use integer centavos.
- Actual source funding must be recorded before budgets can be allocated. This supplies the authoritative data missing from prototype sample totals; it is not a bank connection.
- Fund requests, funding receipts and expenses retain history. Unreferenced pending proposals, unused settings and unassigned custom roles can be deleted; accounts are deactivated.
- Custom roles inherit one of the five approved permission templates. Unreviewed arbitrary permission combinations are not created.
- Public entry requires no login, consistent with the resident recording. Private receipt files are excluded from public information.
- Forgot-password recovery is Admin-assisted because no mail service was supplied. Password resets revoke existing sessions and require a change on next login.
- Recommendations cite completed, public historical projects; they do not make unsupported predictive claims.
- Missing form layouts follow the recorded visual language. Original large design assets and exact typography remain unavailable; the supplied recording logo is used, and the welcome illustration is an approximation.

These decisions describe the implemented interpretation and its limits; they do not claim official government accounting compliance or exact conversion of uninspected Figma frames.

## Requested intelligent component and public feedback

The user requested generated project suggestions and a Youth/Resident suggestion/comment box. Their teacher has not specified AI/ML. The implementation therefore adds explainable rule-based ranking and editable draft generation, without external services or claims of ML. It replaces the earlier history-list-only suggestion panel. Guest/resident feedback is private to Admin/Chairperson for moderation; it is not automatically verified or fed into the generator. See recommendations.md for rules and migration instructions.
