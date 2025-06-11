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
            'role' => 'admin',
            'email' => 'admin@example.com'
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
                // 确保旧用户数据有email字段
                if (!isset($user['email'])) {
                    $user['email'] = '';
                }
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
    $email = trim($_POST['email']);
    
    if (!empty($username) && !empty($password) && !empty($email)) {
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
            'role' => $role,
            'email' => $email
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
            'category' => $category
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
                // 确保旧数据也有所有字段
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
if ($edit_id !== null && isLoggedIn()) {
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
        .success {
            color: green;
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
        .add-form input, .add-form button, .add-form select, .add-form label {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .add-form input[type="text"], .add-form select, .add-form input[type="date"], .add-form input[type="color"] {
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
        .login-link {
            text-align: center;
            margin: 20px 0;
        }
        .login-link a {
            color: #2196F3;
            text-decoration: none;
        }
        .login-link a:hover {
            text-decoration: underline;
        }
        .stats-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        .stat-card {
            flex: 1;
            min-width: 150px;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            color: white;
        }
        .stat-total { background-color: #2196F3; }
        .stat-expired { background-color: #f44336; }
        .stat-critical { background-color: #FF9800; }
        .stat-warning { background-color: #FFEB3B; color: #333; }
        .stat-normal { background-color: #4CAF50; }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
        }
        .stat-label {
            font-size: 14px;
        }
        .user-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        @media (max-width: 768px) {
            .add-form input[type="text"], .add-form select, .add-form input[type="date"], .add-form input[type="color"] {
                flex-basis: 100%;
            }
            .subscription-item {
                flex-direction: column;
                align-items: flex-start;
            }
            .subscription-actions {
                margin-top: 10px;
            }
            .stats-container {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>会员到期提醒系统</h1>
        
        <?php if (!isLoggedIn()): ?>
            <div class="login-link">
                <a href="#login-modal" onclick="document.getElementById('login-modal').style.display='block'">登录</a> 以管理订阅
            </div>
        <?php else: ?>
            <div class="user-controls">
                <div>
                    <?php if (isAdmin()): ?>
                        <a href="#add-user-modal" onclick="document.getElementById('add-user-modal').style.display='block'">添加用户</a>
                    <?php endif; ?>
                    <?php if (isAdmin()): ?>
                        <a href="#admin-panel" onclick="document.getElementById('admin-panel').style.display='block'">管理面板</a>
                    <?php endif; ?>
                </div>
                <a href="?logout=1" class="logout-btn">退出登录</a>
            </div>
        <?php endif; ?>
        
        <!-- 统计卡片 -->
        <div class="stats-container">
            <div class="stat-card stat-total">
                <div class="stat-value"><?php echo $stats['total']; ?></div>
                <div class="stat-label">总订阅数</div>
            </div>
            <div class="stat-card stat-expired">
                <div class="stat-value"><?php echo $stats['expired']; ?></div>
                <div class="stat-label">已过期</div>
            </div>
            <div class="stat-card stat-critical">
                <div class="stat-value"><?php echo $stats['critical']; ?></div>
                <div class="stat-label">即将到期</div>
            </div>
            <div class="stat-card stat-warning">
                <div class="stat-value"><?php echo $stats['warning']; ?></div>
                <div class="stat-label">近期到期</div>
            </div>
            <div class="stat-card stat-normal">
                <div class="stat-value"><?php echo $stats['normal']; ?></div>
                <div class="stat-label">正常</div>
            </div>
        </div>
        
        <!-- 过滤和排序 -->
        <div class="filter-sort">
            <label>分类:</label>
            <select id="category-filter" onchange="filterSubscriptions()">
                <?php foreach (getAllCategories($all_subscriptions) as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($category === $cat) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <?php if (isAdmin()): ?>
                <label>用户:</label>
                <select id="user-filter" onchange="filterSubscriptions()">
                    <?php foreach (getAllUserNames($all_subscriptions) as $user): ?>
                        <option value="<?php echo htmlspecialchars($user); ?>" <?php echo ($user_filter === $user) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            
            <label>排序:</label>
            <select id="sort-order" onchange="filterSubscriptions()">
                <option value="asc" <?php echo ($sort === 'asc') ? 'selected' : ''; ?>>到期日（早到晚）</option>
                <option value="desc" <?php echo ($sort === 'desc') ? 'selected' : ''; ?>>到期日（晚到早）</option>
            </select>
        </div>
        
        <!-- 订阅列表 -->
        <ul class="subscription-list">
            <?php if (empty($subscriptions)): ?>
                <li class="subscription-item">
                    <div class="subscription-name">暂无订阅记录</div>
                </li>
            <?php else: ?>
                <?php foreach ($subscriptions as $subscription): ?>
                    <li class="subscription-item">
                        <div>
                            <span class="subscription-name" style="<?php echo getStatusStyle(calculateDaysLeft($subscription['expiry_date']), $subscription['color']); ?>">
                                <?php echo htmlspecialchars($subscription['name']); ?>
                            </span>
                            <span class="category-tag"><?php echo htmlspecialchars($subscription['category']); ?></span>
                            <?php if (isAdmin()): ?>
                                <span class="user-tag"><?php echo htmlspecialchars($subscription['user']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="days-left" style="<?php echo getStatusStyle(calculateDaysLeft($subscription['expiry_date']), $subscription['color']); ?>">
                                <?php 
                                $days = calculateDaysLeft($subscription['expiry_date']);
                                if ($days < 0) {
                                    echo '已过期 ' . abs($days) . ' 天';
                                } else {
                                    echo '剩余 ' . $days . ' 天';
                                }
                                ?>
                            </span>
                            <span>（<?php echo htmlspecialchars($subscription['expiry_date']); ?>）</span>
                            
                            <?php if (isLoggedIn() && ($subscription['user'] === $_SESSION['user']['username'] || isAdmin())): ?>
                                <div class="subscription-actions">
                                    <button class="edit-btn" onclick="editSubscription('<?php echo $subscription['id']; ?>')">编辑</button>
                                    <button class="delete-btn" onclick="if(confirm('确定要删除此订阅吗？')) window.location.href='?delete=<?php echo $subscription['id']; ?>'">删除</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
        
        <?php if (isLoggedIn()): ?>
            <!-- 添加订阅表单 -->
            <div class="add-form">
                <input type="text" name="name" placeholder="订阅名称" required>
                <input type="date" name="expiry_date" required>
                <input type="text" name="category" placeholder="分类（可选）">
                <input type="color" name="color" value="#FF0000">
                <button type="button" onclick="addSubscription()">添加订阅</button>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- 登录模态框 -->
    <div id="login-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('login-modal').style.display='none'">&times;</span>
            <h2>登录</h2>
            <?php if (isset($loginError)): ?>
                <div class="error"><?php echo $loginError; ?></div>
            <?php endif; ?>
            <form method="post" class="login-form">
                <input type="text" name="username" placeholder="用户名" required>
                <input type="password" name="password" placeholder="密码" required>
                <button type="submit" name="login">登录</button>
            </form>
        </div>
    </div>
    
    <!-- 添加用户模态框 -->
    <div id="add-user-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('add-user-modal').style.display='none'">&times;</span>
            <h2>添加用户</h2>
            <?php if (isset($userError)): ?>
                <div class="error"><?php echo $userError; ?></div>
            <?php endif; ?>
            <form method="post">
                <input type="text" name="username" placeholder="用户名" required>
                <input type="password" name="password" placeholder="密码" required>
                <input type="email" name="email" placeholder="邮箱" required>
                <label>
                    <input type="checkbox" name="is_admin"> 管理员
                </label>
                <button type="submit" name="add_user">添加</button>
            </form>
        </div>
    </div>
    
    <!-- 编辑订阅模态框 -->
    <div id="edit-modal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('edit-modal').style.display='none'">&times;</span>
            <h2>编辑订阅</h2>
            <form method="post">
                <input type="hidden" name="edit_id" id="edit-id">
                <input type="text" name="name" id="edit-name" placeholder="订阅名称" required>
                <input type="date" name="expiry_date" id="edit-expiry-date" required>
                <input type="text" name="category" id="edit-category" placeholder="分类（可选）">
                <input type="color" name="color" id="edit-color">
                <button type="submit" name="edit">保存</button>
            </form>
        </div>
    </div>
    
    <!-- 管理员面板模态框 -->
    <div id="admin-panel" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('admin-panel').style.display='none'">&times;</span>
            <h2>管理员面板</h2>
            
            <?php if (isAdmin()): ?>
                <h3>用户列表</h3>
                <ul>
                    <?php foreach (getAllUsers() as $user): ?>
                        <li>
                            <?php echo htmlspecialchars($user['username']); ?> 
                            (<?php echo htmlspecialchars($user['email']); ?>)
                            <?php echo ($user['role'] === 'admin') ? ' [管理员]' : ''; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <h3>按用户分组的订阅</h3>
                <?php if (!empty($subscriptionsByUser)): ?>
                    <?php foreach ($subscriptionsByUser as $user => $userSubscriptions): ?>
                        <h4><?php echo htmlspecialchars($user); ?> (<?php echo count($userSubscriptions); ?>)</h4>
                        <ul>
                            <?php foreach ($userSubscriptions as $subscription): ?>
                                <li>
                                    <span style="<?php echo getStatusStyle(calculateDaysLeft($subscription['expiry_date']), $subscription['color']); ?>">
                                        <?php echo htmlspecialchars($subscription['name']); ?>
                                    </span>
                                    (<?php echo htmlspecialchars($subscription['expiry_date']); ?>)
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>暂无订阅记录</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // 过滤和排序订阅
        function filterSubscriptions() {
            const category = document.getElementById('category-filter').value;
            const user = document.getElementById('user-filter') ? document.getElementById('user-filter').value : '全部';
            const sort = document.getElementById('sort-order').value;
            
            let url = '?category=' + encodeURIComponent(category) + '&sort=' + encodeURIComponent(sort);
            
            if (document.getElementById('user-filter')) {
                url += '&user=' + encodeURIComponent(user);
            }
            
            window.location.href = url;
        }
        
        // 添加订阅
        function addSubscription() {
            const name = document.querySelector('.add-form input[name="name"]').value;
            const expiryDate = document.querySelector('.add-form input[name="expiry_date"]').value;
            const category = document.querySelector('.add-form input[name="category"]').value;
            const color = document.querySelector('.add-form input[name="color"]').value;
            
            // 验证
            if (!name || !expiryDate) {
                alert('请填写订阅名称和到期日期');
                return;
            }
            
            // 发送请求
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo $_SERVER['PHP_SELF']; ?>', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        // 刷新页面
                        location.reload();
                    } else {
                        alert('添加订阅失败');
                    }
                }
            };
            xhr.send('add=1&name=' + encodeURIComponent(name) + 
                    '&expiry_date=' + encodeURIComponent(expiryDate) + 
                    '&category=' + encodeURIComponent(category) + 
                    '&color=' + encodeURIComponent(color));
        }
        
        // 编辑订阅
        function editSubscription(id) {
            // 查找订阅信息
            <?php foreach ($all_subscriptions as $subscription): ?>
                if ('<?php echo $subscription['id']; ?>' === id) {
                    document.getElementById('edit-id').value = '<?php echo $subscription['id']; ?>';
                    document.getElementById('edit-name').value = '<?php echo htmlspecialchars($subscription['name']); ?>';
                    document.getElementById('edit-expiry-date').value = '<?php echo $subscription['expiry_date']; ?>';
                    document.getElementById('edit-category').value = '<?php echo htmlspecialchars($subscription['category']); ?>';
                    document.getElementById('edit-color').value = '<?php echo $subscription['color']; ?>';
                    document.getElementById('edit-modal').style.display = 'block';
                    return;
                }
            <?php endforeach; ?>
            
            alert('找不到订阅信息');
        }
        
        // 关闭模态框
        window.onclick = function(event) {
            const modals = ['login-modal', 'add-user-modal', 'edit-modal', 'admin-panel'];
            modals.forEach(function(modalId) {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
        
        // 关闭按钮
        document.querySelectorAll('.close').forEach(function(closeBtn) {
            closeBtn.onclick = function() {
                const modal = this.closest('.modal');
                if (modal) {
                    modal.style.display = 'none';
                }
            }
        });
        
        // 自动关闭模态框
        document.querySelectorAll('form').forEach(function(form) {
            form.onsubmit = function() {
                const modal = this.closest('.modal');
                if (modal) {
                    // 表单提交后关闭模态框
                    setTimeout(function() {
                        modal.style.display = 'none';
                    }, 500);
                }
            }
        });
    </script>
</body>
</html>
