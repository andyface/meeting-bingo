const PATH = '';
var $cookieRack = $('#cookie-rack');

// BINDS

/**
 * Handle creating a game based of the selected settings
 */
$('#create-game').on('click', function() {
    var usernameField = $('#user-name');
    var typeField = $('#type');
    var userName = usernameField.val() ? usernameField.val() : null;
    var type = typeField.val() ? typeField.val() : null;
    var createGame = false;

    if(globals.settings && globals.settings.userName !== userName) {
        $.ajax({
            url: PATH + 'bingoTracker.php',
            method: 'POST',
            data: {
                'action': 'saveUser',
                'name': userName
            },
            dataType: 'json',
            async: false,
            success: function(response) {
                if(response.status === 'failure') {
                    console.log(response.errors);
                }
                else {
                    createGame = true;
                }
            }
        });
    }

    if(createGame) {
        $.ajax({
            url: PATH + 'bingoTracker.php',
            method: 'POST',
            data: {
                'action': 'createGame',
                'type': type
            },
            dataType: 'json',
            success: function (response) {
                window.location = 'board.php?gameId=' + response.gameId;
            }
        });
    }
});

$('#edit-squares').on('click', function() {
    var type = typeField.val() ? typeField.val() : null;
    window.location = 'board.php?type=' + type;
});

$('#join-game').on('click', function() {
    var usernameField = $('#user-name');
    var userName = usernameField.val() ? usernameField.val() : null;
    var gameId = $('#game-id').val();

    if(globals.settings && globals.settings.userName !== userName) {
        $.ajax({
            url: PATH + 'bingoTracker.php',
            method: 'POST',
            data: {
                'action': 'saveUser',
                'name': userName,
                'gameId': gameId
            },
            dataType: 'json',
            async: false,
            success: function() {
                // Redirect user to game after saving details
                window.location = 'board.php?gameId=' + gameId;
            }
        });
    }
});

$cookieRack.on('click', '.close', function() {
    $.ajax({
        url: PATH + 'bingoTracker.php',
        method: 'POST',
        data: {
            'action': 'trackCookieHide'
        },
        dataType: 'json'
    });
});


/**
 * Initialise the cookie toast
 */
$('#cookie-rack .toast').toast({
    'autohide': true,
    'delay': 1000 * 10
});

if(!$('#cookie-rack .toast').hasClass('hide')){
    $('#cookie-rack .toast').toast('show');
}
