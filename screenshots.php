<?php 
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id']) && $_SESSION['role'] == "admin") {
    include "DB_connection.php";
    include "app/model/user.php";

    // Get filter parameters
    $filter_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
    $filter_date = isset($_GET['date']) ? $_GET['date'] : null;

        // Build query: fetch attendance data with time_in/time_out
        $sql = "SELECT s.*, u.full_name, u.username, a.att_date, a.time_in, a.time_out 
            FROM screenshots s 
            INNER JOIN users u ON s.user_id = u.id 
            LEFT JOIN attendance a ON s.attendance_id = a.id 
            WHERE 1=1";
    $params = [];

    if ($filter_user_id) {
        $sql .= " AND s.user_id = ?";
        $params[] = $filter_user_id;
    }

    if ($filter_date) {
        $sql .= " AND DATE(s.taken_at) = ?";
        $params[] = $filter_date;
    }

    $sql .= " ORDER BY s.taken_at DESC";

    $stmt = $pdo->prepare($sql);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $screenshots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all users for filter dropdown
    $users = get_all_users($pdo);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Captures | TaskFlow</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    
    <!-- Sidebar -->
    <?php include "inc/new_sidebar.php"; ?>

    <!-- Main Content -->
    <div class="dash-main">
        
        <!-- Page Header -->
        <div style="margin-bottom: 20px;">
            <h2 style="font-size: 24px; font-weight: 700; color: var(--text-dark); margin: 0 0 8px 0;">Captures</h2>
            <span style="color: var(--text-gray); font-size: 14px;">Monitor employee activity</span>
        </div>
        
        <!-- Filter Section -->
        <div style="background: white; padding: 16px; border-radius: 12px; border: 1px solid #E5E7EB; margin-bottom: 24px;">
            <form method="GET" action="screenshots.php" style="display: flex; flex-wrap: wrap; gap: 10px; margin: 0; align-items: center;">
                <select name="user_id" class="form-input" style="flex: 1; min-width: 180px; margin: 0; padding: 10px; border-radius: 8px; border: 1px solid #E5E7EB;">
                    <option value="">All Employees</option>
                    <?php foreach ($users as $user) { 
                        if ($user['role'] == 'employee') { ?>
                            <option value="<?=$user['id']?>" <?=($filter_user_id == $user['id']) ? 'selected' : ''?>>
                                <?=$user['full_name']?>
                            </option>
                    <?php } } ?>
                </select>
                <input type="date" name="date" value="<?=$filter_date?>" class="form-input" style="flex: 1; min-width: 150px; margin: 0; padding: 10px; border-radius: 8px; border: 1px solid #E5E7EB;">
                <button type="submit" class="btn-primary" style="padding: 10px 20px; white-space: nowrap;">
                    <i class="fa fa-filter"></i> Filter
                </button>
                <a href="screenshots.php" class="btn-outline" style="padding: 10px 20px; white-space: nowrap; text-decoration: none;">
                    <i class="fa fa-refresh"></i> Reset
                </a>
            </form>
        </div>

        <!-- Screenshots Grid -->
        <div id="screenshotsContainer" 
                data-user-id="<?=htmlspecialchars($filter_user_id ?? '')?>" 
                data-date="<?=htmlspecialchars($filter_date ?? '')?>">
            <?php if (!empty($screenshots)) { ?>
                <div class="grid-container" id="screenshotsGrid">
                    <?php foreach ($screenshots as $screenshot) { 
                        $imagePath = $screenshot['image_path'];
                        $fileExists = file_exists($imagePath);
                        $imageUrl = null;
                        if ($fileExists && file_exists($imagePath)) {
                            $mtime = @filemtime($imagePath);
                            $imageUrl = $imagePath . '?t=' . ($mtime ? $mtime : time());
                        }
                    ?>
                        <div class="capture-card" data-screenshot-id="<?=$screenshot['id']?>">
                            <?php if ($fileExists) { ?>
                                <img src="<?=$imageUrl?>" alt="Screenshot" style="width: 100%; height: 180px; object-fit: cover; border-radius: 8px; border: 1px solid #eee; cursor: pointer;"
                                        onclick="showFullImage('<?=$imagePath?>', '<?=htmlspecialchars($screenshot['full_name'])?>', '<?=$screenshot['taken_at']?>')">
                            <?php } else { ?>
                                <div style="width: 100%; height: 180px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                                    <i class="fa fa-image" style="font-size: 32px; color: #ccc;"></i>
                                </div>
                            <?php } ?>
                            
                            <div style="text-align: left; margin-top: 15px;">
                                <div style="font-weight: 600; font-size: 14px; margin-bottom: 5px;">
                                    <?= htmlspecialchars($screenshot['full_name']) ?>
                                </div>
                                <div style="font-size: 12px; color: var(--text-gray);">
                                    <?= date('M d, Y h:i A', strtotime($screenshot['taken_at'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <div style="padding: 40px; text-align: center; color: var(--text-gray);">
                    <i class="fa fa-camera" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;"></i>
                    <h3>No screenshots found</h3>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- Modal for Full Image -->
    <div id="imageModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.9);">
        <div style="position: relative; margin: auto; padding: 20px; width: 90%; max-width: 1200px; top: 50%; transform: translateY(-50%);">
            <span onclick="closeModal()" style="position: absolute; top: 10px; right: 25px; color: #f1f1f1; font-size: 35px; font-weight: bold; cursor: pointer;">&times;</span>
            <img id="modalImage" class="modal-image" style="display: block; margin: auto; max-height: 90vh;">
            <div id="modalInfo" style="color: white; text-align: center; margin-top: 15px; font-size: 16px;"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <?php include 'inc/modals.php'; ?>

    <script type="text/javascript">
        var active = document.querySelector("#navList li:nth-child(5)");
        if (active) {
            active.classList.add("active");
        }

        function showFullImage(imagePath, employeeName, takenAt) {
            var modal = document.getElementById("imageModal");
            var modalImg = document.getElementById("modalImage");
            var modalInfo = document.getElementById("modalInfo");
            
            modal.style.display = "block";
            modalImg.src = imagePath;
            modalInfo.innerHTML = "<strong>" + employeeName + "</strong><br>Taken at: " + takenAt;
        }

        function closeModal() {
            var modal = document.getElementById("imageModal");
            modal.style.display = "none";
        }

        // Close modal when clicking outside the image
        window.onclick = function(event) {
            var modal = document.getElementById("imageModal");
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });

        // Auto-refresh screenshots
        (function() {
            var container = document.getElementById('screenshotsContainer');
            if (!container) return;

            var refreshInterval = null;
            var isRefreshing = false;

            function fetchScreenshots() {
                if (isRefreshing) return;
                isRefreshing = true;

                var userId = container.getAttribute('data-user-id') || '';
                var date = container.getAttribute('data-date') || '';
                
                var url = 'get_screenshots_api.php';
                var params = [];
                if (userId) params.push('user_id=' + encodeURIComponent(userId));
                if (date) params.push('date=' + encodeURIComponent(date));
                if (params.length > 0) url += '?' + params.join('&');

                var xhr = new XMLHttpRequest();
                xhr.open('GET', url, true);
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        isRefreshing = false;
                        if (xhr.status === 200) {
                            try {
                                var response = JSON.parse(xhr.responseText);
                                if (response.status === 'success') {
                                    updateScreenshots(response.screenshots);
                                }
                            } catch (e) {
                                console.error('Error parsing response:', e);
                            }
                        }
                    }
                };
                xhr.send();
            }

            function updateScreenshots(screenshots) {
                var grid = document.getElementById('screenshotsGrid');
                var emptyState = document.getElementById('emptyState');
                
                if (screenshots.length === 0) {
                    // Show empty state
                    if (grid) {
                        grid.style.display = 'none';
                    }
                    if (!emptyState) {
                        var container = document.getElementById('screenshotsContainer');
                        container.innerHTML = '<div class="empty-state" id="emptyState">' +
                            '<i class="fa fa-image"></i>' +
                            '<h3>No Screenshots Found</h3>' +
                            '<p>No screenshots match your filter criteria.</p>' +
                            '</div>';
                    } else {
                        emptyState.style.display = 'block';
                    }
                    return;
                }

                // Hide empty state
                if (emptyState) {
                    emptyState.style.display = 'none';
                }

                // Create or get grid
                if (!grid) {
                    grid = document.createElement('div');
                    grid.className = 'screenshots-container';
                    grid.id = 'screenshotsGrid';
                    container.innerHTML = '';
                    container.appendChild(grid);
                } else {
                    grid.style.display = 'grid';
                }

                // Create maps of existing screenshots by ID and attendance_id
                var existingCards = {};
                var cardsByAttendanceId = {};
                var cards = grid.querySelectorAll('.screenshot-card');
                cards.forEach(function(card) {
                    var id = card.getAttribute('data-screenshot-id');
                    var attendanceId = card.getAttribute('data-attendance-id');
                    if (id) {
                        existingCards[id] = card;
                    }
                    if (attendanceId && attendanceId !== 'null' && attendanceId !== '') {
                        cardsByAttendanceId[attendanceId] = card;
                    }
                });

                // Update or create screenshot cards
                screenshots.forEach(function(screenshot) {
                    var card = existingCards[screenshot.id];
                    
                    // If screenshot ID not found but attendance_id exists, update that card (handles screenshot replacement)
                    if (!card && screenshot.attendance_id) {
                        card = cardsByAttendanceId[screenshot.attendance_id];
                        if (card) {
                            // Update the card's screenshot ID
                            card.setAttribute('data-screenshot-id', screenshot.id);
                        }
                    }
                    
                    if (card) {
                        // Update existing card
                        var img = card.querySelector('.screenshot-thumbnail');
                        if (img && screenshot.image_url) {
                            // Always update image URL to ensure fresh image (handles screenshot replacement)
                            img.src = screenshot.image_url;
                        } else if (!screenshot.file_exists && img) {
                            // Replace image with placeholder
                            var placeholder = document.createElement('div');
                            placeholder.style.cssText = 'width: 100%; height: 200px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 4px;';
                            placeholder.innerHTML = '<i class="fa fa-image" style="font-size: 48px; color: #ccc;"></i>';
                            img.parentNode.replaceChild(placeholder, img);
                        } else if (screenshot.file_exists && !img) {
                            // Restore image if it was a placeholder
                            var placeholder = card.querySelector('div[style*="background: #f0f0f0"]');
                            if (placeholder) {
                                var newImg = document.createElement('img');
                                newImg.src = screenshot.image_url;
                                newImg.alt = 'Screenshot';
                                newImg.className = 'screenshot-thumbnail';
                                newImg.onclick = function() {
                                    showFullImage(screenshot.image_path, screenshot.full_name, screenshot.taken_at);
                                };
                                placeholder.parentNode.replaceChild(newImg, placeholder);
                            }
                        }
                        
                        // Update info
                        var info = card.querySelector('.screenshot-info');
                        if (info) {
                            var html = '<p><strong>Employee:</strong> ' + escapeHtml(screenshot.full_name) + ' (@' + escapeHtml(screenshot.username) + ')</p>' +
                                      '<p><strong>Taken At:</strong> ' + screenshot.taken_at_formatted + '</p>';
                            if (screenshot.time_in) {
                                html += '<p><strong>Time In:</strong> ' + screenshot.time_in + '</p>';
                            }
                            if (screenshot.time_out) {
                                html += '<p><strong>Time Out:</strong> ' + screenshot.time_out + '</p>';
                            }
                            info.innerHTML = html;
                        }
                    } else {
                        // Create new card
                        var newCard = createScreenshotCard(screenshot);
                        grid.appendChild(newCard);
                    }
                });

                // Remove cards that no longer exist
                var currentIds = screenshots.map(function(s) { return s.id.toString(); });
                cards.forEach(function(card) {
                    var id = card.getAttribute('data-screenshot-id');
                    if (id && currentIds.indexOf(id) === -1) {
                        card.remove();
                    }
                });

                // Re-sort cards by taken_at (newest first)
                var allCards = Array.from(grid.querySelectorAll('.screenshot-card'));
                allCards.sort(function(a, b) {
                    var aId = parseInt(a.getAttribute('data-screenshot-id'));
                    var bId = parseInt(b.getAttribute('data-screenshot-id'));
                    var aScreenshot = screenshots.find(function(s) { return s.id === aId; });
                    var bScreenshot = screenshots.find(function(s) { return s.id === bId; });
                    if (!aScreenshot || !bScreenshot) return 0;
                    return new Date(bScreenshot.taken_at) - new Date(aScreenshot.taken_at);
                });
                allCards.forEach(function(card) {
                    grid.appendChild(card);
                });
            }

            function createScreenshotCard(screenshot) {
                var card = document.createElement('div');
                card.className = 'screenshot-card';
                card.setAttribute('data-screenshot-id', screenshot.id);
                if (screenshot.attendance_id) {
                    card.setAttribute('data-attendance-id', screenshot.attendance_id);
                }

                var imageHtml = '';
                if (screenshot.file_exists && screenshot.image_url) {
                    imageHtml = '<img src="' + screenshot.image_url + '" alt="Screenshot" class="screenshot-thumbnail" ' +
                               'onclick="showFullImage(\'' + escapeHtml(screenshot.image_path) + '\', \'' + 
                               escapeHtml(screenshot.full_name) + '\', \'' + screenshot.taken_at + '\')">';
                } else {
                    imageHtml = '<div style="width: 100%; height: 200px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 4px;">' +
                               '<i class="fa fa-image" style="font-size: 48px; color: #ccc;"></i></div>';
                }

                var infoHtml = '<div class="screenshot-info">' +
                              '<p><strong>Employee:</strong> ' + escapeHtml(screenshot.full_name) + ' (@' + escapeHtml(screenshot.username) + ')</p>' +
                              '<p><strong>Taken At:</strong> ' + screenshot.taken_at_formatted + '</p>';
                if (screenshot.time_in) {
                    infoHtml += '<p><strong>Time In:</strong> ' + screenshot.time_in + '</p>';
                }
                if (screenshot.time_out) {
                    infoHtml += '<p><strong>Time Out:</strong> ' + screenshot.time_out + '</p>';
                }
                infoHtml += '</div>';

                card.innerHTML = imageHtml + infoHtml;
                return card;
            }

            function escapeHtml(text) {
                var div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Auto-refresh DISABLED - page will only update on manual refresh
            // This prevents new screenshots from popping up and breaking the layout
            /*
            function startAutoRefresh() {
                if (refreshInterval) return;
                refreshInterval = setInterval(fetchScreenshots, 5000);
            }

            function stopAutoRefresh() {
                if (refreshInterval) {
                    clearInterval(refreshInterval);
                    refreshInterval = null;
                }
            }

            // Start auto-refresh when page loads
            startAutoRefresh();

            // Stop auto-refresh when page is hidden, resume when visible
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    stopAutoRefresh();
                } else {
                    startAutoRefresh();
                    fetchScreenshots(); // Refresh immediately when page becomes visible
                }
            });

            // Clean up on page unload
            window.addEventListener('beforeunload', function() {
                stopAutoRefresh();
            });
            */
        })();
    </script>
</body>
</html>
<?php 
} else { 
    $em = "First login";
    header("Location: login.php?error=$em");
    exit();
}
?>



