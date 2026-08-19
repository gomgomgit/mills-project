# Derived Assumptions Log — module-master-data.screen-027--kelola-corporate.3-tech-spec

## v1 — 2026-08-19
(see earlier entries)

## v2 — 2026-08-19 (ERD rework)

- logo validation rule (jpg/png, max 2MB) ← not specified anywhere, inferred as a reasonable default file-upload constraint, mirrors typical Laravel validation patterns; flagged since no source document specifies exact limits
- logo_url as the response field name (vs `logo` as the request/upload field name) ← mirrors machinery.picture_url's request-vs-response naming split, kept consistent across the two entities that now have image uploads
- corporate_code and name both required+unique independently (not one superseding the other) ← direct continuation of the entity-catalog v4 decision already logged there
