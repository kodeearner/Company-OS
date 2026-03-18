<?php
/**
 * CompanyOS - index.php
 * ─────────────────────────────────────────────────────────────
 * Main Entry Point · Authentication · Layout Shell · Routing
 * ─────────────────────────────────────────────────────────────
 * Reads all config from settings.json
 * Serves login page, then the full MS-Office-style ribbon UI
 */

// ═══════════════════════════════════════════════
// 1. BOOTSTRAP
// ═══════════════════════════════════════════════
define('ROOT', __DIR__);
define('CONFIG_FILE', ROOT . '/settings.json');

if (!file_exists(CONFIG_FILE)) {
    die('<h2 style="color:red;font-family:sans-serif;">Fatal: settings.json not found in ' . ROOT . '</h2>');
}

$CFG = json_decode(file_get_contents(CONFIG_FILE), true);
if (!$CFG) {
    die('<h2 style="color:red;font-family:sans-serif;">Fatal: settings.json is invalid JSON.</h2>');
}

// Timezone
date_default_timezone_set($CFG['app']['timezone'] ?? 'UTC');

// Session
$sessName = $CFG['auth']['session_name'] ?? 'companyos_session';
session_name($sessName);
session_start();

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ═══════════════════════════════════════════════
// 2. SIMPLE ROUTER
// ═══════════════════════════════════════════════
$page    = $_GET['page']    ?? 'dashboard';
$action  = $_GET['action']  ?? '';
$module  = $_GET['module']  ?? '';

$allowedPublicPages = ['login', 'logout'];

// ═══════════════════════════════════════════════
// 3. LOGOUT
// ═══════════════════════════════════════════════
if ($page === 'logout') {
    // Log to audit via api.php
    if (!empty($_SESSION['user_id'])) {
        @file_get_contents(
            'http://localhost' . dirname($_SERVER['SCRIPT_NAME']) . '/api.php?action=audit_log'
            . '&event=logout&user_id=' . (int)$_SESSION['user_id']
        );
    }
    session_destroy();
    header('Location: index.php?page=login&msg=logged_out');
    exit;
}

// ═══════════════════════════════════════════════
// 4. AUTH GATE
// ═══════════════════════════════════════════════
$isLoggedIn = !empty($_SESSION['user_id']) && !empty($_SESSION['user_role']);

if (!$isLoggedIn && $page !== 'login') {
    header('Location: index.php?page=login');
    exit;
}

// ═══════════════════════════════════════════════
// 5. LOGIN HANDLER (POST)
// ═══════════════════════════════════════════════
$loginError = '';
if ($page === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $csrf     = $_POST['csrf_token']    ?? '';

    if ($csrf !== $_SESSION['csrf_token']) {
        $loginError = 'Security token mismatch. Please refresh and try again.';
    } else {
        // Call api.php for credential check
        $apiUrl = 'http://localhost' . dirname($_SERVER['SCRIPT_NAME']) . '/api.php';
        $postData = http_build_query(['action'=>'login','email'=>$email,'password'=>$password]);
        $ctx = stream_context_create(['http'=>['method'=>'POST','header'=>'Content-Type: application/x-www-form-urlencoded','content'=>$postData]]);
        $resp = @file_get_contents($apiUrl, false, $ctx);
        $result = $resp ? json_decode($resp, true) : null;

        if ($result && $result['success']) {
            $_SESSION['user_id']     = $result['data']['id'];
            $_SESSION['user_name']   = $result['data']['name'];
            $_SESSION['user_email']  = $result['data']['email'];
            $_SESSION['user_role']   = $result['data']['role'];
            $_SESSION['user_avatar'] = $result['data']['avatar'] ?? '';
            $_SESSION['login_time']  = time();
            header('Location: index.php?page=dashboard');
            exit;
        } else {
            $loginError = $result['message'] ?? 'Invalid email or password.';
        }
    }
}

// ═══════════════════════════════════════════════
// 6. THEME HELPER
// ═══════════════════════════════════════════════
$activeThemeName = $CFG['theme']['active'] ?? 'corporate_blue';
$theme = $CFG['theme']['themes'][$activeThemeName] ?? $CFG['theme']['themes']['corporate_blue'];

function cssVar(string $key, $theme): string {
    return $theme[$key] ?? '#333333';
}

// ═══════════════════════════════════════════════
// 7. NAVIGATION / MODULE MAP
// ═══════════════════════════════════════════════
$navGroups = [
    'MAIN' => [
        ['id'=>'dashboard',   'label'=>'Dashboard',   'icon'=>'grid',       'page'=>'dashboard'],
    ],
    'GOVERNANCE' => [
        ['id'=>'resolutions', 'label'=>'Resolutions', 'icon'=>'file-text',  'page'=>'resolutions'],
        ['id'=>'directors',   'label'=>'Directors',   'icon'=>'users',      'page'=>'directors'],
        ['id'=>'approvals',   'label'=>'Approvals',   'icon'=>'check-square','page'=>'approvals'],
    ],
    'DOCUMENTS' => [
        ['id'=>'documents',   'label'=>'Documents',   'icon'=>'folder',     'page'=>'documents'],
        ['id'=>'files',       'label'=>'Files',       'icon'=>'paperclip',  'page'=>'files'],
    ],
    'ADMIN' => [
        ['id'=>'users',       'label'=>'Users',       'icon'=>'user',       'page'=>'settings_ui&module=users'],
        ['id'=>'settings',    'label'=>'Settings',    'icon'=>'settings',   'page'=>'settings_ui'],
        ['id'=>'audit',       'label'=>'Audit Log',   'icon'=>'activity',   'page'=>'settings_ui&module=audit'],
    ],
];

// Ribbon tab definitions per page
$ribbonTabs = [
    'dashboard'   => ['Home','View','Tools'],
    'resolutions' => ['Home','Resolution','Approval','Directors','Review','Export'],
    'directors'   => ['Home','Directors','Board','Committees','Export'],
    'approvals'   => ['Home','Pending','History'],
    'documents'   => ['Home','Insert','Layout','Format','Tools','Review','Export'],
    'settings_ui' => ['General','Users','Security','Theme','Database','Notifications'],
];

$currentTabs  = $ribbonTabs[$page] ?? ['Home'];
$activeTab    = $_GET['tab'] ?? $currentTabs[0];
$appName      = $CFG['app']['name'];
$companyShort = $CFG['app']['company_short'];
$userName     = $_SESSION['user_name']  ?? 'Guest';
$userRole     = $_SESSION['user_role']  ?? 'viewer';
$userRoleLabel= $CFG['roles'][$userRole]['label'] ?? ucfirst($userRole);

// ═══════════════════════════════════════════════
// 8. RENDER LOGIN PAGE
// ═══════════════════════════════════════════════
if ($page === 'login'):
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Sign In — <?= htmlspecialchars($CFG['app']['name']) ?></title>
<style>
  :root{
    --primary:<?= cssVar('primary',$theme)?>;
    --primary-light:<?= cssVar('primary_light',$theme)?>;
    --accent:<?= cssVar('accent',$theme)?>;
    --bg:<?= cssVar('bg_body',$theme)?>;
    --card:<?= cssVar('bg_card',$theme)?>;
    --text:<?= cssVar('text_primary',$theme)?>;
    --text2:<?= cssVar('text_secondary',$theme)?>;
    --border:<?= cssVar('border_color',$theme)?>;
    --danger:<?= cssVar('danger',$theme)?>;
    --font:<?= cssVar('font_ui',$theme)?>;
  }
  *{margin:0;padding:0;box-sizing:border-box;}
  body{
    min-height:100vh;display:flex;align-items:center;justify-content:center;
    background:var(--primary);
    background-image: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    font-family:var(--font);
  }
  .login-wrap{
    display:flex;width:900px;min-height:540px;border-radius:16px;
    overflow:hidden;box-shadow:0 25px 80px rgba(0,0,0,0.4);
  }
  .login-brand{
    flex:0 0 380px;
    background:linear-gradient(160deg, rgba(255,255,255,0.08) 0%, rgba(255,255,255,0.02) 100%);
    border-right:1px solid rgba(255,255,255,0.1);
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    padding:48px 40px;color:#fff;text-align:center;
  }
  .brand-icon{
    width:80px;height:80px;border-radius:20px;
    background:rgba(255,255,255,0.15);
    display:flex;align-items:center;justify-content:center;
    margin-bottom:24px;font-size:36px;border:2px solid rgba(255,255,255,0.2);
  }
  .brand-name{font-size:32px;font-weight:700;letter-spacing:-0.5px;margin-bottom:8px;}
  .brand-tagline{font-size:13px;opacity:0.7;line-height:1.6;margin-bottom:32px;}
  .brand-company{
    background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);
    border-radius:10px;padding:16px 20px;text-align:center;width:100%;
  }
  .brand-company .co-label{font-size:10px;opacity:0.6;letter-spacing:1.5px;text-transform:uppercase;}
  .brand-company .co-name{font-size:15px;font-weight:600;margin-top:4px;}
  .login-form-side{
    flex:1;background:var(--card);
    display:flex;flex-direction:column;justify-content:center;padding:52px 48px;
  }
  .form-title{font-size:24px;font-weight:700;color:var(--text);margin-bottom:6px;}
  .form-sub{font-size:13px;color:var(--text2);margin-bottom:36px;}
  .field{margin-bottom:20px;}
  .field label{display:block;font-size:12px;font-weight:600;color:var(--text2);
    text-transform:uppercase;letter-spacing:0.6px;margin-bottom:6px;}
  .field input{
    width:100%;padding:12px 16px;border:1.5px solid var(--border);border-radius:8px;
    font-size:14px;font-family:var(--font);color:var(--text);background:var(--bg);
    outline:none;transition:border-color .2s,box-shadow .2s;
  }
  .field input:focus{border-color:var(--primary-light);box-shadow:0 0 0 3px rgba(37,99,235,0.15);}
  .btn-login{
    width:100%;padding:13px;background:var(--primary);color:#fff;
    border:none;border-radius:8px;font-size:15px;font-weight:600;
    cursor:pointer;transition:background .2s,transform .1s;margin-top:8px;
    font-family:var(--font);letter-spacing:0.3px;
  }
  .btn-login:hover{background:var(--primary-light);}
  .btn-login:active{transform:scale(.99);}
  .error-box{
    background:#fef2f2;border:1px solid #fecaca;border-radius:8px;
    padding:12px 16px;color:var(--danger);font-size:13px;margin-bottom:20px;
    display:flex;align-items:center;gap:8px;
  }
  .login-footer{
    margin-top:32px;font-size:11px;color:var(--text2);text-align:center;
    border-top:1px solid var(--border);padding-top:20px;
  }
  .msg-success{
    background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;
    padding:12px 16px;color:#15803d;font-size:13px;margin-bottom:20px;
  }
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-brand">
    <div class="brand-icon">🏢</div>
    <div class="brand-name"><?= htmlspecialchars($appName) ?></div>
    <div class="brand-tagline"><?= htmlspecialchars($CFG['app']['tagline']) ?></div>
    <div class="brand-company">
      <div class="co-label">Powered for</div>
      <div class="co-name"><?= htmlspecialchars($CFG['app']['company_name']) ?></div>
    </div>
  </div>
  <div class="login-form-side">
    <div class="form-title">Welcome back</div>
    <div class="form-sub">Sign in to your <?= htmlspecialchars($appName) ?> account</div>

    <?php if ($loginError): ?>
      <div class="error-box">⚠ <?= htmlspecialchars($loginError) ?></div>
    <?php endif; ?>

    <?php if (($_GET['msg'] ?? '') === 'logged_out'): ?>
      <div class="msg-success">✓ You have been signed out successfully.</div>
    <?php endif; ?>

    <form method="POST" action="index.php?page=login" autocomplete="on">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"/>
      <div class="field">
        <label>Email Address</label>
        <input type="email" name="email" required placeholder="admin@acmecorp.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"/>
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" required placeholder="••••••••"/>
      </div>
      <button type="submit" class="btn-login">Sign In →</button>
    </form>

    <div class="login-footer">
      <?= htmlspecialchars($appName) ?> v<?= $CFG['app']['version'] ?> &nbsp;·&nbsp;
      <?= htmlspecialchars($CFG['app']['company_name']) ?> &nbsp;·&nbsp;
      <?= date('Y') ?>
    </div>
  </div>
</div>
</body>
</html>
<?php
exit;
endif; // end login page

// ═══════════════════════════════════════════════
// 9. INCLUDE MODULE CONTENT
// ═══════════════════════════════════════════════
ob_start();
switch ($page) {
    case 'resolutions':
        include_once ROOT . '/resolutions.php';
        break;
    case 'documents':
    case 'files':
        include_once ROOT . '/documents.php';
        break;
    case 'directors':
    case 'approvals':
    case 'users':
    case 'settings_ui':
        include_once ROOT . '/settings_ui.php';
        break;
    default: // dashboard
        renderDashboard($CFG, $userRole);
        break;
}
$moduleContent = ob_get_clean();

// ═══════════════════════════════════════════════
// DASHBOARD INLINE RENDER
// ═══════════════════════════════════════════════
function renderDashboard(array $cfg, string $role): void {
    $stats = [
        ['label'=>'Total Resolutions','value'=>'—','icon'=>'📋','color'=>'#2563eb','id'=>'stat_res'],
        ['label'=>'Pending Approvals','value'=>'—','icon'=>'⏳','color'=>'#f59e0b','id'=>'stat_pen'],
        ['label'=>'Active Directors','value'=>'—','icon'=>'👤','color'=>'#10b981','id'=>'stat_dir'],
        ['label'=>'Total Documents',  'value'=>'—','icon'=>'📁','color'=>'#7c3aed','id'=>'stat_doc'],
    ];
    echo '<div class="dash-wrap">';
    echo '<div class="dash-stats">';
    foreach ($stats as $s) {
        echo '<div class="stat-card" data-stat-id="' . $s['id'] . '">';
        echo '<div class="stat-icon" style="background:' . $s['color'] . '22;color:' . $s['color'] . '">' . $s['icon'] . '</div>';
        echo '<div><div class="stat-val" id="' . $s['id'] . '">' . $s['value'] . '</div>';
        echo '<div class="stat-label">' . htmlspecialchars($s['label']) . '</div></div></div>';
    }
    echo '</div>';

    echo '<div class="dash-row">';
    // Recent resolutions
    echo '<div class="dash-card" style="flex:2">';
    echo '<div class="dash-card-header"><span>Recent Resolutions</span><a href="index.php?page=resolutions" class="dash-link">View All →</a></div>';
    echo '<div id="dash_resolutions"><div class="loading-row">Loading…</div></div>';
    echo '</div>';
    // Quick actions
    echo '<div class="dash-card" style="flex:1">';
    echo '<div class="dash-card-header"><span>Quick Actions</span></div>';
    echo '<div class="quick-actions">';
    $actions = [
        ['label'=>'New Resolution','href'=>'index.php?page=resolutions&action=new','icon'=>'📋','color'=>'#2563eb'],
        ['label'=>'Upload Document','href'=>'index.php?page=documents&action=upload','icon'=>'📤','color'=>'#10b981'],
        ['label'=>'Manage Directors','href'=>'index.php?page=directors','icon'=>'👥','color'=>'#7c3aed'],
        ['label'=>'View Audit Log','href'=>'index.php?page=settings_ui&module=audit','icon'=>'🔍','color'=>'#f59e0b'],
    ];
    foreach ($actions as $a) {
        echo '<a href="' . $a['href'] . '" class="qa-btn" style="border-left:4px solid ' . $a['color'] . '">';
        echo '<span>' . $a['icon'] . '</span><span>' . $a['label'] . '</span>';
        echo '</a>';
    }
    echo '</div></div>';
    echo '</div>'; // dash-row

    // Pending approvals
    echo '<div class="dash-card" style="margin-top:20px">';
    echo '<div class="dash-card-header"><span>My Pending Approvals</span><a href="index.php?page=approvals" class="dash-link">View All →</a></div>';
    echo '<div id="dash_approvals"><div class="loading-row">Loading…</div></div>';
    echo '</div>';
    echo '</div>'; // dash-wrap
}

// ═══════════════════════════════════════════════
// 10. FULL LAYOUT RENDER
// ═══════════════════════════════════════════════
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= htmlspecialchars(ucfirst($page)) ?> — <?= htmlspecialchars($appName) ?></title>
<style>
/* ────────────────── CSS VARIABLES ────────────────── */
:root{
  --primary:<?= cssVar('primary',$theme)?>;
  --primary-light:<?= cssVar('primary_light',$theme)?>;
  --secondary:<?= cssVar('secondary',$theme)?>;
  --accent:<?= cssVar('accent',$theme)?>;
  --success:<?= cssVar('success',$theme)?>;
  --danger:<?= cssVar('danger',$theme)?>;
  --warning:<?= cssVar('warning',$theme)?>;
  --info:<?= cssVar('info',$theme)?>;
  --bg-body:<?= cssVar('bg_body',$theme)?>;
  --bg-sidebar:<?= cssVar('bg_sidebar',$theme)?>;
  --bg-topbar:<?= cssVar('bg_topbar',$theme)?>;
  --bg-card:<?= cssVar('bg_card',$theme)?>;
  --text:<?= cssVar('text_primary',$theme)?>;
  --text2:<?= cssVar('text_secondary',$theme)?>;
  --text-sidebar:<?= cssVar('text_sidebar',$theme)?>;
  --border:<?= cssVar('border_color',$theme)?>;
  --ribbon-bg:<?= cssVar('ribbon_bg',$theme)?>;
  --ribbon-border:<?= cssVar('ribbon_border',$theme)?>;
  --tab-active-bg:<?= cssVar('tab_active_bg',$theme)?>;
  --tab-active-border:<?= cssVar('tab_active_border',$theme)?>;
  --font:<?= cssVar('font_ui',$theme)?>;
  --sidebar-w:<?= $CFG['ui']['sidebar_width'] ?>px;
  --topbar-h:<?= $CFG['ui']['topbar_height'] ?>px;
  --ribbon-h:120px;
  --status-h:26px;
}
/* ────────────────── RESET & BASE ────────────────── */
*{margin:0;padding:0;box-sizing:border-box;}
html,body{height:100%;overflow:hidden;}
body{font-family:var(--font);background:var(--bg-body);color:var(--text);font-size:13px;}
a{color:inherit;text-decoration:none;}
button,input,select,textarea{font-family:var(--font);}

/* ────────────────── LAYOUT SHELL ────────────────── */
.app-shell{display:flex;height:100vh;overflow:hidden;}
.sidebar{
  width:var(--sidebar-w);flex-shrink:0;
  background:var(--bg-sidebar);display:flex;flex-direction:column;
  border-right:1px solid rgba(255,255,255,0.08);overflow:hidden;
  transition:width .25s ease;z-index:100;
}
.sidebar.collapsed{width:56px;}
.main-area{flex:1;display:flex;flex-direction:column;overflow:hidden;min-width:0;}

/* ────────────────── TOPBAR ────────────────── */
.topbar{
  height:var(--topbar-h);flex-shrink:0;background:var(--bg-topbar);
  border-bottom:1px solid var(--border);display:flex;align-items:center;
  padding:0 16px;gap:8px;z-index:50;
}
.topbar-title{
  font-size:18px;font-weight:700;color:var(--primary);
  display:flex;align-items:center;gap:8px;
}
.topbar-title .app-icon{font-size:22px;}
.topbar-spacer{flex:1;}
.topbar-breadcrumb{
  font-size:12px;color:var(--text2);display:flex;align-items:center;gap:4px;
}
.topbar-breadcrumb span{color:var(--text2);}
.topbar-breadcrumb .sep{opacity:0.4;}
.topbar-user{
  display:flex;align-items:center;gap:10px;cursor:pointer;position:relative;
  padding:6px 12px;border-radius:8px;transition:background .15s;
}
.topbar-user:hover{background:var(--bg-body);}
.user-avatar{
  width:32px;height:32px;border-radius:50%;background:var(--primary);
  color:#fff;display:flex;align-items:center;justify-content:center;
  font-weight:700;font-size:13px;
}
.user-info{text-align:right;}
.user-name-text{font-weight:600;font-size:13px;}
.user-role-text{font-size:11px;color:var(--text2);}
.user-dropdown{
  position:absolute;top:calc(100% + 4px);right:0;
  background:var(--bg-card);border:1px solid var(--border);
  border-radius:10px;padding:6px;min-width:180px;
  box-shadow:0 8px 30px rgba(0,0,0,0.15);display:none;z-index:200;
}
.user-dropdown.open{display:block;}
.user-dropdown a{
  display:flex;align-items:center;gap:8px;padding:8px 12px;
  border-radius:6px;font-size:13px;color:var(--text);transition:background .1s;
}
.user-dropdown a:hover{background:var(--bg-body);}
.topbar-btn{
  height:34px;padding:0 12px;border:1px solid var(--border);border-radius:6px;
  background:var(--bg-card);color:var(--text);cursor:pointer;
  display:flex;align-items:center;gap:6px;font-size:12px;transition:background .15s;
}
.topbar-btn:hover{background:var(--bg-body);}
.notif-badge{
  background:var(--danger);color:#fff;border-radius:50%;
  width:18px;height:18px;font-size:10px;font-weight:700;
  display:flex;align-items:center;justify-content:center;margin-left:2px;
}

/* ────────────────── RIBBON ────────────────── */
.ribbon-area{
  background:var(--ribbon-bg);border-bottom:1px solid var(--ribbon-border);
  flex-shrink:0;
}
.ribbon-tabs{
  display:flex;border-bottom:1px solid var(--ribbon-border);
  background:var(--bg-topbar);padding:0 8px;
}
.ribbon-tab{
  padding:0 16px;height:36px;display:flex;align-items:center;
  font-size:12px;font-weight:600;cursor:pointer;
  color:var(--text2);letter-spacing:0.3px;text-transform:uppercase;
  border-bottom:3px solid transparent;transition:color .15s,border-color .15s;
  white-space:nowrap;
}
.ribbon-tab:hover{color:var(--primary-light);}
.ribbon-tab.active{
  color:var(--primary-light);
  border-bottom-color:var(--tab-active-border);
  background:var(--tab-active-bg);
}
.ribbon-panels{padding:8px 12px;}
.ribbon-panel{display:none;gap:4px;}
.ribbon-panel.active{display:flex;flex-wrap:wrap;align-items:flex-start;}
.ribbon-group{
  display:flex;flex-direction:column;align-items:stretch;
  border-right:1px solid var(--ribbon-border);padding:0 12px 0 0;margin-right:8px;
}
.ribbon-group:last-child{border-right:none;}
.ribbon-group-label{
  font-size:10px;color:var(--text2);text-align:center;margin-top:4px;
  letter-spacing:0.5px;text-transform:uppercase;
}
.ribbon-row{display:flex;gap:2px;margin-bottom:2px;}
.r-btn{
  min-width:32px;height:32px;padding:0 8px;
  background:transparent;border:1px solid transparent;
  border-radius:5px;cursor:pointer;color:var(--text);
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  gap:1px;transition:background .12s,border-color .12s;font-size:11px;
  white-space:nowrap;
}
.r-btn:hover{background:rgba(0,0,0,0.06);border-color:var(--border);}
.r-btn:active{background:rgba(0,0,0,0.12);}
.r-btn.large{height:56px;min-width:48px;flex-direction:column;gap:3px;font-size:10px;}
.r-btn.large .r-icon{font-size:22px;}
.r-btn.disabled{opacity:.4;cursor:not-allowed;}
.r-btn.active-state{background:var(--primary-light)!important;color:#fff!important;border-color:var(--primary)!important;}
.r-icon{font-size:14px;line-height:1;}
.r-label{font-size:10px;color:inherit;line-height:1;}
.r-sep{width:1px;background:var(--ribbon-border);margin:0 4px;align-self:stretch;}
.r-select{
  height:28px;padding:0 6px;border:1px solid var(--border);border-radius:4px;
  background:var(--bg-card);color:var(--text);font-size:11px;cursor:pointer;
  min-width:80px;
}

/* ────────────────── SIDEBAR ────────────────── */
.sidebar-header{
  height:var(--topbar-h);display:flex;align-items:center;padding:0 16px;
  border-bottom:1px solid rgba(255,255,255,0.08);gap:10px;flex-shrink:0;
}
.sidebar-logo{font-size:20px;}
.sidebar-app-name{
  font-size:15px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;
}
.sidebar-toggle{
  margin-left:auto;background:none;border:none;color:rgba(255,255,255,0.5);
  cursor:pointer;font-size:18px;padding:4px;border-radius:4px;
  transition:color .15s;
}
.sidebar-toggle:hover{color:#fff;}
.sidebar-nav{flex:1;overflow-y:auto;overflow-x:hidden;padding:10px 0;}
.sidebar-nav::-webkit-scrollbar{width:4px;}
.sidebar-nav::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.15);border-radius:2px;}
.nav-group-label{
  padding:12px 16px 4px;font-size:10px;font-weight:600;
  color:rgba(255,255,255,0.35);letter-spacing:1.2px;text-transform:uppercase;
  white-space:nowrap;overflow:hidden;
}
.sidebar.collapsed .nav-group-label{opacity:0;}
.nav-item{
  display:flex;align-items:center;gap:10px;padding:9px 16px;
  color:var(--text-sidebar);cursor:pointer;border-left:3px solid transparent;
  transition:all .15s;text-decoration:none;white-space:nowrap;overflow:hidden;
  border-radius:0 6px 6px 0;margin:1px 8px 1px 0;
}
.nav-item:hover{
  background:rgba(255,255,255,0.08);color:#fff;
}
.nav-item.active{
  background:rgba(255,255,255,0.12);color:#fff;
  border-left-color:var(--accent);
}
.nav-icon{font-size:16px;flex-shrink:0;}
.nav-label{font-size:13px;overflow:hidden;text-overflow:ellipsis;}
.sidebar.collapsed .nav-label{display:none;}
.sidebar-footer{
  padding:12px 16px;border-top:1px solid rgba(255,255,255,0.08);
  font-size:11px;color:rgba(255,255,255,0.3);white-space:nowrap;overflow:hidden;
}
.sidebar.collapsed .sidebar-footer{padding:12px 8px;}

/* ────────────────── CONTENT ────────────────── */
.content-area{
  flex:1;overflow-y:auto;overflow-x:hidden;padding:20px;
  background:var(--bg-body);
}
.content-area::-webkit-scrollbar{width:6px;}
.content-area::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px;}

/* ────────────────── STATUS BAR ────────────────── */
.status-bar{
  height:var(--status-h);background:var(--primary);color:rgba(255,255,255,0.7);
  display:flex;align-items:center;padding:0 16px;gap:16px;font-size:11px;
  flex-shrink:0;border-top:1px solid rgba(255,255,255,0.1);
}
.status-item{display:flex;align-items:center;gap:4px;}
.status-sep{width:1px;height:12px;background:rgba(255,255,255,0.2);}
.status-bar .status-right{margin-left:auto;display:flex;gap:16px;align-items:center;}

/* ────────────────── DASHBOARD ────────────────── */
.dash-wrap{max-width:1200px;}
.dash-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px;}
.stat-card{
  background:var(--bg-card);border-radius:12px;padding:20px;
  display:flex;align-items:center;gap:16px;
  border:1px solid var(--border);transition:box-shadow .2s;
}
.stat-card:hover{box-shadow:0 4px 20px rgba(0,0,0,0.08);}
.stat-icon{width:48px;height:48px;border-radius:10px;font-size:22px;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.stat-val{font-size:28px;font-weight:700;color:var(--text);}
.stat-label{font-size:12px;color:var(--text2);margin-top:2px;}
.dash-row{display:flex;gap:16px;margin-bottom:16px;}
.dash-card{
  background:var(--bg-card);border-radius:12px;border:1px solid var(--border);
  overflow:hidden;
}
.dash-card-header{
  display:flex;align-items:center;justify-content:space-between;
  padding:14px 18px;border-bottom:1px solid var(--border);
  font-weight:600;font-size:13px;
}
.dash-link{font-size:12px;color:var(--primary-light);font-weight:500;}
.loading-row{padding:20px;color:var(--text2);text-align:center;}
.quick-actions{padding:12px;}
.qa-btn{
  display:flex;align-items:center;gap:10px;padding:10px 12px;
  border-radius:6px;margin-bottom:4px;transition:background .15s;
  font-size:13px;
}
.qa-btn:hover{background:var(--bg-body);}

/* ────────────────── TOAST ────────────────── */
.toast-container{
  position:fixed;bottom:40px;right:20px;z-index:9999;
  display:flex;flex-direction:column;gap:8px;
}
.toast{
  background:var(--bg-card);border:1px solid var(--border);border-radius:8px;
  padding:12px 16px;box-shadow:0 4px 20px rgba(0,0,0,0.15);
  display:flex;align-items:center;gap:10px;min-width:280px;max-width:380px;
  animation:toastIn .25s ease;font-size:13px;
}
.toast.success{border-left:4px solid var(--success);}
.toast.error  {border-left:4px solid var(--danger);}
.toast.info   {border-left:4px solid var(--info);}
.toast.warning{border-left:4px solid var(--warning);}
@keyframes toastIn{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:translateX(0)}}

/* ────────────────── MODAL ────────────────── */
.modal-overlay{
  position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;
  display:none;align-items:center;justify-content:center;backdrop-filter:blur(2px);
}
.modal-overlay.open{display:flex;}
.modal{
  background:var(--bg-card);border-radius:14px;padding:28px;
  width:520px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,0.3);
  position:relative;
}
.modal-title{font-size:18px;font-weight:700;margin-bottom:6px;}
.modal-sub{font-size:13px;color:var(--text2);margin-bottom:20px;}
.modal-close{
  position:absolute;top:14px;right:14px;background:none;border:none;
  font-size:18px;cursor:pointer;color:var(--text2);border-radius:4px;padding:4px;
  transition:background .1s;
}
.modal-close:hover{background:var(--bg-body);}
.modal-footer{display:flex;justify-content:flex-end;gap:8px;margin-top:20px;padding-top:16px;border-top:1px solid var(--border);}
.btn{
  height:34px;padding:0 16px;border-radius:6px;font-size:13px;font-weight:500;
  cursor:pointer;border:1px solid var(--border);background:var(--bg-card);
  color:var(--text);transition:background .15s;display:inline-flex;align-items:center;gap:6px;
}
.btn:hover{background:var(--bg-body);}
.btn.primary{background:var(--primary);color:#fff;border-color:var(--primary);}
.btn.primary:hover{background:var(--primary-light);}
.btn.danger{background:var(--danger);color:#fff;border-color:var(--danger);}
.btn.success{background:var(--success);color:#fff;border-color:var(--success);}

/* ────────────────── FORM CONTROLS ────────────────── */
.form-group{margin-bottom:16px;}
.form-group label{display:block;font-size:12px;font-weight:600;color:var(--text2);
  text-transform:uppercase;letter-spacing:0.5px;margin-bottom:5px;}
.form-control{
  width:100%;height:36px;padding:0 10px;border:1.5px solid var(--border);
  border-radius:6px;font-size:13px;background:var(--bg-body);color:var(--text);
  outline:none;transition:border-color .15s,box-shadow .15s;
}
.form-control:focus{border-color:var(--primary-light);box-shadow:0 0 0 3px rgba(37,99,235,0.1);}
textarea.form-control{height:auto;padding:8px 10px;resize:vertical;}

/* ────────────────── TABLES ────────────────── */
.data-table-wrap{overflow-x:auto;border-radius:10px;border:1px solid var(--border);}
.data-table{width:100%;border-collapse:collapse;}
.data-table th{
  background:var(--bg-body);padding:10px 14px;text-align:left;
  font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;
  color:var(--text2);border-bottom:1px solid var(--border);white-space:nowrap;
}
.data-table td{
  padding:10px 14px;border-bottom:1px solid var(--border);
  font-size:13px;vertical-align:middle;
}
.data-table tr:last-child td{border-bottom:none;}
.data-table tr:hover td{background:rgba(0,0,0,0.02);}

/* ────────────────── BADGES ────────────────── */
.badge{
  display:inline-flex;align-items:center;gap:4px;padding:2px 8px;
  border-radius:99px;font-size:11px;font-weight:600;white-space:nowrap;
}
.badge-draft    {background:#f1f5f9;color:#64748b;}
.badge-pending  {background:#fffbeb;color:#92400e;}
.badge-approved {background:#f0fdf4;color:#15803d;}
.badge-rejected {background:#fef2f2;color:#991b1b;}
.badge-archived {background:#f8fafc;color:#94a3b8;}

/* ────────────────── MISC ────────────────── */
.page-header{display:flex;align-items:center;gap:12px;margin-bottom:20px;}
.page-title{font-size:22px;font-weight:700;}
.page-sub{font-size:13px;color:var(--text2);margin-top:2px;}
.card{background:var(--bg-card);border-radius:12px;border:1px solid var(--border);padding:20px;margin-bottom:16px;}
.empty-state{text-align:center;padding:60px 20px;color:var(--text2);}
.empty-state .empty-icon{font-size:48px;margin-bottom:12px;}
.empty-state .empty-msg{font-size:14px;}

/* ────────────────── RESPONSIVE ────────────────── */
@media(max-width:768px){
  .dash-stats{grid-template-columns:repeat(2,1fr);}
  .sidebar{width:56px;}
  .sidebar .nav-label,.sidebar .sidebar-app-name,.sidebar .nav-group-label{display:none;}
}
</style>
</head>
<body>
<div class="app-shell" id="appShell">

<!-- ══════════ SIDEBAR ══════════ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <span class="sidebar-logo">🏢</span>
    <span class="sidebar-app-name"><?= htmlspecialchars($appName) ?></span>
    <button class="sidebar-toggle" onclick="toggleSidebar()" title="Toggle Sidebar">☰</button>
  </div>
  <nav class="sidebar-nav">
    <?php foreach ($navGroups as $groupName => $items): ?>
      <div class="nav-group-label"><?= $groupName ?></div>
      <?php foreach ($items as $item): ?>
        <?php $isActive = ($page === $item['id']); ?>
        <a class="nav-item <?= $isActive ? 'active' : '' ?>"
           href="index.php?page=<?= urlencode($item['page']) ?>"
           title="<?= htmlspecialchars($item['label']) ?>">
          <span class="nav-icon"><?= getIcon($item['icon']) ?></span>
          <span class="nav-label"><?= htmlspecialchars($item['label']) ?></span>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <small>v<?= $CFG['app']['version'] ?></small>
  </div>
</aside>

<!-- ══════════ MAIN AREA ══════════ -->
<div class="main-area">

  <!-- TOPBAR -->
  <div class="topbar">
    <div class="topbar-title">
      <span class="app-icon">🏢</span>
      <span><?= htmlspecialchars($companyShort) ?></span>
    </div>
    <div class="topbar-breadcrumb" style="margin-left:20px;">
      <span><?= htmlspecialchars($appName) ?></span>
      <span class="sep">›</span>
      <span style="color:var(--text)"><?= htmlspecialchars(ucfirst($page)) ?></span>
    </div>
    <div class="topbar-spacer"></div>

    <!-- Save/Undo/Redo quick btns -->
    <?php if (in_array($page, ['resolutions','documents'])): ?>
    <button class="topbar-btn" onclick="cos.undo()" title="Undo (Ctrl+Z)" id="btn_undo">
      ↩ <span>Undo</span>
    </button>
    <button class="topbar-btn" onclick="cos.redo()" title="Redo (Ctrl+Y)" id="btn_redo">
      ↪ <span>Redo</span>
    </button>
    <button class="topbar-btn primary" onclick="cos.save()" title="Save (Ctrl+S)" id="btn_save"
      style="background:var(--primary);color:#fff;border-color:var(--primary);">
      💾 <span>Save</span>
    </button>
    <?php endif; ?>

    <button class="topbar-btn" id="notifBtn" onclick="toggleNotifications()">
      🔔
      <span class="notif-badge" id="notifCount" style="display:none">0</span>
    </button>

    <div class="topbar-user" id="userMenuTrigger" onclick="toggleUserMenu()">
      <div class="user-info">
        <div class="user-name-text"><?= htmlspecialchars($userName) ?></div>
        <div class="user-role-text"><?= htmlspecialchars($userRoleLabel) ?></div>
      </div>
      <div class="user-avatar"><?= strtoupper(substr($userName,0,1)) ?></div>
      <div class="user-dropdown" id="userDropdown">
        <a href="index.php?page=settings_ui&module=profile">👤 My Profile</a>
        <a href="index.php?page=settings_ui">⚙️ Settings</a>
        <a href="index.php?page=logout">🚪 Sign Out</a>
      </div>
    </div>
  </div>

  <!-- RIBBON -->
  <div class="ribbon-area" id="ribbonArea">
    <div class="ribbon-tabs" id="ribbonTabs">
      <?php foreach ($currentTabs as $tab): ?>
        <div class="ribbon-tab <?= $tab === $activeTab ? 'active' : '' ?>"
             onclick="switchRibbonTab('<?= $tab ?>')"
             data-tab="<?= $tab ?>">
          <?= htmlspecialchars($tab) ?>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="ribbon-panels" id="ribbonPanels">
      <?= buildRibbonPanel($page, $activeTab, $currentTabs, $CFG, $userRole) ?>
    </div>
  </div>

  <!-- CONTENT -->
  <div class="content-area" id="contentArea">
    <?= $moduleContent ?>
  </div>

  <!-- STATUS BAR -->
  <div class="status-bar">
    <div class="status-item">🟢 Ready</div>
    <div class="status-sep"></div>
    <div class="status-item" id="statusMsg">Welcome, <?= htmlspecialchars($userName) ?></div>
    <div class="status-right">
      <div class="status-item" id="wordCount"></div>
      <div class="status-sep"></div>
      <div class="status-item"><?= date($CFG['app']['date_format']) ?></div>
      <div class="status-sep"></div>
      <div class="status-item" id="liveClock"></div>
      <div class="status-sep"></div>
      <div class="status-item" id="autoSaveStatus">Auto-save: on</div>
    </div>
  </div>
</div>
</div>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toastContainer"></div>

<!-- MODAL -->
<div class="modal-overlay" id="globalModal">
  <div class="modal" id="globalModalContent">
    <button class="modal-close" onclick="closeModal()">✕</button>
    <div id="modalBody"></div>
  </div>
</div>

<script>
// ════════════════════════════════════════════════
// CompanyOS Core JS (cos)
// ════════════════════════════════════════════════
const cos = {
  csrfToken: '<?= $_SESSION['csrf_token'] ?>',
  page: '<?= $page ?>',
  userRole: '<?= $userRole ?>',
  undoStack: [],
  redoStack: [],
  undoLimit: <?= $CFG['resolutions']['undo_stack_limit'] ?>,
  isDirty: false,
  autoSaveTimer: null,
  clipboard: null,
  autoSaveInterval: <?= $CFG['resolutions']['autosave_interval_seconds'] ?> * 1000,

  // ── API call
  async api(action, data={}, method='POST') {
    try {
      const fd = new FormData();
      fd.append('action', action);
      fd.append('csrf_token', this.csrfToken);
      for (const [k,v] of Object.entries(data)) fd.append(k, v);
      const resp = await fetch('api.php', {method, body: method==='POST'?fd:undefined});
      return await resp.json();
    } catch(e) {
      cos.toast('API error: ' + e.message, 'error');
      return {success:false, message:e.message};
    }
  },

  // ── Toast
  toast(msg, type='info', duration=3500) {
    const icons = {success:'✓',error:'✕',warning:'⚠',info:'ℹ'};
    const el = document.createElement('div');
    el.className = 'toast ' + type;
    el.innerHTML = `<span>${icons[type]||'ℹ'}</span><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(el);
    setTimeout(()=>{ el.style.transition='opacity .3s'; el.style.opacity='0';
      setTimeout(()=>el.remove(),300); }, duration);
  },

  // ── Undo/Redo
  pushUndo(state) {
    this.undoStack.push(JSON.stringify(state));
    if (this.undoStack.length > this.undoLimit) this.undoStack.shift();
    this.redoStack = [];
    this.markDirty();
    this.updateUndoRedoBtns();
  },
  undo() {
    if (!this.undoStack.length) { this.toast('Nothing to undo','info'); return; }
    const current = this.getCurrentState();
    if (current) this.redoStack.push(JSON.stringify(current));
    const prev = JSON.parse(this.undoStack.pop());
    this.applyState(prev);
    this.updateUndoRedoBtns();
    this.setStatus('Undo performed');
  },
  redo() {
    if (!this.redoStack.length) { this.toast('Nothing to redo','info'); return; }
    const current = this.getCurrentState();
    if (current) this.undoStack.push(JSON.stringify(current));
    const next = JSON.parse(this.redoStack.pop());
    this.applyState(next);
    this.updateUndoRedoBtns();
    this.setStatus('Redo performed');
  },
  getCurrentState() {
    // Overridden per module
    return window.cosGetState ? window.cosGetState() : null;
  },
  applyState(state) {
    if (window.cosApplyState) window.cosApplyState(state);
  },
  updateUndoRedoBtns() {
    const u = document.getElementById('btn_undo');
    const r = document.getElementById('btn_redo');
    if (u) u.classList.toggle('disabled', !this.undoStack.length);
    if (r) r.classList.toggle('disabled', !this.redoStack.length);
  },

  // ── Dirty / save
  markDirty() {
    this.isDirty = true;
    const s = document.getElementById('btn_save');
    if (s) { s.style.background='var(--warning)'; s.style.borderColor='var(--warning)'; }
    document.getElementById('autoSaveStatus').textContent = 'Unsaved changes';
  },
  async save() {
    if (window.cosSave) {
      const ok = await window.cosSave();
      if (ok) { this.isDirty=false;
        const s=document.getElementById('btn_save');
        if(s){s.style.background='var(--primary)';s.style.borderColor='var(--primary)';}
        document.getElementById('autoSaveStatus').textContent='Saved ✓';
        this.toast('Saved successfully','success');
      }
    }
  },
  startAutoSave() {
    this.autoSaveTimer = setInterval(()=>{
      if(this.isDirty) this.save();
    }, this.autoSaveInterval);
  },

  // ── Cut / Copy / Paste
  cut(target) {
    const el = typeof target==='string' ? document.getElementById(target) : target;
    if (!el) return;
    this.clipboard = el.tagName==='INPUT'||el.tagName==='TEXTAREA' ? el.value : el.innerHTML;
    if(el.tagName==='INPUT'||el.tagName==='TEXTAREA'){el.value='';} else {el.innerHTML='';}
    this.toast('Cut to clipboard','info');
    this.pushUndo(this.getCurrentState());
  },
  copy(target) {
    const el = typeof target==='string' ? document.getElementById(target) : target;
    if (!el) return;
    this.clipboard = el.tagName==='INPUT'||el.tagName==='TEXTAREA' ? el.value : el.innerHTML;
    // Also try system clipboard
    if (navigator.clipboard) navigator.clipboard.writeText(this.clipboard).catch(()=>{});
    this.toast('Copied to clipboard','info');
  },
  paste(target) {
    const el = typeof target==='string' ? document.getElementById(target) : target;
    if (!el || !this.clipboard) return;
    if(el.tagName==='INPUT'||el.tagName==='TEXTAREA'){el.value+=this.clipboard;} else {el.innerHTML+=this.clipboard;}
    this.pushUndo(this.getCurrentState());
    this.toast('Pasted','info');
  },

  // ── Status bar
  setStatus(msg) {
    document.getElementById('statusMsg').textContent = msg;
  },

  // ── Print
  print() { window.print(); },

  // ── Export
  async exportPdf(id) {
    this.toast('Generating PDF…','info');
    const r = await this.api('export_pdf',{id});
    if(r.success && r.data?.url) window.open(r.data.url,'_blank');
    else this.toast(r.message||'Export failed','error');
  },
  async exportExcel(type) {
    this.toast('Exporting…','info');
    const r = await this.api('export_excel',{type});
    if(r.success && r.data?.url) window.open(r.data.url,'_blank');
    else this.toast(r.message||'Export failed','error');
  },

  // ── Dashboard data load
  async loadDashboard() {
    const r = await this.api('dashboard_stats');
    if (r.success) {
      const d = r.data;
      ['stat_res','stat_pen','stat_dir','stat_doc'].forEach((id,i)=>{
        const el = document.getElementById(id);
        if (el) el.textContent = [d.resolutions,d.pending_approvals,d.directors,d.documents][i] ?? '—';
      });
    }
    const rr = await this.api('recent_resolutions',{limit:5});
    const resDom = document.getElementById('dash_resolutions');
    if (resDom && rr.success && rr.data?.length) {
      resDom.innerHTML = `<table class="data-table"><thead><tr>
        <th>Number</th><th>Title</th><th>Type</th><th>Status</th><th>Date</th></tr></thead><tbody>
        ${rr.data.map(r=>`<tr>
          <td><a href="index.php?page=resolutions&action=view&id=${r.id}">${r.number||'—'}</a></td>
          <td>${r.title||'—'}</td>
          <td>${r.type||'—'}</td>
          <td><span class="badge badge-${r.status}">${r.status}</span></td>
          <td>${r.created_at||'—'}</td>
        </tr>`).join('')}</tbody></table>`;
    } else if (resDom) {
      resDom.innerHTML = '<div class="empty-state"><div class="empty-icon">📋</div><div class="empty-msg">No resolutions yet.</div></div>';
    }
    const ra = await this.api('my_pending_approvals');
    const appDom = document.getElementById('dash_approvals');
    if (appDom && ra.success && ra.data?.length) {
      appDom.innerHTML = `<table class="data-table"><thead><tr>
        <th>Resolution</th><th>Submitted By</th><th>Date</th><th>Action</th></tr></thead><tbody>
        ${ra.data.map(a=>`<tr>
          <td>${a.resolution_title||'—'}</td>
          <td>${a.submitted_by||'—'}</td>
          <td>${a.created_at||'—'}</td>
          <td>
            <button class="btn success" onclick="cos.approveResolution(${a.resolution_id})">✓ Approve</button>
            <button class="btn danger"  onclick="cos.rejectResolution(${a.resolution_id})">✕ Reject</button>
          </td>
        </tr>`).join('')}</tbody></table>`;
    } else if (appDom) {
      appDom.innerHTML = '<div class="loading-row">No pending approvals.</div>';
    }
  },

  async approveResolution(id) {
    if (!confirm('Approve this resolution?')) return;
    const r = await this.api('approve_resolution',{id,vote:'approve'});
    this.toast(r.message || (r.success?'Approved':'Failed'), r.success?'success':'error');
    if(r.success) this.loadDashboard();
  },
  async rejectResolution(id) {
    const reason = prompt('Reason for rejection (optional):') || '';
    const r = await this.api('approve_resolution',{id,vote:'reject',reason});
    this.toast(r.message || (r.success?'Rejected':'Failed'), r.success?'success':'error');
    if(r.success) this.loadDashboard();
  },

  // ── Notifications
  async loadNotifications() {
    const r = await this.api('get_notifications',{limit:10});
    const badge = document.getElementById('notifCount');
    if (r.success && r.data) {
      const unread = r.data.filter(n=>!n.read_at).length;
      if (unread > 0) {
        badge.textContent = unread;
        badge.style.display = 'flex';
      } else {
        badge.style.display = 'none';
      }
    }
  }
};

// ════════════════════════════════════════════════
// UI HELPERS
// ════════════════════════════════════════════════
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('collapsed');
}
function toggleUserMenu() {
  document.getElementById('userDropdown').classList.toggle('open');
}
function toggleNotifications() {
  cos.toast('Notification panel coming in settings_ui.php','info');
}
document.addEventListener('click', e => {
  if (!document.getElementById('userMenuTrigger').contains(e.target))
    document.getElementById('userDropdown').classList.remove('open');
});

function switchRibbonTab(tab) {
  document.querySelectorAll('.ribbon-tab').forEach(t=>{
    t.classList.toggle('active', t.dataset.tab===tab);
  });
  document.querySelectorAll('.ribbon-panel').forEach(p=>{
    p.classList.toggle('active', p.dataset.panel===tab);
  });
  // Update URL without reload
  const url = new URL(window.location);
  url.searchParams.set('tab', tab);
  history.replaceState(null,'', url);
}

function openModal(title, bodyHtml, sub='') {
  document.getElementById('modalBody').innerHTML =
    `<div class="modal-title">${title}</div>
     ${sub?`<div class="modal-sub">${sub}</div>`:''}
     ${bodyHtml}`;
  document.getElementById('globalModal').classList.add('open');
}
function closeModal() {
  document.getElementById('globalModal').classList.remove('open');
}
document.getElementById('globalModal').addEventListener('click', e=>{
  if(e.target===e.currentTarget) closeModal();
});

// Live clock
function updateClock() {
  const now = new Date();
  document.getElementById('liveClock').textContent =
    now.toLocaleTimeString('en-PK',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
}
setInterval(updateClock, 1000); updateClock();

// ════════════════════════════════════════════════
// KEYBOARD SHORTCUTS
// ════════════════════════════════════════════════
document.addEventListener('keydown', e => {
  const ctrl = e.ctrlKey || e.metaKey;
  if (!ctrl) return;
  switch (e.key.toLowerCase()) {
    case 's': e.preventDefault(); cos.save(); break;
    case 'z': e.preventDefault(); e.shiftKey ? cos.redo() : cos.undo(); break;
    case 'y': e.preventDefault(); cos.redo(); break;
    case 'p': e.preventDefault(); cos.print(); break;
    case 'n':
      e.preventDefault();
      if(cos.page==='resolutions') window.location='index.php?page=resolutions&action=new';
      if(cos.page==='documents') window.location='index.php?page=documents&action=new';
      break;
  }
});

// ════════════════════════════════════════════════
// INIT
// ════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
  cos.updateUndoRedoBtns();
  cos.startAutoSave();
  cos.loadNotifications();
  if (cos.page === 'dashboard') cos.loadDashboard();
  // Default ribbon tab
  switchRibbonTab('<?= $activeTab ?>');
  cos.setStatus('<?= ucfirst($page) ?> module loaded');
});
</script>
</body>
</html>
<?php

// ═══════════════════════════════════════════════
// RIBBON BUILDER (PHP)
// ═══════════════════════════════════════════════
function buildRibbonPanel(string $page, string $activeTab, array $tabs, array $cfg, string $role): string {
    $html = '';
    foreach ($tabs as $tab) {
        $isActive = ($tab === $activeTab);
        $html .= '<div class="ribbon-panel ' . ($isActive?'active':'') . '" data-panel="' . htmlspecialchars($tab) . '">';
        $html .= getRibbonGroups($page, $tab, $role);
        $html .= '</div>';
    }
    return $html;
}

function getRibbonGroups(string $page, string $tab, string $role): string {
    $g = '';
    // Universal Home tab groups
    if ($tab === 'Home') {
        $g .= ribbonGroup('Clipboard', [
            ribbonBtnLg('📋','Paste','cos.paste(document.activeElement)'),
            ribbonBtnLg('✂️','Cut','cos.cut(document.activeElement)'),
            ribbonBtnLg('📄','Copy','cos.copy(document.activeElement)'),
        ]);
        $g .= ribbonGroup('History', [
            ribbonBtnLg('↩','Undo','cos.undo()','btn_undo_r'),
            ribbonBtnLg('↪','Redo','cos.redo()','btn_redo_r'),
        ]);
        $g .= ribbonGroup('File', [
            ribbonBtnLg('💾','Save','cos.save()'),
            ribbonBtnLg('🖨','Print','cos.print()'),
            ribbonBtnLg('📤','Export','cos.exportPdf(0)'),
        ]);
    }

    if ($page === 'resolutions') {
        if ($tab === 'Resolution') {
            $g .= ribbonGroup('New', [
                ribbonBtnLg('➕','New Res.','window.location=\"index.php?page=resolutions&action=new\"'),
            ]);
            $g .= ribbonGroup('Type', [
                '<select class="r-select" onchange="cosResSetType(this.value)">
                  <option value="ordinary">Ordinary</option>
                  <option value="special">Special</option>
                  <option value="unanimous">Unanimous</option>
                  <option value="circular">Circular</option>
                 </select>',
            ]);
            $g .= ribbonGroup('Status', [
                ribbonBtn('📨','Submit','cosResSubmit()'),
                ribbonBtn('🗂','Archive','cosResArchive()'),
                ribbonBtn('🗑','Delete','cosResDelete()','','danger'),
            ]);
        }
        if ($tab === 'Approval') {
            $g .= ribbonGroup('Vote', [
                ribbonBtnLg('✅','Approve','cosResVote(\"approve\")'),
                ribbonBtnLg('❌','Reject','cosResVote(\"reject\")'),
                ribbonBtnLg('⏸','Abstain','cosResVote(\"abstain\")'),
            ]);
            $g .= ribbonGroup('Workflow', [
                ribbonBtn('👥','View Votes','cosResViewVotes()'),
                ribbonBtn('📩','Remind All','cosResSendReminders()'),
            ]);
        }
        if ($tab === 'Directors') {
            $g .= ribbonGroup('Directors', [
                ribbonBtnLg('➕','Add Dir.','window.location=\"index.php?page=directors&action=new\"'),
                ribbonBtnLg('👥','View All','window.location=\"index.php?page=directors\"'),
            ]);
        }
        if ($tab === 'Review') {
            $g .= ribbonGroup('Proofing', [
                ribbonBtn('🔍','Find','cosFindReplace()'),
                ribbonBtn('📊','Word Count','cosWordCount()'),
            ]);
            $g .= ribbonGroup('Comments', [
                ribbonBtn('💬','Add Comment','cosAddComment()'),
                ribbonBtn('📋','View All','cosViewComments()'),
            ]);
        }
        if ($tab === 'Export') {
            $g .= ribbonGroup('Export', [
                ribbonBtnLg('📄','PDF','cos.exportPdf(0)'),
                ribbonBtnLg('📊','Excel','cos.exportExcel(\"resolutions\")'),
                ribbonBtnLg('🖨','Print','cos.print()'),
            ]);
        }
    }

    if ($page === 'documents') {
        if ($tab === 'Insert') {
            $g .= ribbonGroup('Objects', [
                ribbonBtnLg('🖼','Image','cosDocInsertImage()'),
                ribbonBtnLg('🔗','Link','cosDocInsertLink()'),
                ribbonBtnLg('📊','Table','cosDocInsertTable()'),
            ]);
        }
        if ($tab === 'Format') {
            $g .= ribbonGroup('Text', [
                ribbonBtn('B','Bold','document.execCommand(\"bold\")','',''),
                ribbonBtn('I','Italic','document.execCommand(\"italic\")','',''),
                ribbonBtn('U','Underline','document.execCommand(\"underline\")','',''),
            ]);
            $g .= ribbonGroup('Paragraph', [
                ribbonBtn('≡','Justify','document.execCommand(\"justifyFull\")'),
                ribbonBtn('⬤','Bullets','document.execCommand(\"insertUnorderedList\")'),
                ribbonBtn('#','Numbered','document.execCommand(\"insertOrderedList\")'),
            ]);
        }
        if ($tab === 'Tools') {
            $g .= ribbonGroup('Actions', [
                ribbonBtnLg('📤','Upload','cosDocUpload()'),
                ribbonBtnLg('📂','New Folder','cosDocNewFolder()'),
                ribbonBtnLg('🗑','Recycle Bin','cosDocRecycleBin()'),
            ]);
        }
    }

    if ($page === 'settings_ui') {
        if ($tab === 'Users') {
            $g .= ribbonGroup('Users', [
                ribbonBtnLg('➕','New User','cosAddUser()'),
                ribbonBtnLg('👥','All Users','cosViewUsers()'),
            ]);
        }
        if ($tab === 'Theme') {
            $g .= ribbonGroup('Theme', [
                '<select class="r-select" onchange="cosChangeTheme(this.value)">
                   <option>corporate_blue</option>
                   <option>executive_dark</option>
                   <option>classic_green</option>
                 </select>',
            ]);
        }
    }

    // Directors page
    if ($page === 'directors') {
        if ($tab === 'Directors') {
            $g .= ribbonGroup('Actions', [
                ribbonBtnLg('➕','Add Director','window.location=\"index.php?page=settings_ui&module=directors&action=new\"'),
                ribbonBtnLg('📄','Export','cos.exportExcel(\"directors\")'),
            ]);
        }
    }

    return $g ?: '<div style="padding:8px 4px;color:var(--text2);font-size:12px;">No actions available for this tab.</div>';
}

function ribbonGroup(string $label, array $items): string {
    $h = '<div class="ribbon-group">';
    $h .= '<div class="ribbon-row">';
    foreach ($items as $item) $h .= $item;
    $h .= '</div>';
    $h .= '<div class="ribbon-group-label">' . htmlspecialchars($label) . '</div>';
    $h .= '</div>';
    return $h;
}

function ribbonBtnLg(string $icon, string $label, string $onclick, string $id=''): string {
    $idAttr = $id ? ' id="' . htmlspecialchars($id) . '"' : '';
    return '<button class="r-btn large"' . $idAttr . ' onclick="' . htmlspecialchars($onclick,ENT_QUOTES) . '">'
         . '<span class="r-icon">' . $icon . '</span>'
         . '<span class="r-label">' . htmlspecialchars($label) . '</span>'
         . '</button>';
}

function ribbonBtn(string $icon, string $label, string $onclick, string $id='', string $extra=''): string {
    $idAttr = $id ? ' id="' . htmlspecialchars($id) . '"' : '';
    return '<button class="r-btn ' . $extra . '"' . $idAttr . ' onclick="' . htmlspecialchars($onclick,ENT_QUOTES) . '" title="' . htmlspecialchars($label) . '">'
         . '<span class="r-icon">' . $icon . '</span>'
         . '<span class="r-label">' . htmlspecialchars($label) . '</span>'
         . '</button>';
}

function getIcon(string $name): string {
    $map = [
        'grid'=>'⊞','file-text'=>'📋','users'=>'👥','check-square'=>'✅',
        'folder'=>'📁','paperclip'=>'📎','user'=>'👤','settings'=>'⚙️',
        'activity'=>'📊','shield'=>'🛡','briefcase'=>'💼','eye'=>'👁',
        'user-check'=>'✔️','dollar-sign'=>'💵','mail'=>'📧','clipboard'=>'📄',
        'book'=>'📖',
    ];
    return $map[$name] ?? '•';
}
?>
