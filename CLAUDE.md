# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

This repository contains D&D 5th Edition game content in XML format. The XML files follow the compendium format used by applications like Fight Club 5e and similar D&D companion apps.

## Repository Structure

```
import-files/
  ├── backgrounds-phb.xml    # Character backgrounds from Player's Handbook
  ├── class-druid-xge.xml    # Druid class options from Xanathar's Guide to Everything
  ├── feats-phb.xml          # Feats from Player's Handbook
  ├── items-base-phb.xml     # Base items from Player's Handbook
  ├── races-phb.xml          # Player races from Player's Handbook
  └── spells-phb.xml         # Spells from Player's Handbook
```

## XML Format Structure

All XML files follow a common structure with `<compendium version="5" auto_indent="NO">` as the root element.

### Spell Format
- Each spell contains: name, level, school, casting time, range, components, duration, classes, text description
- May include `<roll>` elements for damage/scaling calculations
- May include `<ritual>YES</ritual>` for ritual spells

### Background Format
- Contains: name, proficiency skills, traits (description, features, characteristics)
- Traits include narrative text with formatting for tables (d6/d8 rolls)

### Race Format
- Contains: name, size, speed, ability score increases
- Traits categorized as "description" for lore or specific features (Age, Alignment, Size)

### Class Format
- Contains class-specific traits and options
- Organized by source book with flavor text and mechanical options
- May include roll tables for character customization

## Working with XML Files

When modifying XML files:
- Maintain the `<?xml version="1.0" encoding="UTF-8"?>` declaration
- Keep `auto_indent="NO"` attribute in the compendium tag
- Preserve proper XML escaping for special characters
- Maintain consistent indentation (tabs) within elements
- Include source citations in text fields (e.g., "Source: Player's Handbook (2014) p. XXX")

## Content Guidelines

When adding or modifying D&D content:
- Use official D&D 5e source abbreviations (PHB, XGE, DMG, etc.)
- Preserve exact wording from source material when possible
- Include page references in source citations
- Use standard school abbreviations for spells (A=Abjuration, C=Conjuration, etc.)
- Format class lists and tables using proper spacing and bullet points
