<?php 
require_once __DIR__ .'/includes/auth.php';
logoutUser();
header('Location: ' . baseUrl() . 'index.php');
exit;