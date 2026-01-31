// Offscreen document for persistent screen capture
// This document runs independently of the webpage and persists across page refreshes

let mediaStream = null;
let screenshotInterval = null;
let currentAttendanceId = null;
let currentUserId = null;
let apiUrl = null;

const MIN_INTERVAL = 25 * 1000; // 25 seconds
const MAX_INTERVAL = 35 * 1000; // 35 seconds

// Listen for messages from background script
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (message.type === 'START_OFFSCREEN_CAPTURE') {
        startCapture(message.streamId, message.attendanceId, message.userId, message.apiUrl);
        sendResponse({ status: 'started' });
    } else if (message.type === 'STOP_OFFSCREEN_CAPTURE') {
        stopCapture();
        sendResponse({ status: 'stopped' });
    } else if (message.type === 'GET_CAPTURE_STATUS') {
        sendResponse({
            isCapturing: mediaStream !== null,
            attendanceId: currentAttendanceId
        });
    }
    return true;
});

async function startCapture(streamId, attendanceId, userId, url) {
    // Stop any existing capture first
    stopCapture();

    currentAttendanceId = attendanceId;
    currentUserId = userId;
    apiUrl = url;

    try {
        // Get media stream using the streamId from desktopCapture
        mediaStream = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: {
                mandatory: {
                    chromeMediaSource: 'desktop',
                    chromeMediaSourceId: streamId
                }
            }
        });

        console.log('[Offscreen] Screen capture started');

        // Save state to storage
        chrome.storage.local.set({
            captureActive: true,
            attendanceId: attendanceId,
            userId: userId,
            apiUrl: url
        });

        // Start screenshot loop
        scheduleNextScreenshot();

        // Take first screenshot immediately
        await captureAndSend();

    } catch (err) {
        console.error('[Offscreen] Failed to start capture:', err);
        stopCapture();
    }
}

function stopCapture() {
    console.log('[Offscreen] Stopping capture');

    if (screenshotInterval) {
        clearTimeout(screenshotInterval);
        screenshotInterval = null;
    }

    if (mediaStream) {
        mediaStream.getTracks().forEach(track => track.stop());
        mediaStream = null;
    }

    currentAttendanceId = null;
    currentUserId = null;
    apiUrl = null;

    // Clear storage state
    chrome.storage.local.set({
        captureActive: false,
        attendanceId: null,
        userId: null,
        apiUrl: null
    });
}

function scheduleNextScreenshot() {
    const delay = MIN_INTERVAL + Math.random() * (MAX_INTERVAL - MIN_INTERVAL);
    screenshotInterval = setTimeout(async () => {
        if (mediaStream && currentAttendanceId) {
            await captureAndSend();
            scheduleNextScreenshot();
        }
    }, delay);
}

async function captureAndSend() {
    if (!mediaStream || !currentAttendanceId || !apiUrl) {
        console.log('[Offscreen] Cannot capture: missing stream or attendance ID');
        return;
    }

    try {
        const videoTrack = mediaStream.getVideoTracks()[0];

        if (!videoTrack || videoTrack.readyState !== 'live') {
            console.log('[Offscreen] Video track not live, stopping capture');
            stopCapture();
            return;
        }

        const imageCapture = new ImageCapture(videoTrack);
        const bitmap = await imageCapture.grabFrame();

        // Convert to canvas
        const canvas = new OffscreenCanvas(bitmap.width, bitmap.height);
        const ctx = canvas.getContext('2d');
        ctx.drawImage(bitmap, 0, 0);

        // Convert to blob then to base64
        const blob = await canvas.convertToBlob({ type: 'image/png' });
        const reader = new FileReader();

        reader.onloadend = function () {
            const dataUrl = reader.result;

            // Send to server
            fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `attendance_id=${encodeURIComponent(currentAttendanceId)}&image=${encodeURIComponent(dataUrl)}`,
                credentials: 'include'
            }).then(response => {
                console.log('[Offscreen] Screenshot sent successfully');
            }).catch(err => {
                console.error('[Offscreen] Failed to send screenshot:', err);
            });
        };

        reader.readAsDataURL(blob);

    } catch (err) {
        console.error('[Offscreen] Failed to capture screenshot:', err);
    }
}
