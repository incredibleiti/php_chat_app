<?php

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use Slim\Psr7\Factory\ServerRequestFactory;

class AuthMiddlewareTest extends TestCase
{
    private $container;
    private $dbMock;

    protected function setUp(): void
    {
        $this->dbMock = $this->createMock(PDO::class);
        $this->container = new \DI\Container();
        $this->container->set('db', $this->dbMock);

        // Register authMiddleware in THIS test's container
        $this->container->set('authMiddleware', function () {
            $db = $this->container->get('db');
            return function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($db) {
                $token = $request->getHeaderLine('Authorization');

                if (!$token) {
                    $response = new Response();
                    return jsonResponse($response, ['error' => 'Unauthorized'], 401);
                }

                $stmt = $db->prepare("SELECT * FROM users WHERE token = ?");
                $stmt->execute([$token]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    $response = new Response();
                    return jsonResponse($response, ['error' => 'Invalid token'], 401);
                }

                return $handler->handle($request->withAttribute('user', $user));
            };
        });
    }

    public function testNoTokenReturnsUnauthorized()
    {
        $middleware = $this->container->get('authMiddleware');

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = $middleware($request, $handler);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('Unauthorized', (string) $response->getBody());
    }

    public function testInvalidTokenReturnsInvalidTokenError()
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetch')->willReturn(false);

        $this->dbMock->method('prepare')->willReturn($stmtMock);

        $middleware = $this->container->get('authMiddleware');

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/')
            ->withHeader('Authorization', 'invalid-token');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = $middleware($request, $handler);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('Invalid token', (string) $response->getBody());
    }

    public function testValidTokenPassesToNextMiddleware()
    {
        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->method('execute')->willReturn(true);
        $stmtMock->method('fetch')->willReturn(['id' => 1, 'name' => 'User']);

        $this->dbMock->method('prepare')->willReturn($stmtMock);

        $middleware = $this->container->get('authMiddleware');

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/')
            ->withHeader('Authorization', 'valid-token');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
                ->method('handle')
                ->with($this->callback(function ($req) {
                    return $req->getAttribute('user')['id'] === 1;
                }))
                ->willReturn(new Response());

        $response = $middleware($request, $handler);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
