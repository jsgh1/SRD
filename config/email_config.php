<?php
// config/email_config.php

// Si usas Gmail:
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587); // TLS
define('SMTP_USER', 'juansebastiangonzalezhorta.inem@gmail.com'); // CAMBIA ESTO
define('SMTP_PASS', 'zeimmdhrwqwppohr'); // CAMBIA ESTO
define('SMTP_FROM_EMAIL', 'no.contestarsrd25@gmail.com'); // desde donde se envía
define('SMTP_FROM_NAME', 'Sistema de Registro');  // nombre que verá el usuario

// IMPORTANTE (Gmail):
// - Activa la verificación en dos pasos en tu cuenta.
// - Crea una "Contraseña de aplicación" y úsala en SMTP_PASS.
