<?php

class ChatController
{
	public function login()
	{	
		require 'app/Views/login.view.php';
	}

	public function register(){
		require 'app/Views/register.view.php';
	}

	public function entry(){
		// Initialize the session
        session_start();
        
		/* Offene Rechnungen anzeigen */
		$pdo = connectDatabase();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $statement = $pdo->prepare('SELECT name FROM groupchats');
        $statement->execute();
        $chats = $statement->fetchAll();

		require 'app/Views/entry.view.php';
	}

	public function home(){
		// Initialize the session
        session_start();
        
		/* Offene Rechnungen anzeigen */
		$pdo = connectDatabase();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $statement = $pdo->prepare('');
        $statement->execute();
        $chats = $statement->fetchAll();

		require 'app/Views/home.view.php';
	}

	public function addChat(){
		$title = '';
        $pdo = connectDatabase();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = $_POST['chatname'];

            $statement = $pdo->prepare("INSERT INTO `groupchats` (name) VALUES 
            (:name)");
            $statement->bindParam(':name', $name, PDO::PARAM_STR);
            $statement->execute();

            header('Location: http://localhost/PHPWebSocketChat/chat/entry');
        }
		require 'app/Views/entry.view.php';
	}

	public function logout(){
        require 'app/Views/logout.php';
    }
}