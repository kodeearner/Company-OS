<?php
/**
 * CompanyOS - api.php
 * ─────────────────────────────────────────────────────────────
 * REST-like AJAX Backend Endpoint
 * · Auto-creates all DB tables on first run (from settings.json)
 * · Handles: Auth, Resolutions, Directors, Documents, Approvals,
 *   Users, Audit Log, Notifications, Undo/Redo, Search, Export
 * · All config from settings.json
 * ─────────────────────────────────────────────────────────────
 */

// ═══════════════════════════════════════════════
// 1. BOOTSTRAP
// ═══════════════════════════════════════════════
define('ROOT', __DIR__);
define('CONFIG_FILE', ROOT . '/settings.json');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

if (!file_exists(CONFIG_FILE)) {
    echo json_encode(['success'=>false,'message'=>'settings.json not found']);
    exit;
}
$CFG = json_decode(file_get_contents(CONFIG_FILE), true);
if (!$CFG) {
    echo json_encode(['success'=>false,'message'=>'settings.json parse error']);
    exit;
}

date_default_timezone_set($CFG['app']['timezone'] ?? 'UTC');

// Session
$sessName = $CFG['auth']['session_name'] ?? 'companyos_session';
session_name($sessName);
session_start();

// ═══════════════════════════════════════════════
// 2. RESPONSE HELPERS
// ═══════════════════════════════════════════════
function resp(bool $ok, $data=null, string $msg=''): void {
    echo json_encode(['success'=>$ok,'data'=>$data,'message'=>$msg]);
    exit;
}
function respOk($data=null, string $msg='OK'): void { resp(true,$data,$msg); }
function respErr(string $msg='Error', int $code=400): void {
    http_response_code($code);
    resp(false,null,$msg);
}

// ═══════════════════════════════════════════════
// 3. DATABASE CONNECTION & AUTO-INSTALL
// ═══════════════════════════════════════════════
function getDB(array $cfg): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $db = $cfg['database'];
    $dsn = "mysql:host={$db['host']};port={$db['port']};charset={$db['charset']}";
    try {
        $pdo = new PDO($dsn, $db['username'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        // Create DB if not exists
        $dbName = $db['name'];
        $coll   = $db['collation'];
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE {$coll}");
        $pdo->exec("USE `{$dbName}`");
        // Auto-install tables
        if ($db['auto_create_tables']) autoInstallTables($pdo, $cfg);
    } catch (PDOException $e) {
        logError($cfg, 'DB connection failed: ' . $e->getMessage());
        resp(false, null, 'Database connection failed: ' . $e->getMessage());
    }
    return $pdo;
}

// ═══════════════════════════════════════════════
// 4. AUTO TABLE INSTALLER
// ═══════════════════════════════════════════════
function autoInstallTables(PDO $pdo, array $cfg): void {
    $t = $cfg['database']['tables'];
    $sql = [];

    // ── USERS
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$t['users']}` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(200) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(30) NOT NULL DEFAULT 'staff',
        avatar VARCHAR(255) DEFAULT NULL,
        phone VARCHAR(30) DEFAULT NULL,
        status ENUM('active','inactive','suspended') DEFAULT 'active',
        last_login DATETIME DEFAULT NULL,
        login_attempts TINYINT UNSIGNED DEFAULT 0,
        locked_until DATETIME DEFAULT NULL,
        remember_token VARCHAR(100) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB";

    // ── ROLES & PERMISSIONS
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$t['roles']}` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE,
        label VARCHAR(100) NOT NULL,
        color VARCHAR(20) DEFAULT '#6b7280',
        permissions JSON DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB";

    // ── DIRECTORS
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$t['directors']}` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED DEFAULT NULL,
        name VARCHAR(150) NOT NULL,
        designation VARCHAR(100) NOT NULL,
        cnic VARCHAR(20) DEFAULT NULL,
        email VARCHAR(200) DEFAULT NULL,
        phone VARCHAR(30) DEFAULT NULL,
        address TEXT DEFAULT NULL,
        qualification VARCHAR(255) DEFAULT NULL,
        expertise TEXT DEFAULT NULL,
        photo VARCHAR(255) DEFAULT NULL,
        appointment_date DATE DEFAULT NULL,
        resignation_date DATE DEFAULT NULL,
        term_expires DATE DEFAULT NULL,
        status ENUM('active','resigned','deceased','removed') DEFAULT 'active',
        committees JSON DEFAULT NULL,
        din VARCHAR(50) DEFAULT NULL COMMENT 'Director Identification Number',
        notes TEXT DEFAULT NULL,
        created_by INT UNSIGNED DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_name (name)
    ) ENGINE=InnoDB";

    // ── RESOLUTIONS
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$t['resolutions']}` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        number VARCHAR(50) DEFAULT NULL UNIQUE,
        title VARCHAR(500) NOT NULL,
        content LONGTEXT DEFAULT NULL,
        type ENUM('ordinary','special','unanimous','circular') DEFAULT 'ordinary',
        status ENUM('draft','pending','approved','rejected','withdrawn','archived') DEFAULT 'draft',
        quorum_required TINYINT UNSIGNED DEFAULT 51,
        meeting_date DATE DEFAULT NULL,
        effective_date DATE DEFAULT NULL,
        deadline DATE DEFAULT NULL,
        tags JSON DEFAULT NULL,
        attachments JSON DEFAULT NULL,
        metadata JSON DEFAULT NULL,
        created_by INT UNSIGNED NOT NULL,
        submitted_at DATETIME DEFAULT NULL,
        approved_at DATETIME DEFAULT NULL,
        archived_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_type (type),
        INDEX idx_number (number),
        FULLTEXT idx_search (title, content)
    ) ENGINE=InnoDB";

    // ── APPROVALS
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$t['approvals']}` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        resolution_id INT UNSIGNED NOT NULL,
        voter_id INT UNSIGNED NOT NULL,
        voter_type ENUM('director','fellow','admin') DEFAULT 'director',
        vote ENUM('approve','reject','abstain','pending') DEFAULT 'pending',
        reason TEXT DEFAULT NULL,
        signature TEXT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        voted_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_vote (resolution_id, voter_id),
        INDEX idx_resolution (resolution_id),
        INDEX idx_voter (voter_id),
        INDEX idx_vote (vote)
    ) ENGINE=InnoDB";

    // ── FOLDERS
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$t['folders']}` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        parent_id INT UNSIGNED DEFAULT NULL,
        name VARCHAR(255) NOT NULL,
        icon VARCHAR(50) DEFAULT 'folder',
        color VARCHAR(20) DEFAULT '#2563eb',
        description TEXT DEFAULT NULL,
        is_system TINYINT(1) DEFAULT 0,
        permissions JSON DEFAULT NULL,
        created_by INT UNSIGNED DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_parent (parent_id),
        INDEX idx_name (name)
    ) ENGINE=InnoDB";

    // ── DOCUMENTS
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$t['documents']}` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        folder_id INT UNSIGNED DEFAULT NULL,
        title VARCHAR(500) NOT NULL,
        content LONGTEXT DEFAULT NULL,
        file_name VARCHAR(255) DEFAULT NULL,
        file_path VARCHAR(500) DEFAULT NULL,
        file_size INT UNSIGNED DEFAULT 0,
        file_type VARCHAR(100) DEFAULT NULL,
        mime_type VARCHAR(150) DEFAULT NULL,
        extension VARCHAR(20) DEFAULT NULL,
        version INT UNSIGNED DEFAULT 1,
        is_locked TINYINT(1) DEFAULT 0,
        locked_by INT UNSIGNED DEFAULT NULL,
        is_deleted TINYINT(1) DEFAULT 0,
        deleted_at DATETIME DEFAULT NULL,
        tags JSON DEFAULT NULL,
        metadata JSON DEFAULT NULL,
        access_level ENUM('public','internal','confidential','restricted') DEFAULT 'internal',
        created_by INT UNSIGNED NOT NULL,
        updated_by INT UNSIGNED DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_folder (folder_id),
        INDEX idx_deleted (is_deleted),
        INDEX idx_created_by (created_by),
        FULLTEXT idx_search (title, content)
    ) ENGINE=InnoDB";

    // ── DOCUMENT VERSIONS
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$t['doc_versions']}` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        document_id INT UNSIGNED NOT NULL,
        version INT UNSIGNED NOT NULL,
        content LONGTEXT DEFAULT NULL,
        file_path VARCHAR(500) DEFAULT NULL,
        change_summary VARCHAR(500) DEFAULT NULL,
        created_by INT UNSIGNED DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_document (document_id),
        INDEX idx_version (version)
    ) ENGINE=InnoDB";

    // ── COMMENTS
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$t['comments']}` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        entity_type ENUM('resolution','document') NOT NULL,
        entity_id INT UNSIGNED NOT NULL,
        parent_id INT UNSIGNED DEFAULT NULL,
        content TEXT NOT NULL,
        author_id INT UNSIGNED NOT NULL,
        is_internal TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_entity (entity_type, entity_id)
    ) ENGINE=InnoDB";

    // ── NOTIFICATIONS
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$t['notifications']}` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        type VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        body TEXT DEFAULT NULL,
        data JSON DEFAULT NULL,
        read_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_read (read_at)
    ) ENGINE=InnoDB";

    // ── AUDIT LOG
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$t['audit_log']}` (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED DEFAULT NULL,
        user_name VARCHAR(150) DEFAULT NULL,
        action VARCHAR(100) NOT NULL,
        entity_type VARCHAR(50) DEFAULT NULL,
        entity_id INT UNSIGNED DEFAULT NULL,
        old_value JSON DEFAULT NULL,
        new_value JSON DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(500) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_action (action),
        INDEX idx_entity (entity_type, entity_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB";

    // ── UNDO HISTORY
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$t['undo_history']}` (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(100) NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        entity_type VARCHAR(50) NOT NULL,
        entity_id INT UNSIGNED DEFAULT NULL,
        state_data LONGTEXT NOT NULL,
        operation VARCHAR(50) DEFAULT NULL,
        stack_type ENUM('undo','redo') DEFAULT 'undo',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_session (session_id, user_id),
        INDEX idx_entity (entity_type, entity_id)
    ) ENGINE=InnoDB";

    // ── TAGS
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$t['tags']}` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        color VARCHAR(20) DEFAULT '#6b7280',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB";

    // ── TAG RELATIONS
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$t['tag_relations']}` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tag_id INT UNSIGNED NOT NULL,
        entity_type VARCHAR(50) NOT NULL,
        entity_id INT UNSIGNED NOT NULL,
        UNIQUE KEY uq_tag_entity (tag_id, entity_type, entity_id),
        INDEX idx_entity (entity_type, entity_id)
    ) ENGINE=InnoDB";

    // ── SETTINGS (key-value store for runtime settings)
    $sql[] = "CREATE TABLE IF NOT EXISTS `{$t['settings']}` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value LONGTEXT DEFAULT NULL,
        setting_group VARCHAR(50) DEFAULT 'general',
        label VARCHAR(200) DEFAULT NULL,
        updated_by INT UNSIGNED DEFAULT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB";

    // Execute all DDL
    foreach ($sql as $s) {
        try { $pdo->exec($s); } catch (PDOException $e) {
            logError($cfg, 'DDL error: ' . $e->getMessage() . ' | SQL: ' . substr($s,0,100));
        }
    }

    // Seed default admin on first run
    seedDefaultData($pdo, $cfg);
}

// ═══════════════════════════════════════════════
// 5. SEED DEFAULT DATA
// ═══════════════════════════════════════════════
function seedDefaultData(PDO $pdo, array $cfg): void {
    $t = $cfg['database']['tables'];

    // Check if admin exists
    $check = $pdo->query("SELECT COUNT(*) FROM `{$t['users']}`")->fetchColumn();
    if ($check > 0) return; // already seeded

    // Create default admin
    $auth = $cfg['auth'];
    $hash = password_hash($auth['default_admin_password'], PASSWORD_BCRYPT, ['cost'=>12]);
    $stmt = $pdo->prepare("INSERT INTO `{$t['users']}` (name,email,password_hash,role,status)
        VALUES (?,?,?,'super_admin','active')");
    $stmt->execute([$auth['default_admin_name'], $auth['default_admin_email'], $hash]);
    $adminId = (int)$pdo->lastInsertId();

    // Seed default folders
    if (!empty($cfg['documents']['default_folders'])) {
        $fStmt = $pdo->prepare("INSERT INTO `{$t['folders']}` (name,icon,color,is_system,created_by) VALUES (?,?,?,1,?)");
        foreach ($cfg['documents']['default_folders'] as $folder) {
            $fStmt->execute([$folder['name'], $folder['icon'], $folder['color'], $adminId]);
        }
    }

    // Seed settings from JSON config
    $sStmt = $pdo->prepare("INSERT IGNORE INTO `{$t['settings']}` (setting_key,setting_value,setting_group,label)
        VALUES (?,?,?,?)");
    $settingPairs = [
        ['app.name',        $cfg['app']['name'],            'app',      'Application Name'],
        ['app.company',     $cfg['app']['company_name'],    'app',      'Company Name'],
        ['app.theme',       $cfg['theme']['active'],        'theme',    'Active Theme'],
        ['app.timezone',    $cfg['app']['timezone'],        'app',      'Timezone'],
        ['res.numbering',   $cfg['resolutions']['numbering_format'], 'resolutions', 'Resolution Number Format'],
    ];
    foreach ($settingPairs as $sp) {
        $sStmt->execute($sp);
    }

    // Log first-run
    logAudit($pdo, $cfg, null, 'system_init', 'system', null, null, null, 'First run — tables and seed data created');
}

// ═══════════════════════════════════════════════
// 6. LOGGING HELPERS
// ═══════════════════════════════════════════════
function logError(array $cfg, string $msg): void {
    $logDir = ROOT . '/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $logFile = ROOT . '/' . ($cfg['audit']['error_log'] ?? 'logs/error.log');
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ERROR: ' . $msg . PHP_EOL, FILE_APPEND);
}

function logAudit(PDO $pdo, array $cfg, ?int $userId, string $action, string $entityType='', ?int $entityId=null, $oldVal=null, $newVal=null, string $notes=''): void {
    if (!($cfg['audit']['enabled'] ?? true)) return;
    $t = $cfg['database']['tables']['audit_log'];
    try {
        $userName = $_SESSION['user_name'] ?? null;
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $stmt = $pdo->prepare("INSERT INTO `{$t}` (user_id,user_name,action,entity_type,entity_id,old_value,new_value,ip_address,user_agent,notes)
            VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $userId, $userName, $action, $entityType, $entityId,
            $oldVal ? json_encode($oldVal) : null,
            $newVal ? json_encode($newVal) : null,
            $ip, $ua, $notes
        ]);
    } catch (\Throwable $e) {
        logError($cfg, 'Audit log failed: ' . $e->getMessage());
    }
}

// ═══════════════════════════════════════════════
// 7. AUTH HELPERS
// ═══════════════════════════════════════════════
function requireAuth(array $cfg): array {
    if (empty($_SESSION['user_id'])) {
        respErr('Unauthorized — please log in', 401);
    }
    return ['id'=>$_SESSION['user_id'],'role'=>$_SESSION['user_role'],'name'=>$_SESSION['user_name']];
}

function hasPermission(string $role, string $permission, array $cfg): bool {
    $rolePerms = $cfg['roles'][$role]['permissions'] ?? [];
    return in_array('*', $rolePerms) || in_array($permission, $rolePerms);
}

function requirePermission(string $permission, array $cfg): void {
    $role = $_SESSION['user_role'] ?? 'viewer';
    if (!hasPermission($role, $permission, $cfg)) {
        respErr('Permission denied: ' . $permission, 403);
    }
}

// ═══════════════════════════════════════════════
// 8. RESOLUTION NUMBER GENERATOR
// ═══════════════════════════════════════════════
function generateResolutionNumber(PDO $pdo, array $cfg): string {
    $t      = $cfg['database']['tables']['resolutions'];
    $format = $cfg['resolutions']['numbering_format'] ?? 'RES-{YEAR}-{SEQ:04d}';
    $year   = date('Y');

    $count = (int)$pdo->query("SELECT COUNT(*) FROM `{$t}` WHERE YEAR(created_at) = {$year}")->fetchColumn();
    $seq   = $count + 1;

    $number = str_replace(
        ['{YEAR}', '{SEQ:04d}', '{SEQ}'],
        [$year, str_pad($seq,4,'0',STR_PAD_LEFT), $seq],
        $format
    );
    return $number;
}

// ═══════════════════════════════════════════════
// 9. CSRF CHECK
// ═══════════════════════════════════════════════
function checkCsrf(): void {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        // For login action, skip CSRF (handled in index.php)
    }
    // Allow for API calls from the same session
}

// ═══════════════════════════════════════════════
// 10. ACTION ROUTER
// ═══════════════════════════════════════════════
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$pdo    = null; // lazy init

switch ($action) {

    // ────────────────────────────────────────────
    // AUTH: LOGIN
    // ────────────────────────────────────────────
    case 'login': {
        $pdo  = getDB($CFG);
        $t    = $CFG['database']['tables']['users'];
        $email= trim($_POST['email'] ?? '');
        $pass = $_POST['password'] ?? '';

        if (!$email || !$pass) { respErr('Email and password required'); }

        $stmt = $pdo->prepare("SELECT * FROM `{$t}` WHERE email=? AND status='active' LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            logAudit($pdo, $CFG, null, 'login_failed', 'user', null, null, null, 'Email: '.$email);
            respErr('Invalid email or password');
        }

        // Check lockout
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            respErr('Account temporarily locked. Try after ' . date($CFG['app']['time_format'], strtotime($user['locked_until'])));
        }

        if (!password_verify($pass, $user['password_hash'])) {
            $attempts = (int)$user['login_attempts'] + 1;
            $max      = (int)($CFG['auth']['max_login_attempts'] ?? 5);
            $lockMins = (int)($CFG['auth']['lockout_duration_minutes'] ?? 15);
            $lockedUntil = null;
            if ($attempts >= $max) {
                $lockedUntil = date('Y-m-d H:i:s', strtotime("+{$lockMins} minutes"));
            }
            $upd = $pdo->prepare("UPDATE `{$t}` SET login_attempts=?, locked_until=? WHERE id=?");
            $upd->execute([$attempts, $lockedUntil, $user['id']]);
            logAudit($pdo, $CFG, $user['id'], 'login_failed', 'user', $user['id']);
            respErr('Invalid email or password');
        }

        // Success
        $pdo->prepare("UPDATE `{$t}` SET login_attempts=0,locked_until=NULL,last_login=NOW() WHERE id=?")->execute([$user['id']]);
        logAudit($pdo, $CFG, $user['id'], 'login', 'user', $user['id'], null, null, 'Login successful');
        respOk([
            'id'     => $user['id'],
            'name'   => $user['name'],
            'email'  => $user['email'],
            'role'   => $user['role'],
            'avatar' => $user['avatar'],
        ], 'Login successful');
    }

    // ────────────────────────────────────────────
    // AUDIT LOG (internal)
    // ────────────────────────────────────────────
    case 'audit_log': {
        $pdo  = getDB($CFG);
        $uid  = (int)($_GET['user_id'] ?? $_SESSION['user_id'] ?? 0);
        $evt  = $_GET['event'] ?? 'unknown';
        logAudit($pdo, $CFG, $uid ?: null, $evt, 'user', $uid ?: null);
        respOk(null, 'Logged');
    }

    // ────────────────────────────────────────────
    // DASHBOARD STATS
    // ────────────────────────────────────────────
    case 'dashboard_stats': {
        requireAuth($CFG);
        $pdo = getDB($CFG);
        $t   = $CFG['database']['tables'];
        $uid = (int)$_SESSION['user_id'];
        $role= $_SESSION['user_role'];

        $resCount  = (int)$pdo->query("SELECT COUNT(*) FROM `{$t['resolutions']}` WHERE status!='archived'")->fetchColumn();
        $dirCount  = (int)$pdo->query("SELECT COUNT(*) FROM `{$t['directors']}` WHERE status='active'")->fetchColumn();
        $docCount  = (int)$pdo->query("SELECT COUNT(*) FROM `{$t['documents']}` WHERE is_deleted=0")->fetchColumn();

        // Pending approvals for this user
        $pendQ = $pdo->prepare("SELECT COUNT(*) FROM `{$t['approvals']}` ap
            JOIN `{$t['resolutions']}` r ON r.id=ap.resolution_id
            WHERE ap.voter_id=? AND ap.vote='pending' AND r.status='pending'");
        $pendQ->execute([$uid]);
        $pendCount = (int)$pendQ->fetchColumn();

        respOk([
            'resolutions'      => $resCount,
            'pending_approvals'=> $pendCount,
            'directors'        => $dirCount,
            'documents'        => $docCount,
        ]);
    }

    // ────────────────────────────────────────────
    // RECENT RESOLUTIONS
    // ────────────────────────────────────────────
    case 'recent_resolutions': {
        requireAuth($CFG);
        $pdo   = getDB($CFG);
        $t     = $CFG['database']['tables'];
        $limit = max(1, min(50, (int)($_POST['limit'] ?? $_GET['limit'] ?? 10)));
        $stmt  = $pdo->prepare("SELECT r.id,r.number,r.title,r.type,r.status,
            DATE_FORMAT(r.created_at,'%d %b %Y') AS created_at,
            u.name AS created_by_name
            FROM `{$t['resolutions']}` r
            LEFT JOIN `{$t['users']}` u ON u.id=r.created_by
            ORDER BY r.created_at DESC LIMIT ?");
        $stmt->execute([$limit]);
        respOk($stmt->fetchAll());
    }

    // ────────────────────────────────────────────
    // MY PENDING APPROVALS
    // ────────────────────────────────────────────
    case 'my_pending_approvals': {
        requireAuth($CFG);
        $pdo = getDB($CFG);
        $t   = $CFG['database']['tables'];
        $uid = (int)$_SESSION['user_id'];
        $stmt = $pdo->prepare("SELECT ap.id,ap.resolution_id,
            r.title AS resolution_title,r.number AS resolution_number,
            u.name AS submitted_by,
            DATE_FORMAT(ap.created_at,'%d %b %Y') AS created_at
            FROM `{$t['approvals']}` ap
            JOIN `{$t['resolutions']}` r ON r.id=ap.resolution_id
            LEFT JOIN `{$t['users']}` u ON u.id=r.created_by
            WHERE ap.voter_id=? AND ap.vote='pending' AND r.status='pending'
            ORDER BY ap.created_at ASC LIMIT 20");
        $stmt->execute([$uid]);
        respOk($stmt->fetchAll());
    }

    // ────────────────────────────────────────────
    // RESOLUTIONS — LIST
    // ────────────────────────────────────────────
    case 'list_resolutions': {
        requireAuth($CFG);
        $pdo    = getDB($CFG);
        $t      = $CFG['database']['tables'];
        $status = $_POST['status'] ?? '';
        $type   = $_POST['type']   ?? '';
        $search = $_POST['search'] ?? '';
        $page_n = max(1,(int)($_POST['page'] ?? 1));
        $limit  = (int)($CFG['ui']['items_per_page'] ?? 25);
        $offset = ($page_n-1)*$limit;

        $where  = ['1=1'];
        $params = [];
        if ($status) { $where[] = 'r.status=?'; $params[] = $status; }
        if ($type)   { $where[] = 'r.type=?';   $params[] = $type;   }
        if ($search) { $where[] = '(r.title LIKE ? OR r.number LIKE ?)'; $params[]="%$search%"; $params[]="%$search%"; }
        $whereStr = implode(' AND ', $where);

        $total = $pdo->prepare("SELECT COUNT(*) FROM `{$t['resolutions']}` r WHERE $whereStr");
        $total->execute($params);
        $totalRows = (int)$total->fetchColumn();

        $stmt = $pdo->prepare("SELECT r.*,
            DATE_FORMAT(r.created_at,'%d %b %Y') AS created_date,
            DATE_FORMAT(r.meeting_date,'%d %b %Y') AS meeting_date_fmt,
            u.name AS author_name
            FROM `{$t['resolutions']}` r
            LEFT JOIN `{$t['users']}` u ON u.id=r.created_by
            WHERE $whereStr ORDER BY r.created_at DESC LIMIT ? OFFSET ?");
        $params[] = $limit; $params[] = $offset;
        $stmt->execute($params);
        respOk(['rows'=>$stmt->fetchAll(),'total'=>$totalRows,'page'=>$page_n,'limit'=>$limit]);
    }

    // ────────────────────────────────────────────
    // RESOLUTIONS — GET ONE
    // ────────────────────────────────────────────
    case 'get_resolution': {
        requireAuth($CFG);
        $pdo = getDB($CFG);
        $t   = $CFG['database']['tables'];
        $id  = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if (!$id) respErr('ID required');

        $stmt = $pdo->prepare("SELECT r.*,u.name AS author_name,u.email AS author_email
            FROM `{$t['resolutions']}` r
            LEFT JOIN `{$t['users']}` u ON u.id=r.created_by
            WHERE r.id=? LIMIT 1");
        $stmt->execute([$id]);
        $res = $stmt->fetch();
        if (!$res) respErr('Resolution not found',404);

        // Votes
        $vStmt = $pdo->prepare("SELECT a.*,u.name AS voter_name,u.role AS voter_role
            FROM `{$t['approvals']}` a LEFT JOIN `{$t['users']}` u ON u.id=a.voter_id
            WHERE a.resolution_id=?");
        $vStmt->execute([$id]);
        $res['votes'] = $vStmt->fetchAll();

        // Comments
        $cStmt = $pdo->prepare("SELECT c.*,u.name AS author_name
            FROM `{$t['comments']}` c LEFT JOIN `{$t['users']}` u ON u.id=c.author_id
            WHERE c.entity_type='resolution' AND c.entity_id=?
            ORDER BY c.created_at ASC");
        $cStmt->execute([$id]);
        $res['comments'] = $cStmt->fetchAll();

        respOk($res);
    }

    // ────────────────────────────────────────────
    // RESOLUTIONS — CREATE
    // ────────────────────────────────────────────
    case 'create_resolution': {
        requireAuth($CFG);
        requirePermission('manage_resolutions', $CFG);
        $pdo = getDB($CFG);
        $t   = $CFG['database']['tables'];
        $uid = (int)$_SESSION['user_id'];

        $title   = trim($_POST['title']   ?? '');
        $content = $_POST['content']      ?? '';
        $type    = $_POST['type']         ?? 'ordinary';
        $meeting = $_POST['meeting_date'] ?? null;
        $deadline= $_POST['deadline']     ?? null;
        $tags    = $_POST['tags']         ?? null;

        if (!$title) respErr('Title is required');

        $validTypes = array_keys($CFG['resolutions']['types']);
        if (!in_array($type, $validTypes)) $type = 'ordinary';

        $number  = generateResolutionNumber($pdo, $CFG);
        $quorum  = (int)($CFG['resolutions']['types'][$type]['quorum_percent'] ?? 51);

        $stmt = $pdo->prepare("INSERT INTO `{$t['resolutions']}` (number,title,content,type,status,quorum_required,meeting_date,deadline,tags,created_by)
            VALUES (?,?,?,?,'draft',?,?,?,?,?)");
        $stmt->execute([$number,$title,$content,$type,$quorum,
            $meeting ?: null, $deadline ?: null,
            $tags ? json_encode(array_filter(array_map('trim',explode(',',$tags)))) : null,
            $uid]);
        $newId = (int)$pdo->lastInsertId();
        logAudit($pdo,$CFG,$uid,'create_resolution','resolution',$newId,null,['title'=>$title,'type'=>$type],'Resolution created');

        // Push initial undo state
        saveUndoState($pdo,$CFG,$uid,'resolution',$newId,'create',['title'=>$title,'content'=>$content,'type'=>$type,'status'=>'draft']);

        respOk(['id'=>$newId,'number'=>$number], 'Resolution created: '.$number);
    }

    // ────────────────────────────────────────────
    // RESOLUTIONS — UPDATE
    // ────────────────────────────────────────────
    case 'update_resolution': {
        requireAuth($CFG);
        requirePermission('manage_resolutions',$CFG);
        $pdo = getDB($CFG);
        $t   = $CFG['database']['tables'];
        $uid = (int)$_SESSION['user_id'];
        $id  = (int)($_POST['id'] ?? 0);
        if (!$id) respErr('ID required');

        // Fetch old for undo
        $old = $pdo->prepare("SELECT * FROM `{$t['resolutions']}` WHERE id=? LIMIT 1");
        $old->execute([$id]); $oldData = $old->fetch();
        if (!$oldData) respErr('Not found',404);
        if (!in_array($oldData['status'],['draft']) && $_SESSION['user_role'] !== 'super_admin')
            respErr('Only draft resolutions can be edited');

        $title   = trim($_POST['title']   ?? $oldData['title']);
        $content = $_POST['content']      ?? $oldData['content'];
        $type    = $_POST['type']         ?? $oldData['type'];
        $meeting = $_POST['meeting_date'] ?? $oldData['meeting_date'];
        $deadline= $_POST['deadline']     ?? $oldData['deadline'];

        // Save undo before update
        saveUndoState($pdo,$CFG,$uid,'resolution',$id,'update',$oldData);

        $stmt = $pdo->prepare("UPDATE `{$t['resolutions']}` SET title=?,content=?,type=?,meeting_date=?,deadline=?,updated_at=NOW() WHERE id=?");
        $stmt->execute([$title,$content,$type,$meeting?:null,$deadline?:null,$id]);
        logAudit($pdo,$CFG,$uid,'update_resolution','resolution',$id,$oldData,['title'=>$title,'type'=>$type]);
        respOk(['id'=>$id],'Resolution updated');
    }

    // ────────────────────────────────────────────
    // RESOLUTIONS — SUBMIT FOR APPROVAL
    // ────────────────────────────────────────────
    case 'submit_resolution': {
        requireAuth($CFG);
        $pdo = getDB($CFG);
        $t   = $CFG['database']['tables'];
        $uid = (int)$_SESSION['user_id'];
        $id  = (int)($_POST['id'] ?? 0);
        if (!$id) respErr('ID required');

        $res = $pdo->prepare("SELECT * FROM `{$t['resolutions']}` WHERE id=? LIMIT 1");
        $res->execute([$id]); $r = $res->fetch();
        if (!$r) respErr('Not found',404);
        if ($r['status'] !== 'draft') respErr('Only draft resolutions can be submitted');
        if (!$r['title'] || !$r['content']) respErr('Title and content are required before submitting');

        saveUndoState($pdo,$CFG,$uid,'resolution',$id,'submit',$r);

        // Set status to pending
        $pdo->prepare("UPDATE `{$t['resolutions']}` SET status='pending',submitted_at=NOW() WHERE id=?")->execute([$id]);

        // Create approval rows for all active directors and fellows
        $voters = $pdo->query("SELECT id,role FROM `{$t['users']}` WHERE status='active' AND role IN ('director','fellow','admin') ORDER BY id")->fetchAll();
        $aStmt  = $pdo->prepare("INSERT IGNORE INTO `{$t['approvals']}` (resolution_id,voter_id,voter_type) VALUES (?,?,?)");
        foreach ($voters as $v) {
            $vType = in_array($v['role'],['director']) ? 'director' : ($v['role']==='fellow'?'fellow':'admin');
            $aStmt->execute([$id,$v['id'],$vType]);
        }

        // Notifications
        createNotificationsForRole($pdo,$CFG,$id,'director','Approval Required',"Resolution #{$r['number']} needs your vote");
        createNotificationsForRole($pdo,$CFG,$id,'fellow','Approval Required',"Resolution #{$r['number']} needs your vote");

        logAudit($pdo,$CFG,$uid,'submit_resolution','resolution',$id,['status'=>'draft'],['status'=>'pending']);
        respOk(['id'=>$id],'Resolution submitted for approval');
    }

    // ────────────────────────────────────────────
    // RESOLUTIONS — VOTE/APPROVE/REJECT
    // ────────────────────────────────────────────
    case 'approve_resolution': {
        requireAuth($CFG);
        $pdo    = getDB($CFG);
        $t      = $CFG['database']['tables'];
        $uid    = (int)$_SESSION['user_id'];
        $id     = (int)($_POST['id'] ?? 0);
        $vote   = $_POST['vote']   ?? 'approve';
        $reason = trim($_POST['reason'] ?? '');
        if (!$id) respErr('ID required');
        if (!in_array($vote,['approve','reject','abstain'])) respErr('Invalid vote');

        $res = $pdo->prepare("SELECT * FROM `{$t['resolutions']}` WHERE id=? AND status='pending' LIMIT 1");
        $res->execute([$id]); $r=$res->fetch();
        if(!$r) respErr('Resolution not found or not in pending state',404);

        // Check if voter has a slot
        $chk = $pdo->prepare("SELECT id FROM `{$t['approvals']}` WHERE resolution_id=? AND voter_id=? LIMIT 1");
        $chk->execute([$id,$uid]); $slot=$chk->fetch();
        if (!$slot) respErr('You are not assigned to vote on this resolution',403);

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $pdo->prepare("UPDATE `{$t['approvals']}` SET vote=?,reason=?,ip_address=?,voted_at=NOW() WHERE resolution_id=? AND voter_id=?")
            ->execute([$vote,$reason,$ip,$id,$uid]);

        // Check if resolution can be auto-resolved
        autoCheckResolutionStatus($pdo,$CFG,$id,$r);
        logAudit($pdo,$CFG,$uid,'vote_resolution','resolution',$id,null,['vote'=>$vote,'reason'=>$reason]);
        respOk(['vote'=>$vote],'Vote recorded: '.ucfirst($vote));
    }

    // ────────────────────────────────────────────
    // RESOLUTIONS — ARCHIVE / DELETE / WITHDRAW
    // ────────────────────────────────────────────
    case 'archive_resolution': {
        requireAuth($CFG); requirePermission('manage_resolutions',$CFG);
        $pdo=$getDB=$CFG; $pdo=getDB($CFG);
        $t=$CFG['database']['tables']; $id=(int)($_POST['id']??0); $uid=(int)$_SESSION['user_id'];
        if(!$id) respErr('ID required');
        $pdo->prepare("UPDATE `{$t['resolutions']}` SET status='archived',archived_at=NOW() WHERE id=?")->execute([$id]);
        logAudit($pdo,$CFG,$uid,'archive_resolution','resolution',$id);
        respOk(null,'Archived');
    }
    case 'delete_resolution': {
        requireAuth($CFG); requirePermission('manage_resolutions',$CFG);
        $pdo=getDB($CFG); $t=$CFG['database']['tables']; $id=(int)($_POST['id']??0); $uid=(int)$_SESSION['user_id'];
        if(!$id) respErr('ID required');
        $old=$pdo->prepare("SELECT * FROM `{$t['resolutions']}` WHERE id=? LIMIT 1"); $old->execute([$id]); $oldD=$old->fetch();
        if(!$oldD) respErr('Not found',404);
        saveUndoState($pdo,$CFG,$uid,'resolution',$id,'delete',$oldD);
        $pdo->prepare("DELETE FROM `{$t['resolutions']}` WHERE id=? AND status='draft'")->execute([$id]);
        logAudit($pdo,$CFG,$uid,'delete_resolution','resolution',$id,$oldD);
        respOk(null,'Deleted');
    }

    // ────────────────────────────────────────────
    // DIRECTORS — LIST
    // ────────────────────────────────────────────
    case 'list_directors': {
        requireAuth($CFG);
        $pdo=$pdo=getDB($CFG); $t=$CFG['database']['tables'];
        $status=$_POST['status']??'active'; $search=$_POST['search']??'';
        $where=['1=1']; $params=[];
        if($status){ $where[]='status=?'; $params[]=$status; }
        if($search){ $where[]='(name LIKE ? OR designation LIKE ? OR email LIKE ?)'; $params[]="%$search%"; $params[]="%$search%"; $params[]="%$search%"; }
        $ws=implode(' AND ',$where);
        $stmt=$pdo->prepare("SELECT *,DATE_FORMAT(appointment_date,'%d %b %Y') AS appt_fmt,DATE_FORMAT(term_expires,'%d %b %Y') AS term_fmt FROM `{$t['directors']}` WHERE $ws ORDER BY name ASC");
        $stmt->execute($params);
        respOk($stmt->fetchAll());
    }

    // ────────────────────────────────────────────
    // DIRECTORS — GET ONE
    // ────────────────────────────────────────────
    case 'get_director': {
        requireAuth($CFG); $pdo=getDB($CFG); $t=$CFG['database']['tables'];
        $id=(int)($_POST['id']??$_GET['id']??0); if(!$id) respErr('ID required');
        $stmt=$pdo->prepare("SELECT * FROM `{$t['directors']}` WHERE id=? LIMIT 1"); $stmt->execute([$id]);
        $d=$stmt->fetch(); if(!$d) respErr('Not found',404);
        respOk($d);
    }

    // ────────────────────────────────────────────
    // DIRECTORS — CREATE
    // ────────────────────────────────────────────
    case 'create_director': {
        requireAuth($CFG); requirePermission('manage_directors',$CFG);
        $pdo=getDB($CFG); $t=$CFG['database']['tables']; $uid=(int)$_SESSION['user_id'];

        $name   = trim($_POST['name']        ?? ''); if(!$name) respErr('Name required');
        $desig  = trim($_POST['designation'] ?? ''); if(!$desig) respErr('Designation required');
        $cnic   = trim($_POST['cnic']        ?? '');
        $email  = trim($_POST['email']       ?? '');
        $phone  = trim($_POST['phone']       ?? '');
        $apptDt = $_POST['appointment_date'] ?? null;
        $termEx = $_POST['term_expires']     ?? null;
        $qualif = trim($_POST['qualification'] ?? '');
        $expertise=trim($_POST['expertise'] ?? '');
        $comms  = $_POST['committees']       ?? [];
        $din    = trim($_POST['din']         ?? '');

        $stmt=$pdo->prepare("INSERT INTO `{$t['directors']}` (name,designation,cnic,email,phone,appointment_date,term_expires,qualification,expertise,committees,din,status,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,'active',?)");
        $stmt->execute([$name,$desig,$cnic,$email,$phone,$apptDt?:null,$termEx?:null,$qualif,$expertise,
            !empty($comms)?json_encode($comms):null,$din,$uid]);
        $newId=(int)$pdo->lastInsertId();
        logAudit($pdo,$CFG,$uid,'create_director','director',$newId,null,['name'=>$name,'designation'=>$desig]);
        respOk(['id'=>$newId],'Director added: '.$name);
    }

    // ────────────────────────────────────────────
    // DIRECTORS — UPDATE
    // ────────────────────────────────────────────
    case 'update_director': {
        requireAuth($CFG); requirePermission('manage_directors',$CFG);
        $pdo=getDB($CFG); $t=$CFG['database']['tables']; $uid=(int)$_SESSION['user_id'];
        $id=(int)($_POST['id']??0); if(!$id) respErr('ID required');
        $old=$pdo->prepare("SELECT * FROM `{$t['directors']}` WHERE id=? LIMIT 1"); $old->execute([$id]); $oldD=$old->fetch();
        if(!$oldD) respErr('Not found',404);
        saveUndoState($pdo,$CFG,$uid,'director',$id,'update',$oldD);
        $fields=['name','designation','cnic','email','phone','appointment_date','term_expires','qualification','expertise','din','notes','status'];
        $sets=[]; $params=[];
        foreach($fields as $f){
            if(isset($_POST[$f])){ $sets[]="$f=?"; $params[]=$_POST[$f]?:null; }
        }
        if(isset($_POST['committees'])){ $sets[]='committees=?'; $params[]=json_encode($_POST['committees']); }
        if(!$sets) respErr('Nothing to update');
        $params[]=$id;
        $pdo->prepare("UPDATE `{$t['directors']}` SET ".implode(',',$sets).",updated_at=NOW() WHERE id=?")->execute($params);
        logAudit($pdo,$CFG,$uid,'update_director','director',$id,$oldD,$_POST);
        respOk(['id'=>$id],'Director updated');
    }

    // ────────────────────────────────────────────
    // DIRECTORS — RESIGN / REMOVE
    // ────────────────────────────────────────────
    case 'resign_director': {
        requireAuth($CFG); requirePermission('manage_directors',$CFG);
        $pdo=getDB($CFG); $t=$CFG['database']['tables']; $uid=(int)$_SESSION['user_id'];
        $id=(int)($_POST['id']??0); $resDate=$_POST['resignation_date']??date('Y-m-d'); $reason=trim($_POST['reason']??'');
        if(!$id) respErr('ID required');
        $pdo->prepare("UPDATE `{$t['directors']}` SET status='resigned',resignation_date=?,notes=CONCAT(IFNULL(notes,''),' | Resigned: $reason'),updated_at=NOW() WHERE id=?")->execute([$resDate,$id]);
        logAudit($pdo,$CFG,$uid,'resign_director','director',$id,null,['status'=>'resigned','date'=>$resDate]);
        respOk(null,'Director resigned');
    }

    // ────────────────────────────────────────────
    // DOCUMENTS — LIST
    // ────────────────────────────────────────────
    case 'list_documents': {
        requireAuth($CFG); $pdo=getDB($CFG); $t=$CFG['database']['tables'];
        $folderId=(int)($_POST['folder_id']??0); $search=$_POST['search']??''; $deleted=(int)($_POST['deleted']??0);
        $where=['d.is_deleted=?']; $params=[$deleted];
        if($folderId){ $where[]='d.folder_id=?'; $params[]=$folderId; }
        if($search){ $where[]='(d.title LIKE ? OR d.file_name LIKE ?)'; $params[]="%$search%"; $params[]="%$search%"; }
        $ws=implode(' AND ',$where);
        $stmt=$pdo->prepare("SELECT d.id,d.title,d.file_name,d.file_size,d.file_type,d.version,d.access_level,
            d.folder_id,f.name AS folder_name,u.name AS created_by_name,
            DATE_FORMAT(d.created_at,'%d %b %Y') AS created_date,
            DATE_FORMAT(d.updated_at,'%d %b %Y %H:%i') AS updated_date
            FROM `{$t['documents']}` d
            LEFT JOIN `{$t['folders']}` f ON f.id=d.folder_id
            LEFT JOIN `{$t['users']}` u ON u.id=d.created_by
            WHERE $ws ORDER BY d.updated_at DESC LIMIT 100");
        $stmt->execute($params); respOk($stmt->fetchAll());
    }

    // ────────────────────────────────────────────
    // DOCUMENTS — CREATE / UPDATE
    // ────────────────────────────────────────────
    case 'create_document': {
        requireAuth($CFG); requirePermission('create_documents',$CFG);
        $pdo=getDB($CFG); $t=$CFG['database']['tables']; $uid=(int)$_SESSION['user_id'];
        $title=trim($_POST['title']??''); if(!$title) respErr('Title required');
        $content=$_POST['content']??'';
        $folderId=(int)($_POST['folder_id']??0)?:null;
        $access=$_POST['access_level']??'internal';
        $validAccess=['public','internal','confidential','restricted'];
        if(!in_array($access,$validAccess)) $access='internal';
        $stmt=$pdo->prepare("INSERT INTO `{$t['documents']}` (title,content,folder_id,access_level,created_by) VALUES (?,?,?,?,?)");
        $stmt->execute([$title,$content,$folderId,$access,$uid]);
        $newId=(int)$pdo->lastInsertId();
        logAudit($pdo,$CFG,$uid,'create_document','document',$newId,null,['title'=>$title]);
        saveUndoState($pdo,$CFG,$uid,'document',$newId,'create',['title'=>$title,'content'=>$content]);
        respOk(['id'=>$newId],'Document created');
    }
    case 'update_document': {
        requireAuth($CFG); $pdo=getDB($CFG); $t=$CFG['database']['tables']; $uid=(int)$_SESSION['user_id'];
        $id=(int)($_POST['id']??0); if(!$id) respErr('ID required');
        $old=$pdo->prepare("SELECT * FROM `{$t['documents']}` WHERE id=? AND is_deleted=0 LIMIT 1"); $old->execute([$id]); $oldD=$old->fetch();
        if(!$oldD) respErr('Not found',404);
        // Check edit permission
        if($oldD['created_by']!==$uid && !hasPermission($_SESSION['user_role'],'manage_documents',$CFG))
            respErr('Permission denied',403);
        saveUndoState($pdo,$CFG,$uid,'document',$id,'update',$oldD);
        // Save version snapshot
        $vStmt=$pdo->prepare("INSERT INTO `{$t['doc_versions']}` (document_id,version,content,change_summary,created_by) VALUES (?,?,?,?,?)");
        $vStmt->execute([$id,$oldD['version'],$oldD['content'],$_POST['change_summary']??'Edit',$uid]);

        $newVersion=$oldD['version']+1;
        $title=trim($_POST['title']??$oldD['title']);
        $content=$_POST['content']??$oldD['content'];
        $access=$_POST['access_level']??$oldD['access_level'];
        $pdo->prepare("UPDATE `{$t['documents']}` SET title=?,content=?,access_level=?,version=?,updated_by=?,updated_at=NOW() WHERE id=?")
            ->execute([$title,$content,$access,$newVersion,$uid,$id]);
        logAudit($pdo,$CFG,$uid,'update_document','document',$id,$oldD,['title'=>$title,'version'=>$newVersion]);
        respOk(['id'=>$id,'version'=>$newVersion],'Document updated (v'.$newVersion.')');
    }

    // ────────────────────────────────────────────
    // DOCUMENTS — SOFT DELETE / RESTORE
    // ────────────────────────────────────────────
    case 'delete_document': {
        requireAuth($CFG); $pdo=getDB($CFG); $t=$CFG['database']['tables']; $uid=(int)$_SESSION['user_id'];
        $id=(int)($_POST['id']??0); if(!$id) respErr('ID required');
        $pdo->prepare("UPDATE `{$t['documents']}` SET is_deleted=1,deleted_at=NOW() WHERE id=? AND is_deleted=0")->execute([$id]);
        logAudit($pdo,$CFG,$uid,'delete_document','document',$id);
        respOk(null,'Moved to recycle bin');
    }
    case 'restore_document': {
        requireAuth($CFG); $pdo=getDB($CFG); $t=$CFG['database']['tables']; $uid=(int)$_SESSION['user_id'];
        $id=(int)($_POST['id']??0); if(!$id) respErr('ID required');
        $pdo->prepare("UPDATE `{$t['documents']}` SET is_deleted=0,deleted_at=NULL WHERE id=?")->execute([$id]);
        logAudit($pdo,$CFG,$uid,'restore_document','document',$id);
        respOk(null,'Document restored');
    }

    // ────────────────────────────────────────────
    // FOLDERS — CRUD
    // ────────────────────────────────────────────
    case 'list_folders': {
        requireAuth($CFG); $pdo=getDB($CFG); $t=$CFG['database']['tables'];
        $stmt=$pdo->query("SELECT f.*,(SELECT COUNT(*) FROM `{$t['documents']}` d WHERE d.folder_id=f.id AND d.is_deleted=0) AS doc_count FROM `{$t['folders']}` f ORDER BY f.name ASC");
        respOk($stmt->fetchAll());
    }
    case 'create_folder': {
        requireAuth($CFG); requirePermission('manage_documents',$CFG);
        $pdo=getDB($CFG); $t=$CFG['database']['tables']; $uid=(int)$_SESSION['user_id'];
        $name=trim($_POST['name']??''); if(!$name) respErr('Name required');
        $parentId=(int)($_POST['parent_id']??0)?:null;
        $stmt=$pdo->prepare("INSERT INTO `{$t['folders']}` (name,parent_id,icon,color,created_by) VALUES (?,?,?,?,?)");
        $stmt->execute([$name,$parentId,$_POST['icon']??'folder',$_POST['color']??'#2563eb',$uid]);
        logAudit($pdo,$CFG,$uid,'create_folder','folder',(int)$pdo->lastInsertId(),null,['name'=>$name]);
        respOk(['id'=>(int)$pdo->lastInsertId()],'Folder created');
    }
    case 'rename_folder': {
        requireAuth($CFG); requirePermission('manage_documents',$CFG);
        $pdo=getDB($CFG); $t=$CFG['database']['tables']; $uid=(int)$_SESSION['user_id'];
        $id=(int)($_POST['id']??0); $name=trim($_POST['name']??'');
        if(!$id||!$name) respErr('ID and name required');
        $pdo->prepare("UPDATE `{$t['folders']}` SET name=?,updated_at=NOW() WHERE id=? AND is_system=0")->execute([$name,$id]);
        logAudit($pdo,$CFG,$uid,'rename_folder','folder',$id);
        respOk(null,'Folder renamed');
    }

    // ────────────────────────────────────────────
    // USERS — LIST / CREATE / UPDATE
    // ────────────────────────────────────────────
    case 'list_users': {
        requireAuth($CFG); requirePermission('manage_users',$CFG);
        $pdo=getDB($CFG); $t=$CFG['database']['tables'];
        $stmt=$pdo->query("SELECT id,name,email,role,status,DATE_FORMAT(last_login,'%d %b %Y %H:%i') AS last_login_fmt,DATE_FORMAT(created_at,'%d %b %Y') AS created_date FROM `{$t['users']}` ORDER BY created_at DESC");
        respOk($stmt->fetchAll());
    }
    case 'create_user': {
        requireAuth($CFG); requirePermission('manage_users',$CFG);
        $pdo=getDB($CFG); $t=$CFG['database']['tables']; $uid=(int)$_SESSION['user_id'];
        $name=trim($_POST['name']??''); $email=trim($_POST['email']??''); $pass=$_POST['password']??''; $role=$_POST['role']??'staff';
        if(!$name||!$email||!$pass) respErr('Name, email and password required');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)) respErr('Invalid email');
        $minLen=(int)($CFG['auth']['password_min_length']??8);
        if(strlen($pass)<$minLen) respErr("Password must be at least $minLen characters");
        if(!array_key_exists($role,$CFG['roles'])) $role='staff';
        $check=$pdo->prepare("SELECT id FROM `{$t['users']}` WHERE email=? LIMIT 1"); $check->execute([$email]);
        if($check->fetch()) respErr('Email already exists');
        $hash=password_hash($pass,PASSWORD_BCRYPT,['cost'=>12]);
        $stmt=$pdo->prepare("INSERT INTO `{$t['users']}` (name,email,password_hash,role) VALUES (?,?,?,?)");
        $stmt->execute([$name,$email,$hash,$role]);
        $newId=(int)$pdo->lastInsertId();
        logAudit($pdo,$CFG,$uid,'create_user','user',$newId,null,['name'=>$name,'email'=>$email,'role'=>$role]);
        respOk(['id'=>$newId],'User created: '.$name);
    }
    case 'update_user': {
        requireAuth($CFG); requirePermission('manage_users',$CFG);
        $pdo=getDB($CFG); $t=$CFG['database']['tables']; $uid=(int)$_SESSION['user_id'];
        $id=(int)($_POST['id']??0); if(!$id) respErr('ID required');
        $sets=[]; $params=[];
        if(isset($_POST['name'])){ $sets[]='name=?'; $params[]=trim($_POST['name']); }
        if(isset($_POST['role'])){ $r=$_POST['role']; if(array_key_exists($r,$CFG['roles'])){ $sets[]='role=?'; $params[]=$r; } }
        if(isset($_POST['status'])){ $s=$_POST['status']; if(in_array($s,['active','inactive','suspended'])){ $sets[]='status=?'; $params[]=$s; } }
        if(isset($_POST['password'])&&$_POST['password']){ $sets[]='password_hash=?'; $params[]=password_hash($_POST['password'],PASSWORD_BCRYPT,['cost'=>12]); }
        if(!$sets) respErr('Nothing to update');
        $params[]=$id;
        $pdo->prepare("UPDATE `{$t['users']}` SET ".implode(',',$sets).",updated_at=NOW() WHERE id=?")->execute($params);
        logAudit($pdo,$CFG,$uid,'update_user','user',$id);
        respOk(['id'=>$id],'User updated');
    }
    case 'delete_user': {
        requireAuth($CFG); requirePermission('manage_users',$CFG);
        $pdo=getDB($CFG); $t=$CFG['database']['tables']; $uid=(int)$_SESSION['user_id'];
        $id=(int)($_POST['id']??0); if(!$id) respErr('ID required');
        if($id===$uid) respErr('Cannot delete your own account');
        $pdo->prepare("UPDATE `{$t['users']}` SET status='inactive' WHERE id=?")->execute([$id]);
        logAudit($pdo,$CFG,$uid,'deactivate_user','user',$id);
        respOk(null,'User deactivated');
    }

    // ────────────────────────────────────────────
    // UNDO / REDO
    // ────────────────────────────────────────────
    case 'save_undo_state': {
        requireAuth($CFG); $pdo=getDB($CFG);
        $uid=(int)$_SESSION['user_id']; $sid=session_id();
        $entity=$_POST['entity_type']??''; $eid=(int)($_POST['entity_id']??0);
        $state=$_POST['state']??''; $op=$_POST['operation']??'update';
        if(!$entity||!$state) respErr('entity_type and state required');
        saveUndoState($pdo,$CFG,$uid,$entity,$eid,$op,json_decode($state,true));
        respOk(null,'State saved');
    }
    case 'get_undo_stack': {
        requireAuth($CFG); $pdo=getDB($CFG); $t=$CFG['database']['tables'];
        $uid=(int)$_SESSION['user_id']; $sid=session_id();
        $entity=$_POST['entity_type']??'';
        $stmt=$pdo->prepare("SELECT * FROM `{$t['undo_history']}` WHERE session_id=? AND user_id=? AND entity_type=? ORDER BY id DESC LIMIT 50");
        $stmt->execute([$sid,$uid,$entity]); respOk($stmt->fetchAll());
    }

    // ────────────────────────────────────────────
    // COMMENTS
    // ────────────────────────────────────────────
    case 'add_comment': {
        requireAuth($CFG); $pdo=getDB($CFG); $t=$CFG['database']['tables']; $uid=(int)$_SESSION['user_id'];
        $etype=$_POST['entity_type']??''; $eid=(int)($_POST['entity_id']??0); $content=trim($_POST['content']??'');
        if(!$etype||!$eid||!$content) respErr('entity_type, entity_id and content required');
        $stmt=$pdo->prepare("INSERT INTO `{$t['comments']}` (entity_type,entity_id,content,author_id) VALUES (?,?,?,?)");
        $stmt->execute([$etype,$eid,$content,$uid]);
        respOk(['id'=>(int)$pdo->lastInsertId()],'Comment added');
    }
    case 'get_comments': {
        requireAuth($CFG); $pdo=getDB($CFG); $t=$CFG['database']['tables'];
        $etype=$_POST['entity_type']??''; $eid=(int)($_POST['entity_id']??0);
        if(!$etype||!$eid) respErr('entity_type and entity_id required');
        $stmt=$pdo->prepare("SELECT c.*,u.name AS author_name,DATE_FORMAT(c.created_at,'%d %b %Y %H:%i') AS created_fmt FROM `{$t['comments']}` c LEFT JOIN `{$t['users']}` u ON u.id=c.author_id WHERE c.entity_type=? AND c.entity_id=? ORDER BY c.created_at ASC");
        $stmt->execute([$etype,$eid]); respOk($stmt->fetchAll());
    }

    // ────────────────────────────────────────────
    // NOTIFICATIONS
    // ────────────────────────────────────────────
    case 'get_notifications': {
        requireAuth($CFG); $pdo=getDB($CFG); $t=$CFG['database']['tables'];
        $uid=(int)$_SESSION['user_id']; $limit=max(1,min(50,(int)($_POST['limit']??$_GET['limit']??20)));
        $stmt=$pdo->prepare("SELECT *,DATE_FORMAT(created_at,'%d %b %H:%i') AS created_fmt FROM `{$t['notifications']}` WHERE user_id=? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$uid,$limit]); respOk($stmt->fetchAll());
    }
    case 'mark_notification_read': {
        requireAuth($CFG); $pdo=getDB($CFG); $t=$CFG['database']['tables']; $uid=(int)$_SESSION['user_id'];
        $id=(int)($_POST['id']??0);
        $pdo->prepare("UPDATE `{$t['notifications']}` SET read_at=NOW() WHERE id=? AND user_id=?")->execute([$id,$uid]);
        respOk(null,'Marked read');
    }
    case 'mark_all_notifications_read': {
        requireAuth($CFG); $pdo=getDB($CFG); $t=$CFG['database']['tables']; $uid=(int)$_SESSION['user_id'];
        $pdo->prepare("UPDATE `{$t['notifications']}` SET read_at=NOW() WHERE user_id=? AND read_at IS NULL")->execute([$uid]);
        respOk(null,'All marked read');
    }

    // ────────────────────────────────────────────
    // AUDIT LOG — VIEW
    // ────────────────────────────────────────────
    case 'list_audit_log': {
        requireAuth($CFG); requirePermission('view_audit',$CFG);
        $pdo=getDB($CFG); $t=$CFG['database']['tables'];
        $limit=max(1,min(200,(int)($_POST['limit']??50)));
        $offset=max(0,(int)($_POST['offset']??0));
        $filter=$_POST['action']??''; $userId=(int)($_POST['user_id']??0);
        $where=['1=1']; $params=[];
        if($filter){ $where[]='action LIKE ?'; $params[]="%$filter%"; }
        if($userId){ $where[]='user_id=?'; $params[]=$userId; }
        $ws=implode(' AND ',$where); $params[]=$limit; $params[]=$offset;
        $stmt=$pdo->prepare("SELECT *,DATE_FORMAT(created_at,'%d %b %Y %H:%i:%s') AS created_fmt FROM `{$t['audit_log']}` WHERE $ws ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute($params); respOk($stmt->fetchAll());
    }

    // ────────────────────────────────────────────
    // GLOBAL SEARCH
    // ────────────────────────────────────────────
    case 'search': {
        requireAuth($CFG); $pdo=getDB($CFG); $t=$CFG['database']['tables'];
        $q=trim($_POST['q']??$_GET['q']??''); if(strlen($q)<2) respErr('Query too short');
        $q="%$q%"; $results=[];
        // Resolutions
        $s=$pdo->prepare("SELECT id,'resolution' AS type,number AS ref,title,status FROM `{$t['resolutions']}` WHERE title LIKE ? OR number LIKE ? LIMIT 10");
        $s->execute([$q,$q]); $results=array_merge($results,$s->fetchAll());
        // Documents
        $s=$pdo->prepare("SELECT id,'document' AS type,'' AS ref,title,access_level AS status FROM `{$t['documents']}` WHERE is_deleted=0 AND (title LIKE ? OR file_name LIKE ?) LIMIT 10");
        $s->execute([$q,$q]); $results=array_merge($results,$s->fetchAll());
        // Directors
        $s=$pdo->prepare("SELECT id,'director' AS type,din AS ref,name AS title,status FROM `{$t['directors']}` WHERE name LIKE ? OR email LIKE ? LIMIT 5");
        $s->execute([$q,$q]); $results=array_merge($results,$s->fetchAll());
        respOk($results);
    }

    // ────────────────────────────────────────────
    // EXPORT (placeholder — full impl in documents/resolutions.php)
    // ────────────────────────────────────────────
    case 'export_pdf': {
        requireAuth($CFG);
        $id=(int)($_POST['id']??0);
        // Full PDF generation using FPDF/TCPDF in documents.php/resolutions.php
        respOk(['url'=>'#','message'=>'PDF export available in full module'],'Redirect to module for PDF generation');
    }
    case 'export_excel': {
        requireAuth($CFG);
        $type=$_POST['type']??'resolutions';
        respOk(['url'=>'#','message'=>'Excel export in settings_ui.php'],'Redirect to module');
    }

    // ────────────────────────────────────────────
    // SETTINGS — GET/SET
    // ────────────────────────────────────────────
    case 'get_settings': {
        requireAuth($CFG); requirePermission('manage_settings',$CFG);
        $pdo=getDB($CFG); $t=$CFG['database']['tables'];
        $stmt=$pdo->query("SELECT * FROM `{$t['settings']}` ORDER BY setting_group,setting_key");
        respOk($stmt->fetchAll());
    }
    case 'save_setting': {
        requireAuth($CFG); requirePermission('manage_settings',$CFG);
        $pdo=getDB($CFG); $t=$CFG['database']['tables']; $uid=(int)$_SESSION['user_id'];
        $key=trim($_POST['key']??''); $val=$_POST['value']??'';
        if(!$key) respErr('Key required');
        $pdo->prepare("INSERT INTO `{$t['settings']}` (setting_key,setting_value,updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=?,updated_by=?,updated_at=NOW()")->execute([$key,$val,$uid,$val,$uid]);
        logAudit($pdo,$CFG,$uid,'save_setting','setting',null,null,['key'=>$key]);
        respOk(null,'Setting saved');
    }

    // ────────────────────────────────────────────
    // DEFAULT
    // ────────────────────────────────────────────
    default:
        respErr('Unknown action: ' . htmlspecialchars($action), 400);
}

// ═══════════════════════════════════════════════
// HELPER: Save Undo State to DB
// ═══════════════════════════════════════════════
function saveUndoState(PDO $pdo, array $cfg, int $uid, string $entity, ?int $eid, string $op, $stateData): void {
    $t   = $cfg['database']['tables']['undo_history'];
    $sid = session_id();
    $limit = (int)($cfg['resolutions']['undo_stack_limit'] ?? 50);
    try {
        // Insert
        $stmt = $pdo->prepare("INSERT INTO `{$t}` (session_id,user_id,entity_type,entity_id,state_data,operation,stack_type) VALUES (?,?,?,?,?,?,'undo')");
        $stmt->execute([$sid,$uid,$entity,$eid,json_encode($stateData),$op]);
        // Prune old entries beyond limit
        $countQ = $pdo->prepare("SELECT COUNT(*) FROM `{$t}` WHERE session_id=? AND user_id=? AND entity_type=? AND stack_type='undo'");
        $countQ->execute([$sid,$uid,$entity]);
        $count = (int)$countQ->fetchColumn();
        if ($count > $limit) {
            $excess = $count - $limit;
            $pdo->prepare("DELETE FROM `{$t}` WHERE session_id=? AND user_id=? AND entity_type=? AND stack_type='undo' ORDER BY id ASC LIMIT $excess")->execute([$sid,$uid,$entity]);
        }
    } catch (\Throwable $e) {
        logError($cfg, 'saveUndoState failed: ' . $e->getMessage());
    }
}

// ═══════════════════════════════════════════════
// HELPER: Auto-check resolution quorum/status
// ═══════════════════════════════════════════════
function autoCheckResolutionStatus(PDO $pdo, array $cfg, int $resId, array $res): void {
    $t      = $cfg['database']['tables'];
    $quorum = (int)$res['quorum_required'];

    $votes  = $pdo->prepare("SELECT vote,COUNT(*) AS cnt FROM `{$t['approvals']}` WHERE resolution_id=? GROUP BY vote");
    $votes->execute([$resId]);
    $voteCounts = ['approve'=>0,'reject'=>0,'abstain'=>0,'pending'=>0];
    foreach ($votes->fetchAll() as $v) { $voteCounts[$v['vote']] = (int)$v['cnt']; }

    $total    = array_sum($voteCounts);
    $voted    = $voteCounts['approve'] + $voteCounts['reject'] + $voteCounts['abstain'];
    $pending  = $voteCounts['pending'];
    $approved = $voteCounts['approve'];

    if ($total === 0) return;

    $approvePercent = ($voted > 0) ? ($approved / $voted * 100) : 0;

    if ($pending === 0) {
        // All votes cast
        $newStatus = ($approvePercent >= $quorum) ? 'approved' : 'rejected';
        $extra = ($newStatus === 'approved') ? ',approved_at=NOW()' : '';
        $pdo->prepare("UPDATE `{$t['resolutions']}` SET status=?$extra WHERE id=?")->execute([$newStatus,$resId]);
        createNotificationsForAll($pdo,$cfg,$resId,'Resolution '.ucfirst($newStatus),"Resolution #{$res['number']} has been {$newStatus}");
    }
}

// ═══════════════════════════════════════════════
// HELPER: Create notifications for role
// ═══════════════════════════════════════════════
function createNotificationsForRole(PDO $pdo, array $cfg, int $resId, string $role, string $title, string $body): void {
    if (!($cfg['notifications']['in_app_enabled'] ?? true)) return;
    $t    = $cfg['database']['tables'];
    $stmt = $pdo->prepare("SELECT id FROM `{$t['users']}` WHERE role=? AND status='active'");
    $stmt->execute([$role]);
    $nStmt = $pdo->prepare("INSERT INTO `{$t['notifications']}` (user_id,type,title,body,data) VALUES (?,'resolution',?,?,?)");
    foreach ($stmt->fetchAll() as $u) {
        $nStmt->execute([$u['id'],$title,$body,json_encode(['resolution_id'=>$resId])]);
    }
}
function createNotificationsForAll(PDO $pdo, array $cfg, int $resId, string $title, string $body): void {
    if (!($cfg['notifications']['in_app_enabled'] ?? true)) return;
    $t    = $cfg['database']['tables'];
    $stmt = $pdo->query("SELECT id FROM `{$t['users']}` WHERE status='active'");
    $nStmt= $pdo->prepare("INSERT INTO `{$t['notifications']}` (user_id,type,title,body,data) VALUES (?,'resolution',?,?,?)");
    foreach ($stmt->fetchAll() as $u) {
        $nStmt->execute([$u['id'],$title,$body,json_encode(['resolution_id'=>$resId])]);
    }
}
?>
