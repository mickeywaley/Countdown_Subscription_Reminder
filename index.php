<?php
session_start();

// 配置信息
define('USERS_FILE', 'users.txt');
define('DATA_FILE', 'subscriptions.txt');

// 检查用户是否已登录
function isLoggedIn() {
    return isset($_SESSION['user']);
}

// 检查是否为管理员
function isAdmin() {
    return isLoggedIn() && $_SESSION['user']['role'] === 'admin';
}

// 加载所有用户
function getAllUsers() {
    if (!file_exists(USERS_FILE)) {
        // 如果用户文件不存在，创建默认管理员账户
        $admin = [
            'username' => 'admin',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'role' => 'admin'
        ];
        saveUser($admin);
        return [$admin];
    }
    
    $content = file_get_contents(USERS_FILE);
    $lines = explode(PHP_EOL, $content);
    $users = [];
    
    foreach ($lines as $line) {
        if (!empty($line)) {
            $user = json_decode($line, true);
            if ($user) {
                $users[] = $user;
            }
        }
    }
    
    return $users;
}

// 保存用户
function saveUser($user) {
    $data = json_encode($user) . PHP_EOL;
    file_put_contents(USERS_FILE, $data, FILE_APPEND);
}

// 更新用户
function updateUser($username, $newUser) {
    $users = getAllUsers();
    
    foreach ($users as $index => $user) {
        if ($user['username'] === $username) {
            $users[$index] = $newUser;
            break;
        }
    }
    
    // 重写文件
    file_put_contents(USERS_FILE, '');
    foreach ($users as $user) {
        saveUser($user);
    }
}

// 登录验证
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $users = getAllUsers();
    $loginError = '用户名或密码错误';
    
    foreach ($users as $user) {
        if ($user['username'] === $username && password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// 登出
if (isset($_GET['logout'])) {
    unset($_SESSION['user']);
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// 添加新用户
if (isset($_POST['add_user']) && isAdmin()) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = isset($_POST['is_admin']) ? 'admin' : 'user';
    
    if (!empty($username) && !empty($password)) {
        // 检查用户名是否已存在
        $users = getAllUsers();
        foreach ($users as $user) {
            if ($user['username'] === $username) {
                $userError = '用户名已存在';
                goto skip_add_user;
            }
        }
        
        $newUser = [
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role
        ];
        
        saveUser($newUser);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    skip_add_user:
}

// 添加新订阅
if (isset($_POST['add']) && isLoggedIn()) {
    $name = trim($_POST['name']);
    $expiry_date = trim($_POST['expiry_date']);
    $color = trim($_POST['color']) ?: '#FF0000'; // 默认红色
    $category = trim($_POST['category']) ?: '未分类'; // 新增分类字段
    
    if (!empty($name) && !empty($expiry_date)) {
        $subscription = [
            'id' => uniqid(), // 添加唯一ID
            'user' => $_SESSION['user']['username'], // 添加用户信息
            'name' => $name,
            'expiry_date' => $expiry_date,
            'color' => $color,
            'category' => $category // 新增分类字段
        ];
        
        saveSubscription($subscription);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// 修改订阅
if (isset($_POST['edit']) && isLoggedIn()) {
    $id = $_POST['edit_id'];
    $name = trim($_POST['name']);
    $expiry_date = trim($_POST['expiry_date']);
    $color = trim($_POST['color']) ?: '#FF0000';
    $category = trim($_POST['category']) ?: '未分类';
    
    // 检查用户是否有权限编辑此订阅
    $subscription = getSubscriptionById($id);
    if ($subscription && ($subscription['user'] === $_SESSION['user']['username'] || isAdmin())) {
        if (!empty($name) && !empty($expiry_date)) {
            $subscription = [
                'id' => $id, // 保持相同的ID
                'user' => $subscription['user'], // 保持相同的用户
                'name' => $name,
                'expiry_date' => $expiry_date,
                'color' => $color,
                'category' => $category
            ];
            
            updateSubscription($id, $subscription);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// 删除订阅
if (isset($_GET['delete']) && isLoggedIn()) {
    $id = $_GET['delete'];
    
    // 检查用户是否有权限删除此订阅
    $subscription = getSubscriptionById($id);
    if ($subscription && ($subscription['user'] === $_SESSION['user']['username'] || isAdmin())) {
        deleteSubscription($id);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// 获取订阅通过ID
function getSubscriptionById($id) {
    $subscriptions = getAllSubscriptions();
    
    foreach ($subscriptions as $subscription) {
        if ($subscription['id'] === $id) {
            return $subscription;
        }
    }
    
    return null;
}

// 保存订阅到文件
function saveSubscription($subscription) {
    $data = json_encode($subscription) . PHP_EOL;
    file_put_contents(DATA_FILE, $data, FILE_APPEND);
}

// 更新订阅
function updateSubscription($id, $subscription) {
    $subscriptions = getAllSubscriptions();
    
    // 查找具有匹配ID的订阅
    foreach ($subscriptions as $index => $item) {
        if ($item['id'] === $id) {
            $subscriptions[$index] = $subscription;
            break;
        }
    }
    
    // 重写文件
    file_put_contents(DATA_FILE, '');
    foreach ($subscriptions as $item) {
        saveSubscription($item);
    }
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
                // 确保旧数据也有ID、用户和分类字段
                if (!isset($subscription['id'])) {
                    $subscription['id'] = uniqid();
                }
                if (!isset($subscription['user'])) {
                    $subscription['user'] = 'admin';
                }
                if (!isset($subscription['category'])) {
                    $subscription['category'] = '未分类';
                }
                $subscriptions[] = $subscription;
            }
        }
    }
    
    return $subscriptions;
}

// 删除订阅
function deleteSubscription($id) {
    $subscriptions = getAllSubscriptions();
    
    // 查找具有匹配ID的订阅并删除
    foreach ($subscriptions as $index => $item) {
        if ($item['id'] === $id) {
            unset($subscriptions[$index]);
            break;
        }
    }
    
    $subscriptions = array_values($subscriptions); // 重新索引数组
    
    // 重写文件
    file_put_contents(DATA_FILE, '');
    foreach ($subscriptions as $subscription) {
        saveSubscription($subscription);
    }
}

// 计算剩余天数
function calculateDaysLeft($expiry_date) {
    $today = new DateTime();
    $expiry = new DateTime($expiry_date);
    $interval = $today->diff($expiry);
    return $interval->invert ? -$interval->days : $interval->days;
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

// 获取所有分类
function getAllCategories($subscriptions) {
    $categories = ['全部'];
    foreach ($subscriptions as $subscription) {
        if (!in_array($subscription['category'], $categories)) {
            $categories[] = $subscription['category'];
        }
    }
    return $categories;
}

// 获取所有用户
function getAllUserNames($subscriptions) {
    $users = ['全部'];
    foreach ($subscriptions as $subscription) {
        if (!in_array($subscription['user'], $users)) {
            $users[] = $subscription['user'];
        }
    }
    return $users;
}

// 获取所有订阅
$all_subscriptions = getAllSubscriptions(); // 获取所有订阅，不应用过滤
$subscriptions = $all_subscriptions; // 默认使用所有订阅

// 处理排序和过滤
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'asc';
$category = isset($_GET['category']) ? $_GET['category'] : '全部';
$user_filter = isset($_GET['user']) ? $_GET['user'] : '全部';

// 过滤用户（仅管理员可见）
if (isAdmin() && $user_filter !== '全部') {
    $subscriptions = array_filter($subscriptions, function($subscription) use ($user_filter) {
        return $subscription['user'] === $user_filter;
    });
    $subscriptions = array_values($subscriptions); // 重新索引数组
} elseif (!isAdmin()) {
    // 普通用户只能看到自己的订阅
    $subscriptions = array_filter($subscriptions, function($subscription) {
        return $subscription['user'] === $_SESSION['user']['username'];
    });
    $subscriptions = array_values($subscriptions); // 重新索引数组
}

// 排序
usort($subscriptions, function($a, $b) use ($sort) {
    $daysA = calculateDaysLeft($a['expiry_date']);
    $daysB = calculateDaysLeft($b['expiry_date']);
    
    if ($sort === 'asc') {
        return $daysA - $daysB;
    } else {
        return $daysB - $daysA;
    }
});

// 过滤分类
if ($category !== '全部') {
    $subscriptions = array_filter($subscriptions, function($subscription) use ($category) {
        return $subscription['category'] === $category;
    });
    $subscriptions = array_values($subscriptions); // 重新索引数组
}

// 获取当前编辑的订阅
$edit_id = isset($_GET['edit']) ? $_GET['edit'] : null;
$edit_subscription = null;
if ($edit_id !== null) {
    // 从所有订阅中查找，而不仅是过滤后的订阅
    foreach ($all_subscriptions as $subscription) {
        if ($subscription['id'] === $edit_id) {
            // 检查用户是否有权限编辑此订阅
            if ($subscription['user'] === $_SESSION['user']['username'] || isAdmin()) {
                $edit_subscription = $subscription;
            }
            break;
        }
    }
}

// 统计信息
$stats = [
    'total' => count($subscriptions),
    'expired' => count(array_filter($subscriptions, function($s) { return calculateDaysLeft($s['expiry_date']) < 0; })),
    'critical' => count(array_filter($subscriptions, function($s) { 
        $days = calculateDaysLeft($s['expiry_date']);
        return $days >= 0 && $days <= 7; 
    })),
    'warning' => count(array_filter($subscriptions, function($s) { 
        $days = calculateDaysLeft($s['expiry_date']);
        return $days > 7 && $days <= 30; 
    })),
    'normal' => count(array_filter($subscriptions, function($s) { 
        return calculateDaysLeft($s['expiry_date']) > 30; 
    }))
];

// 按用户分组订阅（仅管理员可见）
$subscriptionsByUser = [];
if (isAdmin()) {
    foreach ($subscriptions as $subscription) {
        if (!isset($subscriptionsByUser[$subscription['user']])) {
            $subscriptionsByUser[$subscription['user']] = [];
        }
        $subscriptionsByUser[$subscription['user']][] = $subscription;
    }
}
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            text-align: center;
            margin: 0 0 20px;
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
        .add-form input, .add-form button, .add-form select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .add-form input[type="text"], .add-form select {
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
            margin: 0;
        }
        .subscription-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }
        .subscription-item:hover {
            background-color: #f9f9f9;
        }
        .subscription-name {
            font-size: 18px;
            margin-right: 10px;
        }
        .days-left {
            font-weight: bold;
            margin-left: 10px;
        }
        .delete-btn {
            background-color: #f44336;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 3px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .delete-btn:hover {
            background-color: #d32f2f;
        }
        .edit-btn {
            background-color: #FF9800;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 3px;
            cursor: pointer;
            margin-right: 10px;
            transition: background-color 0.2s;
        }
        .edit-btn:hover {
            background-color: #F57C00;
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
            transition: background-color 0.2s;
        }
        .logout-btn:hover {
            background-color: #d32f2f;
        }
        .category-tag {
            display: inline-block;
            padding: 4px 8px;
            margin-left: 10px;
            border-radius: 3px;
            background-color: #f0f0f0;
            font-size: 12px;
            color: #666;
        }
        .user-tag {
            display: inline-block;
            padding: 4px 8px;
            margin-left: 10px;
            border-radius: 3px;
            background-color: #e0e0e0;
            font-size: 12px;
            color: #333;
        }
        .filter-sort {
            margin: 15px 0;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 3px;
        }
        .filter-sort select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 5px;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        .user-management {
            margin: 20px 0;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        .user-list {
            list-style-type: none;
            padding: 0;
            margin: 10px 0 0;
        }
        .user-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .user-role {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
        }
        .admin-role {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        .user-role {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        .stats-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            flex: 1;
            min-width: 120px;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            color: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            margin: 0 0 5px;
            font-size: 16px;
        }
        .stat-card p {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
        }
        .total-card {
            background-color: #2196F3;
        }
        .expired-card {
            background-color: #F44336;
        }
        .critical-card {
            background-color: #FF9800;
        }
        .warning-card {
            background-color: #FFEB3B;
            color: #333;
        }
        .normal-card {
            background-color: #4CAF50;
        }
        .user-section {
            margin-bottom: 30px;
            border: 1px solid #eee;
            border-radius: 5px;
            overflow: hidden;
        }
        .user-header {
            padding: 15px;
            background-color: #f5f5f5;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .user-subscriptions {
            padding: 0;
        }
        .add-user-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 10px;
        }
        .add-user-form input, .add-user-form button {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .add-user-form input[type="text"], .add-user-form input[type="password"] {
            flex: 1;
        }
        .add-user-form button {
            background-color: #4CAF50;
            color: white;
            cursor: pointer;
        }
        .btn-primary {
            background-color: #2196F3;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 3px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-primary:hover {
            background-color: #1976D2;
        }
        .btn-secondary {
            background-color: #607D8B;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 3px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-secondary:hover {
            background-color: #455A64;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .welcome-message {
            font-size: 18px;
            color: #666;
        }
        @media (max-width: 600px) {
            .add-form, .add-user-form, .filter-sort {
                flex-direction: column;
            }
            .subscription-item {
                flex-direction: column;
                align-items: flex-start;
                padding: 10px;
            }
            .subscription-name {
                margin-bottom: 5px;
            }
            .filter-sort {
                align-items: flex-start;
            }
            .user-item {
                flex-direction: column;
                align-items: flex-start;
            }
            .user-role {
                margin-top: 5px;
            }
            .stat-card {
                min-width: 100%;
            }
            .user-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .user-actions {
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1>会员到期提醒系统</h1>
            <?php if (isLoggedIn()): ?>
            <div class="welcome-message">
                欢迎, <?php echo htmlspecialchars($_SESSION['user']['username']); ?> 
                <?php if (isAdmin()): ?>
                <span class="user-role admin-role">管理员</span>
                <?php else: ?>
                <span class="user-role user-role">普通用户</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (!isLoggedIn()): ?>
            <div class="login-form">
                <h2>用户登录</h2>
                <?php if (isset($loginError)): ?>
                    <div class="error"><?php echo $loginError; ?></div>
                <?php endif; ?>
                <form method="post">
                    <input type="text" name="username" placeholder="用户名" required>
                    <input type="password" name="password" placeholder="密码" required>
                    <button type="submit" name="login" class="btn-primary">登录</button>
                </form>
            </div>
        <?php else: ?>
            <div class="stats-container">
                <div class="stat-card total-card">
                    <h3>总计</h3>
                    <p><?php echo $stats['total']; ?></p>
                </div>
                <div class="stat-card expired-card">
                    <h3>已过期</h3>
                    <p><?php echo $stats['expired']; ?></p>
                </div>
                <div class="stat-card critical-card">
                    <h3>紧急</h3>
                    <p><?php echo $stats['critical']; ?></p>
                </div>
                <div class="stat-card warning-card">
                    <h3>警告</h3>
                    <p><?php echo $stats['warning']; ?></p>
                </div>
                <div class="stat-card normal-card">
                    <h3>正常</h3>
                    <p><?php echo $stats['normal']; ?></p>
                </div>
            </div>
            
            <?php if (isAdmin()): ?>
            <div class="user-management">
                <h2>用户管理</h2>
                <div class="add-user-form">
                    <input type="text" name="username" placeholder="用户名" required>
                    <input type="password" name="password" placeholder="密码" required>
                    <label>
                        <input type="checkbox" name="is_admin"> 管理员
                    </label>
                    <button type="submit" name="add_user" class="btn-primary">添加用户</button>
                </div>
                <?php if (isset($userError)): ?>
                    <div class="error"><?php echo $userError; ?></div>
                <?php endif; ?>
                <h3>用户列表</h3>
                <ul class="user-list">
                    <?php foreach (getAllUsers() as $user): ?>
                    <li class="user-item">
                        <span><?php echo htmlspecialchars($user['username']); ?></span>
                        <span class="<?php echo $user['role'] === 'admin' ? 'admin-role' : 'user-role'; ?>">
                            <?php echo $user['role'] === 'admin' ? '管理员' : '普通用户'; ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="admin-controls">
                <h2>添加新订阅</h2>
                <form class="add-form" method="post">
                    <input type="text" name="name" placeholder="订阅名称" required>
                    <input type="date" name="expiry_date" required>
                    <input type="color" name="color" value="#FF0000">
                    <input type="text" name="category" placeholder="分类" value="未分类">
                    <button type="submit" name="add" class="btn-primary">添加</button>
                </form>
                <button class="logout-btn" onclick="window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>?logout=1'">退出登录</button>
            </div>
            
            <!-- 排序和过滤选项 -->
            <div class="filter-sort">
                <span>排序:</span>
                <select id="sort-select" onchange="window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>?sort=' + this.value + '&category=<?php echo $category; ?><?php echo isAdmin() ? '&user=' . urlencode($user_filter) : ''; ?>'">
                    <option value="asc" <?php echo ($sort === 'asc') ? 'selected' : ''; ?>>按剩余时间升序</option>
                    <option value="desc" <?php echo ($sort === 'desc') ? 'selected' : ''; ?>>按剩余时间降序</option>
                </select>
                
                <span>分类:</span>
                <select id="category-select" onchange="window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>?sort=<?php echo $sort; ?>&category=' + encodeURIComponent(this.value) + '<?php echo isAdmin() ? '&user=' . urlencode($user_filter) : ''; ?>'">
                    <?php foreach (getAllCategories($subscriptions) as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($category === $cat) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <?php if (isAdmin()): ?>
                <span>用户:</span>
                <select id="user-select" onchange="window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>?sort=<?php echo $sort; ?>&category=<?php echo urlencode($category); ?>&user=' + encodeURIComponent(this.value)">
                    <?php foreach (getAllUserNames($subscriptions) as $user): ?>
                        <option value="<?php echo htmlspecialchars($user); ?>" <?php echo ($user_filter === $user) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>
            
            <h2>订阅列表</h2>
            
            <?php if (empty($subscriptions)): ?>
                <div class="container">
                    <p style="text-align: center; color: #666; padding: 30px;">暂无订阅记录</p>
                </div>
            <?php else: ?>
                <?php if (isAdmin()): ?>
                    <!-- 管理员视图：按用户分组 -->
                    <?php foreach ($subscriptionsByUser as $username => $userSubscriptions): ?>
                    <div class="user-section">
                        <div class="user-header">
                            <span><?php echo htmlspecialchars($username); ?> 的订阅</span>
                            <div class="user-actions">
                                <span class="user-role <?php echo $username === 'admin' ? 'admin-role' : 'user-role'; ?>">
                                    <?php echo $username === 'admin' ? '管理员' : '普通用户'; ?>
                                </span>
                            </div>
                        </div>
                        <ul class="subscription-list user-subscriptions">
                            <?php foreach ($userSubscriptions as $subscription): ?>
                                <li class="subscription-item">
                                    <div>
                                        <span class="subscription-name" style="<?php echo getStatusStyle(calculateDaysLeft($subscription['expiry_date']), $subscription['color']); ?>">
                                            <?php echo htmlspecialchars($subscription['name']); ?>
                                        </span>
                                        <span class="category-tag"><?php echo htmlspecialchars($subscription['category']); ?></span>
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
                                    <div>
                                        <button class="edit-btn" onclick="window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>?edit=<?php echo $subscription['id']; ?>&sort=<?php echo $sort; ?>&category=<?php echo urlencode($category); ?>&user=<?php echo urlencode($user_filter); ?>'">编辑</button>
                                        <button class="delete-btn" onclick="if(confirm('确定要删除此订阅吗？')) window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>?delete=<?php echo $subscription['id']; ?>&sort=<?php echo $sort; ?>&category=<?php echo urlencode($category); ?>&user=<?php echo urlencode($user_filter); ?>'">删除</button>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- 普通用户视图 -->
                    <ul class="subscription-list">
                        <?php foreach ($subscriptions as $subscription): ?>
                            <li class="subscription-item">
                                <div>
                                    <span class="subscription-name" style="<?php echo getStatusStyle(calculateDaysLeft($subscription['expiry_date']), $subscription['color']); ?>">
                                        <?php echo htmlspecialchars($subscription['name']); ?>
                                    </span>
                                    <span class="category-tag"><?php echo htmlspecialchars($subscription['category']); ?></span>
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
                                <div>
                                    <button class="edit-btn" onclick="window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>?edit=<?php echo $subscription['id']; ?>&sort=<?php echo $sort; ?>&category=<?php echo urlencode($category); ?>'">编辑</button>
                                    <button class="delete-btn" onclick="if(confirm('确定要删除此订阅吗？')) window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>?delete=<?php echo $subscription['id']; ?>&sort=<?php echo $sort; ?>&category=<?php echo urlencode($category); ?>'">删除</button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- 编辑模态框 -->
            <?php if ($edit_subscription): ?>
            <div id="edit-modal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="document.getElementById('edit-modal').style.display='none'">&times;</span>
                    <h2>编辑订阅</h2>
                    <form method="post">
                        <input type="hidden" name="edit_id" value="<?php echo $edit_subscription['id']; ?>">
                        <div style="margin: 10px 0;">
                            <label>名称:</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($edit_subscription['name']); ?>" required>
                        </div>
                        <div style="margin: 10px 0;">
                            <label>到期日:</label>
                            <input type="date" name="expiry_date" value="<?php echo $edit_subscription['expiry_date']; ?>" required>
                        </div>
                        <div style="margin: 10px 0;">
                            <label>颜色:</label>
                            <input type="color" name="color" value="<?php echo $edit_subscription['color']; ?>">
                        </div>
                        <div style="margin: 10px 0;">
                            <label>分类:</label>
                            <input type="text" name="category" value="<?php echo htmlspecialchars($edit_subscription['category']); ?>">
                        </div>
                        <div style="margin-top: 20px;">
                            <button type="submit" name="edit" class="btn-primary">保存</button>
                            <button type="button" onclick="document.getElementById('edit-modal').style.display='none'" class="btn-secondary">取消</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <script>
                // 显示编辑模态框
                document.getElementById('edit-modal').style.display = 'block';
                
                // 点击关闭按钮时隐藏模态框
                document.querySelector('.close').onclick = function() {
                    document.getElementById('edit-modal').style.display = 'none';
                    window.history.replaceState({}, document.title, '<?php echo $_SERVER['PHP_SELF']; ?>?sort=<?php echo $sort; ?>&category=<?php echo urlencode($category); ?><?php echo isAdmin() ? '&user=' . urlencode($user_filter) : ''; ?>');
                }
                
                // 点击模态框外部时隐藏模态框
                window.onclick = function(event) {
                    var modal = document.getElementById('edit-modal');
                    if (event.target == modal) {
                        modal.style.display = 'none';
                        window.history.replaceState({}, document.title, '<?php echo $_SERVER['PHP_SELF']; ?>?sort=<?php echo $sort; ?>&category=<?php echo urlencode($category); ?><?php echo isAdmin() ? '&user=' . urlencode($user_filter) : ''; ?>');
                    }
                }
            </script>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
