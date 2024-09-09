globals.thingsToWrite = globals.thingsToWrite.concat([
    {
        text: "Whatcha doing?",
        bibmoji: "ⱺ_ⱺ"
    },
    {
        text: "BINGO!",
        bibmoji: "^U^"
    }
]);

writeToConsole({
    text: $('#user-name').val() ? "Hello again, " + $('#user-name').val() : "Hello, what's your name?",
    bibmoji: $('#user-name').val() ? "^_^" : "?_?",
    newline: true
});