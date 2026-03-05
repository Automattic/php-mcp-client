# Transports

The PHP MCP Client communicates with MCP servers through a transport layer. Two transports are included: **stdio** for local subprocess servers and **HTTP** for remote servers. You can also implement your own.

## Choosing a transport

| | Stdio | HTTP |
|---|---|---|
| **Use when** | Server runs as a local CLI tool | Server is remote or shared |
| **Connection** | Launches a subprocess | Connects to an HTTP endpoint |
| **Session** | Process lifetime | Managed via `Mcp-Session-Id` header |
| **Requirements** | `proc_open` enabled | `ext-curl` |
| **Example servers** | `npx @modelcontextprotocol/server-*` | Custom HTTP MCP servers |

## Stdio transport

Launches the MCP server as a subprocess and communicates over stdin/stdout using newline-delimited JSON-RPC messages.

### Basic usage

```php
use Automattic\PhpMcpClient\Transport\StdioTransport;

$transport = new StdioTransport('npx', ['-y', '@modelcontextprotocol/server-filesystem', '/tmp']);
```

### Constructor parameters

| Parameter | Type | Default | Description |
|---|---|---|---|
| `$command` | `string` | — | The command to run |
| `$args` | `array` | `[]` | Command arguments |
| `$env` | `?array` | `null` | Environment variables (inherits current env when null) |

### Passing environment variables

Some servers need environment variables for configuration:

```php
$transport = new StdioTransport('my-mcp-server', [], [
    'API_KEY'  => 'sk-...',
    'DATABASE' => 'production',
    'PATH'     => getenv('PATH'),
]);
```

> **Note:** When you pass `$env`, it replaces the entire environment — it does not merge with the current environment. Include `PATH` and any other variables the server needs.

### Reading stderr

The stdio transport drains stderr automatically to prevent pipe buffer deadlocks. You can read stderr output for diagnostics:

```php
$stderr = $transport->readStderr();

if ($stderr !== '') {
    echo "Server stderr: {$stderr}\n";
}
```

### Process lifecycle

- **Connect:** starts the subprocess via `proc_open()`
- **Disconnect:** sends SIGTERM, waits up to 2 seconds for graceful exit, then sends SIGKILL if still running
- **Destructor:** calls `disconnect()` as a safety net if you forget

## HTTP transport

Communicates with a remote MCP server over HTTP POST requests. Manages session state through the `Mcp-Session-Id` header.

### Basic usage

```php
use Automattic\PhpMcpClient\Transport\Http\HttpTransport;

$transport = new HttpTransport('https://mcp.example.com/api');
```

### With authentication

```php
$transport = new HttpTransport(
    'https://mcp.example.com/api',
    null,  // use default HTTP client
    null,  // use default logger
    ['Authorization' => 'Bearer your-token']
);
```

### Constructor parameters

| Parameter | Type | Default | Description |
|---|---|---|---|
| `$endpoint_url` | `string` | — | The MCP server endpoint URL (http or https) |
| `$http_client` | `?HttpClientInterface` | `null` | Custom HTTP client (defaults to `CurlHttpClient`) |
| `$logger` | `?LoggerInterface` | `null` | PSR-3 logger for transport debugging |
| `$custom_headers` | `array` | `[]` | Headers included in every request |

### Session management

The HTTP transport handles sessions automatically:

1. On the first request, no session header is sent
2. When the server responds with `Mcp-Session-Id`, the transport stores it
3. All subsequent requests include the session header
4. On `disconnect()`, the transport sends an HTTP DELETE to close the session

If the server returns 404 with an existing session ID, the transport treats it as an expired session and throws a `TransportException`.

### Custom HTTP client

The default `CurlHttpClient` works for most cases. You can configure it:

```php
use Automattic\PhpMcpClient\Transport\Http\CurlHttpClient;

$http_client = new CurlHttpClient(
    60,    // request timeout in seconds (default: 30)
    15,    // connection timeout in seconds (default: 10)
    false  // disable SSL verification (default: true)
);

$transport = new HttpTransport('https://mcp.example.com/api', $http_client);
```

> **Warning:** Only disable SSL verification in development. Always verify SSL in production.

### Using a different HTTP library

Implement `HttpClientInterface` to use Guzzle, Symfony HTTP Client, or any other library:

```php
use Automattic\PhpMcpClient\Transport\Http\HttpClientInterface;
use Automattic\PhpMcpClient\Transport\Http\HttpResponse;

class GuzzleHttpClient implements HttpClientInterface
{
    private \GuzzleHttp\Client $guzzle;

    public function __construct(\GuzzleHttp\Client $guzzle)
    {
        $this->guzzle = $guzzle;
    }

    public function post(string $url, string $body, array $headers): HttpResponse
    {
        $response = $this->guzzle->post($url, [
            'body'    => $body,
            'headers' => $headers,
        ]);

        return new HttpResponse(
            $response->getStatusCode(),
            (string) $response->getBody(),
            $this->flattenHeaders($response->getHeaders())
        );
    }

    public function delete(string $url, array $headers): void
    {
        $this->guzzle->delete($url, ['headers' => $headers]);
    }

    private function flattenHeaders(array $headers): array
    {
        $flat = [];

        foreach ($headers as $name => $values) {
            $flat[$name] = $values[0];
        }

        return $flat;
    }
}
```

Then pass it to the transport:

```php
$transport = new HttpTransport(
    'https://mcp.example.com/api',
    new GuzzleHttpClient(new \GuzzleHttp\Client())
);
```

## Custom transports

Implement `TransportInterface` to create your own transport:

```php
use Automattic\PhpMcpClient\Contracts\TransportInterface;

class MyTransport implements TransportInterface
{
    public function connect(): void { /* ... */ }
    public function disconnect(): void { /* ... */ }
    public function isConnected(): bool { /* ... */ }
    public function send(string $message): void { /* ... */ }
    public function receive(?float $timeout = null): ?string { /* ... */ }
}
```

The `receive()` method should:
- Block until a message is available or the timeout expires
- Return `null` on timeout
- Return the raw JSON-RPC message string
- Throw `TransportException` on failure

## Next steps

- [Usage guide](usage.md) — working with tools, resources, and prompts
- [Advanced usage](advanced-usage.md) — message handlers, roots, cancellation, error handling
