// Background service worker for the extension
// Uses offscreen document for persistent screen capture

let currentAttendanceId = null;
let currentUserId = null;
let apiUrl = null;
const DEFAULT_MIN_INTERVAL_MINUTES = 20;
const DEFAULT_MAX_INTERVAL_MINUTES = 30;
const MIN_ALLOWED_INTERVAL_MINUTES = 5;
const MAX_ALLOWED_INTERVAL_MINUTES = 180;
const MIN_IDLE_THRESHOLD_SECONDS = 15;

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

function normalizeIntervalMinutes(value, fallbackValue) {
    const parsed = Number.parseInt(String(value ?? ''), 10);
    if (!Number.isFinite(parsed) || parsed < MIN_ALLOWED_INTERVAL_MINUTES || parsed > MAX_ALLOWED_INTERVAL_MINUTES) {
        return fallbackValue;
    }
    return parsed;
}

function resolveCaptureIntervalConfig(minValue, maxValue) {
    let minMinutes = normalizeIntervalMinutes(minValue, DEFAULT_MIN_INTERVAL_MINUTES);
    let maxMinutes = normalizeIntervalMinutes(maxValue, DEFAULT_MAX_INTERVAL_MINUTES);

    if (minMinutes > maxMinutes) {
        [minMinutes, maxMinutes] = [maxMinutes, minMinutes];
    }

    return {
        minIntervalMinutes: minMinutes,
        maxIntervalMinutes: maxMinutes
    };
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
    if (request.type === 'REQUEST_MONITOR_STREAM') {
        requestMonitorStreamId(sender.tab)
            .then((streamId) => sendResponse({ status: 'success', streamId: streamId }))
            .catch((err) => sendResponse({ status: 'error', message: err.message }));
        return true;
    } else if (request.type === 'CAPTURE_SCREENSHOT') {
        startScreenshotCapture(
            request.attendanceId,
            request.userId,
            request.apiUrl,
            request.minIntervalMinutes,
            request.maxIntervalMinutes
        )
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
    } else if (request.type === 'GET_SYSTEM_IDLE_STATE') {
        const rawThreshold = Number(request.thresholdSeconds);
        const thresholdSeconds = Math.min(
            3600,
            Math.max(
                MIN_IDLE_THRESHOLD_SECONDS,
                isFinite(rawThreshold) ? Math.floor(rawThreshold) : MIN_IDLE_THRESHOLD_SECONDS
            )
        );

        if (!chrome.idle || typeof chrome.idle.queryState !== 'function') {
            sendResponse({ state: 'unsupported', thresholdSeconds: thresholdSeconds, error: 'idle_api_unavailable' });
            return false;
        }

        chrome.idle.queryState(thresholdSeconds, (state) => {
            if (chrome.runtime.lastError) {
                sendResponse({
                    state: 'unknown',
                    thresholdSeconds: thresholdSeconds,
                    error: chrome.runtime.lastError.message || 'idle_query_failed'
                });
                return;
            }
            sendResponse({
                state: state || 'unknown',
                thresholdSeconds: thresholdSeconds,
                error: null
            });
        });
        return true;
    } else if (request.type === 'ENSURE_CAPTURE_POPUP') {
        ensureCapturePopupWindow(sender.tab, request || {});
    } else if (request.type === 'MINIMIZE_WINDOW') {
        if (sender.tab && sender.tab.windowId) {
            chrome.windows.update(sender.tab.windowId, { state: 'minimized' });
        }
    }
    return false;
});

async function requestMonitorStreamId(tab) {
    if (!tab || !tab.id) {
        throw new Error('Unable to identify the monitoring tab');
    }

    function shouldAutoMinimizeShareWindow(targetTab) {
        if (!targetTab || !targetTab.windowId) return false;
        const url = String(targetTab.url || targetTab.pendingUrl || '');
        return url.indexOf('capture.html') !== -1;
    }

    function autoMinimizeShareWindow(targetTab) {
        if (!shouldAutoMinimizeShareWindow(targetTab)) return;
        try {
            chrome.windows.update(targetTab.windowId, { state: 'minimized' });
        } catch (e) {
            // best effort only
        }
    }

    return new Promise((resolve, reject) => {
        chrome.desktopCapture.chooseDesktopMedia(
            ['screen'],
            tab,
            (streamId) => {
                if (chrome.runtime.lastError) {
                    reject(new Error(chrome.runtime.lastError.message || 'Unable to start screen sharing'));
                    return;
                }

                if (!streamId) {
                    reject(new Error('User cancelled screen capture'));
                    return;
                }

                autoMinimizeShareWindow(tab);
                resolve(streamId);
            }
        );
    });
}

function ensureCapturePopupWindow(tab, options) {
    if (!tab || !tab.id || !tab.windowId) {
        return;
    }
    const url = String(tab.url || tab.pendingUrl || '');
    if (url.indexOf('capture.html') === -1) {
        return;
    }

    chrome.windows.get(tab.windowId, function (win) {
        if (chrome.runtime.lastError) {
            return;
        }
        if (win && win.type === 'popup') {
            return;
        }

        const width = Number(options.width);
        const height = Number(options.height);
        const left = Number(options.left);
        const top = Number(options.top);

        const createOptions = {
            tabId: tab.id,
            type: 'popup',
            focused: true
        };
        if (isFinite(width) && width >= 300) {
            createOptions.width = Math.round(width);
        }
        if (isFinite(height) && height >= 260) {
            createOptions.height = Math.round(height);
        }
        if (isFinite(left) && left >= 0) {
            createOptions.left = Math.round(left);
        }
        if (isFinite(top) && top >= 0) {
            createOptions.top = Math.round(top);
        }

        try {
            chrome.windows.create(createOptions, function (newWin) {
                if (!chrome.runtime.lastError && newWin) {
                    return;
                }
                const fallbackOptions = {
                    url: url,
                    type: 'popup',
                    focused: true
                };
                if (isFinite(width) && width >= 300) {
                    fallbackOptions.width = Math.round(width);
                }
                if (isFinite(height) && height >= 260) {
                    fallbackOptions.height = Math.round(height);
                }
                if (isFinite(left) && left >= 0) {
                    fallbackOptions.left = Math.round(left);
                }
                if (isFinite(top) && top >= 0) {
                    fallbackOptions.top = Math.round(top);
                }
                chrome.windows.create(fallbackOptions, function (fallbackWin) {
                    if (fallbackWin && fallbackWin.tabs && fallbackWin.tabs.length) {
                        try {
                            chrome.tabs.remove(tab.id);
                        } catch (err) {
                            // best effort only
                        }
                    }
                });
            });
        } catch (err) {
            // best effort only
        }
    });
}

async function startScreenshotCapture(attendanceId, userId, url, minIntervalMinutes, maxIntervalMinutes) {
    // Stop any existing capture first
    await stopScreenshotCapture();

    const intervalConfig = resolveCaptureIntervalConfig(minIntervalMinutes, maxIntervalMinutes);
    currentAttendanceId = attendanceId;
    currentUserId = userId;
    apiUrl = url || 'http://localhost/task_management_v2/save_screenshot.php';

    // Save state to storage for persistence
    await chrome.storage.local.set({
        captureActive: true,
        attendanceId: attendanceId,
        userId: userId,
        apiUrl: apiUrl,
        minIntervalMinutes: intervalConfig.minIntervalMinutes,
        maxIntervalMinutes: intervalConfig.maxIntervalMinutes
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
            ['screen'],
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
                        apiUrl: apiUrl,
                        minIntervalMinutes: intervalConfig.minIntervalMinutes,
                        maxIntervalMinutes: intervalConfig.maxIntervalMinutes
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
        apiUrl: null,
        minIntervalMinutes: null,
        maxIntervalMinutes: null
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
