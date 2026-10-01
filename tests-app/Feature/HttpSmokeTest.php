<?php

namespace AppTests\Feature;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

/**
 * Boots the actual app via PHP's built-in server as a real subprocess and
 * hits it over HTTP with Guzzle — the same thing the CI workflow's curl
 * smoke test does, but runnable locally with `composer test` and easy to
 * extend with real assertions (session cookies, JSON bodies, etc.) as you
 * add routes.
 */
final class HttpSmokeTest extends TestCase
{
    private static $process;
    private static $pipes;
    private static string $baseUri = 'http://127.0.0.1:8917';

    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__, 2);

        self::$process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:8917', '-t', $root, $root.'/index.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            self::$pipes,
            $root
        );

        if (self::$process === false) {
            self::fail('Could not start the built-in PHP server for the feature test.');
        }

        self::waitForServer();
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$process)) {
            proc_terminate(self::$process);
            proc_close(self::$process);
        }
    }

    private static function waitForServer(): void
    {
        $client = new Client(['base_uri' => self::$baseUri, 'timeout' => 1, 'http_errors' => false]);

        for ($i = 0; $i < 20; $i++) {
            try {
                $client->get('/');

                return;
            } catch (\Throwable $e) {
                usleep(150000);
            }
        }

        self::fail('The built-in PHP server never became ready.');
    }

    public function testHomepageRendersWithoutError(): void
    {
        $client = new Client(['base_uri' => self::$baseUri, 'http_errors' => false]);

        $response = $client->get('/');

        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringNotContainsString('PHP Error was encountered', $body);
    }

    public function testExampleModuleRendersWithoutError(): void
    {
        $client = new Client(['base_uri' => self::$baseUri, 'http_errors' => false]);

        $response = $client->get('/example');

        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringNotContainsString('PHP Error was encountered', $body);
        $this->assertStringContainsString('rendered by Modules::run', $body);
    }

    public function testUnknownRouteRendersCustom404Page(): void
    {
        $client = new Client(['base_uri' => self::$baseUri, 'http_errors' => false]);

        $response = $client->get('/this-page-does-not-exist');

        $this->assertSame(404, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('<title>404 | Not Found</title>', $body);
        $this->assertStringNotContainsString('PHP Error was encountered', $body);
    }

    public function testUnknownRouteReturnsJsonErrorForApiRequests(): void
    {
        $client = new Client(['base_uri' => self::$baseUri, 'http_errors' => false]);

        $response = $client->get('/this-page-does-not-exist', ['headers' => ['Accept' => 'application/json']]);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(
            ['status' => 404, 'error' => 'Not Found', 'message' => 'The page you requested was not found.'],
            json_decode((string) $response->getBody(), true)
        );
    }
}
