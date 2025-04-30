<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login & Register</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* General Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        /* Container */
        .container {
            position: relative;
            width: 100%;
            max-width: 900px;
            height: 500px;
            background: #ffffff;
            border-radius: 15px;
            overflow: hidden;
            display: flex;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        /* Panels */
        .form-container {
            position: absolute;
            top: 0;
            width: 50%;
            height: 100%;
            transition: all 0.6s ease-in-out;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            padding: 40px;
            background: #fff;
        }

        /* Sign-In & Sign-Up Forms */
        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
            width: 100%;
        }

        h2 {
            color: #333;
            font-size: 1.8rem;
            margin-bottom: 10px;
        }

        .input-field {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            border: 1px solid #ccc;
            border-radius: 30px;
            gap: 10px;
        }

        .input-field input {
            border: none;
            outline: none;
            width: 100%;
        }

        button {
            background-color: #2ebd93;
            border: none;
            color: white;
            padding: 10px 0;
            font-weight: bold;
            border-radius: 30px;
            cursor: pointer;
            transition: 0.3s;
        }

        button:hover {
            background-color: #249070;
        }

        /* Toggle Panel */
        .overlay-container {
            position: absolute;
            top: 0;
            left: 50%;
            width: 50%;
            height: 100%;
            background: #2ebd93;
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            transition: all 0.6s ease-in-out;
        }

        .overlay-container h2 {
            margin-bottom: 10px;
        }

        .overlay-container button {
            background-color: transparent;
            border: 2px solid #fff;
            color: #fff;
            padding: 10px 20px;
            border-radius: 30px;
        }

        /* Animations */
        .container.right-panel-active .sign-in {
            transform: translateX(100%);
        }

        .container.right-panel-active .overlay-container {
            transform: translateX(-100%);
        }

        .container.right-panel-active .sign-up {
            transform: translateX(0%);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                height: 700px;
            }
            .form-container,
            .overlay-container {
                position: static;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container" id="container">
        <!-- Sign-In Form -->
        <div class="form-container sign-in">
            <h2>Sign In</h2>
            <form method="POST" action="login_process.php">
                <div class="input-field">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" placeholder="Username" required>
                </div>
                <div class="input-field">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                <button type="submit">SIGN IN</button>
            </form>
        </div>

        <!-- Sign-Up Form -->
        <div class="form-container sign-up">
            <h2>Create Account</h2>
            <form method="POST" action="register_process.php">
                <div class="input-field">
                    <i class="fas fa-user"></i>
                    <input type="text" name="name" placeholder="Name" required>
                </div>
                <div class="input-field">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="Email" required>
                </div>
                <div class="input-field">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                <button type="submit">SIGN UP</button>
            </form>
        </div>

        <!-- Overlay -->
        <div class="overlay-container">
            <h2 id="overlay-text">Welcome Back!</h2>
            <p>To keep connected with us please login with your personal info</p>
            <button id="toggleButton">SIGN UP</button>
        </div>
    </div>

    <script>
        const container = document.getElementById('container');
        const toggleButton = document.getElementById('toggleButton');
        const overlayText = document.getElementById('overlay-text');

        toggleButton.addEventListener('click', () => {
            container.classList.toggle('right-panel-active');
            overlayText.textContent = container.classList.contains('right-panel-active')
                ? "Hello, Friend!"
                : "Welcome Back!";
            toggleButton.textContent = container.classList.contains('right-panel-active')
                ? "SIGN IN"
                : "SIGN UP";
        });
    </script>
</body>
</html>
