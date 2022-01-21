-- This is the SQL for the database structure from the WebSocket website --

DROP DATABASE IF EXISTS WebSocket;
CREATE DATABASE WebSocket;
USE WebSocket;

CREATE TABLE users (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE groupchats (
	id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
	name VARCHAR(100) NOT NULL
);

CREATE TABLE history (
	id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
	fk_groupchatId INT NOT NULL,
	fk_userId INT NOT NULL,
	FOREIGN KEY (fk_groupchatId) REFERENCES groupchats(id),
	FOREIGN KEY (fk_userId) REFERENCES users(id)
);