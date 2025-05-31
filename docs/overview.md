# ArtisanPack UI CMS Framework

## Overview

The ArtisanPack UI CMS Framework is a modular content management system framework built on top of Laravel. It provides a structured way to build and extend CMS functionality through a module-based architecture.

## Core Concepts

### Modules

The framework is built around the concept of modules. A module is a self-contained unit of functionality that can be registered with the framework. There are four types of modules:

1. **Base Module**: Implements the `Module` interface and provides basic functionality.
2. **Admin Module**: Extends the base module with admin-specific functionality.
3. **Public Module**: Extends the base module with public-facing functionality.
4. **Auth Module**: Extends the base module with authentication-specific functionality.

Each module type has its own initialization method that is called at the appropriate time during the application lifecycle.

### Event System

The framework uses the Eventy event system to provide hooks for extending functionality. This allows modules to register actions and filters that can be triggered at various points in the application lifecycle.

### Settings

The framework includes a Settings module that provides a way to store and retrieve application settings. Settings can be categorized and are stored in the database.

## Global Helper

The framework provides a global helper function `cmsFramework()` that returns an instance of the Functions utility. This utility provides access to all functions registered by modules.

## Integration with Laravel

The framework is integrated with Laravel through a service provider that registers the framework with the Laravel service container and loads migrations and views.

## Getting Started

To use the framework, you need to:

1. Install the package via Composer
2. Register the service provider in your Laravel application
3. Create and register your own modules

## Available Modules

Currently, the framework includes the following modules:

- **Settings**: Provides functionality for managing application settings