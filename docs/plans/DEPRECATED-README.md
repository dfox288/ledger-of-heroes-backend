# DEPRECATED PLAN NOTICE

**File:** `2025-11-17-dnd-xml-importer-implementation-OLD-DEPRECATED.md`

**Status:** ⚠️ DEPRECATED - DO NOT USE

## Why Deprecated?

This implementation plan had **52% of the database schema missing** and significant discrepancies with the approved database design document including:

- ❌ Used `source_book_id` instead of `source_id`
- ❌ Missing `needs_concentration` column for spells
- ❌ Missing 16 tables from the approved design (~52% incomplete)
- ❌ Items table missing weapon/armor/magic item columns
- ❌ Added timestamps when design explicitly says NO timestamps
- ❌ Used `source_page` (integer) instead of `source_pages` (text)
- ❌ Missing `edition` field from sources table

## Current Plan

**Use:** `2025-11-17-dnd-xml-importer-implementation.md` (the rewritten version)

This plan is fully aligned with:
- Database design document (docs/plans/2025-11-17-dnd-compendium-database-design.md)
- All 31 tables from approved schema
- Correct naming conventions
- NO timestamps policy
- Comprehensive items schema
- FK-based polymorphic relationships

## Migration Path

If you already implemented from the old plan:
1. Create new branch: `git checkout -b schema-redesign`
2. Follow the new implementation plan from scratch
3. Migrate existing data if needed
