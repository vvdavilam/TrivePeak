<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thrive Peak</title>
    <link rel="stylesheet" href="css/authenticate.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
</head>
<body>
    <div class="login-container">
        <header>
            <img src="images/think_unlimited_black.png" alt="Logo" class="logo">
            <div class="Thrive">
                <img src="images/thrivepeak_text_logo.png" alt="ThrivePeak">
            </div>
            <nav>
                <a href="about_us.html">About us</a>
            </nav>
        </header>
        <div class="login-box">
            <h1>Unlock Potential, Drive Success!</h1>
            <p>Welcome!</p>
        </div>
        <div class="form-container">
            <form id="loginForm" action="autentica.php" method="POST">
                <input type="email" name="email" id="email" placeholder="Email address" required>
                <input type="password" name="password" id="password" placeholder="Password" required>
                <?php
            session_start();
            if (isset($_SESSION['error_msg'])) {
                echo '<p class="error-note">' . $_SESSION['error_msg'] . '</p>';
                unset($_SESSION['error_msg']); // Clear the error message after displaying
            }
            ?>
                <div class="remember-me">
                    <input type="checkbox" id="remember">
                    <label for="remember">Remember Me</label>
                </div>
                <button type="submit">Enter</button>
            </form>
        </div>
        <footer>
            <h1>
                <!-- Footer content if needed -->
            </h1>
        </footer>
    </div>
</body>
</html>
