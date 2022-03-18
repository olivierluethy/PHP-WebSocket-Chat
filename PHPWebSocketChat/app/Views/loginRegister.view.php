<!DOCTYPE html>
<html lang="en">

<head>
    <title>Chat</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <script src="js/jquery.js" type="text/javascript"></script>
    <link rel="shortcut icon" href="../images/shortcut.png">
    <link rel="stylesheet" href="../public/css/loginRegister.css">
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

        <form id="login" action="login" method="POST">
            <div class="form-group">
                <label>Username:</label><br>
                <input type="text" name="username" id="loginUser"><br>
                <p id="loginUserError"></p><br>
            </div>
            <div class="form-group">
                <label>Password:</label><br>
                <input type="password" name="password" id="loginPass"><br>
                <p id="loginPassError"></p><br>
            </div>
            <div class="form-group">
                <input type="submit" class="btn btn-primary" value="Login">
            </div><br>
            <p>Don't have an account? <a id="changeTo" onclick="changeToRegister()">Sign up now</a>.</p>
        </form>

        <form id="register" action="register" method="POST">
            <div class="form-group">
                <label>Username:</label><br>
                <input type="text" name="username" id="registerUser"><br>
                <p id="registerUserError"></p><br>
            </div>
            <div class="form-group">
                <label>Password:</label><br>
                <input type="password" name="password" id="registerPass"><br>
                <p id="registerPassError"></p><br>
            </div>
            <div class="form-group">
                <label>Confirm Password:</label><br>
                <input type="password" name="confirm_password" id="registerPasVerify"><br>
                <p id="registerPassVerifyError"></p><br>
            </div>
            <div class="form-button">
                <input type="submit" class="register" value="SIGN IN">
                <input type="reset" class="reset" value="Reset">
            </div>
            <p>Already have an account? <a id="changeTo" onclick="changeToLogin()">Login here</a>.</p>
        </form>
        <script src="../public/js/changeForm.js"></script>
        <script src="../public/js/RegisterValidation.js"></script>
    </div>
</body>

</html>