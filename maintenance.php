<?php
session_start();

// Define the same admin access key as in adminaccess_panel.php
define('ADMIN_ACCESS_KEY', 'YOUR_SECURE_ADMIN_KEY_123');

// Handle admin login
if (isset($_POST['admin_password']) && $_POST['admin_password'] === ADMIN_ACCESS_KEY) {
    $_SESSION['admin_authenticated'] = true;
    header("Location: /index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲 - Under Maintenance</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Space Grotesk', sans-serif;
            background: #0d0d0d;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Simple gradient orbs */
        body::before,
        body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.15;
            pointer-events: none;
        }

        body::before {
            width: 500px;
            height: 500px;
            background: #8b5cf6;
            top: -200px;
            right: -200px;
        }

        body::after {
            width: 400px;
            height: 400px;
            background: #6366f1;
            bottom: -150px;
            left: -150px;
        }

        .container {
            max-width: 450px;
            width: 100%;
            text-align: center;
            position: relative;
            z-index: 10;
            animation: fadeIn 0.5s ease-out;
        }

        /* Logo */
        .logo-wrapper {
            margin-bottom: 32px;
        }

        .logo-circle {
            width: 100px;
            height: 100px;
            margin: 0 auto 24px;
            background: linear-gradient(135deg, #8b5cf6, #6366f1);
            border-radius: 50%;
            padding: 3px;
            box-shadow: 0 8px 32px rgba(139, 92, 246, 0.4);
        }

        .logo-inner {
            width: 100%;
            height: 100%;
            background: #0d0d0d;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .logo-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .brand-name {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #a78bfa, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: 4px;
        }

        /* Card */
        .main-card {
            background: rgba(20, 20, 20, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: 32px;
            padding: 48px 36px;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.4);
        }

        h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 16px;
            letter-spacing: -0.5px;
        }

        .subtitle {
            font-size: 1.05rem;
            color: #9ca3af;
            line-height: 1.6;
            margin-bottom: 40px;
        }

        /* Animated bars */
        .loader-bars {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            gap: 8px;
            height: 50px;
            margin: 40px 0;
        }

        .bar {
            width: 8px;
            background: linear-gradient(180deg, #8b5cf6, #6366f1);
            border-radius: 10px;
            animation: wave 1.2s ease-in-out infinite;
        }

        .bar:nth-child(1) { animation-delay: 0s; }
        .bar:nth-child(2) { animation-delay: 0.1s; }
        .bar:nth-child(3) { animation-delay: 0.2s; }
        .bar:nth-child(4) { animation-delay: 0.3s; }
        .bar:nth-child(5) { animation-delay: 0.4s; }

        /* Status text */
        .status {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(139, 92, 246, 0.3);
            padding: 12px 24px;
            border-radius: 50px;
            margin-top: 32px;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background: #8b5cf6;
            border-radius: 50%;
            animation: blink 2s ease-in-out infinite;
        }

        .status-text {
            font-size: 0.9rem;
            color: #a78bfa;
            font-weight: 600;
        }

        /* Admin Login Button */
        .admin-login-btn {
            background: linear-gradient(135deg, #8b5cf6, #6366f1);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 14px 28px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 40px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 8px 24px rgba(139, 92, 246, 0.3);
        }

        .admin-login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(139, 92, 246, 0.4);
        }

        .admin-login-btn i {
            font-size: 1.1rem;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: rgba(20, 20, 20, 0.9);
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 24px;
            padding: 36px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.5);
            animation: slideUp 0.3s ease-out;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .modal-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: white;
        }

        .modal-close {
            background: none;
            border: none;
            color: #9ca3af;
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.3s;
        }

        .modal-close:hover {
            color: white;
        }

        .modal-body {
            margin-bottom: 24px;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #9ca3af;
            font-size: 0.9rem;
        }

        .form-input {
            width: 100%;
            padding: 14px 18px;
            background: rgba(30, 30, 30, 0.7);
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
        }

        .modal-footer {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn {
            padding: 12px 20px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-cancel {
            background: rgba(255, 255, 255, 0.1);
            color: #9ca3af;
        }

        .btn-cancel:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .btn-submit {
            background: linear-gradient(135deg, #8b5cf6, #6366f1);
            color: white;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(139, 92, 246, 0.3);
        }

        .error-message {
            color: #ef4444;
            font-size: 0.85rem;
            margin-top: 8px;
            text-align: left;
            display: none;
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes wave {
            0%, 100% {
                height: 20px;
                opacity: 0.4;
            }
            50% {
                height: 50px;
                opacity: 1;
            }
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }

        /* Mobile responsive */
        @media (max-width: 480px) {
            body {
                padding: 16px;
            }

            .logo-circle {
                width: 90px;
                height: 90px;
                margin-bottom: 20px;
            }

            .logo-inner {
                padding: 18px;
            }

            .brand-name {
                font-size: 1.65rem;
                letter-spacing: 3px;
            }

            .main-card {
                padding: 36px 28px;
                border-radius: 28px;
            }

            h1 {
                font-size: 1.65rem;
            }

            .subtitle {
                font-size: 1rem;
            }

            .loader-bars {
                height: 45px;
                margin: 32px 0;
            }

            .bar {
                width: 7px;
            }

            .status {
                padding: 10px 20px;
            }

            .status-text {
                font-size: 0.85rem;
            }

            .admin-login-btn {
                padding: 12px 24px;
                font-size: 0.9rem;
            }

            .modal-content {
                padding: 28px 24px;
            }

            .modal-title {
                font-size: 1.2rem;
            }
        }

        @media (max-width: 360px) {
            .logo-circle {
                width: 80px;
                height: 80px;
            }

            .logo-inner {
                padding: 16px;
            }

            .brand-name {
                font-size: 1.5rem;
                letter-spacing: 2px;
            }

            .main-card {
                padding: 32px 24px;
            }

            h1 {
                font-size: 1.5rem;
            }

            .subtitle {
                font-size: 0.95rem;
            }

            .loader-bars {
                height: 40px;
            }

            .bar {
                width: 6px;
                gap: 6px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-wrapper">
            <div class="logo-circle">
                <div class="logo-inner">
                    <img src="https://cxchk.site/assets/branding/cardxchk-mark.png" alt="Logo" class="logo-img">
                </div>
            </div>
            <div class="brand-name">𝑪𝑨𝑹𝑫 ✘ 𝑪𝑯𝑲</div>
        </div>

        <div class="main-card">
            <h1>Under Maintenance</h1>
            <p class="subtitle">
                We're making some improvements. Please check back in a few moments.
            </p>

            <div class="loader-bars">
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
            </div>

            <div class="status">
                <div class="status-dot"></div>
                <span class="status-text">Updating System</span>
            </div>

            <button class="admin-login-btn" onclick="openLoginModal()">
                <i class="fas fa-user-shield"></i> Admin Login
            </button>
        </div>
    </div>

    <!-- Admin Login Modal -->
    <div class="modal" id="loginModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Admin Access</h3>
                <button class="modal-close" onclick="closeLoginModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="adminLoginForm" method="post">
                    <div class="form-group">
                        <label class="form-label" for="adminPassword">Admin Access Key</label>
                        <input type="password" id="adminPassword" name="admin_password" class="form-input" placeholder="Enter admin access key" required>
                        <div class="error-message" id="loginError">Invalid access key. Please try again.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" onclick="closeLoginModal()">Cancel</button>
                <button type="submit" form="adminLoginForm" class="btn btn-submit">Login</button>
            </div>
        </div>
    </div>

    <script>
        function openLoginModal() {
            document.getElementById('loginModal').classList.add('active');
            document.getElementById('adminPassword').focus();
        }

        function closeLoginModal() {
            document.getElementById('loginModal').classList.remove('active');
            document.getElementById('adminPassword').value = '';
            document.getElementById('loginError').style.display = 'none';
        }

        // Handle form submission
        document.getElementById('adminLoginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const password = document.getElementById('adminPassword').value;
            const errorElement = document.getElementById('loginError');
            
            // Simple validation (in a real app, this would be done server-side)
            if (password === '') {
                errorElement.style.display = 'block';
                return;
            }
            
            // Submit the form
            this.submit();
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLoginModal();
            }
        });
    </script>
</body>
</html>
