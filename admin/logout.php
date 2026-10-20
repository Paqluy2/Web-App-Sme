<?php
session_start();
session_destroy();
header('Location: login.php'); // Relative path stays inside /WebApp/admin/
exit;