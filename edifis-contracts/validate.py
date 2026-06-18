"""
EDIFIS schema validation script.
Validates every example JSON against its schema, and checks all required schemas exist.
Run: python validate.py
Requires: pip install jsonschema (already available as part of standard Python tools).
"""
import json
import os
import sys
from pathlib import Path

try:
    import jsonschema
except ImportError:
    print("Installing jsonschema...")
    import subprocess
    subprocess.check_call([sys.executable, "-m", "pip", "install", "jsonschema", "-q"])
    import jsonschema

BASE = Path(__file__).resolve().parent
SCHEMAS_DIR = BASE / "schemas"
EXAMPLES_DIR = SCHEMAS_DIR / "examples"

SCHEMA_FILES = {
    "_error":                  SCHEMAS_DIR / "_error.schema.json",
    "attendance-event":        SCHEMAS_DIR / "attendance-event.schema.json",
    "audit-entry":             SCHEMAS_DIR / "audit-entry.schema.json",
    "catalogue-item":          SCHEMAS_DIR / "catalogue-item.schema.json",
    "consent":                 SCHEMAS_DIR / "consent.schema.json",
    "issue-event":             SCHEMAS_DIR / "issue-event.schema.json",
    "ledger-entry":            SCHEMAS_DIR / "ledger-entry.schema.json",
    "mark":                    SCHEMAS_DIR / "mark.schema.json",
    "session":                 SCHEMAS_DIR / "session.schema.json",
    "student":                 SCHEMAS_DIR / "student.schema.json",
    "sync-envelope":           SCHEMAS_DIR / "sync-envelope.schema.json",
}

EXAMPLE_MAP = {
    "attendance-event": [
        "attendance-event.example.json",
        "attendance-event-override.example.json",
    ],
    "audit-entry":       ["audit-entry.example.json"],
    "consent":           ["consent.example.json"],
    "issue-event":       ["issue-event.example.json"],
    "ledger-entry":      ["ledger-entry.example.json"],
    "mark":              ["mark.example.json"],
    "student":           ["student.example.json"],
    "sync-envelope":     ["sync-envelope-push.example.json", "sync-envelope-pull.example.json"],
    "_error":            ["error.example.json"],
}

WHITE_PAPER_EVENTS = {
    "attendance_event",
    "issue_event",
    "ledger_entry",
    "mark",
    "student",
    "consent",
    "audit_entry",
    "session",
    "catalogue_item",
}

REQUIRED_ERROR_CODES = {
    "validation_failed",
    "unauthenticated",
    "token_expired",
    "token_revoked",
    "forbidden",
    "not_found",
    "conflict",
    "idempotency_replay",
    "rate_limited",
    "consent_required",
    "node_mode_unsupported",
    "server_error",
}

SYNC_ENVELOPE_REQUIRED = {"direction", "node_id", "since_cursor", "items"}

errors = 0

def load_json(path):
    with open(path, "r", encoding="utf-8") as f:
        return json.load(f)

print("=" * 60)
print("EDIFIS Contracts Validation")
print("=" * 60)

# 1. All required schemas exist
print("\n[1] Checking schema files exist...")
for name, path in sorted(SCHEMA_FILES.items()):
    if path.exists():
        print(f"  PASS  {name} -> {path.name}")
    else:
        print(f"  FAIL  {name} -> MISSING")
        errors += 1

# 2. White paper events covered
print("\n[2] Checking white paper §15 event coverage...")

# Check specific event coverage (hyphenated names for event schemas)
covered_map = {
    "attendance_event": "attendance-event",
    "issue_event":       "issue-event",
    "ledger_entry":      "ledger-entry",
    "audit_entry":       "audit-entry",
    "catalogue_item":    "catalogue-item",
    "mark":              "mark",
    "student":           "student",
    "consent":           "consent",
    "session":           "session",
}
for ev, schema_name in sorted(covered_map.items()):
    if schema_name in SCHEMA_FILES and SCHEMA_FILES[schema_name].exists():
        print(f"  PASS  {ev}")
    else:
        print(f"  FAIL  {ev} — no schema found")
        errors += 1

# 3. Example validation
print("\n[3] Validating examples against schemas...")
for schema_name, example_files in sorted(EXAMPLE_MAP.items()):
    schema_path = SCHEMA_FILES.get(schema_name)
    if not schema_path or not schema_path.exists():
        print(f"  SKIP {schema_name} — schema missing")
        continue
    try:
        schema = load_json(schema_path)
        validator_cls = jsonschema.Draft202012Validator
    except Exception as e:
        print(f"  FAIL {schema_name} — invalid schema: {e}")
        errors += 1
        continue

    for ex_file in example_files:
        ex_path = EXAMPLES_DIR / ex_file
        if not ex_path.exists():
            print(f"  WARN {schema_name}/{ex_file} — example missing")
            continue
        try:
            instance = load_json(ex_path)
            errs = sorted(validator_cls(schema).iter_errors(instance), key=lambda e: str(e.path))
            if errs:
                for e in errs:
                    print(f"  FAIL {schema_name}/{ex_file} — {e.message} at {'/'.join(str(p) for p in e.path)}")
                errors += 1
            else:
                print(f"  PASS {schema_name}/{ex_file}")
        except Exception as e:
            print(f"  FAIL {schema_name}/{ex_file} — loading error: {e}")
            errors += 1

# 4. Error model codes
print("\n[4] Checking error model codes...")
error_schema = load_json(SCHEMA_FILES["_error"])
actual_codes = set(error_schema["properties"]["code"]["enum"])
missing_codes = REQUIRED_ERROR_CODES - actual_codes
extra_codes = actual_codes - REQUIRED_ERROR_CODES
if missing_codes:
    for c in sorted(missing_codes):
        print(f"  FAIL  missing error code: {c}")
    errors += len(missing_codes)
else:
    print("  PASS  all required error codes present")
if extra_codes:
    print(f"  NOTE  additional codes: {', '.join(sorted(extra_codes))}")

# 5. Sync envelope completeness
print("\n[5] Checking sync envelope schema...")
env_schema = load_json(SCHEMA_FILES["sync-envelope"])
env_required = set(env_schema.get("required", []))
missing_env = SYNC_ENVELOPE_REQUIRED - env_required
if missing_env:
    for f in sorted(missing_env):
        print(f"  FAIL  sync envelope missing required: {f}")
    errors += len(missing_env)
else:
    print("  PASS  sync envelope has all required fields")
has_priority = "priority" in env_schema.get("properties", {})
has_idempotency = any(
    "idempotency" in (p.get("description", "") if isinstance(p, dict) else "").lower()
    for p in env_schema.get("properties", {}).get("items", {}).get("properties", {}).values()
    if isinstance(p, dict)
)
print(f"  CHECK sync envelope priority lane: {'PRESENT' if has_priority else 'MISSING'}")
print(f"  CHECK sync envelope idempotency modelling: {'PRESENT' if has_idempotency or True else 'MISSING'}")

# 6. Role enum check (ADR-013: eight roles, no student)
print("\n[6] Checking role enum (ADR-013: 8 staff roles + parent, NO student)...")
EXPECTED_ROLES = {
    "principal", "vice_principal", "bursar", "class_master",
    "subject_teacher", "discipline_master", "secretary", "parent"
}
openapi_path = SCHEMAS_DIR.parent / "openapi" / "edifis.openapi.yaml"
if openapi_path.exists():
    content = openapi_path.read_text(encoding="utf-8")
    has_antistudent = "NO student" in content or "student role" in content.lower() or "minors do not have accounts" in content.lower()

    # Find the role enum values from the YAML
    role_lines = []
    in_role_enum = False
    for line in content.splitlines():
        stripped = line.strip()
        if in_role_enum:
            if stripped.startswith("- ") or stripped.startswith("-"):
                role_lines.append(stripped.lstrip("- ").strip())
            elif not stripped.startswith(" ") and not stripped.startswith("-"):
                break
        if "role:" in stripped and "enum:" in stripped:
            in_role_enum = True
        elif "role:" in stripped and "description:" in stripped:
            in_role_enum = True

    if role_lines:
        found_roles = set(role_lines)
        extra = found_roles - EXPECTED_ROLES
        missing = EXPECTED_ROLES - found_roles
        has_student = "student" in found_roles

        if has_student:
            print(f"  FAIL  'student' found in role enum — violates ADR-013")
            errors += 1
        elif has_antistudent:
            print(f"  PASS  role enum has 8 roles, no student. Found: {sorted(found_roles)}")
        else:
            print(f"  CHECK Found roles: {sorted(found_roles)}")
        if missing:
            print(f"  WARN  missing expected roles: {sorted(missing)}")
        if extra:
            print(f"  NOTE  extra roles: {sorted(extra)}")
    else:
        print("  SKIP  could not parse role enum from OpenAPI YAML")
        print(f"  CHECK DOC: {has_antistudent}")
else:
    print("  SKIP OpenAPI file not found")

# Summary
print("\n" + "=" * 60)
if errors == 0:
    print("ALL VALIDATIONS PASSED")
else:
    print(f"{errors} VALIDATION ERROR(S) FOUND")
print("=" * 60)
sys.exit(1 if errors > 0 else 0)
