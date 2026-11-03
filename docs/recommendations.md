# Project suggestions, draft generation and community feedback

## What it does

The Official's Projects page now has a rule-based recommendation and draft generator. It maps a supplied youth need to planning templates and relevant completed, approved public projects. It ranks candidates, explains the rules and prepares an editable proposal draft. It does not call an AI service, train a machine-learning model or predict project success. Whether this satisfies an academic definition of an intelligent component must be confirmed with the teacher.

Inputs: specific need text, configured category, fund source, budget ceiling, target group and proposed beneficiary count. The fund source's recorded unallocated balance is shown as a generation-time snapshot; insufficient available funding produces a warning, not a false approval. A proposal can be planned before funding is available.

## Candidate selection and scoring

- Historical candidates use up to the latest 200 completed, approved, publicly visible projects in the chosen category. Nonpublic/unapproved/incomplete projects are excluded.
- Planning templates cover learning, digital skills, health/hygiene, wellbeing awareness, clean-up, sports, livelihood and youth consultation. These are proposed activities, not fake records or guaranteed outcomes.
- A template must match a compatible category name. Custom categories without compatible templates can use matching historical projects. Empty or unrelated inputs produce no suggestions.
- Need text is tokenized, common words removed, and matching candidate keywords counted. Need score = `60 * min(1, matched_tokens / min(5, need_tokens))`, rounded to an integer.
- A historical estimate fitting the user ceiling adds 20 affordability points. Candidates with known estimates over the ceiling are excluded. Unknown costs receive zero affordability points and are explicitly labelled unverified.
- A direct completed project reference adds 20 evidence points; a template supported by a category benchmark adds 10; an unsupported template adds zero.
- Similar active own/public projects reduce the score by 15 using title-token Jaccard overlap of at least 0.65. Other officials' private proposals are not exposed for this comparison.
- Highest five scores are shown, with deterministic tie-breaking. These scores are matching rules, not probabilities or evidence of causal impact.

## Costs and draft output

A historical estimate uses actual recorded expense. If the old beneficiary count is positive, it scales that expense to the proposed count. Otherwise it uses whole-project expense and warns that scope was not adjusted. A template receives a median scaled cost benchmark only when at least three completed public projects in the category have actual expenses and positive beneficiary counts. Category averages/medians can mix different scopes: current quotations are always needed.

Without reliable cost history, the proposed amount is the official's stated ceiling, labelled as a planning placeholder, never as a cost estimate. All costs are integer centavos. Generated drafts contain a proposed title, objective, activities, supplied need and participants, planning amount and assumptions. Dates, venue and committee are left for the official to confirm. The draft is session-local, belongs to the current account and expires after 30 minutes. Generating it creates no project or financial record. Explicit submission uses the existing proposal validation and Chairperson approval flow.

## Community feedback

Guests and resident accounts can submit a Suggestion or Comment, optional provided name, subject and message, and optionally select a currently public project. Messages are private. Admin and Chairperson can review, mark status and keep internal notes. Guest identities and claims are unverified; Reviewed is an administrative status, not proof of truth. Messages are not automatically injected into recommendation criteria or published.

CSRF checks, field-length limits, safe output encoding, role-based inbox access and a persistent five-per-ten-minute connection limit apply. Shared IP connections share the limit. Review actions are audited. No real financial data, bank details or passwords should be entered in public feedback.

## Updating

For an existing database, import `database/migrations/002_public_feedback.sql` once while the existing database is selected. Fresh installs use the full schema. Back up before changes. The migration adds only feedback and rate-limit tables; it does not overwrite existing application records.
