
    // Store user ID from PHP session
    var currentUserId = null;
    var isEmployeeUser = null;
    var isAdminUser = null;
    var attendanceAjaxCsrfToken = null;
    var bulletinPostCsrfToken = null;
    var bulletinDeleteCsrfToken = null;
    var bulletinPosts = null;
    var bulletinTagLabels = { ann: 'Announcement', rem: 'Reminder', alt: 'Alert' };

    const btnIn = document.getElementById('btnTimeIn');
    const btnOut = document.getElementById('btnTimeOut');
    const statusSpan = document.getElementById('attendanceStatus');
    const btnInIcon = document.getElementById('clockInButtonIcon');
    const btnInLabel = document.getElementById('clockInButtonLabel');
    const btnInLockNote = document.getElementById('clockInButtonLockNote');
    const clockInSetupAnchor = document.getElementById('clockInSetupAnchor');
    const clockInSetupHover = document.getElementById('clockInSetupHover');
    const clockInSetupOpenGuideBtn = document.getElementById('clockInSetupOpenGuideBtn');
    const clockInSetupHideHoverBtn = document.getElementById('clockInSetupHideHoverBtn');
    const clockInSetupBanner = document.getElementById('clockInSetupBanner');
    const clockInSetupBannerBtn = document.getElementById('clockInSetupBannerBtn');
    let clockInSetupModal = document.getElementById('clockInSetupModal');
    let clockInSetupCloseBtn = document.getElementById('clockInSetupCloseBtn');
    let clockInSetupDownloadBtn = document.getElementById('clockInSetupDownloadBtn');
    let clockInSetupDownloadCard = document.getElementById('clockInSetupDownloadCard');
    let clockInSetupDownloadIcon = document.getElementById('clockInSetupDownloadIcon');
    let clockInSetupDownloadTitle = document.getElementById('clockInSetupDownloadTitle');
    let clockInSetupDownloadText = document.getElementById('clockInSetupDownloadText');
    let clockInSetupStatusCard = document.getElementById('clockInSetupStatusCard');
    let clockInSetupStatusCheck = document.getElementById('clockInSetupStatusCheck');
    let clockInSetupStatusTitle = document.getElementById('clockInSetupStatusTitle');
    let clockInSetupStatusText = document.getElementById('clockInSetupStatusText');
    let clockInSetupPrimaryBtn = document.getElementById('clockInSetupPrimaryBtn');
    let clockInSetupDismissHoverBtn = document.getElementById('clockInSetupDismissHoverBtn');
    let clockInGuideTabButtons = Array.prototype.slice.call(document.querySelectorAll('[data-clockin-tab-button]'));
    let clockInGuidePanels = Array.prototype.slice.call(document.querySelectorAll('[data-clockin-panel]'));
    let clockInGuideVideoShells = Array.prototype.slice.call(document.querySelectorAll('[data-clockin-video-shell]'));
    let clockInGuideSlideshows = Array.prototype.slice.call(document.querySelectorAll('[data-clockin-slideshow]'));
    let attendanceId = null;
    let captureWindow = null;
    let hasActiveAttendance = false;
    let isAutoClockOutInProgress = false;
    let isManualClockOutInProgress = false;
    let isClockInRequestInProgress = false;
    const idleCheckThresholdMs = 100000; // 100 seconds
    const idleCheckCountdownStartSeconds = 60;
    let idleCheckTimer = null;
    let idleCheckCountdownTimer = null;
    let idleCheckSecondsRemaining = idleCheckCountdownStartSeconds;
    let lastDashboardActivityAt = Date.now();
    let isIdleCheckModalOpen = false;
    let isIdleLogoutInProgress = false;
    let pendingIdleCaptureResolve = null;
    let pendingIdleCaptureTimer = null;
    const defaultDocumentTitle = document.title;
    let lastIdleNotificationPermissionRequestAt = 0;
    const idleNotificationPermissionRequestCooldownMs = 30000;
    let idleWarningNotification = null;
    const captureHeartbeatStorageKey = 'taskflow_capture_heartbeat';
    const captureInputStateStorageKey = 'taskflow_capture_input_state';
    const captureHeartbeatFreshMs = 60000;
    const captureInputStateFreshMs = 45000;
    let lastCaptureHeartbeatAt = 0;
    let lastCaptureInputState = 'unknown';
    let lastCaptureInputStateAt = 0;
    let lastCaptureInputThresholdReached = false;
    const clockInNavWarningKey = 'taskflow_nav_clockin_warned_once_user_' + String(currentUserId || 'guest');
    const clockInGuideHoverDisabledKey = 'taskflow_clockin_hover_guide_disabled_user_' + String(currentUserId || 'guest');
    const clockInExtensionDownloadKey = 'taskflow_clockin_extension_downloaded_user_' + String(currentUserId || 'guest');
    const clockInGuideSteps = [
        {
            iconClass: 'fa-download',
            label: 'Step 1: Download the Extension',
            desc: 'Use the download button below to get the screen capture extension zip file.'
        },
        {
            iconClass: 'fa-puzzle-piece',
            label: 'Step 2: Open Chrome Extensions',
            desc: 'Open chrome://extensions in Google Chrome.'
        },
        {
            iconClass: 'fa-wrench',
            label: 'Step 3: Enable Developer Mode',
            desc: 'Turn on Developer mode at the top-right of the Extensions page.'
        },
        {
            iconClass: 'fa-folder-open',
            label: 'Step 4: Load Unpacked',
            desc: 'Extract the zip, click Load unpacked, then select the extracted extension folder.'
        },
        {
            iconClass: 'fa-check-circle',
            label: 'Step 5: Refresh and Clock In',
            desc: 'Refresh this page after loading the extension. Clock In unlocks as soon as the extension is detected.'
        }
    ];
    let hasSeenClockInNavWarning = false;
    let pendingNavTarget = null;
    let pendingBulletinDeleteId = null;
    let isCaptureExtensionAvailable = !!window.screenshotExtensionAvailable;
    let clockInGuideHoverDisabled = false;
    let clockInExtensionDownloaded = false;
    let clockInGuideTab = 'video';
    let clockInGuideSlideIndex = 0;
    let clockInGuideSlideTimer = null;
    let clockInSetupHoverHideTimer = null;
    let hasClockInSetupBindingsInitialized = false;
    try {
        hasSeenClockInNavWarning = sessionStorage.getItem(clockInNavWarningKey) === '1';
    } catch (e) {
        hasSeenClockInNavWarning = false;
    }
    try {
        clockInGuideHoverDisabled = localStorage.getItem(clockInGuideHoverDisabledKey) === '1';
        clockInExtensionDownloaded = localStorage.getItem(clockInExtensionDownloadKey) === '1';
    } catch (e) {
        clockInGuideHoverDisabled = false;
        clockInExtensionDownloaded = false;
    }

    function setStoredClockInGuideFlag(key, enabled) {
        try {
            if (enabled) {
                localStorage.setItem(key, '1');
            } else {
                localStorage.removeItem(key);
            }
        } catch (e) {
            // no-op
        }
    }

    function refreshClockInSetupDeferredElements() {
        clockInSetupModal = document.getElementById('clockInSetupModal');
        clockInSetupCloseBtn = document.getElementById('clockInSetupCloseBtn');
        clockInSetupDownloadBtn = document.getElementById('clockInSetupDownloadBtn');
        clockInSetupDownloadCard = document.getElementById('clockInSetupDownloadCard');
        clockInSetupDownloadIcon = document.getElementById('clockInSetupDownloadIcon');
        clockInSetupDownloadTitle = document.getElementById('clockInSetupDownloadTitle');
        clockInSetupDownloadText = document.getElementById('clockInSetupDownloadText');
        clockInSetupStatusCard = document.getElementById('clockInSetupStatusCard');
        clockInSetupStatusCheck = document.getElementById('clockInSetupStatusCheck');
        clockInSetupStatusTitle = document.getElementById('clockInSetupStatusTitle');
        clockInSetupStatusText = document.getElementById('clockInSetupStatusText');
        clockInSetupPrimaryBtn = document.getElementById('clockInSetupPrimaryBtn');
        clockInSetupDismissHoverBtn = document.getElementById('clockInSetupDismissHoverBtn');
        clockInGuideTabButtons = Array.prototype.slice.call(document.querySelectorAll('[data-clockin-tab-button]'));
        clockInGuidePanels = Array.prototype.slice.call(document.querySelectorAll('[data-clockin-panel]'));
        clockInGuideVideoShells = Array.prototype.slice.call(document.querySelectorAll('[data-clockin-video-shell]'));
        clockInGuideSlideshows = Array.prototype.slice.call(document.querySelectorAll('[data-clockin-slideshow]'));
    }

    refreshClockInSetupDeferredElements();

    function isClockInSetupLocked() {
        return !hasActiveAttendance && !isCaptureExtensionAvailable;
    }

    function syncClockInGuideVideoState(shell) {
        if (!shell) return;
        var video = shell.querySelector('[data-clockin-video]');
        if (!video) return;
        shell.classList.toggle('is-playing', !video.paused && !video.ended);
    }

    function pauseClockInGuideVideo(scope) {
        clockInGuideVideoShells.forEach(function (shell) {
            var shellScope = shell.parentNode && shell.parentNode.getAttribute('data-clockin-scope');
            if (scope && shellScope !== scope) return;
            var video = shell.querySelector('[data-clockin-video]');
            if (video && !video.paused) {
                video.pause();
            }
            syncClockInGuideVideoState(shell);
        });
    }

    function playClockInGuideVideo(scope) {
        clockInGuideVideoShells.forEach(function (shell) {
            var shellScope = shell.parentNode && shell.parentNode.getAttribute('data-clockin-scope');
            if (scope && shellScope !== scope) return;
            var video = shell.querySelector('[data-clockin-video]');
            if (!video) return;
            if (video.ended) {
                video.currentTime = 0;
            }
            var playPromise = video.play();
            if (playPromise && typeof playPromise.then === 'function') {
                playPromise.then(function () {
                    syncClockInGuideVideoState(shell);
                }).catch(function () {
                    syncClockInGuideVideoState(shell);
                });
                return;
            }
            syncClockInGuideVideoState(shell);
        });
    }

    function toggleClockInGuideVideo(shell) {
        if (!shell) return;
        var video = shell.querySelector('[data-clockin-video]');
        if (!video) return;
        if (video.paused || video.ended) {
            if (video.ended) {
                video.currentTime = 0;
            }
            var playPromise = video.play();
            if (playPromise && typeof playPromise.then === 'function') {
                playPromise.then(function () {
                    syncClockInGuideVideoState(shell);
                }).catch(function () {
                    syncClockInGuideVideoState(shell);
                });
                return;
            }
            syncClockInGuideVideoState(shell);
            return;
        }
        video.pause();
        syncClockInGuideVideoState(shell);
    }

    function ensureClockInGuideSlideTimer() {
        if (clockInGuideSlideTimer) {
            clearInterval(clockInGuideSlideTimer);
        }
        clockInGuideSlideTimer = setInterval(function () {
            clockInGuideSlideIndex = (clockInGuideSlideIndex + 1) % clockInGuideSteps.length;
            renderClockInGuideSlides();
        }, 3500);
    }

    function buildClockInGuideDots(container) {
        if (!container) return;
        container.innerHTML = '';
        clockInGuideSteps.forEach(function (step, index) {
            var dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'clockin-guide-slide-dot' + (index === clockInGuideSlideIndex ? ' is-active' : '');
            dot.setAttribute('aria-label', 'Show clock-in setup step ' + String(index + 1));
            dot.addEventListener('click', function () {
                clockInGuideSlideIndex = index;
                renderClockInGuideSlides();
                ensureClockInGuideSlideTimer();
            });
            container.appendChild(dot);
        });
    }

    function renderClockInGuideSlides() {
        var step = clockInGuideSteps[clockInGuideSlideIndex];
        clockInGuideSlideshows.forEach(function (slideshow) {
            var iconEl = slideshow.querySelector('.clockin-guide-slide-icon');
            var labelEl = slideshow.querySelector('.clockin-guide-slide-label');
            var descEl = slideshow.querySelector('.clockin-guide-slide-desc');
            var counterEl = slideshow.querySelector('.clockin-guide-slide-counter');
            if (iconEl) {
                iconEl.innerHTML = '<i class="fa ' + step.iconClass + '"></i>';
            }
            if (labelEl) {
                labelEl.textContent = step.label;
            }
            if (descEl) {
                descEl.textContent = step.desc;
            }
            if (counterEl) {
                counterEl.textContent = String(clockInGuideSlideIndex + 1) + '/' + String(clockInGuideSteps.length);
            }

            var size = slideshow.getAttribute('data-clockin-slideshow') || '';
            var dots = document.querySelector('[data-clockin-slide-dots="' + size + '"]');
            buildClockInGuideDots(dots);
        });
    }

    function setClockInGuideTab(tabName) {
        clockInGuideTab = tabName === 'slides' ? 'slides' : 'video';
        clockInGuideTabButtons.forEach(function (button) {
            var isActive = button.getAttribute('data-clockin-tab-button') === clockInGuideTab;
            button.classList.toggle('is-active', isActive);
        });
        clockInGuidePanels.forEach(function (panel) {
            var isActive = panel.getAttribute('data-clockin-panel') === clockInGuideTab;
            panel.classList.toggle('is-active', isActive);
        });

        if (clockInGuideTab === 'video') {
            if (clockInSetupAnchor && clockInSetupAnchor.classList.contains('is-hover-guide-visible')) {
                playClockInGuideVideo('compact');
            }
            if (clockInSetupModal && !clockInSetupModal.hidden) {
                playClockInGuideVideo('full');
            }
        } else {
            pauseClockInGuideVideo('compact');
            pauseClockInGuideVideo('full');
        }
    }

    function syncClockInSetupDownloadCard() {
        if (!clockInSetupDownloadCard) return;
        clockInSetupDownloadCard.classList.toggle('is-downloaded', clockInExtensionDownloaded);
        if (clockInSetupDownloadIcon) {
            clockInSetupDownloadIcon.className = 'fa ' + (clockInExtensionDownloaded ? 'fa-check' : 'fa-archive');
        }
        if (clockInSetupDownloadTitle) {
            clockInSetupDownloadTitle.textContent = clockInExtensionDownloaded
                ? 'Extension package downloaded'
                : 'TaskFlow Screen Capture Extension';
        }
        if (clockInSetupDownloadText) {
            clockInSetupDownloadText.textContent = clockInExtensionDownloaded
                ? 'Next: extract the zip, open chrome://extensions, then load the folder as an unpacked extension.'
                : 'Download the zip file first, then load the extracted folder in Chrome.';
        }
        if (clockInSetupDownloadBtn) {
            clockInSetupDownloadBtn.textContent = clockInExtensionDownloaded ? 'Download Again' : 'Download';
        }
    }

    function syncClockInSetupStatusCard() {
        if (!clockInSetupStatusCard) return;
        clockInSetupStatusCard.classList.toggle('is-ready', isCaptureExtensionAvailable);
        if (clockInSetupStatusTitle) {
            clockInSetupStatusTitle.textContent = isCaptureExtensionAvailable
                ? 'Extension detected'
                : 'Extension not detected yet';
        }
        if (clockInSetupStatusText) {
            clockInSetupStatusText.textContent = isCaptureExtensionAvailable
                ? 'This page can see the screen capture extension. Clock In is unlocked now.'
                : 'Load it unpacked in chrome://extensions, then refresh this page to unlock Clock In.';
        }
        if (clockInSetupStatusCheck) {
            clockInSetupStatusCheck.innerHTML = '<i class="fa ' + (isCaptureExtensionAvailable ? 'fa-check' : 'fa-refresh') + '"></i>';
        }
        if (clockInSetupPrimaryBtn) {
            clockInSetupPrimaryBtn.textContent = isCaptureExtensionAvailable
                ? 'Clock In Is Now Unlocked'
                : 'Refresh Page After Install';
        }
    }

    function closeClockInSetupHover() {
        if (!clockInSetupAnchor) return;
        if (clockInSetupHoverHideTimer) {
            clearTimeout(clockInSetupHoverHideTimer);
            clockInSetupHoverHideTimer = null;
        }
        clockInSetupAnchor.classList.remove('is-hover-guide-visible');
        if (clockInSetupHover) {
            clockInSetupHover.setAttribute('aria-hidden', 'true');
        }
        pauseClockInGuideVideo('compact');
    }

    function openClockInSetupHover() {
        if (!clockInSetupAnchor || !clockInSetupHover) return;
        if (clockInGuideHoverDisabled || !isClockInSetupLocked()) return;
        if (window.matchMedia && !window.matchMedia('(hover: hover)').matches) return;
        if (clockInSetupModal && !clockInSetupModal.hidden) return;
        if (clockInSetupHoverHideTimer) {
            clearTimeout(clockInSetupHoverHideTimer);
            clockInSetupHoverHideTimer = null;
        }
        clockInSetupAnchor.classList.add('is-hover-guide-visible');
        clockInSetupHover.setAttribute('aria-hidden', 'false');
        if (clockInGuideTab === 'video') {
            playClockInGuideVideo('compact');
        }
    }

    function scheduleClockInSetupHoverClose() {
        if (!clockInSetupAnchor) return;
        if (clockInSetupHoverHideTimer) {
            clearTimeout(clockInSetupHoverHideTimer);
        }
        clockInSetupHoverHideTimer = setTimeout(function () {
            closeClockInSetupHover();
        }, 180);
    }

    function openClockInSetupModal(preferredTab) {
        if (!clockInSetupModal) return;
        if (preferredTab) {
            setClockInGuideTab(preferredTab);
        }
        clockInSetupModal.hidden = false;
        document.body.classList.add('is-clockin-setup-modal-open');
        closeClockInSetupHover();
        syncClockInSetupDownloadCard();
        syncClockInSetupStatusCard();
        if (clockInGuideTab === 'video') {
            playClockInGuideVideo('full');
        }
    }

    function closeClockInSetupModal() {
        if (!clockInSetupModal) return;
        clockInSetupModal.hidden = true;
        document.body.classList.remove('is-clockin-setup-modal-open');
        pauseClockInGuideVideo('full');
    }

    function hideClockInSetupHoverGuide() {
        clockInGuideHoverDisabled = true;
        setStoredClockInGuideFlag(clockInGuideHoverDisabledKey, true);
        closeClockInSetupHover();
    }

    function syncClockInSetupUi() {
        var locked = isClockInSetupLocked();
        if (clockInSetupBanner) {
            clockInSetupBanner.hidden = !locked;
        }
        if (btnIn) {
            btnIn.classList.toggle('is-locked', locked);
            btnIn.disabled = !!isClockInRequestInProgress;
        }
        if (btnInLabel) {
            btnInLabel.textContent = isClockInRequestInProgress ? 'Clocking in...' : 'Clock In';
        }
        if (btnInIcon) {
            btnInIcon.className = 'fa ' + (isClockInRequestInProgress ? 'fa-spinner fa-spin' : 'fa-play');
        }
        if (btnInLockNote) {
            btnInLockNote.style.display = locked ? 'inline-flex' : 'none';
        }
        syncClockInSetupDownloadCard();
        syncClockInSetupStatusCard();
        if (!locked) {
            closeClockInSetupHover();
        }
    }

    function setCaptureExtensionAvailability(isAvailable) {
        isCaptureExtensionAvailable = !!isAvailable;
        syncClockInSetupUi();
    }

    function markClockInNavWarningSeen() {
        hasSeenClockInNavWarning = true;
        try {
            sessionStorage.setItem(clockInNavWarningKey, '1');
        } catch (e) {
            // no-op
        }
    }

    function parseServerTimeToMs(value) {
        if (!value) return 0;
        var raw = String(value).trim();
        if (!raw) return 0;
        var ms = Date.parse(raw);
        if (!isNaN(ms)) return ms;
        ms = Date.parse(raw.replace(' ', 'T'));
        return isNaN(ms) ? 0 : ms;
    }

    function setLastCaptureHeartbeat(ms, updateActivityClock) {
        var parsedMs = Number(ms);
        if (!isFinite(parsedMs) || parsedMs <= 0) return;
        if (parsedMs > lastCaptureHeartbeatAt) {
            lastCaptureHeartbeatAt = parsedMs;
        }
        if (!updateActivityClock) return;
        if ((Date.now() - parsedMs) > captureHeartbeatFreshMs) return;
        if (!hasFreshCaptureInputState()) return;
        if (lastCaptureInputThresholdReached) return;
        if (lastCaptureInputState !== 'active') return;
        lastDashboardActivityAt = Math.max(lastDashboardActivityAt, parsedMs);
    }

    function getCaptureHeartbeatFromStorage(rawValue) {
        var raw = rawValue;
        if (typeof raw !== 'string') {
            try {
                raw = localStorage.getItem(captureHeartbeatStorageKey);
            } catch (e) {
                return null;
            }
        }
        if (!raw) return null;
        try {
            var payload = JSON.parse(raw);
            if (!payload || !payload.ts) return null;
            var heartbeatTs = Number(payload.ts);
            if (!isFinite(heartbeatTs) || heartbeatTs <= 0) return null;
            var heartbeatAttendanceId = payload.attendance_id != null ? Number(payload.attendance_id) : null;
            if (attendanceId && heartbeatAttendanceId && Number(attendanceId) !== heartbeatAttendanceId) {
                return null;
            }
            return {
                ts: heartbeatTs,
                attendance_id: heartbeatAttendanceId
            };
        } catch (e) {
            return null;
        }
    }

    function refreshCaptureHeartbeatFromStorage(updateActivityClock) {
        var heartbeat = getCaptureHeartbeatFromStorage();
        if (!heartbeat) return;
        setLastCaptureHeartbeat(heartbeat.ts, !!updateActivityClock);
    }

    function syncHeartbeatFromAttendancePayload(payload) {
        if (!payload) return;
        if (payload.last_heartbeat_at) {
            setLastCaptureHeartbeat(parseServerTimeToMs(payload.last_heartbeat_at), true);
        }
        if (payload.heartbeat_age_seconds !== null && payload.heartbeat_age_seconds !== undefined) {
            var ageSeconds = Number(payload.heartbeat_age_seconds);
            if (isFinite(ageSeconds) && ageSeconds >= 0) {
                setLastCaptureHeartbeat(Date.now() - (ageSeconds * 1000), true);
            }
        }
    }

    function hasFreshCaptureHeartbeat() {
        if (!hasActiveAttendance) return false;
        refreshCaptureHeartbeatFromStorage(true);
        if (!lastCaptureHeartbeatAt) return false;
        return (Date.now() - lastCaptureHeartbeatAt) <= captureHeartbeatFreshMs;
    }

    function setLastCaptureInputState(state, ts, thresholdReached, updateActivityClock) {
        var parsedTs = Number(ts);
        if (!isFinite(parsedTs) || parsedTs <= 0) return;
        if (parsedTs < lastCaptureInputStateAt) return;

        lastCaptureInputStateAt = parsedTs;
        lastCaptureInputState = (state ? String(state) : 'unknown').toLowerCase();
        lastCaptureInputThresholdReached = !!thresholdReached;

        if (updateActivityClock && !lastCaptureInputThresholdReached && lastCaptureInputState === 'active') {
            if ((Date.now() - parsedTs) <= captureInputStateFreshMs) {
                lastDashboardActivityAt = Math.max(lastDashboardActivityAt, parsedTs);
            }
        }
    }

    function getCaptureInputStateFromStorage(rawValue) {
        var raw = rawValue;
        if (typeof raw !== 'string') {
            try {
                raw = localStorage.getItem(captureInputStateStorageKey);
            } catch (e) {
                return null;
            }
        }
        if (!raw) return null;
        try {
            var payload = JSON.parse(raw);
            if (!payload || !payload.ts) return null;
            var inputTs = Number(payload.ts);
            if (!isFinite(inputTs) || inputTs <= 0) return null;
            var inputAttendanceId = payload.attendance_id != null ? Number(payload.attendance_id) : null;
            if (attendanceId && inputAttendanceId && Number(attendanceId) !== inputAttendanceId) {
                return null;
            }
            var state = payload.state ? String(payload.state).toLowerCase() : 'unknown';
            var thresholdReachedRaw = payload.threshold_reached;
            var thresholdReached = thresholdReachedRaw === true || thresholdReachedRaw === 1 || thresholdReachedRaw === '1';
            return {
                ts: inputTs,
                attendance_id: inputAttendanceId,
                state: state,
                threshold_reached: thresholdReached
            };
        } catch (e) {
            return null;
        }
    }

    function refreshCaptureInputStateFromStorage(updateActivityClock) {
        var inputState = getCaptureInputStateFromStorage();
        if (!inputState) return;
        setLastCaptureInputState(inputState.state, inputState.ts, inputState.threshold_reached, !!updateActivityClock);
    }

    function hasFreshCaptureInputState() {
        refreshCaptureInputStateFromStorage(true);
        if (!lastCaptureInputStateAt) return false;
        return (Date.now() - lastCaptureInputStateAt) <= captureInputStateFreshMs;
    }

    function isCaptureInputIdle() {
        if (!hasFreshCaptureInputState()) return false;
        if (lastCaptureInputThresholdReached) return true;
        return lastCaptureInputState === 'locked';
    }

    function canTreatCaptureAsActive() {
        if (!hasFreshCaptureHeartbeat()) return false;
        if (!hasFreshCaptureInputState()) return false;
        if (lastCaptureInputThresholdReached) return false;
        return lastCaptureInputState === 'active';
    }

    // Toggle button visibility based on state
    function updateButtonState(isTimedIn) {
        hasActiveAttendance = !!isTimedIn;
        if (!btnIn || !btnOut) return;
        if (isTimedIn) {
            closeClockInSetupHover();
            closeClockInSetupModal();
            btnIn.style.display = 'none';
            btnOut.style.display = 'flex';
            btnOut.style.marginTop = '0px'; 
            btnOut.innerHTML = '<i class="fa fa-pause"></i> Clock Out/Pause';
            btnOut.disabled = false;
        } else {
            lastCaptureHeartbeatAt = 0;
            lastCaptureInputState = 'unknown';
            lastCaptureInputStateAt = 0;
            lastCaptureInputThresholdReached = false;
            console.log("Resetting to Clock In state");
            isClockInRequestInProgress = false;
            btnIn.style.display = 'flex';
            btnOut.style.display = 'none';
            syncClockInSetupUi();
        }
    }

    // Simple AJAX helper
    function ajax(url, data, cb, method) {
        var xhr = new XMLHttpRequest();
        var useMethod = method || 'POST';
        xhr.open(useMethod, url, true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        cb(JSON.parse(xhr.responseText));
                    } catch (e) {
                        cb({status: 'error', message: 'Invalid JSON response', raw: xhr.responseText});
                    }
                } else {
                    cb({status: 'error', message: 'Network error', statusCode: xhr.status, raw: xhr.responseText});
                }
            }
        };
        if (useMethod === 'POST') {
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send(data);
        } else {
            xhr.send();
        }
    }

    // Listen for messages from capture window
    window.addEventListener('message', function(event) {
        // Only accept from same origin
        if (event.origin !== window.location.origin) return;
        if (event.data && event.data.type === 'CAPTURE_BEFORE_LOGOUT_DONE') {
            if (pendingIdleCaptureResolve) {
                var resolveFn = pendingIdleCaptureResolve;
                pendingIdleCaptureResolve = null;
                if (pendingIdleCaptureTimer) {
                    clearTimeout(pendingIdleCaptureTimer);
                    pendingIdleCaptureTimer = null;
                }
                resolveFn(!!event.data.success);
            }
            return;
        }
        if (!statusSpan) return;
        
        if (event.data.type === 'CAPTURE_STARTED') {
            statusSpan.textContent = 'Timed in. Screen capture active.';
            statusSpan.className = '';
            statusSpan.style.color = ''; // Reset color
        } else if (event.data.type === 'CAPTURE_STOPPED') {
            statusSpan.textContent = 'Screen capture stopped.';
            if (!isManualClockOutInProgress && (!event.data.reason || event.data.reason !== 'attendance_ended')) {
                autoClockOutDueToCaptureIssue('Screen sharing stopped. You have been clocked out.');
            }
        } else if (event.data.type === 'CAPTURE_ERROR') {
             autoClockOutDueToCaptureIssue('Screen share denied/canceled. You have been clocked out.');
        }
    });

    window.addEventListener('storage', function (event) {
        if (event.key === captureHeartbeatStorageKey && event.newValue) {
            var heartbeat = getCaptureHeartbeatFromStorage(event.newValue);
            if (!heartbeat) return;
            setLastCaptureHeartbeat(heartbeat.ts, true);
            if (isIdleCheckModalOpen && canTreatCaptureAsActive()) {
                closeIdleCheckModal();
                return;
            }
            if (hasActiveAttendance && !isIdleCheckModalOpen && !isIdleLogoutInProgress) {
                startIdleCheckTimer();
            }
            return;
        }

        if (event.key === captureInputStateStorageKey && event.newValue) {
            var inputState = getCaptureInputStateFromStorage(event.newValue);
            if (!inputState) return;
            setLastCaptureInputState(inputState.state, inputState.ts, inputState.threshold_reached, true);
            if (isIdleCheckModalOpen && canTreatCaptureAsActive()) {
                closeIdleCheckModal();
                return;
            }
            if (hasActiveAttendance && !isIdleCheckModalOpen && !isIdleLogoutInProgress) {
                startIdleCheckTimer();
            }
        }
    });

    function autoClockOutDueToCaptureIssue(message) {
        var fallbackMessage = 'You were clocked out because screen sharing was canceled or stopped.';
        if (isAutoClockOutInProgress || isManualClockOutInProgress) return;
        if (!hasActiveAttendance && !attendanceId) {
            return;
        }

        isAutoClockOutInProgress = true;
        if (statusSpan) statusSpan.textContent = 'Clocking out...';

        ajax('time_out.php', 'csrf_token=' + encodeURIComponent(attendanceAjaxCsrfToken), function (res) {
            attendanceId = null;
            var autoMessage = (res && res.status === 'success') ? fallbackMessage : message;
            setClockedOutUI();
            openAutoClockOutModal(autoMessage);

            var now = new Date();
            var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
            var elOut = document.getElementById('statTimeOut');
            if (elOut) elOut.innerText = timeStr;

            isAutoClockOutInProgress = false;
        });
    }

    function downloadCaptureExtensionPackage() {
        var downloadLink = document.createElement('a');
        downloadLink.href = 'extension.zip';
        downloadLink.download = 'taskflow-screen-capture-extension.zip';
        downloadLink.rel = 'noopener';
        downloadLink.style.display = 'none';
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
        clockInExtensionDownloaded = true;
        setStoredClockInGuideFlag(clockInExtensionDownloadKey, true);
        syncClockInSetupDownloadCard();
        if (statusSpan && !hasActiveAttendance) {
            statusSpan.textContent = 'Extension download started. Install it, then refresh this page.';
            statusSpan.style.color = '#B45309';
        }
    }

    // Clock In Handler
    if (btnIn) {
        btnIn.addEventListener('click', async function () {
            if (isClockInSetupLocked()) {
                openClockInSetupModal('video');
                return;
            }

            requestIdleNotificationPermission();
            isClockInRequestInProgress = true;
            syncClockInSetupUi();
            statusSpan.textContent = 'Clocking in...';
            statusSpan.style.color = ''; // Reset color
            
            ajax('time_in.php', 'csrf_token=' + encodeURIComponent(attendanceAjaxCsrfToken), function (res) {
                if (res.status === 'success') {
                    attendanceId = res.attendance_id || null;
                    hasActiveAttendance = true;
                    isClockInRequestInProgress = false;
                    setLastCaptureHeartbeat(Date.now(), true);
                    setLastCaptureInputState('active', Date.now(), false, true);
                    
                    // Instant UI Update
                    var now = new Date();
                    var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
                    var el = document.getElementById('statTimeIn');
                    if(el) el.innerText = timeStr;
                    var elOut = document.getElementById('statTimeOut');
                    if(elOut) elOut.innerText = '--:--';
                    
                    // Open capture window
                    // Width/Height small, bottom right or minimized
                    const width = 400;
                    const height = 300;
                    const left = screen.width - width;
                    const top = screen.height - height;
                    
                    captureWindow = window.open(
                        'capture.html?attendanceId=' + encodeURIComponent(attendanceId) + '&userId=' + encodeURIComponent(currentUserId) + '&csrf_token=' + encodeURIComponent(attendanceAjaxCsrfToken),
                        'TaskFlowCapture',
                        'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top
                    );

                    updateButtonState(true);
                } else {
                    isClockInRequestInProgress = false;
                    statusSpan.textContent = res.message || 'Error during time in';
                    statusSpan.style.color = '#EF4444';
                    syncClockInSetupUi();
                }
            });
        });
    }

    // Clock Out Handler
    if (btnOut) {
        btnOut.addEventListener('click', function () {
            // Show Confirmation Modal
            document.getElementById('confirmModal').style.display = 'flex';
        });
    }
    
    // Actual Clock Out Logic
    function confirmClockOut() {
        document.getElementById('confirmModal').style.display = 'none';
        isManualClockOutInProgress = true;
        
        btnOut.disabled = true;
        statusSpan.textContent = 'Clocking out...';
        statusSpan.style.color = ''; // Reset color

        // Signal other tabs/windows (including capture.html) to stop immediately.
        signalCaptureStop('manual_clock_out');
        
        // Close capture window
        if (captureWindow && !captureWindow.closed) {
            captureWindow.close();
        }
        
        // Then record time out
        ajax('time_out.php', 'csrf_token=' + encodeURIComponent(attendanceAjaxCsrfToken), function (res) {
            if (res.status === 'success') {
                statusSpan.textContent = 'Timed out. Session ended.';
                attendanceId = null;
                hasActiveAttendance = false;
                updateButtonState(false);
                
                // Instant UI Update
                var now = new Date();
                var timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
                var elOut = document.getElementById('statTimeOut');
                if(elOut) elOut.innerText = timeStr;
                
            } else {
                statusSpan.textContent = res.message || 'Error during time out';
                statusSpan.style.color = '#EF4444';
                btnOut.disabled = false;
            }
            isManualClockOutInProgress = false;
        });
    }
    
    function closeConfirmModal() {
        document.getElementById('confirmModal').style.display = 'none';
    }

    function setClockedOutUI(message, isError) {
        attendanceId = null;
        hasActiveAttendance = false;
        lastCaptureHeartbeatAt = 0;
        lastCaptureInputState = 'unknown';
        lastCaptureInputStateAt = 0;
        lastCaptureInputThresholdReached = false;
        updateButtonState(false);
        if (statusSpan) {
            statusSpan.textContent = message || 'Timed out. Session ended.';
            statusSpan.className = isError ? 'status-error' : '';
            statusSpan.style.color = isError ? '#EF4444' : '';
        }
    }

    function signalCaptureStop(reason) {
        try {
            localStorage.setItem('taskflow_force_stop_capture', JSON.stringify({
                ts: Date.now(),
                reason: reason || 'clock_out'
            }));
            setTimeout(function () {
                localStorage.removeItem('taskflow_force_stop_capture');
            }, 1000);
        } catch (e) {
            // no-op
        }
    }

    function startIdleCheckTimer() {
        if (window.__taskflowSharedIdleEnabled) return;
        if (idleCheckTimer) {
            clearTimeout(idleCheckTimer);
        }
        if (isIdleCheckModalOpen || isIdleLogoutInProgress) return;
        if (hasFreshCaptureHeartbeat() && isCaptureInputIdle()) {
            openIdleCheckModal();
            return;
        }
        if (canTreatCaptureAsActive()) {
            var heartbeatAgeMs = Math.max(0, Date.now() - lastCaptureHeartbeatAt);
            var untilHeartbeatStaleMs = Math.max(1000, captureHeartbeatFreshMs - heartbeatAgeMs + 500);
            var inputAgeMs = lastCaptureInputStateAt ? Math.max(0, Date.now() - lastCaptureInputStateAt) : captureInputStateFreshMs;
            var untilInputRefreshMs = Math.max(1000, captureInputStateFreshMs - inputAgeMs + 500);
            var untilStaleMs = Math.min(untilHeartbeatStaleMs, untilInputRefreshMs);
            idleCheckTimer = setTimeout(function () {
                startIdleCheckTimer();
            }, untilStaleMs);
            return;
        }
        var elapsedMs = Date.now() - lastDashboardActivityAt;
        var remainingMs = idleCheckThresholdMs - elapsedMs;
        if (remainingMs <= 0) {
            openIdleCheckModal();
            return;
        }
        idleCheckTimer = setTimeout(function () {
            openIdleCheckModal();
        }, remainingMs);
    }

    function updateIdleCheckCountdownLabel() {
        var countdownLabel = document.getElementById('idleCheckCountdown');
        if (countdownLabel) {
            countdownLabel.textContent = String(Math.max(0, idleCheckSecondsRemaining));
        }
        updateIdleAlertIndicators();
    }

    function closeIdleWarningNotification() {
        if (!idleWarningNotification) return;
        try {
            idleWarningNotification.close();
        } catch (e) {
            // no-op
        }
        idleWarningNotification = null;
    }

    function updateIdleAlertIndicators() {
        if (isIdleCheckModalOpen && document.hidden) {
            document.title = 'TaskFlow: Confirm (' + String(Math.max(0, idleCheckSecondsRemaining)) + 's)';
            return;
        }
        document.title = defaultDocumentTitle;
    }

    function requestIdleNotificationPermission() {
        if (!isEmployeeUser) return;
        if (!('Notification' in window)) return;
        if (Notification.permission !== 'default') return;
        var now = Date.now();
        if ((now - lastIdleNotificationPermissionRequestAt) < idleNotificationPermissionRequestCooldownMs) return;
        lastIdleNotificationPermissionRequestAt = now;
        try {
            var permissionPromise = Notification.requestPermission();
            if (permissionPromise && typeof permissionPromise.catch === 'function') {
                permissionPromise.catch(function () {
                    // no-op
                });
            }
        } catch (e) {
            // no-op
        }
    }

    function notifyIdleWhileHidden() {
        updateIdleAlertIndicators();
        if (!document.hidden) return;
        if (!('Notification' in window)) return;
        if (Notification.permission !== 'granted') return;
        closeIdleWarningNotification();
        try {
            idleWarningNotification = new Notification('TaskFlow idle warning', {
                body: 'Confirm within ' + String(idleCheckCountdownStartSeconds) + ' seconds or you will be logged out.',
                tag: 'taskflow-idle-warning',
                requireInteraction: true
            });
            idleWarningNotification.onclick = function () {
                window.focus();
                closeIdleWarningNotification();
            };
        } catch (e) {
            // no-op
        }
    }

    function stopIdleCheckCountdown() {
        if (idleCheckCountdownTimer) {
            clearInterval(idleCheckCountdownTimer);
            idleCheckCountdownTimer = null;
        }
    }

    function requestCaptureBeforeIdleLogout() {
        return new Promise(function (resolve) {
            if (!captureWindow || captureWindow.closed) {
                resolve(false);
                return;
            }
            if (pendingIdleCaptureResolve) {
                var previousResolve = pendingIdleCaptureResolve;
                pendingIdleCaptureResolve = null;
                if (pendingIdleCaptureTimer) {
                    clearTimeout(pendingIdleCaptureTimer);
                    pendingIdleCaptureTimer = null;
                }
                previousResolve(false);
            }
            pendingIdleCaptureResolve = resolve;
            pendingIdleCaptureTimer = setTimeout(function () {
                if (!pendingIdleCaptureResolve) return;
                var timeoutResolve = pendingIdleCaptureResolve;
                pendingIdleCaptureResolve = null;
                pendingIdleCaptureTimer = null;
                timeoutResolve(false);
            }, 10000);
            try {
                captureWindow.postMessage({ type: 'CAPTURE_NOW_BEFORE_LOGOUT' }, window.location.origin);
            } catch (e) {
                if (pendingIdleCaptureTimer) {
                    clearTimeout(pendingIdleCaptureTimer);
                    pendingIdleCaptureTimer = null;
                }
                pendingIdleCaptureResolve = null;
                resolve(false);
            }
        });
    }

    async function logoutFromIdleTimeout() {
        if (isIdleLogoutInProgress) return;
        isIdleLogoutInProgress = true;
        stopIdleCheckCountdown();
        isIdleCheckModalOpen = false;
        closeIdleWarningNotification();
        updateIdleAlertIndicators();
        if (idleCheckTimer) {
            clearTimeout(idleCheckTimer);
            idleCheckTimer = null;
        }
        await requestCaptureBeforeIdleLogout();
        signalCaptureStop('idle_logout');
        setTimeout(function () {
            window.location.href = 'logout.php';
        }, 700);
    }

    function startIdleCheckCountdown() {
        stopIdleCheckCountdown();
        idleCheckSecondsRemaining = idleCheckCountdownStartSeconds;
        updateIdleCheckCountdownLabel();
        idleCheckCountdownTimer = setInterval(function () {
            if (canTreatCaptureAsActive()) {
                closeIdleCheckModal();
                return;
            }
            idleCheckSecondsRemaining -= 1;
            updateIdleCheckCountdownLabel();
            if (idleCheckSecondsRemaining <= 0) {
                logoutFromIdleTimeout();
            }
        }, 1000);
    }

    function openIdleCheckModal() {
        if (window.__taskflowSharedIdleEnabled) return;
        const modal = document.getElementById('idleCheckModal');
        if (!modal || isIdleCheckModalOpen) return;
        if (idleCheckTimer) {
            clearTimeout(idleCheckTimer);
            idleCheckTimer = null;
        }
        isIdleCheckModalOpen = true;
        modal.style.display = 'flex';
        startIdleCheckCountdown();
        notifyIdleWhileHidden();
    }

    function closeIdleCheckModal() {
        const modal = document.getElementById('idleCheckModal');
        if (modal) modal.style.display = 'none';
        stopIdleCheckCountdown();
        closeIdleWarningNotification();
        isIdleCheckModalOpen = false;
        idleCheckSecondsRemaining = idleCheckCountdownStartSeconds;
        updateIdleCheckCountdownLabel();
        lastDashboardActivityAt = Date.now();
        startIdleCheckTimer();
    }

    function onDashboardUserActivity() {
        requestIdleNotificationPermission();
        if (isIdleCheckModalOpen) return;
        lastDashboardActivityAt = Date.now();
        startIdleCheckTimer();
    }

    function setupIdleCheckPrompt() {
        if (!isEmployeeUser) return;
        const activityEvents = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart'];
        activityEvents.forEach(function (eventName) {
            document.addEventListener(eventName, onDashboardUserActivity, true);
        });

        document.addEventListener('visibilitychange', function () {
            updateIdleAlertIndicators();
            if (document.hidden && isIdleCheckModalOpen) {
                notifyIdleWhileHidden();
            }
            if (!document.hidden && !isIdleCheckModalOpen) {
                startIdleCheckTimer();
            }
            if (!document.hidden) {
                closeIdleWarningNotification();
            }
        });

        refreshCaptureHeartbeatFromStorage(true);
        refreshCaptureInputStateFromStorage(true);
        lastDashboardActivityAt = Date.now();
        startIdleCheckTimer();
    }

    // On page load, check for active attendance
    if (btnIn && btnOut) {
        ajax('check_attendance.php', '', function (res) {
            if (res.status === 'success' && res.has_active_attendance) {
                attendanceId = res.attendance_id || null;
                syncHeartbeatFromAttendancePayload(res);
                refreshCaptureHeartbeatFromStorage(true);
                refreshCaptureInputStateFromStorage(true);
                
                // Always show Timed In state (Clock Out button) if DB says we are active.
                // This persists across page refreshes/navigation.
                updateButtonState(true);
                statusSpan.textContent = 'Timed in. Monitoring active.';
            } else if (res.status === 'success') {
                setClockedOutUI();
            }
        }, 'GET');
    }

    if (window.screenshotExtensionAvailable) {
        setCaptureExtensionAvailability(true);
    }

    window.addEventListener('screenshotExtensionReady', function () {
        setCaptureExtensionAvailability(true);
    });

    function initClockInSetupBindings() {
        if (hasClockInSetupBindingsInitialized) return;
        hasClockInSetupBindingsInitialized = true;
        refreshClockInSetupDeferredElements();

        clockInGuideVideoShells.forEach(function (shell) {
            var toggle = shell.querySelector('[data-clockin-video-toggle]');
            var pause = shell.querySelector('[data-clockin-video-pause]');
            var video = shell.querySelector('[data-clockin-video]');
            if (toggle) {
                toggle.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleClockInGuideVideo(shell);
                });
            }
            if (pause) {
                pause.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (video) {
                        video.pause();
                    }
                    syncClockInGuideVideoState(shell);
                });
            }
            if (video) {
                video.addEventListener('click', function (e) {
                    e.preventDefault();
                    toggleClockInGuideVideo(shell);
                });
                video.addEventListener('play', function () {
                    syncClockInGuideVideoState(shell);
                });
                video.addEventListener('pause', function () {
                    syncClockInGuideVideoState(shell);
                });
                video.addEventListener('ended', function () {
                    syncClockInGuideVideoState(shell);
                });
                syncClockInGuideVideoState(shell);
            }
        });

        clockInGuideTabButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                setClockInGuideTab(button.getAttribute('data-clockin-tab-button'));
            });
        });

        clockInGuideSlideshows.forEach(function (slideshow) {
            var navButtons = Array.prototype.slice.call(slideshow.querySelectorAll('[data-clockin-slide-nav]'));
            navButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    var dir = Number(button.getAttribute('data-clockin-slide-nav') || '0');
                    if (!dir) return;
                    clockInGuideSlideIndex = (clockInGuideSlideIndex + dir + clockInGuideSteps.length) % clockInGuideSteps.length;
                    renderClockInGuideSlides();
                    ensureClockInGuideSlideTimer();
                });
            });
        });

        if (clockInSetupAnchor) {
            clockInSetupAnchor.addEventListener('mouseenter', openClockInSetupHover);
            clockInSetupAnchor.addEventListener('mouseleave', scheduleClockInSetupHoverClose);
            clockInSetupAnchor.addEventListener('focusin', openClockInSetupHover);
            clockInSetupAnchor.addEventListener('focusout', function (e) {
                if (!e.relatedTarget || !clockInSetupAnchor.contains(e.relatedTarget)) {
                    scheduleClockInSetupHoverClose();
                }
            });
        }

        if (clockInSetupBannerBtn) {
            clockInSetupBannerBtn.addEventListener('click', function () {
                openClockInSetupModal('video');
            });
        }

        if (clockInSetupOpenGuideBtn) {
            clockInSetupOpenGuideBtn.addEventListener('click', function () {
                openClockInSetupModal(clockInGuideTab);
            });
        }

        if (clockInSetupHideHoverBtn) {
            clockInSetupHideHoverBtn.addEventListener('click', function () {
                hideClockInSetupHoverGuide();
            });
        }

        if (clockInSetupDismissHoverBtn) {
            clockInSetupDismissHoverBtn.addEventListener('click', function () {
                hideClockInSetupHoverGuide();
            });
        }

        if (clockInSetupCloseBtn) {
            clockInSetupCloseBtn.addEventListener('click', function () {
                closeClockInSetupModal();
            });
        }

        if (clockInSetupModal) {
            clockInSetupModal.addEventListener('click', function (e) {
                if (e.target === clockInSetupModal) {
                    closeClockInSetupModal();
                }
            });
        }

        if (clockInSetupDownloadBtn) {
            clockInSetupDownloadBtn.addEventListener('click', function () {
                downloadCaptureExtensionPackage();
                syncClockInSetupUi();
            });
        }

        if (clockInSetupPrimaryBtn) {
            clockInSetupPrimaryBtn.addEventListener('click', function () {
                if (isCaptureExtensionAvailable) {
                    closeClockInSetupModal();
                    if (btnIn) {
                        btnIn.focus();
                    }
                    return;
                }
                window.location.reload();
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && clockInSetupModal && !clockInSetupModal.hidden) {
                closeClockInSetupModal();
            }
        });

        renderClockInGuideSlides();
        ensureClockInGuideSlideTimer();
        setClockInGuideTab(clockInGuideTab);
        syncClockInSetupUi();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initClockInSetupBindings);
    } else {
        initClockInSetupBindings();
    }

    // Keep UI in sync if admin clocks out the user (SSE with fallback)
    if (btnIn && btnOut) {
        function applyAttendanceState(payload) {
            if (!payload) return;
            if (payload.has_active_attendance) {
                attendanceId = payload.attendance_id || attendanceId;
                syncHeartbeatFromAttendancePayload(payload);
                refreshCaptureHeartbeatFromStorage(true);
                refreshCaptureInputStateFromStorage(true);
                hasActiveAttendance = true;
                updateButtonState(true);
                if (statusSpan) {
                    statusSpan.textContent = 'Timed in. Monitoring active.';
                }
                if (payload.time_in) {
                    var elIn = document.getElementById('statTimeIn');
                    if (elIn) elIn.innerText = payload.time_in;
                }
                if (payload.time_out) {
                    var elOut = document.getElementById('statTimeOut');
                    if (elOut) elOut.innerText = payload.time_out;
                }
            } else {
                if (hasActiveAttendance || attendanceId || (captureWindow && !captureWindow.closed)) {
                    signalCaptureStop('attendance_inactive');
                }
                if (captureWindow && !captureWindow.closed) {
                    captureWindow.close();
                }
                hasActiveAttendance = false;
                setClockedOutUI();
                if (payload.time_out) {
                    var elOut2 = document.getElementById('statTimeOut');
                    if (elOut2) elOut2.innerText = payload.time_out;
                }
            }
        }

        function fallbackPoll() {
            ajax('check_attendance.php', '', function (res) {
                if (res.status === 'success') {
                    applyAttendanceState(res);
                }
            }, 'GET');
        }

        var source = new EventSource('sse_my_attendance.php');
        source.onmessage = function (event) {
            try {
                var data = JSON.parse(event.data || '{}');
                if (data && data.status === 'success') {
                    applyAttendanceState(data);
                }
            } catch (e) {
                // ignore parse errors
            }
        };
        source.onerror = function () {
            source.close();
            fallbackPoll();
            setInterval(fallbackPoll, 5000);
        };
    }

    function closeModal() {
        document.getElementById('pausedModal').style.display = 'none';
    }

    function navigateWithClockInGuard(targetHref) {
        if (shouldAskClockInConfirmation(targetHref)) {
            pendingNavTarget = targetHref || null;
            openNavClockInModal();
            return false;
        }
        window.location.href = targetHref;
        return true;
    }

    function shouldAskClockInConfirmation(targetHref) {
        if (!isEmployeeUser) return false;
        if (hasActiveAttendance) return false;
        if (hasSeenClockInNavWarning) return false;
        if (!targetHref) return false;
        if (targetHref.startsWith('#') || targetHref.toLowerCase().startsWith('javascript:')) return false;

        const targetUrl = new URL(targetHref, window.location.href);
        const targetPath = targetUrl.pathname.toLowerCase();
        if (targetPath.endsWith('/logout.php') || targetPath === 'logout.php') return false;

        const currentUrl = new URL(window.location.href);
        return targetUrl.pathname !== currentUrl.pathname || targetUrl.search !== currentUrl.search;
    }

    function openNavClockInModal() {
        const modal = document.getElementById('navClockInModal');
        markClockInNavWarningSeen();
        if (modal) modal.style.display = 'flex';
    }

    function closeNavClockInModal() {
        const modal = document.getElementById('navClockInModal');
        if (modal) modal.style.display = 'none';
    }

    function continueNavAfterClockInWarning() {
        const target = pendingNavTarget;
        closeNavClockInModal();
        pendingNavTarget = null;
        if (target) {
            window.location.href = target;
        }
    }

    function openAutoClockOutModal(message) {
        var modal = document.getElementById('autoClockOutModal');
        var text = document.getElementById('autoClockOutMessage');
        if (text) text.textContent = message || 'You were clocked out because screen sharing was canceled or stopped.';
        if (modal) modal.style.display = 'flex';
    }

    function closeAutoClockOutModal() {
        var modal = document.getElementById('autoClockOutModal');
        if (modal) modal.style.display = 'none';
    }

    function switchAdminLeaderboardTab(tabName) {
        var employeesTab = document.getElementById('adminTabEmployees');
        var groupsTab = document.getElementById('adminTabGroups');
        var employeesPanel = document.getElementById('adminPanelEmployees');
        var groupsPanel = document.getElementById('adminPanelGroups');
        if (!employeesTab || !groupsTab || !employeesPanel || !groupsPanel) return;

        var showEmployees = tabName !== 'groups';
        employeesTab.classList.toggle('active', showEmployees);
        groupsTab.classList.toggle('active', !showEmployees);
        employeesPanel.style.display = showEmployees ? 'flex' : 'none';
        groupsPanel.style.display = showEmployees ? 'none' : 'flex';
    }

    function switchEmployeeLeaderboardTab(tabName) {
        var employeesTab = document.getElementById('employeeTabEmployees');
        var groupsTab = document.getElementById('employeeTabGroups');
        var employeesPanel = document.getElementById('employeePanelEmployees');
        var groupsPanel = document.getElementById('employeePanelGroups');
        if (!employeesTab || !groupsTab || !employeesPanel || !groupsPanel) return;

        var showEmployees = tabName !== 'groups';
        employeesTab.classList.toggle('active', showEmployees);
        groupsTab.classList.toggle('active', !showEmployees);
        employeesPanel.style.display = showEmployees ? 'flex' : 'none';
        groupsPanel.style.display = showEmployees ? 'none' : 'flex';
        requestAnimationFrame(applyBulletinAndTileHeights);
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function limitBulletinListToThreeVisibleItems() {
        var list = document.getElementById('bulletinList');
        if (!list) return;

        var posts = list.querySelectorAll('.bpost');
        var listStyle = window.getComputedStyle(list);
        var paddingTop = parseFloat(listStyle.paddingTop) || 0;
        var paddingBottom = parseFloat(listStyle.paddingBottom) || 0;
        var gap = parseFloat(listStyle.rowGap || listStyle.gap) || 0;
        var targetHeight = 0;

        if (posts.length >= 3) {
            var thirdItemBottom = posts[2].offsetTop + posts[2].offsetHeight;
            targetHeight = Math.ceil(thirdItemBottom + paddingBottom + gap);
        } else {
            var baseItemHeight = 82;
            if (posts.length > 0) {
                baseItemHeight = posts[0].offsetHeight || baseItemHeight;
            } else {
                var emptyState = list.querySelector('.bulletin-empty');
                if (emptyState) {
                    baseItemHeight = Math.max(72, Math.floor((emptyState.offsetHeight || 0) / 3));
                }
            }
            targetHeight = Math.ceil((baseItemHeight * 3) + (gap * 2) + paddingTop + paddingBottom);
        }

        list.style.overflowY = posts.length > 3 ? 'auto' : 'hidden';
        list.style.minHeight = targetHeight + 'px';
        list.style.maxHeight = targetHeight + 'px';

        if (posts.length === 0) {
            var emptyStateEl = list.querySelector('.bulletin-empty');
            if (emptyStateEl) {
                emptyStateEl.style.minHeight = Math.max(0, targetHeight - paddingTop - paddingBottom) + 'px';
            }
        }
    }

    function limitAdminLeaderboardToFourVisibleItems() {
        if (!isAdminUser) return;

        var employeesPanel = document.getElementById('adminPanelEmployees');
        var groupsPanel = document.getElementById('adminPanelGroups');
        var activePanel = null;
        if (employeesPanel && employeesPanel.style.display !== 'none') {
            activePanel = employeesPanel;
        } else if (groupsPanel && groupsPanel.style.display !== 'none') {
            activePanel = groupsPanel;
        } else {
            activePanel = employeesPanel || groupsPanel;
        }
        if (!activePanel) return;

        var list = activePanel.querySelector('.leaderboard-list');
        if (!list) return;

        var items = list.querySelectorAll('.leaderboard-item');
        list.style.overflowY = 'auto';

        if (items.length <= 4) {
            list.style.maxHeight = 'none';
            return;
        }

        var listStyle = window.getComputedStyle(list);
        var paddingBottom = parseFloat(listStyle.paddingBottom) || 0;
        var gap = parseFloat(listStyle.rowGap || listStyle.gap) || 0;
        var fourthItemBottom = items[3].offsetTop + items[3].offsetHeight;
        var targetHeight = Math.ceil(fourthItemBottom + paddingBottom + gap);

        list.style.maxHeight = targetHeight + 'px';
    }

    function syncAdminBulletinHeightToLeaderboard() {
        if (!isAdminUser) return;
        if (window.innerWidth <= 1180) {
            var smallScreenBulletinList = document.getElementById('bulletinList');
            if (smallScreenBulletinList) {
                smallScreenBulletinList.style.maxHeight = 'none';
                smallScreenBulletinList.style.minHeight = '';
            }
            return;
        }

        var employeesPanel = document.getElementById('adminPanelEmployees');
        var groupsPanel = document.getElementById('adminPanelGroups');
        var activePanel = null;
        if (employeesPanel && employeesPanel.style.display !== 'none') {
            activePanel = employeesPanel;
        } else if (groupsPanel && groupsPanel.style.display !== 'none') {
            activePanel = groupsPanel;
        } else {
            activePanel = employeesPanel || groupsPanel;
        }

        var leaderboardList = activePanel ? activePanel.querySelector('.leaderboard-list') : null;
        var bulletinList = document.getElementById('bulletinList');
        if (!leaderboardList || !bulletinList) return;

        var leaderRect = leaderboardList.getBoundingClientRect();
        var bulletinRect = bulletinList.getBoundingClientRect();
        var targetHeight = Math.floor(leaderRect.bottom - bulletinRect.top);
        if (targetHeight <= 120) return;

        var posts = bulletinList.querySelectorAll('.bpost');
        bulletinList.style.overflowY = posts.length > 4 ? 'auto' : 'hidden';
        bulletinList.style.minHeight = targetHeight + 'px';
        bulletinList.style.maxHeight = targetHeight + 'px';

        if (posts.length === 0) {
            var style = window.getComputedStyle(bulletinList);
            var paddingTop = parseFloat(style.paddingTop) || 0;
            var paddingBottom = parseFloat(style.paddingBottom) || 0;
            var emptyState = bulletinList.querySelector('.bulletin-empty');
            if (emptyState) {
                emptyState.style.minHeight = Math.max(0, targetHeight - paddingTop - paddingBottom) + 'px';
            }
        }
    }

    function applyBulletinAndTileHeights() {
        if (isAdminUser) {
            limitAdminLeaderboardToFourVisibleItems();
            syncAdminBulletinHeightToLeaderboard();
            return;
        }
        var employeeBulletinCard = document.querySelector('.bulletin-card');
        if (employeeBulletinCard) {
            employeeBulletinCard.style.height = '';
        }
        limitBulletinListToThreeVisibleItems();
    }

    function renderBulletins() {
        var list = document.getElementById('bulletinList');
        if (!list) return;

        if (!Array.isArray(bulletinPosts) || bulletinPosts.length === 0) {
            list.style.maxHeight = 'none';
            list.innerHTML = '<div class=\"bulletin-empty\"><i class=\"fa fa-inbox\" style=\"font-size:22px; display:block; margin-bottom:8px;\"></i>No posts yet.</div>';
            requestAnimationFrame(applyBulletinAndTileHeights);
            return;
        }

        list.innerHTML = bulletinPosts.map(function (post) {
            var type = (post && post.type) ? String(post.type) : 'ann';
            if (!bulletinTagLabels[type]) {
                type = 'ann';
            }
            var postId = post && post.id ? parseInt(post.id, 10) : 0;
            var deleteAction = '';
            if (isAdminUser && postId > 0) {
                deleteAction = '<button type=\"button\" class=\"bdelete\" title=\"Delete post\" onclick=\"deleteBulletinPost(' + postId + ')\"><i class=\"fa fa-trash\"></i></button>';
            }

            return '' +
                '<div class=\"bpost ' + type + '\">' +
                    '<div class=\"bpost-top\">' +
                        '<span class=\"btag ' + type + '\">' + escapeHtml(bulletinTagLabels[type]) + '</span>' +
                        '<div class=\"bmeta\">' +
                            '<span class=\"btime\">' + escapeHtml(post.time || '') + '</span>' +
                            deleteAction +
                        '</div>' +
                    '</div>' +
                    '<div class=\"btitle\">' + escapeHtml(post.title || '') + '</div>' +
                    '<div class=\"bbody\">' + escapeHtml(post.body || '') + '</div>' +
                '</div>';
        }).join('');

        requestAnimationFrame(applyBulletinAndTileHeights);
    }

    function openBulletinPostModal() {
        var modal = document.getElementById('bulletinPostModal');
        if (modal) modal.style.display = 'flex';
    }

    function closeBulletinPostModal() {
        var modal = document.getElementById('bulletinPostModal');
        if (modal) modal.style.display = 'none';
    }

    function submitBulletinPost() {
        if (!isAdminUser) return;
        var typeEl = document.getElementById('bulletinType');
        var titleEl = document.getElementById('bulletinTitle');
        var bodyEl = document.getElementById('bulletinBody');
        var submitBtn = document.querySelector('#bulletinPostModal .bulletin-btn-submit');
        if (!typeEl || !titleEl || !bodyEl) return;

        var type = typeEl.value;
        var title = titleEl.value.trim();
        var body = bodyEl.value.trim();
        if (!title || !body) {
            alert('Please fill in title and message.');
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Posting...';
        }

        var payload = 'csrf_token=' + encodeURIComponent(bulletinPostCsrfToken) +
            '&type=' + encodeURIComponent(type) +
            '&title=' + encodeURIComponent(title) +
            '&body=' + encodeURIComponent(body);

        ajax('bulletin_post.php', payload, function (res) {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Post to All Users';
            }

            if (!res || res.status !== 'success' || !res.post) {
                alert((res && res.message) ? res.message : 'Unable to publish bulletin post.');
                return;
            }

            bulletinPosts.unshift(res.post);
            renderBulletins();
            closeBulletinPostModal();
            titleEl.value = '';
            bodyEl.value = '';
        });
    }

    function deleteBulletinPost(postId) {
        if (!isAdminUser) return;
        var id = parseInt(postId, 10);
        if (!id) return;

        pendingBulletinDeleteId = id;
        openBulletinDeleteModal();
    }

    function openBulletinDeleteModal() {
        var modal = document.getElementById('bulletinDeleteModal');
        if (modal) modal.style.display = 'flex';
    }

    function closeBulletinDeleteModal() {
        var modal = document.getElementById('bulletinDeleteModal');
        if (modal) modal.style.display = 'none';
        pendingBulletinDeleteId = null;
    }

    function confirmBulletinDelete() {
        if (!isAdminUser) return;
        var id = parseInt(pendingBulletinDeleteId, 10);
        if (!id) {
            closeBulletinDeleteModal();
            return;
        }
        var btn = document.getElementById('bulletinDeleteConfirmBtn');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Deleting...';
        }

        var payload = 'csrf_token=' + encodeURIComponent(bulletinDeleteCsrfToken) +
            '&post_id=' + encodeURIComponent(String(id));

        ajax('bulletin_delete.php', payload, function (res) {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Delete';
            }
            if (!res || res.status !== 'success') {
                alert((res && res.message) ? res.message : 'Unable to delete bulletin post.');
                return;
            }

            bulletinPosts = (bulletinPosts || []).filter(function (item) {
                return parseInt(item && item.id ? item.id : 0, 10) !== id;
            });
            renderBulletins();
            closeBulletinDeleteModal();
        });
    }

    if (isEmployeeUser) {
        document.addEventListener('click', function (e) {
            const link = e.target.closest('a[href]');
            if (!link) return;
            const href = link.getAttribute('href');
            if (shouldAskClockInConfirmation(href)) {
                e.preventDefault();
                pendingNavTarget = href || null;
                openNavClockInModal();
            }
        }, true);
    }

    document.addEventListener('click', function (event) {
        var postModalEl = document.getElementById('bulletinPostModal');
        if (postModalEl && event.target === postModalEl) {
            closeBulletinPostModal();
        }
        var deleteModalEl = document.getElementById('bulletinDeleteModal');
        if (deleteModalEl && event.target === deleteModalEl) {
            closeBulletinDeleteModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        closeBulletinPostModal();
        closeBulletinDeleteModal();
    });

    window.addEventListener('resize', function () {
        applyBulletinAndTileHeights();
    });

    switchAdminLeaderboardTab('employees');
    switchEmployeeLeaderboardTab('employees');
    renderBulletins();
    if (isEmployeeUser && !window.__taskflowSharedIdleEnabled) {
        setupIdleCheckPrompt();
    }
