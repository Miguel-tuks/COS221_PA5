<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }

function h($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

function setFlash($type, $msg) {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function showFlash() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        echo '<div class="flash flash-' . h($f['type']) . '">' . h($f['msg']) . '</div>';
        unset($_SESSION['flash']);
    }
}

function renderStars($rating) {
    $rating = (float)$rating;
    $rating = max(0, min(5, $rating));
    $full   = floor($rating);
    $out    = '<span class="stars" aria-label="' . h($rating) . ' out of 5">';
    for ($i = 1; $i <= 5; $i++) {
        $out .= ($i <= $full) ? '★' : '☆';
    }
    $out .= ' <small>(' . number_format($rating, 1) . ')</small></span>';
    return $out;
}

function options($items, $valueKey, $labelKey, $selected = null) {
    $out = '';
    foreach ($items as $row) {
        $val = h($row[$valueKey]);
        $lbl = h($row[$labelKey]);
        $sel = ((string)$row[$valueKey] === (string)$selected) ? ' selected' : '';
        $out .= "<option value=\"$val\"$sel>$lbl</option>";
    }
    return $out;
}

function destLabel($row) {
    return $row['city'] . ', ' . $row['country'];
}

function isStrongEnough($password) {
    return strlen($password) >= 8
        && preg_match('/[A-Za-z]/', $password)
        && preg_match('/\d/', $password);
}
