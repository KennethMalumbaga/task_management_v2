// Offscreen document for persistent screen capture
// This document runs independently of the webpage and persists across page refreshes

let mediaStream = null;
let screenshotInterval = null;
let currentAttendanceId = null;
let currentUserId = null;
let apiUrl = null;

// Notify background that offscreen is ready
chrome.runtime.sendMessage({ type: 'OFFSCREEN_READY' });

const MIN_INTERVAL = 20 * 1000; // 20 seconds
const MAX_INTERVAL = 30 * 1000; // 30 seconds

async function logDebug(message) {
    const timestamp = new Date().toISOString().split('T')[1].slice(0, -1);
    const logEntry = `[OFF ${timestamp}] ${message}`;
    console.log(logEntry);

    // Get existing logs
    const data = await chrome.storage.local.get(['debugLogs']);
    let logs = data.debugLogs || [];
    logs.push(logEntry);

    // Keep last 50 logs
    if (logs.length > 50) logs = logs.slice(-50);

    await chrome.storage.local.set({ debugLogs: logs });
}

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
    logDebug('startCapture called');
    // Stop any existing capture first
    stopCapture();

    currentAttendanceId = attendanceId;
    currentUserId = userId;
    apiUrl = url;

    try {
        logDebug('Requesting getUserMedia with streamId: ' + streamId);
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

        logDebug('Screen capture started (stream obtained)');

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
        logDebug('Failed to start capture: ' + err.message);
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
        logDebug('Cannot capture: missing stream/ID/url');
        return;
    }

    try {
        const videoTrack = mediaStream.getVideoTracks()[0];

        if (!videoTrack || videoTrack.readyState !== 'live') {
            logDebug('Video track ended or not live');
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
            // logDebug('Image captured, sending to: ' + apiUrl);

            // Send to server
            fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `attendance_id=${encodeURIComponent(currentAttendanceId)}&image=${encodeURIComponent(dataUrl)}`,
                credentials: 'include'
            }).then(response => {
                response.text().then(text => {
                    // logDebug('Server response: ' + text.substring(0, 50)); 
                    if (text.includes('"status":"success"')) {
                        logDebug('Screenshot sent successfully');
                    } else {
                        logDebug('Server error: ' + text.substring(0, 100));
                    }
                });
            }).catch(err => {
                logDebug('Fetch error: ' + err.message);
            });
        };

        reader.readAsDataURL(blob);

    } catch (err) {
        logDebug('Capture failed: ' + err.message);
        console.error('[Offscreen] Failed to capture screenshot:', err);
    }
}
