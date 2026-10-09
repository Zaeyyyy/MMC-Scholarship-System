<?php
session_start();
if (!empty($_SESSION['user_id'])) {
    if (($_SESSION['role'] ?? '') === 'admin') {
        header('Location: academic_year.php');
    } else {
        header('Location: student_dashboard.php');
    }
} else {
    header('Location: login.php');
}
exit;
