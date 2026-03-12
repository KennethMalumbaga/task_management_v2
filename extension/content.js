// Content script that communicates between the webpage and background script
(function () {
    'use strict';

    function safeSendMessage(payload, cb) {
        try {
            chrome.runtime.sendMessage(payload, (response) => {
                const runtimeError = chrome.runtime.lastError ? chrome.runtime.lastError.message : null;
                cb(response, runtimeError);
            });
        } catch (err) {
            cb(null, (err && err.message) ? err.message : 'extension_context_invalidated');
        }
    }

    // Listen for messages from the webpage
    window.addEventListener('message', function (event) {
        // Only accept messages from same origin
        if (event.origin !== window.location.origin) return;

        if (event.data.type === 'REQUEST_MONITOR_STREAM') {
            safeSendMessage({
                type: 'REQUEST_MONITOR_STREAM'
            }, (response, runtimeError) => {
                window.postMessage({
                    type: 'MONITOR_STREAM_RESPONSE',
                    requestId: event.data.requestId || null,
                    status: response ? response.status : 'error',
                    streamId: response ? (response.streamId || null) : null,
                    message: runtimeError || (response ? response.message : 'no_extension_response')
                }, window.location.origin);
            });
        } else if (event.data.type === 'REQUEST_SCREENSHOT') {
            // Forward to background script
            safeSendMessage({
                type: 'CAPTURE_SCREENSHOT',
                attendanceId: event.data.attendanceId,
                userId: event.data.userId,
                apiUrl: event.data.apiUrl
            }, (response) => {
                // Notify webpage of result
                window.postMessage({
                    type: 'SCREENSHOT_RESPONSE',
                    status: response ? response.status : 'error'
                }, window.location.origin);
            });
        } else if (event.data.type === 'STOP_SCREENSHOT') {
            safeSendMessage({
                type: 'STOP_SCREENSHOT'
            }, (response) => {
                window.postMessage({
                    type: 'SCREENSHOT_STOPPED',
                    status: response ? response.status : 'error'
                }, window.location.origin);
            });
        } else if (event.data.type === 'CHECK_CAPTURE_STATUS') {
            safeSendMessage({
                type: 'CHECK_CAPTURE_STATUS'
            }, (response) => {
                window.postMessage({
                    type: 'CAPTURE_STATUS',
                    isCapturing: response ? response.isCapturing : false,
                    attendanceId: response ? response.attendanceId : null
                }, window.location.origin);
            });
        } else if (event.data.type === 'CHECK_SYSTEM_IDLE_STATE') {
            safeSendMessage({
                type: 'GET_SYSTEM_IDLE_STATE',
                thresholdSeconds: event.data.thresholdSeconds
            }, (response, runtimeError) => {
                window.postMessage({
                    type: 'SYSTEM_IDLE_STATE',
                    requestId: event.data.requestId || null,
                    state: response ? response.state : 'unknown',
                    thresholdSeconds: response ? response.thresholdSeconds : (event.data.thresholdSeconds || null),
                    error: runtimeError || (response ? response.error : 'no_extension_response')
                }, window.location.origin);
            });
        } else if (event.data.type === 'MINIMIZE_WINDOW') {
            safeSendMessage({
                type: 'MINIMIZE_WINDOW'
            }, function () { });
        }
    });

    try {
        window.postMessage({
            type: 'SCREENSHOT_EXTENSION_BRIDGE_READY'
        }, window.location.origin);
    } catch (err) {
        // no-op
    }


})();
