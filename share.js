// share.js

// Function to share the image via the selected platform
function shareImage(imageURL, platform) {
    let shareUrl = '';

    switch (platform) {
        case 'whatsapp':
            shareUrl = `https://wa.me/?text=${encodeURIComponent('Check out this quote: ' + imageURL)}`;
            break;
        case 'facebook':
            shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(imageURL)}`;
            break;
        case 'twitter':
            shareUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent('Check out this quote: ' + imageURL)}`;
            break;
        case 'instagram':
            alert('Instagram does not support sharing via URL directly. Please upload manually on the app.');
            return;
        case 'tiktok':
            alert('TikTok does not support sharing via URL directly. Please upload manually on the app.');
            return;
        case 'youtube':
            alert('YouTube does not support sharing via URL directly. Please upload manually on the app.');
            return;
        default:
            alert('Unsupported platform');
            return;
    }

    // Open the share link in a new window
    window.open(shareUrl, '_blank');
}

// Function to handle the share button click
document.getElementById('share-btn').addEventListener('click', function () {
    const container = document.getElementById('quote-container');
    html2canvas(container, {
        scale: 4, // Higher resolution for better image quality
        useCORS: true,
        backgroundColor: "rgba(230, 245, 255, 0.9)", // Background color for image
    }).then(canvas => {
        const imageURL = canvas.toDataURL('image/png'); // Convert to image URL

        // Create a popup for selecting a platform
        const platforms = ['whatsapp', 'facebook', 'twitter', 'instagram', 'tiktok', 'youtube'];
        const sharePrompt = `
            <div style="text-align:center; padding: 20px;">
                <h2>Share this Quote</h2>
                <button onclick="shareImage('${imageURL}', 'whatsapp')">Share on WhatsApp</button>
                <button onclick="shareImage('${imageURL}', 'facebook')">Share on Facebook</button>
                <button onclick="shareImage('${imageURL}', 'twitter')">Share on Twitter</button>
                <button onclick="shareImage('${imageURL}', 'instagram')">Share on Instagram</button>
                <button onclick="shareImage('${imageURL}', 'tiktok')">Share on TikTok</button>
                <button onclick="shareImage('${imageURL}', 'youtube')">Share on YouTube</button>
            </div>
        `;

        const popup = window.open('', '_blank', 'width=400,height=400');
        popup.document.write(sharePrompt);
    });
});
