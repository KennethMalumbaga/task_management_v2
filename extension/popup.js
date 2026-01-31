document.addEventListener('DOMContentLoaded', async () => {
    const statusDiv = document.getElementById('status');
    const logsDiv = document.getElementById('logs');
    const refreshBtn = document.getElementById('refreshLogs');
    const clearBtn = document.getElementById('clearLogs');

    async function updateStatus() {
        const data = await chrome.storage.local.get(['captureActive', 'attendanceId']);
        if (data.captureActive) {
            statusDiv.textContent = 'Active (Attendance ID: ' + data.attendanceId + ')';
            statusDiv.className = 'status active';
        } else {
            statusDiv.textContent = 'Inactive';
            statusDiv.className = 'status inactive';
        }
    }

    async function showLogs() {
        const data = await chrome.storage.local.get(['debugLogs']);
        const logs = data.debugLogs || [];
        logsDiv.innerHTML = logs.map(log => `<div>${log}</div>`).join('');
    }

    refreshBtn.addEventListener('click', showLogs);

    clearBtn.addEventListener('click', async () => {
        await chrome.storage.local.set({ debugLogs: [] });
        showLogs();
    });

    // Auto update
    updateStatus();
    showLogs();
    setInterval(updateStatus, 1000);
});
