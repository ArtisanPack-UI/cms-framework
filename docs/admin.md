---
title: Admin
---

# Admin Module

The Admin module provides infrastructure for building an authenticated admin area including menus, pages, widgets, and authorization.

## Admin Guides

- [Menu and Pages](Admin-Menu-And-Pages) — Create sections, pages, and subpages, and understand how routes are registered
- [Widgets](Admin-Widgets) — Register and create admin dashboard widgets
- [Authorization](Admin-Authorization) — Gate capabilities and admin middleware

## Overview

The Admin module exposes simple helper functions to register menu sections and pages, while managers handle storage and routing behind the scenes:

- AdminMenuManager — Stores sections and items and builds a capability‑filtered menu structure
- AdminPageManager — Registers HTTP routes for admin pages and applies authorization middleware

See the guides above for usage and examples.
