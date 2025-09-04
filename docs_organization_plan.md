# Documentation Organization Plan

## Proposed Structure

Based on the analysis of 31 existing documentation files, here's the proposed organization:

### Root Level
- home.md (main documentation index) - KEEP
- overview.md (framework overview) - KEEP  
- README.md (if needed for docs-specific readme)

### 1. getting-started/ (subdirectory)
**Parent page**: getting-started.md
**Files to move here**:
- installation.md
- configuration.md
- ai-guidelines.md

### 2. core-features/ (subdirectory) 
**Parent page**: core-features.md (to be created)

#### 2.1 core-features/administration/ (sub-subdirectory)
**Parent page**: core-features/administration.md (to be created)
**Files**:
- admin-menus.md
- dashboard-widgets.md
- implementing-dashboard-widgets.md

#### 2.2 core-features/security/ (sub-subdirectory)  
**Parent page**: core-features/security.md (to be created)
**Files**:
- users.md
- api-authentication.md
- SettingUpSanctum.md → sanctum-setup.md
- audit-logging.md
- two-factor-authentication.md
- setting-up-two-factor-authentication.md → two-factor-setup.md

#### 2.3 core-features/content/ (sub-subdirectory)
**Parent page**: core-features/content.md (to be created) 
**Files**:
- content-types.md
- settings.md
- notifications.md
- notification-implementation.md

#### 2.4 core-features/pwa/ (sub-subdirectory)
**Parent page**: core-features/pwa.md (rename existing pwa.md)
**Files**:
- pwa-integration-guide.md

### 3. api/ (subdirectory)
**Parent page**: api.md (existing file)
**Files**:
- API_EXAMPLES.md → examples.md
- api-authentication.md (cross-reference, primary in security)

### 4. development/ (subdirectory)
**Parent page**: development.md (to be created)
**Files**:
- cmsframework.md → framework-core.md
- comprehensive-cms-development-guide.md → comprehensive-guide.md
- custom-cms-implementation.md
- implementing-themes.md
- themes.md
- plugins.md
- contributing.md

### 5. guides/ (subdirectory) 
**Parent page**: guides.md (to be created)
**Files**:
- usage.md
- PERFORMANCE_TESTING_GUIDE.md → performance-testing.md
- ERROR_HANDLING_STRATEGY.md → error-handling.md

## Files Requiring YAML Headers (8 total)
- API_EXAMPLES.md → "API Examples"
- ERROR_HANDLING_STRATEGY.md → "Error Handling Strategy" 
- PERFORMANCE_TESTING_GUIDE.md → "Performance Testing Guide"
- api.md → "API Documentation"
- comprehensive-cms-development-guide.md → "Comprehensive Development Guide"
- configuration.md → "Configuration"
- contributing.md → "Contributing Guide"
- installation.md → "Installation"
- usage.md → "Usage Guide"

## New Files to Create
- getting-started.md (parent page)
- core-features.md (parent page)
- core-features/administration.md (parent page)
- core-features/security.md (parent page) 
- core-features/content.md (parent page)
- development.md (parent page)
- guides.md (parent page)