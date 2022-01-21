<?php
session_start();
// Check if the user is logged in, if not then redirect him to login page
// if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
//     header("location: index.php");
//     exit;
// }
?>
<!DOCTYPE html>
<html>
<head>
	<title>Chat</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width">
	<script src="js/jquery.js" type="text/javascript"></script>
	<link rel="shortcut icon" href="../images/shortcut.png">
	<link rel="stylesheet" href="../public/css/home.css">
</head>
<body>
	<header>
		<img src="../images/shortcut.png" alt="">
		<nav>
			<h1>PHP WebSocket Chat</h1>
		</nav>
		<div class="chatname">
			<h1>Chatname</h1>
		</div>
		<div class="dropdown">
			<button class="dropbtn"><?php echo $_SESSION["username"]; ?></button>
			<div class="dropdown-content">
				<a href="logout">Logout</a>
			</div>
		</div>
	</header>
	<div class="background-image"></div>
	<div id="wrapper">
		<div id="chat_output"></div>
		<textarea id="chat_input" placeholder="Deine Nachricht..."></textarea>
		<script type="text/javascript">
		var userId = parseInt("<?php echo $_SESSION["id"]; ?>");
		var UserName = "<?php echo $_SESSION["username"]; ?>";
		
		jQuery(function($){
			// Websocket
			var websocket_server = new WebSocket("ws://localhost:8080/");
			websocket_server.onopen = function(e) {
				websocket_server.send(
					JSON.stringify({
						'type':'socket',
						'user_id': UserName
					})
				);
			};
			websocket_server.onerror = function(e) {
				// Errorhandling
			}
			websocket_server.onmessage = function(e)
			{
				var json = JSON.parse(e.data);
				switch(json.type) {
					case 'chat':
						$('#chat_output').append(json.msg);
						break;
				}
			}
			// Events
			$('#chat_input').on('keyup',function(e){
				if(e.keyCode==13 && !e.shiftKey)
				{
					var chat_msg = $(this).val();
					websocket_server.send(
						JSON.stringify({
							'type':'chat', // Der Typ der Nachricht ist Chat
							'user_id': UserName, // Hier wird die zufällige Session des Users hinzugefügt
							'chat_msg':chat_msg // Chatnachricht wird hier hinzugefügt
						})
					);
					$(this).val('');
				}
			});
		});
		</script>
	</div>
</body>
</html>