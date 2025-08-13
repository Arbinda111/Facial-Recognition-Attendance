<?php
require 'auth.php';
session_destroy();
header("Location: admin_login.php?msg=logged+out");
