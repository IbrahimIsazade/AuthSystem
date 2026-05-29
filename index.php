<?php
session_start();

// Autoloading simulation (require dependencies)
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/core/PasswordGenerator.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Vault.php';

$dbConnection = (new Database())->getConnection();
$userModel = new User($dbConnection);
$vaultModel = new Vault($dbConnection);

// Basic Routing & Controllers
$viewData = ['message' => '', 'generated_password' => '', 'entries' => []];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'register':
            if ($userModel->register($_POST['username'], $_POST['password'])) {
                $viewData['message'] = "Account created. Please log in.";
            } else {
                $viewData['message'] = "Username taken or creation failed.";
            }
            break;

        case 'login':
            if ($userModel->login($_POST['username'], $_POST['password'])) {
                $_SESSION['user_id'] = $userModel->id;
                $_SESSION['username'] = $userModel->username;
                $_SESSION['master_key'] = $userModel->getActiveMasterKey();
                header("Location: index.php");
                exit;
            } else {
                $viewData['message'] = "Invalid credentials.";
            }
            break;

        case 'generate':
            $viewData['generated_password'] = PasswordGenerator::generate(
                (int)$_POST['lower'], (int)$_POST['upper'],
                (int)$_POST['numbers'], (int)$_POST['specials']
            );
            break;

        case 'save_password':
            if (isset($_SESSION['user_id'])) {
                $success = $vaultModel->saveEntry(
                    $_SESSION['user_id'],
                    $_POST['service'],
                    $_POST['secret'],
                    $_SESSION['master_key']
                );
                $viewData['message'] = $success ? "Password securely vaulted." : "Failed to save.";
            }
            break;

        case 'change_password':
            if (isset($_SESSION['user_id'])) {
                $userModel->id = $_SESSION['user_id'];
                if ($userModel->updatePassword($_POST['old_password'], $_POST['new_password'])) {
                    $viewData['message'] = "Login password changed. Master Key recoded successfully.";
                } else {
                    $viewData['message'] = "Failed to update password. Check old password.";
                }
            }
            break;
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Fetch vault entries if logged in
if (isset($_SESSION['user_id'])) {
    $viewData['entries'] = $vaultModel->getUserEntries($_SESSION['user_id'], $_SESSION['master_key']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vault Architecture</title>
    <style>
        :root { --primary: #2563eb; --bg: #f8fafc; --surface: #ffffff; --text: #1e293b; --border: #e2e8f0; }
        body { font-family: system-ui, -apple-system, sans-serif; background: var(--bg); color: var(--text); padding: 2rem; margin: 0; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .flex { display: flex; gap: 1rem; flex-wrap: wrap; }
        .form-group { flex: 1; min-width: 200px; margin-bottom: 1rem; }
        label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; }
        input { width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; box-sizing: border-box; }
        button { background: var(--primary); color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; font-weight: 500; }
        button:hover { opacity: 0.9; }
        .alert { background: #dbeafe; color: #1e40af; padding: 1rem; border-radius: 4px; margin-bottom: 1.5rem; border: 1px solid #bfdbfe; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { text-align: left; padding: 0.75rem; border-bottom: 1px solid var(--border); }
    </style>
</head>
<body>

<div class="container">
    <h1 style="margin-bottom: 2rem;">Cryptographic Vault</h1>

    <?php if ($viewData['message']): ?>
        <div class="alert"><?= htmlspecialchars($viewData['message']) ?></div>
    <?php endif; ?>

    <?php if (!isset($_SESSION['user_id'])): ?>
        <div class="flex">
            <div class="card" style="flex: 1;">
                <h3>Login</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group"><label>Username</label><input type="text" name="username" required></div>
                    <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
                    <button type="submit">Access Vault</button>
                </form>
            </div>
            <div class="card" style="flex: 1;">
                <h3>Register</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="register">
                    <div class="form-group"><label>Username</label><input type="text" name="username" required></div>
                    <div class="form-group"><label>Master Password</label><input type="password" name="password" required></div>
                    <button type="submit">Create Identity</button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="card flex" style="justify-content: space-between; align-items: center;">
            <div>Logged in as: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></div>
            <a href="?logout=1" style="color: var(--primary); text-decoration: none;">End Session</a>
        </div>

        <div class="card">
            <h3>Password Generator (GUI Fields Requirement)</h3>
            <form method="POST" class="flex" style="align-items: flex-end;">
                <input type="hidden" name="action" value="generate">
                <div class="form-group"><label>Lowercase</label><input type="number" name="lower" value="3" min="0"></div>
                <div class="form-group"><label>Uppercase</label><input type="number" name="upper" value="3" min="0"></div>
                <div class="form-group"><label>Numbers</label><input type="number" name="numbers" value="2" min="0"></div>
                <div class="form-group"><label>Specials</label><input type="number" name="specials" value="2" min="0"></div>
                <div class="form-group"><button type="submit">Generate</button></div>
            </form>
            <?php if ($viewData['generated_password']): ?>
                <div style="margin-top: 1rem; font-family: monospace; font-size: 1.25rem; background: #f1f5f9; padding: 1rem; border-radius: 4px; text-align: center;">
                    <?= htmlspecialchars($viewData['generated_password']) ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="flex">
            <div class="card" style="flex: 1;">
                <h3>Save to Vault</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="save_password">
                    <div class="form-group"><label>Service Name (e.g., GitHub)</label><input type="text" name="service" required></div>
                    <div class="form-group">
                        <label>Password to Encrypt</label>
                        <input type="text" name="secret" value="<?= htmlspecialchars($viewData['generated_password']) ?>" required>
                    </div>
                    <button type="submit">Secure & Save</button>
                </form>
            </div>

            <div class="card" style="flex: 1;">
                <h3>Update Login Password</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group"><label>Current Password</label><input type="password" name="old_password" required></div>
                    <div class="form-group"><label>New Password</label><input type="password" name="new_password" required></div>
                    <button type="submit">Change & Recode Key</button>
                </form>
            </div>
        </div>

        <div class="card">
            <h3>Your Decrypted Vault</h3>
            <table>
                <tr>
                    <th>Service</th>
                    <th>Password</th>
                    <th>Date Added</th>
                </tr>
                <?php foreach ($viewData['entries'] as $entry): ?>
                    <tr>
                        <td><?= htmlspecialchars($entry['service_name']) ?></td>
                        <td><code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px;"><?= htmlspecialchars($entry['decrypted_secret']) ?></code></td>
                        <td><small><?= htmlspecialchars($entry['created_at']) ?></small></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($viewData['entries'])): ?>
                    <tr><td colspan="3" style="text-align: center; color: #64748b;">No passwords saved yet.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    <?php endif; ?>

</div>
</body>
</html>