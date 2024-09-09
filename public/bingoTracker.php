<?php
declare(strict_types=1);

namespace Bingo\Src\Controllers;

use Bingo\Src\Services\PdoConnection;

include '..' . DIRECTORY_SEPARATOR . 'autoloader.php';

// initialise db connection
$connection = (new PdoConnection())->getConnection();

$action = $_POST['action'];
$request = $_POST;

$controller = new BingoController($connection, (int) $_COOKIE['userId'], $_POST['gameId'] ?? null);

if(method_exists($controller, $action)) {
    $response = $controller->{$action}($request);

    echo json_encode($response);
}