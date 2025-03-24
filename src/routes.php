<?php
use Slim\Routing\RouteCollectorProxy;

/**
 * Register all API routes for the chat application.
 *
 * @param \Slim\App $app
 * @param \DI\Container $container
 */
function setupRoutes($app, $container) {
    if (!$app) {
        throw new Exception("App instance is not defined in routes.php");
    }

    $app->group('/api', function (RouteCollectorProxy $group) use ($container) {

        // Create a Chat Group (Prevents Duplicate Names)
        $group->post('/groups', function ($request, $response) use ($container) {
            $data = json_decode($request->getBody(), true);
            $db = $container->get('db');

            // Check if group already exists
            $stmt = $db->prepare("SELECT id FROM chat_groups WHERE name = ?");
            $stmt->execute([$data['name']]);
            if ($stmt->fetch()) {
                return jsonResponse($response, ['error' => 'Group name already exists'], 400);
            }

            // Insert new group if it does not exist
            $stmt = $db->prepare("INSERT INTO chat_groups (name) VALUES (?)");
            $stmt->execute([$data['name']]);

            return jsonResponse($response, ['message' => 'Chat group created'], 201);
        });

        // Join a Chat Group
        $group->post('/groups/{group_id}/join', function ($request, $response, $args) use ($container) {
            $user = $request->getAttribute('user'); // Get the authenticated user
            $db = $container->get('db');

            // Check if the user is already in the group
            $stmt = $db->prepare("SELECT id FROM group_members WHERE user_id = ? AND group_id = ?");
            $stmt->execute([$user['id'], $args['group_id']]);
            if ($stmt->fetch()) {
                return jsonResponse($response, ['error' => 'User already in this group'], 400);
            }

            // Insert user into group_members table
            $stmt = $db->prepare("INSERT INTO group_members (user_id, group_id) VALUES (?, ?)");
            $stmt->execute([$user['id'], $args['group_id']]);

            return jsonResponse($response, ['message' => 'Joined group successfully']);
        })->add($container->get('authMiddleware')); // Requires authentication

        // Send a Message in a Group
        $group->post('/groups/{group_id}/messages', function ($request, $response, $args) use ($container) {
            $data = json_decode($request->getBody(), true);
            $user = $request->getAttribute('user'); // Get the authenticated user
            $db = $container->get('db');

            // Check if user is a member of the group before sending a message
            $stmt = $db->prepare("SELECT id FROM group_members WHERE user_id = ? AND group_id = ?");
            $stmt->execute([$user['id'], $args['group_id']]);
            if (!$stmt->fetch()) {
                return jsonResponse($response, ['error' => 'You are not a member of this group'], 403);
            }

            // Insert the message into the database
            $stmt = $db->prepare("INSERT INTO messages (group_id, user_id, message) VALUES (?, ?, ?)");
            $stmt->execute([$args['group_id'], $user['id'], $data['message']]);

            return jsonResponse($response, ['message' => 'Message sent'], 201);
        })->add($container->get('authMiddleware')); // Requires authentication

        // Get All Messages in a Group
        $group->get('/groups/{group_id}/messages', function ($request, $response, $args) use ($container) {
            $db = $container->get('db');

            $stmt = $db->prepare("SELECT messages.message, users.username, messages.created_at FROM messages 
                JOIN users ON messages.user_id = users.id WHERE group_id = ? ORDER BY created_at ASC");
            $stmt->execute([$args['group_id']]);

            return jsonResponse($response, $stmt->fetchAll(PDO::FETCH_ASSOC));
        });

    });
}