<?php
require_once __DIR__.'/../bootstrap/app.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
verify_csrf(); logout_user(); header('Location: '.base_url('auth/login.php')); exit;
