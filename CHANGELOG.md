# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.4.1] - 04-25-2023

### Added

- Added migration query update to the Install migration for rosas->lsst namespace

## [0.4] - 04-12-2023

### Changed

- Namespace change from rosas -> lsst

### Fixed

- Tech debt clean-up:
  - PhpStorm-isms for method/class annotations
  - Miscellaneous tech debt clean-up
  - Changed return type of exception logic in Assets service to string
  - Changed logging to Craft::warn() for stdout