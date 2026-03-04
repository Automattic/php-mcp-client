<?php

declare(strict_types=1);

namespace GalatanOvidiu\PhpMcpClient\Contracts;

use Psr\Log\LoggerInterface as PsrLoggerInterface;

/**
 * Logger interface extending PSR-3 for MCP client logging.
 *
 * Provides additional context-aware logging capabilities specific to MCP operations.
 *
 * @since n.e.x.t
 */
interface LoggerInterface extends PsrLoggerInterface
{
}
