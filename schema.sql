
CREATE DATABASE IF NOT EXISTS order;
USE order;

-- Users
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('superadmin', 'admin') NOT NULL,
  suspended BOOLEAN DEFAULT FALSE,
  date_added DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Products
CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  category VARCHAR(100),
  price DECIMAL(10,2) NOT NULL,
  image VARCHAR(255),
  added_by VARCHAR(100),
  date_added DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Transactions
CREATE TABLE transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_type ENUM('Dine-In', 'Take-Out') NOT NULL,
  items TEXT NOT NULL,
  total DECIMAL(10,2) NOT NULL,
  cashier VARCHAR(100),
  date_added DATETIME DEFAULT CURRENT_TIMESTAMP
);
