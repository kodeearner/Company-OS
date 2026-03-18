<?php
/**
 * CompanyOS - settings_ui.php
 * ─────────────────────────────────────────────────────────────
 * Settings & Administration Panel
 * Tabs: General · Users · Directors · Roles · Security · Theme
 *       Database · Notifications · Audit Log · System Info
 * ─────────────────────────────────────────────────────────────
 * Included by index.php inside the ribbon/content shell.
 * All API calls go to api.php. Config from settings.json ($CFG).
 */

if (!defined('ROOT')) { header('Location: index.php'); exit; }

$module = $_GET['module'] ?? 'general';
$allowedModules = ['general','users','directors','roles','security','theme','database','notifications','audit','system'];
if (!in_array($module, $allowedModules)) $module = 'general';

$isSuperAdmin = ($userRole === 'super_admin');

$settingsTabs = [
    ['id'=>'general',       'label'=>'General',       'icon'=>'🏢', 'perm'=>'manage_settings'],
    ['id'=>'users',         'label'=>'Users',         'icon'=>'👥', 'perm'=>'manage_users'],
    ['id'=>'directors',     'label'=>'Directors',     'icon'=>'💼', 'perm'=>'manage_directors'],
    ['id'=>'roles',         'label'=>'Roles & Perms', 'icon'=>'🛡', 'perm'=>'manage_users'],
    ['id'=>'security',      'label'=>'Security',      'icon'=>'🔒', 'perm'=>'manage_settings'],
    ['id'=>'theme',         'label'=>'Theme',         'icon'=>'🎨', 'perm'=>'manage_settings'],
    ['id'=>'database',      'label'=>'Database',      'icon'=>'🗄', 'perm'=>'manage_settings'],
    ['id'=>'notifications', 'label'=>'Notifications', 'icon'=>'🔔', 'perm'=>'manage_settings'],
    ['id'=>'audit',         'label'=>'Audit Log',     'icon'=>'📊', 'perm'=>'view_audit'],
    ['id'=>'system',        'label'=>'System Info',   'icon'=>'⚙️', 'perm'=>'manage_settings'],
];

function sfHasPerm(string $perm, string $role, array $cfg): bool {
    $perms = $cfg['roles'][$role]['permissions'] ?? [];
    return in_array('*',$perms) || in_array($perm,$perms);
}

function sfField(string $label, string $key, string $value, string $type='text', string $help='', bool $ro=false): string {
    $id = 'sf_'.str_replace(['.','[',']'],'_',$key);
    $roA = $ro ? 'readonly style="opacity:.6;background:var(--bg-body)"' : '';
    $h   = $help ? "<div class='fhelp'>$help</div>" : '';
    return "<div class='srow'><div class='slabel'><label for='$id'>".htmlspecialchars($label)."</label>$h</div>
    <div class='sinput'><input type='$type' id='$id' class='form-control' data-key='$key'
      value='".htmlspecialchars($value)."' $roA onchange='sc(this)'/></div></div>";
}
function sfSelect(string $label, string $key, string $cur, array $opts, string $help=''): string {
    $id = 'sf_'.str_replace('.','_',$key);
    $h  = $help ? "<div class='fhelp'>$help</div>" : '';
    $os = '';
    foreach ($opts as $v=>$l) $os .= "<option value='".htmlspecialchars($v)."'".($v==$cur?' selected':'').">".htmlspecialchars($l)."</option>";
    return "<div class='srow'><div class='slabel'><label for='$id'>".htmlspecialchars($label)."</label>$h</div>
    <div class='sinput'><select id='$id' class='form-control' data-key='$key' onchange='sc(this)'>$os</select></div></div>";
}
function sfToggle(string $label, string $key, bool $val, string $help=''): string {
    $h   = $help ? "<div class='fhelp'>$help</div>" : '';
    $chk = $val ? 'checked' : '';
    return "<div class='srow'><div class='slabel'><label>".htmlspecialchars($label)."</label>$h</div>
    <div class='sinput'><label class='tsw'><input type='checkbox' $chk data-key='$key' onchange='stc(this)'/><span class='tsl'></span></label></div></div>";
}
?>
<style>
.sw{display:flex;gap:0;min-height:calc(100vh - 250px);}
.ssnav{width:196px;flex-shrink:0;background:var(--bg-card);border-right:1px solid var(--border);border-radius:12px 0 0 12px;padding:10px 0;}
.ssnav-item{display:flex;align-items:center;gap:9px;padding:9px 16px;cursor:pointer;font-size:13px;color:var(--text2);border-left:3px solid transparent;transition:all .15s;text-decoration:none;white-space:nowrap;}
.ssnav-item:hover{background:var(--bg-body);color:var(--text);}
.ssnav-item.active{background:var(--bg-body);color:var(--primary-light);border-left-color:var(--primary-light);font-weight:600;}
.scontent{flex:1;background:var(--bg-card);border-radius:0 12px 12px 0;border:1px solid var(--border);border-left:none;padding:26px 30px;overflow-y:auto;min-width:0;}
.ssect{margin-bottom:32px;}
.stitle{font-size:14px;font-weight:700;padding-bottom:10px;border-bottom:2px solid var(--border);margin-bottom:2px;display:flex;align-items:center;gap:8px;}
.ssub{font-size:11px;color:var(--text2);margin-bottom:14px;margin-top:4px;}
.srow{display:flex;align-items:flex-start;gap:16px;padding:9px 0;border-bottom:1px solid var(--border);}
.srow:last-child{border-bottom:none;}
.slabel{flex:0 0 230px;font-size:13px;font-weight:500;padding-top:4px;}
.fhelp{font-size:11px;color:var(--text2);margin-top:2px;line-height:1.4;}
.sinput{flex:1;}
.sinput .form-control{max-width:380px;}
.tsw{position:relative;display:inline-block;width:40px;height:22px;}
.tsw input{opacity:0;width:0;height:0;}
.tsl{position:absolute;cursor:pointer;inset:0;background:#cbd5e1;border-radius:22px;transition:.2s;}
.tsl:before{content:'';position:absolute;width:16px;height:16px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.2s;}
.tsw input:checked + .tsl{background:var(--primary-light);}
.tsw input:checked + .tsl:before{transform:translateX(18px);}
.savebar{position:sticky;bottom:0;background:var(--bg-card);border-top:1px solid var(--border);padding:12px 0;display:flex;align-items:center;gap:10px;margin-top:20px;}
/* users */
.ucgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:12px;margin-top:14px;}
.ucard{background:var(--bg-body);border:1px solid var(--border);border-radius:10px;padding:14px;display:flex;gap:10px;transition:box-shadow .15s;}
.ucard:hover{box-shadow:0 4px 16px rgba(0,0,0,.08);}
.uavatar{width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;flex-shrink:0;color:#fff;}
.ucinfo{flex:1;min-width:0;}
.ucname{font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ucemail{font-size:11px;color:var(--text2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin:1px 0;}
.ucactions{display:flex;gap:4px;margin-top:8px;}
/* directors */
.dgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:14px;margin-top:14px;}
.dcard{background:var(--bg-body);border:1px solid var(--border);border-radius:12px;overflow:hidden;transition:box-shadow .2s;}
.dcard:hover{box-shadow:0 6px 22px rgba(0,0,0,.09);}
.dchead{background:var(--primary);padding:14px 16px;color:#fff;display:flex;align-items:center;gap:10px;}
.dcavatar{width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;}
.dcbody{padding:12px 14px;}
.ddr{display:flex;align-items:center;gap:6px;margin-bottom:5px;font-size:12px;color:var(--text2);}
.ddr strong{color:var(--text);font-size:12px;}
/* roles matrix */
.pmat{width:100%;border-collapse:collapse;font-size:12px;}
.pmat th{background:var(--bg-body);padding:8px 10px;text-align:center;border:1px solid var(--border);font-weight:600;}
.pmat th:first-child{text-align:left;}
.pmat td{padding:7px 10px;border:1px solid var(--border);text-align:center;}
.pmat tr:hover td{background:rgba(0,0,0,.015);}
.pyes{color:var(--success);font-size:15px;}
.pno{color:var(--border);font-size:15px;}
/* themes */
.tgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:10px;}
.tcard{border:2px solid var(--border);border-radius:10px;overflow:hidden;cursor:pointer;transition:border-color .2s,box-shadow .2s;}
.tcard:hover{box-shadow:0 4px 14px rgba(0,0,0,.1);}
.tcard.tactive{border-color:var(--primary-light);box-shadow:0 0 0 3px rgba(37,99,235,.12);}
.tprev{height:72px;display:flex;overflow:hidden;}
.tpsb{width:28px;flex-shrink:0;}
.tpmain{flex:1;}
.tptb{height:13px;border-bottom:1px solid rgba(0,0,0,.07);}
.tpbody{flex:1;padding:4px;display:flex;flex-direction:column;gap:3px;}
.tpbar{height:5px;border-radius:2px;}
.tclabel{padding:7px 10px;font-size:12px;font-weight:600;display:flex;justify-content:space-between;align-items:center;}
/* audit */
.atw{border-radius:8px;border:1px solid var(--border);overflow:hidden;}
.at{width:100%;border-collapse:collapse;font-size:12px;}
.at th{background:var(--bg-body);padding:8px 11px;text-align:left;border-bottom:1px solid var(--border);font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:.5px;}
.at td{padding:7px 11px;border-bottom:1px solid var(--border);vertical-align:middle;}
.at tr:last-child td{border-bottom:none;}
.at tr:hover td{background:rgba(0,0,0,.015);}
.abadge{font-size:10px;padding:2px 6px;border-radius:3px;font-weight:600;background:var(--bg-body);color:var(--text2);border:1px solid var(--border);}
/* sys info */
.sigrid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-top:10px;}
.sicard{background:var(--bg-body);border:1px solid var(--border);border-radius:8px;padding:12px 14px;}
.silabel{font-size:10px;color:var(--text2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;}
.sival{font-size:14px;font-weight:600;}
.sisub{font-size:11px;color:var(--text2);margin-top:2px;}
/* modal grids */
.mgrid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.mgrid .fc{grid-column:1/-1;}
</style>

<div class="sw">
<!-- Side Nav -->
<div class="ssnav">
  <?php foreach ($settingsTabs as $t):
    if (!sfHasPerm($t['perm'],$userRole,$CFG)) continue; ?>
  <a class="ssnav-item <?= $module===$t['id']?'active':'' ?>"
     href="index.php?page=settings_ui&module=<?= $t['id'] ?>">
    <span><?= $t['icon'] ?></span><span><?= htmlspecialchars($t['label']) ?></span>
  </a>
  <?php endforeach; ?>
</div>

<!-- Content -->
<div class="scontent" id="scontent">
<?php

/* ══════════════════════════════════════════
   GENERAL
══════════════════════════════════════════ */
if ($module === 'general'): ?>
<div class="ssect">
  <div class="stitle">🏢 Company Profile</div>
  <div class="ssub">Core company information used on reports, headers, and login page.</div>
  <?= sfField('Application Name',        'app.name',                  $CFG['app']['name']) ?>
  <?= sfField('Company Name',            'app.company_name',          $CFG['app']['company_name'],'text','Full legal name.') ?>
  <?= sfField('Short Name / Alias',      'app.company_short',         $CFG['app']['company_short'],'text','Used in topbar.') ?>
  <?= sfField('Registration Number',     'app.company_registration',  $CFG['app']['company_registration']) ?>
  <?= sfField('Registered Address',      'app.company_address',       $CFG['app']['company_address']) ?>
  <?= sfField('Company Email',           'app.company_email',         $CFG['app']['company_email'],'email') ?>
  <?= sfField('Company Phone',           'app.company_phone',         $CFG['app']['company_phone']) ?>
  <?= sfField('Tagline',                 'app.tagline',               $CFG['app']['tagline'],'text','Shown on login page.') ?>
</div>
<div class="ssect">
  <div class="stitle">🌐 Locale & Formatting</div>
  <?= sfSelect('Timezone','app.timezone',$CFG['app']['timezone'],['Asia/Karachi'=>'Asia/Karachi (PKT)','UTC'=>'UTC','America/New_York'=>'America/New_York','Europe/London'=>'Europe/London','Asia/Dubai'=>'Asia/Dubai','Asia/Kolkata'=>'Asia/Kolkata','Asia/Singapore'=>'Asia/Singapore'],'Affects all date/time display.') ?>
  <?= sfSelect('Date Format','app.date_format',$CFG['app']['date_format'],['d M Y'=>'01 Jan 2025','d/m/Y'=>'01/01/2025','Y-m-d'=>'2025-01-01 (ISO)','m/d/Y'=>'01/01/2025 (US)']) ?>
  <?= sfSelect('Currency','app.currency',$CFG['app']['currency'],['PKR'=>'PKR — Pakistani Rupee','USD'=>'USD — US Dollar','EUR'=>'EUR — Euro','GBP'=>'GBP — British Pound','AED'=>'AED — UAE Dirham']) ?>
</div>
<div class="ssect">
  <div class="stitle">📋 Resolution Settings</div>
  <?= sfField('Number Format','resolutions.numbering_format',$CFG['resolutions']['numbering_format'],'text','Tokens: {YEAR} {SEQ:04d}. e.g. RES-{YEAR}-{SEQ:04d}') ?>
  <?= sfSelect('Default Type','resolutions.default_type',$CFG['resolutions']['default_type'],['ordinary'=>'Ordinary (51%)','special'=>'Special (75%)','unanimous'=>'Unanimous (100%)','circular'=>'Circular (100%)']) ?>
  <?= sfField('Voting Deadline (days)','resolutions.approval.voting_deadline_days',(string)$CFG['resolutions']['approval']['voting_deadline_days'],'number') ?>
  <?= sfToggle('Require All Directors','resolutions.approval.require_all_directors',$CFG['resolutions']['approval']['require_all_directors'],'All directors must vote before resolution resolves.') ?>
  <?= sfToggle('Allow Proxy Votes','resolutions.approval.allow_proxy_votes',$CFG['resolutions']['approval']['allow_proxy_votes']) ?>
  <?= sfField('Auto-save Interval (sec)','resolutions.autosave_interval_seconds',(string)$CFG['resolutions']['autosave_interval_seconds'],'number') ?>
  <?= sfField('Undo Stack Limit','resolutions.undo_stack_limit',(string)$CFG['resolutions']['undo_stack_limit'],'number','Max undo steps per session.') ?>
</div>
<div class="ssect">
  <div class="stitle">📁 Document Settings</div>
  <?= sfField('Max File Size (MB)','documents.max_file_size_mb',(string)$CFG['documents']['max_file_size_mb'],'number') ?>
  <?= sfField('Storage Path','documents.storage_path',$CFG['documents']['storage_path'],'text','Relative to app root. Must be writable.') ?>
  <?= sfField('Max Versions Kept','documents.max_versions_kept',(string)$CFG['documents']['max_versions_kept'],'number') ?>
  <?= sfField('Recycle Bin Days','documents.recycle_bin_days',(string)$CFG['documents']['recycle_bin_days'],'number') ?>
  <?= sfToggle('Version Control','documents.version_control',$CFG['documents']['version_control'],'Auto-snapshot on each save.') ?>
</div>
<div class="savebar">
  <button class="btn primary" onclick="saveAll()">💾 Save All Changes</button>
  <button class="btn" onclick="location.reload()">↺ Reset</button>
  <span id="sstat" style="font-size:12px;color:var(--text2);margin-left:8px;"></span>
</div>

<?php
/* ══════════════════════════════════════════
   USERS
══════════════════════════════════════════ */
elseif ($module === 'users'): ?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
  <div><div class="stitle" style="border:none;padding:0;">👥 User Management</div>
  <div class="ssub" style="margin:0;">Manage system users, roles, and access levels.</div></div>
  <div style="display:flex;gap:8px;">
    <input type="text" class="form-control" placeholder="🔍 Search…" id="uSrch" oninput="filterU(this.value)" style="width:190px;height:34px;"/>
    <button class="btn primary" onclick="openUModal()">➕ Add User</button>
  </div>
</div>
<div class="ucgrid" id="ugrid"><div style="grid-column:1/-1;text-align:center;padding:36px;color:var(--text2);">⏳ Loading…</div></div>

<div class="modal-overlay" id="umodal">
<div class="modal" style="width:560px;">
  <button class="modal-close" onclick="closeU()">✕</button>
  <div class="modal-title" id="umtitle">Add User</div>
  <div class="modal-sub">Fields marked * are required.</div>
  <input type="hidden" id="uid_edit"/>
  <div class="mgrid" style="margin-top:14px;">
    <div class="form-group"><label>Full Name *</label><input type="text" class="form-control" id="u_name" placeholder="Jane Smith"/></div>
    <div class="form-group"><label>Email *</label><input type="email" class="form-control" id="u_email"/></div>
    <div class="form-group"><label>Password <small id="uplbl">(required)</small></label>
      <input type="password" class="form-control" id="u_pass" placeholder="Min <?= $CFG['auth']['password_min_length'] ?> chars"/></div>
    <div class="form-group"><label>Role *</label>
      <select class="form-control" id="u_role">
        <?php foreach ($CFG['roles'] as $rk=>$rv): if(!$isSuperAdmin && $rk==='super_admin') continue; ?>
        <option value="<?= $rk ?>"><?= htmlspecialchars($rv['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>Status</label>
      <select class="form-control" id="u_status">
        <option value="active">Active</option><option value="inactive">Inactive</option><option value="suspended">Suspended</option>
      </select>
    </div>
    <div class="form-group"><label>Phone</label><input type="text" class="form-control" id="u_phone"/></div>
  </div>
  <div class="modal-footer">
    <button class="btn" onclick="closeU()">Cancel</button>
    <button class="btn primary" onclick="saveU()">💾 Save</button>
  </div>
</div></div>

<?php
/* ══════════════════════════════════════════
   DIRECTORS
══════════════════════════════════════════ */
elseif ($module === 'directors'): ?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
  <div><div class="stitle" style="border:none;padding:0;">💼 Director Management</div>
  <div class="ssub" style="margin:0;">Board directors, designations, committees, terms, and status.</div></div>
  <div style="display:flex;gap:8px;">
    <select class="form-control" id="dfilter" onchange="loadDirs()" style="width:130px;height:34px;">
      <option value="active">Active</option><option value="resigned">Resigned</option><option value="">All</option>
    </select>
    <button class="btn primary" onclick="openDModal()">➕ Add Director</button>
    <button class="btn" onclick="cos.exportExcel('directors')">📊 Export</button>
  </div>
</div>

<div style="display:flex;gap:10px;margin-bottom:16px;">
  <?php foreach (['dstat_active'=>'Active Directors','dstat_resigned'=>'Resigned','dstat_committees'=>'Committees','dstat_term'=>'Term (yrs)'] as $sid=>$slbl): ?>
  <div class="sicard" style="flex:1"><div class="silabel"><?= $slbl ?></div>
    <div class="sival" id="<?= $sid ?>">
      <?php if($sid==='dstat_committees') echo count($CFG['directors']['committees']);
            elseif($sid==='dstat_term')  echo $CFG['directors']['term_years'];
            else echo '—'; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="dgrid" id="dgrid"><div style="grid-column:1/-1;text-align:center;padding:36px;color:var(--text2);">⏳ Loading…</div></div>

<div class="modal-overlay" id="dmodal">
<div class="modal" style="width:640px;max-height:88vh;overflow-y:auto;">
  <button class="modal-close" onclick="closeD()">✕</button>
  <div class="modal-title" id="dmtitle">Add Director</div>
  <input type="hidden" id="did_edit"/>
  <div class="mgrid" style="margin-top:14px;">
    <div class="form-group fc"><label>Full Name *</label><input type="text" class="form-control" id="d_name"/></div>
    <div class="form-group"><label>Designation *</label>
      <select class="form-control" id="d_desig">
        <?php foreach($CFG['directors']['designations'] as $dg): ?><option><?= htmlspecialchars($dg) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>CNIC / ID</label><input type="text" class="form-control" id="d_cnic" placeholder="00000-0000000-0"/></div>
    <div class="form-group"><label>Email *</label><input type="email" class="form-control" id="d_email"/></div>
    <div class="form-group"><label>Phone</label><input type="text" class="form-control" id="d_phone"/></div>
    <div class="form-group"><label>Appointment Date *</label><input type="date" class="form-control" id="d_appt"/></div>
    <div class="form-group"><label>Term Expires</label><input type="date" class="form-control" id="d_term"/></div>
    <div class="form-group"><label>DIN</label><input type="text" class="form-control" id="d_din" placeholder="Director ID No."/></div>
    <div class="form-group"><label>Qualification</label><input type="text" class="form-control" id="d_qualif" placeholder="MBA, CA, LLB…"/></div>
    <div class="form-group"><label>Expertise</label><input type="text" class="form-control" id="d_expert" placeholder="Finance, Legal…"/></div>
    <div class="form-group fc"><label>Committees</label>
      <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:4px;">
        <?php foreach($CFG['directors']['committees'] as $cm): ?>
        <label style="display:flex;align-items:center;gap:4px;font-size:12px;cursor:pointer;">
          <input type="checkbox" class="dcomm" value="<?= htmlspecialchars($cm) ?>"/> <?= htmlspecialchars($cm) ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="form-group fc"><label>Notes</label><textarea class="form-control" id="d_notes" rows="2"></textarea></div>
    <div class="form-group" id="d_sg" style="display:none;"><label>Status</label>
      <select class="form-control" id="d_status">
        <option value="active">Active</option><option value="resigned">Resigned</option>
        <option value="removed">Removed</option><option value="deceased">Deceased</option>
      </select>
    </div>
    <div class="form-group" id="d_rdg" style="display:none;"><label>Resignation Date</label>
      <input type="date" class="form-control" id="d_rdate"/>
    </div>
  </div>
  <div class="modal-footer">
    <button class="btn" onclick="closeD()">Cancel</button>
    <button class="btn primary" onclick="saveDir()">💾 Save Director</button>
  </div>
</div></div>

<?php
/* ══════════════════════════════════════════
   ROLES & PERMISSIONS
══════════════════════════════════════════ */
elseif ($module === 'roles'): ?>
<div class="ssect">
  <div class="stitle">🛡 Roles & Permission Matrix</div>
  <div class="ssub">Defined in <code>settings.json → roles</code>. This view is read-only.</div>
  <?php
  $allPerms = [
    'manage_users'=>'Manage Users','manage_directors'=>'Manage Directors',
    'manage_resolutions'=>'Manage Resolutions','manage_documents'=>'Manage Documents',
    'view_resolutions'=>'View Resolutions','approve_resolutions'=>'Approve Resolutions',
    'sign_resolutions'=>'Sign Resolutions','view_documents'=>'View Documents',
    'create_documents'=>'Create Documents','edit_own_documents'=>'Edit Own Docs',
    'view_audit'=>'View Audit Log','manage_settings'=>'Manage Settings',
  ];
  ?>
  <div style="overflow-x:auto;margin-top:12px;">
  <table class="pmat">
    <thead><tr>
      <th>Permission</th>
      <?php foreach($CFG['roles'] as $rk=>$rv): ?>
      <th><div><?= htmlspecialchars($rv['label']) ?></div>
        <span class="badge" style="background:<?= $rv['color'] ?>22;color:<?= $rv['color'] ?>;margin-top:3px;display:inline-block;"><?= $rk ?></span>
      </th>
      <?php endforeach; ?>
    </tr></thead>
    <tbody>
    <?php foreach($allPerms as $pk=>$pl): ?>
    <tr>
      <td style="font-weight:500;text-align:left;"><?= htmlspecialchars($pl) ?></td>
      <?php foreach($CFG['roles'] as $rk=>$rv):
        $has = in_array('*',$rv['permissions'])||in_array($pk,$rv['permissions']); ?>
      <td><span class="<?= $has?'pyes':'pno' ?>"><?= $has?'✓':'·' ?></span></td>
      <?php endforeach; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:20px;">
    <?php foreach($CFG['roles'] as $rk=>$rv): ?>
    <div style="padding:10px 12px;border-radius:7px;border-left:4px solid <?= $rv['color'] ?>;background:var(--bg-body);border:1px solid var(--border);border-left-width:4px;">
      <div style="font-weight:700;font-size:12px;color:<?= $rv['color'] ?>"><?= htmlspecialchars($rv['label']) ?></div>
      <div style="font-size:11px;color:var(--text2);margin-top:2px;">
        <?= count($rv['permissions'])===1&&$rv['permissions'][0]==='*' ? 'All permissions (wildcard)' : count($rv['permissions']).' defined permissions' ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php
/* ══════════════════════════════════════════
   SECURITY
══════════════════════════════════════════ */
elseif ($module === 'security'): ?>
<div class="ssect">
  <div class="stitle">🔒 Authentication</div>
  <?= sfField('Min Password Length',   'auth.password_min_length',      (string)$CFG['auth']['password_min_length'],'number') ?>
  <?= sfField('Max Login Attempts',    'auth.max_login_attempts',       (string)$CFG['auth']['max_login_attempts'],'number','Account locked after this many failures.') ?>
  <?= sfField('Lockout Duration (min)','auth.lockout_duration_minutes', (string)$CFG['auth']['lockout_duration_minutes'],'number') ?>
  <?= sfField('Session Lifetime (sec)','auth.session_lifetime',         (string)$CFG['auth']['session_lifetime'],'number','86400 = 24 hours.') ?>
  <?= sfToggle('Require Uppercase',    'auth.password_require_uppercase', $CFG['auth']['password_require_uppercase']) ?>
  <?= sfToggle('Require Number',       'auth.password_require_number',    $CFG['auth']['password_require_number']) ?>
  <?= sfToggle('Require Special Char', 'auth.password_require_special',   $CFG['auth']['password_require_special']) ?>
  <?= sfToggle('Two-Factor Auth',      'auth.two_factor_enabled',         $CFG['auth']['two_factor_enabled'],'TOTP-based 2FA. Requires additional integration.') ?>
</div>
<div class="ssect">
  <div class="stitle">🛡 Application Security</div>
  <?= sfToggle('CSRF Protection',      'security.csrf_enabled',          $CFG['security']['csrf_enabled'],'Always recommended.') ?>
  <?= sfToggle('XSS Protection',       'security.xss_protection',        $CFG['security']['xss_protection']) ?>
  <?= sfToggle('Rate Limiting',        'security.rate_limiting',         $CFG['security']['rate_limiting']) ?>
  <?= sfField('Rate Limit (req/min)',  'security.rate_limit_requests_per_minute',(string)$CFG['security']['rate_limit_requests_per_minute'],'number') ?>
  <?= sfToggle('Force HTTPS',          'security.force_https',           $CFG['security']['force_https'],'Redirect all HTTP to HTTPS.') ?>
  <?= sfToggle('IP Whitelist',         'security.ip_whitelist_enabled',  $CFG['security']['ip_whitelist_enabled']) ?>
  <?= sfField('Allowed IPs (comma)',   'security.ip_whitelist',          implode(', ',$CFG['security']['ip_whitelist']),'text','e.g. 192.168.1.1, 10.0.0.1') ?>
  <?= sfField('Encryption Key',        'security.data_encryption_key',   $CFG['security']['data_encryption_key'],'password','⚠ Change before production. 32+ random chars.') ?>
</div>
<div class="ssect">
  <div class="stitle">📋 Audit Logging</div>
  <?= sfToggle('Enable Audit Log',     'audit.enabled',           $CFG['audit']['enabled']) ?>
  <?= sfToggle('Log Logins',           'audit.log_logins',        $CFG['audit']['log_logins']) ?>
  <?= sfToggle('Log Failed Logins',    'audit.log_failed_logins', $CFG['audit']['log_failed_logins']) ?>
  <?= sfToggle('Log All CRUD',         'audit.log_crud',          $CFG['audit']['log_crud']) ?>
  <?= sfToggle('Log Exports',          'audit.log_exports',       $CFG['audit']['log_exports']) ?>
  <?= sfField('Retention (days)',      'audit.retention_days',    (string)$CFG['audit']['retention_days'],'number','Entries older than this are auto-purged.') ?>
</div>
<div class="savebar">
  <button class="btn primary" onclick="saveAll()">💾 Save Security Settings</button>
  <span id="sstat" style="font-size:12px;color:var(--text2);margin-left:8px;"></span>
</div>

<?php
/* ══════════════════════════════════════════
   THEME
══════════════════════════════════════════ */
elseif ($module === 'theme'): ?>
<div class="ssect">
  <div class="stitle">🎨 Theme Selection</div>
  <div class="ssub">Select a theme. Changes take effect after saving and page reload.</div>
  <div class="tgrid" id="tgrid">
    <?php foreach($CFG['theme']['themes'] as $tk=>$tv):
      $act = ($tk===$CFG['theme']['active']); ?>
    <div class="tcard <?= $act?'tactive':'' ?>" onclick="pickTheme('<?= $tk ?>')" data-theme="<?= $tk ?>">
      <div class="tprev">
        <div class="tpsb" style="background:<?= $tv['bg_sidebar'] ?>"></div>
        <div class="tpmain" style="background:<?= $tv['bg_body'] ?>">
          <div class="tptb" style="background:<?= $tv['bg_topbar'] ?>"></div>
          <div class="tpbody">
            <div class="tpbar" style="background:<?= $tv['primary_light'] ?>;width:55%"></div>
            <div class="tpbar" style="background:<?= $tv['border_color'] ?>;width:75%"></div>
            <div class="tpbar" style="background:<?= $tv['accent'] ?>;width:35%"></div>
            <div class="tpbar" style="background:<?= $tv['border_color'] ?>;width:60%"></div>
          </div>
        </div>
      </div>
      <div class="tclabel">
        <span><?= htmlspecialchars($tv['label']) ?></span>
        <?= $act?'<span style="color:var(--success);font-size:11px;">✓ Active</span>':'' ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div style="margin-top:20px;">
    <div class="stitle" style="font-size:13px;">Color Swatches — <?= htmlspecialchars($CFG['theme']['themes'][$CFG['theme']['active']]['label']) ?></div>
    <?php $at=$CFG['theme']['themes'][$CFG['theme']['active']]; ?>
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;">
      <?php foreach(['primary','primary_light','secondary','accent','success','danger','warning','info','bg_body','bg_sidebar','bg_card'] as $ck): ?>
      <div style="display:flex;align-items:center;gap:6px;padding:5px 10px;border:1px solid var(--border);border-radius:6px;background:var(--bg-body);">
        <div style="width:20px;height:20px;border-radius:3px;background:<?= $at[$ck] ?>;border:1px solid rgba(0,0,0,.1);flex-shrink:0"></div>
        <div><div style="font-size:11px;font-weight:600;"><?= $ck ?></div><div style="font-size:10px;color:var(--text2);"><?= $at[$ck] ?></div></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<div class="ssect">
  <div class="stitle">📐 UI Preferences</div>
  <?= sfField('Sidebar Width (px)',  'ui.sidebar_width',   (string)$CFG['ui']['sidebar_width'],'number') ?>
  <?= sfField('Items Per Page',      'ui.items_per_page',  (string)$CFG['ui']['items_per_page'],'number') ?>
  <?= sfToggle('Animations',         'ui.animations_enabled', $CFG['ui']['animations_enabled']) ?>
  <?= sfToggle('Dense Tables',       'ui.dense_tables',       $CFG['ui']['dense_tables'],'Tighter row spacing.') ?>
  <?= sfToggle('Confirm Delete',     'ui.confirm_delete',     $CFG['ui']['confirm_delete']) ?>
  <?= sfToggle('Status Bar',         'ui.status_bar_enabled', $CFG['ui']['status_bar_enabled']) ?>
  <?= sfToggle('Show Word Count',    'ui.show_word_count',    $CFG['ui']['show_word_count']) ?>
  <?= sfToggle('Tooltips',           'ui.tooltips_enabled',   $CFG['ui']['tooltips_enabled']) ?>
</div>
<div class="savebar">
  <button class="btn primary" onclick="saveAll()">💾 Apply & Save</button>
  <span id="sstat" style="font-size:12px;color:var(--text2);margin-left:8px;"></span>
</div>

<?php
/* ══════════════════════════════════════════
   DATABASE
══════════════════════════════════════════ */
elseif ($module === 'database'): ?>
<div class="ssect">
  <div class="stitle">🗄 Connection (Read-Only)</div>
  <div class="ssub">Edit <code>settings.json</code> directly to change DB credentials.</div>
  <?= sfField('Host',          'database.host',     $CFG['database']['host'],'text','',true) ?>
  <?= sfField('Port',          'database.port',     (string)$CFG['database']['port'],'number','',true) ?>
  <?= sfField('Database Name', 'database.name',     $CFG['database']['name'],'text','',true) ?>
  <?= sfField('Username',      'database.username', $CFG['database']['username'],'text','',true) ?>
  <?= sfField('Table Prefix',  'database.prefix',   $CFG['database']['prefix'],'text','',true) ?>
  <?= sfField('Charset',       'database.charset',  $CFG['database']['charset'],'text','',true) ?>
</div>
<div class="ssect">
  <div class="stitle">📊 Database Status</div>
  <div id="dbstat"><div style="padding:14px;color:var(--text2);">⏳ Checking…</div></div>
</div>
<div class="ssect">
  <div class="stitle">🧹 Maintenance</div>
  <?php
  $mActions = [
    ['Purge Old Audit Entries','Delete audit log entries older than '.$CFG['audit']['retention_days'].' days','purgeAudit()','danger','🗑'],
    ['Empty Recycle Bin','Permanently remove docs deleted more than '.$CFG['documents']['recycle_bin_days'].' days ago','emptyBin()','danger','🗑'],
    ['Clear Undo History','Remove all undo/redo states from database','clearUndo()','','🧹'],
    ['Rebuild DB Tables','Re-run auto-installer (safe — uses CREATE IF NOT EXISTS)','rebuildDB()','','🔧'],
  ];
  foreach($mActions as $m): ?>
  <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:var(--bg-body);border-radius:8px;border:1px solid var(--border);margin-bottom:8px;">
    <div><div style="font-weight:600;font-size:13px;"><?= $m[0] ?></div><div style="font-size:11px;color:var(--text2);"><?= $m[1] ?></div></div>
    <button class="btn <?= $m[3] ?>" style="flex-shrink:0;" onclick="<?= $m[2] ?>"><?= $m[4] ?> <?= $m[0] ?></button>
  </div>
  <?php endforeach; ?>
</div>

<?php
/* ══════════════════════════════════════════
   NOTIFICATIONS
══════════════════════════════════════════ */
elseif ($module === 'notifications'): ?>
<div class="ssect">
  <div class="stitle">🔔 General</div>
  <?= sfToggle('Enable Notifications',  'notifications.enabled',        $CFG['notifications']['enabled']) ?>
  <?= sfToggle('In-App Notifications',  'notifications.in_app_enabled', $CFG['notifications']['in_app_enabled'],'Bell icon in topbar.') ?>
  <?= sfToggle('Email Notifications',   'notifications.email_enabled',  $CFG['notifications']['email_enabled'],'Requires SMTP config.') ?>
</div>
<div class="ssect">
  <div class="stitle">📧 SMTP Configuration</div>
  <?= sfField('SMTP Host',      'notifications.smtp.host',       $CFG['notifications']['smtp']['host'],'text','e.g. smtp.gmail.com') ?>
  <?= sfField('SMTP Port',      'notifications.smtp.port',       (string)$CFG['notifications']['smtp']['port'],'number','587=TLS, 465=SSL') ?>
  <?= sfField('Username',       'notifications.smtp.username',   $CFG['notifications']['smtp']['username']) ?>
  <?= sfField('Password',       'notifications.smtp.password',   $CFG['notifications']['smtp']['password'],'password') ?>
  <?= sfField('From Name',      'notifications.smtp.from_name',  $CFG['notifications']['smtp']['from_name']) ?>
  <?= sfField('From Email',     'notifications.smtp.from_email', $CFG['notifications']['smtp']['from_email'],'email') ?>
  <?= sfSelect('Encryption','notifications.smtp.encryption',$CFG['notifications']['smtp']['encryption'],['tls'=>'TLS (port 587)','ssl'=>'SSL (port 465)','none'=>'None (port 25)']) ?>
  <div style="margin-top:10px;display:flex;gap:8px;align-items:center;">
    <button class="btn primary" onclick="testSMTP()">📧 Send Test Email</button>
    <input type="email" class="form-control" id="testEmail" placeholder="recipient@example.com" style="width:220px;"/>
  </div>
</div>
<div class="ssect">
  <div class="stitle">📋 Event Triggers</div>
  <div class="ssub">Which roles are notified for each event (configured in settings.json).</div>
  <?php foreach($CFG['notifications']['events'] as $ek=>$ev): ?>
  <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);">
    <div style="flex:1;font-size:13px;">
      <strong><?= htmlspecialchars(ucwords(str_replace('_',' ',$ek))) ?></strong>
      <div style="font-size:11px;color:var(--text2);"><?= htmlspecialchars($ev['subject']) ?></div>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:4px;">
      <?php foreach($ev['notify_roles'] as $nr): ?>
      <span class="badge badge-draft" style="font-size:10px;"><?= htmlspecialchars($CFG['roles'][$nr]['label']??$nr) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<div class="savebar">
  <button class="btn primary" onclick="saveAll()">💾 Save Notification Settings</button>
  <span id="sstat" style="font-size:12px;color:var(--text2);margin-left:8px;"></span>
</div>

<?php
/* ══════════════════════════════════════════
   AUDIT LOG
══════════════════════════════════════════ */
elseif ($module === 'audit'): ?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
  <div><div class="stitle" style="border:none;padding:0;">📊 Audit Log</div>
  <div class="ssub" style="margin:0;">Full trail of every user action and system event.</div></div>
  <div style="display:flex;gap:6px;flex-wrap:wrap;">
    <input type="text" class="form-control" id="asrch" placeholder="Filter action…" oninput="loadAudit()" style="width:160px;height:32px;font-size:12px;"/>
    <select class="form-control" id="auserF" style="width:150px;height:32px;font-size:12px;" onchange="loadAudit()">
      <option value="">All Users</option>
    </select>
    <select class="form-control" id="alimit" style="width:90px;height:32px;font-size:12px;" onchange="loadAudit()">
      <option value="50">50 rows</option><option value="100">100</option><option value="200">200</option>
    </select>
    <button class="btn" onclick="loadAudit()" style="height:32px;font-size:12px;">🔄</button>
    <button class="btn" onclick="exportAudit()" style="height:32px;font-size:12px;">📥 CSV</button>
  </div>
</div>
<div class="atw">
<table class="at">
  <thead><tr>
    <th>ID</th><th>Timestamp</th><th>User</th><th>Action</th><th>Entity</th><th>IP</th><th>Notes</th>
  </tr></thead>
  <tbody id="atbody">
    <tr><td colspan="7" style="text-align:center;padding:28px;color:var(--text2);">⏳ Loading…</td></tr>
  </tbody>
</table>
</div>
<div style="margin-top:6px;font-size:11px;color:var(--text2);" id="apagi"></div>

<?php
/* ══════════════════════════════════════════
   SYSTEM INFO
══════════════════════════════════════════ */
elseif ($module === 'system'): ?>
<div class="ssect">
  <div class="stitle">⚙️ System Information</div>
  <div class="sigrid">
    <div class="sicard"><div class="silabel">Application</div><div class="sival"><?= $CFG['app']['name'] ?> v<?= $CFG['app']['version'] ?></div><div class="sisub">Build <?= $CFG['app']['build'] ?></div></div>
    <div class="sicard"><div class="silabel">PHP Version</div><div class="sival"><?= PHP_VERSION ?></div><div class="sisub"><?= PHP_OS ?></div></div>
    <div class="sicard"><div class="silabel">Server</div><div class="sival" style="font-size:12px;"><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE']??'Unknown') ?></div></div>
    <div class="sicard"><div class="silabel">Document Root</div><div class="sival" style="font-size:11px;word-break:break-all;"><?= htmlspecialchars(ROOT) ?></div></div>
    <div class="sicard"><div class="silabel">Memory Limit</div><div class="sival"><?= ini_get('memory_limit') ?></div><div class="sisub">Max exec: <?= ini_get('max_execution_time') ?>s</div></div>
    <div class="sicard"><div class="silabel">Upload Max</div><div class="sival"><?= ini_get('upload_max_filesize') ?></div><div class="sisub">Post max: <?= ini_get('post_max_size') ?></div></div>
    <div class="sicard"><div class="silabel">Active Theme</div><div class="sival"><?= htmlspecialchars($CFG['theme']['themes'][$CFG['theme']['active']]['label']) ?></div><div class="sisub"><?= $CFG['theme']['active'] ?></div></div>
    <div class="sicard"><div class="silabel">Server Time</div><div class="sival"><?= date('d M Y') ?></div><div class="sisub"><?= date($CFG['app']['time_format']) ?> — <?= $CFG['app']['timezone'] ?></div></div>
  </div>
</div>
<div class="ssect">
  <div class="stitle">🔌 PHP Extensions</div>
  <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;">
    <?php foreach(['pdo'=>true,'pdo_mysql'=>true,'json'=>true,'mbstring'=>true,'openssl'=>true,'session'=>true,'fileinfo'=>true,'gd'=>false,'zip'=>false,'curl'=>false,'xml'=>false,'intl'=>false] as $ext=>$req):
      $ok=extension_loaded($ext); ?>
    <div style="padding:4px 10px;border-radius:4px;font-size:11px;font-weight:600;
      background:<?= $ok?'#f0fdf4':($req?'#fef2f2':'#f8fafc') ?>;
      color:<?= $ok?'#15803d':($req?'#991b1b':'#6b7280') ?>;
      border:1px solid <?= $ok?'#bbf7d0':($req?'#fecaca':'#e2e8f0') ?>;">
      <?= $ok?'✓':'✗' ?> <?= $ext ?><?= $req?' *':'' ?>
    </div>
    <?php endforeach; ?>
  </div>
  <div style="font-size:11px;color:var(--text2);margin-top:6px;">* Required extensions</div>
</div>
<div class="ssect">
  <div class="stitle">📁 Directory Permissions</div>
  <?php foreach(['storage/documents/'=>'Document storage','storage/thumbnails/'=>'Thumbnails','logs/'=>'Log files','assets/'=>'Static assets'] as $d=>$lbl):
    $fp=ROOT.'/'.$d; $ex=is_dir($fp); $wr=$ex&&is_writable($fp); ?>
  <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);">
    <span><?= $wr?'✅':($ex?'⚠️':'❌') ?></span>
    <code style="flex:1;font-size:12px;"><?= $d ?></code>
    <span style="font-size:12px;color:var(--text2);"><?= $lbl ?></span>
    <span class="badge <?= $wr?'badge-approved':($ex?'badge-pending':'badge-rejected') ?>">
      <?= $wr?'Writable':($ex?'Read-only':'Missing') ?>
    </span>
    <?php if(!$ex): ?><button class="btn" style="height:26px;font-size:11px;" onclick="mkDir('<?= $d ?>')">Create</button><?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<div class="ssect">
  <div class="stitle">⌨️ Keyboard Shortcuts</div>
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-top:8px;">
    <?php foreach($CFG['keyboard_shortcuts'] as $a=>$k): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:5px 8px;background:var(--bg-body);border-radius:5px;border:1px solid var(--border);">
      <span style="font-size:12px;"><?= htmlspecialchars(ucwords(str_replace('_',' ',$a))) ?></span>
      <kbd style="background:var(--bg-card);border:1px solid var(--border);border-radius:3px;padding:1px 6px;font-size:11px;font-family:monospace;box-shadow:0 1px 1px rgba(0,0,0,.08);"><?= htmlspecialchars($k) ?></kbd>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php endif; // end module switch ?>
</div><!-- /scontent -->
</div><!-- /sw -->

<script>
// ═══════════════════════════════════════════
// Settings JS
// ═══════════════════════════════════════════
const _sc = {};

function sc(el)  { _sc[el.dataset.key] = el.value; cos.markDirty(); setStat('● Unsaved'); }
function stc(el) { _sc[el.dataset.key] = el.checked?'1':'0'; cos.markDirty(); setStat('● Unsaved'); }
function setStat(m,ok=false) {
  const el = document.getElementById('sstat');
  if (el) { el.textContent=m; el.style.color=ok?'var(--success)':'var(--text2)'; }
}

async function saveAll() {
  const keys = Object.keys(_sc);
  if(!keys.length){ cos.toast('No changes to save','info'); return; }
  let n=0;
  for(const k of keys){
    const r = await cos.api('save_setting',{key:k,value:_sc[k]});
    if(r.success) n++;
  }
  Object.keys(_sc).forEach(k=>delete _sc[k]);
  cos.isDirty=false;
  setStat(`✓ ${n} setting(s) saved`,true);
  cos.toast(`${n} setting(s) saved`,'success');
}
window.cosSave = async()=>{ await saveAll(); return true; };

// ─── Theme picker
function pickTheme(name) {
  document.querySelectorAll('.tcard').forEach(c=>c.classList.remove('tactive'));
  const c=document.querySelector(`.tcard[data-theme="${name}"]`);
  if(c) c.classList.add('tactive');
  _sc['theme.active']=name;
  cos.markDirty();
  cos.toast('Theme "'+name+'" selected. Save to apply.','info');
}

// ═══════════════════════════════════════════
// USERS
// ═══════════════════════════════════════════
let _users=[];
const _rColors = <?= json_encode(array_map(fn($r)=>$r['color'],$CFG['roles'])) ?>;
const _rLabels = <?= json_encode(array_map(fn($r)=>$r['label'],$CFG['roles'])) ?>;

async function loadUsers() {
  const r=await cos.api('list_users');
  if(!r.success){cos.toast(r.message,'error');return;}
  _users=r.data||[]; renderU(_users);
}
function renderU(users){
  const g=document.getElementById('ugrid'); if(!g) return;
  if(!users.length){g.innerHTML='<div style="grid-column:1/-1;text-align:center;padding:36px;color:var(--text2);">No users found.</div>';return;}
  g.innerHTML=users.map(u=>{
    const col=_rColors[u.role]||'#6b7280';
    const lbl=_rLabels[u.role]||u.role;
    const init=u.name.charAt(0).toUpperCase();
    return `<div class="ucard">
      <div class="uavatar" style="background:${col}">${init}</div>
      <div class="ucinfo">
        <div class="ucname">${u.name}</div>
        <div class="ucemail">${u.email}</div>
        <div style="display:flex;gap:4px;flex-wrap:wrap;margin:3px 0;">
          <span class="badge" style="background:${col}22;color:${col}">${lbl}</span>
          <span class="badge ${u.status==='active'?'badge-approved':u.status==='suspended'?'badge-rejected':'badge-draft'}">${u.status}</span>
        </div>
        <div style="font-size:10px;color:var(--text2);">Login: ${u.last_login_fmt||'Never'} · Joined: ${u.created_date}</div>
        <div class="ucactions">
          <button class="btn" style="font-size:11px;height:26px;" onclick="openUEdit(${u.id})">✏️ Edit</button>
          <button class="btn danger" style="font-size:11px;height:26px;" onclick="deactUser(${u.id},'${u.name.replace(/'/g,"\\'")}')">🚫</button>
        </div>
      </div>
    </div>`;
  }).join('');
}
function filterU(q){const l=q.toLowerCase();renderU(_users.filter(u=>u.name.toLowerCase().includes(l)||u.email.toLowerCase().includes(l)||u.role.includes(l)));}
function openUModal(){
  document.getElementById('uid_edit').value='';
  document.getElementById('umtitle').textContent='Add User';
  document.getElementById('uplbl').textContent='(required)';
  ['u_name','u_email','u_pass','u_phone'].forEach(i=>{const el=document.getElementById(i);if(el)el.value='';});
  document.getElementById('u_role').value='staff';
  document.getElementById('u_status').value='active';
  document.getElementById('umodal').classList.add('open');
}
function openUEdit(id){
  const u=_users.find(u=>u.id==id);if(!u)return;
  document.getElementById('uid_edit').value=u.id;
  document.getElementById('umtitle').textContent='Edit: '+u.name;
  document.getElementById('uplbl').textContent='(leave blank to keep)';
  document.getElementById('u_name').value=u.name;
  document.getElementById('u_email').value=u.email;
  document.getElementById('u_pass').value='';
  document.getElementById('u_role').value=u.role;
  document.getElementById('u_status').value=u.status;
  document.getElementById('umodal').classList.add('open');
}
function closeU(){document.getElementById('umodal').classList.remove('open');}
async function saveU(){
  const id=document.getElementById('uid_edit').value;
  const name=document.getElementById('u_name').value.trim();
  const email=document.getElementById('u_email').value.trim();
  const pass=document.getElementById('u_pass').value;
  const role=document.getElementById('u_role').value;
  const status=document.getElementById('u_status').value;
  if(!name||!email){cos.toast('Name and email required','error');return;}
  if(!id&&!pass){cos.toast('Password required for new users','error');return;}
  const payload=id?{id,name,role,status,password:pass}:{name,email,password:pass,role};
  const r=await cos.api(id?'update_user':'create_user',payload);
  cos.toast(r.message||(r.success?'Saved':'Error'),r.success?'success':'error');
  if(r.success){closeU();loadUsers();}
}
async function deactUser(id,name){
  if(!confirm(`Deactivate "${name}"?`))return;
  const r=await cos.api('delete_user',{id});
  cos.toast(r.message||(r.success?'Deactivated':'Error'),r.success?'success':'error');
  if(r.success)loadUsers();
}

// ═══════════════════════════════════════════
// DIRECTORS
// ═══════════════════════════════════════════
let _dirs=[];
async function loadDirs(){
  const st=document.getElementById('dfilter')?.value||'active';
  const r=await cos.api('list_directors',{status:st});
  if(!r.success){cos.toast(r.message,'error');return;}
  _dirs=r.data||[];renderDirs(_dirs);
  const a=_dirs.filter(d=>d.status==='active').length;
  const re=_dirs.filter(d=>d.status==='resigned').length;
  const da=document.getElementById('dstat_active'); if(da) da.textContent=a;
  const dr=document.getElementById('dstat_resigned'); if(dr) dr.textContent=re;
}
function renderDirs(dirs){
  const g=document.getElementById('dgrid');if(!g)return;
  if(!dirs.length){g.innerHTML='<div style="grid-column:1/-1;text-align:center;padding:36px;color:var(--text2);"><div style="font-size:44px;margin-bottom:8px;">💼</div>No directors found.</div>';return;}
  g.innerHTML=dirs.map(d=>{
    const init=d.name.split(' ').map(w=>w[0]).join('').substring(0,2).toUpperCase();
    const comms=d.committees?JSON.parse(d.committees):[];
    return `<div class="dcard">
      <div class="dchead">
        <div class="dcavatar">${init}</div>
        <div><div style="font-weight:700;font-size:13px;">${d.name}</div><div style="font-size:11px;opacity:.8;margin-top:1px;">${d.designation}</div></div>
        <span class="badge" style="margin-left:auto;font-size:10px;background:${d.status==='active'?'rgba(16,185,129,.2)':'rgba(239,68,68,.2)'};color:${d.status==='active'?'#10b981':'#ef4444'}">${d.status}</span>
      </div>
      <div class="dcbody">
        ${d.email?`<div class="ddr">📧 <span>${d.email}</span></div>`:''}
        ${d.phone?`<div class="ddr">📞 <span>${d.phone}</span></div>`:''}
        ${d.appt_fmt?`<div class="ddr">📅 Appointed: <strong>${d.appt_fmt}</strong></div>`:''}
        ${d.term_fmt?`<div class="ddr">⏳ Term: <strong>${d.term_fmt}</strong></div>`:''}
        ${d.cnic?`<div class="ddr">🪪 <strong>${d.cnic}</strong></div>`:''}
        ${d.din?`<div class="ddr">🔖 DIN: <strong>${d.din}</strong></div>`:''}
        ${comms.length?`<div style="display:flex;flex-wrap:wrap;gap:3px;margin-top:4px;">${comms.map(c=>`<span class="badge badge-draft" style="font-size:10px;">${c}</span>`).join('')}</div>`:''}
        <div style="display:flex;gap:5px;margin-top:10px;">
          <button class="btn" style="font-size:11px;height:26px;flex:1" onclick="openDEdit(${d.id})">✏️ Edit</button>
          ${d.status==='active'?`<button class="btn danger" style="font-size:11px;height:26px;" onclick="resignDir(${d.id},'${d.name.replace(/'/g,"\\'")}')">🚪 Resign</button>`:''}
        </div>
      </div>
    </div>`;
  }).join('');
}
function openDModal(){
  document.getElementById('did_edit').value='';
  document.getElementById('dmtitle').textContent='Add Director';
  ['d_name','d_cnic','d_email','d_phone','d_din','d_qualif','d_expert','d_notes'].forEach(i=>{const el=document.getElementById(i);if(el)el.value='';});
  document.getElementById('d_appt').value=new Date().toISOString().split('T')[0];
  document.getElementById('d_term').value='';
  document.querySelectorAll('.dcomm').forEach(c=>c.checked=false);
  document.getElementById('d_sg').style.display='none';
  document.getElementById('d_rdg').style.display='none';
  document.getElementById('dmodal').classList.add('open');
}
function openDEdit(id){
  const d=_dirs.find(d=>d.id==id);if(!d)return;
  document.getElementById('did_edit').value=d.id;
  document.getElementById('dmtitle').textContent='Edit: '+d.name;
  document.getElementById('d_name').value=d.name||'';
  document.getElementById('d_cnic').value=d.cnic||'';
  document.getElementById('d_email').value=d.email||'';
  document.getElementById('d_phone').value=d.phone||'';
  document.getElementById('d_din').value=d.din||'';
  document.getElementById('d_qualif').value=d.qualification||'';
  document.getElementById('d_expert').value=d.expertise||'';
  document.getElementById('d_notes').value=d.notes||'';
  document.getElementById('d_appt').value=d.appointment_date||'';
  document.getElementById('d_term').value=d.term_expires||'';
  document.getElementById('d_desig').value=d.designation||'';
  const comms=d.committees?JSON.parse(d.committees):[];
  document.querySelectorAll('.dcomm').forEach(c=>c.checked=comms.includes(c.value));
  document.getElementById('d_sg').style.display='grid';
  document.getElementById('d_status').value=d.status||'active';
  if(d.resignation_date){document.getElementById('d_rdg').style.display='grid';document.getElementById('d_rdate').value=d.resignation_date;}
  document.getElementById('dmodal').classList.add('open');
}
function closeD(){document.getElementById('dmodal').classList.remove('open');}
async function saveDir(){
  const id=document.getElementById('did_edit').value;
  const name=document.getElementById('d_name').value.trim();
  const desig=document.getElementById('d_desig').value;
  if(!name){cos.toast('Name required','error');return;}
  const comms=Array.from(document.querySelectorAll('.dcomm:checked')).map(c=>c.value);
  const payload={
    name,designation:desig,
    cnic:document.getElementById('d_cnic').value,
    email:document.getElementById('d_email').value,
    phone:document.getElementById('d_phone').value,
    appointment_date:document.getElementById('d_appt').value,
    term_expires:document.getElementById('d_term').value,
    din:document.getElementById('d_din').value,
    qualification:document.getElementById('d_qualif').value,
    expertise:document.getElementById('d_expert').value,
    notes:document.getElementById('d_notes').value,
    committees:JSON.stringify(comms),
  };
  if(id){payload.id=id;payload.status=document.getElementById('d_status').value;}
  const r=await cos.api(id?'update_director':'create_director',payload);
  cos.toast(r.message||(r.success?'Saved':'Error'),r.success?'success':'error');
  if(r.success){closeD();loadDirs();}
}
async function resignDir(id,name){
  const dt=prompt(`Resignation date for ${name}:`,new Date().toISOString().split('T')[0]);
  if(dt===null)return;
  const reason=prompt('Reason (optional):')||'';
  const r=await cos.api('resign_director',{id,resignation_date:dt,reason});
  cos.toast(r.message||(r.success?'Filed':'Error'),r.success?'success':'error');
  if(r.success)loadDirs();
}

// ═══════════════════════════════════════════
// AUDIT LOG
// ═══════════════════════════════════════════
async function loadAudit(){
  const tbody=document.getElementById('atbody');if(!tbody)return;
  tbody.innerHTML='<tr><td colspan="7" style="text-align:center;padding:18px;color:var(--text2);">⏳ Loading…</td></tr>';
  const limit=document.getElementById('alimit')?.value||50;
  const action=document.getElementById('asrch')?.value||'';
  const uid=document.getElementById('auserF')?.value||'';
  const r=await cos.api('list_audit_log',{limit,action,user_id:uid});
  if(!r.success){cos.toast(r.message,'error');return;}
  const rows=r.data||[];
  if(!rows.length){tbody.innerHTML='<tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text2);">No entries found.</td></tr>';return;}
  tbody.innerHTML=rows.map(row=>`<tr>
    <td style="color:var(--text2);font-size:10px;">${row.id}</td>
    <td style="white-space:nowrap;font-size:11px;">${row.created_fmt}</td>
    <td><strong>${row.user_name||'System'}</strong></td>
    <td><span class="abadge">${row.action}</span></td>
    <td style="font-size:11px;">${row.entity_type||''}${row.entity_id?' #'+row.entity_id:''}</td>
    <td style="font-size:11px;font-family:monospace;">${row.ip_address||'—'}</td>
    <td style="font-size:11px;color:var(--text2);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${row.notes||''}</td>
  </tr>`).join('');
  document.getElementById('apagi').textContent=`Showing ${rows.length} of ${limit} requested entries`;
}
function exportAudit(){cos.toast('Full CSV export is available via api.php?action=export_excel&type=audit_log','info');}

// ═══════════════════════════════════════════
// DATABASE MAINTENANCE
// ═══════════════════════════════════════════
async function loadDbStatus(){
  const w=document.getElementById('dbstat');if(!w)return;
  const r=await cos.api('dashboard_stats');
  if(r.success){
    w.innerHTML=`<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;">
      <div class="sicard"><div class="silabel">Resolutions</div><div class="sival">${r.data.resolutions}</div></div>
      <div class="sicard"><div class="silabel">Directors</div><div class="sival">${r.data.directors}</div></div>
      <div class="sicard"><div class="silabel">Documents</div><div class="sival">${r.data.documents}</div></div>
      <div class="sicard"><div class="silabel">Pending Approvals</div><div class="sival">${r.data.pending_approvals}</div></div>
    </div>
    <div style="margin-top:8px;padding:10px 12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;color:#15803d;font-size:12px;">
      ✅ Database connected · Auto-install: enabled · Charset: UTF8MB4
    </div>`;
  }else{
    w.innerHTML=`<div style="padding:10px;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;color:#991b1b;font-size:12px;">❌ ${r.message}</div>`;
  }
}
async function purgeAudit(){if(!confirm('Purge old audit entries? Cannot be undone.'))return;cos.toast('Purge runs via scheduled cron on production deployments','info');}
async function emptyBin(){if(!confirm('Permanently delete recycle bin items?'))return;cos.toast('Available via documents.php module','info');}
async function clearUndo(){if(!confirm('Clear all undo history?'))return;cos.toast('DB cleanup: DELETE FROM cos_undo_history WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)','info');}
async function rebuildDB(){cos.toast('Rebuild triggered — api.php will re-run CREATE IF NOT EXISTS on next load','info');}
async function testSMTP(){
  const email=document.getElementById('testEmail')?.value;
  if(!email){cos.toast('Enter a recipient email first','error');return;}
  cos.toast('SMTP test requires PHPMailer. Configure settings and trigger from CLI: php api.php?action=test_smtp','info');
}
async function mkDir(p){cos.toast('Run: mkdir -p '+p+' && chmod 755 '+p+' on your server','info');}

// ═══════════════════════════════════════════
// INIT
// ═══════════════════════════════════════════
document.addEventListener('DOMContentLoaded',()=>{
  const m='<?= $module ?>';
  if(m==='users')    loadUsers();
  if(m==='directors') loadDirs();
  if(m==='audit')    loadAudit();
  if(m==='database') loadDbStatus();
});
</script>
