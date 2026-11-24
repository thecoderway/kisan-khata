# Geographic Master Data and Import Runbook

**Status:** Implemented and imported  
**Last verified:** 2026-11-24  
**Dataset:** Madhya Pradesh Local Government Directory (LGD) village snapshot  
**Snapshot date:** 21-04-2026

## 1. Purpose and scope

Kisan Khata uses official LGD administrative codes to provide consistent State → District → Subdistrict/Tehsil → Village selection.

The current imported dataset covers **Madhya Pradesh only**. It is not an all-India master dataset. The table is shared read-only reference data used by tenant workflows; it is not owned by an individual shop.

This master-data delivery prepares the foundation for Phase 4. It does not mean Shop Profile location editing, customer location forms, lookup APIs or customer management have already been implemented.

## 2. Source file and snapshot selection

The source CSV used for the import is:

`imports/mp_villages.csv`

The file is intentionally excluded from Git by this `.gitignore` rule:

```gitignore
# Local government master-data imports
/imports/*.csv
```

The CSV contains 17 source columns and multiple historical snapshots. The import selected only records whose `data_gov_update_date` equals `21-04-2026`.

Dataset selection facts recorded during preparation:

- Latest selected snapshot: `21-04-2026`.
- Selected unique village codes: **57,484**.
- Historical-only records: 29, excluded from the selected snapshot.
- Older records overlapping the latest snapshot: 1,376 duplicate village-code conflicts; only the latest snapshot was selected.
- Selected data state code: `23` (Madhya Pradesh).

Do not combine historical snapshots in the live table. A village name is not a unique identifier; use LGD codes and the administrative hierarchy.

## 3. Database schema

Migration:

`database/migrations/2026_11_24_000003_create_villages_table.php`

Table: `villages`

The table preserves the 17 CSV source columns and their source naming/order:

1. `villageCode` — string(20), primary key
2. `villageNameEnglish` — string(255), required
3. `villageNameLocal` — string(255), nullable
4. `villageCensus2011Code` — string(20), nullable
5. `subdistrictCode` — string(20), required
6. `subdistrictNameEnglish` — string(255), required
7. `subdistrictNameLocal` — string(255), nullable
8. `subdistrictCensus2011Code` — string(20), nullable
9. `districtCode` — string(20), required
10. `districtNameEnglish` — string(255), required
11. `districtNameLocal` — string(255), nullable
12. `districtCensus2011Code` — string(20), nullable
13. `stateCode` — string(20), required
14. `stateNameEnglish` — string(255), required
15. `stateNameLocal` — string(255), nullable
16. `stateCensus2011Code` — string(20), nullable
17. `data_gov_update_date` — string(20), nullable; source format is DD-MM-YYYY

Indexes:

- `subdistrictCode`
- `districtCode`
- `stateCode`
- Composite `stateCode, districtCode, subdistrictCode`
- Composite `villageNameEnglish, districtCode, subdistrictCode`

Village names are deliberately non-unique. LGD codes are stored as strings to preserve source representation and leading zeros where present.

## 4. Import command

Command source:

`app/Console/Commands/ImportVillages.php`

Signature:

```text
lgd:import-villages
  {--source=imports/mp_villages.csv}
  {--date=21-04-2026}
  {--expected=57484}
  {--chunk=500}
  {--execute}
```

The command is validation-only by default. It does not insert rows unless `--execute` is explicitly supplied.

### Validation and safety behavior

Before importing, the command:

1. Checks the requested date uses DD-MM-YYYY.
2. Checks expected count and chunk-size bounds.
3. Checks the source file exists and is readable.
4. Validates the exact 17-column header names and order, including UTF-8 BOM handling.
5. Scans only rows matching the selected snapshot date.
6. Validates row column counts and required values.
7. Rejects duplicate `villageCode` values within the selected snapshot.
8. Verifies the selected row count equals the expected count.
9. Calculates a SHA-256 hash of the source file.
10. Refuses actual import if the `villages` table is not empty.
11. Rechecks table emptiness and source hash inside the database transaction.
12. Inserts in configurable chunks and verifies inserted/database counts and source hash before transaction completion.

Any exception during the transaction causes rollback. The command does not merge historical snapshots or update existing rows.

## 5. Re-running or refreshing data

The current command intentionally refuses to import into a non-empty table. It is a one-time initial-load command, not an upsert or synchronization mechanism.

For a future LGD refresh:

1. Obtain and review a new official source file and snapshot date.
2. Compare schema/header changes and code changes.
3. Decide and document whether the update requires a controlled full replacement, a versioned snapshot table, or a migration strategy.
4. Back up the database and verify restore capability before any replacement.
5. Update the command and expected validation criteria only after review.
6. Run dry-run validation first and obtain explicit approval before changing live reference data.

Do not truncate or delete the current master table as an informal refresh procedure.

## 6. Import execution procedure

Run from the Laravel project root. The CSV must be present at the configured source path in the application container.

Dry run:

```bash
docker compose exec -T app php artisan lgd:import-villages
```

Actual import, only after reviewing successful dry-run output and receiving explicit authorization:

```bash
docker compose exec -u www-data -T app php artisan lgd:import-villages --execute
```

For an alternate source, date, expected count or chunk size, pass the corresponding documented option. Do not guess the expected count; derive it from the reviewed snapshot.

## 7. Verified initial import evidence (2026-11-24)

The following results were observed after the approved import:

- Command result: `Import successful: 57484 villages inserted.`
- Total rows in `villages`: **57,484**.
- Unique `villageCode` values: **57,484**.
- Snapshot date distribution: only `21-04-2026`, with 57,484 rows.
- State code distribution: only `23`, with 57,484 rows.
- Git status was clean after commit/push; the CSV remained ignored.
- Migration and import command were committed and pushed to `main`.

These are historical verification results for the initial import. Re-run checks after any future data refresh.

## 8. Recommended verification queries

Use read-only queries after import or refresh:

```sql
SELECT COUNT(*) AS total_rows,
       COUNT(DISTINCT villageCode) AS unique_village_codes
FROM villages;

SELECT data_gov_update_date, COUNT(*) AS rows_count
FROM villages
GROUP BY data_gov_update_date;

SELECT stateCode, stateNameEnglish, COUNT(*) AS rows_count
FROM villages
GROUP BY stateCode, stateNameEnglish;
```

For the recorded initial import, expected results are 57,484 total rows, 57,484 unique village codes, one date (`21-04-2026`) and one state code (`23`).

## 9. Application integration rules

- The `villages` table is shared, read-only reference data and must not contain `shop_id`.
- Ordinary shop users must never receive permission to import, edit or delete master data.
- Expose only read-only lookup operations to retrieve State, District, Subdistrict and Village options from the imported hierarchy.
- Use LGD codes as stable identifiers; do not rely on village names as unique keys.
- Validate submitted parent-child relationships server-side.
- Shop and customer location fields store their own selected LGD codes. They do not copy or mutate master rows.
- The currently available dataset supports Madhya Pradesh only; unsupported states must not be presented as having complete location options.

## 10. Related documentation

- `docs/01-product-requirements.md`
- `docs/02-business-rules.md`
- `docs/04-database-architecture.md`
- `docs/05-multi-tenancy.md`
- `docs/06-security.md`
- `docs/07-ui-ux-guidelines.md`
- `docs/08-api-architecture.md`
- `docs/09-testing-strategy.md`
- `docs/10-development-roadmap.md`
- `docs/PROJECT-STATUS.md`
- `database/migrations/2026_11_24_000003_create_villages_table.php`
- `app/Console/Commands/ImportVillages.php`
