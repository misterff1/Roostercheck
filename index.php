<?php
$config = file_exists('config.php') ? include 'config.php' : [];

$pinLength = isset($config['pin_length']) ? (int)$config['pin_length'] : 6;
if ($pinLength < 3) {
    $pinLength = 3;
} elseif ($pinLength > 6) {
    $pinLength = 6;
}

function get_valid_timeout($value, $default) {
    if (!isset($value) || !is_numeric($value)) {
        return $default;
    }
    $val = (int)$value;
    if ($val < 5) {
        return 5;
    }
    if ($val > 60) {
        return 60;
    }
    return $val;
}

$pinResetTimeout = get_valid_timeout($config['pin_reset_timeout'] ?? null, 15);
$logoutTimeout = get_valid_timeout($config['logout_timeout'] ?? null, 20);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Zermelo Rooster</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div id="app" data-pin-reset-timeout="<?php echo $pinResetTimeout; ?>" data-logout-timeout="<?php echo $logoutTimeout; ?>">
        <div id="login-screen" class="screen">
            <div class="glass-card">
                <img src="logo.png" alt="Zermelo" class="login-logo">
                <div class="pin-display" id="pin-display">
                    <?php for ($i = 0; $i < $pinLength; $i++): ?>
                        <div class="pin-digit"></div>
                    <?php endfor; ?>
                </div>

                <div class="numpad">
                    <button class="num-btn" data-num="1">1</button>
                    <button class="num-btn" data-num="2">2</button>
                    <button class="num-btn" data-num="3">3</button>
                    <button class="num-btn" data-num="4">4</button>
                    <button class="num-btn" data-num="5">5</button>
                    <button class="num-btn" data-num="6">6</button>
                    <button class="num-btn" data-num="7">7</button>
                    <button class="num-btn" data-num="8">8</button>
                    <button class="num-btn" data-num="9">9</button>
                    <div class="numpad-spacer"></div>
                    <button class="num-btn" data-num="0">0</button>
                    <button class="num-btn revert-btn" id="revert-btn">⌫</button>
                </div>

                <div id="loading-indicator" class="hidden">
                    <div class="spinner"></div>
                    <span>Rooster laden...</span>
                </div>
            </div>
        </div>

        <div id="schedule-screen" class="screen hidden">
            <div id="header-sentinel"></div>
            <div class="header">
                <div class="student-info">
                    <span id="display-student"></span>
                </div>
                <div class="timer-container">
                    <svg class="timer-svg" viewBox="0 0 40 40">
                        <circle class="timer-bg" cx="20" cy="20" r="18"></circle>
                        <circle class="timer-progress" cx="20" cy="20" r="18"></circle>
                    </svg>
                    <span id="timer-text"><?php echo $logoutTimeout; ?></span>
                </div>
                <button id="logout-btn">Uitloggen</button>
            </div>

            <div id="message-container" class="message-banner hidden"></div>
            
            <div id="next-lesson-container">
                <div id="next-lesson"></div>
            </div>

            <div id="today-lessons-container">
                <div id="today-lessons-list"></div>
            </div>

            <div id="tomorrow-divider" class="divider hidden">
                <span>Morgen</span>
            </div>

            <div id="tomorrow-lessons-container">
                <div id="tomorrow-lessons-list"></div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
