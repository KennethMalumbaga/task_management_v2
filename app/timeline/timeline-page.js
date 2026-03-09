
(function () {
    var root = document.getElementById('tlpApp');
    if (!root) {
        return;
    }

    var STATUS_META = {
        ongoing: { label: 'Ongoing', bg: '#DCFCE7', color: '#166534', dot: '#10B981' },
        planning: { label: 'Planning', bg: '#FEF3C7', color: '#92400E', dot: '#F59E0B' },
        completed: { label: 'Completed', bg: '#EDE9FE', color: '#5B21B6', dot: '#8B5CF6' }
    };
    var PHASE_TRACKING_META = {
        planning: { label: 'Planning', bg: '#F3F4F6', color: '#4B5563' },
        ongoing: { label: 'Ongoing', bg: '#FEF3C7', color: '#92400E' },
        completed: { label: 'Completed', bg: '#DCFCE7', color: '#166534' }
    };

    var COLORS = ['#6C3CE1', '#8B5CF6', '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#0EA5E9', '#EC4899'];
    var LIGHTS = ['#EDE9FE', '#F3E8FF', '#DBEAFE', '#DCFCE7', '#FEF3C7', '#FEE2E2', '#E0F2FE', '#FCE7F3'];
    var ICON_CHOICES = [
        'fa-search', 'fa-cogs', 'fa-paint-brush', 'fa-flask', 'fa-rocket', 'fa-list-alt',
        'fa-wrench', 'fa-check', 'fa-rss', 'fa-lock', 'fa-area-chart', 'fa-shield',
        'fa-lightbulb-o', 'fa-database', 'fa-code'
    ];
    var COLOR_CHOICES = ['#6C3CE1', '#8B5CF6', '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#0EA5E9', '#EC4899'];
    var MIN_DAY_COLUMN_WIDTH = 28;
    var TARGET_DAY_COLUMN_WIDTH = 44;

    var state = {
        role: String(root.dataset.role || '').toLowerCase(),
        userId: Number(root.dataset.userId || 0),
        csrfToken: String(root.dataset.csrf || ''),
        todayDay: 1,
        projects: [],
        activeFilter: 'all',
        searchValue: '',
        activeProjectId: null,
        currentScreen: 'overview',
        taskModalProjectId: null,
        editingTaskId: null,
        phaseModalTaskId: null,
        phaseDayLimit: 365,
        editingPhaseId: null,
        confirmAction: null,
        selectedIcon: 'fa-circle',
        selectedColor: '#6C3CE1'
    };

    var el = {
        loading: document.getElementById('tlpLoading'),
        alert: document.getElementById('tlpAlert'),
        adminOverview: document.getElementById('tlpAdminOverview'),
        adminDetail: document.getElementById('tlpAdminDetail'),
        employeeView: document.getElementById('tlpEmployeeView'),
        statsGrid: document.getElementById('tlpStatsGrid'),
        tileGrid: document.getElementById('tlpTileGrid'),
        adminEmpty: document.getElementById('tlpAdminEmpty'),
        search: document.getElementById('tlpSearchInput'),
        backBtn: document.getElementById('tlpBackBtn'),
        detailAvatar: document.getElementById('tlpDetailAvatar'),
        detailName: document.getElementById('tlpDetailName'),
        detailSub: document.getElementById('tlpDetailSub'),
        detailStatus: document.getElementById('tlpDetailStatus'),
        detailMembers: document.getElementById('tlpDetailMembers'),
        detailProgress: document.getElementById('tlpDetailProgress'),
        adminGanttMount: document.getElementById('tlpAdminGanttMount'),
        projectSwitch: document.getElementById('tlpProjectSwitch'),
        employeeMount: document.getElementById('tlpEmployeeMount'),
        taskModalWrap: document.getElementById('tlpTaskModalWrap'),
        taskModalTitle: document.getElementById('tlpTaskModalTitle'),
        taskNameInput: document.getElementById('tlpTaskNameInput'),
        taskAssigneeSelect: document.getElementById('tlpTaskAssigneeSelect'),
        taskSaveBtn: document.getElementById('tlpTaskSaveBtn'),
        taskCancelBtn: document.getElementById('tlpTaskCancelBtn'),
        phaseModalWrap: document.getElementById('tlpPhaseModalWrap'),
        phaseModalTitle: document.getElementById('tlpPhaseModalTitle'),
        phaseNameInput: document.getElementById('tlpPhaseNameInput'),
        phaseDescInput: document.getElementById('tlpPhaseDescInput'),
        phaseStartInput: document.getElementById('tlpPhaseStartInput'),
        phaseDurationInput: document.getElementById('tlpPhaseDurationInput'),
        phaseGuideCount: document.getElementById('tlpPhaseGuideCount'),
        phaseGuideLimit: document.getElementById('tlpPhaseGuideLimit'),
        phaseGuideList: document.getElementById('tlpPhaseGuideList'),
        phaseGuideTip: document.getElementById('tlpPhaseGuideTip'),
        iconGrid: document.getElementById('tlpIconGrid'),
        colorGrid: document.getElementById('tlpColorGrid'),
        phaseSaveBtn: document.getElementById('tlpPhaseSaveBtn'),
        phaseCancelBtn: document.getElementById('tlpPhaseCancelBtn'),
        confirmModalWrap: document.getElementById('tlpConfirmModalWrap'),
        confirmTitle: document.getElementById('tlpConfirmTitle'),
        confirmMessage: document.getElementById('tlpConfirmMessage'),
        confirmBtn: document.getElementById('tlpConfirmBtn'),
        confirmCancelBtn: document.getElementById('tlpConfirmCancelBtn'),
        tooltip: document.getElementById('tlpTooltip'),
        tooltipTitle: document.getElementById('tlpTooltipTitle'),
        tooltipDesc: document.getElementById('tlpTooltipDesc'),
        tooltipDays: document.getElementById('tlpTooltipDays')
    };

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function toInitials(name) {
        var parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) {
            return 'U';
        }
        if (parts.length === 1) {
            return parts[0].slice(0, 2).toUpperCase();
        }
        return (parts[0].charAt(0) + parts[1].charAt(0)).toUpperCase();
    }

    function colorIndexForProject(project) {
        var pid = Number(project && project.id ? project.id : 0);
        var lid = Number(project && project.leader && project.leader.id ? project.leader.id : 0);
        return Math.abs((pid * 3) + lid) % COLORS.length;
    }

    function safeStatus(status) {
        var key = String(status || '').toLowerCase();
        if (key === 'active') {
            key = 'ongoing';
        }
        return STATUS_META[key] ? key : 'planning';
    }

    function safePhaseTrackingStatus(status) {
        var key = String(status || '').toLowerCase();
        return PHASE_TRACKING_META[key] ? key : 'planning';
    }

    function normalizeIcon(iconClass) {
        var cleaned = String(iconClass || '').trim();
        if (/^[a-z0-9\-\s]{2,40}$/i.test(cleaned)) {
            return cleaned;
        }
        return 'fa-circle';
    }

    function normalizeColor(hex) {
        var value = String(hex || '').trim();
        if (/^#[0-9a-fA-F]{6}$/.test(value)) {
            return value.toUpperCase();
        }
        return '#6C3CE1';
    }

    function clampNumber(value, min, max, fallback) {
        var n = Number(value);
        if (!Number.isFinite(n)) {
            return fallback;
        }
        n = Math.floor(n);
        if (n < min) {
            return min;
        }
        if (n > max) {
            return max;
        }
        return n;
    }
    function showLoading(show, text) {
        if (!el.loading) {
            return;
        }
        el.loading.style.display = show ? 'block' : 'none';
        if (show && text) {
            el.loading.textContent = text;
        }
    }

    function showAlert(message) {
        if (!el.alert) {
            return;
        }
        if (!message) {
            el.alert.classList.remove('show');
            el.alert.textContent = '';
            return;
        }
        el.alert.classList.add('show');
        el.alert.textContent = message;
    }

    function setScreen(screenName) {
        el.adminOverview.classList.remove('active');
        el.adminDetail.classList.remove('active');
        el.employeeView.classList.remove('active');
        if (screenName === 'admin-detail') {
            el.adminDetail.classList.add('active');
            return;
        }
        if (screenName === 'employee') {
            el.employeeView.classList.add('active');
            return;
        }
        el.adminOverview.classList.add('active');
    }

    async function requestJson(url, options) {
        var response = await fetch(url, Object.assign({ credentials: 'same-origin' }, options || {}));
        var data;
        try {
            data = await response.json();
        } catch (error) {
            data = { ok: false, message: 'Unexpected response from server.' };
        }

        if (!response.ok || !data.ok) {
            throw new Error(data.message || 'Request failed.');
        }
        return data;
    }

    async function apiGetTimeline() {
        return requestJson('app/timeline/get.php');
    }

    async function apiPost(url, payload) {
        var params = new URLSearchParams();
        params.set('csrf_token', state.csrfToken);
        Object.keys(payload || {}).forEach(function (key) {
            if (payload[key] === undefined || payload[key] === null) {
                return;
            }
            params.set(key, String(payload[key]));
        });

        return requestJson(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: params.toString()
        });
    }

    function getProjectById(projectId) {
        return state.projects.find(function (project) {
            return Number(project.id) === Number(projectId);
        }) || null;
    }

    function getActiveProject() {
        if (!state.projects.length) {
            return null;
        }
        if (state.activeProjectId !== null) {
            var selected = getProjectById(state.activeProjectId);
            if (selected) {
                return selected;
            }
        }
        return state.projects[0];
    }

    function getTaskContextByTimelineTaskId(timelineTaskId) {
        var normalizedTaskId = Number(timelineTaskId || 0);
        if (!normalizedTaskId) {
            return null;
        }

        for (var i = 0; i < state.projects.length; i += 1) {
            var project = state.projects[i];
            var tasks = Array.isArray(project.tasks) ? project.tasks : [];
            for (var j = 0; j < tasks.length; j += 1) {
                if (Number(tasks[j].id || 0) === normalizedTaskId) {
                    return {
                        project: project,
                        task: tasks[j]
                    };
                }
            }
        }
        return null;
    }

    function getPhaseDayLimitForTask(timelineTaskId) {
        var context = getTaskContextByTimelineTaskId(timelineTaskId);
        if (!context || !context.project) {
            return 365;
        }

        var deadlineDays = Number(context.project.deadline_days || 0);
        if (Number.isFinite(deadlineDays) && deadlineDays > 0) {
            return clampNumber(deadlineDays, 1, 365, 365);
        }
        return 365;
    }

    function parseIntegerInput(value) {
        var raw = String(value === undefined || value === null ? '' : value).trim();
        if (raw === '') {
            return null;
        }
        var n = Number(raw);
        if (!Number.isFinite(n)) {
            return null;
        }
        return Math.floor(n);
    }

    function getPhaseGuideItemsForTask(timelineTaskId, excludedPhaseId) {
        var context = getTaskContextByTimelineTaskId(timelineTaskId);
        if (!context || !context.task || !Array.isArray(context.task.phases)) {
            return [];
        }

        var excludedId = Number(excludedPhaseId || 0);
        return context.task.phases
            .map(function (phase, index) {
                var phaseId = Number(phase.id || 0);
                if (excludedId > 0 && phaseId === excludedId) {
                    return null;
                }
                var startDay = clampNumber(phase.start_day, 1, 365, 1);
                var durationDays = clampNumber(phase.duration_days, 1, 180, 1);
                return {
                    id: phaseId,
                    index: index,
                    name: String(phase.name || 'Phase'),
                    startDay: startDay,
                    durationDays: durationDays,
                    endDay: startDay + durationDays - 1
                };
            })
            .filter(Boolean)
            .sort(function (a, b) {
                if (a.startDay !== b.startDay) {
                    return a.startDay - b.startDay;
                }
                if (a.endDay !== b.endDay) {
                    return a.endDay - b.endDay;
                }
                return a.index - b.index;
            });
    }

    function getSuggestedStartDay(phaseItems, dayLimit) {
        if (!Array.isArray(phaseItems) || !phaseItems.length) {
            return 1;
        }

        var maxEndDay = 0;
        phaseItems.forEach(function (item) {
            maxEndDay = Math.max(maxEndDay, Number(item.endDay || 0));
        });

        return clampNumber(maxEndDay + 1, 1, dayLimit, 1);
    }

    function getRangeOverlapItems(startDay, endDay, phaseItems) {
        if (!Array.isArray(phaseItems) || !phaseItems.length) {
            return [];
        }
        return phaseItems.filter(function (item) {
            return !(endDay < item.startDay || startDay > item.endDay);
        });
    }

    function setPhaseGuideTip(mode, text) {
        if (!el.phaseGuideTip) {
            return;
        }
        var normalizedMode = String(mode || '').trim();
        var className = 'tlp-phase-guide-tip';
        if (normalizedMode) {
            className += ' ' + normalizedMode;
        }
        el.phaseGuideTip.className = className;
        el.phaseGuideTip.textContent = String(text || '');
    }

    function renderPhaseGuide() {
        if (!el.phaseGuideList || !el.phaseGuideTip || !el.phaseGuideLimit) {
            return;
        }

        var dayLimit = clampNumber(state.phaseDayLimit, 1, 365, 365);
        var phaseItems = getPhaseGuideItemsForTask(state.phaseModalTaskId, state.editingPhaseId);
        var suggestion = getSuggestedStartDay(phaseItems, dayLimit);
        var phaseCount = phaseItems.length;

        el.phaseGuideLimit.textContent = 'Day 1-' + dayLimit;
        if (el.phaseGuideCount) {
            el.phaseGuideCount.textContent = phaseCount + ' phase' + (phaseCount === 1 ? '' : 's');
        }

        if (!phaseItems.length) {
            el.phaseGuideList.innerHTML = '<div class="tlp-phase-guide-empty">No phases yet for this timeline task.</div>';
        } else {
            el.phaseGuideList.innerHTML = phaseItems.map(function (item) {
                var dayLabel = item.startDay === item.endDay
                    ? ('D' + item.startDay)
                    : ('D' + item.startDay + '-' + item.endDay);
                var titleText = item.name + ' (Day ' + item.startDay + '-' + item.endDay + ')';
                return '' +
                    '<div class="tlp-phase-guide-chip" title="' + escapeHtml(titleText) + '">' +
                        '<span class="tlp-phase-guide-chip-days">' + dayLabel + '</span>' +
                    '</div>';
            }).join('');
        }

        var startDay = clampNumber(el.phaseStartInput.value, 1, dayLimit, 1);
        var maxDuration = Math.max(1, (dayLimit - startDay) + 1);
        var durationDays = clampNumber(el.phaseDurationInput.value, 1, maxDuration, 1);
        var endDay = startDay + durationDays - 1;
        var overlaps = getRangeOverlapItems(startDay, endDay, phaseItems);

        if (overlaps.length > 0) {
            setPhaseGuideTip(
                'warning',
                'Day ' + startDay + '-' + endDay + ' overlaps with ' + overlaps.length + ' existing phase(s).'
            );
            return;
        }

        if (state.editingPhaseId) {
            setPhaseGuideTip('ok', 'Day ' + startDay + '-' + endDay + ' is clear.');
            return;
        }

        setPhaseGuideTip(
            'info',
            'Suggested: start on Day ' + suggestion + '. Current Day ' + startDay + '-' + endDay + ' is clear.'
        );
    }

    function syncPhaseDayInputs(options) {
        options = options || {};
        var forceNormalize = !!options.forceNormalize;
        var limit = clampNumber(state.phaseDayLimit, 1, 365, 365);

        var rawStartDay = parseIntegerInput(el.phaseStartInput.value);
        var startDay = rawStartDay === null ? 1 : clampNumber(rawStartDay, 1, limit, 1);
        if (forceNormalize || rawStartDay !== null) {
            el.phaseStartInput.value = String(startDay);
        }
        el.phaseStartInput.max = String(limit);

        var maxDuration = Math.max(1, (limit - startDay) + 1);
        el.phaseDurationInput.max = String(maxDuration);

        var rawDuration = parseIntegerInput(el.phaseDurationInput.value);
        if (forceNormalize || rawDuration !== null) {
            var duration = rawDuration === null ? 1 : clampNumber(rawDuration, 1, maxDuration, 1);
            el.phaseDurationInput.value = String(duration);
        }

        renderPhaseGuide();
    }

    function getStatusMeta(statusKey) {
        return STATUS_META[safeStatus(statusKey)];
    }

    function getFilteredProjects() {
        var query = state.searchValue.trim().toLowerCase();
        return state.projects.filter(function (project) {
            var status = safeStatus(project.status);
            if (state.activeFilter !== 'all' && status !== state.activeFilter) {
                return false;
            }
            if (!query) {
                return true;
            }
            var projectName = String(project.name || '').toLowerCase();
            var group = String(project.group || '').toLowerCase();
            var leader = String(project.leader && project.leader.name ? project.leader.name : '').toLowerCase();
            return projectName.indexOf(query) >= 0 || group.indexOf(query) >= 0 || leader.indexOf(query) >= 0;
        });
    }

    function buildStats() {
        var total = state.projects.length;
        var ongoing = 0;
        var planning = 0;
        var completed = 0;

        state.projects.forEach(function (project) {
            var statusKey = safeStatus(project.status);
            if (statusKey === 'ongoing') {
                ongoing += 1;
            } else if (statusKey === 'planning') {
                planning += 1;
            } else if (statusKey === 'completed') {
                completed += 1;
            }
        });

        var cards = [
            { icon: 'fa-line-chart', iconBg: '#EDE9FE', value: total, label: 'Total Projects', color: '#6C3CE1' },
            { icon: 'fa-bolt', iconBg: '#DCFCE7', value: ongoing, label: 'Ongoing', color: '#059669' },
            { icon: 'fa-clock-o', iconBg: '#FEF3C7', value: planning, label: 'Planning', color: '#D97706' },
            { icon: 'fa-check', iconBg: '#DBEAFE', value: completed, label: 'Completed', color: '#2563EB' }
        ];

        el.statsGrid.innerHTML = cards.map(function (card) {
            return '' +
                '<div class="tlp-stat-card">' +
                    '<div class="tlp-stat-icon" style="background:' + card.iconBg + ';color:' + card.color + '">' +
                        '<i class="fa ' + card.icon + '"></i>' +
                    '</div>' +
                    '<div>' +
                        '<div class="tlp-stat-value" style="color:' + card.color + '">' + card.value + '</div>' +
                        '<div class="tlp-stat-label">' + card.label + '</div>' +
                    '</div>' +
                '</div>';
        }).join('');
    }

    function buildTileMiniRows(project, totalDays) {
        if (!Array.isArray(project.tasks) || !project.tasks.length) {
            return '<div class="tlp-mini-row"></div>';
        }

        var rows = [];
        project.tasks.forEach(function (task) {
            var bars = [];
            (task.phases || []).forEach(function (phase) {
                var start = clampNumber(phase.start_day, 1, 365, 1);
                var duration = clampNumber(phase.duration_days, 1, 180, 1);
                var left = ((start - 1) / totalDays) * 100;
                var width = (duration / totalDays) * 100;
                bars.push('<div class="tlp-mini-bar" style="left:' + left + '%;width:' + width + '%;background:' + normalizeColor(phase.color) + '"></div>');
            });
            rows.push('<div class="tlp-mini-row">' + bars.join('') + '</div>');
        });
        return rows.join('');
    }

    function buildMemberBubbles(project, colorIdx) {
        var members = Array.isArray(project.members) ? project.members : [];
        var visible = members.slice(0, 4);
        var html = [];
        visible.forEach(function (member, index) {
            var color = COLORS[(colorIdx + index + 1) % COLORS.length];
            html.push(
                '<div class="tlp-member-bubble" style="background:' + color + ';z-index:' + (30 - index) + '">' +
                    escapeHtml(toInitials(member.name)) +
                '</div>'
            );
        });
        if (members.length > 4) {
            html.push('<div class="tlp-member-bubble" style="background:#E5E7EB;color:#6B7280">' + (members.length - 4) + '</div>');
        }
        return html.join('');
    }
    function renderAdminOverview() {
        buildStats();
        var filtered = getFilteredProjects();
        el.tileGrid.innerHTML = '';
        el.adminEmpty.style.display = filtered.length ? 'none' : 'block';

        filtered.forEach(function (project) {
            var statusMeta = getStatusMeta(project.status);
            var colorIdx = colorIndexForProject(project);
            var accent = COLORS[colorIdx];
            var light = LIGHTS[colorIdx];
            var totalDays = clampNumber(project.total_days, 1, 365, 20);

            var tile = document.createElement('div');
            tile.className = 'tlp-tile';
            tile.style.setProperty('--tlp-accent', accent);
            tile.dataset.projectId = String(project.id);

            tile.innerHTML = '' +
                '<div class="tlp-tile-top">' +
                    '<div class="tlp-avatar" style="background:' + light + ';color:' + accent + '">' +
                        escapeHtml(toInitials(project.leader && project.leader.name ? project.leader.name : project.name)) +
                    '</div>' +
                    '<span class="tlp-status" style="background:' + statusMeta.bg + ';color:' + statusMeta.color + '">' +
                        '<span class="tlp-status-dot" style="background:' + statusMeta.dot + '"></span>' + escapeHtml(statusMeta.label) +
                    '</span>' +
                '</div>' +
                '<div>' +
                    '<div class="tlp-tile-title">' + escapeHtml(project.name || 'Untitled Project') + '</div>' +
                    '<div class="tlp-tile-sub">Group: ' + escapeHtml(project.group || 'Project Team') + ' | Leader: ' + escapeHtml(project.leader && project.leader.name ? project.leader.name : 'N/A') + '</div>' +
                '</div>' +
                '<div>' +
                    '<div class="tlp-progress-head">' +
                        '<span>Progress</span>' +
                        '<span style="color:' + accent + '">' + clampNumber(project.progress, 0, 100, 0) + '%</span>' +
                    '</div>' +
                    '<div class="tlp-progress-track">' +
                        '<div class="tlp-progress-fill" style="width:' + clampNumber(project.progress, 0, 100, 0) + '%;background:linear-gradient(90deg,' + accent + ',' + accent + 'CC)"></div>' +
                    '</div>' +
                '</div>' +
                '<div class="tlp-mini-gantt">' + buildTileMiniRows(project, totalDays) + '</div>' +
                '<div class="tlp-tile-footer">' +
                    '<div class="tlp-member-stack">' + buildMemberBubbles(project, colorIdx) + '</div>' +
                    '<span class="tlp-view-link" style="color:' + accent + '">View timeline <i class="fa fa-angle-right"></i></span>' +
                '</div>';

            tile.addEventListener('click', function () {
                state.activeProjectId = Number(project.id);
                state.currentScreen = 'detail';
                renderAdminDetail();
            });

            el.tileGrid.appendChild(tile);
        });
    }

    function renderMemberStackForDetail(project, colorIdx) {
        var html = [];
        (project.members || []).forEach(function (member, index) {
            var color = COLORS[(colorIdx + index + 1) % COLORS.length];
            html.push(
                '<div class="tlp-member-bubble" style="background:' + color + ';z-index:' + (30 - index) + '">' +
                    escapeHtml(toInitials(member.name)) +
                '</div>'
            );
        });
        return html.join('');
    }

    function buildGanttContent(project, canEdit, accent, light) {
        var totalDays = clampNumber(project.total_days, 1, 365, 20);
        var today = clampNumber(state.todayDay, 1, totalDays, 1);
        var labelColumnWidth = window.innerWidth <= 900 ? 190 : 220;
        var shellWidthPx = root ? Math.floor(root.getBoundingClientRect().width) : window.innerWidth;
        var dayAreaWidthPx = Math.max(280, shellWidthPx - labelColumnWidth - 4);
        var maxVisibleDays = Math.max(1, Math.floor(dayAreaWidthPx / MIN_DAY_COLUMN_WIDTH));
        var preferredVisibleDays = Math.max(1, Math.floor(dayAreaWidthPx / TARGET_DAY_COLUMN_WIDTH));
        var visibleDays = Math.min(totalDays, Math.max(preferredVisibleDays, Math.min(maxVisibleDays, totalDays)));
        var dayColumnWidth = Math.max(MIN_DAY_COLUMN_WIDTH, Math.floor(dayAreaWidthPx / visibleDays));
        var timelineWidthPx = totalDays * dayColumnWidth;
        var phaseBarGapPx = Math.max(2, Math.min(6, Math.round(dayColumnWidth * 0.08)));
        var dayHeaders = [];
        for (var day = 1; day <= totalDays; day += 1) {
            var cls = day === today ? 'tlp-day today' : 'tlp-day';
            var style = day === today ? ' style="color:' + accent + ';background:' + light + '"' : '';
            dayHeaders.push('<div class="' + cls + '"' + style + '>' + day + '</div>');
        }

        var tasks = Array.isArray(project.tasks) ? project.tasks : [];
        if (!tasks.length) {
            var emptyAddTaskButton = canEdit
                ? '<div class="tlp-actions-row"><button type="button" class="tlp-add-task-btn" data-action="add-task" data-project-id="' + Number(project.id) + '"><i class="fa fa-plus"></i> Add Timeline Task</button></div>'
                : '';
            return '' +
                '<div class="tlp-workspace">' +
                    '<div class="tlp-empty" style="display:block;margin:16px;border:0;border-radius:10px;background:#F9FAFB">' +
                        '<i class="fa fa-list-alt"></i>' +
                        '<strong>No timeline tasks yet</strong>' +
                        '<span>' + (canEdit ? 'Use "Add Timeline Task" to create the first work area.' : 'The leader has not created timeline tasks yet.') + '</span>' +
                    '</div>' +
                '</div>' +
                emptyAddTaskButton;
        }

        var taskHtml = [];
        tasks.forEach(function (task, taskIndex) {
            var gridLines = [];
            for (var d = 1; d <= totalDays; d += 1) {
                var left = ((d - 1) / totalDays) * 100;
                var lineColor = d === today ? accent + '55' : '#F3F4F6';
                gridLines.push('<div class="tlp-grid-line" style="left:' + left + '%;background:' + lineColor + '"></div>');
            }
            var todayLeft = ((today - 0.5) / totalDays) * 100;
            gridLines.push('<div class="tlp-today-line" style="left:' + todayLeft + '%;background:' + accent + '"></div>');

            var phaseBars = [];
            var phaseRows = [];
            var laneHeight = 30;
            var laneGap = 8;
            var lanePadding = 8;
            var phaseItems = (task.phases || []).map(function (phase, phaseIndex) {
                var start = clampNumber(phase.start_day, 1, 365, 1);
                var duration = clampNumber(phase.duration_days, 1, 180, 1);
                return {
                    phase: phase,
                    phaseIndex: phaseIndex,
                    start: start,
                    duration: duration,
                    endDay: start + duration - 1
                };
            });

            var laneEndDays = [];
            var orderedForLanes = phaseItems.slice().sort(function (a, b) {
                if (a.start !== b.start) {
                    return a.start - b.start;
                }
                if (a.endDay !== b.endDay) {
                    return a.endDay - b.endDay;
                }
                return a.phaseIndex - b.phaseIndex;
            });

            orderedForLanes.forEach(function (item) {
                var lane = 0;
                while (lane < laneEndDays.length && item.start <= laneEndDays[lane]) {
                    lane += 1;
                }
                if (lane >= laneEndDays.length) {
                    laneEndDays.push(item.endDay);
                } else {
                    laneEndDays[lane] = item.endDay;
                }
                item.lane = lane;
            });

            var laneByIndex = {};
            orderedForLanes.forEach(function (item) {
                laneByIndex[item.phaseIndex] = item.lane || 0;
            });

            var laneCount = Math.max(1, laneEndDays.length);
            var barAreaHeight = Math.max(58, (lanePadding * 2) + (laneCount * laneHeight) + ((laneCount - 1) * laneGap));

            phaseItems.forEach(function (item) {
                var phase = item.phase;
                var phaseId = Number(phase.id || 0);
                var start = item.start;
                var duration = item.duration;
                var endDay = item.endDay;
                var lane = Number(laneByIndex[item.phaseIndex] || 0);
                var topPx = lanePadding + (lane * (laneHeight + laneGap));
                var widthPct = (duration / totalDays) * 100;
                var leftPx = ((start - 1) * dayColumnWidth) + (phaseBarGapPx / 2);
                var widthPx = (duration * dayColumnWidth) - phaseBarGapPx;
                if (leftPx < 0) {
                    leftPx = 0;
                }
                if (widthPx < 8) {
                    widthPx = 8;
                }
                if ((leftPx + widthPx) > timelineWidthPx) {
                    widthPx = Math.max(8, timelineWidthPx - leftPx);
                }
                var color = normalizeColor(phase.color);
                var icon = normalizeIcon(phase.icon || 'fa-circle');
                var phaseDescription = String(phase.description || '').trim();
                var showName = widthPct > 7;
                var commonDataset = '' +
                    ' data-project-id="' + Number(project.id) + '"' +
                    ' data-task-id="' + Number(task.id) + '"' +
                    ' data-phase-id="' + phaseId + '"' +
                    ' data-phase-name="' + escapeHtml(phase.name || '') + '"' +
                    ' data-phase-desc="' + escapeHtml(phaseDescription) + '"' +
                    ' data-phase-icon="' + escapeHtml(icon) + '"' +
                    ' data-phase-color="' + escapeHtml(color) + '"' +
                    ' data-phase-start="' + start + '"' +
                    ' data-phase-duration="' + duration + '"';

                phaseBars.push(
                    '<div class="tlp-phase-bar"' +
                        (canEdit ? ' data-action="edit-phase"' : '') +
                        commonDataset +
                        ' style="top:' + topPx + 'px;left:' + leftPx + 'px;width:' + widthPx + 'px;height:' + laneHeight + 'px;min-width:8px;background:' + color + ';box-shadow:0 2px 7px ' + color + '55">' +
                        '<i class="fa ' + icon + '"></i>' +
                        (showName ? '<span class="tlp-phase-bar-name">' + escapeHtml(phase.name || 'Phase') + '</span>' : '') +
                    '</div>'
                );

                var rowActions = '';
                if (canEdit) {
                    rowActions = '<button type="button" class="tlp-phase-remove-btn" data-action="delete-phase" data-phase-id="' + phaseId + '" title="Remove phase"><i class="fa fa-times"></i></button>';
                }

                var tracking = phase.tracking || {};
                var trackingStatusKey = safePhaseTrackingStatus(tracking.status);
                var trackingMeta = PHASE_TRACKING_META[trackingStatusKey];
                var subtasksTotal = clampNumber(tracking.subtasks_total, 0, 9999, 0);
                var memberDoneCount = clampNumber(tracking.member_done_count, 0, 9999, 0);
                var leaderDoneCount = clampNumber(tracking.leader_done_count, 0, 9999, 0);
                var trackingHint = subtasksTotal > 0
                    ? ('Member done ' + memberDoneCount + '/' + subtasksTotal + ' | Leader approved ' + leaderDoneCount + '/' + subtasksTotal)
                    : 'No linked subtasks yet';

                phaseRows.push(
                    '<div class="tlp-phase-row">' +
                        '<div class="tlp-phase-row-main">' +
                            '<i class="fa ' + icon + '"></i>' +
                            '<div class="tlp-phase-text">' +
                                '<span class="tlp-phase-name">' + escapeHtml(phase.name || 'Phase') + '</span>' +
                                '<span class="tlp-phase-desc">' + escapeHtml(phaseDescription || 'No description') + '</span>' +
                                '<span class="tlp-phase-desc" style="display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:4px;">' +
                                    '<span style="padding:2px 8px;border-radius:999px;background:' + trackingMeta.bg + ';color:' + trackingMeta.color + ';font-size:11px;font-weight:600;">' +
                                        escapeHtml(trackingMeta.label) +
                                    '</span>' +
                                    '<span style="font-size:11px;color:#6B7280;">' + escapeHtml(trackingHint) + '</span>' +
                                '</span>' +
                            '</div>' +
                            '<span class="tlp-phase-meta">' +
                                '<span class="tlp-day-badge" style="background:' + light + ';color:' + color + '">Day ' + start + '-' + endDay + '</span>' +
                                rowActions +
                            '</span>' +
                        '</div>' +
                    '</div>'
                );
            });

            if (canEdit) {
                phaseRows.push(
                    '<button type="button" class="tlp-add-phase" data-action="add-phase" data-task-id="' + Number(task.id) + '">' +
                        '<i class="fa fa-plus"></i> Add Phase' +
                    '</button>'
                );
            }

            taskHtml.push(
                '<div class="tlp-gantt-task">' +
                    '<div class="tlp-task-row" style="background:' + (taskIndex % 2 === 0 ? '#FAFAFB' : '#FFFFFF') + ';min-height:' + barAreaHeight + 'px">' +
                        '<div class="tlp-task-label">' +
                            '<div class="tlp-task-title">' + escapeHtml(task.title || 'Timeline Task') + '</div>' +
                            '<div class="tlp-task-assignee">Assignee: ' + escapeHtml(task.assignee_name || 'Unassigned') + '</div>' +
                            (canEdit
                                ? '<div class="tlp-task-actions">' +
                                    '<button type="button" class="tlp-icon-btn edit" data-action="edit-task" data-project-id="' + Number(project.id) + '" data-task-id="' + Number(task.id) + '" data-task-title="' + escapeHtml(task.title || '') + '" data-assignee-user-id="' + Number(task.assignee_user_id || 0) + '" title="Edit timeline task"><i class="fa fa-pencil"></i></button>' +
                                    '<button type="button" class="tlp-icon-btn del" data-action="delete-task" data-task-id="' + Number(task.id) + '" title="Delete timeline task"><i class="fa fa-trash"></i></button>' +
                                '</div>'
                                : '') +
                        '</div>' +
                        '<div class="tlp-bar-area" style="height:' + barAreaHeight + 'px;width:' + timelineWidthPx + 'px;min-width:' + timelineWidthPx + 'px">' + gridLines.join('') + phaseBars.join('') + '</div>' +
                    '</div>' +
                    '<div class="tlp-phase-list">' + phaseRows.join('') + '</div>' +
                '</div>'
            );
        });

        var addTaskButton = canEdit
            ? '<div class="tlp-actions-row"><button type="button" class="tlp-add-task-btn" data-action="add-task" data-project-id="' + Number(project.id) + '"><i class="fa fa-plus"></i> Add Timeline Task</button></div>'
            : '';

        return '' +
            '<div class="tlp-workspace">' +
                '<div class="tlp-gantt-scroll" style="width:100%;--tlp-day-width:' + dayColumnWidth + 'px">' +
                    '<div class="tlp-gantt-canvas">' +
                        '<div class="tlp-gantt-head">' +
                            '<div class="tlp-label-col">Task / Assignee</div>' +
                            '<div class="tlp-day-row" style="width:' + timelineWidthPx + 'px;min-width:' + timelineWidthPx + 'px">' + dayHeaders.join('') + '</div>' +
                        '</div>' +
                        taskHtml.join('') +
                    '</div>' +
                '</div>' +
            '</div>' +
            addTaskButton +
            '<div class="tlp-progress-strip">' +
                '<div class="tlp-progress-strip-label">Overall Progress</div>' +
                '<div class="tlp-progress-strip-track">' +
                    '<div class="tlp-progress-strip-fill" style="width:' + clampNumber(project.progress, 0, 100, 0) + '%;background:linear-gradient(90deg,' + accent + ',' + accent + '99)"></div>' +
                '</div>' +
                '<div class="tlp-progress-strip-pct" style="color:' + accent + '">' + clampNumber(project.progress, 0, 100, 0) + '%</div>' +
            '</div>';
    }
    function renderAdminDetail() {
        var project = getActiveProject();
        if (!project) {
            state.currentScreen = 'overview';
            setScreen('admin-overview');
            renderAdminOverview();
            return;
        }

        var colorIdx = colorIndexForProject(project);
        var accent = COLORS[colorIdx];
        var light = LIGHTS[colorIdx];
        var statusMeta = getStatusMeta(project.status);
        var canEdit = !!(project.permissions && project.permissions.can_edit);
        var projectName = project.name || 'Untitled Project';
        var leaderName = project.leader && project.leader.name ? project.leader.name : 'N/A';

        el.detailAvatar.style.background = light;
        el.detailAvatar.style.color = accent;
        el.detailAvatar.textContent = toInitials(leaderName || projectName);
        el.detailName.textContent = projectName;
        el.detailSub.textContent = 'Group: ' + (project.group || 'Project Team') + ' | Leader: ' + leaderName + ' | ' + clampNumber(project.total_days, 1, 365, 20) + ' days';
        el.detailStatus.innerHTML = '<span class="tlp-status-dot" style="background:' + statusMeta.dot + '"></span>' + escapeHtml(statusMeta.label);
        el.detailStatus.style.background = statusMeta.bg;
        el.detailStatus.style.color = statusMeta.color;
        el.detailMembers.innerHTML = renderMemberStackForDetail(project, colorIdx);
        el.detailProgress.style.background = light;
        el.detailProgress.style.color = accent;
        el.detailProgress.textContent = clampNumber(project.progress, 0, 100, 0) + '% done';

        el.adminGanttMount.innerHTML = buildGanttContent(project, canEdit, accent, light);
        bindGanttDragScroll();
        setScreen('admin-detail');
    }

    function renderProjectSwitch() {
        if (state.projects.length <= 1) {
            el.projectSwitch.innerHTML = '';
            return;
        }

        el.projectSwitch.innerHTML = state.projects.map(function (project) {
            var activeClass = Number(project.id) === Number(state.activeProjectId) ? ' active' : '';
            return '' +
                '<button type="button" class="tlp-project-pill' + activeClass + '" data-action="switch-project" data-project-id="' + Number(project.id) + '">' +
                    escapeHtml(project.name || 'Project') +
                '</button>';
        }).join('');
    }

    function renderEmployee() {
        renderProjectSwitch();
        var project = getActiveProject();
        if (!project) {
            el.employeeMount.innerHTML = '' +
                '<div class="tlp-empty" style="display:block">' +
                    '<i class="fa fa-folder-open-o"></i>' +
                    '<strong>No assigned projects</strong>' +
                    '<span>You do not have a timeline assignment yet.</span>' +
                '</div>';
            setScreen('employee');
            return;
        }

        var permissions = project.permissions || {};
        var canEdit = !!permissions.can_edit;
        var userRole = String(permissions.user_project_role || 'member').toLowerCase();
        var colorIdx = colorIndexForProject(project);
        var accent = COLORS[colorIdx];
        var light = LIGHTS[colorIdx];
        var statusMeta = getStatusMeta(project.status);

        var roleStyle = canEdit
            ? 'background:#EDE9FE;color:#5B21B6;border-color:#C4B5FD'
            : 'background:#ECFDF5;color:#065F46;border-color:#6EE7B7';
        var roleIcon = canEdit ? 'fa-user-secret' : 'fa-user';
        var roleTitle = canEdit ? 'You are the project leader.' : 'You are viewing as a team member.';
        var roleText = canEdit
            ? 'You can add timeline tasks, add/edit phases, and remove phases for this project.'
            : 'This timeline is read-only. It shows your project schedule and planned sequence.';

        var memberCards = (project.members || []).map(function (member, index) {
            var bubbleColor = COLORS[(colorIdx + index + 1) % COLORS.length];
            var isMe = Number(member.id) === Number(state.userId);
            var memberRole = String(member.role || 'member').toLowerCase();
            var roleTagStyle = memberRole === 'leader'
                ? 'background:#EDE9FE;color:#5B21B6'
                : 'background:#F3F4F6;color:#6B7280';

            return '' +
                '<div class="tlp-member-card"' + (isMe ? ' style="border-color:' + accent + '"' : '') + '>' +
                    '<span class="tlp-member-avatar" style="background:' + bubbleColor + '">' + escapeHtml(toInitials(member.name)) + '</span>' +
                    '<div>' +
                        '<div class="tlp-member-name">' + escapeHtml(member.name || 'User') + (isMe ? ' (you)' : '') + '</div>' +
                        '<span class="tlp-member-role" style="' + roleTagStyle + '">' + (memberRole === 'leader' ? 'Leader' : 'Member') + '</span>' +
                    '</div>' +
                '</div>';
        }).join('');

        var roleSubtitle = userRole === 'leader' ? 'Leader View' : 'Member View';
        var headerAvatar = toInitials(project.leader && project.leader.name ? project.leader.name : project.name);

        el.employeeMount.innerHTML = '' +
            '<div class="tlp-role-banner" style="' + roleStyle + '">' +
                '<i class="fa ' + roleIcon + '"></i>' +
                '<div><strong>' + roleTitle + '</strong> ' + roleText + '</div>' +
            '</div>' +
            '<div class="tlp-project-card">' +
                '<div class="tlp-avatar" style="background:' + light + ';color:' + accent + '">' + escapeHtml(headerAvatar) + '</div>' +
                '<div class="tlp-project-main">' +
                    '<div class="tlp-project-name">' + escapeHtml(project.name || 'Untitled Project') + '</div>' +
                    '<div class="tlp-project-sub">Group: ' + escapeHtml(project.group || 'Project Team') + ' | Leader: ' + escapeHtml(project.leader && project.leader.name ? project.leader.name : 'N/A') + ' | ' + roleSubtitle + '</div>' +
                '</div>' +
                '<div style="min-width:190px;flex:1">' +
                    '<div class="tlp-progress-head">' +
                        '<span>Progress</span>' +
                        '<span style="color:' + accent + '">' + clampNumber(project.progress, 0, 100, 0) + '%</span>' +
                    '</div>' +
                    '<div class="tlp-progress-track">' +
                        '<div class="tlp-progress-fill" style="width:' + clampNumber(project.progress, 0, 100, 0) + '%;background:linear-gradient(90deg,' + accent + ',' + accent + 'CC)"></div>' +
                    '</div>' +
                '</div>' +
                '<span class="tlp-status" style="background:' + statusMeta.bg + ';color:' + statusMeta.color + '">' +
                    '<span class="tlp-status-dot" style="background:' + statusMeta.dot + '"></span>' + escapeHtml(statusMeta.label) +
                '</span>' +
            '</div>' +
            '<div class="tlp-member-strip">' + memberCards + '</div>' +
            buildGanttContent(project, canEdit, accent, light);

        bindGanttDragScroll();
        setScreen('employee');
    }

    function renderByRole() {
        if (state.role === 'admin') {
            if (state.currentScreen === 'detail') {
                renderAdminDetail();
                return;
            }
            renderAdminOverview();
            setScreen('admin-overview');
            return;
        }
        state.currentScreen = 'employee';
        renderEmployee();
    }

    async function refreshData() {
        showAlert('');
        showLoading(true, 'Loading timeline data...');

        try {
            var data = await apiGetTimeline();
            state.projects = Array.isArray(data.projects) ? data.projects : [];
            state.todayDay = clampNumber(data.today_day, 1, 365, 1);
            if (data.csrf_token) {
                state.csrfToken = String(data.csrf_token);
            }

            if (state.activeProjectId === null && state.projects.length) {
                state.activeProjectId = Number(state.projects[0].id);
            } else if (state.activeProjectId !== null && !getProjectById(state.activeProjectId) && state.projects.length) {
                state.activeProjectId = Number(state.projects[0].id);
            }
            renderByRole();
        } catch (error) {
            showAlert(error && error.message ? error.message : 'Unable to load timeline.');
            if (state.role === 'admin') {
                setScreen('admin-overview');
                el.statsGrid.innerHTML = '';
                el.tileGrid.innerHTML = '';
                el.adminEmpty.style.display = 'block';
            } else {
                setScreen('employee');
                el.employeeMount.innerHTML =
                    '<div class="tlp-empty" style="display:block"><i class="fa fa-warning"></i><strong>Unable to load timeline</strong><span>Please refresh the page.</span></div>';
            }
        } finally {
            showLoading(false);
        }
    }
    function openTaskModal(projectId, taskPayload) {
        var pid = Number(projectId || 0);
        var project = getProjectById(pid);
        if (!project) {
            return;
        }
        var editingTaskId = taskPayload && taskPayload.taskId ? Number(taskPayload.taskId) : null;
        var selectedAssigneeId = taskPayload && taskPayload.assigneeUserId ? Number(taskPayload.assigneeUserId) : 0;

        state.taskModalProjectId = pid;
        state.editingTaskId = editingTaskId;
        el.taskModalTitle.innerHTML = editingTaskId
            ? '<i class="fa fa-pencil"></i> Edit Timeline Task'
            : '<i class="fa fa-tasks"></i> Add Timeline Task';
        el.taskSaveBtn.textContent = editingTaskId ? 'Save Task' : 'Add Task';
        el.taskNameInput.value = taskPayload && taskPayload.title ? taskPayload.title : '';

        var options = ['<option value="">Unassigned</option>'];
        (project.members || []).forEach(function (member) {
            var memberId = Number(member.id || 0);
            var selected = memberId === selectedAssigneeId ? ' selected' : '';
            options.push('<option value="' + memberId + '"' + selected + '>' + escapeHtml(member.name || 'User') + '</option>');
        });
        el.taskAssigneeSelect.innerHTML = options.join('');
        el.taskModalWrap.classList.add('show');
        window.setTimeout(function () {
            el.taskNameInput.focus();
        }, 0);
    }

    function closeTaskModal() {
        state.taskModalProjectId = null;
        state.editingTaskId = null;
        el.taskModalTitle.innerHTML = '<i class="fa fa-tasks"></i> Add Timeline Task';
        el.taskSaveBtn.textContent = 'Add Task';
        el.taskModalWrap.classList.remove('show');
    }

    async function submitTaskModal() {
        var projectId = Number(state.taskModalProjectId || 0);
        var editingTaskId = Number(state.editingTaskId || 0);
        var title = String(el.taskNameInput.value || '').trim();
        var assigneeUserId = Number(el.taskAssigneeSelect.value || 0);
        if (!projectId) {
            return;
        }
        if (!title) {
            showAlert('Task title is required.');
            el.taskNameInput.focus();
            return;
        }

        showAlert('');
        el.taskSaveBtn.disabled = true;
        try {
            await apiPost('app/timeline/save_task.php', {
                task_id: editingTaskId > 0 ? editingTaskId : '',
                project_id: projectId,
                title: title,
                assignee_user_id: assigneeUserId > 0 ? assigneeUserId : ''
            });
            closeTaskModal();
            await refreshData();
        } catch (error) {
            showAlert(error && error.message ? error.message : 'Unable to save timeline task.');
        } finally {
            el.taskSaveBtn.disabled = false;
        }
    }

    function renderIconChoices() {
        el.iconGrid.innerHTML = ICON_CHOICES.map(function (icon) {
            var active = state.selectedIcon === icon ? ' active' : '';
            return '<button type="button" class="tlp-icon-choice' + active + '" data-icon="' + icon + '"><i class="fa ' + icon + '"></i></button>';
        }).join('');
    }

    function renderColorChoices() {
        el.colorGrid.innerHTML = COLOR_CHOICES.map(function (color) {
            var active = state.selectedColor === color ? ' active' : '';
            return '<button type="button" class="tlp-color-choice' + active + '" data-color="' + color + '" style="background:' + color + '"></button>';
        }).join('');
    }

    function openPhaseModal(taskId, phasePayload) {
        state.phaseModalTaskId = Number(taskId || 0);
        state.editingPhaseId = phasePayload && phasePayload.phaseId ? Number(phasePayload.phaseId) : null;
        state.phaseDayLimit = getPhaseDayLimitForTask(state.phaseModalTaskId);

        var phaseGuideItems = getPhaseGuideItemsForTask(state.phaseModalTaskId, state.editingPhaseId);
        var suggestedStart = getSuggestedStartDay(phaseGuideItems, state.phaseDayLimit);

        var initialStart = phasePayload && phasePayload.startDay ? Number(phasePayload.startDay) : suggestedStart;
        var normalizedStart = clampNumber(initialStart, 1, state.phaseDayLimit, 1);
        var initialDuration = phasePayload && phasePayload.durationDays ? Number(phasePayload.durationDays) : 3;
        var maxDuration = Math.max(1, (state.phaseDayLimit - normalizedStart) + 1);
        var normalizedDuration = clampNumber(initialDuration, 1, maxDuration, Math.min(3, maxDuration));

        el.phaseModalTitle.innerHTML = state.editingPhaseId
            ? '<i class="fa fa-pencil"></i> Edit Phase'
            : '<i class="fa fa-calendar"></i> Add Phase';
        el.phaseNameInput.value = phasePayload && phasePayload.name ? phasePayload.name : '';
        el.phaseDescInput.value = phasePayload && phasePayload.description ? phasePayload.description : '';
        el.phaseStartInput.value = String(normalizedStart);
        el.phaseStartInput.max = String(state.phaseDayLimit);
        el.phaseDurationInput.value = String(normalizedDuration);
        el.phaseDurationInput.max = String(maxDuration);
        state.selectedIcon = normalizeIcon(phasePayload && phasePayload.icon ? phasePayload.icon : 'fa-circle');
        state.selectedColor = normalizeColor(phasePayload && phasePayload.color ? phasePayload.color : '#6C3CE1');
        renderIconChoices();
        renderColorChoices();
        syncPhaseDayInputs({ forceNormalize: true });
        el.phaseModalWrap.classList.add('show');
        window.setTimeout(function () {
            el.phaseNameInput.focus();
        }, 0);
    }

    function closePhaseModal() {
        state.phaseModalTaskId = null;
        state.phaseDayLimit = 365;
        state.editingPhaseId = null;
        if (el.phaseGuideList) {
            el.phaseGuideList.innerHTML = '';
        }
        if (el.phaseGuideCount) {
            el.phaseGuideCount.textContent = '0 phases';
        }
        setPhaseGuideTip('', '');
        el.phaseModalWrap.classList.remove('show');
    }

    function openConfirmModal(title, message, confirmLabel, onConfirm) {
        state.confirmAction = typeof onConfirm === 'function' ? onConfirm : null;
        el.confirmTitle.innerHTML = '<i class="fa fa-exclamation-triangle"></i> ' + escapeHtml(title || 'Confirm action');
        el.confirmMessage.textContent = String(message || 'Are you sure you want to continue?');
        el.confirmBtn.textContent = String(confirmLabel || 'Confirm');
        el.confirmBtn.disabled = false;
        el.confirmModalWrap.classList.add('show');
    }

    function closeConfirmModal() {
        state.confirmAction = null;
        el.confirmBtn.disabled = false;
        el.confirmModalWrap.classList.remove('show');
    }

    async function submitConfirmModal() {
        if (typeof state.confirmAction !== 'function') {
            closeConfirmModal();
            return;
        }
        var action = state.confirmAction;
        el.confirmBtn.disabled = true;
        try {
            await action();
        } finally {
            closeConfirmModal();
        }
    }

    async function submitPhaseModal() {
        var taskId = Number(state.phaseModalTaskId || 0);
        var name = String(el.phaseNameInput.value || '').trim();
        var description = String(el.phaseDescInput.value || '').trim();
        var dayLimit = clampNumber(state.phaseDayLimit, 1, 365, 365);
        var startDay = clampNumber(el.phaseStartInput.value, 1, dayLimit, 1);
        var maxDuration = Math.max(1, (dayLimit - startDay) + 1);
        var durationDays = clampNumber(el.phaseDurationInput.value, 1, maxDuration, 1);
        if (!taskId) {
            return;
        }
        if (!name) {
            showAlert('Phase name is required.');
            el.phaseNameInput.focus();
            return;
        }

        el.phaseStartInput.value = String(startDay);
        el.phaseDurationInput.max = String(maxDuration);
        el.phaseDurationInput.value = String(durationDays);

        showAlert('');
        el.phaseSaveBtn.disabled = true;
        try {
            await apiPost('app/timeline/save_phase.php', {
                phase_id: state.editingPhaseId ? Number(state.editingPhaseId) : '',
                timeline_task_id: taskId,
                name: name,
                description: description,
                icon: state.selectedIcon,
                color: state.selectedColor,
                start_day: startDay,
                duration_days: durationDays
            });
            closePhaseModal();
            await refreshData();
        } catch (error) {
            showAlert(error && error.message ? error.message : 'Unable to save phase.');
        } finally {
            el.phaseSaveBtn.disabled = false;
        }
    }

    async function deletePhase(phaseId) {
        var normalizedPhaseId = Number(phaseId || 0);
        if (!normalizedPhaseId) {
            return;
        }
        openConfirmModal('Delete phase', 'Remove this phase from the timeline?', 'Delete', async function () {
            showAlert('');
            try {
                await apiPost('app/timeline/delete_phase.php', { phase_id: normalizedPhaseId });
                await refreshData();
            } catch (error) {
                showAlert(error && error.message ? error.message : 'Unable to delete phase.');
            }
        });
    }

    function openTaskFromDataset(dataset) {
        var projectId = Number(dataset.projectId || 0);
        if (!projectId) {
            return;
        }
        openTaskModal(projectId, {
            taskId: Number(dataset.taskId || 0),
            title: String(dataset.taskTitle || ''),
            assigneeUserId: Number(dataset.assigneeUserId || 0)
        });
    }

    async function deleteTask(taskId) {
        var normalizedTaskId = Number(taskId || 0);
        if (!normalizedTaskId) {
            return;
        }
        openConfirmModal('Delete timeline task', 'Delete this timeline task and all of its phases?', 'Delete Task', async function () {
            showAlert('');
            try {
                await apiPost('app/timeline/delete_task.php', { task_id: normalizedTaskId });
                await refreshData();
            } catch (error) {
                showAlert(error && error.message ? error.message : 'Unable to delete timeline task.');
            }
        });
    }

    function openPhaseFromDataset(dataset) {
        var taskId = Number(dataset.taskId || 0);
        if (!taskId) {
            return;
        }
        openPhaseModal(taskId, {
            phaseId: Number(dataset.phaseId || 0),
            name: String(dataset.phaseName || ''),
            description: String(dataset.phaseDesc || ''),
            icon: String(dataset.phaseIcon || 'fa-circle'),
            color: String(dataset.phaseColor || '#6C3CE1'),
            startDay: clampNumber(dataset.phaseStart, 1, 365, 1),
            durationDays: clampNumber(dataset.phaseDuration, 1, 180, 1)
        });
    }

    function showTooltipFromTarget(event, target) {
        var phaseName = target.dataset.phaseName || '';
        var phaseDesc = target.dataset.phaseDesc || '';
        var start = clampNumber(target.dataset.phaseStart, 1, 365, 1);
        var duration = clampNumber(target.dataset.phaseDuration, 1, 180, 1);
        var end = start + duration - 1;
        el.tooltipTitle.textContent = phaseName || 'Phase';
        el.tooltipDesc.textContent = phaseDesc || 'No description';
        el.tooltipDays.textContent = 'Day ' + start + ' - ' + end + ' (' + duration + 'd)';
        el.tooltip.classList.add('show');
        moveTooltip(event);
    }

    function moveTooltip(event) {
        if (!el.tooltip.classList.contains('show')) {
            return;
        }
        el.tooltip.style.left = (event.clientX + 14) + 'px';
        el.tooltip.style.top = (event.clientY - 44) + 'px';
    }

    function hideTooltip() {
        el.tooltip.classList.remove('show');
    }

    function bindGanttDragScroll() {
        document.querySelectorAll('.tlp-gantt-scroll').forEach(function (scrollEl) {
            if (!scrollEl || scrollEl.dataset.dragBound === '1') {
                return;
            }
            scrollEl.dataset.dragBound = '1';

            var isDragging = false;
            var startX = 0;
            var startScroll = 0;
            var interactiveSelector = 'button, a, input, select, textarea, label, [data-action], [role="button"]';

            function stopDragging() {
                isDragging = false;
                scrollEl.classList.remove('dragging');
            }

            scrollEl.addEventListener('pointerdown', function (event) {
                if (event.pointerType === 'mouse' && event.button !== 0) {
                    return;
                }
                if (event.target && event.target.closest(interactiveSelector)) {
                    return;
                }
                isDragging = true;
                startX = event.clientX;
                startScroll = scrollEl.scrollLeft;
                scrollEl.classList.add('dragging');
                if (typeof scrollEl.setPointerCapture === 'function') {
                    scrollEl.setPointerCapture(event.pointerId);
                }
            });

            scrollEl.addEventListener('pointermove', function (event) {
                if (!isDragging) {
                    return;
                }
                var delta = event.clientX - startX;
                if (Math.abs(delta) < 6) {
                    return;
                }
                scrollEl.scrollLeft = startScroll - delta;
                event.preventDefault();
            });

            scrollEl.addEventListener('pointerup', stopDragging);
            scrollEl.addEventListener('pointercancel', stopDragging);
            scrollEl.addEventListener('mouseleave', stopDragging);
        });
    }

    function handleActionClick(target) {
        var action = target.dataset.action;
        if (!action) {
            return;
        }
        if (action === 'add-task') {
            openTaskModal(Number(target.dataset.projectId || 0), null);
            return;
        }
        if (action === 'edit-task') {
            openTaskFromDataset(target.dataset);
            return;
        }
        if (action === 'delete-task') {
            deleteTask(Number(target.dataset.taskId || 0));
            return;
        }
        if (action === 'add-phase') {
            openPhaseModal(Number(target.dataset.taskId || 0), null);
            return;
        }
        if (action === 'edit-phase') {
            openPhaseFromDataset(target.dataset);
            return;
        }
        if (action === 'delete-phase') {
            deletePhase(Number(target.dataset.phaseId || 0));
            return;
        }
        if (action === 'switch-project') {
            state.activeProjectId = Number(target.dataset.projectId || 0);
            renderEmployee();
        }
    }

    function bindEvents() {
        var resizeTimer = null;

        document.querySelectorAll('.tlp-filter-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                document.querySelectorAll('.tlp-filter-btn').forEach(function (btn) {
                    btn.classList.remove('active');
                });
                button.classList.add('active');
                state.activeFilter = button.dataset.filter || 'all';
                renderAdminOverview();
            });
        });

        el.search.addEventListener('input', function (event) {
            state.searchValue = String(event.target.value || '');
            renderAdminOverview();
        });

        el.backBtn.addEventListener('click', function () {
            state.currentScreen = 'overview';
            renderAdminOverview();
            setScreen('admin-overview');
        });

        el.taskCancelBtn.addEventListener('click', closeTaskModal);
        el.taskSaveBtn.addEventListener('click', submitTaskModal);
        el.taskModalWrap.addEventListener('click', function (event) {
            if (event.target === el.taskModalWrap) {
                closeTaskModal();
            }
        });

        el.phaseCancelBtn.addEventListener('click', closePhaseModal);
        el.phaseSaveBtn.addEventListener('click', submitPhaseModal);
        el.phaseStartInput.addEventListener('input', syncPhaseDayInputs);
        el.phaseDurationInput.addEventListener('input', syncPhaseDayInputs);
        el.phaseModalWrap.addEventListener('click', function (event) {
            if (event.target === el.phaseModalWrap) {
                closePhaseModal();
            }
        });

        el.confirmCancelBtn.addEventListener('click', closeConfirmModal);
        el.confirmBtn.addEventListener('click', submitConfirmModal);
        el.confirmModalWrap.addEventListener('click', function (event) {
            if (event.target === el.confirmModalWrap) {
                closeConfirmModal();
            }
        });

        document.addEventListener('click', function (event) {
            var actionTarget = event.target.closest('[data-action]');
            if (actionTarget) {
                handleActionClick(actionTarget);
                return;
            }
            var iconChoice = event.target.closest('.tlp-icon-choice');
            if (iconChoice && iconChoice.dataset.icon) {
                state.selectedIcon = normalizeIcon(iconChoice.dataset.icon);
                renderIconChoices();
                return;
            }
            var colorChoice = event.target.closest('.tlp-color-choice');
            if (colorChoice && colorChoice.dataset.color) {
                state.selectedColor = normalizeColor(colorChoice.dataset.color);
                renderColorChoices();
            }
        });

        document.addEventListener('mouseover', function (event) {
            var bar = event.target.closest('.tlp-phase-bar');
            if (bar) {
                showTooltipFromTarget(event, bar);
            }
        });

        document.addEventListener('mousemove', function (event) {
            moveTooltip(event);
        });

        document.addEventListener('mouseout', function (event) {
            var bar = event.target.closest('.tlp-phase-bar');
            if (bar) {
                hideTooltip();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeTaskModal();
                closePhaseModal();
                closeConfirmModal();
                hideTooltip();
            }
        });

        window.addEventListener('resize', function () {
            if (resizeTimer) {
                window.clearTimeout(resizeTimer);
            }
            resizeTimer = window.setTimeout(function () {
                if (!state.projects.length) {
                    return;
                }
                renderByRole();
            }, 120);
        });
    }

    bindEvents();
    refreshData();
})();
