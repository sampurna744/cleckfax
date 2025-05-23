<?php
session_start();
$error_message = ""; // Declare the variable here
$account_error = ""; // Declare the variable here
$user_role_error = ""; // Declare the variable here

include("connection/connection.php");

if (isset($_POST["sign_in"])) {
    // Input Sanitization 
    require("input_validation/input_sanitization.php");

    // Check if $_POST["email"] exists before sanitizing
    $email = isset($_POST["email"]) ? sanitizeEmail($_POST["email"]) : "";

    // Check if $_POST["password"] exists before sanitizing
    $password = isset($_POST["password"]) ? sanitizePassword($_POST["password"]) : "";

    $remember = isset($_POST["remember"]) ? $_POST["remember"] : 0;
    $pass = $_POST["password"];

    // Prepare the SQL statement
    $sql = "SELECT 
        HU.FIRST_NAME, 
        HU.LAST_NAME, 
        HU.USER_ID, 
        HU.USER_PASSWORD, 
        HU.USER_PROFILE_PICTURE, 
        HU.USER_TYPE, 
        T.VERIFICATION_STATUS
    FROM 
        CLECK_USER HU
    JOIN 
        TRADER T ON HU.USER_ID = T.USER_ID
    WHERE 
        HU.USER_EMAIL = :email";

    // Prepare the OCI statement
    $stmt = oci_parse($conn, $sql);

    // Bind the email parameter
    oci_bind_by_name($stmt, ':email', $email);

    // Execute the statement
    if (oci_execute($stmt)) {
        // Fetch the result
        if ($row = oci_fetch_assoc($stmt)) {
            $first_name = $row['FIRST_NAME'];
            $last_name = $row['LAST_NAME'];
            $user_id = $row['USER_ID'];
            $passwords = $row['USER_PASSWORD'];
            $profile_picture = $row['USER_PROFILE_PICTURE'];
            $user_role = $row['USER_TYPE'];
            $status = $row["VERIFICATION_STATUS"];
            
            // Corrected password verification using password_verify
            if ($password == $passwords && $user_role == "trader" && $status == 1) {
                if ($remember == 1) {
                    setcookie("email_trader", $email, time() + 60 * 60 * 24 * 30, "/");
                    setcookie("password_trader", $pass, time() + 60 * 60 * 24 * 30, "/");
                }
                // Registering session username
                $_SESSION["email"] = $email;
                $_SESSION["accesstime"] = date("ymdhis");
                $_SESSION["name"] = $first_name . " " . $last_name;
                $_SESSION["picture"] = $profile_picture;
                $_SESSION["userid"] = $user_id;
                $_SESSION["role"] = $user_role;
                header("Location:trader_dashboard/trader_dashboard.php");
                exit();
            } elseif ($status != 1) {
                $error_message = "Your account has been disabled.";
            } elseif ($user_role != "trader") {
                $error_message = "You are not a registered trader at HudderFoods.";
            } else {
                $error_message = "Incorrect password. Please try again!";
            }
        } else {
            $error_message = "User not found. Please check your email.";
        }
    } else {
        $error = oci_error($stmt);
        echo "Error executing SQL statement: " . $error['message'];
    }

    // Free the statement and close the connection
    oci_free_statement($stmt);
    oci_close($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trader Sign In</title>
    <link rel="icon" href="logo_ico.png" type="image/png">
    <link rel="stylesheet" href="without_session_navbar.css">
    <link rel="stylesheet" href="footer.css">
    <link rel="stylesheet" href="customer_signin.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php
        include("without_session_navbar.php");
    ?>
    <div class="sign-in-container">
        <h2>Trader Sign In</h2>
        <?php
            if (!empty($error_message)) {
                echo "<p style='color: red;'>$error_message</p>";
            }
        ?>
        <form method="POST" id="trader_signin" name="trader_signin" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="text" id="email" name="email" placeholder="Enter your Email" required value="<?php if (isset($_COOKIE["email_trader"])) { echo $_COOKIE["email_trader"]; } ?>">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}" title="Password must be at least 6 characters long and contain at least one lowercase letter, one uppercase letter, and one number" value="<?php if (isset($_COOKIE["password_trader"])) { echo $_COOKIE["password_trader"]; } ?>">
            </div>
            <div class="form-group">
                <label for="remember"><input type="checkbox" id="remember" name="remember" alt="Remember Me" value="1">Remember Me</label>
            </div>
            <div class="form-group">
                <input type="submit" value="Sign In" name="sign_in" id="sign_in">
            </div>
        </form>
        <div class="action-links">
            <a href="trader_forgot_password.php" class="forgot-password">Forgot Password?</a>
            <p>Wanna Be A Trader At HudderFoods? <a href="trader_signup.php" class="sign-up-link">Sign Up</a></p>
            <p>Are You A Customer? <a href="customer_signin.php" class="sign-up-link">Customer Sign In</a></p>
        </div>
    </div>
    <?php
        include("footer.php");
    ?>
    <script src="without_session_navbar.js"></script>
</body>
</html>
