---
title: Core
---

# Core Module

The Core module provides cross‑cutting services used throughout the CMS Framework. Currently, it includes a lightweight Asset Manager and helper functions for registering assets in different application contexts.

## Core Guides

- [Assets](Core-Assets) — Registering and retrieving admin, public, and authenticated assets

## Shared Traits (v1.1.0)

The Core module provides shared traits used across multiple modules:

- **[HasManifestParsing](developer/traits#hasmanifestparsing-trait)** — Secure JSON manifest parsing, slug validation, and path traversal prevention. Used by PluginManager and ThemeManager.

## Enums (v1.1.0)

- **[UpdateType](developer/enums#updatetype-enum)** — Categorizes update checks as `application`, `plugin`, or `theme`.

See the [[developer/traits]] and [[developer/enums]] pages for complete documentation.
