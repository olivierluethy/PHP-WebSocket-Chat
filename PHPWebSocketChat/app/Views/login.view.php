<?php
// Initialize the session
session_start();
 
// Check if the user is already logged in, if yes then redirect him to welcome page
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: entry");
    exit;
}
 
// Include config file
require_once "config.php";
 
// Define variables and initialize with empty values
$username = $password = "";
$username_err = $password_err = $login_err = "";
 
// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
 
    // Check if username is empty
    if(empty(trim($_POST["username"]))){
        $username_err = "Please enter username.";
    } else{
        $username = trim($_POST["username"]);
    }
    
    // Check if password is empty
    if(empty(trim($_POST["password"]))){
        $password_err = "Please enter your password.";
    } else{
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if(empty($username_err) && empty($password_err)){
        // Prepare a select statement
        $sql = "SELECT id, username, password FROM users WHERE username = ?";
        
        if($stmt = mysqli_prepare($link, $sql)){
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            
            // Set parameters
            $param_username = $username;
            
            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)){
                // Store result
                mysqli_stmt_store_result($stmt);
                
                // Check if username exists, if yes then verify password
                if(mysqli_stmt_num_rows($stmt) == 1){                    
                    // Bind result variables
                    mysqli_stmt_bind_result($stmt, $id, $username, $hashed_password);
                    if(mysqli_stmt_fetch($stmt)){
                        if(password_verify($password, $hashed_password)){
                            // Password is correct, so start a new session
                            session_start();
                            
                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["username"] = $username;                            
                            
                            // Redirect user to welcome page
                            header("location: entry");
                        } else{
                            // Password is not valid, display a generic error message
                            $login_err = "Invalid username or password.";
                        }
                    }
                } else{
                    // Username doesn't exist, display a generic error message
                    $login_err = "Invalid username or password.";
                }
            } else{
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }
    
    // Close connection
    mysqli_close($link);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Chat</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width">
	<script src="js/jquery.js" type="text/javascript"></script>
	<link rel="shortcut icon" href="../images/shortcut.png">
	<link rel="stylesheet" href="../public/css/index.css">
</head>
<body>
    <header>
		<img src="../images/shortcut.png" alt="">
		<nav>
			<h1>PHP WebSocket Chat</h1>
		</nav>
	</header>

    <h1 class="title">Willkommen zu PHP WebSocket Chat</h1>

    <div class="wrapper">
        <?php 
        if(!empty($login_err)){
            echo '<div class="alert-danger">' . $login_err . '</div>';
        }        
        ?>

        <form action="login" method="post">
            <div class="form-group">
                <label>Username:</label><br>
                <input type="text" id="logUser" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $username; ?>"><br>
                <span class="invalid-feedback"><?php echo $username_err; ?></span><br>
            </div>    
            <div class="form-group">
                <label>Password:</label><br>
                <input type="password" id="logPass" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>"><br>
                <span class="invalid-feedback"><?php echo $password_err; ?></span><br><br>
            </div>
            <div class="form-group">
                <input type="submit" class="btn btn-primary" value="Login">
            </div><br>
            <p>Don't have an account? <a href="register">Sign up now</a>.</p>
        </form>
        <script src="js/clientSideValidationLogin.js"></script>
    </div>
</body>
</html>