<?php
require_once __DIR__.'/bootstrap/app.php';
if(!current_user()) redirect('auth/login.php');
redirect(account_home(current_user()));
