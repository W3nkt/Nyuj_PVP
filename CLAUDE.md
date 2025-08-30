# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Platform Overview

Bull PVP is a skill-based competitive gaming platform with audit chain transaction logging. Users place bets on matches between competitors, with results verified by trusted streamers and recorded on an immutable audit chain.

## Key Architecture Components

### Core System Structure
- **Frontend**: PHP-based web interface using Bootstrap CSS framework
- **Database**: MySQL with PDO for data persistence
- **Blockchain**: Custom implementation with proof-of-work transaction logging for transaction immutability
- **Authentication**: Session-based with role-based access control (user/streamer/admin)
- **File Uploads**: Competitor images stored in `assets/uploads/competitors/`

### Database Architecture
- **Main Schema**: `database_schema_fixed.sql` - Core platform tables (users, events, bets, wallets)
- **Blockchain Schema**: `audit chain_schema.sql` - Immutable transaction logging system
- **Migration**: `audit chain_migration.sql` - Links legacy system to audit chain

### Directory Structure
- `/config/` - Database connection, audit chain class, session management
- `/admin/` - Admin panel for managing events and users
- `/user/` - User dashboard, betting interface, wallet management
- `/api/` - AJAX endpoints for betting, wallet operations, transaction logging
- `/auth/` - Login, registration, logout functionality
- `/assets/` - CSS, JavaScript, images, file uploads

## Development Environment Setup

### Database Initialization
1. Execute `database_schema_fixed.sql` first (core tables)
2. Execute `audit chain_schema.sql` (audit chain tables)
3. Execute `audit chain_migration.sql` (integration)
4. Run `setup.php` to create admin account and sample data

### Audit Chain System Setup
1. Navigate to `init_audit chain.php` to initialize the audit chain
2. Creates genesis block and platform wallet with $1,000,000
3. View audit chain status at `audit chain_explorer.php`

### Common Development Tasks

#### Database Operations
- Connection class: `config/database.php` (uses PDO with error handling)
- Database credentials: Host=localhost, DB=battle_bidding, User=ckateng

#### Testing the Platform
- Run `setup.php` to create sample accounts (admin, streamers, test users)
- Default test password: `password123`
- Admin panel: `/admin/index.php`
- User dashboard: `/user/dashboard.php`

#### Working with Blockchain
- All financial transactions logged via `config/Blockchain.php`
- Mining system: `api/transaction logging.php` and `cron_transaction logging.php`
- Transaction types: deposit, withdrawal, bet_place, bet_match, bet_win, etc.
- Double-spend prevention built into transaction validation

## Key Development Patterns

### Transaction Flow
1. User action creates transaction in mempool
2. Mining process validates and creates block
3. Balances updated atomically with database transactions
4. Immutable record stored in audit chain_transactions table

### Event/Betting Workflow
- Events progress through states: created → accepting_bets → live → streamer_voting → completed
- Bets have status: pending → matched → won/lost/refunded
- Platform takes configurable fee percentage (default 7%)

### Security Patterns
- All user inputs escaped with `htmlspecialchars()`
- Database queries use prepared statements
- Role-based access control with `requireRole()` function
- File uploads restricted and validated

### Error Handling
- Database connection errors show detailed debug info in development
- Blockchain validation prevents double-spending attempts
- Transaction failures are logged with rollback protection

## Testing and Debugging

- `test_connection.php` - Database connectivity test
- `debug.php` - General system debugging
- `audit chain_explorer.php` - View audit chain state and transactions
- `phpinfo.php` - PHP configuration details

## File Upload Configuration

- Competitor images: `assets/uploads/competitors/`
- Profile pictures: `assets/uploads/profile_pictures/`
- Naming convention: `{type}_{id}_{timestamp}.{ext}`