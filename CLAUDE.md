# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PHP implementation of the Model Context Protocol (MCP) client. Communicates with MCP servers via JSON-RPC 2.0 over stdio transport (HTTP/SSE transport not yet implemented).

**Protocol Version:** 2025-11-25

## Commands

```bash
# Install dependencies
composer install

# Run tests
composer test                                  # All tests
./vendor/bin/phpunit tests/Unit                # Unit tests only
./vendor/bin/phpunit --filter=McpClientTest    # Single test class

# Static analysis (level 9)
composer phpstan

# Code style
composer phpcs

# Run example
php examples/example-stdio.php npx -y @modelcontextprotocol/server-filesystem /tmp
```

## Code Quality Rules

After every file modification or addition in `src/`:

1. Run `/inspect` on the modified file(s) to check for IDE-detected issues
2. Run `composer phpstan` to verify static analysis passes at level 9

Do not consider a task complete until both checks pass without errors.

## Architecture

The codebase uses a flat structure under `src/` with logical grouping by concern:

### Key Components

**McpClient** (`Client/McpClient.php`) - Main client orchestrating the MCP protocol lifecycle:
- Initialization handshake with capability negotiation
- Request/response handling with timeout support
- Server-initiated message handling via `MessageHandlerInterface`

**JSON-RPC Layer** (`JsonRpc/`) - Implements JSON-RPC 2.0:
- `Message` - Base class with `fromJson()`/`toJson()` parsing
- `Request`, `Response`, `Notification` - Message types
- `Error` - Standard JSON-RPC error codes

**Transport** (`Transport/`) - Transport implementations:
- `TransportInterface` defines `connect()`, `disconnect()`, `send()`, `receive()`
- `StdioTransport` launches MCP server as subprocess, communicates via stdin/stdout

**Contracts** (`Contracts/`) - Interfaces for transport, message handling, and logging.

**Exception** (`Exception/`) - Domain-specific exceptions.

### JSON Encoding Gotcha

MCP servers expect empty objects as `{}` not `[]`. Use `stdClass` or `(object)` cast for empty arrays that must serialize as objects:
```php
'arguments' => empty($args) ? new \stdClass() : $args
```

## Namespace

`Automattic\PhpMcpClient\` maps to `src/` via PSR-4.

## Dependencies

- `wordpress/php-mcp-schema` (dev-trunk from GitHub) - MCP DTOs and type definitions
- `psr/log` - Logging interface
