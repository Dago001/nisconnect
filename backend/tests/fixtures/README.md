# Test fixtures

## `test-signing-key.pem`

A throwaway 2048-bit RSA key, generated for the test suite and **committed on
purpose**. It is not a credential: it belongs to no account, signs nothing that
any service trusts, and grants no access to anything.

`GoogleAccessTokenTest` needs *a* private key to prove it produces a real RS256
JWT. Generating one per run with `openssl_pkey_new()` fails on a default Windows
PHP install, which cannot locate an `openssl.cnf`, so the key is checked in
instead to keep the suite runnable everywhere.

Real Firebase service-account keys never belong in this repository. They live
outside the working tree and are named by `FCM_CREDENTIALS` in `backend/.env`;
`.gitignore` also blocks `firebase-adminsdk-*.json` and `service-account*.json`.
See [docs/PUSH_NOTIFICATIONS.md](../../../docs/PUSH_NOTIFICATIONS.md).
