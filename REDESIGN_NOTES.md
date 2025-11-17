# Schema Redesign - Starting Fresh

**Date:** 2025-11-17
**Branch:** schema-redesign
**Reason:** Align implementation with approved database design document

## What We're Doing

Starting from scratch to implement the database schema **exactly** as specified in the approved design document.

## Key Changes from Old Implementation

1. ✅ **NO timestamps** on any table (static compendium data)
2. ✅ **`source_id`** not `source_book_id`
3. ✅ **`sources`** table not `source_books`
4. ✅ **`source_pages`** as text (supports "148, 150")
5. ✅ **All 31 tables** from design (vs 15 in old implementation)
6. ✅ **`needs_concentration`** field for spells
7. ✅ **Comprehensive items** table with weapon/armor/magic columns
8. ✅ **`edition`** field in sources
9. ✅ **FK-based polymorphic** relationships
10. ✅ **Classes and monsters** systems included

## Implementation Plan

Following: `docs/plans/2025-11-17-dnd-xml-importer-implementation.md`

28 tasks across 7 phases to build complete D&D 5e compendium.

## Current Status

Clean slate - ready to begin Task 1 of new implementation plan.
