<?php
// Redirect logged-in users to the dashboard
session_start();
if (isset($_SESSION['role']) && isset($_SESSION['id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TaskFlow — Manage Teams, Track Progress</title>
    <meta name="description" content="The complete task management platform with time tracking, screen monitoring, performance ratings, and real-time collaboration. Built for modern teams.">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <!-- Landing Page CSS -->
    <link rel="stylesheet" href="css/landing.css">
</head>
<body class="landing-page">

    <!-- ===== NAVBAR ===== -->
    <nav class="landing-navbar" id="landingNav">
        <a href="landing.php" class="landing-navbar-brand">
            <img src="img/logo.png" alt="TaskFlow" class="brand-logo">
            <img src="img/logo2.png" alt="Nehemiah Solutions" class="brand-logo-secondary">
        </a>

        <ul class="landing-navbar-links" id="navLinks">
            <li><a href="#features">Features</a></li>
            <li><a href="#pricing">Pricing</a></li>
            <li><a href="#faq">FAQ</a></li>
        </ul>

        <div class="landing-navbar-actions">
            <a href="login.php" class="landing-btn-signin">Sign In</a>
            <a href="signup.php" class="landing-btn-getstarted">Get Started</a>
        </div>

        <button class="landing-hamburger" id="hamburger" aria-label="menu">
            <span></span><span></span><span></span>
        </button>
    </nav>

    <!-- ===== HERO SECTION ===== -->
    <section class="landing-hero">
        <div class="landing-hero-content landing-animate">
            <div class="landing-hero-badge">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                Trusted by 50,000+ teams worldwide
            </div>

            <h1>Manage Teams, <span class="highlight">Track Progress</span></h1>

            <p class="landing-hero-desc">
                The complete task management platform with time tracking, screen monitoring, performance ratings, and real-time collaboration. Built for modern teams.
            </p>

            <div class="landing-hero-buttons">
                <a href="signup.php" class="landing-btn-trial">
                    Start Free Trial
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
                <button class="landing-btn-demo" onclick="document.getElementById('demo').scrollIntoView({behavior:'smooth'})">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    Watch Demo
                </button>
            </div>

            <div class="landing-hero-checks">
                <span><span class="check-icon">&#10003;</span> No credit card required</span>
                <span><span class="check-icon">&#10003;</span> 2-day free trial</span>
            </div>
        </div>

        <div class="landing-hero-visual landing-animate landing-animate-delay-2">
            <div class="landing-dashboard-card">
                <div class="landing-dashboard-dots">
                    <span></span><span></span><span></span>
                </div>

                <div class="landing-dashboard-main">
                    <span class="label">Active Tasks</span>
                    <span class="value">24</span>
                </div>
                <div class="landing-dashboard-bar"></div>

                <div class="landing-dashboard-stats" style="margin-top: 14px;">
                    <div class="landing-dashboard-stat">
                        <div class="stat-label">
                            <span class="stat-icon"><i class="fa fa-clock-o"></i></span>
                            Time Tracked
                        </div>
                        <div class="stat-value">42h</div>
                    </div>
                    <div class="landing-dashboard-stat">
                        <div class="stat-label">
                            <span class="stat-icon"><i class="fa fa-star-o"></i></span>
                            Avg Rating
                        </div>
                        <div class="stat-value">4.8</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== FEATURES SECTION ===== -->
    <section class="landing-features" id="features">
        <div class="landing-features-grid">
            <div class="landing-feature-card landing-animate">
                <div class="landing-feature-icon purple">
                    <i class="fa fa-calendar"></i>
                </div>
                <h3>Smart Scheduling</h3>
                <p>Integrated calendar for task planning and deadline management</p>
            </div>

            <div class="landing-feature-card landing-animate landing-animate-delay-1">
                <div class="landing-feature-icon amber">
                    <i class="fa fa-star"></i>
                </div>
                <h3>Performance Rating</h3>
                <p>Rate and review team member performance with detailed analytics</p>
            </div>

            <div class="landing-feature-card landing-animate landing-animate-delay-2">
                <div class="landing-feature-icon blue">
                    <i class="fa fa-users"></i>
                </div>
                <h3>Team Collaboration</h3>
                <p>Create groups and manage teams with role-based access control</p>
            </div>

            <div class="landing-feature-card landing-animate landing-animate-delay-3">
                <div class="landing-feature-icon indigo">
                    <i class="fa fa-shield"></i>
                </div>
                <h3>Secure &amp; Compliant</h3>
                <p>Enterprise-grade security with data encryption and compliance</p>
            </div>
        </div>
    </section>

    <!-- ===== SEE TASKFLOW IN ACTION ===== -->
    <section class="landing-demo" id="demo">
        <div class="landing-demo-inner">
            <div class="landing-demo-content landing-animate">
                <h2>See TaskFlow in action</h2>
                <p>Watch how teams use TaskFlow to manage projects, track time, monitor productivity, and collaborate in real-time.</p>

                <ul class="landing-demo-features">
                    <li>
                        <span class="demo-check"><i class="fa fa-check"></i></span>
                        <div class="demo-text">
                            <h4>Set up in minutes</h4>
                            <p>No complex configuration required. Get started instantly.</p>
                        </div>
                    </li>
                    <li>
                        <span class="demo-check"><i class="fa fa-check"></i></span>
                        <div class="demo-text">
                            <h4>Seamless collaboration</h4>
                            <p>Built-in chat, groups, and role-based permissions.</p>
                        </div>
                    </li>
                    <li>
                        <span class="demo-check"><i class="fa fa-check"></i></span>
                        <div class="demo-text">
                            <h4>Actionable insights</h4>
                            <p>Real-time analytics and performance metrics.</p>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="landing-demo-visual landing-animate landing-animate-delay-2">
                <div class="landing-video-card">
                    <div class="landing-play-btn">
                        <svg viewBox="0 0 24 24"><polygon points="5,3 19,12 5,21"/></svg>
                    </div>
                    <div class="video-label">Watch Product Demo</div>
                    <div class="video-duration">3 minutes</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== PRICING SECTION ===== -->
    <section class="landing-pricing" id="pricing">
        <div class="landing-pricing-badge">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
            Simple Pricing
        </div>

        <h2>Choose your <span class="highlight">perfect plan</span></h2>
        <p>Start free, scale as you grow. No hidden fees.</p>

        <div class="landing-pricing-toggle">
            <button class="landing-toggle-btn active" id="toggleMonthly" type="button">Monthly</button>
        </div>

        <div class="landing-pricing-note">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
            Not sure what these features mean? <a href="#faq">Click here for detailed explanations</a>
        </div>

        <div class="landing-pricing-cards">
            <!-- Starter -->
            <div class="landing-price-card">
                <div class="plan-name">Starter</div>
                <div class="plan-desc">Perfect for individuals and small teams getting started</div>
                <div class="plan-price">
                    <span class="amount" data-monthly="$9">$9</span>
                    <span class="period">/mo</span>
                </div>
                <ul class="plan-features">
                    <li><span class="feat-check">&#10003;</span> Up to 10 team members</li>
                </ul>
                <a href="signup.php?plan=starter" class="landing-btn-plan">Get Started</a>
            </div>

            <!-- Professional -->
            <div class="landing-price-card popular">
                <div class="landing-popular-tag">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2z"/></svg>
                   
                </div>
                <div class="plan-name">Professional</div>
                <div class="plan-desc">For growing teams that need advanced features</div>
                <div class="plan-price">
                    <span class="amount" data-monthly="$17">$17</span>
                    <span class="period">/mo</span>
                </div>
                <ul class="plan-features">
                    <li><span class="feat-check">&#10003;</span> Up to 20 team members</li>
                </ul>
                <a href="signup.php?plan=professional" class="landing-btn-plan primary">Get Started</a>
            </div>

            <!-- Enterprise -->
            <div class="landing-price-card">
                <div class="plan-name">Enterprise</div>
                <div class="plan-desc">For large organizations with custom needs</div>
                <div class="plan-price">
                    <span class="amount" data-monthly="$29">$29</span>
                    <span class="period">/mo</span>
                </div>
                <ul class="plan-features">
                    <li><span class="feat-check">&#10003;</span> Up to 40 team members</li>
                </ul>
                <a href="signup.php?plan=enterprise" class="landing-btn-plan">Contact Sales</a>
            </div>
        </div>
    </section>

    <!-- ===== FAQ SECTION ===== -->
    <section class="landing-faq" id="faq">
        <h2>Frequently Asked Questions</h2>
        <p>Everything you need to know about TaskFlow</p>

        <div class="landing-faq-item open">
            <button class="landing-faq-question" onclick="toggleFaq(this)">
                What is TaskFlow?
                <span class="faq-arrow"><i class="fa fa-chevron-down"></i></span>
            </button>
            <div class="landing-faq-answer">
                TaskFlow is a comprehensive task management platform designed for modern teams. It combines task tracking, time monitoring with screen capture, performance ratings, real-time messaging, and group collaboration — all in one place.
            </div>
        </div>

        <div class="landing-faq-item">
            <button class="landing-faq-question" onclick="toggleFaq(this)">
                How does time tracking work?
                <span class="faq-arrow"><i class="fa fa-chevron-down"></i></span>
            </button>
            <div class="landing-faq-answer">
                Team members clock in and out from the dashboard. While clocked in, the system randomly captures screenshots to ensure accountability. Admins can review time logs and screenshots in the attendance section.
            </div>
        </div>

        <div class="landing-faq-item">
            <button class="landing-faq-question" onclick="toggleFaq(this)">
                Can I try TaskFlow for free?
                <span class="faq-arrow"><i class="fa fa-chevron-down"></i></span>
            </button>
            <div class="landing-faq-answer">
                Yes! The Starter plan is completely free and includes up to 5 team members, basic task management, time tracking, and team chat. No credit card required.
            </div>
        </div>

        <div class="landing-faq-item">
            <button class="landing-faq-question" onclick="toggleFaq(this)">
                What are performance ratings?
                <span class="faq-arrow"><i class="fa fa-chevron-down"></i></span>
            </button>
            <div class="landing-faq-answer">
                Admins can rate completed tasks on a scale of 1-5. The system tracks individual and group performance over time, giving you a leaderboard view of your top performers and most effective teams.
            </div>
        </div>

        <div class="landing-faq-item">
            <button class="landing-faq-question" onclick="toggleFaq(this)">
                How do I invite team members?
                <span class="faq-arrow"><i class="fa fa-chevron-down"></i></span>
            </button>
            <div class="landing-faq-answer">
                Admins can generate unique invite links from the Users section. Share the link with your team — they'll create their account and automatically join your workspace.
            </div>
        </div>
    </section>

    <!-- ===== FOOTER ===== -->
    <footer class="landing-footer">
        <div class="landing-footer-inner">
            <div class="landing-footer-col">
                <div class="landing-footer-brand">
                    <img src="img/logo.png" alt="TaskFlow" class="brand-logo">
                    <img src="img/logo2.png" alt="Nehemiah Solutions" class="brand-logo-secondary">
                </div>
                <p>The complete task management platform for modern teams. Track tasks, monitor time, rate performance, and collaborate in real-time.</p>
            </div>

            <div class="landing-footer-col">
                <h4>Product</h4>
                <ul>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#pricing">Pricing</a></li>
                    <li><a href="#faq">FAQ</a></li>
                </ul>
            </div>

            <div class="landing-footer-col">
                <h4>Company</h4>
                <ul>
                    <li><a href="#">About</a></li>
                    <li><a href="#">Blog</a></li>
                    <li><a href="#">Careers</a></li>
                </ul>
            </div>

            <div class="landing-footer-col">
                <h4>Support</h4>
                <ul>
                    <li><a href="#">Help Center</a></li>
                    <li><a href="#">Contact</a></li>
                    <li><a href="#">Privacy</a></li>
                </ul>
            </div>
        </div>

        <div class="landing-footer-bottom">
            <span>&copy; <?php echo date('Y'); ?> TaskFlow. All rights reserved.</span>
            <span>
                <a href="#" style="color: #9CA3AF; margin-left: 16px;">Terms</a>
                <a href="#" style="color: #9CA3AF; margin-left: 16px;">Privacy</a>
            </span>
        </div>
    </footer>

    <!-- AI Support Chat -->
    <div class="landing-support-panel" id="supportPanel" aria-hidden="true">
        <div class="landing-support-head">
            <div class="support-brand">
                <span class="support-icon"><i class="fa fa-magic"></i></span>
                <div>
                    <strong>TaskFlow Support</strong>
                    <p><span class="dot"></span>Online &bull; Reply in ~2 min</p>
                </div>
            </div>
            <button type="button" class="support-close" id="supportCloseBtn" aria-label="Close chat">
                <i class="fa fa-times"></i>
            </button>
        </div>

        <div class="landing-support-messages" id="supportMessages"></div>

        <div class="landing-support-actions">
            <button type="button" class="support-quick-btn" data-query="How do I get started?">Get Started</button>
            <button type="button" class="support-quick-btn" data-query="How can I invite employees?">Invite Users</button>
            <button type="button" class="support-quick-btn" data-query="How does clock in and clock out work?">Time Tracker</button>
            <button type="button" class="support-quick-btn" data-query="Where can I manage billing and seats?">Billing</button>
        </div>

        <form class="landing-support-input" id="supportForm">
            <input type="text" id="supportInput" placeholder="Type your message..." maxlength="240" autocomplete="off">
            <button type="submit" aria-label="Send"><i class="fa fa-paper-plane-o"></i></button>
        </form>
        <div class="landing-support-note">Answers are based on your TaskFlow user guide.</div>
    </div>

    <button class="landing-chat-btn" id="supportToggleBtn" aria-label="Open support chat">
        <i class="fa fa-comment"></i>
    </button>

    <!-- ===== SCRIPTS ===== -->
    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            document.getElementById('landingNav').classList.toggle('scrolled', window.scrollY > 10);
        });

        // Mobile hamburger menu
        document.getElementById('hamburger').addEventListener('click', function() {
            document.getElementById('navLinks').classList.toggle('open');
        });

        // Plan card selection
        var priceCards = document.querySelectorAll('.landing-price-card');
        var popularTag = document.querySelector('.landing-popular-tag');

        function selectPricingCard(card) {
            if (!card) return;

            priceCards.forEach(function(el) {
                el.classList.remove('popular');
                el.setAttribute('aria-pressed', 'false');
                var btn = el.querySelector('.landing-btn-plan');
                if (btn) btn.classList.remove('primary');
            });

            card.classList.add('popular');
            card.setAttribute('aria-pressed', 'true');

            var selectedBtn = card.querySelector('.landing-btn-plan');
            if (selectedBtn) selectedBtn.classList.add('primary');

            if (popularTag && popularTag.parentElement !== card) {
                card.insertBefore(popularTag, card.firstChild);
            }
        }

        priceCards.forEach(function(card) {
            card.setAttribute('tabindex', '0');
            card.setAttribute('role', 'button');
            card.setAttribute('aria-pressed', card.classList.contains('popular') ? 'true' : 'false');

            card.addEventListener('click', function() {
                selectPricingCard(card);
            });

            card.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    selectPricingCard(card);
                }
            });
        });

        selectPricingCard(document.querySelector('.landing-price-card.popular') || priceCards[0]);

        // FAQ accordion
        function toggleFaq(btn) {
            var item = btn.parentElement;
            var allItems = document.querySelectorAll('.landing-faq-item');
            allItems.forEach(function(el) {
                if (el !== item) el.classList.remove('open');
            });
            item.classList.toggle('open');
        }

        // Scroll animation observer
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.style.animationPlayState = 'running';
                }
            });
        }, { threshold: 0.15 });

        document.querySelectorAll('.landing-animate').forEach(function(el) {
            el.style.animationPlayState = 'paused';
            observer.observe(el);
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('.landing-navbar-links a, a[href^="#"]').forEach(function(link) {
            link.addEventListener('click', function(e) {
                var href = this.getAttribute('href');
                if (href && href.startsWith('#')) {
                    e.preventDefault();
                    var target = document.querySelector(href);
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        document.getElementById('navLinks').classList.remove('open');
                    }
                }
            });
        });

        // Support chat (knowledge from README-USER-GUIDE.md flows)
        var supportToggleBtn = document.getElementById('supportToggleBtn');
        var supportCloseBtn = document.getElementById('supportCloseBtn');
        var supportPanel = document.getElementById('supportPanel');
        var supportMessages = document.getElementById('supportMessages');
        var supportForm = document.getElementById('supportForm');
        var supportInput = document.getElementById('supportInput');
        var quickButtons = document.querySelectorAll('.support-quick-btn');
        var hasWelcomed = false;

        var supportKnowledge = [
            {
                keywords: ['start', 'get started', 'trial', 'signup', 'sign up'],
                answer: 'Start here: click <a href="signup.php">Create Account</a>, then <a href="login.php">Log In</a>, then go to your Dashboard. You can begin from this page using Get Started or Start Free Trial.'
            },
            {
                keywords: ['invite', 'employee', 'subscriber', 'join workspace', 'join'],
                answer: 'Admin flow: open the <a href="invite-user.php">Invites page</a>, send an invite, then the employee opens the invite link, sets a password, and logs in.'
            },
            {
                keywords: ['clock', 'time in', 'time out', 'tracker', 'monitor'],
                answer: 'Employee daily flow: dashboard -> Clock In -> work on tasks/messages -> Clock Out. If user navigates without clock-in, the reminder appears once per login.'
            },
            {
                keywords: ['task', 'create task', 'my task', 'assignee'],
                answer: 'Admins create and manage work in the <a href="tasks.php">Tasks section</a>. Employees complete assigned work in <a href="my_task.php">My Tasks</a>.'
            },
            {
                keywords: ['billing', 'seat', 'plan', 'subscription', 'workspace-billing'],
                answer: 'Billing and seat limits are managed in the <a href="workspace-billing.php">Workspace Billing page</a> (admin only).'
            },
            {
                keywords: ['forgot password', 'reset password', 'password'],
                answer: 'Use <a href="login.php">Forgot Password</a> from the login screen. After login, you can update details from your Profile.'
            },
            {
                keywords: ['login', 'sign in'],
                answer: 'Use the <a href="login.php">Sign In page</a>. New users should create an account first, or join using an admin invite link.'
            }
        ];

        function appendSupportMessage(role, text, isHtml) {
            var row = document.createElement('div');
            row.className = 'support-msg-row ' + role;
            var bubble = document.createElement('div');
            bubble.className = 'support-msg-bubble';
            if (isHtml) {
                bubble.innerHTML = text;
            } else {
                bubble.textContent = text;
            }
            row.appendChild(bubble);
            supportMessages.appendChild(row);
            supportMessages.scrollTop = supportMessages.scrollHeight;
        }

        function openSupportPanel() {
            supportPanel.classList.add('open');
            supportPanel.setAttribute('aria-hidden', 'false');
            if (!hasWelcomed) {
                appendSupportMessage(
                    'bot',
                    'Hi! I can guide you through TaskFlow setup and daily flows. Ask about signup, invites, tasks, time tracking, billing, or password reset.',
                    false
                );
                hasWelcomed = true;
            }
            setTimeout(function () {
                supportInput.focus();
            }, 100);
        }

        function closeSupportPanel() {
            supportPanel.classList.remove('open');
            supportPanel.setAttribute('aria-hidden', 'true');
        }

        function getSupportReply(question) {
            var q = (question || '').toLowerCase();
            for (var i = 0; i < supportKnowledge.length; i++) {
                var item = supportKnowledge[i];
                for (var j = 0; j < item.keywords.length; j++) {
                    if (q.indexOf(item.keywords[j]) !== -1) {
                        return item.answer;
                    }
                }
            }
            return 'I can help with onboarding and user flows. Try asking: "How do I get started?", "How do I invite employees?", "How does clock in work?", or "Where is billing?".';
        }

        function handleSupportQuestion(rawQuestion) {
            var question = (rawQuestion || '').trim();
            if (!question) return;
            appendSupportMessage('user', question, false);
            var reply = getSupportReply(question);
            setTimeout(function () {
                appendSupportMessage('bot', reply, true);
            }, 220);
        }

        if (supportToggleBtn) {
            supportToggleBtn.addEventListener('click', function () {
                if (supportPanel.classList.contains('open')) {
                    closeSupportPanel();
                } else {
                    openSupportPanel();
                }
            });
        }

        if (supportCloseBtn) {
            supportCloseBtn.addEventListener('click', closeSupportPanel);
        }

        if (supportForm) {
            supportForm.addEventListener('submit', function (e) {
                e.preventDefault();
                handleSupportQuestion(supportInput.value);
                supportInput.value = '';
            });
        }

        quickButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                openSupportPanel();
                handleSupportQuestion(btn.getAttribute('data-query') || '');
            });
        });
    </script>
</body>
</html>
