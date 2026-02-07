<?php
if (!empty($_SESSION['flash']) && is_array($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $type = $flash['type'] ?? 'info';
    $message = $flash['message'] ?? '';
    $detail = $flash['detail'] ?? '';
    $alert = 'info';
    if ($type === 'success') {
        $alert = 'success';
    } elseif ($type === 'error') {
        $alert = 'danger';
    }
    if ($message !== '') {
        echo '<div class="alert alert-' . htmlspecialchars($alert) . '">';
        echo htmlspecialchars($message);
        if ($detail !== '') {
            echo ' <span class="text-muted">(' . htmlspecialchars($detail) . ')</span>';
        }
        echo '</div>';
    }
}
?>
