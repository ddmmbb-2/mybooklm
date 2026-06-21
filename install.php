<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/lib/Auth.php';
Auth::createUser('admin', 'yourpassword', 'admin');
echo '管理員建立完成，請立即刪除 install.php';