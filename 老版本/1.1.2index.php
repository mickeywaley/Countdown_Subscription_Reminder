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
    $category = trim($_POST['category']) ?: '未分类'; // 新增分类字段
    
    if (!empty($name) && !empty($expiry_date)) {
        $subscription = [
            'id' => uniqid(), // 添加唯一ID
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
    
    if (!empty($name) && !empty($expiry_date)) {
        $subscription = [
            'id' => $id, // 保持相同的ID
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
                // 确保旧数据也有ID和分类字段
                if (!isset($subscription['id'])) {
                    $subscription['id'] = uniqid();
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

// 获取所有订阅
$all_subscriptions = getAllSubscriptions(); // 获取所有订阅，不应用过滤
$subscriptions = $all_subscriptions; // 默认使用所有订阅

// 处理排序和过滤
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'asc';
$category = isset($_GET['category']) ? $_GET['category'] : '全部';

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
            $edit_subscription = $subscription;
            break;
        }
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
        .edit-btn {
            background-color: #FF9800;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            margin-right: 5px;
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
        .category-tag {
            display: inline-block;
            padding: 2px 8px;
            margin-left: 10px;
            border-radius: 3px;
            background-color: #f0f0f0;
            font-size: 12px;
            color: #666;
        }
        .filter-sort {
            margin: 15px 0;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
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
            .filter-sort {
                flex-direction: column;
                align-items: flex-start;
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
                    <input type="text" name="category" placeholder="分类" value="未分类">
                    <button type="submit" name="add">添加</button>
                </form>
                <button class="logout-btn" onclick="window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>?logout=1'">退出登录</button>
            </div>
        <?php endif; ?>
        
        <!-- 排序和过滤选项 -->
        <div class="filter-sort">
            <span>排序:</span>
            <select id="sort-select" onchange="window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>?sort=' + this.value + '&category=<?php echo $category; ?>'">
                <option value="asc" <?php echo ($sort === 'asc') ? 'selected' : ''; ?>>按剩余时间升序</option>
                <option value="desc" <?php echo ($sort === 'desc') ? 'selected' : ''; ?>>按剩余时间降序</option>
            </select>
            
            <span>分类:</span>
            <select id="category-select" onchange="window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>?sort=<?php echo $sort; ?>&category=' + encodeURIComponent(this.value)">
                <?php foreach (getAllCategories($subscriptions) as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($category === $cat) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <h2>订阅列表</h2>
        <ul class="subscription-list">
            <?php if (empty($subscriptions)): ?>
                <li class="subscription-item">
                    <span>暂无订阅记录</span>
                </li>
            <?php else: ?>
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
                        <?php if (isLoggedIn()): ?>
                            <div>
                                <button class="edit-btn" onclick="window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>?edit=<?php echo $subscription['id']; ?>&sort=<?php echo $sort; ?>&category=<?php echo urlencode($category); ?>'">编辑</button>
                                <button class="delete-btn" onclick="if(confirm('确定要删除此订阅吗？')) window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>?delete=<?php echo $subscription['id']; ?>&sort=<?php echo $sort; ?>&category=<?php echo urlencode($category); ?>'">删除</button>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
    
    <!-- 编辑模态框 -->
    <?php if ($edit_subscription !== null): ?>
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('editModal').style.display='none'; window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>?sort=<?php echo $sort; ?>&category=<?php echo urlencode($category); ?>'">&times;</span>
            <h2>编辑订阅</h2>
            <form method="post">
                <input type="hidden" name="edit_id" value="<?php echo $edit_subscription['id']; ?>">
                <div style="margin: 10px 0;">
                    <label>名称:</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($edit_subscription['name']); ?>" required style="width: 100%; padding: 8px; margin-top: 5px;">
                </div>
                <div style="margin: 10px 0;">
                    <label>到期日期:</label>
                    <input type="date" name="expiry_date" value="<?php echo $edit_subscription['expiry_date']; ?>" required style="width: 100%; padding: 8px; margin-top: 5px;">
                </div>
                <div style="margin: 10px 0;">
                    <label>颜色:</label>
                    <input type="color" name="color" value="<?php echo $edit_subscription['color']; ?>" style="margin-top: 5px;">
                </div>
                <div style="margin: 10px 0;">
                    <label>分类:</label>
                    <input type="text" name="category" value="<?php echo htmlspecialchars($edit_subscription['category']); ?>" style="width: 100%; padding: 8px; margin-top: 5px;">
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" name="edit" style="padding: 10px 20px; background-color: #2196F3; color: white; border: none; border-radius: 3px; cursor: pointer;">保存</button>
                    <button type="button" onclick="document.getElementById('editModal').style.display='none'; window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>?sort=<?php echo $sort; ?>&category=<?php echo urlencode($category); ?>'" style="padding: 10px 20px; margin-left: 10px; background-color: #f44336; color: white; border: none; border-radius: 3px; cursor: pointer;">取消</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // 自动显示编辑模态框
        document.getElementById('editModal').style.display = 'block';
    </script>
    <?php endif; ?>
</body>
</html>
