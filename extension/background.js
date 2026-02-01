// Background service worker for the extension
// Uses offscreen document for persistent screen capture

let currentAttendanceId = null;
let currentUserId = null;
let apiUrl = null;

const OFFSCREEN_DOCUMENT_PATH = 'offscreen.html';

async function logDebug(message) {
    const timestamp = new Date().toISOString().split('T')[1].slice(0, -1);
    const logEntry = `[BG ${timestamp}] ${message}`;
    console.log(logEntry);

    // Get existing logs
    const data = await chrome.storage.local.get(['debugLogs']);
    let logs = data.debugLogs || [];
    logs.push(logEntry);

    // Keep last 50 logs
    if (logs.length > 50) logs = logs.slice(-50);

    await chrome.storage.local.set({ debugLogs: logs });
}

// Check if offscreen document exists
async function hasOffscreenDocument() {
    const contexts = await chrome.runtime.getContexts({
        contextTypes: ['OFFSCREEN_DOCUMENT'],
        documentUrls: [chrome.runtime.getURL(OFFSCREEN_DOCUMENT_PATH)]
    });
    return contexts.length > 0;
}

// Create offscreen document if it doesn't exist
async function setupOffscreenDocument() {
    if (await hasOffscreenDocument()) {
        return;
    }

    await chrome.offscreen.createDocument({
        url: OFFSCREEN_DOCUMENT_PATH,
        reasons: ['DISPLAY_MEDIA'],
        justification: 'Screen capture for employee monitoring'
    });
}

// Close offscreen document
async function closeOffscreenDocument() {
    if (await hasOffscreenDocument()) {
        await chrome.offscreen.closeDocument();
    }
}

// Listen for messages from content script
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
    if (request.type === 'CAPTURE_SCREENSHOT') {
        startScreenshotCapture(request.attendanceId, request.userId, request.apiUrl)
            .then(() => sendResponse({ status: 'started' }))
            .catch(err => sendResponse({ status: 'error', message: err.message }));
        return true; // Keep channel open for async response
    } else if (request.type === 'STOP_SCREENSHOT') {
        stopScreenshotCapture()
            .then(() => sendResponse({ status: 'stopped' }))
            .catch(err => sendResponse({ status: 'error', message: err.message }));
        return true;
    } else if (request.type === 'CHECK_CAPTURE_STATUS') {
        checkCaptureStatus()
            .then(status => sendResponse(status))
            .catch(err => sendResponse({ isCapturing: false }));
        return true;
    }
    return false;
});

async function startScreenshotCapture(attendanceId, userId, url) {
    // Stop any existing capture first
    await stopScreenshotCapture();

    currentAttendanceId = attendanceId;
    currentUserId = userId;
    apiUrl = url || 'http://localhost/task_management_v2/save_screenshot.php';

    // Save state to storage for persistence
    await chrome.storage.local.set({
        captureActive: true,
        attendanceId: attendanceId,
        userId: userId,
        apiUrl: apiUrl
    });

    // Get the active tab
    const tabs = await chrome.tabs.query({ active: true, currentWindow: true });
    if (!tabs[0]) {
        throw new Error('No active tab found');
    }

    // Request screen capture permission
    return new Promise((resolve, reject) => {
        logDebug('Requesting desktop media...');
        chrome.desktopCapture.chooseDesktopMedia(
            ['screen', 'window'],
            tabs[0],
            async (streamId) => {
                if (!streamId) {
                    logDebug('User cancelled capture');
                    await chrome.storage.local.set({ captureActive: false });
                    reject(new Error('User cancelled screen capture'));
                    return;
                }

                logDebug('Got streamId: ' + streamId);

                try {
                    // Create offscreen document
                    logDebug('Setting up offscreen doc...');
                    const existingcontexts = await chrome.runtime.getContexts({
                        contextTypes: ['OFFSCREEN_DOCUMENT'],
                        documentUrls: [chrome.runtime.getURL(OFFSCREEN_DOCUMENT_PATH)]
                    });

                    if (existingcontexts.length === 0) {
                        await setupOffscreenDocument();
                        // Wait a bit for the script to load and be ready to receive messages
                        await new Promise(r => setTimeout(r, 500));
                    }

                    // Send streamId to offscreen document to start capture
                    logDebug('Sending START_OFFSCREEN_CAPTURE');
                    chrome.runtime.sendMessage({
                        type: 'START_OFFSCREEN_CAPTURE',
                        streamId: streamId,
                        attendanceId: attendanceId,
                        userId: userId,
                        apiUrl: apiUrl
                    });

                    logDebug('Message sent to offscreen');
                    resolve();
                } catch (err) {
                    logDebug('Error setting up offscreen: ' + err.message);
                    console.error('Failed to setup offscreen document:', err);
                    reject(err);
                }
            }
        );
    });
}

async function stopScreenshotCapture() {
    currentAttendanceId = null;
    currentUserId = null;

    // Clear storage
    await chrome.storage.local.set({
        captureActive: false,
        attendanceId: null,
        userId: null,
        apiUrl: null
    });

    // Tell offscreen document to stop
    try {
        if (await hasOffscreenDocument()) {
            chrome.runtime.sendMessage({ type: 'STOP_OFFSCREEN_CAPTURE' });
            // Give it a moment then close
            setTimeout(async () => {
                await closeOffscreenDocument();
            }, 500);
        }
    } catch (err) {
        console.error('Error stopping capture:', err);
    }
}

async function checkCaptureStatus() {
    const data = await chrome.storage.local.get(['captureActive', 'attendanceId']);
    return {
        isCapturing: data.captureActive === true,
        attendanceId: data.attendanceId
    };
}

// On extension startup, check if we should resume capture
chrome.runtime.onStartup.addListener(async () => {
    const data = await chrome.storage.local.get(['captureActive']);
    if (data.captureActive) {
        console.log('[Background] Previous capture session detected, clearing state');
        // Clear state - user needs to re-initiate screen share after browser restart
        await chrome.storage.local.set({ captureActive: false });
    }
});

// On install/update
chrome.runtime.onInstalled.addListener(async () => {
    console.log('[Background] Extension installed/updated');
    await chrome.storage.local.set({ captureActive: false });
});
