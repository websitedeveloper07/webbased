<?php
session_start();

// Admin access key (change this to your secure key)
define('ADMIN_ACCESS_KEY', 'iloveyoupayal');

// Maintenance flag file path
define('MAINTENANCE_FLAG', 'maintenance.flag');

// Check if admin is logged in
if (isset($_POST['access_key'])) {
    if ($_POST['access_key'] === ADMIN_ACCESS_KEY) {
        $_SESSION['admin_authenticated'] = true;
    } else {
        $error = "Invalid access key!";
    }
}

// Handle maintenance toggle
if (isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true) {
    if (isset($_POST['maintenance_action'])) {
        if ($_POST['maintenance_action'] === 'enable') {
            file_put_contents(MAINTENANCE_FLAG, '1');
            $status_message = "Maintenance mode has been enabled.";
        } else {
            if (file_exists(MAINTENANCE_FLAG)) {
                unlink(MAINTENANCE_FLAG);
                $status_message = "Maintenance mode has been disabled.";
            }
        }
    }
    
    // Handle logout
    if (isset($_GET['logout'])) {
        unset($_SESSION['admin_authenticated']);
        header("Location: adminaccess_panel.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Access Panel | Card X CHK</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;600&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Rajdhani', sans-serif;
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            color: #e0e0e0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: rgba(15, 15, 35, 0.9);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(167, 139, 250, 0.2);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }
        
        .container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at center, rgba(124, 58, 237, 0.1) 0%, transparent 70%);
            z-index: -1;
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(167, 139, 250, 0.6);
            box-shadow: 0 0 20px rgba(124, 58, 237, 0.4);
        }
        
        h1 {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: #a78bfa;
            margin-bottom: 30px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #c4b5fd;
        }
        
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(167, 139, 250, 0.3);
            border-radius: 8px;
            color: #e0e0e0;
            font-size: 1rem;
            font-family: 'Rajdhani', sans-serif;
            transition: all 0.3s ease;
        }
        
        input[type="password"]:focus {
            outline: none;
            border-color: rgba(167, 139, 250, 0.6);
            box-shadow: 0 0 10px rgba(124, 58, 237, 0.3);
        }
        
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(90deg, #7c3aed, #a78bfa);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Rajdhani', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(124, 58, 237, 0.4);
        }
        
        button:active {
            transform: translateY(1px);
        }
        
        .maintenance-status {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .status-enabled {
            color: #f87171;
            border-left: 4px solid #f87171;
        }
        
        .status-disabled {
            color: #4ade80;
            border-left: 4px solid #4ade80;
        }
        
        .status-text {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .status-desc {
            font-size: 0.9rem;
            color: #a0a0a0;
        }
        
        .control-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        
        .control-buttons button {
            flex: 1;
        }
        
        .enable-btn {
            background: linear-gradient(90deg, #ef4444, #f87171);
        }
        
        .disable-btn {
            background: linear-gradient(90deg, #10b981, #4ade80);
        }
        
        .logout-btn {
            background: linear-gradient(90deg, #6b7280, #9ca3af);
            margin-top: 20px;
        }
        
        .error {
            color: #f87171;
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.2);
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .success {
            color: #4ade80;
            background: rgba(74, 222, 128, 0.1);
            border: 1px solid rgba(74, 222, 128, 0.2);
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }
        
        .grid-lines {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(124, 58, 237, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(124, 58, 237, 0.05) 1px, transparent 1px);
            background-size: 40px 40px;
            z-index: -1;
            animation: gridMove 20s linear infinite;
        }
        
        @keyframes gridMove {
            0% { transform: translate(0, 0); }
            100% { transform: translate(40px, 40px); }
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 0.8rem;
            color: #777;
        }
        
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .container {
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 1.6rem;
            }
            
            .control-buttons {
                flex-direction: column;
                gap: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 25px 15px;
            }
            
            h1 {
                font-size: 1.4rem;
                margin-bottom: 20px;
            }
            
            .logo {
                width: 70px;
                height: 70px;
            }
        }
    </style>
</head>
<body>
    <div class="grid-lines"></div>
    <div class="particles" id="particles"></div>
    
    <div class="container">
        <div class="logo-container">
            <img src="https://cxchk.site/assets/branding/cardxchk-mark.png" alt="Card X CHK Logo" class="logo">
        </div>
        
        <?php if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true): ?>
            <h1>Admin Access Panel</h1>
            
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label for="access_key">Enter Admin Access Key</label>
                    <input type="password" id="access_key" name="access_key" required>
                </div>
                <button type="submit">Authenticate</button>
            </form>
        <?php else: ?>
            <h1>Maintenance Control Panel</h1>
            
            <?php if (isset($status_message)): ?>
                <div class="success"><?php echo $status_message; ?></div>
            <?php endif; ?>
            
            <div class="maintenance-status <?php echo file_exists(MAINTENANCE_FLAG) ? 'status-enabled' : 'status-disabled'; ?>">
                <div class="status-text">
                    Maintenance Mode: <?php echo file_exists(MAINTENANCE_FLAG) ? 'ENABLED' : 'DISABLED'; ?>
                </div>
                <div class="status-desc">
                    <?php 
                    if (file_exists(MAINTENANCE_FLAG)) {
                        echo "Visitors are currently seeing the maintenance page.";
                    } else {
                        echo "Visitors can access the website normally.";
                    }
                    ?>
                </div>
            </div>
            
            <div class="control-buttons">
                <?php if (!file_exists(MAINTENANCE_FLAG)): ?>
                    <button type="submit" form="maintenance-form" name="maintenance_action" value="enable" class="enable-btn">
                        <i class="fas fa-power-off"></i> Enable Maintenance
                    </button>
                <?php else: ?>
                    <button type="submit" form="maintenance-form" name="maintenance_action" value="disable" class="disable-btn">
                        <i class="fas fa-power-off"></i> Disable Maintenance
                    </button>
                <?php endif; ?>
            </div>
            
            <form id="maintenance-form" method="post" style="display: none;"></form>
            
            <a href="adminaccess_panel.php?logout=1" class="logout-btn" style="display: block; text-align: center; text-decoration: none; padding: 12px; border-radius: 8px; margin-top: 20px;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        <?php endif; ?>
        
        <div class="footer">
            <p>© <?php echo date('Y'); ?> Card X CHK. All rights reserved.</p>
        </div>
    </div>

    <script>
        // Create floating particles
        document.addEventListener('DOMContentLoaded', function() {
            const particlesContainer = document.getElementById('particles');
            
            // Create particles
            for (let i = 0; i < 30; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                
                // Random size between 2px and 5px
                const size = Math.random() * 3 + 2;
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                
                // Random position
                particle.style.left = `${Math.random() * 100}%`;
                particle.style.top = `${Math.random() * 100}%`;
                
                // Random opacity
                particle.style.opacity = Math.random() * 0.5 + 0.1;
                
                // Random animation delay
                particle.style.animationDelay = `${Math.random() * 5}s`;
                
                // Random animation duration
                particle.style.animationDuration = `${15 + Math.random() * 30}s`;
                
                particlesContainer.appendChild(particle);
            }
            
            // Create CSS animation for particles
            const style = document.createElement('style');
            style.innerHTML = `
                .particle {
                    position: absolute;
                    background: #a78bfa;
                    border-radius: 50%;
                    pointer-events: none;
                    animation: float 20s infinite linear;
                }
                
                @keyframes float {
                    0% { 
                        transform: translateY(0) translateX(0); 
                        opacity: 0;
                    }
                    10% { 
                        opacity: 0.7;
                    }
                    90% { 
                        opacity: 0.7;
                    }
                    100% { 
                        transform: translateY(-100vh) translateX(100px); 
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>
