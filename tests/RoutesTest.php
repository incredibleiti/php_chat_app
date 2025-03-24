<?php

use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Psr7\Factory\ServerRequestFactory;

require_once __DIR__ . '/../vendor/autoload.php';

class RoutesTest extends TestCase
{
    private $app;
    private $container;

    protected function setUp(): void
{
    $this->container = new \DI\Container();
    AppFactory::setContainer($this->container);
    $app = AppFactory::create();

    // ✅ Set global container BEFORE requiring database.php
    global $container;
    $container = $this->container;
    require __DIR__ . '/../src/Database.php';

    // ✅ Register inline authMiddleware AFTER DB is available
    $this->container->set('authMiddleware', function () {
        $db = $this->container->get('db');
        return function ($request, $handler) use ($db) {
            $token = $request->getHeaderLine('Authorization');

            if (!$token) {
                $response = new \Slim\Psr7\Response();
                $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }

            $stmt = $db->prepare("SELECT * FROM users WHERE token = ?");
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $response = new \Slim\Psr7\Response();
                $response->getBody()->write(json_encode(['error' => 'Invalid token']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }

            return $handler->handle($request->withAttribute('user', $user));
        };
    });

    // ✅ Register routes
    require_once __DIR__ . '/../src/Routes.php';
    setupRoutes($app, $this->container);

    $this->app = $app;

    // ✅ Cleanup test database
    $db = $this->container->get('db');
    $db->exec("DELETE FROM users");
    $db->exec("DELETE FROM chat_groups");
    $db->exec("DELETE FROM group_members");
    $db->exec("DELETE FROM messages");
}

    public function testFullFlow()
    {
        $db = $this->container->get('db');

        // 1. Insert user manually
        $username = 'testuser';
        $token = 'testtoken123';
        $stmt = $db->prepare("INSERT INTO users (username, token) VALUES (?, ?)");
        $stmt->execute([$username, $token]);
        $userId = $db->lastInsertId();

        // 2. Create a chat group
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/groups');
        $request->getBody()->write(json_encode(['name' => 'Dev Group']));
        $request = $request->withHeader('Content-Type', 'application/json');
        $response = $this->app->handle($request);
        $this->assertEquals(201, $response->getStatusCode());

        // 3. Get group ID
        $stmt = $db->prepare("SELECT id FROM chat_groups WHERE name = ?");
        $stmt->execute(['Dev Group']);
        $groupId = $stmt->fetchColumn();

        // 4. Join the group
        $request = (new ServerRequestFactory())->createServerRequest('POST', "/api/groups/{$groupId}/join")
            ->withHeader('Authorization', $token);
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        // 5. Send a message
        $request = (new ServerRequestFactory())->createServerRequest('POST', "/api/groups/{$groupId}/messages");
        $request->getBody()->write(json_encode(['message' => 'Hello, group!']));
        $request = $request
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', $token);
        $response = $this->app->handle($request);
        $this->assertEquals(201, $response->getStatusCode());

        // 6. Get messages
        $request = (new ServerRequestFactory())->createServerRequest('GET', "/api/groups/{$groupId}/messages");
        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $response->getBody()->rewind();
        $messages = json_decode($response->getBody()->getContents(), true);
        $this->assertCount(1, $messages);
        $this->assertEquals('Hello, group!', $messages[0]['message']);
        $this->assertEquals($username, $messages[0]['username']);
    }
}
