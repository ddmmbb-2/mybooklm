<?php
session_start();
require_once __DIR__ . '/lib/Auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Auth::login($_POST['username'] ?? '', $_POST['password'] ?? '')) {
        header('Location: index.php');
        exit;
    }
    $error = '帳號或密碼錯誤';
}

if (Auth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>知識庫登入</title>
    <style>
        /* 全域重置 */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: #f3f4f6;
            padding: 1rem;
        }

        .login-card {
            background: white;
            padding: 2rem 1.5rem;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 400px;
            transition: all 0.2s ease;
        }

        .login-card h2 {
            text-align: center;
            margin-bottom: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            font-size: 1.5rem;
        }

        .login-card .error {
            color: #dc2626;
            background: #fee2e2;
            padding: 0.6rem 0.8rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            text-align: center;
        }

        .login-card label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.3rem;
            color: #374151;
            font-size: 0.9rem;
        }

        .login-card input {
            width: 100%;
            padding: 0.8rem 0.9rem;
            margin-bottom: 1.2rem;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
            background: #fafbfc;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
            -webkit-appearance: none;
        }

        .login-card input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
            background: white;
        }

        .login-card button {
            width: 100%;
            padding: 0.85rem;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            margin-top: 0.3rem;
        }

        .login-card button:hover {
            background: #2563eb;
        }

        .login-card button:active {
            transform: scale(0.98);
        }

        /* 手機專用調整 */
        @media (max-width: 480px) {
            body {
                padding: 0.5rem;
                align-items: flex-start;
                padding-top: 10vh;
            }

            .login-card {
                padding: 1.8rem 1.2rem;
                border-radius: 14px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            }

            .login-card h2 {
                font-size: 1.3rem;
                margin-bottom: 1.2rem;
            }

            .login-card input {
                padding: 0.9rem 0.9rem;
                font-size: 1rem;
                margin-bottom: 1rem;
                border-radius: 10px;
            }

            .login-card button {
                padding: 0.9rem;
                font-size: 1rem;
                border-radius: 10px;
            }
        }

        /* 針對極小螢幕（< 360px）再縮減間距 */
        @media (max-width: 360px) {
            .login-card {
                padding: 1.5rem 1rem;
            }
            .login-card input {
                padding: 0.7rem 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>🔐 知識庫登入</h2>
        <?php if ($error) echo "<p class='error'>$error</p>"; ?>
        <form method="post">
            <label for="username">帳號</label>
            <input type="text" name="username" id="username" placeholder="請輸入帳號" required autofocus>

            <label for="password">密碼</label>
            <input type="password" name="password" id="password" placeholder="請輸入密碼" required>

            <button type="submit">登入</button>
        </form>
    </div>
</body>
</html>