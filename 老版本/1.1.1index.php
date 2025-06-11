<?php
session_start();

// 配置信息
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123'); // 请修改为安全的密码
define('DATA_FILE', 'subscriptions.txt');

// 检查用户是否已登录
function isLoggedIn() {
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

// 登录验证
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $_SESSION['admin'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $loginError = '用户名或密码错误';
    }
}

// 登出
if (isset($_GET['logout'])) {
    unset($_SESSION['admin']);
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// 添加新订阅
if (isset($_POST['add']) && isLoggedIn()) {
    $name = trim($_POST['name']);
    $expiry_date = trim($_POST['expiry_date']);
    $color = trim($_POST['color']) ?: '#FF0000'; // 默认红色
    
    if (!empty($name) && !empty($expiry_date)) {
        $subscription = [
            'name' => $name,
            'expiry_date' => $expiry_date,
            'color' => $color
        ];
        
        saveSubscription($subscription);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// 删除订阅
if (isset($_GET['delete']) && isLoggedIn()) {
    $id = $_GET['delete'];
    deleteSubscription($id);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// 保存订阅到文件
function saveSubscription($subscription) {
    $data = json_encode($subscription) . PHP_EOL;
    file_put_contents(DATA_FILE, $data, FILE_APPEND);
}

// 读取所有订阅
function getAllSubscriptions() {
    if (!file_exists(DATA_FILE)) {
        return [];
    }
    
    $content = file_get_contents(DATA_FILE);
    $lines = explode(PHP_EOL, $content);
    $subscriptions = [];
    
    foreach ($lines as $line) {
        if (!empty($line)) {
            $subscription = json_decode($line, true);
            if ($subscription) {
                $subscriptions[] = $subscription;
            }
        }
    }
    
    return $subscriptions;
}

// 删除订阅
function deleteSubscription($id) {
    $subscriptions = getAllSubscriptions();
    
    if (isset($subscriptions[$id])) {
        unset($subscriptions[$id]);
        $subscriptions = array_values($subscriptions); // 重新索引数组
        
        // 重写文件
        file_put_contents(DATA_FILE, '');
        foreach ($subscriptions as $subscription) {
            saveSubscription($subscription);
        }
    }
}

// 计算剩余天数
function calculateDaysLeft($expiry_date) {
    $today = new DateTime();
    $expiry = new DateTime($expiry_date);
    $interval = $today->diff($expiry);
    return $interval->days;
}

// 获取到期状态样式
function getStatusStyle($days_left, $custom_color = '#FF0000') {
    if ($days_left < 0) {
        return 'color: #888; text-decoration: line-through;';
    } elseif ($days_left <= 7) {
        return 'color: ' . $custom_color . '; font-weight: bold;';
    } elseif ($days_left <= 30) {
        return 'color: #FFA500;';
    } else {
        return 'color: #008000;';
    }
}

// 获取所有订阅
$subscriptions = getAllSubscriptions();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>会员到期提醒系统</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
        }
        .login-form {
            max-width: 300px;
            margin: 0 auto;
            text-align: center;
        }
        .login-form input {
            width: 100%;
            padding: 10px;
            margin: 5px 0;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .login-form button {
            width: 100%;
            padding: 10px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        .error {
            color: red;
            margin: 10px 0;
        }
        .admin-controls {
            margin: 20px 0;
        }
        .add-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        .add-form input, .add-form button {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .add-form input[type="text"] {
            flex: 1;
        }
        .add-form button {
            background-color: #2196F3;
            color: white;
            cursor: pointer;
        }
        .subscription-list {
            list-style-type: none;
            padding: 0;
        }
        .subscription-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .subscription-name {
            font-size: 18px;
        }
        .days-left {
            font-weight: bold;
        }
        .delete-btn {
            background-color: #f44336;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
        }
        .logout-btn {
            display: block;
            margin: 20px auto;
            padding: 10px 20px;
            background-color: #f44336;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        @media (max-width: 600px) {
            .add-form {
                flex-direction: column;
            }
            .subscription-item {
                flex-direction: column;
                align-items: flex-start;
            }
            .subscription-name {
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>会员到期提醒系统</h1>
        
        <?php if (!isLoggedIn()): ?>
            <div class="login-form">
                <h2>管理员登录</h2>
                <?php if (isset($loginError)): ?>
                    <div class="error"><?php echo $loginError; ?></div>
                <?php endif; ?>
                <form method="post">
                    <input type="text" name="username" placeholder="用户名" required>
                    <input type="password" name="password" placeholder="密码" required>
                    <button type="submit" name="login">登录</button>
                </form>
            </div>
        <?php else: ?>
            <div class="admin-controls">
                <h2>添加新订阅</h2>
                <form class="add-form" method="post">
                    <input type="text" name="name" placeholder="订阅名称" required>
                    <input type="date" name="expiry_date" required>
                    <input type="color" name="color" value="#FF0000">
                    <button type="submit" name="add">添加</button>
                </form>
                <button class="logout-btn" onclick="window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>?logout=1'">退出登录</button>
            </div>
        <?php endif; ?>
        
        <h2>订阅列表</h2>
        <ul class="subscription-list">
            <?php if (empty($subscriptions)): ?>
                <li class="subscription-item">
                    <span>暂无订阅记录</span>
                </li>
            <?php else: ?>
                <?php foreach ($subscriptions as $index => $subscription): ?>
                    <li class="subscription-item">
                        <div>
                            <span class="subscription-name" style="<?php echo getStatusStyle(calculateDaysLeft($subscription['expiry_date']), $subscription['color']); ?>">
                                <?php echo htmlspecialchars($subscription['name']); ?>
                            </span>
                            <span>到期日: <?php echo $subscription['expiry_date']; ?></span>
                            <span class="days-left">
                                <?php 
                                $days_left = calculateDaysLeft($subscription['expiry_date']);
                                if ($days_left < 0) {
                                    echo '已过期 ' . abs($days_left) . ' 天';
                                } else {
                                    echo '剩余 ' . $days_left . ' 天';
                                }
                                ?>
                            </span>
                        </div>
                        <?php if (isLoggedIn()): ?>
                            <button class="delete-btn" onclick="if(confirm('确定要删除此订阅吗？')) window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>?delete=<?php echo $index; ?>'">删除</button>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
</body>
</html>
