<?php
/* =========================================================
   models.php
   Core Data Models
   DishCovery – Ingredient-Centric CBF System
   ========================================================= */

require_once __DIR__ . '/dbConfig.php';

/* ================================
   INPUT VALIDATION FUNCTIONS
   ================================ */

/**
 * Validates user ID: positive integer
 */
function validateUserId($userId) {
    return is_numeric($userId) && $userId > 0;
}

/**
 * Validates recipe ID: positive integer
 */
function validateRecipeId($recipeId) {
    return is_numeric($recipeId) && $recipeId > 0;
}

/**
 * Validates rating: 1-5
 */
function validateRating($rating) {
    return is_numeric($rating) && $rating >= 1 && $rating <= 5;
}

/* ================================
   USER MODEL
   ================================ */

/**
 * Creates a new user in the database
 * @param string $username
 * @param string $email
 * @param string $password
 * @return bool Success status
 */
function createUser($username, $email, $password) {
    global $conn;

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare(
        "INSERT INTO users (username, email, password_hash)
         VALUES (?, ?, ?)"
    );
    $stmt->bind_param("sss", $username, $email, $passwordHash);

    return $stmt->execute();
}

/**
 * Retrieves user by email
 * @param string $email
 * @return array|null User data or null if not found
 */
function getUserByEmail($email) {
    global $conn;

    $stmt = $conn->prepare(
        "SELECT * FROM users WHERE email = ?"
    );
    $stmt->bind_param("s", $email);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

/**
 * Retrieves user by ID
 * @param int $userId
 * @return array|null User data or null if not found
 */
function getUserById($userId) {
    global $conn;

    if (!validateUserId($userId)) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT * FROM users WHERE user_id = ?"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

/* ================================
   ADMIN MODEL
   ================================ */

/**
 * Ensures the admins table exists in the database
 */
function ensureAdminsTableExists() {
    global $conn;

    $conn->query(
        "CREATE TABLE IF NOT EXISTS admins (
            admin_id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )"
    );
}

/**
 * Creates a new admin in the database
 * @param string $username
 * @param string $email
 * @param string $password
 * @return bool Success status
 */
function createAdmin($username, $email, $password) {
    global $conn;

    ensureAdminsTableExists();

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare(
        "INSERT INTO admins (username, email, password_hash)
         VALUES (?, ?, ?)"
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("sss", $username, $email, $passwordHash);

    return $stmt->execute();
}

/**
 * Retrieves admin by email
 * @param string $email
 * @return array|null Admin data or null if not found
 */
function getAdminByEmail($email) {
    global $conn;

    ensureAdminsTableExists();

    $stmt = $conn->prepare(
        "SELECT * FROM admins WHERE email = ?"
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

/**
 * Retrieves admin by ID
 * @param int $adminId
 * @return array|null Admin data or null if not found
 */
function getAdminById($adminId) {
    global $conn;

    if (!validateUserId($adminId)) { // reuse for admin ID
        return null;
    }

    ensureAdminsTableExists();

    $stmt = $conn->prepare(
        "SELECT * FROM admins WHERE admin_id = ?"
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("i", $adminId);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

/* ================================
   USER PREFERENCES MODEL
   ================================ */

/**
 * Saves user preferences to the database
 * @param int $userId
 * @param string|null $diet
 * @param int|null $maxCalories
 * @param int|null $maxTime
 * @param string|null $cuisine
 * @return bool Success status
 */
function saveUserPreferences($userId, $diet, $maxCalories, $maxTime, $cuisine) {
    global $conn;

    if (!validateUserId($userId)) {
        return false;
    }

    $stmt = $conn->prepare(
        "REPLACE INTO user_preferences
         (user_id, diet_type, max_calories, max_cooking_time, cuisine_preference)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "isiis",
        $userId,
        $diet,
        $maxCalories,
        $maxTime,
        $cuisine
    );

    return $stmt->execute();
}

/**
 * Retrieves user preferences
 * @param int $userId
 * @return array|null Preferences data or null if not found
 */
function getUserPreferences($userId) {
    global $conn;

    if (!validateUserId($userId)) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT * FROM user_preferences WHERE user_id = ?"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

/* ================================
   RECIPE MODEL
   ================================ */

/**
 * Retrieves a recipe by its ID
 * @param int $recipeId
 * @return array|null Recipe data or null if not found
 */
function getRecipeById($recipeId) {
    global $conn;

    if (!validateRecipeId($recipeId)) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT * FROM recipes WHERE recipe_id = ?"
    );
    $stmt->bind_param("i", $recipeId);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

/**
 * Retrieves all recipes from the database
 * Note: This loads all recipes into memory, consider pagination for large datasets
 * @return array List of all recipes
 */
function getAllRecipes() {
    global $conn;

    $result = $conn->query("SELECT * FROM recipes");
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Searches recipes in the database by name, ingredients, cuisine, or meal type
 * @param string $query Search term
 * @param int $limit Maximum number of results (1-300)
 * @return array Matching recipes
 */
function searchRecipesInDatabase(string $query, int $limit = 100): array {
    global $conn;

    $search = trim($query);
    if ($search === '') {
        return [];
    }

    $safeLimit = max(1, min(300, $limit));
    $pattern = '%' . $search . '%';

    $stmt = $conn->prepare(
        "SELECT *
         FROM recipes
         WHERE recipe_name LIKE ?
            OR ingredient_list LIKE ?
            OR cuisine_type LIKE ?
            OR meal_type LIKE ?
         ORDER BY recipe_id DESC
         LIMIT ?"
    );
    $stmt->bind_param('ssssi', $pattern, $pattern, $pattern, $pattern, $safeLimit);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/* ================================
   BOOKMARK MODEL
   ================================ */

/**
 * Adds a bookmark for a user and recipe
 * @param int $userId
 * @param int $recipeId
 * @return bool Success status
 */
function addBookmark($userId, $recipeId) {
    global $conn;

    if (!validateUserId($userId) || !validateRecipeId($recipeId)) {
        return false;
    }

    if (isRecipeBookmarkedByUser($userId, $recipeId)) {
        return true;
    }

    $stmt = $conn->prepare(
        "INSERT INTO bookmarks (user_id, recipe_id)
         VALUES (?, ?)"
    );
    $stmt->bind_param("ii", $userId, $recipeId);

    return $stmt->execute();
}

/**
 * Removes a bookmark for a user and recipe
 * @param int $userId
 * @param int $recipeId
 * @return bool Success status
 */
function removeBookmark($userId, $recipeId) {
    global $conn;

    if (!validateUserId($userId) || !validateRecipeId($recipeId)) {
        return false;
    }

    $stmt = $conn->prepare(
        "DELETE FROM bookmarks
         WHERE user_id = ? AND recipe_id = ?"
    );
    $stmt->bind_param("ii", $userId, $recipeId);

    return $stmt->execute();
}

/**
 * Checks if a recipe is bookmarked by a user
 * @param int $userId
 * @param int $recipeId
 * @return bool True if bookmarked
 */
function isRecipeBookmarkedByUser($userId, $recipeId): bool {
    global $conn;

    if (!validateUserId($userId) || !validateRecipeId($recipeId)) {
        return false;
    }

    $stmt = $conn->prepare(
        "SELECT 1
         FROM bookmarks
         WHERE user_id = ? AND recipe_id = ?
         LIMIT 1"
    );
    $stmt->bind_param("ii", $userId, $recipeId);
    $stmt->execute();

    return (bool)$stmt->get_result()->fetch_assoc();
}

/**
 * Gets all bookmarked recipe IDs for a user
 * @param int $userId
 * @return array List of recipe IDs
 */
function getUserBookmarkRecipeIds($userId): array {
    global $conn;

    if (!validateUserId($userId)) {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT recipe_id
         FROM bookmarks
         WHERE user_id = ?"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $ids = [];
    foreach ($rows as $row) {
        $ids[] = (int)$row['recipe_id'];
    }

    return $ids;
}

/**
 * Gets all bookmarked recipes for a user
 * @param int $userId
 * @return array List of recipe data
 */
function getUserBookmarks($userId) {
    global $conn;

    if (!validateUserId($userId)) {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT r.*
         FROM bookmarks b
         JOIN recipes r ON b.recipe_id = r.recipe_id
         WHERE b.user_id = ?
         ORDER BY b.created_at DESC"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Gets all rated recipes for a user
 * @param int $userId
 * @return array List of rated recipes with rating data
 */
function getUserRatedRecipes(int $userId): array {
    global $conn;

    if (!validateUserId($userId)) {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT r.*, uf.rating, uf.created_at AS rated_at
         FROM user_feedback uf
         JOIN recipes r ON uf.recipe_id = r.recipe_id
         WHERE uf.user_id = ? AND uf.rating IS NOT NULL AND uf.rating > 0
         ORDER BY uf.created_at DESC, uf.feedback_id DESC"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/* ================================
   FEEDBACK MODEL (TAM / SUS)
   ================================ */

/**
 * Ensures unique constraint exists on user_feedback table
 */
function ensureUserFeedbackUniqueConstraintExists(): void {
    global $conn;

    $result = $conn->query(
        "SELECT 1
         FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'user_feedback'
           AND INDEX_NAME = 'uniq_user_recipe_feedback'
         LIMIT 1"
    );

    if ($result && !$result->fetch_assoc()) {
        $conn->query(
            "DELETE uf_old
             FROM user_feedback uf_old
             JOIN user_feedback uf_new
               ON uf_old.user_id = uf_new.user_id
              AND uf_old.recipe_id = uf_new.recipe_id
              AND uf_old.feedback_id < uf_new.feedback_id"
        );

        $conn->query(
            "ALTER TABLE user_feedback
             ADD UNIQUE KEY uniq_user_recipe_feedback (user_id, recipe_id)"
        );
    }
}

/**
 * Submits or updates feedback for a recipe
 * @param int $userId
 * @param int $recipeId
 * @param int $rating 1-5
 * @return bool Success status
 */
function submitFeedback($userId, $recipeId, $rating) {
    global $conn;

    if (!validateUserId($userId) || !validateRecipeId($recipeId) || !validateRating($rating)) {
        return false;
    }

    ensureUserFeedbackUniqueConstraintExists();

    $stmt = $conn->prepare(
        "INSERT INTO user_feedback
         (user_id, recipe_id, rating)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE
         rating = VALUES(rating),
         created_at = CURRENT_TIMESTAMP"
    );
    $stmt->bind_param(
        "iii",
        $userId,
        $recipeId,
        $rating
    );

    return $stmt->execute();
}

/**
 * Gets the latest rating for a user-recipe pair
 * @param int $userId
 * @param int $recipeId
 * @return int Rating value (0 if none)
 */
function getUserRecipeLatestRating(int $userId, int $recipeId): int {
    global $conn;

    if (!validateUserId($userId) || !validateRecipeId($recipeId)) {
        return 0;
    }

    $stmt = $conn->prepare(
        "SELECT rating
         FROM user_feedback
         WHERE user_id = ? AND recipe_id = ?
         ORDER BY created_at DESC, feedback_id DESC
         LIMIT 1"
    );
    $stmt->bind_param("ii", $userId, $recipeId);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['rating'] ?? 0);
}

/**
 * Gets all feedback for a recipe
 * @param int $recipeId
 * @return array List of feedback entries
 */
function getRecipeFeedback($recipeId) {
    global $conn;

    if (!validateRecipeId($recipeId)) {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT * FROM user_feedback WHERE recipe_id = ?"
    );
    $stmt->bind_param("i", $recipeId);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/* ================================
   RECOMMENDATION LOG MODEL
   ================================ */

/**
 * Logs a recommendation event
 * @param int $userId
 * @param array $inputIngredients
 * @param array $recommendedRecipes
 * @return bool Success status
 */
function logRecommendation($userId, $inputIngredients, $recommendedRecipes) {
    global $conn;

    if (!validateUserId($userId)) {
        return false;
    }

    $topRecipeIds = [];
    foreach ((array)$recommendedRecipes as $item) {
        if (is_array($item)) {
            $candidateId = (int)($item['recipe_id'] ?? 0);
            if ($candidateId <= 0 && isset($item['recipe']) && is_array($item['recipe'])) {
                $candidateId = (int)($item['recipe']['recipe_id'] ?? 0);
            }
        } else {
            $candidateId = (int)$item;
        }

        if ($candidateId > 0) {
            $topRecipeIds[] = $candidateId;
        }
    }

    $topRecipeIds = array_values(array_unique($topRecipeIds));

    $stmt = $conn->prepare(
        "INSERT INTO recommendation_logs
         (user_id, input_ingredients, recommended_recipes, top_k_count)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "issi",
        $userId,
        json_encode($inputIngredients),
        json_encode($topRecipeIds),
        count($topRecipeIds)
    );

    return $stmt->execute();
}

/**
 * Ensures top_k_count column exists in recommendation_logs
 */
function ensureRecommendationLogsTopKColumnExists(): void {
    global $conn;

    $result = $conn->query(
        "SELECT 1
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'recommendation_logs'
           AND COLUMN_NAME = 'top_k_count'
         LIMIT 1"
    );

    if ($result && !$result->fetch_assoc()) {
        $conn->query("ALTER TABLE recommendation_logs ADD COLUMN top_k_count INT NULL");
    }
}

/**
 * Ensures recommendation_results table exists
 */
function ensureRecommendationResultsTableExists(): void {
    global $conn;

    $conn->query(
        "CREATE TABLE IF NOT EXISTS recommendation_results (
            result_id INT AUTO_INCREMENT PRIMARY KEY,
            log_id INT NOT NULL,
            recipe_id INT NOT NULL,
            is_relevant TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_log_recipe (log_id, recipe_id),
            INDEX idx_rr_log (log_id),
            INDEX idx_rr_recipe (recipe_id),
            FOREIGN KEY (log_id) REFERENCES recommendation_logs(log_id),
            FOREIGN KEY (recipe_id) REFERENCES recipes(recipe_id)
        )"
    );
}

/**
 * Seeds recommendation results for a log
 * @param int $logId
 * @param array $recipeIds
 * @return bool Success status
 */
function seedRecommendationResultsForLog(int $logId, array $recipeIds): bool {
    global $conn;

    if ($logId <= 0 || empty($recipeIds)) {
        return false;
    }

    ensureRecommendationResultsTableExists();

    $stmt = $conn->prepare(
        "INSERT IGNORE INTO recommendation_results (log_id, recipe_id, is_relevant)
         VALUES (?, ?, 0)"
    );

    foreach ($recipeIds as $recipeId) {
        $safeRecipeId = (int)$recipeId;
        if ($safeRecipeId <= 0) {
            continue;
        }
        $stmt->bind_param('ii', $logId, $safeRecipeId);
        $stmt->execute();
    }

    return true;
}

function markRecommendationResultRelevant(int $logId, int $recipeId): bool {
    global $conn;

    if ($logId <= 0 || $recipeId <= 0) {
        return false;
    }

    ensureRecommendationResultsTableExists();

    $existsStmt = $conn->prepare(
        "SELECT 1
         FROM recommendation_results
         WHERE log_id = ? AND recipe_id = ?
         LIMIT 1"
    );
    $existsStmt->bind_param('ii', $logId, $recipeId);
    $existsStmt->execute();

    if (!$existsStmt->get_result()->fetch_assoc()) {
        return false;
    }

    $update = $conn->prepare(
        "UPDATE recommendation_results
         SET is_relevant = 1
         WHERE log_id = ? AND recipe_id = ?"
    );
    $update->bind_param('ii', $logId, $recipeId);
    return $update->execute();
}

/**
 * Ensures recommendation_relevance table exists
 */
function ensureRecommendationRelevanceTableExists(): void {
    global $conn;

    $conn->query(
        "CREATE TABLE IF NOT EXISTS recommendation_relevance (
            relevance_id INT AUTO_INCREMENT PRIMARY KEY,
            log_id INT NULL,
            user_id INT NOT NULL,
            recipe_id INT NOT NULL,
            interaction_type VARCHAR(30) NOT NULL DEFAULT 'click',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_relevance_user_log (user_id, log_id),
            INDEX idx_relevance_recipe (recipe_id),
            UNIQUE KEY uniq_relevance_event (log_id, user_id, recipe_id, interaction_type)
        )"
    );
}

/**
 * Gets the latest recommendation log ID for a user
 * @param int $userId
 * @return int Log ID or 0 if none
 */
function getLatestRecommendationLogIdForUser(int $userId): int {
    global $conn;

    if (!validateUserId($userId)) {
        return 0;
    }

    $stmt = $conn->prepare(
        "SELECT log_id
         FROM recommendation_logs
         WHERE user_id = ?
         ORDER BY log_id DESC
         LIMIT 1"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return (int)($row['log_id'] ?? 0);
}

/**
 * Logs recommendation relevance event
 * @param int $userId
 * @param int $recipeId
 * @param int $logId Optional log ID
 * @param string $interactionType Type of interaction
 * @return bool Success status
 */
function logRecommendationRelevance(int $userId, int $recipeId, int $logId = 0, string $interactionType = 'click'): bool {
    global $conn;

    if (!validateUserId($userId) || !validateRecipeId($recipeId)) {
        return false;
    }

    ensureRecommendationRelevanceTableExists();

    $safeType = trim($interactionType) === '' ? 'click' : trim($interactionType);
    $safeLogId = $logId > 0 ? $logId : null;

    $stmt = $conn->prepare(
        "INSERT IGNORE INTO recommendation_relevance
         (log_id, user_id, recipe_id, interaction_type)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param('iiis', $safeLogId, $userId, $recipeId, $safeType);

    return $stmt->execute();
}
