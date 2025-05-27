# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

HLstatsX Community Edition is a real-time gaming statistics and ranking system for Source engine games (Counter-Strike, Half-Life, Team Fortress, etc.). The system consists of:

- **Web Frontend**: PHP application in `/web/` directory
- **Perl Daemon**: Real-time log parsing daemons in `/scripts/` directory  
- **Game Plugins**: SourceMod/AMX Mod X plugins in `/sourcemod/` and `/amxmodx/` directories
- **Database**: MySQL with UTF-8MB4 support and migration system in `/sql/`

## Architecture

### Web Application (`/web/`)
- **Entry Point**: `hlstats.php` - main application controller with mode-based routing
- **Page System**: Modular pages in `/pages/` directory, loaded dynamically based on `?mode=` parameter
- **Database Layer**: Custom `class_db.php` with MySQL abstraction and connection handling
- **Configuration**: `config.php` contains database and path settings (must be configured manually)
- **Assets**: Game-specific images, flags, and styling in `/hlstatsimg/` and `/styles/` directories

### Key Components
- **Session Management**: PHP sessions for game selection and authentication state
- **Caching System**: Optional historical cache system with MD5-based directory structure
- **Security**: Input validation, XSS protection, and restricted file access controls
- **Multi-game Support**: Dynamic game loading with game-specific configurations and assets

### Database Integration
- Uses custom `DB_` classes with procedural PHP patterns
- UTF-8MB4 charset with unicode collation for emoji/special character support
- Persistent connection option via `DB_PCONNECT` setting
- No ORM - direct SQL queries with input sanitization

### Development Patterns
- **Legacy Codebase**: Uses procedural PHP (circa early 2000s), not object-oriented
- **Include System**: Shared functions in `/includes/` directory loaded via `require()`
- **Mode Routing**: Single entry point with mode-based page inclusion
- **Template System**: Basic PHP templating with header/footer includes

## Working with the Codebase

### Configuration Requirements
1. Copy and configure `config.php` with database credentials and paths
2. Ensure `hlstatsimg/progress/` directory is writable for dynamic image generation
3. Set up MySQL database with UTF-8MB4 support

### Common Tasks
- **Add New Pages**: Create PHP file in `/pages/` and add mode to `$valid_modes` array in `hlstats.php`
- **Database Changes**: Use migration system in `/sql/migrations/` directory
- **Game Support**: Add game configuration and assets in appropriate directories
- **Styling**: Modify CSS files in `/styles/` directory (multiple themes supported)

### Security Considerations
- All database input must be properly sanitized (no prepared statements used)
- File inclusion uses whitelist approach with `$valid_modes` array
- XSS protection through input filtering in main controller
- Direct file access prevented via `IN_HLSTATS` constant checks

### Testing and Deployment
- No automated testing framework present
- Manual testing via web interface required
- Database migrations handled through `/updater/` system
- Perl daemons require separate deployment and configuration

## Notable Limitations
- Legacy PHP patterns (no composer, autoloading, or modern frameworks)
- No automated testing or build system
- Manual configuration required for database and paths
- Perl daemons have separate dependency management