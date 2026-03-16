<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pin and Throw — Login</title>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Syne:wght@700;800;900&family=DM+Sans:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>

<body class="login-page">

    <nav>
        <a href="index.html">
            <img src="resources/logo (2).png" alt="Pin and Throw" class="nav-logo-img">
        </a>
    </nav>

    <div class="page">
        <div class="card">
            <h1>Login</h1>

            <div class="error-msg" id="errorMsg"></div>

            <div class="field-group">
                <label for="inputUser">Username or Email</label>
                <input type="text" id="inputUser" autocomplete="username">
            </div>

            <div class="field-group">
                <label for="inputPass">Password</label>
                <input type="password" id="inputPass" autocomplete="current-password">
            </div>

            <div class="forgot">
                <a href="#">Forgot Password</a>
            </div>

            <div class="btn-row">
                <button class="btn-login" onclick="doLogin()">Login</button>
                <a href="index.html" class="btn-home" title="Back to Home">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1H15v-5h-6v5H4a1 1 0 01-1-1V9.5z" stroke="#fff"
                            stroke-width="1.8" stroke-linejoin="round" fill="none" />
                    </svg>
                </a>
            </div>

            <div class="register-hint">No Account?</div>
            <a href="register.php" class="register-link">Create an Account</a>
        </div>
    </div>

    <script>
        var DEMO_USERS = [
            { username: 'resident', email: 'resident@pinandthrow.com', password: 'password123', name: 'Juan dela Cruz', avatar: 'https://i.pravatar.cc/80?img=11' },
            { username: 'feone', email: 'feone@pinandthrow.com', password: 'feone123', name: 'Feone Marie Remoquillo', avatar: 'https://i.pravatar.cc/80?img=47' },
            { username: 'maria', email: 'maria@pinandthrow.com', password: 'maria123', name: 'Maria Santos', avatar: 'https://i.pravatar.cc/80?img=23' }
        ];

        function showError(msg) {
            var el = document.getElementById('errorMsg');
            el.textContent = msg;
            el.classList.add('show');
        }

        function doLogin() {
            var user = document.getElementById('inputUser').value.trim();
            var pass = document.getElementById('inputPass').value;
            document.getElementById('errorMsg').classList.remove('show');

            if (!user || !pass) {
                showError('Please fill in both fields.');
                return;
            }

            var found = null;
            for (var i = 0; i < DEMO_USERS.length; i++) {
                var u = DEMO_USERS[i];
                if ((u.username === user || u.email === user) && u.password === pass) { found = u; break; }
            }

            if (!found) {
                showError('Invalid username/email or password.');
                return;
            }

            localStorage.setItem('pat_session', JSON.stringify({ name: found.name, username: found.username, avatar: found.avatar }));
            window.location.href = 'index.html';
        }

        document.getElementById('inputPass').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') doLogin();
        });

        if (localStorage.getItem('pat_session')) {
            window.location.href = 'index.html';
        }
    </script>
</body>

</html>
