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

    // Open the share link in the current window
    window.location.href = shareUrl;
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

        // Create a modal for selecting a platform
        const modal = document.createElement('div');
        modal.id = 'share-modal';
        modal.style.position = 'fixed';
        modal.style.top = 0;
        modal.style.left = 0;
        modal.style.width = '100%';
        modal.style.height = '100%';
        modal.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
        modal.style.display = 'flex';
        modal.style.justifyContent = 'center';
        modal.style.alignItems = 'center';
        modal.style.zIndex = '1000';

        modal.innerHTML = `
            <div style="background: white; padding: 20px; border-radius: 10px; text-align: center; max-width: 400px; width: 100%;">
                <h2>Share this Quote</h2>
                <button onclick="shareImage('${imageURL}', 'whatsapp')">Share on WhatsApp</button>
                <button onclick="shareImage('${imageURL}', 'facebook')">Share on Facebook</button>
                <button onclick="shareImage('${imageURL}', 'twitter')">Share on Twitter</button>
                <button onclick="shareImage('${imageURL}', 'instagram')">Share on Instagram</button>
                <button onclick="shareImage('${imageURL}', 'tiktok')">Share on TikTok</button>
                <button onclick="shareImage('${imageURL}', 'youtube')">Share on YouTube</button>
                <br><br>
                <button onclick="closeModal()">Close</button>
            </div>
        `;
        
        document.body.appendChild(modal);
    });
});

// Function to close the modal
function closeModal() {
    const modal = document.getElementById('share-modal');
    modal.style.display = 'none';
}
