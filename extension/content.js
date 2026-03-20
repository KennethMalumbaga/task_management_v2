// Content script that communicates between the webpage and background script
(function () {
    'use strict';

    function isTransientMessageFailure(message) {
        if (!message) return false;
        return message === 'The message port closed before a response was received.' ||
            message === 'Extension context invalidated.' ||
            message.indexOf('Receiving end does not exist') !== -1;
    }

    function safeSendMessage(payload, cb, options) {
        const callback = typeof cb === 'function' ? cb : function () { };
        const settings = options || {};
        const maxAttempts = settings.retryOnDisconnect ? 3 : 1;
        let attempt = 0;

        function send() {
            attempt += 1;
            try {
                chrome.runtime.sendMessage(payload, (response) => {
                    const runtimeError = chrome.runtime.lastError ? chrome.runtime.lastError.message : null;
                    const shouldRetry = attempt < maxAttempts &&
                        (isTransientMessageFailure(runtimeError) || (!runtimeError && !response && settings.retryOnDisconnect));

                    if (shouldRetry) {
                        setTimeout(send, 200 * attempt);
                        return;
                    }

                    callback(response, runtimeError);
                });
            } catch (err) {
                const errorMessage = (err && err.message) ? err.message : 'extension_context_invalidated';
                if (attempt < maxAttempts && isTransientMessageFailure(errorMessage)) {
                    setTimeout(send, 200 * attempt);
                    return;
                }
                callback(null, errorMessage);
            }
        }

        send();
    }

    function postBridgeMessage(message) {
        try {
            window.postMessage(message, window.location.origin);
        } catch (err) {
            // no-op
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
                postBridgeMessage({
                    type: 'MONITOR_STREAM_RESPONSE',
                    requestId: event.data.requestId || null,
                    status: response ? response.status : 'error',
                    streamId: response ? (response.streamId || null) : null,
                    message: runtimeError || (response ? response.message : 'no_extension_response')
                });
            }, { retryOnDisconnect: true });
        } else if (event.data.type === 'REQUEST_SCREENSHOT') {
            // Forward to background script
            safeSendMessage({
                type: 'CAPTURE_SCREENSHOT',
                attendanceId: event.data.attendanceId,
                userId: event.data.userId,
                apiUrl: event.data.apiUrl
            }, (response) => {
                // Notify webpage of result
                postBridgeMessage({
                    type: 'SCREENSHOT_RESPONSE',
                    status: response ? response.status : 'error'
                });
            });
        } else if (event.data.type === 'STOP_SCREENSHOT') {
            safeSendMessage({
                type: 'STOP_SCREENSHOT'
            }, (response) => {
                postBridgeMessage({
                    type: 'SCREENSHOT_STOPPED',
                    status: response ? response.status : 'error'
                });
            });
        } else if (event.data.type === 'CHECK_CAPTURE_STATUS') {
            safeSendMessage({
                type: 'CHECK_CAPTURE_STATUS'
            }, (response) => {
                postBridgeMessage({
                    type: 'CAPTURE_STATUS',
                    isCapturing: response ? response.isCapturing : false,
                    attendanceId: response ? response.attendanceId : null
                });
            });
        } else if (event.data.type === 'CHECK_SYSTEM_IDLE_STATE') {
            safeSendMessage({
                type: 'GET_SYSTEM_IDLE_STATE',
                thresholdSeconds: event.data.thresholdSeconds
            }, (response, runtimeError) => {
                postBridgeMessage({
                    type: 'SYSTEM_IDLE_STATE',
                    requestId: event.data.requestId || null,
                    state: response ? response.state : 'unknown',
                    thresholdSeconds: response ? response.thresholdSeconds : (event.data.thresholdSeconds || null),
                    error: runtimeError || (response ? response.error : 'no_extension_response')
                });
            }, { retryOnDisconnect: true });
        } else if (event.data.type === 'ENSURE_CAPTURE_POPUP') {
            safeSendMessage({
                type: 'ENSURE_CAPTURE_POPUP',
                width: event.data.width,
                height: event.data.height,
                left: event.data.left,
                top: event.data.top
            }, function () { });
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
