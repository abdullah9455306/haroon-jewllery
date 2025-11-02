<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Debug</title>
</head>
<body>
    <h1>Session Debug Information</h1>

    <h3>Session Status:</h3>
    <pre><?php print_r(session_status()); ?></pre>

    <h3>Session ID:</h3>
    <pre><?php echo session_id(); ?></pre>

    <h3>Session Data:</h3>
    <pre><?php print_r($_SESSION); ?></pre>

    <h3>Is user_id set and valid?</h3>
    <p>isset($_SESSION['user_id']): <?php echo isset($_SESSION['user_id']) ? 'YES' : 'NO'; ?></p>
    <p>!empty($_SESSION['user_id']): <?php echo !empty($_SESSION['user_id']) ? 'YES' : 'NO'; ?></p>
    <p>$_SESSION['user_id'] value: <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET'; ?></p>

    <h3>Actions:</h3>
    <a href="?clear=1">Clear Session</a> |
    <a href="?set_test=1">Set Test user_id</a> |
    <a href="products.php">Go to Products</a>

    <?php
    if (isset($_GET['clear'])) {
        session_destroy();
        echo "<p>Session destroyed. <a href='debug_session.php'>Refresh</a></p>";
    }

    if (isset($_GET['set_test'])) {
        $_SESSION['user_id'] = 123;
        echo "<p>Test user_id set. <a href='debug_session.php'>Refresh</a></p>";
    }
    ?>
</body>
</html>