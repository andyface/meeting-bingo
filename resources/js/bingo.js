const PATH = '';
const GIPHY_GIFS_URL = 'https://api.giphy.com/v1/gifs/';
var timesBingoed = 0;
var $card = $('#card');
var $cells = $card.find('td');
var $notificationsToastRack = $('#notification-toast-rack');
var $cookieRack = $('#cookie-rack');
var toastTimeout;
var gifTimeout = [];

const gifHoldTimeout = 5;
const notificationAutoHide = 20;
const cookieAutoHide = 10;
const rollAutoHide = 10;
const notificationCheckDuration = 15;
const bingoMatchValue = 34;

// BINDS

/**
 * Handle saving user details
 */
$('#save-changes').on('click', function() {
    var userName = $('#user-name').val() ? $('#user-name').val() : null;
    var type = $('#type').val() ? $('#type').val() : null;

    if(globals.settings && globals.settings.userName !== userName) {
        $.ajax({
            url: PATH + 'bingoTracker.php',
            method: 'POST',
            data: {
                'action': 'saveUser',
                'name': userName
            },
            dataType: 'json',
            success: function(response) {
                if(response.status === 'failure') {
                    console.log(response.errors);
                }
            }
        });
    }

    if(globals.settings && globals.settings.type !== type) {
        $.ajax({
            url: PATH + 'bingoTracker.php',
            method: 'POST',
            data: {
                'action': 'changeType',
                'type': type
            },
            dataType: 'json',
            complete: function() {
                location.reload();
            }
        });
    }
});

/**
 * Sets a square to be matched or unmatched and triggers checking for a bingo
 */
$cells.not('.uber-square').on('click', function(){
    var $clicked = $(this);
    var clickedId = $clicked.data('value');
    var activeSquare = $clicked.not('.dead, .mini-square, .uber-square').length > 0;
    var previousTimesBingoed = 0;

    // If the square's not a dead on, trigger matching
    if(!$clicked.data('clicked')) {
        getGif($clicked);

        $clicked.data('clicked', true);

        if(activeSquare) {
            $clicked.data('matched', true);

            if($clicked.data('rarity') === 'rare') {
                refreshUnmatchedSquare(1);
            }
            else if($clicked.data('rarity') === 'legendary') {
                refreshUnmatchedSquare(4);
            }
        }

        previousTimesBingoed = timesBingoed;

        // will set timesBingoed if the match makes a bingo, which is then used below to see if the user has just bingoed or not
        bingo();

        trackMatch(
            $('#user-name').val(),
            $clicked.data('id'),
            $clicked.data('rarity'),
            previousTimesBingoed < timesBingoed ? 1 : 0,
            globals.settings.gameId,
            $clicked.data('coordinate')
        );

        // Handle mini squares and fully matching an Uber square after the main tracking so it doesn't interfere
        if($clicked.is('.mini-square')) {
            var $clickedUberSquare = $clicked.closest('.uber-square');
            var $matchedMiniSquares = $clickedUberSquare.find('td.matched');

            $clicked.addClass('matched');

            if($matchedMiniSquares.length === 4) {
                previousTimesBingoed = timesBingoed;

                $clickedUberSquare.data('matched', true);

                // will set timesBingoed if the match makes a bingo, which is then used below to see if the user has just bingoed or not
                bingo();

                trackMatch(
                    $('#user-name').val(),
                    999999999,
                    'legendary',
                    previousTimesBingoed < timesBingoed ? 1 : 0,
                    globals.settings.gameId,
                    $clickedUberSquare.data('coordinate')
                );

                refreshUnmatchedSquare(4);
            }
        }
    }
    else {
        // Clear any timeouts set on the square for showing the gif image and remove the class hold
        if(gifTimeout[clickedId]) {
            clearTimeout(gifTimeout[clickedId]);
            gifTimeout[clickedId] = null;
            $clicked.removeClass('hold');
        }

        getGif($clicked);
    }
});

function trackMatch(userName, optionId, rarity, bingoed, gameId, coordinates) {
    $.ajax({
        url: PATH + 'bingoTracker.php',
        method: 'POST',
        data: {
            'action': 'trackMatch',
            'name': userName,
            'optionId': optionId,
            'rarity': rarity,
            'bingoed': bingoed,
            'gameId': gameId,
            'coordinate': coordinates
        },
        success: function(response) {
            updateProgressBar(response.xp, response.maxXp);
        },
        dataType: 'json'
    });
}

/**
 * Simple redirect to create a new game
 */
$('#new-game').on('click', function() {
    window.location = 'index.php';
});

/**
 * Toggles the roll display when clicking the button
 */
$('#toggle-rolls').on('click', function() {
    if($('#toast-rack').data('visible') === true) {
        hideRolls();
    }
    else {
        showRolls(false);
    }
});

/**
 * Handle clicking the like button on a match notification
 */
$notificationsToastRack.on('click', '.like-button', function() {
    var $likeButton = $(this);
    var $notification = $likeButton.closest('.toast');
    var $icon = $likeButton.find('.fa-thumbs-up');
    if($icon.hasClass('far')) {
        $icon.removeClass('far').addClass('fas');

        $.ajax({
            url: PATH + 'bingoTracker.php',
            method: 'POST',
            data: {
                'action': 'trackLike',
                'optionId': $notification.data('optionId'),
                'gameId': globals.settings.gameId
            },
            dataType: 'json'
        });
    }
});

/**
 * Handle clicking the dislike button on a match notification
 */
$notificationsToastRack.on('click', '.dislike-button', function() {
    var $dislikeButton = $(this);
    var $notification = $dislikeButton.closest('.toast');
    var $icon = $dislikeButton.find('.fa-thumbs-down');
    if($icon.hasClass('far')) {
        $icon.removeClass('far').addClass('fas');

        $.ajax({
            url: PATH + 'bingoTracker.php',
            method: 'POST',
            data: {
                'action': 'trackDislike',
                'optionId': $notification.data('optionId'),
                'dislike': true,
                'gameId': globals.settings.gameId
            },
            dataType: 'json'
        });
    }
});

/**
 * Close the cookie notification and trigger saving a cookie (ironically) to say that the notification was closed
 */
$cookieRack.on('click', '.close', function() {
    $.ajax({
        url: PATH + 'bingoTracker.php',
        method: 'POST',
        data: {
            'action': 'trackCookieHide',
        },
        dataType: 'json'
    });
});

$('.refresh-square').on('click', function( event ) {
    event.stopPropagation();

    refreshSquare($(this).closest('td'));
});

// FUNCTIONS

/**
 * Checks for if the card has a bingo or not
 */
function bingo() {
    var $table = $('#card');
    var lines = {};

    lines['row1'] = $table.find('tr.card-row:eq(0)').find('td.card-square').filter(function(){
        return $(this).data('matched');
    });

    lines['row2'] = $table.find('tr.card-row:eq(1)').find('td.card-square').filter(function(){
        return $(this).data('matched');
    });

    lines['row3'] = $table.find('tr.card-row:eq(2)').find('td.card-square').filter(function(){
        return $(this).data('matched');
    });

    lines['row4'] = $table.find('tr.card-row:eq(3)').find('td.card-square').filter(function(){
        return $(this).data('matched');
    });

    lines['column1'] = $table.find('tr.card-row').find('td.card-square:eq(0)').filter(function(){
        return $(this).data('matched');
    });

    lines['column2'] = $table.find('tr.card-row').find('td.card-square:eq(1)').filter(function(){
        return $(this).data('matched');
    });

    lines['column3'] = $table.find('tr.card-row').find('td.card-square:eq(2)').filter(function(){
        return $(this).data('matched');
    });

    lines['column4'] = $table.find('tr.card-row').find('td.card-square:eq(3)').filter(function(){
        return $(this).data('matched');
    });

    lines['diaganol1'] = $table.find('tr.card-row:eq(0)').find('td.card-square:eq(0)')
        .add($table.find('tr.card-row:eq(1)').find('td.card-square:eq(1)'))
        .add($table.find('tr.card-row:eq(2)').find('td.card-square:eq(2)'))
        .add($table.find('tr.card-row:eq(3)').find('td.card-square:eq(3)'))
        .filter(function(){
            return $(this).data('matched');
        });

    lines['diaganol2'] = $table.find('tr.card-row:eq(0)').find('td.card-square:eq(3)')
        .add($table.find('tr.card-row:eq(1)').find('td.card-square:eq(2)'))
        .add($table.find('tr.card-row:eq(2)').find('td.card-square:eq(1)'))
        .add($table.find('tr.card-row:eq(3)').find('td.card-square:eq(0)'))
        .filter(function(){
            return $(this).data('matched');
        });

    var previousTimesBingoed = timesBingoed;
    var alreadyBingoed = timesBingoed;

    $.each(lines, function(index, $cells) {
        if(calculateValue($cells) === bingoMatchValue) {
            if(alreadyBingoed <= 0) {
                timesBingoed++;
            }

            alreadyBingoed--;
        }
    });

    if(timesBingoed > 0 && timesBingoed > previousTimesBingoed) {
        thatsABingo();
    }
}

/**
 * calculate the value of a set of cells to see if they make a bingo
 * @param $cells collection of cells to calculate the values from
 * @returns {number}
 */
function calculateValue($cells) {
    var value = 0;
    $cells.each(function() {
        value += $(this).data('value');
    });

    return value;
}

/**
 * Trigger winning modal
 */
function thatsABingo() {
    var $thatsABingo = $('#thats-a-bingo')

    $thatsABingo.find('#times-bingoed').text(timesBingoed);
    $thatsABingo.modal();
}

/**
 * shows the roll details
 */
function showRolls() {
    var $rack = $('#toast-rack');
    $rack.data('visible', true);

    $rack.find('.toast').toast('show');
}

/**
 * Hides the roll details
 */
function hideRolls() {
    clearTimeout(toastTimeout);
    var $rack = $('#toast-rack');
    $rack.data('visible', false);

    $rack.find('.toast').toast('hide');
}

/**
 *
 * @param resetLastNotification Bool If the last notification date should be reset, used for when a new card is initialised
 * to prevent seeing notifications from a previous session
 */
function checkNotifications(resetLastNotification) {
    $.ajax({
        url: PATH + 'bingoTracker.php',
        method: 'POST',
        data: {
            'action': 'checkNotification',
            'resetLastNotification': resetLastNotification,
            'gameId': globals.settings.gameId
        },
        dataType: 'json',
        success: function(data) {
            if(data.notifications) {
                createToasts(data.notifications, data.lastNotification);
                fillNotificationsCentre(data.notifications, data.lastNotification, resetLastNotification);
                createOpponentBoard(data.notifications);
                fillOpponentBoard(data.notifications);
            }
        }
    });
}

function fillNotificationsCentre(notifications, lastNotification, firstLoad) {
    var $notificationCentre = $('#notification-centre');
    var $notificationsBody = $notificationCentre.find('.card-body');
    var $noNotifications = $notificationCentre.find('#no-notifications');

    if(notifications.length > 0 && $noNotifications.length > 0) {
        $noNotifications.remove();
    }

    $.each(notifications, function (index, notification) {
        if(firstLoad || notification.datetime > lastNotification) {
            var output = '';

            switch(notification.type) {
                case 'match':
                    output = notification.username + ' matched ' + notification.value + (notification.bingoed === '1' ? ' and got a <strong>Bingo</strong>.': '.');
                    break;
                case 'like':
                    output = notification.username + ' liked ' + notification.value + '.';
                    break;
                case 'dislike':
                    output = notification.username + ' disliked ' + notification.value + '.';
                    break;
                case 'join':
                    output = notification.username + ' joined the game.';
                    break;
                case 'bingo':
                    output = notification.username + ' got a bingo.';
                    break;
                case 'refresh':
                    output = notification.username + ' refreshed ' + notification.value + '.';
                    break;
            }

            var dateTime = new Date(notification.datetime);
            var displayDate = dateTime.toLocaleString('en-GB', {
                'dateStyle': 'short',
                'timeStyle': 'medium'
            });

            $notificationsBody.append('<div>' + displayDate + ': ' + output + '</div>');
        }
    });
}

function createOpponentBoard(data) {
    var $opponentsContainer = $('#opponents');

    $.each(data, function(index, opponent) {
        if(opponent.type === 'join' && opponent.userId !== globals.settings.userId) {
            var $opponentCard = $('#opponent-' + opponent.userId);

            if (!$opponentCard.length) {
                $opponentsContainer.append(
                    '<div class="row">' +
                    '<div class="col">' +
                    '<h6 class="text-center">' + opponent.username + '</h6>' +
                    '<table id="opponent-' + opponent.userId + '" class="opponent-card mb-2">' +
                    '<tr>' +
                    '<td class="A1"></td>' +
                    '<td class="A2"></td>' +
                    '<td class="A3"></td>' +
                    '<td class="A4"></td>' +
                    '</tr>' +
                    '<tr>' +
                    '<td class="B1"></td>' +
                    '<td class="B2"></td>' +
                    '<td class="B3"></td>' +
                    '<td class="B4"></td>' +
                    '</tr>' +
                    '<tr>' +
                    '<td class="C1"></td>' +
                    '<td class="C2"></td>' +
                    '<td class="C3"></td>' +
                    '<td class="C4"></td>' +
                    '</tr>' +
                    '<tr>' +
                    '<td class="D1"></td>' +
                    '<td class="D2"></td>' +
                    '<td class="D3"></td>' +
                    '<td class="D4"></td>' +
                    '</tr>' +
                    '</table>' +
                    '</div>' +
                    '</div>'
                );
            }
        }
    });
}

function fillOpponentBoard(data) {
    $.each(data, function(index, opponentMatch) {
        if(opponentMatch.type === 'match' && opponentMatch.userId !== globals.settings.userId) {
            var $opponentsContainer = $('#opponents');
            var $opponentCard = $opponentsContainer.find('#opponent-' + opponentMatch.userId);

            var $square = $opponentCard.find('.' + opponentMatch.coordinates);
            var rarity = opponentMatch.rarity

            if (!$square.hasClass(rarity)) {
                $square.addClass(rarity);
            }
        }
    });
}

/**
 * Creates a notification toast for when another player matches a square
 * @param data Details of the square matched
 * @param lastNotification The data of the last notification shown
 */
function createToasts(data, lastNotification) {
    $.each(data, function(index, notification) {
        if(notification.datetime > lastNotification && notification.userId !== globals.settings.userId) {
            var $toast = null;

            switch(notification.type) {
                case 'join':
                    $toast = generateJoinedNotification(notification);
                    break;
                case 'match':
                    $toast = generateMatchedNotification(notification);
                    break;
                case 'like':
                    $toast = generateLikedNotification(notification, false);
                    break;
                case 'dislike':
                    $toast = generateLikedNotification(notification, true);
                    break;
                case 'bingo':
                    $toast = generateBingoNotification(notification, true);
                    break;
                case 'refresh':
                    $toast = generateRefreshNotification(notification, true);
                    break;
            }

            if($toast) {
                $notificationsToastRack.append($toast);
            }
        }
    });

    $notificationsToastRack
        .find('.toast')
        .toast({'autohide': true, 'delay': 1000 * notificationAutoHide})
        .toast('show')
        .on('hidden.bs.toast', function () {
            $(this).remove();
        });
}

function generateMatchedNotification(data) {
    var secondsAgo = calculateSeconds(data['datetime']);
    $toast = $('<div class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-id="' + data['id'] + '" data-option-id="' + data['optionId'] + '">\n' +
        '    <div class="toast-header">\n' +
        '        <i class="fas fa-check-square matched"></i>\n' +
        '        <strong class="mr-auto">Square Matched</strong>\n' +
        '        <small>' + secondsAgo + ' seconds ago</small>\n' +
        '        <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">\n' +
        '            <span aria-hidden="true">&times;</span>\n' +
        '        </button>\n' +
        '    </div>\n' +
        '    <div class="toast-body">' +
        '        <div class="message"></div>' +
        '        <div class="reaction-buttons">' +
        '            <span class="like-button">' +
        '                <i class="far fa-thumbs-up"></i>' +
        '            </span>' +
        '            <span class="dislike-button">' +
        '                 <i class="far fa-thumbs-down"></i>' +
        '            </span>' +
        '        </div>' +
        '    </div>\n' +
        '</div>');
    $toastMessage = $toast.find('.toast-body .message');
    $toastMessage.html((data['username'] ? data['username'] : 'Someone') + ' just matched <strong>' + data['value'] + '</strong>');

    return $toast;
}

function generateLikedNotification(data, dislike) {
    var secondsAgo = calculateSeconds(data['datetime']);
    var verbage = dislike ? 'disliked' : 'liked';
    var icon = dislike ? 'fa-thumbs-down' : 'fa-thumbs-up';
    $toast = $('<div class="toast" role="alert" aria-live="assertive" aria-atomic="true">\n' +
        '    <div class="toast-header">\n' +
        '        <i class="fas ' + icon + '"></i>\n' +
        '        <strong class="mr-auto">Match ' + verbage + '</strong>\n' +
        '        <small>' + secondsAgo + ' seconds ago</small>\n' +
        '        <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">\n' +
        '            <span aria-hidden="true">&times;</span>\n' +
        '        </button>\n' +
        '    </div>\n' +
        '    <div class="toast-body">' +
        '        <div class="message"></div>' +
        '    </div>\n' +
        '</div>');
    var $toastMessage = $toast.find('.toast-body .message');
    $toastMessage.html((data['username'] ? data['username'] : 'Someone') + ' ' + verbage + ' <strong>' + data['value'] + '</strong>');

    return $toast;
}

function generateJoinedNotification(data) {
    var secondsAgo = calculateSeconds(data['datetime']);
    $toast = $('<div class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-id="' + data['id'] + '">\n' +
        '    <div class="toast-header">\n' +
        '        <i class="fas fa-sign-in-alt"></i>\n' +
        '        <strong class="mr-auto">Opponent Joined</strong>\n' +
        '        <small>' + secondsAgo + ' seconds ago</small>\n' +
        '        <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">\n' +
        '            <span aria-hidden="true">&times;</span>\n' +
        '        </button>\n' +
        '    </div>\n' +
        '    <div class="toast-body">' +
        '        <div class="message"></div>' +
        '    </div>\n' +
        '</div>');
    $toastMessage = $toast.find('.toast-body .message');
    $toastMessage.html((data['username'] ? data['username'] : 'Someone') + ' joined the game</strong>');

    return $toast;
}

function generateBingoNotification(data) {
    var secondsAgo = calculateSeconds(data['datetime']);
    $toast = $('<div class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-id="' + data['id'] + '">\n' +
        '    <div class="toast-header">\n' +
        '        <i class="fas fa-medal bingo"></i>\n' +
        '        <strong class="mr-auto">Bingo</strong>\n' +
        '        <small>' + secondsAgo + ' seconds ago</small>\n' +
        '        <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">\n' +
        '            <span aria-hidden="true">&times;</span>\n' +
        '        </button>\n' +
        '    </div>\n' +
        '    <div class="toast-body">' +
        '        <div class="message"></div>' +
        '    </div>\n' +
        '</div>');
    $toastMessage = $toast.find('.toast-body .message');
    $toastMessage.html((data['username'] ? data['username'] : 'Someone') + ' got a Bingo!</strong>');

    return $toast;
}

function generateRefreshNotification(data) {
    var secondsAgo = calculateSeconds(data['datetime']);
    $toast = $('<div class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-id="' + data['id'] + '">\n' +
        '    <div class="toast-header">\n' +
        '        <i class="fas fa-redo-alt"></i>\n' +
        '        <strong class="mr-auto">Bingo</strong>\n' +
        '        <small>' + secondsAgo + ' seconds ago</small>\n' +
        '        <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">\n' +
        '            <span aria-hidden="true">&times;</span>\n' +
        '        </button>\n' +
        '    </div>\n' +
        '    <div class="toast-body">' +
        '        <div class="message"></div>' +
        '    </div>\n' +
        '</div>');
    $toastMessage = $toast.find('.toast-body .message');
    $toastMessage.html((data['username'] ? data['username'] : 'Someone') + ' refreshed <strong>' + data['value'] + '</strong>');

    return $toast;
}

function calculateSeconds(dateTime) {
    var localDate = new Date();
    var localOffsetMilliseconds = localDate.getTimezoneOffset() * 60 * 1000;
    return Math.floor(((localDate - new Date(dateTime)) + localOffsetMilliseconds) / 1000);
}

function getGif($clicked) {
    var getRandom = true;

    $.ajax({
        url: GIPHY_GIFS_URL + 'search',
        data: {
            api_key: 'rwiauNqWsLuVz1FRPaP1iFYp30MADwLg',
            q: $clicked.data('gif'),
            g: 'pg',
            limit: 25,
            lang: 'en'
        },
        dataType: 'json',
        async: false,
        success: function(response) {
            if(response.pagination.count > 1) {
                // Get a random number from 0 - total results of the search to match with indexes
                var randomImage = Math.round(Math.random() * (response.pagination.count - 1));
                var responseImages = response.data[randomImage].images;
                var gifUrl = '';

                if($clicked.hasClass('mini-square')) {
                    gifUrl = responseImages.fixed_width_small.url
                }
                else {
                    gifUrl = responseImages.fixed_width.url
                }

                setGif($clicked, gifUrl);

                getRandom = false;
            }
        }
    });

    if(getRandom) {
        // If no gif was found for the search, return a random one
        $.ajax({
            url: GIPHY_GIFS_URL + 'random',
            data: {
                api_key: 'rwiauNqWsLuVz1FRPaP1iFYp30MADwLg',
                g: 'pg',
                limit: 1,
                lang: 'en'
            },
            dataType: 'json',
            success: function (response) {
                var responseImages = response.data.images;
                var gifUrl = '';

                if($clicked.hasClass('mini-square')) {
                    gifUrl = responseImages.fixed_width_small.url
                }
                else {
                    gifUrl = responseImages.fixed_width.url
                }

                setGif($clicked, gifUrl);
            }
        });
    }
}

function setGif($clicked, gifUrl) {
    var clickedId = $clicked.data('value');
    $clicked.find('.gif').css('background-image', 'url(' + gifUrl + ')');
    $clicked.addClass('matched');

    // If the square is matched and doesn't already have the class hold or a gifTimeout stored, start a timeout for holding the gif image on first click
    if($clicked.hasClass('matched') && !$clicked.hasClass('hold') && !gifTimeout[clickedId]) {
        $clicked.addClass('hold');
        gifTimeout[clickedId] = setTimeout(function(){
            $clicked.removeClass('hold');
        }, 1000 * gifHoldTimeout);
    }
}

// Trigger saving the time user joined the game
function joinGame() {
    $.ajax({
        url: PATH + 'bingoTracker.php',
        method: 'POST',
        data: {
            'action': 'joinGame',
            'gameId': globals.settings.gameId
        },
        dataType: 'json'
    });
}

function refreshUnmatchedSquare(squaresToRefresh) {
    $.ajax({
        url: PATH + 'bingoTracker.php',
        method: 'POST',
        data: {
            'action': 'getNewSquare',
            'gameId': globals.settings.gameId,
            'rarity': 'common',
            'existingSquares': getExistingSquaresIds(),
            'squaresToGet': squaresToRefresh
        },
        dataType: 'json',
        success: function(data) {
            var $unmatchedCommonSquares = $cells.filter(function() {
                var $square = $(this);
                return !$square.data('clicked') && $square.is('.common');
            });

            for (var i = 0; i < squaresToRefresh; i++) {
                var random = Math.floor((Math.random() * $unmatchedCommonSquares.length) - 1);

                var $randomSquare = $($unmatchedCommonSquares.splice(random, 1));

                updateSquareData($randomSquare, data.option[i]);
            }
        }
    });
}

function refreshSquare($square) {
    var squarity = $square.data('rarity');

    $.ajax({
        url: PATH + 'bingoTracker.php',
        method: 'POST',
        data: {
            'action': 'getNewSquare',
            'gameId': globals.settings.gameId,
            'rarity': squarity,
            'existingSquares': getExistingSquaresIds(),
            'costsXp': true,
            'squaresToGet': 1
        },
        dataType: 'json',
        success: function(data) {
            trackRefresh($square.data('id'));
            updateSquareData($square, data.option[0]);
            updateProgressBar(data.xp, data.maxXp);
        }
    });
}

function trackRefresh(refreshSquareId) {
    $.ajax({
        url: PATH + 'bingoTracker.php',
        method: 'POST',
        data: {
            'action': 'trackRefresh',
            'optionId': refreshSquareId,
            'gameId': globals.settings.gameId
        },
        dataType: 'json'
    });
}

function getExistingSquaresIds() {
    var existingIds = [];

    $cells.each(function() {
        var optionId = $(this).data('id');

        if(optionId) {
            existingIds.push(optionId);
        }
    });

    return existingIds;
}

function updateSquareData($square, squareData) {
    $square.data('id', squareData.id)
        .attr('data-id', squareData.id)
        .data('gif', squareData.gif)
        .attr('data-gif', squareData.gif)
        .data('rarity', squareData.rarityString)
        .attr('data-rarity', squareData.rarityString)
        .attr('class', 'card-square ' + squareData.rarityString)
        .find('.square-text').text(squareData.value);

    // trigger free or dead cells so they can be tracked
    if($square.is('.free') || $square.is('.dead')) {
        $square.trigger('click');
    }
}

/**
 * Initialises the card so that it's ready to be used
 */
function initialiseCard() {
    if(!$card.hasClass('initialised')) {
        var $rollToasts = $('#toast-rack .toast');

        // setup roll toast
        $rollToasts.toast({
            'autohide': false
        })
            .on('hidden.bs.toast', function () {
                hideRolls();
            });

        toastTimeout = setTimeout(function () {
            $rollToasts.toast('hide');
        }, 1000 * rollAutoHide);

        // Show rolls
        showRolls(true);

        // start notification checker interval
        setInterval(function () {
            checkNotifications(false);
        }, 1000 * notificationCheckDuration);

        // Do initial notification check
        checkNotifications(true);

        // trigger free cells so they can be tracked
        $cells.filter('.free').each(function () {
            $(this).trigger('click');
        });

        // Set non clickable square to have gifs
        $cells.filter('.dead').each(function () {
            $(this).trigger('click');
        });

        $card.addClass('initialised');
    }
}

function updateProgressBar(xp, maxXp) {
    $('.progress-bar')
        .attr('aria-valuenow', xp)
        .attr('aria-valuemax', maxXp)
        .css('width', Math.round((xp / maxXp) * 100) + '%')
        .find('#xp-amount').text(xp);
}

// INITIALISATIONS
joinGame();
initialiseCard();

/**
 * Initialise the cookie toast
 */
$('#cookie-rack .toast').toast({
    'autohide': true,
    'delay': 1000 * cookieAutoHide
});

if(!$('#cookie-rack .toast').hasClass('hide')){
    $('#cookie-rack .toast').toast('show');
}

$(function() {
    $('[data-toggle="tooltip"]').tooltip()
});
