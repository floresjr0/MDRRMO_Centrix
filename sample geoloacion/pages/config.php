<?php
// Basic configuration for MDRRMO web app

date_default_timezone_set('Asia/Manila');

// TODO: Change these to your actual database credentials.
const DB_HOST = 'localhost';
const DB_NAME = 'mdrrmo_db';
const DB_USER = 'root';
const DB_PASS = '';

// Mail settings for PHPMailer (configure according to your SMTP provider)
const MAIL_FROM_ADDRESS = 'no-reply@example.com';
const MAIL_FROM_NAME    = 'MDRRMO San Ildefonso';
const MAIL_SMTP_HOST    = 'smtp.example.com';
const MAIL_SMTP_PORT    = 587;
const MAIL_SMTP_USERNAME = 'marteflores07@gmail.com';
const MAIL_SMTP_PASSWORD = 'chjxflpeftslypby';
const MAIL_SMTP_SECURE   = 'tls'; // or 'ssl'

// Weather API (optional, used by cron_fetch_weather.php)
// Obtain an API key from a provider such as OpenWeatherMap and place it here.
const WEATHER_API_KEY = '69eaf017b4b5ae9da5f860057b79920d';

