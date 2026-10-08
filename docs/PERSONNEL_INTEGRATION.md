# NISconnect — Personnel Integration

The mobile app **never** talks to the NIS personnel database directly. All verification flows
through the NISconnect backend, which selects a **personnel provider** by configuration.

```
Flutter App -> NISconnect API -> Auth -> PersonnelVerificationService -> PersonnelProvider -> NIS source
```

## Provider interface
`App\Personnel\PersonnelProviderInterface`:

```php
interface PersonnelProviderInterface
{
    /** Look up an authorised personnel record by numeric service number. */
    public function findByServiceNumber(string $serviceNumber): ?PersonnelRecordData;
}
```

`PersonnelRecordData` is an immutable DTO carrying only NIS‑approved fields (service number,
surname, first/other name, rank, directorate, department, zone, command, formation, unit,
posting, official email, status, photo reference). Which fields are returned to the client is
controlled by `config/personnel.php` → `fields`.

## Implementations
| Provider | Class | Use |
|----------|-------|-----|
| Demo | `DemoPersonnelProvider` | **Development/testing only.** Sample records. Throws if `APP_ENV=production`. |
| API | `ApiPersonnelProvider` | Calls the real NIS Personnel REST API (base URL + credentials from env). |
| Database | `DatabasePersonnelProvider` | Reads a read‑only NIS personnel DB connection. |

## Selection
```
# backend/.env
PERSONNEL_PROVIDER=demo        # demo | api | database
PERSONNEL_DEMO_ACCEPT_ANY=false  # demo only: treat unknown numbers as active test officers (never in production)
PERSONNEL_SERVICE_NUMBER_LENGTH=          # empty = any length; or fixed e.g. 6
PERSONNEL_SERVICE_NUMBER_MIN=4
PERSONNEL_SERVICE_NUMBER_MAX=12

# api provider
PERSONNEL_API_BASE_URL=
PERSONNEL_API_KEY=
PERSONNEL_API_TIMEOUT=10

# database provider (read-only connection)
PERSONNEL_DB_CONNECTION=nis_personnel
```

Bound in `PersonnelServiceProvider` — swapping providers requires **no code change** in
controllers, services or the app. Connecting the real NIS source later = set env + provide
the read‑only connection/credentials.

## Configurable Service Number rules
- Digits only (`^[0-9]+$`), enforced client‑side (numeric keyboard + formatter) and
  server‑side (Form Request rule).
- Length is **configurable** (exact length, or min/max), never needlessly hard‑coded.
- Stored as `VARCHAR(20)` with a digit check constraint so **leading zeroes are preserved**
  (`001234` never becomes `1234`).

## Error handling
- Not found / not authorised → generic `"We could not verify this Service Number. Please
  check the number and try again."` (no enumeration signal).
- Source unavailable → `"The personnel verification service is temporarily unavailable.
  Please try again later."` No SQL errors, stack traces or internal detail ever surface.
