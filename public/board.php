<?php

use Bingo\Src\Helpers\BingoHelper;
use Bingo\Src\Helpers\AssetManager;
use Bingo\Src\Services\PdoConnection;
use Bingo\Src\Services\Bingo;
use Bingo\Src\Services\User;
use Bingo\Src\Services\Game;

include '../autoloader.php';

$debug = (bool) (isset($_GET['debug']) ? $_GET['debug'] : 0);

// Default to using non minified versions of assets
$assetManager = new AssetManager(TRUE);

// initialise db connection
$pdo = new PdoConnection();
$connection = $pdo->getConnection();

// Get the settings from query and cookies
$gameId = $_GET['gameId'];
$userId = ((int) $_COOKIE['userId']) ?? null;
$settingsSaved = isset($_COOKIE['settingsSaved']) ? true : false;

$user = new User($connection, $userId);
$game = new Game($connection, $gameId);

if(!$user->isUser() || !$game->isGame()) {
    header('Location:index.php?gameId=' . $gameId . '&noUser=true');
}

$settings = json_encode([
    'saved' => $settingsSaved,
    'userId' => $user->getId(),
    'userName' => $user->getUsername(),
    'type' => $game->getType(),
    'gameId' => $game->getGameId()
], JSON_NUMERIC_CHECK);

$bingo = new Bingo($connection, $game, $user, $debug);

$bingoCard = $bingo->generateCard();
?>
<html>
    <head>
        <title>Meeting Bingo - Because everyone needs something to keep them from going insane while in meetings</title>
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css" integrity="sha384-50oBUHEmvpQ+1lW4y57PTFmhCaXp0ML5d60M1M7uH2+nqUivzIebhndOJK28anvf" crossorigin="anonymous">
        <link rel="stylesheet" href="<?= $assetManager->getFileUrl('resources/css/bingo.css'); ?>">
        <script>
            var globals = {};
            globals.debug = <?= $debug ? '1' : '0'; ?>;
            globals.settings = <?= $settings; ?>;
        </script>
    </head>
    <body>
        <div class="container">
            <div class="row justify-content-center">
                <div id="notification-centre" class="col-10 text-center">
                    <div class="collapse card overflow-auto flex-column-reverse" id="notifications">
                        <div class="card-body text-left">
                            <div id="no-notifications">There are no notifications</div>
                        </div>
                    </div>
                    <a id="notification-centre-toggle" class="btn btn-outline-secondary collapsed" data-toggle="collapse" href="#notifications" role="button" aria-expanded="false" aria-controls="notification-centre">
                        <i class="fas fa-angle-double-down"></i>
                        <i class="fas fa-angle-double-up"></i>
                    </a>
                </div>
            </div>
            <div class="row">
                <div class="col-2">
                    <div id="notification-toast-rack"></div>
                </div>
                <div class="col-8">
                    <h1 class="display-4 text-center">Meeting Bingo</h1>
                </div>
                <div class="col-2">
                    <div id="toast-rack">
                        <button id="new-game" type="button" class="btn btn-primary">New game</button>
                        <button id="toggle-rolls" type="button" class="btn btn-primary"><i class="fas fa-dice"></i></button>
                        <div class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                            <div class="toast-header">
                                <i class="fas fa-dice-d20 unlucky"></i>
                                <strong class="mr-auto">Unlucky D20 roll</strong>
                                <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="toast-body">
                                Roll a D20 to see if you get an unlucky square:<br />
                                You rolled a <strong><?= $bingoCard['rolls']['unlucky']; ?></strong>.<br /><?= ($bingoCard['rolls']['unlucky'] === 1 ? 'Unlucky.' : 'Congratulations.'); ?>
                            </div>
                            <div class="toast-header">
                                <i class="fas fa-dice-d20 lucky"></i>
                                <strong class="mr-auto">Lucky D20 roll</strong>
                            </div>
                            <div class="toast-body">
                                Roll a D20 to see if you get a lucky square:<br />
                                You rolled a <strong><?= $bingoCard['rolls']['lucky']; ?></strong>.<br /><?= ($bingoCard['rolls']['lucky'] === 20 ? 'Congratulations.' : 'Unlucky.'); ?>
                            </div>
                            <div class="toast-header">
                                <i class="fas fa-dice-d6 rare"></i>
                                <strong class="mr-auto">Rare roll</strong>
                            </div>
                            <div class="toast-body">
                                Roll a custom D6 to see how many rare squares you get:<br />
                                You rolled a <strong><?= $bingoCard['rolls']['rare']; ?></strong><br />
                                <br /><em>Rare square will refresh one unmatched square when matched</em>
                            </div>
                            <div class="toast-header">
                                <i class="fas fa-gem legendary"></i>
                                <strong class="mr-auto">Legendary roll</strong>
                            </div>
                            <div class="toast-body">
                                Roll a D30 to see if you get a legendary square:<br />
                                You rolled a <strong><?= $bingoCard['rolls']['legendary']; ?></strong><br />
                                <?= $bingoCard['rolls']['legendaryCoinFlip'] ? 'You got an <strong>&Uuml;ber Square</strong><br />' : ''; ?>
                                <br /><em>Legendary square will refresh four unmatched square when matched</em>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row row-cols-md-1">
                <div class="col"></div>
                <div class="col card-column">
                    <table id="card">
                        <?php foreach($bingoCard as $rowID => $rowData):
                                if($rowID !== 'rolls'):
                                    $columnCoordinate = 1;
                                    ?><tr class="card-row"><?php
                                    foreach($rowData as $key => $data):
                                        ?><td data-value="<?= Bingo::SQUARE_SCORES[$key]; ?>" data-id="<?= $data['id'];?>" data-coordinate="<?= $rowID . $columnCoordinate; ?>" data-rarity="<?= $data['rarityString']; ?>"  data-gif="<?= $data['gif']; ?>" class="card-square <?= $data['rarityString'] . (isset($data['uberSquare']) ? ' uber-square' : ''); ?>"><?php
                                        if(isset($data['uberSquare'])) :
                                            ?><table><?php
                                            foreach($data['squares'] as $uberKey => $uberData):
                                                if($uberKey === 0 || $uberKey === 2):
                                                    ?><tr><?php
                                                endif;
                                                ?><td class="mini-square"  data-id="<?= $uberData['id'];?>" data-value="U<?= $uberKey; ?>" data-gif="<?= $uberData['gif']; ?>">
                                                    <div class="gif"></div>
                                                    <span class="square-text"><?= $uberData['value']; ?></span>
                                                </td><?php
                                                if($uberKey === 1 || $uberKey === 3):
                                                    ?></tr><?php
                                                endif;
                                            endforeach;
                                            ?></table><?php
                                        else :
                                            ?><div class="refresh-square" data-toggle="tooltip" data-placement="top" title="Costs <?= User::XP_AMOUNT[$data['rarity']] * 2;?> XP">
                                                <i class="fas fa-redo-alt"></i>
                                            </div>
                                            <div class="gif"></div>
                                            <span class="square-text"><?= $data['value']; ?></span><?php
                                        endif;
                                        ?></td><?php
                                        $columnCoordinate++;
                                    endforeach;
                                    ?></tr><?php
                                endif;
                            endforeach;
                        ?>
                    </table>
                </div>
                <div class="col">
                    <div class="progress mb-2">
                        <div class="progress-bar" role="progressbar" style="width: <?= BingoHelper::getPercentageValue($user->getLevelMax(), $user->getXp()); ?>%" aria-valuenow="<?= $user->getXp(); ?>" aria-valuemin="0" aria-valuemax="<?= $user->getLevelMax(); ?>>">
                            <span>XP: <span id="xp-amount"><?= $user->getXp(); ?></span></span>
                        </div>
                    </div>
                    <div id="opponents" class="container-sm">
                        <?php foreach($game->getOpponents($userId) as $opponent): ?>
                        <div class="row">
                            <div class="col">
                                <h6 class="text-center"><?= $opponent['username'] ?></h6>
                                <table id="opponent-<?= $opponent['id']; ?>" class="opponent-card mb-2">
                                    <?php
                                    for($row = 'A'; $row <= 'D'; $row++):
                                        ?><tr><?php
                                        for($column = 1; $column <=4; $column++):
                                            ?><td class="<?= $row . $column; ?>"></td><?php
                                        endfor;
                                        ?></tr><?php
                                    endfor;
                                    ?>
                                </table>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="thats-a-bingo" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-body text-center">
                            <i class="fas fa-medal bingo"></i>
                            <h1>THAT'S A BINGO!</h1>
                            <p>You have bingoed <span id="times-bingoed">0</span> times this game</p>
                        </div>
                        <div class="modal-footer text-center">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Hurray</button>
                        </div>
                    </div>
                </div>
            </div>
            <div id="cookie-rack">
                <div class="toast<?= isset($_COOKIE['noCookie']) ? ' hide' : ''; ?>" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header">
                        <i class="fas fa-cookie-bite"></i>
                        <strong class="mr-auto">Cookies</strong>
                        <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
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
    <script src="<?= $assetManager->getFileUrl('resources/js/bingo.js'); ?>"></script>
</html>