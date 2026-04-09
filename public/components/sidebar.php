<?php
require_once __DIR__ . '/../../algo/filterProcessor.php';

// Get stored filter state from session
$filterState = getStoredFilterState();
$ingredientsValue = $_SESSION['ingredients_input'] ?? '';

// Extract individual filter values for comparison
// Use submitted values passed from dashboard.php (read from SESSION)
$activeCuisine = strtolower($submittedCuisine ?? $filterState['cuisine_preference'] ?? '');
$activeDiet = strtolower($submittedDiet ?? $filterState['diet_type'] ?? '');
$activeMeal = strtolower($submittedMeal ?? $filterState['meal_type'] ?? '');
$activeMaxTime = (string)($submittedTime ?? $filterState['max_cooking_time'] ?? '');
$activeMaxCalories = (string)($submittedCalories ?? $filterState['max_calories'] ?? '');
?>

<div id="sidebar" class="sidebar">

    <form method="POST" action="../core/handleForms.php">

        <!-- INGREDIENTS -->
        <div class="filter-section">
            <h6><i class="bi bi-basket-fill"></i> Ingredients</h6>
            <textarea name="ingredients"
                      class="form-control dark-input"
                      placeholder="chicken, garlic, rice"
                      required><?php echo htmlspecialchars($ingredientsValue); ?></textarea>
        </div>

        <!-- CUISINE -->
        <div class="filter-section">
            <h6><i class="bi bi-globe"></i> Cuisine</h6>
            <div class="pill-group">
                <?php
                $cuisines = ["Any","Algerian","American","Australian","British","Chinese","Egyptian","Filipino","French","Greek","Indian","Italian","Jamaican","Japanese","Malaysian","Mexican","Moroccan","Polish","Portuguese","Russian","Saudi Arabian","Spanish","Syrian","Thai","Turkish","Uruguayan","Venezulan","Vietnamese"];
                foreach ($cuisines as $cuisine) {
                    if ($cuisine === "Any") {
                        $isActive = empty($activeCuisine) ? ' active' : '';
                        $dataValue = '';
                    } else {
                        $isActive = strtolower($cuisine) === $activeCuisine ? ' active' : '';
                        $dataValue = $cuisine;
                    }
                    echo "<button type='button' class='pill-btn$isActive'
                            data-name='cuisine_preference'
                            data-value='$dataValue'>$cuisine</button>";
                }
                ?>
            </div>
        </div>

        <!-- DIET -->
        <div class="filter-section">
            <h6><i class="bi bi-heart-pulse-fill"></i> Diet Type</h6>
            <div class="pill-group">
                <?php
                $diets = ["Any","General","Vegan","Keto","Gluten free","Halal"];
                foreach ($diets as $diet) {
                    if ($diet === "Any") {
                        $isActive = empty($activeDiet) ? ' active' : '';
                        $dataValue = '';
                        $displayName = 'Any';
                    } else {
                        $isActive = strtolower($diet) === $activeDiet ? ' active' : '';
                        $dataValue = $diet;
                        $displayName = ucfirst($diet);
                    }
                    echo "<button type='button' class='pill-btn$isActive'
                            data-name='diet_type'
                            data-value='$dataValue'>$displayName</button>";
                }
                ?>
            </div>
        </div>

        <!-- MEAL TYPE -->
        <div class="filter-section">
            <h6><i class="bi bi-egg-fried"></i> Meal Type</h6>
            <div class="pill-group">
                <?php
                $meals = ["Any","Breakfast","Lunch","Dinner","Snack"];
                foreach ($meals as $meal) {
                    if ($meal === "Any") {
                        $isActive = empty($activeMeal) ? ' active' : '';
                        $dataValue = '';
                        $displayName = 'Any';
                    } else {
                        $isActive = strtolower($meal) === $activeMeal ? ' active' : '';
                        $dataValue = $meal;
                        $displayName = ucfirst($meal);
                    }
                    echo "<button type='button' class='pill-btn$isActive'
                            data-name='meal_type'
                            data-value='$dataValue'>$displayName</button>";
                }
                ?>
            </div>
        </div>

        <!-- COOKING TIME -->
        <div class="filter-section">
            <h6><i class="bi bi-clock-fill"></i> Cooking Time</h6>
            <select name="max_cooking_time" class="form-select dark-input">
                <option value="">Any</option>
                <?php
                for ($i=10; $i<=80; $i+=10) {
                    $selected = ((string)$i === $activeMaxTime) ? ' selected' : '';
                    echo "<option value='$i'" . $selected . ">$i minutes</option>";
                }
                ?>
            </select>
        </div>

        <!-- CALORIES -->
        <div class="filter-section">
            <h6><i class="bi bi-fire"></i> Calories</h6>
            <select name="max_calories" class="form-select dark-input">
                <option value="">Any</option>
                <option value="300"<?php echo $activeMaxCalories === '300' ? ' selected' : ''; ?>>Up to 300 kcal</option>
                <option value="500"<?php echo $activeMaxCalories === '500' ? ' selected' : ''; ?>>Up to 500 kcal</option>
                <option value="700"<?php echo $activeMaxCalories === '700' ? ' selected' : ''; ?>>Up to 700 kcal</option>
                <option value="1000"<?php echo $activeMaxCalories === '1000' ? ' selected' : ''; ?>>Up to 1000 kcal</option>
            </select>
        </div>

        <input type="hidden" name="cuisine_preference" value="<?php echo htmlspecialchars($_SESSION['selected_cuisine_preference'] ?? ''); ?>">
        <input type="hidden" name="diet_type" value="<?php echo htmlspecialchars($_SESSION['selected_diet_type'] ?? ''); ?>">
        <input type="hidden" name="meal_type" value="<?php echo htmlspecialchars($_SESSION['selected_meal_type'] ?? ''); ?>">

        <button type="submit" name="recommend" value="1"
                class="btn apply-btn w-100 mt-3">
            Apply Filters
        </button>

        <a href="../core/handleForms.php?clear_filters=1" class="btn btn-outline-light w-100 mt-2">
            Clear
        </a>

    </form>

</div>
