<?php
// Configuration
define('SECRET_KEY', 'votre_cle_secrete_ici123!');
define('TOKEN_EXPIRATION', 3600); // 1 heure en secondes

// Fonctions d'authentification
function generateToken($userId, $email) {
    $tokenData = [
        'user_id' => $userId,
        'email' => $email,
        'exp' => time() + TOKEN_EXPIRATION  // Changé 'expires' en 'exp'
    ];
    return base64_encode(json_encode($tokenData)) . '.' . 
           hash_hmac('sha256', json_encode($tokenData), SECRET_KEY);
}

function validateToken($token) {
    $parts = explode('.', $token);
    if (count($parts) != 2) return false;
    
    $data = json_decode(base64_decode($parts[0]), true);
    if (!$data || !isset($data['exp'])) return false;  // Vérifiez 'exp' au lieu de 'expires'
    
    $expectedSig = hash_hmac('sha256', json_encode($data), SECRET_KEY);
    if (!hash_equals($parts[1], $expectedSig)) return false;  // Utilisation de hash_equals pour la sécurité
    
    if (time() > $data['exp']) return false;  // Vérifiez 'exp' au lieu de 'expires'
    
    return $data;
}

function checkAuth() {
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Authorization header missing']);
        exit();
    }

    $authHeader = $headers['Authorization'];
    if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['error' => 'Token not found in header']);
        exit();
    }

    $token = $matches[1];
    $payload = validateToken($token);
    
    if (!$payload) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or expired token']);
        exit();
    }
    
    return $payload;
}

// Fonctions modifiées pour utiliser l'authentification

function getAllShows($pdo) {
    checkAuth();
    
    try {
        $stmt = $pdo->query("SELECT * FROM shows");
        $shows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($shows);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getShow($pdo, $id) {
    checkAuth();
    
    if (!is_numeric($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'ID must be an integer']);
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM shows WHERE id = ?");
        $stmt->execute([$id]);
        $show = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$show) {
            http_response_code(404);
            echo json_encode(['error' => 'Show not found']);
        } else {
            echo json_encode($show);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function createShow($pdo) {
    $user = checkAuth();
    
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $errors = [];
    $allowedCategories = ['movie', 'anime', 'serie'];

    if (empty($data['title'])) $errors[] = 'Title is required';
    if (empty($data['description'])) $errors[] = 'Description is required';
    if (!in_array($data['category'] ?? '', $allowedCategories)) $errors[] = 'Category must be movie, anime, or serie';
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['errors' => $errors]);
        return;
    }
    
    $imagePath = handleImageUpload();
    
    try {
        $stmt = $pdo->prepare("INSERT INTO shows (title, description, category, image, user_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['title'],
            $data['description'],
            $data['category'],
            $imagePath,
            $user['user_id']
        ]);
        
        $id = $pdo->lastInsertId();
        http_response_code(201);
        echo json_encode([
            'id' => $id,
            'title' => $data['title'],
            'description' => $data['description'],
            'category' => $data['category'],
            'image' => $imagePath
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function updateShow($pdo, $id) {
    $user = checkAuth();
    
    if (!is_numeric($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'ID must be an integer']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $errors = [];
    $allowedCategories = ['movie', 'anime', 'serie'];

    if (empty($data['title'])) $errors[] = 'Title is required';
    if (empty($data['description'])) $errors[] = 'Description is required';
    if (!in_array($data['category'] ?? '', $allowedCategories)) $errors[] = 'Category must be movie, anime, or serie';
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['errors' => $errors]);
        return;
    }
    
    $imagePath = handleImageUpload();
    
    try {
        // Vérifier que le show appartient à l'utilisateur
        $stmt = $pdo->prepare("SELECT user_id FROM shows WHERE id = ?");
        $stmt->execute([$id]);
        $show = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$show) {
            http_response_code(404);
            echo json_encode(['error' => 'Show not found']);
            return;
        }
        
        if ($show['user_id'] != $user['user_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'You can only update your own shows']);
            return;
        }

        if ($imagePath) {
            $sql = "UPDATE shows SET title = ?, description = ?, category = ?, image = ? WHERE id = ?";
            $params = [$data['title'], $data['description'], $data['category'], $imagePath, $id];
        } else {
            $sql = "UPDATE shows SET title = ?, description = ?, category = ? WHERE id = ?";
            $params = [$data['title'], $data['description'], $data['category'], $id];
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode([
            'id' => $id,
            'title' => $data['title'],
            'description' => $data['description'],
            'category' => $data['category'],
            'image' => $imagePath ?? $data['current_image']
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function deleteShow($pdo, $id) {
    $user = checkAuth();
    
    if (!is_numeric($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'ID must be an integer']);
        return;
    }

    try {
        // Vérifier que le show appartient à l'utilisateur
        $stmt = $pdo->prepare("SELECT user_id FROM shows WHERE id = ?");
        $stmt->execute([$id]);
        $show = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$show) {
            http_response_code(404);
            echo json_encode(['error' => 'Show not found']);
            return;
        }
        
        if ($show['user_id'] != $user['user_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'You can only delete your own shows']);
            return;
        }

        $stmt = $pdo->prepare("DELETE FROM shows WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['message' => 'Show deleted successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function loginUser($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validation
    if (empty($input['email']) || empty($input['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, email, password FROM users WHERE email = ?");
        $stmt->execute([$input['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($input['password'], $user['password'])) {
            $token = generateToken($user['id'], $user['email']);
            
            // Réponse réussie
            echo json_encode([
                'success' => true,
                'token' => $token,
                'expires_in' => TOKEN_EXPIRATION,
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid email or password']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>