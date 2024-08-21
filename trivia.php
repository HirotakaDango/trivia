<?php
// URL of the Open Trivia Database API
$baseApiUrl = 'https://opentdb.com/api.php?amount=1';

// Initialize session to store score, health, and other states
session_start();
if (!isset($_SESSION['score'])) $_SESSION['score'] = 0;
if (!isset($_SESSION['health'])) $_SESSION['health'] = 100;
if (!isset($_SESSION['difficulty'])) $_SESSION['difficulty'] = 'easy';
if (!isset($_SESSION['category'])) $_SESSION['category'] = '';
if (!isset($_SESSION['type'])) $_SESSION['type'] = '';

// Function to fetch available categories from the API
function fetchCategories() {
  $categoryUrl = 'https://opentdb.com/api_category.php';
  $response = file_get_contents($categoryUrl);
  if ($response === FALSE) {
    return false;
  }
  $data = json_decode($response, true);
  return $data['trivia_categories'] ?? false;
}

// Function to fetch trivia question
function fetchTriviaQuestion($url) {
  $response = file_get_contents($url);
  if ($response === FALSE) {
    return false;
  }
  $data = json_decode($response, true);
  return $data['results'][0] ?? false;
}

// Function to prompt user for an answer and validate it
function promptUser($question, $options, $correctAnswer) {
  echo "Question: " . htmlspecialchars(html_entity_decode($question)) . "\n";
  echo "Options:\n";
  foreach ($options as $index => $option) {
    echo ($index + 1) . ". " . htmlspecialchars(html_entity_decode($option)) . "\n";
  }

  $handle = fopen("php://stdin", "r");
  $choice = trim(fgets($handle));

  if (!is_numeric($choice) || $choice < 1 || $choice > count($options)) {
    echo "Invalid choice. Please select a number between 1 and " . count($options) . ".\n";
    return false;
  }

  $selectedOption = $options[$choice - 1];
  if ($selectedOption === $correctAnswer) {
    echo "Correct!\n";
    $_SESSION['score']++;
  } else {
    echo "Wrong! The correct answer was: " . htmlspecialchars(html_entity_decode($correctAnswer)) . "\n";
    $_SESSION['health'] -= 5;
  }

  return true;
}

// Function to update and display game status
function updateGameStatus() {
  echo "Score: " . $_SESSION['score'] . " | Health: " . $_SESSION['health'] . " | Difficulty: " . $_SESSION['difficulty'] . "\n";
  if ($_SESSION['health'] <= 0) {
    echo "Game Over! Final Score: " . $_SESSION['score'] . "\n";
    session_destroy();
    exit;
  }
}

// Function to prompt user to choose a category
function chooseCategory($categories) {
  echo "Please choose a category (or leave blank for all categories):\n";
  foreach ($categories as $category) {
    echo $category['id'] . ". " . htmlspecialchars($category['name']) . "\n";
  }

  $handle = fopen("php://stdin", "r");
  $choice = trim(fgets($handle));

  if ($choice === '') {
    // User chose to leave it blank
    $_SESSION['category'] = ''; // Empty means all categories
    return true;
  }

  foreach ($categories as $category) {
    if ($choice == $category['id']) {
      $_SESSION['category'] = $choice;
      return true;
    }
  }

  echo "Invalid choice. Please select a valid category ID or leave it blank.\n";
  return false;
}

// Function to prompt user to choose question type
function chooseType() {
  echo "Please choose the type of question (or leave blank for all types):\n";
  echo "1. Multiple Choice\n";
  echo "2. True/False\n";

  $handle = fopen("php://stdin", "r");
  $choice = trim(fgets($handle));

  if ($choice === '') {
    // User chose to leave it blank
    $_SESSION['type'] = ''; // Empty means all types
    return true;
  }

  if ($choice == '1') {
    $_SESSION['type'] = 'multiple';
  } elseif ($choice == '2') {
    $_SESSION['type'] = 'boolean';
  } else {
    echo "Invalid choice. Please choose 1, 2, or leave it blank.\n";
    return false;
  }

  return true;
}

// Function to prompt user to choose difficulty
function chooseDifficulty() {
  echo "Please choose the difficulty level:\n";
  echo "1. Easy\n";
  echo "2. Medium\n";
  echo "3. Hard\n";

  $handle = fopen("php://stdin", "r");
  $choice = trim(fgets($handle));

  if ($choice == '1') {
    $_SESSION['difficulty'] = 'easy';
  } elseif ($choice == '2') {
    $_SESSION['difficulty'] = 'medium';
  } elseif ($choice == '3') {
    $_SESSION['difficulty'] = 'hard';
  } else {
    echo "Invalid choice. Please choose 1, 2, or 3.\n";
    return false;
  }

  return true;
}

// Get available categories and prompt the user to choose one
$categories = fetchCategories();
if ($categories === false) {
  echo "Error fetching categories.\n";
  exit;
}

while (!chooseCategory($categories));
while (!chooseType());
while (!chooseDifficulty());

while (true) {
  // Build the API URL with parameters
  $apiUrl = $baseApiUrl . '&difficulty=' . $_SESSION['difficulty'];
  if ($_SESSION['type'] !== '') {
    $apiUrl .= '&type=' . $_SESSION['type'];
  }
  if ($_SESSION['category'] !== '') {
    $apiUrl .= '&category=' . $_SESSION['category'];
  }

  // Fetch a trivia question
  $question = fetchTriviaQuestion($apiUrl);
  if ($question !== false) {
    $options = array_merge($question['incorrect_answers'], [$question['correct_answer']]);
    shuffle($options);
    promptUser($question['question'], $options, $question['correct_answer']);
    updateGameStatus();
  } else {
    echo "Error fetching question.\n";
  }

  // Pause before the next question
  echo "Fetching next question...\n";
  sleep(10); // Adjust sleep time as needed
}