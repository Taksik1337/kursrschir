<?php
require 'functions.php';

if (isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); exit(); }

$auth_error = "";
$initial_tab = "login";

// --- 1. ЛОГИКА АВТОРИЗАЦИИ / РЕГИСТРАЦИИ ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $u = $_POST['username'];
    $p = $_POST['password'];
    $action = $_POST['action'];

    if ($action == 'register') {
        $initial_tab = "register";
        $res = call_api("POST", "$api_url/register", ["username" => $u, "password" => $p]);
        if ($res['code'] == 200) {
            // Успех -> сразу логиним
            $_SESSION['token'] = $res['body']['access_token'];
            $_SESSION['username'] = $u;
            header("Location: index.php"); exit();
        } else {
            $auth_error = $res['body']['detail'] ?? "Ошибка регистрации";
        }
    }

    if ($action == 'login') {
        $initial_tab = "login";
        $postData = http_build_query(["username" => $u, "password" => $p]);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "$api_url/token", CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
        ]);
        $resp = json_decode(curl_exec($curl), true); $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($code == 200 && isset($resp['access_token'])) {
            $_SESSION['token'] = $resp['access_token'];
            $_SESSION['username'] = $u;
            header("Location: index.php"); exit();
        } else {
            $auth_error = "Неверный логин или пароль";
        }
    }
}

// --- 2. AJAX СОХРАНЕНИЕ ---
if (isset($_SESSION['token']) && isset($_GET['ajax_sync'])) {
    $input = json_decode(file_get_contents('php://input'), true);
    $payload = ["device_id" => $input['device'] ?? "Web", "timestamp" => microtime(true), "data" => $input['data']];
    $res = call_api("POST", "$api_url/sync", $payload, $_SESSION['token']);
    echo json_encode($res['body'] ?? []); exit();
}

// --- 3. ЗАГРУЗКА ДАННЫХ ПРИ ВХОДЕ ---
$initial_data = ["note" => "", "todos" => [], "theme" => "light", "profile" => ["email" => "", "avatar" => "https://cdn-icons-png.flaticon.com/512/149/149071.png"]];
if (isset($_SESSION['token'])) {
    $res = call_api("POST", "$api_url/sync", ["device_id" => "Init", "timestamp" => 0, "data" => (object)[]], $_SESSION['token']);
    if (isset($res['body']['data']) && is_array($res['body']['data'])) {
        $initial_data = array_merge($initial_data, $res['body']['data']);
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>SyncHub Enterprise</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="<?= $initial_data['theme'] == 'dark' ? 'dark-mode' : '' ?>">

<?php if (!isset($_SESSION['token'])): ?>
    <!-- ЭКРАН АВТОРИЗАЦИИ -->
    <div class="login-container">
        <div class="login-card">
            <div style="font-size: 40px; color:#6366f1; margin-bottom: 10px;"><i class="fas fa-layer-group"></i></div>
            <h2 style="margin-bottom: 30px;">SyncHub Cloud</h2>

            <!-- Переключатель вкладок -->
            <div class="auth-tabs">
                <div class="auth-tab <?= $initial_tab == 'login' ? 'active' : '' ?>" onclick="switchAuth('login')">Вход</div>
                <div class="auth-tab <?= $initial_tab == 'register' ? 'active' : '' ?>" onclick="switchAuth('register')">Регистрация</div>
            </div>

            <?php if ($auth_error): ?>
                <div style="color:#ef4444; font-size:13px; margin-bottom:15px; background:#fee2e2; padding:8px; border-radius:8px;">
                    <i class="fas fa-exclamation-circle"></i> <?= $auth_error ?>
                </div>
            <?php endif; ?>

            <!-- Форма Входа -->
            <form id="loginForm" method="POST" style="display: <?= $initial_tab == 'login' ? 'block' : 'none' ?>;">
                <input type="hidden" name="action" value="login">
                <input type="text" name="username" placeholder="Логин" required>
                <input type="password" name="password" placeholder="Пароль" required>
                <button type="submit" class="btn-primary">Войти в систему</button>
            </form>

            <!-- Форма Регистрации -->
            <form id="registerForm" method="POST" style="display: <?= $initial_tab == 'register' ? 'block' : 'none' ?>;">
                <input type="hidden" name="action" value="register">
                <input type="text" name="username" placeholder="Придумайте логин" required>
                <input type="password" name="password" placeholder="Придумайте пароль" required>
                <button type="submit" class="btn-primary" style="background: #10b981;">Создать аккаунт</button>
            </form>
        </div>
    </div>

    <script>
        function switchAuth(type) {
            document.getElementById('loginForm').style.display = (type === 'login') ? 'block' : 'none';
            document.getElementById('registerForm').style.display = (type === 'register') ? 'block' : 'none';
            document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
            event.target.classList.add('active');
        }
    </script>

<?php else: ?>
    <!-- ГЛАВНЫЙ ИНТЕРФЕЙС (SyncHub) -->
    <div class="app-shell">
        <div class="sidebar">
            <div class="logo"><i class="fas fa-cloud"></i> SyncHub</div>
            <div class="nav-btn active" onclick="showTab('notes', this)"><i class="fas fa-sticky-note"></i> Заметки</div>
            <div class="nav-btn" onclick="showTab('tasks', this)"><i class="fas fa-tasks"></i> Задачи</div>
            <div class="nav-btn" onclick="showTab('profile', this)"><i class="fas fa-user-circle"></i> Профиль</div>
            <div style="margin-top: auto;">
                <label style="font-size:10px; color:gray; margin-left:15px;">УСТРОЙСТВО:</label>
                <select id="deviceSelector" style="width:90%; margin: 5px auto 15px auto; display:block; padding:8px; border-radius:10px; border:1px solid var(--border); background: var(--bg); color: var(--text);">
                    <option value="Windows Desktop">🖥️ Windows Desktop</option>
                    <option value="Mobile App">📱 Mobile App</option>
                    <option value="Web Terminal">💻 Web Terminal</option>
                </select>
                <div class="nav-btn" onclick="toggleTheme()"><i class="fas fa-moon"></i> Темная тема</div>
                <a href="?logout=1" class="nav-btn" style="text-decoration:none;"><i class="fas fa-sign-out-alt"></i> Выйти</a>
            </div>
        </div>

        <div class="main-content">
            <div class="top-bar">
                <h2 id="pageTitle">Мои Заметки</h2>
                <div id="status" class="status-indicator"><i class="fas fa-check-circle"></i> Синхронизировано</div>
            </div>

            <div id="tab-notes" class="tab-content">
                <textarea id="noteInput" placeholder="Начните писать здесь..."><?= htmlspecialchars($initial_data['note']) ?></textarea>
            </div>

            <div id="tab-tasks" class="tab-content" style="display:none;">
                <div style="display:flex; gap:10px; margin-bottom:20px;">
                    <input type="text" id="newTaskInput" placeholder="Добавить задачу..." style="margin:0;">
                    <button class="btn-primary" style="width:auto; padding: 0 20px;" onclick="addTask()">OK</button>
                </div>
                <div id="todoList"></div>
            </div>

            <div id="tab-profile" class="tab-content" style="display:none;">
                <div style="text-align:center; margin-bottom:30px;">
                    <img src="<?= htmlspecialchars($initial_data['profile']['avatar']) ?>" id="avatarPreview" style="width:100px; height:100px; border-radius:50%; border:4px solid #6366f1; object-fit:cover; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                    <h3 style="margin: 10px 0 5px 0;"><?= $_SESSION['username'] ?></h3>
                    <span class="status-badge" style="background:#d1fae5; color:#065f46; padding:4px 12px; border-radius:20px; font-size:12px;">Аккаунт верифицирован</span>
                </div>
                <div style="background: var(--bg); padding: 25px; border-radius: 20px; border: 1px solid var(--border);">
                    <div style="margin-bottom:20px;">
                        <label style="display:block; font-size:12px; margin-bottom:8px; color:var(--text-sec); font-weight:600;">EMAIL АДРЕС</label>
                        <input type="text" id="emailInput" value="<?= htmlspecialchars($initial_data['profile']['email']) ?>" style="margin:0;" placeholder="example@mail.com">
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; margin-bottom:8px; color:var(--text-sec); font-weight:600;">URL АВАТАРА</label>
                        <input type="text" id="avatarInput" value="<?= htmlspecialchars($initial_data['profile']['avatar']) ?>" style="margin:0;">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let appData = <?= json_encode($initial_data) ?>;
        let saveTimeout;

        function showTab(name, btn) {
            document.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
            document.getElementById('tab-' + name).style.display = 'block';
            document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('pageTitle').innerText = btn.innerText;
        }

        async function syncWithServer() {
            document.getElementById('status').classList.remove('visible');
            try {
                const response = await fetch('index.php?ajax_sync=1', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        device: document.getElementById('deviceSelector').value,
                        data: appData
                    })
                });
                if (response.ok) document.getElementById('status').classList.add('visible');
            } catch (e) { console.error(e); }
        }

        function triggerSave() {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(syncWithServer, 1000);
        }

        document.getElementById('noteInput').addEventListener('input', e => { appData.note = e.target.value; triggerSave(); });
        document.getElementById('emailInput').addEventListener('input', e => { appData.profile.email = e.target.value; triggerSave(); });
        document.getElementById('avatarInput').addEventListener('input', e => {
            appData.profile.avatar = e.target.value;
            document.getElementById('avatarPreview').src = e.target.value;
            triggerSave();
        });

        function toggleTheme() {
            appData.theme = (appData.theme === 'light') ? 'dark' : 'light';
            document.body.className = (appData.theme === 'dark') ? 'dark-mode' : '';
            syncWithServer();
        }

        function renderTasks() {
            const list = document.getElementById('todoList');
            list.innerHTML = '';
            appData.todos.forEach((t, i) => {
                list.innerHTML += `
                <div class="todo-item">
                    <input type="checkbox" class="todo-checkbox" ${t.done ? 'checked' : ''} onchange="toggleTask(${i})">
                    <span class="todo-text ${t.done ? 'done' : ''}">${t.text}</span>
                    <i class="fas fa-trash delete-btn" onclick="deleteTask(${i})"></i>
                </div>`;
            });
        }
        function addTask() {
            const val = document.getElementById('newTaskInput').value;
            if(val) { appData.todos.push({text: val, done: false}); document.getElementById('newTaskInput').value = ''; renderTasks(); syncWithServer(); }
        }
        function toggleTask(i) { appData.todos[i].done = !appData.todos[i].done; renderTasks(); syncWithServer(); }
        function deleteTask(i) { appData.todos.splice(i, 1); renderTasks(); syncWithServer(); }

        renderTasks();
    </script>
<?php endif; ?>
</body>
</html>