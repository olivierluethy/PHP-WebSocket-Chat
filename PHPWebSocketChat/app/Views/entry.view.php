<?php
// Check if the user is logged in, if not then redirect him to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login");
    exit;
}

$chatCounter = 0;

foreach ($chats as $chat){
	$chatCounter++;
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
	<link rel="stylesheet" href="../public/css/entry.css">
</head>
<body>
    <header>
		<img src="../images/shortcut.png" alt="">
		<nav>
			<h1>PHP WebSocket Chat</h1>
		</nav>
		<div class="dropdown">
			<button class="dropbtn"><?php echo $_SESSION["username"]; ?></button>
			<div class="dropdown-content">
				<a href="logout">Logout</a>
			</div>
		</div>
	</header>

    <h1 class="title">Select chat or create one</h1>

    <main>
        <p>Select chat:</p>
		<?php
		if($chatCounter > 0){
			echo "<select name='cars' id='cars'>";
			foreach ($chats as $chat){
        		echo "<option value='volvo'>". $chat['name'] . "</option>";
        	}
			echo "</select><br>";
			echo "<button type='submit'>Join</button><br><br>";
		}else {
			echo "<p style='color: red; font-weight: bold';>There aren't any chats available yet</p><br><br>";
		}
		?>
        <p>Create chat:</p>

		<form action="addChat" method="post">
			<input type="text" id="chatname" name="chatname"><br><br>
			<input type="submit" value="Create Chat">
		</form> 
    </main>

</body>
</html>