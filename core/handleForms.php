<?php
/* =========================================================
   handleForms.php
   Form Processing Controller
   DishCovery – Ingredient-Centric CBF System
   ========================================================= */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ===== DEBUG LOG =====
$debugLog = __DIR__ . '/../debug_requests.log';
function debugLog($message) {
    global $debugLog;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] " . $message . "\n";
    file_put_contents($debugLog, $line, FILE_APPEND);
    error_log($line);
}

debugLog("=== PAGE REQUEST ===");
debugLog("Method: " . $_SERVER['REQUEST_METHOD']);
debugLog("URI: " . $_SERVER['REQUEST_URI']);
debugLog("POST keys: " . json_encode(array_keys($_POST)));
debugLog("GET keys: " . json_encode(array_keys($_GET)));

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../algo/recommend.php';
require_once __DIR__ . '/../algo/filterProcessor.php';

/* =========================================================
   INPUT VALIDATION FUNCTIONS
   ========================================================= */

/**
 * Validates username: not empty, alphanumeric + spaces, length 3-50
 */
function validateUsername($username) {
    $username = trim($username);
    if (empty($username)) return false;
    if (strlen($username) < 3 || strlen($username) > 50) return false;
    if (!preg_match('/^[a-zA-Z0-9\s]+$/', $username)) return false;
    return true;
}

/**
 * Validates email format
 */
function validateEmail($email) {
    $email = trim($email);
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validates password: at least 8 chars, uppercase, number
 */
function validatePassword($password) {
    if (strlen($password) < 8) return false;
    if (!preg_match('/[A-Z]/', $password)) return false;
    if (!preg_match('/\d/', $password)) return false;
    return true;
}

/**
 * Validates recipe ID: positive integer
 */
if (!function_exists('validateRecipeId')) {
    function validateRecipeId($recipeId) {
        return is_numeric($recipeId) && $recipeId > 0;
    }
}

/**
 * Validates rating: 1-5
 */
if (!function_exists('validateRating')) {
    function validateRating($rating) {
        return is_numeric($rating) && $rating >= 1 && $rating <= 5;
    }
}

/* =========================================================
   BUSINESS LOGIC FUNCTIONS
   ========================================================= */

/**
 * Handles user registration logic
 * @param string $username
 * @param string $email
 * @param string $password
 * @param bool $agreedToTerms
 * @return array ['success' => true] or ['error' => 'message']
 */
function handleUserRegistration($username, $email, $password, $agreedToTerms) {
    if (!$agreedToTerms) {
        return ['error' => 'terms_required'];
    }

    if (!validateUsername($username)) {
        return ['error' => 'invalid_username'];
    }

    if (!validateEmail($email)) {
        return ['error' => 'invalid_email'];
    }

    if (!validatePassword($password)) {
        return ['error' => 'weak_password'];
    }

    if (createUser($username, $email, $password)) {
        return ['success' => true];
    } else {
        return ['error' => 'registration_failed'];
    }
}

/**
 * Handles user login logic
 * @param string $email
 * @param string $password
 * @return array ['success' => true] or ['error' => 'message']
 */
function handleUserLogin($email, $password) {
    if (empty(trim($email)) || empty($password)) {
        return ['error' => 'missing_fields'];
    }

    if (!validateEmail($email)) {
        return ['error' => 'invalid_email'];
    }

    if (loginUser($email, $password)) {
        return ['success' => true];
    } else {
        return ['error' => 'invalid_credentials'];
    }
}

/**
 * Handles saving user preferences
 * @param int $userId
 * @param array $preferences
 * @return array ['success' => true] or ['error' => 'message']
 */
function handleSavePreferences($userId, $preferences) {
    // Basic validation - allow null values
    if (isset($preferences['max_calories']) && (!is_numeric($preferences['max_calories']) || $preferences['max_calories'] < 0)) {
        return ['error' => 'invalid_calories'];
    }
    if (isset($preferences['max_cooking_time']) && (!is_numeric($preferences['max_cooking_time']) || $preferences['max_cooking_time'] < 0)) {
        return ['error' => 'invalid_time'];
    }

    saveUserPreferences(
        $userId,
        $preferences['diet_type'] ?? null,
        $preferences['max_calories'] ?? null,
        $preferences['max_cooking_time'] ?? null,
        $preferences['cuisine_preference'] ?? null
    );

    return ['success' => true];
}

/**
 * Handles bookmark toggle logic
 * @param int $userId
 * @param int $recipeId
 * @param int $logId
 * @return array ['success' => true, 'saved' => bool, 'message' => string] or ['error' => 'message']
 */
function handleToggleBookmark($userId, $recipeId, $logId) {
    if (!validateRecipeId($recipeId)) {
        return ['error' => 'invalid_recipe_id'];
    }

    $currentlySaved = isRecipeBookmarkedByUser($userId, $recipeId);

    if ($currentlySaved) {
        $ok = removeBookmark($userId, $recipeId);
        $saved = false;
    } else {
        $ok = addBookmark($userId, $recipeId);
        $saved = true;
        if ($ok && $logId > 0) {
            logRecommendationRelevance($userId, $recipeId, $logId, 'save');
        }
    }

    if (!$ok) {
        return ['error' => 'update_failed'];
    }

    return ['success' => true, 'saved' => $saved, 'message' => $saved ? 'Recipe saved to favorites.' : 'Recipe removed from favorites.'];
}

/**
 * Handles feedback submission logic
 * @param int $userId
 * @param int $recipeId
 * @param int $rating
 * @return array ['success' => true] or ['error' => 'message']
 */
function handleSubmitFeedback($userId, $recipeId, $rating) {
    if (!validateRecipeId($recipeId)) {
        return ['error' => 'invalid_recipe_id'];
    }

    if (!validateRating($rating)) {
        return ['error' => 'invalid_rating'];
    }

    $existingRating = getUserRecipeLatestRating($userId, $recipeId);
    if ($existingRating > 0) {
        return ['error' => 'already_rated'];
    }

    submitFeedback($userId, $recipeId, $rating);

    $activeLogId = (int)($_SESSION['latest_recommendation_log_id'] ?? 0);
    if ($rating >= 4 && $activeLogId > 0) {
        markRecommendationResultRelevant($activeLogId, $recipeId);
    }

    return ['success' => true];
}

/**
 * Handles recommendation generation logic
 * @param int $userId
 * @param string $ingredientsInput
 * @param array $postData
 * @return array ['success' => true] or ['error' => 'message']
 */
function handleGenerateRecommendation($userId, $ingredientsInput, $postData) {
    $ingredientsArray = parseIngredientsInput($ingredientsInput);

    if (empty($ingredientsArray)) {
        return ['error' => 'no_ingredients'];
    }

    $inlineFilters = processFilterInput($postData);
    $preferences = mergePreferences($inlineFilters, $userId);

    $recommendations = generateRecommendations($userId, $ingredientsArray, $preferences, 10);

    // Store in session
    $_SESSION['recommendation_results'] = $recommendations;
    $_SESSION['user_ingredients'] = $ingredientsArray;
    $_SESSION['ingredients_input'] = $ingredientsInput;
    $_SESSION['filter_state'] = $preferences;
    $_SESSION['recommendation_fallback'] = false;
    $_SESSION['latest_recommendation_log_id'] = getLatestRecommendationLogIdForUser($userId);

    // Store filter values for form persistence
    $_SESSION['selected_cuisine_preference'] = $postData['cuisine_preference'] ?? '';
    $_SESSION['selected_diet_type'] = $postData['diet_type'] ?? '';
    $_SESSION['selected_meal_type'] = $postData['meal_type'] ?? '';
    $_SESSION['selected_max_cooking_time'] = $postData['max_cooking_time'] ?? '';
    $_SESSION['selected_max_calories'] = $postData['max_calories'] ?? '';

    return ['success' => true];
}

/* =========================================================
   REQUEST HANDLERS
   ========================================================= */

/* =========================================================
   REGISTER
   ========================================================= */

if (isset($_POST['register'])) {
    $result = handleUserRegistration(
        trim($_POST['username']),
        trim($_POST['email']),
        $_POST['password'],
        isset($_POST['agree_terms']) && $_POST['agree_terms'] === '1'
    );

    if (isset($result['error'])) {
        header("Location: ../public/register.php?error=" . $result['error']);
    } else {
        header("Location: ../public/login.php?success=registered");
    }
    exit();
}

/* =========================================================
   LOGIN
   ========================================================= */

if (isset($_POST['login'])) {
    $result = handleUserLogin(trim($_POST['email']), $_POST['password']);

    if (isset($result['error'])) {
        header("Location: ../public/login.php?error=" . $result['error']);
    } else {
        header("Location: ../public/login.php?success=login");
    }
    exit();
}

/* =========================================================
   LOGOUT
   ========================================================= */

if (isset($_GET['logout'])) {
    logoutUser();
    header("Location: ../public/login.php");
    exit();
}

/* =========================================================
   CLEAR FILTERS
   ========================================================= */

if (isset($_GET['clear_filters'])) {
    clearFilterState();
    header("Location: ../public/dashboard.php");
    exit();
}

/* =========================================================
   SAVE USER PREFERENCES
   ========================================================= */

if (isset($_POST['save_preferences'])) {
    requireLogin();

    $preferences = [
        'diet_type' => $_POST['diet_type'] ?? null,
        'max_calories' => $_POST['max_calories'] ?? null,
        'max_cooking_time' => $_POST['max_cooking_time'] ?? null,
        'cuisine_preference' => $_POST['cuisine_preference'] ?? null,
    ];

    $result = handleSavePreferences($_SESSION['user_id'], $preferences);

    if (isset($result['error'])) {
        header("Location: ../preferences.php?error=" . $result['error']);
    } else {
        header("Location: ../preferences.php?success=saved");
    }
    exit();
}

/* =========================================================
   BOOKMARK
   ========================================================= */

if (isset($_POST['bookmark'])) {
    requireLogin();

    $recipeId = intval($_POST['recipe_id']);
    addBookmark($_SESSION['user_id'], $recipeId);

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

if (isset($_POST['remove_bookmark'])) {
    requireLogin();

    $recipeId = intval($_POST['recipe_id']);
    removeBookmark($_SESSION['user_id'], $recipeId);

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

if (isset($_POST['log_recommendation_relevance'])) {
    requireLogin();

    $userId = (int)$_SESSION['user_id'];
    $recipeId = (int)($_POST['recipe_id'] ?? 0);
    $activeLogId = (int)($_SESSION['latest_recommendation_log_id'] ?? 0);
    $logId = $activeLogId > 0 ? $activeLogId : (int)($_POST['log_id'] ?? 0);
    $interactionType = trim((string)($_POST['interaction_type'] ?? 'click'));

    if (!validateRecipeId($recipeId)) {
        header('Content-Type: application/json');
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Invalid recipe id.']);
        exit();
    }

    $ok = logRecommendationRelevance($userId, $recipeId, $logId, $interactionType);

    header('Content-Type: application/json');
    if (!$ok) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Could not log relevance.']);
        exit();
    }

    echo json_encode(['ok' => true]);
    exit();
}

if (isset($_POST['toggle_bookmark'])) {
    requireLogin();

    $recipeId = (int)($_POST['recipe_id'] ?? 0);
    $activeLogId = (int)($_SESSION['latest_recommendation_log_id'] ?? 0);
    $logId = $activeLogId > 0 ? $activeLogId : (int)($_POST['log_id'] ?? 0);

    $result = handleToggleBookmark($_SESSION['user_id'], $recipeId, $logId);

    header('Content-Type: application/json');
    if (isset($result['error'])) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => $result['error']]);
    } else {
        echo json_encode(['ok' => true, 'saved' => $result['saved'], 'message' => $result['message']]);
    }
    exit();
}

/* =========================================================
   SUBMIT FEEDBACK (TAM / SUS)
   ========================================================= */

if (isset($_POST['submit_feedback'])) {
    requireLogin();

    $recipeId = intval($_POST['recipe_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 0);

    $result = handleSubmitFeedback($_SESSION['user_id'], $recipeId, $rating);

    if (isset($result['error'])) {
        header("Location: ../public/recipe.php?id=" . $recipeId . "&feedback=" . $result['error']);
    } else {
        header("Location: ../public/recipe.php?id=" . $recipeId . "&feedback=success");
    }
    exit();
}

/* =========================================================
   GENERATE RECOMMENDATION
   ========================================================= */

function fetchFallbackRecipes(array $ingredients, array $preferences, int $limit = 10) {
    global $conn;

    $where = [];
    $params = [];
    $types = '';

    $ingredientClauses = [];
    foreach ($ingredients as $ingredient) {
        $ingredient = trim($ingredient);
        if ($ingredient === '') {
            continue;
        }
        $ingredientClauses[] = "ingredient_list LIKE ?";
        $params[] = '%' . $ingredient . '%';
        $types .= 's';
    }

    if (!empty($ingredientClauses)) {
        $where[] = '(' . implode(' OR ', $ingredientClauses) . ')';
    }

    if (!empty($preferences['max_calories'])) {
        $targetCalories = (int)$preferences['max_calories'];
        $minCalories = max(0, $targetCalories - 150);
        $maxCalories = $targetCalories + 150;
        $where[] = '(calories IS NULL OR calories BETWEEN ? AND ?)';
        $params[] = $minCalories;
        $params[] = $maxCalories;
        $types .= 'ii';
    }

    if (!empty($preferences['max_cooking_time'])) {
        $targetTime = (int)$preferences['max_cooking_time'];
        $minTime = max(0, $targetTime - 20);
        $maxTime = $targetTime + 20;
        $where[] = '(cooking_time IS NULL OR cooking_time BETWEEN ? AND ?)';
        $params[] = $minTime;
        $params[] = $maxTime;
        $types .= 'ii';
    }

    if (!empty($preferences['cuisine_preference'])) {
        $where[] = '(cuisine_type IS NULL OR LOWER(cuisine_type) = ?)';
        $params[] = strtolower($preferences['cuisine_preference']);
        $types .= 's';
    }

    if (!empty($preferences['diet_type'])) {
        $where[] = '(diet_label IS NULL OR LOWER(diet_label) = ?)';
        $params[] = strtolower($preferences['diet_type']);
        $types .= 's';
    }

    $query = 'SELECT * FROM recipes';
    if (!empty($where)) {
        $query .= ' WHERE ' . implode(' AND ', $where);
    }
    $query .= ' ORDER BY recipe_id DESC LIMIT ?';
    $params[] = $limit;
    $types .= 'i';

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    $results = [];
    $data = $stmt->get_result();
    while ($row = $data->fetch_assoc()) {
        $results[] = [
            'recipe' => $row,
            'similarity_score' => 0
        ];
    }

    return $results;
}

if (isset($_POST['recommend'])) {

    debugLog("=== RECOMMENDATION HANDLER ===");
    debugLog("POST['recommend'] is set");
    
    requireLogin();

    debugLog("User ID: " . $_SESSION['user_id']);
    debugLog("Full POST data: " . json_encode($_POST));

    $ingredientsInput = trim($_POST['ingredients'] ?? '');
    debugLog("Ingredients input: '$ingredientsInput'");

    $result = handleGenerateRecommendation($_SESSION['user_id'], $ingredientsInput, $_POST);

    debugLog("handleGenerateRecommendation() returned: " . (isset($result['error']) ? 'error: ' . $result['error'] : 'success'));

    if (isset($result['error'])) {
        $_SESSION['error_message'] = "Please provide at least one ingredient"; // for no_ingredients
        header("Location: ../public/dashboard.php?error=" . $result['error']);
    } else {
        debugLog("SESSION stored. About to redirect to ../public/dashboard.php");
        debugLog("SESSION['recommendation_results'] count: " . count($_SESSION['recommendation_results'] ?? []));
        header("Location: ../public/dashboard.php");
    }
    exit();
}
