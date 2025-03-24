<?php
use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Psr7\Factory\ServerRequestFactory;

require_once __DIR__ . '/../vendor/autoload.php';

class GroupTest extends TestCase
{
    private $app;
    private $token;

    protected function setUp(): void
    {
        $container = new Container();
        AppFactory::setContainer($container);
        $app = AppFactory::create();

        require __DIR__ . '/../src/Database.php';
        require __DIR__ . '/../src/Middleware.php';
        require __DIR__ . '/../src/Routes.php';

        setupRoutes($app, $container);
        $this->app = $app;

        // Setup DB for test
        $db = $container->get('db');
        $db->exec("DELETE FROM chat_groups");
        $db->exec("DELETE FROM users");

        $token = 'test-token';
        $stmt = $db->prepare("INSERT INTO users (username, token) VALUES (?, ?)");
        $stmt->execute(['testuser', $token]);

        $this->token = $token;
    }

    public function testGroupCanBeCreated()
    {
        $request = (new ServerRequestFactory)->createServerRequest('POST', '/api/groups');
        $request->getBody()->write(json_encode(['name' => 'Test Group']));
        $request = $request
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Authorization', $this->token);

        $response = $this->app->handle($request);

        $this->assertEquals(201, $response->getStatusCode());

        $response->getBody()->rewind();
        $body = json_decode($response->getBody()->getContents(), true);

        $this->assertEquals('Chat group created', $body['message']);

    }
}
