// Content script that communicates between the webpage and background script
(function () {
    'use strict';

    // Listen for messages from the webpage
    window.addEventListener('message', function (event) {
        // Only accept messages from same origin
        if (event.origin !== window.location.origin) return;

        if (event.data.type === 'REQUEST_SCREENSHOT') {
            // Forward to background script
            chrome.runtime.sendMessage({
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
            chrome.runtime.sendMessage({
                type: 'STOP_SCREENSHOT'
            }, (response) => {
                window.postMessage({
                    type: 'SCREENSHOT_STOPPED',
                    status: response ? response.status : 'error'
                }, window.location.origin);
            });
        } else if (event.data.type === 'CHECK_CAPTURE_STATUS') {
            chrome.runtime.sendMessage({
                type: 'CHECK_CAPTURE_STATUS'
            }, (response) => {
                window.postMessage({
                    type: 'CAPTURE_STATUS',
                    isCapturing: response ? response.isCapturing : false,
                    attendanceId: response ? response.attendanceId : null
                }, window.location.origin);
            });
        }
    });


})();
