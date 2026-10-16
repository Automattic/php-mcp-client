<?php

declare(strict_types=1);

namespace Automattic\PhpMcpClient\Tests\Unit\Client;

use Automattic\PhpMcpClient\Client\ClientCapabilities;
use Automattic\PhpMcpClient\Client\McpClient;
use Automattic\PhpMcpClient\Exception\TimeoutException;
use Fiber;
use PHPUnit\Framework\TestCase;

/**
 * Concurrent requests on one client from PHP fibers.
 *
 * Each fiber waits inside the shared transport; responses arrive in any
 * order and whichever fiber reads one hands it to the request it answers.
 */
final class McpClientConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        if (! class_exists(Fiber::class)) {
            $this->markTestSkipped('Fibers require PHP 8.1.');
        }
    }

    public function test_concurrentRequests_withResponsesInReverseOrder_eachReturnsItsOwnResult(): void
    {
        [$client, $transport] = $this->createConnectedClient();

        $first  = new Fiber(static fn() => $client->callTool('first', [], 2.0));
        $second = new Fiber(static fn() => $client->callTool('second', [], 2.0));
        $first->start();
        $second->start();

        // Requests 2 and 3 are in flight; the server answers the later one first.
        $transport->deliver($this->toolResponse(3, 'second-result'));
        $transport->deliver($this->toolResponse(2, 'first-result'));

        $results = $this->drive(['first' => $first, 'second' => $second]);

        $this->assertSame('first-result', $results['first']['content'][0]['text']);
        $this->assertSame('second-result', $results['second']['content'][0]['text']);
    }

    public function test_concurrentRequests_whenOneTimesOut_otherStillReturns(): void
    {
        [$client, $transport] = $this->createConnectedClient();

        $slow = new Fiber(static function () use ($client) {
            try {
                return $client->callTool('slow', [], 0.2);
            } catch (TimeoutException $e) {
                return 'timed out';
            }
        });
        $fast = new Fiber(static fn() => $client->callTool('fast', [], 2.0));
        $slow->start();
        $fast->start();

        $transport->deliver($this->toolResponse(3, 'fast-result'));

        $results = $this->drive(['slow' => $slow, 'fast' => $fast]);

        $this->assertSame('timed out', $results['slow']);
        $this->assertSame('fast-result', $results['fast']['content'][0]['text']);
    }

    public function test_responseForFinishedRequest_isDiscarded(): void
    {
        [$client, $transport] = $this->createConnectedClient();

        $transport->deliver($this->toolResponse(99, 'stray'));
        $transport->deliver($this->toolResponse(2, 'own-result'));

        $result = $client->callTool('tool', [], 1.0);

        $this->assertSame('own-result', $result['content'][0]['text']);
    }

    /**
     * Resume suspended fibers until every one has returned.
     *
     * @param array<string, Fiber> $fibers Fibers keyed by name.
     *
     * @return array<string, mixed> Return values keyed by name.
     */
    private function drive(array $fibers): array
    {
        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline) {
            $running = 0;
            foreach ($fibers as $fiber) {
                if (! $fiber->isTerminated()) {
                    $fiber->resume();
                    $running += $fiber->isTerminated() ? 0 : 1;
                }
            }
            if ($running === 0) {
                break;
            }
            usleep(5000);
        }

        $results = [];
        foreach ($fibers as $name => $fiber) {
            $this->assertTrue($fiber->isTerminated(), "Fiber $name did not finish");
            $results[ $name ] = $fiber->getReturn();
        }

        return $results;
    }

    /**
     * @return array{0: McpClient, 1: FiberTransport}
     */
    private function createConnectedClient(): array
    {
        $transport = new FiberTransport();
        $transport->deliver((string) json_encode([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'result'  => [
                'protocolVersion' => '2025-11-25',
                'serverInfo'      => [ 'name' => 'test-server', 'version' => '1.0.0' ],
                'capabilities'    => [ 'tools' => new \stdClass() ],
            ],
        ]));

        $client = new McpClient($transport, new ClientCapabilities(), 'test-client', '1.0.0');
        $client->connect();

        return [$client, $transport];
    }

    private function toolResponse(int $id, string $text): string
    {
        return (string) json_encode([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => [ 'content' => [ [ 'type' => 'text', 'text' => $text ] ] ],
        ]);
    }
}
