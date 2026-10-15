<?php

header('Content-Type: text/html; charset=utf-8');

echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Laravel Dockerized</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding: 50px; background-color: #f8fafc; }
        .card { background: white; max-width: 500px; margin: 0 auto; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        h1 { color: #ef4444; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Hello from Laravel in Docker!</h1>
        <p>PHP runtime running successfully via Docker container.</p>
        <p><strong>Environment:</strong> PHP ' . PHP_VERSION . ' (Alpine Linux)</p>
    </div>
</body>
</html>';