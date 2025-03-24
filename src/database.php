<?php
use PDO;

if (!isset($container)) {
    throw new Exception("Container is not defined. Make sure index.php includes database.php after creating the container.");
}

// Set up database connection inside the container
// assignment is for sqlite so using the same
$container->set('db', function () {
    $pdo = new PDO('sqlite:' . __DIR__ . '/../chat.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
});

if (!function_exists('setupDatabase')) {
    function setupDatabase($db) {
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            token TEXT UNIQUE NOT NULL
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS chat_groups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS group_members (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            group_id INTEGER NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id),
            FOREIGN KEY(group_id) REFERENCES chat_groups(id)
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            group_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            message TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(group_id) REFERENCES chat_groups(id),
            FOREIGN KEY(user_id) REFERENCES users(id)
        )");
    }
}

// Run database setup
setupDatabase($container->get('db'));

// Insert a default test user (if not already exists) === This is helpful if you follow the documentation and use this to test ======
$db = $container->get('db');
$stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
$stmt->execute(['testuser']);

if ($stmt->fetchColumn() == 0) {
    $stmt = $db->prepare("INSERT INTO users (username, token) VALUES (?, ?)");
    $stmt->execute(['testuser', 'token123']);
}
