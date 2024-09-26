const Token = document.getElementById('google-meet-and-zoom-integration').getAttribute('data-wp-nonce');

const googleClient = document.getElementById('google-meet-and-zoom-integration').getAttribute('data-google-client-id');
const zoomApiKey = document.getElementById('google-meet-and-zoom-integration').getAttribute('data-zoom-api-key');

export default {
    Token,
    googleClient,
    zoomApiKey
};
