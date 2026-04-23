# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased](https://github.com/carstingaxion/gatherpress-export-import/compare/0.2.1...HEAD)

## [0.2.1](https://github.com/carstingaxion/gatherpress-export-import/compare/0.2.0...0.2.1) - 2026-04-23

- [Small UI cleanup](https://github.com/carstingaxion/gatherpress-export-import/commit/54eeb4a9a8007edbda4177a3c4555961884cfddf)
- [Small UI cleanup](https://github.com/carstingaxion/gatherpress-export-import/commit/54dc24ace6dfba21e6c805e85443bc3d58887f83)
- [Hide Alpha notice from Playground](https://github.com/carstingaxion/gatherpress-export-import/commit/2008aba4562cb594630ff30297dae84bb3e1bfb8)
- [Just link the importer (as temp. fix for the button logic)](https://github.com/carstingaxion/gatherpress-export-import/commit/356bba59d448890ec0967e72cf29c5219a678ab3)

## [0.2.0](https://github.com/carstingaxion/gatherpress-export-import/compare/0.1.0...0.2.0) - 2026-04-23

- Import interception for six event plugins: The Events Calendar, Events Manager, Modern Events Calendar, EventON, All-in-One Event Calendar, and Event Organiser.
- Automatic post type rewriting, datetime conversion, venue linking, and taxonomy mapping during standard WordPress XML imports.
- Two-pass import strategy for plugins that store venues as taxonomy terms, with shared traits for easy extension.
- Venue detail assembly from individual meta fields into GatherPress's `gatherpress_venue_information` JSON format (TEC, Events Manager).
- Custom importer screen under Tools > Import with prerequisite checks and step-by-step guidance.
- Comprehensive unit and integration test suites, including end-to-end WXR import tests with fixture files.
- WordPress Playground blueprints with demo data for every supported source plugin.
