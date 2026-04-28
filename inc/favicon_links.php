<?php
declare(strict_types=1);

$iconPath = '/img/logo.png';
$docRootRaw = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
$projectRootFs = realpath(__DIR__ . '/..');

if ($docRootRaw !== '' && $projectRootFs !== false) {
    $docRootFs = realpath($docRootRaw);
    if ($docRootFs !== false) {
        $projectNorm = str_replace('\\', '/', $projectRootFs);
        $docRootNorm = str_replace('\\', '/', $docRootFs);
        if (str_starts_with($projectNorm, $docRootNorm)) {
            $tail = trim(substr($projectNorm, strlen($docRootNorm)), '/');
            if ($tail !== '') {
                $iconPath = '/' . $tail . '/img/logo.png';
            }
        }
    }
}

$iconPathEscaped = htmlspecialchars($iconPath, ENT_QUOTES, 'UTF-8');
?>
<link rel="icon" href="<?= $iconPathEscaped ?>" type="image/png" sizes="any">
<link rel="shortcut icon" href="<?= $iconPathEscaped ?>" type="image/png">
<link rel="apple-touch-icon" href="<?= $iconPathEscaped ?>">
