<?php
session_start();

header('Content-Type: application/json');

error_reporting(0);

// Get the results for calculations for type URL (type FILE contains this calculations in the result.csv file)
if (isset($_GET['getResults'], $_SESSION['hitsOnFirstPage'], $_SESSION['totalHits'], $_SESSION['totalPages'])) {
    die(json_encode([$_SESSION['hitsOnFirstPage'], $_SESSION['totalHits'], $_SESSION['totalPages'], ($_SESSION['hitsOnFirstPage'] > 0) ? number_format((float) ($_SESSION['hitsOnFirstPage'] / $_SESSION['totalPages']), 2, '.', '') : 'NaN', ($_SESSION['totalHits'] > 0) ? number_format((float) ($_SESSION['totalHits'] / $_SESSION['totalPages']), 2, '.', '') : 'NaN']));
}

require '../vendor/autoload.php';
require 'WebScraper.php';
require 'RequestHandler.php';

$requestHandler = new RequestHandler();

$requestHandler->level = intval($_POST['level']);
$requestHandler->nextLevel = $requestHandler->level + 1;
$requestHandler->levels = intval($_POST['levels']);
$requestHandler->urlID = intval($_POST['urlID']);
$requestHandler->condition = $_POST['condition'];

// Start application on very first call
if (isset($_POST['firstCall'])) {
    if (isset($_FILES['csv'])) {
        $requestHandler->startApp('file', $_FILES);
    } else {
        $requestHandler->startApp('url');
    }
}

// Set URL
$requestHandler->selectURL();

// Handle URL
$requestHandler->handleURL();

// Set the next level of URLs if the current level is less then the levels requested
$requestHandler->handleLevels();

// Output JSON result
die(json_encode($requestHandler->createOutput()));
