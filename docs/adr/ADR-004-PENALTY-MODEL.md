# ADR-004: Legacy `money` Becomes An Admin-Controlled Penalty Ledger

Status: Accepted

Date: 2026-03-06

## Why This ADR Exists

The legacy schema uses `money`, which invites the wrong mental model.

The user clarified that this is not a real money system.
It is only used to track punishment for a student, and only an admin can remove it.

If I treat it like a wallet, allowance, or payment system, I will build the wrong thing.

## Context

- Legacy data includes `dle_users.money` and `pay_log`.
- The UI and code may use financial wording, but the business meaning is discipline tracking.
- Students do not self-settle penalties.
- Admin action is required to clear or reduce them.

## Decision

The rewrite models this domain as a penalty ledger.

Core concepts:

- `penalty_accounts`
- `penalty_transactions`
- `violations`
- `violation_resolutions`

Penalty balance is an admin-controlled discipline number, not a wallet balance.

## Implementation Rules

- Store penalty amounts as integer units.
- Every change to the penalty balance must create a ledger transaction.
- Students can view penalties, but cannot clear, pay, or reduce them.
- Admin clearance or waiver is explicit and auditable.
- Device consequence completion may create a note or event, but it does not directly zero the balance automatically unless the admin workflow explicitly does so.

## Do Not Do

- Do not build payment flows.
- Do not build allowance or earning logic unless the product explicitly changes later.
- Do not model external payment providers, checkout, or credits.
- Do not name the module `Wallet`.

## Consequences

- The module is simpler and truer to the real product.
- Auditability improves.
- The UI language can be made explicit and non-financial.

## Revisit Only If

- the product later introduces real earning, spending, or student-managed balance behavior

Until then, this is discipline state, not finance.
