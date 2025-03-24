<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

$container->set('authMiddleware', function () use ($container) {
    return function (Request $request, RequestHandler $handler) use ($container) {
        $token = $request->getHeaderLine('Authorization');
        $db = $container->get('db');

        if (!$token) {
            return jsonResponse(new Response(), ['error' => 'Unauthorized'], 401);
        }

        $stmt = $db->prepare("SELECT * FROM users WHERE token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return jsonResponse(new Response(), ['error' => 'Invalid token'], 401);
        }

        return $handler->handle($request->withAttribute('user', $user));
    };
});

function jsonResponse($response, $data, $status = 200) {
    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
}