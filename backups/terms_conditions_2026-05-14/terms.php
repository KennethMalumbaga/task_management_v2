<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terms and Conditions | TaskFlow</title>
    <meta name="description" content="Terms and Conditions for TaskFlow by Nehemiah Solutions.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: #F3F4F6;
            color: #374151;
            font-family: 'Inter', Arial, Helvetica, sans-serif;
            line-height: 1.7;
            min-height: 100vh;
        }

        .tc-shell {
            width: min(820px, calc(100% - 32px));
            margin: 0 auto;
            padding: 36px 0 60px;
        }

        /* Top bar */
        .tc-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 28px;
            flex-wrap: wrap;
        }

        .tc-brand-link {
            color: #6C3CE1;
            font-weight: 700;
            font-size: 18px;
            text-decoration: none;
            letter-spacing: -0.01em;
        }

        .tc-topbar-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .tc-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 16px;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            background: #fff;
            color: #374151;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
            white-space: nowrap;
        }

        .tc-btn:hover {
            background: #F9FAFB;
            border-color: #9CA3AF;
            color: #111827;
        }

        .tc-btn-primary {
            background: #6C3CE1;
            color: #fff;
            border-color: #6C3CE1;
        }

        .tc-btn-primary:hover {
            background: #5a30c5;
            border-color: #5a30c5;
            color: #fff;
        }

        /* Card */
        .tc-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.07), 0 1px 4px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .tc-card-header {
            padding: 28px 36px 24px;
            border-bottom: 1px solid #E5E7EB;
            background: linear-gradient(135deg, #6C3CE1 0%, #9B6DFF 100%);
            color: #fff;
        }

        .tc-card-header h1 {
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 4px;
        }

        .tc-card-header p {
            font-size: 13px;
            opacity: 0.85;
        }

        .tc-card-body {
            padding: 32px 36px;
        }

        /* Org header box */
        .tc-org-box {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 16px 20px;
            background: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-radius: 10px;
            margin-bottom: 28px;
        }

        .tc-org-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #6C3CE1;
            margin-top: 5px;
            flex-shrink: 0;
        }

        .tc-org-info {
            font-size: 13px;
            color: #4B5563;
            line-height: 1.6;
        }

        .tc-org-info strong {
            display: block;
            color: #111827;
            font-size: 14px;
            margin-bottom: 2px;
        }

        /* Doc title */
        .tc-doc-title {
            font-size: 15px;
            font-weight: 700;
            color: #111827;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid #E5E7EB;
        }

        /* Intro paragraphs */
        .tc-intro {
            color: #4B5563;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .tc-intro:last-of-type {
            margin-bottom: 28px;
        }

        /* Sections */
        .tc-section {
            margin-bottom: 24px;
            padding-bottom: 24px;
            border-bottom: 1px solid #F3F4F6;
        }

        .tc-section:last-of-type {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .tc-section-heading {
            display: flex;
            align-items: baseline;
            gap: 10px;
            margin-bottom: 10px;
        }

        .tc-section-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 26px;
            height: 26px;
            border-radius: 6px;
            background: #EDE9FE;
            color: #6C3CE1;
            font-size: 12px;
            font-weight: 700;
            flex-shrink: 0;
            padding: 0 6px;
        }

        .tc-section-heading h3 {
            font-size: 14.5px;
            font-weight: 700;
            color: #111827;
        }

        .tc-section p {
            font-size: 13.5px;
            color: #4B5563;
            margin-bottom: 8px;
            margin-left: 36px;
        }

        .tc-section p:last-child {
            margin-bottom: 0;
        }

        .tc-section ul {
            margin: 6px 0 0 52px;
            padding: 0;
            color: #4B5563;
            font-size: 13.5px;
        }

        .tc-section ul li {
            margin-bottom: 4px;
        }

        /* Agreement notice */
        .tc-agreement-notice {
            margin-top: 28px;
            padding: 16px 20px;
            background: #EDE9FE;
            border-left: 4px solid #6C3CE1;
            border-radius: 8px;
            color: #4C1D95;
            font-size: 13.5px;
            font-weight: 500;
            line-height: 1.55;
        }

        /* Footer actions */
        .tc-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 18px 36px;
            border-top: 1px solid #E5E7EB;
            background: #F9FAFB;
            flex-wrap: wrap;
        }

        .tc-card-footer-note {
            font-size: 12px;
            color: #9CA3AF;
        }

        .tc-card-footer-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        @media (max-width: 640px) {
            .tc-card-header { padding: 22px 20px 18px; }
            .tc-card-header h1 { font-size: 20px; }
            .tc-card-body { padding: 22px 20px; }
            .tc-card-footer { padding: 14px 20px; }
            .tc-section p, .tc-section ul { margin-left: 0; }
        }
    </style>
</head>
<body>
    <main class="tc-shell">

        <nav class="tc-topbar">
            <a class="tc-brand-link" href="landing.php">TaskFlow</a>
            <div class="tc-topbar-actions">
                <a class="tc-btn" href="docs/NSC-TaskFlow-Terms__Conditions.pdf" target="_blank" rel="noopener">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    Open PDF
                </a>
                <a class="tc-btn tc-btn-primary" href="signup.php">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                    Back to Signup
                </a>
            </div>
        </nav>

        <article class="tc-card">
            <header class="tc-card-header">
                <h1>Terms and Conditions</h1>
                <p>TaskFlow by Nehemiah Solutions &mdash; Please read these terms carefully before registering.</p>
            </header>

            <div class="tc-card-body">
                <div class="tc-org-box">
                    <div class="tc-org-dot"></div>
                    <div class="tc-org-info">
                        <strong>Nehemiah Solutions</strong>
                        2<sup>nd</sup> Floor DDTC Building, Juan Luna Street, Davao City &nbsp;&bull;&nbsp;
                        Website: www.nehemiahsolutions.com &nbsp;&bull;&nbsp;
                        Email: nehemiah.solutions.corp@gmail.com &nbsp;&bull;&nbsp;
                        0916-264-6505
                    </div>
                </div>

                <p class="tc-doc-title">TASKFLOW: TERMS AND CONDITIONS OF REGISTRATION</p>

                <p class="tc-intro">Welcome to TaskFlow! TaskFlow is a task and workflow management platform designed to help organizations, teams, and individuals manage projects, assignments, collaboration, productivity, and operational workflows.</p>
                <p class="tc-intro">By accessing, registering, or using the platform, you acknowledge that you have read, understood, and agreed to comply with the following Terms and Conditions:</p>

                <div class="tc-section">
                    <div class="tc-section-heading">
                        <span class="tc-section-num">1</span>
                        <h3>Compliance with the Data Privacy Act of 2012</h3>
                    </div>
                    <p>TaskFlow values and protects the privacy and confidentiality of user and organizational information. All personal data collected, stored, processed, and transmitted through the platform shall be handled in accordance with the Data Privacy Act of 2012 and other applicable laws and regulations.</p>
                    <p>By using the platform, users consent to the collection, storage, processing, and use of their information for legitimate operational, communication, collaboration, reporting, security, and administrative purposes.</p>
                </div>

                <div class="tc-section">
                    <div class="tc-section-heading">
                        <span class="tc-section-num">2</span>
                        <h3>Authorized Use of the Platform</h3>
                    </div>
                    <p>TaskFlow is intended for lawful and authorized project management, workflow coordination, team collaboration, and productivity-related activities. Users agree not to use the platform for illegal, harmful, fraudulent, abusive, or unauthorized purposes.</p>
                    <p>Any attempt to disrupt, manipulate, reverse-engineer, or gain unauthorized access to the platform or other users' accounts is strictly prohibited and may result in immediate account suspension and legal action.</p>
                </div>

                <div class="tc-section">
                    <div class="tc-section-heading">
                        <span class="tc-section-num">3</span>
                        <h3>User Account and Workspace Responsibilities</h3>
                    </div>
                    <p>Workspace owners are responsible for maintaining the security of their account credentials. You must not share your login credentials with unauthorized individuals. You are responsible for all activities that occur under your account.</p>
                    <p>Workspace owners are responsible for managing their team members, assigning roles and permissions appropriately, and ensuring that all users under their workspace comply with these Terms and Conditions.</p>
                    <p>TaskFlow reserves the right to suspend or terminate accounts found to be in violation of these terms, without prior notice.</p>
                </div>

                <div class="tc-section">
                    <div class="tc-section-heading">
                        <span class="tc-section-num">4</span>
                        <h3>Data Ownership and Confidentiality</h3>
                    </div>
                    <p>All data, tasks, files, and content created within a workspace remain the property of the respective workspace owner and organization. TaskFlow does not claim ownership over user-generated content.</p>
                    <p>Users agree to maintain the confidentiality of organizational data accessed through the platform and not to disclose, copy, or distribute such information to unauthorized parties.</p>
                </div>

                <div class="tc-section">
                    <div class="tc-section-heading">
                        <span class="tc-section-num">5</span>
                        <h3>Intellectual Property</h3>
                    </div>
                    <p>All platform features, designs, interfaces, trademarks, and software components of TaskFlow are the intellectual property of Nehemiah Solutions. Users are granted a limited, non-exclusive, non-transferable license to use the platform solely for its intended purpose.</p>
                    <p>Reproduction, distribution, modification, or creation of derivative works from any part of the TaskFlow platform without explicit written consent is strictly prohibited.</p>
                </div>

                <div class="tc-section">
                    <div class="tc-section-heading">
                        <span class="tc-section-num">6</span>
                        <h3>Service Availability and Modifications</h3>
                    </div>
                    <p>TaskFlow strives to maintain high availability and performance. However, Nehemiah Solutions does not guarantee uninterrupted access and shall not be liable for downtime caused by maintenance, technical issues, or circumstances beyond its control.</p>
                    <p>Nehemiah Solutions reserves the right to modify, update, or discontinue any features of TaskFlow at any time. Users will be notified of significant changes where reasonably practicable.</p>
                </div>

                <div class="tc-section">
                    <div class="tc-section-heading">
                        <span class="tc-section-num">7</span>
                        <h3>Limitation of Liability</h3>
                    </div>
                    <p>To the fullest extent permitted by applicable law, Nehemiah Solutions shall not be liable for any indirect, incidental, special, or consequential damages arising from the use or inability to use the platform, including but not limited to loss of data, revenue, or business opportunities.</p>
                    <p>Users acknowledge that they use the platform at their own risk and that Nehemiah Solutions' total liability, if any, shall not exceed the amount paid by the user for the applicable subscription period.</p>
                </div>

                <div class="tc-section">
                    <div class="tc-section-heading">
                        <span class="tc-section-num">8</span>
                        <h3>Termination</h3>
                    </div>
                    <p>Either party may terminate the account or subscription at any time in accordance with the chosen plan terms. Upon termination, access to the workspace and its data will be revoked. Workspace owners are advised to export their data prior to account closure.</p>
                    <p>Nehemiah Solutions reserves the right to terminate or suspend access immediately if there is evidence of a violation of these Terms and Conditions or applicable law.</p>
                </div>

                <div class="tc-section">
                    <div class="tc-section-heading">
                        <span class="tc-section-num">9</span>
                        <h3>Governing Law</h3>
                    </div>
                    <p>These Terms and Conditions shall be governed by and construed in accordance with the laws of the Republic of the Philippines. Any disputes arising from the use of the platform shall be subject to the exclusive jurisdiction of the courts in Davao City, Philippines.</p>
                </div>

                <div class="tc-section">
                    <div class="tc-section-heading">
                        <span class="tc-section-num">10</span>
                        <h3>Contact Information</h3>
                    </div>
                    <p>For questions, concerns, or data privacy requests related to these Terms and Conditions, please contact:</p>
                    <ul>
                        <li><strong>Nehemiah Solutions</strong></li>
                        <li>2<sup>nd</sup> Floor DDTC Building, Juan Luna Street, Davao City</li>
                        <li>Email: nehemiah.solutions.corp@gmail.com</li>
                        <li>Phone: 0916-264-6505</li>
                        <li>Website: www.nehemiahsolutions.com</li>
                    </ul>
                </div>

                <div class="tc-agreement-notice">
                    By registering and using TaskFlow, you confirm that you have read, understood, and agreed to these Terms and Conditions.
                </div>
            </div>

            <footer class="tc-card-footer">
                <span class="tc-card-footer-note">Last updated: May 2025 &nbsp;&bull;&nbsp; Nehemiah Solutions</span>
                <div class="tc-card-footer-actions">
                    <a class="tc-btn" href="docs/NSC-TaskFlow-Terms__Conditions.pdf" target="_blank" rel="noopener">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Download PDF
                    </a>
                    <a class="tc-btn tc-btn-primary" href="signup.php">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                        Back to Signup
                    </a>
                </div>
            </footer>
        </article>

    </main>
</body>
</html>
