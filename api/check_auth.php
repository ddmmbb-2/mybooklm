<?php
session_start();
require_once __DIR__ . '/../lib/Auth.php';
Auth::requireLoginForApi();