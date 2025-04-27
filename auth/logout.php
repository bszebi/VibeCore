<?php
session_start(); 
require_once '../includes/Auth.php';
Auth::logout();
exit;