// tab-title-switcher.js
const originalTitle = document.title; // Store the original title

document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        // When tab loses focus, change title
        document.title = "Come back! | Creative Pulse";
    } else {
        // When tab regains focus, restore original title
        document.title = originalTitle;
    }
});
