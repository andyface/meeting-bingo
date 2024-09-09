<?php
declare(strict_types=1);

use Bingo\Src\Helpers\AssetManager;
use Bingo\Src\Services\PdoConnection;
use Bingo\Src\Services\User;

include '../autoloader.php';

$debug = (bool) (isset($_GET['debug']) ? $_GET['debug'] : 0);

// Default to using non minified versions of assets
$assetManager = new AssetManager(TRUE);

// initialise db connection
$pdo = new PdoConnection();
$connection = $pdo->getConnection();

// Get the settings stored in the cookie
$gameId = $_GET['gameId'] ?? null;
$userId = ((int) $_COOKIE['userId']) ?? null;
$type = $_COOKIE['type'] ?? null;
$settingsSaved = isset($_COOKIE['settingsSaved']) ? true : false;
$invalidName = $_GET['noUser'] ?? null;

$settings = json_encode([
    'saved' => $settingsSaved,
    'user' => $userId,
    'type' => $type,
    'gameId' => $gameId
], JSON_NUMERIC_CHECK);

$user = new User($connection, $userId);
?>
<html>
    <head>
        <title>Meeting Bingo - Because everyone needs something to keep them from going insane while in meetings</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css" integrity="sha384-50oBUHEmvpQ+1lW4y57PTFmhCaXp0ML5d60M1M7uH2+nqUivzIebhndOJK28anvf" crossorigin="anonymous">
        <link rel="stylesheet" href="<?= $assetManager->getFileUrl('resources/css/bingo.css'); ?>">
        <link rel="stylesheet" href="<?= $assetManager->getFileUrl('../bib/bib.css'); ?>">
        <script>
            if(!globals) {
                var globals = {};
            }
            globals.debug = <?= $debug ? '1' : '0'; ?>;
            globals.settings = <?= $settings; ?>;
        </script>
    </head>
    <body>
        <div class="container-fluid">
            <h1 class="display-4 text-center">Meeting Bingo</h1>
            <div class="row" style="background: #2b2b2b">
                <div class="col"></div>
                <div class="col-4">
                    <div id="terminal" style="padding: 10px 0">
                        <div><span class="bold green">Bib@server</span>:<span class="bold blue">~/bib</span> <span id="bibmoji">O_O</span></div>
                        <div id="viewport">
                            <div id="prompt">> <span id="console"></span><span id="consoleInput" role="textbox" contenteditable></span><span id="blinky" class="blink">_</span></div>
                        </div>
                    </div>
                </div>
                <div class="col"></div>
            </div>
            <div class="row mt-3">
                <div class="col"></div>
                <div class="col-4">
                    <?php if($user->getUsername()) { ?>
                        <h5>Welcome back, <?= $user->getUsername(); ?></h5>
                        <input id="user-name" type="hidden" value="<?= $user->getUsername(); ?>" />
                    <?php } else { ?>
                        <div class="row">
                            <div class="col-auto">
                                <label class="col-form-label">Type your name: </label>
                            </div>
                            <div class="col has-validation">
                                <input id="user-name"  class="form-control<?= $invalidName ? ' is-invalid' : ''; ?>" type="text" maxlength="255" value="" />
                                <div class="invalid-feedback">
                                    Please type a name
                                </div>
                            </div>
                            <div class="col-auto"></div>
                        </div>
                    <?php } ?>
                </div>
                <div class="col"></div>
            </div>
            <hr />
            <?php if(!$gameId) { ?>
            <h3 class="text-center">Start a game</h3>
            <div class="row">
                <div class="col"></div>
                <div class="col-4">
                    <div class="row">
                        <div class="col-auto">
                            <label class="col-form-label">Select meeting type: </label>
                        </div>
                        <div class="col">
                            <div class="row mb-2">
                                <div class="col">
                                    <select id="type" name="type" class="form-select">
                                        <option value=""<?= $type === '' ? ' selected="selected"' : '' ; ?>>General</option>
                                        <option value="sprint"<?= $type === 'sprint' ? ' selected="selected"' : '' ; ?>>Sprint</option>
                                        <option value="stabilisation"<?= $type === 'stabilisation' ? ' selected="selected"' : '' ; ?>>Stabilisation</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto">
                            <button type="button" id="create-game" class="btn btn-primary">New game</button>
                        </div>
                    </div>
                </div>
                <div class="col"></div>
            </div>
            <hr />
            <?php } ?>
            <h3 class="text-center">Join a game</h3>
            <div class="row">
                <div class="col"></div>
                <div class="col-4">
                    <div class="row">
                        <div class="col-auto">
                            <label class="col-form-label">Game ID:</label>
                        </div>
                        <div class="col">
                            <input id="game-id" class="form-control"  type="text" maxlength="255" value="<?= $gameId; ?>" />
                        </div>
                        <div class="col-auto">
                            <button type="button" id="join-game" class="btn btn-primary">Join game</button>
                        </div>
                    </div>
                </div>
                <div class="col"></div>
            </div>
            <div id="cookie-rack">
                <div class="toast<?= isset($_COOKIE['noCookie']) ? ' hide' : ''; ?>" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header">
                        <i class="fas fa-cookie-bite"></i>
                        <strong class="me-auto">Cookies</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body">
                        Cookies are used for site experience only, no tracking cookies are used, cos they taste bad
                    </div>
                </div>
            </div>
        </div>
    </body>
    <script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
    <script src="<?= $assetManager->getFileUrl('resources/js/index.js'); ?>"></script>
    <script src="<?= $assetManager->getFileUrl('../bib/bib.js'); ?>"></script>
    <script src="<?= $assetManager->getFileUrl('resources/js/bingoBib.js'); ?>"></script>
</html>
