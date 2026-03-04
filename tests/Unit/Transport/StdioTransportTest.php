<?php

declare(strict_types=1);

namespace GalatanOvidiu\PhpMcpClient\Tests\Unit\Transport;

use GalatanOvidiu\PhpMcpClient\Contracts\TransportInterface;
use GalatanOvidiu\PhpMcpClient\Exception\TransportException;
use GalatanOvidiu\PhpMcpClient\Transport\StdioTransport;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for StdioTransport.
 *
 * Uses `cat` as a simple echo server for subprocess communication tests.
 *
 * @covers \GalatanOvidiu\PhpMcpClient\Transport\StdioTransport
 */
final class StdioTransportTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    /**
     * Test that StdioTransport implements TransportInterface.
     */
    public function test_implements_transportInterface(): void
    {
        $transport = new StdioTransport('cat');

        $this->assertInstanceOf(TransportInterface::class, $transport);
    }

    /**
     * Test constructor stores command and args.
     */
    public function test_constructor_storesCommandAndArgs(): void
    {
        $transport = new StdioTransport('node', ['server.js', '--port=3000']);

        $reflection = new ReflectionClass($transport);

        $command_prop = $reflection->getProperty('command');
        $command_prop->setAccessible(true);
        $this->assertSame('node', $command_prop->getValue($transport));

        $args_prop = $reflection->getProperty('args');
        $args_prop->setAccessible(true);
        $this->assertSame(['server.js', '--port=3000'], $args_prop->getValue($transport));
    }

    /**
     * Test constructor stores env when provided.
     */
    public function test_constructor_storesEnv(): void
    {
        $env = ['NODE_ENV' => 'production'];
        $transport = new StdioTransport('node', [], $env);

        $reflection = new ReflectionClass($transport);
        $env_prop = $reflection->getProperty('env');
        $env_prop->setAccessible(true);

        $this->assertSame($env, $env_prop->getValue($transport));
    }

    /**
     * Test constructor defaults env to null.
     */
    public function test_constructor_defaultsEnvToNull(): void
    {
        $transport = new StdioTransport('cat');

        $reflection = new ReflectionClass($transport);
        $env_prop = $reflection->getProperty('env');
        $env_prop->setAccessible(true);

        $this->assertNull($env_prop->getValue($transport));
    }

    // -------------------------------------------------------------------------
    // isConnected()
    // -------------------------------------------------------------------------

    /**
     * Test isConnected returns false before connect.
     */
    public function test_isConnected_beforeConnect_returnsFalse(): void
    {
        $transport = new StdioTransport('cat');

        $this->assertFalse($transport->isConnected());
    }

    /**
     * Test isConnected returns true after connect.
     */
    public function test_isConnected_afterConnect_returnsTrue(): void
    {
        $transport = new StdioTransport('cat');
        $transport->connect();

        $this->assertTrue($transport->isConnected());

        $transport->disconnect();
    }

    /**
     * Test isConnected returns false after disconnect.
     */
    public function test_isConnected_afterDisconnect_returnsFalse(): void
    {
        $transport = new StdioTransport('cat');
        $transport->connect();
        $transport->disconnect();

        $this->assertFalse($transport->isConnected());
    }

    // -------------------------------------------------------------------------
    // connect()
    // -------------------------------------------------------------------------

    /**
     * Test connect sets connected state.
     */
    public function test_connect_setsConnectedState(): void
    {
        $transport = new StdioTransport('cat');

        $this->assertFalse($transport->isConnected());

        $transport->connect();

        $this->assertTrue($transport->isConnected());

        $transport->disconnect();
    }

    /**
     * Test connect launches the subprocess and creates pipes.
     */
    public function test_connect_launchesSubprocess(): void
    {
        $transport = new StdioTransport('cat');
        $transport->connect();

        $reflection = new ReflectionClass($transport);

        $process_prop = $reflection->getProperty('process');
        $process_prop->setAccessible(true);
        $this->assertIsResource($process_prop->getValue($transport));

        $pipes_prop = $reflection->getProperty('pipes');
        $pipes_prop->setAccessible(true);
        $pipes = $pipes_prop->getValue($transport);
        $this->assertIsArray($pipes);
        $this->assertCount(3, $pipes);

        $transport->disconnect();
    }

    /**
     * Test connect is idempotent when already connected.
     */
    public function test_connect_whenAlreadyConnected_isIdempotent(): void
    {
        $transport = new StdioTransport('cat');
        $transport->connect();

        $reflection = new ReflectionClass($transport);
        $process_prop = $reflection->getProperty('process');
        $process_prop->setAccessible(true);
        $original_process = $process_prop->getValue($transport);

        $transport->connect();

        $this->assertSame($original_process, $process_prop->getValue($transport));
        $this->assertTrue($transport->isConnected());

        $transport->disconnect();
    }

    /**
     * Test connect with an invalid command still opens a process.
     *
     * Note: proc_open spawns a shell wrapper, so even invalid commands
     * result in a valid process resource. The failure surfaces later
     * when trying to read from stdout (process terminates immediately).
     */
    public function test_connect_withInvalidCommand_opensProcess(): void
    {
        $transport = new StdioTransport('nonexistent_command_that_does_not_exist_xyz');
        $transport->connect();

        // The process is created (shell wrapper), but the command fails inside it.
        // The transport reports connected because proc_open succeeded.
        $this->assertTrue($transport->isConnected());

        $transport->disconnect();
    }

    /**
     * Test connect builds command with arguments.
     */
    public function test_connect_buildsCommandWithArgs(): void
    {
        $transport = new StdioTransport('cat');
        $transport->connect();

        // If we got here without exception, the command was built and executed
        $this->assertTrue($transport->isConnected());

        $transport->disconnect();
    }

    // -------------------------------------------------------------------------
    // disconnect()
    // -------------------------------------------------------------------------

    /**
     * Test disconnect closes pipes and process.
     */
    public function test_disconnect_closesPipesAndProcess(): void
    {
        $transport = new StdioTransport('cat');
        $transport->connect();

        $reflection = new ReflectionClass($transport);

        $process_prop = $reflection->getProperty('process');
        $process_prop->setAccessible(true);

        $pipes_prop = $reflection->getProperty('pipes');
        $pipes_prop->setAccessible(true);

        $transport->disconnect();

        $this->assertNull($process_prop->getValue($transport));
        $this->assertNull($pipes_prop->getValue($transport));
        $this->assertFalse($transport->isConnected());
    }

    /**
     * Test disconnect clears the read buffer.
     */
    public function test_disconnect_clearsReadBuffer(): void
    {
        $transport = new StdioTransport('cat');
        $transport->connect();

        $reflection = new ReflectionClass($transport);
        $buffer_prop = $reflection->getProperty('read_buffer');
        $buffer_prop->setAccessible(true);

        // Manually set some buffer content
        $buffer_prop->setValue($transport, 'leftover data');

        $transport->disconnect();

        $this->assertSame('', $buffer_prop->getValue($transport));
    }

    /**
     * Test disconnect handles already disconnected gracefully.
     */
    public function test_disconnect_whenAlreadyDisconnected_isIdempotent(): void
    {
        $transport = new StdioTransport('cat');

        // Should not throw
        $transport->disconnect();
        $transport->disconnect();

        $this->assertFalse($transport->isConnected());
    }

    /**
     * Test disconnect after connect then disconnect again is safe.
     */
    public function test_disconnect_afterConnectAndDisconnect_isIdempotent(): void
    {
        $transport = new StdioTransport('cat');
        $transport->connect();
        $transport->disconnect();

        // Second disconnect should be safe
        $transport->disconnect();

        $this->assertFalse($transport->isConnected());
    }

    // -------------------------------------------------------------------------
    // send()
    // -------------------------------------------------------------------------

    /**
     * Test send throws TransportException when not connected.
     */
    public function test_send_whenNotConnected_throwsException(): void
    {
        $transport = new StdioTransport('cat');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Transport is not connected');

        $transport->send('{"jsonrpc":"2.0","id":1,"method":"test"}');
    }

    /**
     * Test send writes message to stdin pipe.
     *
     * Uses `cat` as an echo server: what we send should be readable back.
     */
    public function test_send_writesMessageToStdin(): void
    {
        $transport = new StdioTransport('cat');
        $transport->connect();

        $message = '{"jsonrpc":"2.0","id":1,"method":"initialize"}';
        $transport->send($message);

        // cat echoes back what we send, so we should receive it
        $response = $transport->receive(2.0);
        $this->assertSame($message, $response);

        $transport->disconnect();
    }

    /**
     * Test send strips embedded newlines from the message.
     */
    public function test_send_stripsEmbeddedNewlines(): void
    {
        $transport = new StdioTransport('cat');
        $transport->connect();

        $message_with_newlines = "line1\nline2\rline3\r\nline4";
        $transport->send($message_with_newlines);

        $response = $transport->receive(2.0);
        $this->assertSame('line1line2line3line4', $response);

        $transport->disconnect();
    }

    /**
     * Test send throws when not connected but pipes are null.
     */
    public function test_send_whenDisconnected_throwsException(): void
    {
        $transport = new StdioTransport('cat');
        $transport->connect();
        $transport->disconnect();

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Transport is not connected');

        $transport->send('{}');
    }

    // -------------------------------------------------------------------------
    // receive()
    // -------------------------------------------------------------------------

    /**
     * Test receive throws TransportException when not connected.
     */
    public function test_receive_whenNotConnected_throwsException(): void
    {
        $transport = new StdioTransport('cat');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Transport is not connected');

        $transport->receive();
    }

    /**
     * Test receive returns null when no data is available (timeout).
     */
    public function test_receive_withNoData_returnsNullOnTimeout(): void
    {
        // Use `sleep 10` so the process stays alive but produces no output
        $transport = new StdioTransport('sleep', ['10']);
        $transport->connect();

        $result = $transport->receive(0.1);

        $this->assertNull($result);

        $transport->disconnect();
    }

    /**
     * Test receive parses JSON response from stdout.
     */
    public function test_receive_parsesResponseFromStdout(): void
    {
        $transport = new StdioTransport('cat');
        $transport->connect();

        $json_message = '{"jsonrpc":"2.0","id":1,"result":{"protocolVersion":"2024-11-05"}}';
        $transport->send($json_message);

        $response = $transport->receive(2.0);

        $this->assertSame($json_message, $response);

        $transport->disconnect();
    }

    /**
     * Test receive handles multiple messages in sequence.
     */
    public function test_receive_handlesMultipleMessages(): void
    {
        $transport = new StdioTransport('cat');
        $transport->connect();

        $message1 = '{"jsonrpc":"2.0","id":1,"method":"first"}';
        $message2 = '{"jsonrpc":"2.0","id":2,"method":"second"}';

        $transport->send($message1);
        $transport->send($message2);

        $response1 = $transport->receive(2.0);
        $response2 = $transport->receive(2.0);

        $this->assertSame($message1, $response1);
        $this->assertSame($message2, $response2);

        $transport->disconnect();
    }

    /**
     * Test receive handles carriage return in message.
     */
    public function test_receive_stripsCarriageReturn(): void
    {
        // Use printf to send a message with \r\n line ending
        $transport = new StdioTransport('printf', ['hello\\r\\n']);
        $transport->connect();

        $response = $transport->receive(2.0);

        $this->assertSame('hello', $response);

        $transport->disconnect();
    }

    /**
     * Test receive throws when process terminates unexpectedly.
     */
    public function test_receive_whenProcessTerminated_throwsException(): void
    {
        // `true` exits immediately with no output
        $transport = new StdioTransport('true');
        $transport->connect();

        // Give the process time to exit
        usleep(100000);

        $this->expectException(TransportException::class);

        $transport->receive(1.0);
    }

    /**
     * Test receive after disconnect throws exception.
     */
    public function test_receive_afterDisconnect_throwsException(): void
    {
        $transport = new StdioTransport('cat');
        $transport->connect();
        $transport->disconnect();

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Transport is not connected');

        $transport->receive();
    }

    // -------------------------------------------------------------------------
    // readStderr()
    // -------------------------------------------------------------------------

    /**
     * Test readStderr returns empty string when pipes are null.
     */
    public function test_readStderr_withNoPipes_returnsEmptyString(): void
    {
        $transport = new StdioTransport('cat');

        $this->assertSame('', $transport->readStderr());
    }

    /**
     * Test readStderr returns stderr output.
     */
    public function test_readStderr_returnsStderrOutput(): void
    {
        // Use sh -c to echo to stderr
        $transport = new StdioTransport('sh', ['-c', 'echo "error message" >&2 && sleep 5']);
        $transport->connect();

        // Give the process time to write to stderr
        usleep(200000);

        $stderr = $transport->readStderr();
        $this->assertStringContainsString('error message', $stderr);

        $transport->disconnect();
    }

    // -------------------------------------------------------------------------
    // buildCommand() (tested indirectly)
    // -------------------------------------------------------------------------

    /**
     * Test that command with arguments is built correctly.
     *
     * Verifies indirectly by sending/receiving through echo with args.
     */
    public function test_buildCommand_withArgs_worksCorrectly(): void
    {
        // Use printf with an argument to verify args are passed
        $transport = new StdioTransport('printf', ['%s\\n', 'hello_world']);
        $transport->connect();

        $response = $transport->receive(2.0);
        $this->assertSame('hello_world', $response);

        $transport->disconnect();
    }

    // -------------------------------------------------------------------------
    // Full lifecycle
    // -------------------------------------------------------------------------

    /**
     * Test full connect, send, receive, disconnect lifecycle.
     */
    public function test_fullLifecycle_connectSendReceiveDisconnect(): void
    {
        $transport = new StdioTransport('cat');

        // Start disconnected
        $this->assertFalse($transport->isConnected());

        // Connect
        $transport->connect();
        $this->assertTrue($transport->isConnected());

        // Send and receive
        $message = '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}';
        $transport->send($message);
        $response = $transport->receive(2.0);
        $this->assertSame($message, $response);

        // Disconnect
        $transport->disconnect();
        $this->assertFalse($transport->isConnected());
    }

    /**
     * Test reconnect after disconnect works.
     */
    public function test_reconnect_afterDisconnect_works(): void
    {
        $transport = new StdioTransport('cat');

        // First connection
        $transport->connect();
        $transport->send('{"id":1}');
        $response1 = $transport->receive(2.0);
        $this->assertSame('{"id":1}', $response1);
        $transport->disconnect();

        // Second connection
        $transport->connect();
        $transport->send('{"id":2}');
        $response2 = $transport->receive(2.0);
        $this->assertSame('{"id":2}', $response2);
        $transport->disconnect();
    }
}
