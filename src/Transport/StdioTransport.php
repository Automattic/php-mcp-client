<?php

declare(strict_types=1);

namespace Automattic\PhpMcpClient\Transport;

use Automattic\PhpMcpClient\Exception\TransportException;

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
     * Read buffer for incomplete or multi-message chunks.
     */
    private string $read_buffer = '';

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

        // Set stdout and stderr to non-blocking for timeout support
        // and to prevent deadlocks when the server writes to stderr.
        stream_set_blocking($this->pipes[1], false);
        stream_set_blocking($this->pipes[2], false);

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
            proc_terminate($this->process, 15); // SIGTERM

            // Wait up to 2 seconds for graceful exit before escalating to SIGKILL
            $deadline = microtime(true) + 2.0;
            $running  = true;

            while (microtime(true) < $deadline) {
                $status = proc_get_status($this->process);

                if (!$status['running']) {
                    $running = false;
                    break;
                }

                usleep(50000); // 50ms
            }

            if ($running) {
                proc_terminate($this->process, 9); // SIGKILL
            }

            proc_close($this->process);
            $this->process = null;
        }

        $this->read_buffer = '';
        $this->connected   = false;
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
        $start  = microtime(true);

        // Check if a complete message already exists in the buffer
        $newline_pos = strpos($this->read_buffer, "\n");

        if ($newline_pos !== false) {
            return $this->extractMessage($newline_pos);
        }

        $stderr = $this->pipes[2];

        while (true) {
            $read   = [ $stdout, $stderr ];
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

            // Drain stderr to prevent pipe buffer deadlocks (only if stderr is ready)
            if (in_array($stderr, $read, true)) {
                $this->drainStderr($stderr);
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

            $this->read_buffer .= $chunk;

            // Look for complete message (newline-delimited)
            $newline_pos = strpos($this->read_buffer, "\n");

            if ($newline_pos !== false) {
                return $this->extractMessage($newline_pos);
            }
        }
    }

    /**
     * Extract the first complete message from the read buffer.
     *
     * @param int $newline_pos Position of the newline delimiter in the buffer.
     *
     * @return string The extracted message.
     */
    private function extractMessage(int $newline_pos): string
    {
        $message           = substr($this->read_buffer, 0, $newline_pos);
        $this->read_buffer = substr($this->read_buffer, $newline_pos + 1);

        return rtrim($message, "\r");
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

        return $this->consumeStream($this->pipes[2]);
    }

    /**
     * Drain stderr pipe to prevent buffer-full deadlocks.
     *
     * Reads and discards any available stderr data so the server process
     * does not block when writing to stderr.
     *
     * @param resource $stderr The stderr pipe resource.
     */
    private function drainStderr($stderr): void
    {
        $this->consumeStream($stderr);
    }

    /**
     * Read all available data from a non-blocking stream.
     *
     * @param resource $stream The stream resource to read from.
     *
     * @return string The data read from the stream.
     */
    private function consumeStream($stream): string
    {
        $output = '';

        while (( $chunk = fread($stream, 8192) ) !== false && $chunk !== '') {
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
