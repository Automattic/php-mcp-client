<?php

declare(strict_types=1);

namespace Ovidiu\McpClient\Integration\Transport;

use Ovidiu\McpClient\Core\Exception\TransportException;
use Ovidiu\McpClient\Core\Transport\AbstractTransport;

/**
 * Stdio transport implementation for MCP.
 *
 * Communicates with an MCP server subprocess via stdin/stdout.
 * Messages are newline-delimited JSON-RPC.
 *
 * @since n.e.x.t
 */
class StdioTransport extends AbstractTransport
{
    private string $command;

    /** @var array<string> */
    private array $args;

    /** @var array<string, string>|null */
    private ?array $env;

    /** @var resource|null */
    private $process = null;

    /** @var array<int, resource>|null */
    private ?array $pipes = null;

    /**
     * Create a new stdio transport.
     *
     * @param string $command The command to run the MCP server.
     * @param array<string> $args Optional command arguments.
     * @param array<string, string>|null $env Optional environment variables.
     */
    public function __construct(string $command, array $args = [], ?array $env = null)
    {
        $this->command = $command;
        $this->args    = $args;
        $this->env     = $env;
    }

    /**
     * {@inheritDoc}
     */
    public function connect(): void
    {
        if ($this->connected) {
            return;
        }

        $full_command = $this->buildCommand();

        $descriptors = [
            0 => [ 'pipe', 'r' ], // stdin
            1 => [ 'pipe', 'w' ], // stdout
            2 => [ 'pipe', 'w' ], // stderr
        ];

        $env = $this->env ?? null;

        $process = proc_open($full_command, $descriptors, $this->pipes, null, $env);

        if ($process === false) {
            throw new TransportException("Failed to start MCP server process: {$full_command}");
        }

        $this->process = $process;

        // Set stdout to non-blocking for timeout support
        stream_set_blocking($this->pipes[1], false);

        $this->connected = true;
    }

    /**
     * {@inheritDoc}
     */
    public function disconnect(): void
    {
        if (! $this->connected) {
            return;
        }

        if ($this->pipes !== null) {
            foreach ($this->pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            $this->pipes = null;
        }

        if ($this->process !== null && is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }

        $this->connected = false;
    }

    /**
     * {@inheritDoc}
     */
    public function send(string $message): void
    {
        if (! $this->connected || $this->pipes === null) {
            throw new TransportException('Transport is not connected');
        }

        // Messages must not contain embedded newlines
        $message = str_replace([ "\r\n", "\r", "\n" ], '', $message);

        $written = fwrite($this->pipes[0], $message . "\n");

        if ($written === false) {
            throw new TransportException('Failed to write to MCP server stdin');
        }

        fflush($this->pipes[0]);
    }

    /**
     * {@inheritDoc}
     */
    public function receive(?float $timeout = null): ?string
    {
        if (! $this->connected || $this->pipes === null) {
            throw new TransportException('Transport is not connected');
        }

        $stdout = $this->pipes[1];
        $buffer = '';
        $start  = microtime(true);

        while (true) {
            $read   = [ $stdout ];
            $write  = null;
            $except = null;

            // Calculate remaining timeout
            if ($timeout !== null) {
                $elapsed   = microtime(true) - $start;
                $remaining = $timeout - $elapsed;

                if ($remaining <= 0) {
                    return null; // Timeout
                }

                $tv_sec  = (int) floor($remaining);
                $tv_usec = (int) ( ( $remaining - $tv_sec ) * 1000000 );
            } else {
                $tv_sec  = null;
                $tv_usec = null;
            }

            $ready = stream_select($read, $write, $except, $tv_sec, $tv_usec);

            if ($ready === false) {
                throw new TransportException('stream_select failed');
            }

            if ($ready === 0) {
                return null; // Timeout
            }

            $chunk = fread($stdout, 8192);

            if ($chunk === false) {
                throw new TransportException('Failed to read from MCP server stdout');
            }

            if ($chunk === '') {
                // Check if process is still running
                if ($this->process !== null) {
                    $status = proc_get_status($this->process);

                    if (! $status['running']) {
                        throw new TransportException('MCP server process terminated unexpectedly');
                    }
                }

                // Small sleep to avoid busy loop
                usleep(1000);
                continue;
            }

            $buffer .= $chunk;

            // Look for complete message (newline-delimited)
            $newline_pos = strpos($buffer, "\n");

            if ($newline_pos !== false) {
                $message = substr($buffer, 0, $newline_pos);

                // Trim any carriage return
                return rtrim($message, "\r");
            }
        }
    }

    /**
     * Read any available stderr output.
     *
     * @return string The stderr output.
     */
    public function readStderr(): string
    {
        if ($this->pipes === null || ! isset($this->pipes[2])) {
            return '';
        }

        stream_set_blocking($this->pipes[2], false);

        $output = '';

        while (( $chunk = fread($this->pipes[2], 8192) ) !== false && $chunk !== '') {
            $output .= $chunk;
        }

        return $output;
    }

    /**
     * Build the full command string.
     */
    private function buildCommand(): string
    {
        $parts = [ escapeshellcmd($this->command) ];

        foreach ($this->args as $arg) {
            $parts[] = escapeshellarg($arg);
        }

        return implode(' ', $parts);
    }

    /**
     * Destructor ensures cleanup.
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
