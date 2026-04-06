<?php

require_once __DIR__ . '/../../inc/tenant.php';
require_once __DIR__ . '/../model/Notification.php';

if (!function_exists('tm_workspace_subscription_reminder_message')) {
    function tm_workspace_subscription_reminder_message(array $ownerContact, array $subscriptionNotice)
    {
        $workspaceName = trim((string)($ownerContact['workspace_name'] ?? ''));
        if ($workspaceName === '') {
            $workspaceName = 'Your workspace';
        }

        $subjectLabel = !empty($subscriptionNotice['is_trial']) ? 'trial' : 'subscription';
        $endsAtDisplay = trim((string)($subscriptionNotice['ends_at_display'] ?? ''));
        if ($endsAtDisplay === '') {
            $endsAtDisplay = 'the scheduled billing end date';
        }

        return $workspaceName . ' ' . $subjectLabel . ' will end on '
            . $endsAtDisplay
            . '. Please renew before access is interrupted.';
    }
}

if (!function_exists('tm_dispatch_workspace_subscription_reminder')) {
    function tm_dispatch_workspace_subscription_reminder($pdo, $orgId, $fallbackRecipientId = null, $warningDays = 15, $ignoreWindow = false)
    {
        $orgId = (int)$orgId;
        $fallbackRecipientId = $fallbackRecipientId !== null ? (int)$fallbackRecipientId : null;
        $warningDays = max(1, (int)$warningDays);
        $ignoreWindow = (bool)$ignoreWindow;

        $result = [
            'org_id' => $orgId,
            'dispatched' => false,
            'created' => false,
            'email_sent' => false,
            'duplicate' => false,
            'ignore_window' => $ignoreWindow,
            'reason' => null,
            'message' => null,
            'recipient_id' => null,
            'owner_email' => null,
            'notice' => null,
            'owner_contact' => null,
        ];

        if ($orgId <= 0) {
            $result['reason'] = 'Workspace context is missing.';
            return $result;
        }

        $subscriptionNotice = tenant_workspace_subscription_notice($pdo, $orgId, $warningDays, $ignoreWindow);
        $result['notice'] = $subscriptionNotice;
        if (empty($subscriptionNotice['show'])) {
            $result['reason'] = (string)($subscriptionNotice['reason'] ?? 'Workspace is not within the reminder window.');
            return $result;
        }

        $ownerContact = tenant_get_workspace_owner_contact($pdo, $orgId);
        $result['owner_contact'] = $ownerContact;

        $recipientId = isset($ownerContact['user_id']) ? (int)$ownerContact['user_id'] : 0;
        if ($recipientId <= 0 && $fallbackRecipientId !== null && $fallbackRecipientId > 0) {
            $recipientId = $fallbackRecipientId;
        }
        $result['recipient_id'] = $recipientId > 0 ? $recipientId : null;

        $ownerEmail = trim((string)($ownerContact['email'] ?? ''));
        $result['owner_email'] = $ownerEmail !== '' ? $ownerEmail : null;

        $message = tm_workspace_subscription_reminder_message($ownerContact, $subscriptionNotice);
        $result['message'] = $message;
        $notificationType = 'Subscription Reminder';

        $previousOrgId = tenant_get_current_org_id();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['organization_id'] = $orgId;

        try {
            if ($recipientId > 0 && notification_exists_for_recipient($pdo, $recipientId, $notificationType, $message)) {
                $result['duplicate'] = true;
                $result['reason'] = 'Reminder already exists for this billing period.';
                return $result;
            }

            if ($recipientId <= 0) {
                $result['reason'] = 'Workspace owner account could not be resolved.';
                return $result;
            }

            insert_notification($pdo, [$message, $recipientId, $notificationType]);
            $result['created'] = true;
            $result['dispatched'] = true;

            if ($ownerEmail !== '') {
                if (!function_exists('send_workspace_subscription_reminder_email')) {
                    require_once __DIR__ . '/../send_email.php';
                }

                $emailSent = send_workspace_subscription_reminder_email(
                    $ownerEmail,
                    (string)($ownerContact['full_name'] ?? 'Workspace Owner'),
                    (string)($ownerContact['workspace_name'] ?? 'Workspace'),
                    (string)($subscriptionNotice['ends_at_display'] ?? ''),
                    !empty($subscriptionNotice['is_trial'])
                );
                $result['email_sent'] = (bool)$emailSent;
                if (!$emailSent) {
                    $result['reason'] = 'Notification created, but reminder email could not be sent.';
                }
            }

            return $result;
        } finally {
            if ($previousOrgId !== null && $previousOrgId > 0) {
                $_SESSION['organization_id'] = (int)$previousOrgId;
            } else {
                unset($_SESSION['organization_id']);
            }
        }
    }
}
