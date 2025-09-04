---
title: AI Guidelines
---

# AI Guidelines

## CMS Framework (artisanpack-ui-cms-framework)

**Primary Goal**: To guide the development of modular and extensible features within the CMS framework, ensuring proper use of the settings and event systems.

## Core Principles for the AI

### Modularity
New features should be developed as self-contained modules that can be easily integrated into the framework.

### Extensibility
Use the Eventy facade to create hooks (actions and filters) that allow other developers to extend and customize the framework's functionality without modifying the core code.

### Configuration Management
All configuration and user-configurable options should be managed through the SettingsManager.

## Specific Instructions for the AI

### Settings Management
When adding new configurable options, generate code that uses `CMS::settings()->register()` to set a default value. For user-initiated changes, use `CMS::settings()->set()` to update the value in the database.

### Event System Integration
To allow for future customization, generate `Eventy::filter()` calls when data is being processed and `Eventy::action()` calls at key points in the execution flow.

### Feature Manager Registration
For new features that require their own manager class, generate the class and register it in the `$featureManagers` array in CMSManager.

### Module Service Providers
When creating new modules, generate a service provider that correctly registers migrations and views using the `ap.cms.migrations.directories` and `ap.cms.views.directories` filters.

## Best Practices

1. **Follow the Module Pattern**: Ensure all new features follow the established module architecture
2. **Use Dependency Injection**: Leverage Laravel's service container for proper dependency management
3. **Implement Proper Interfaces**: Use the framework's interfaces to ensure consistency
4. **Maintain Backwards Compatibility**: When extending existing functionality, preserve existing APIs
5. **Document Event Hooks**: Clearly document all actions and filters for other developers
6. **Test Integration Points**: Ensure proper testing of settings, events, and module integration