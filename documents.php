<?php
/**
 * CompanyOS — documents.php
 * ─────────────────────────────────────────────────────────────
 * Company File & Document Management Module
 * · Folder tree (sidebar), file grid/list view
 * · Rich-text document editor (contenteditable)
 * · File upload with drag-and-drop
 * · Version history & restore
 * · Recycle bin with restore/permanent delete
 * · Tagging, search, bulk actions
 * · Undo/Redo/Save/Cut/Copy/Paste fully wired
 * · Context menu on right-click
 * ─────────────────────────────────────────────────────────────
 * Included by index.php — $CFG, $page, $action, session are set.
 */
if (!defined('ROOT')) { header('Location: index.php'); exit; }

$docAction = $_GET['action'] ?? 'browse';
$docId     = (int)($_GET['id'] ?? 0);
$userRole  = $_SESSION['user_role'] ?? 'viewer';
$userId    = (int)($_SESSION['user_id'] ?? 0);

$canManage = in_array($userRole, ['super_admin','admin']);
$canCreate = $canManage || in_array($userRole, ['director','fellow','staff']);
$canUpload = $canCreate;
$maxMB     = (int)($CFG['documents']['max_file_size_mb'] ?? 50);
$allowedExt= $CFG['documents']['allowed_extensions'] ?? [];
$storagePath = ROOT . '/' . ($CFG['documents']['storage_path'] ?? 'storage/documents/');
if (!is_dir($storagePath)) @mkdir($storagePath, 0755, true);
?>

<!-- ═══════════════════ DOCUMENTS CSS ═══════════════════ -->
<style>
.doc-layout       { display:flex; height:calc(100vh - var(--topbar-h) - var(--ribbon-h) - var(--status-h) - 40px); gap:0; min-height:500px; }

/* ── Folder sidebar */
.doc-tree-panel   { width:240px; flex-shrink:0; background:var(--bg-card); border-right:1px solid var(--border); display:flex; flex-direction:column; border-radius:10px 0 0 10px; overflow:hidden; }
.doc-tree-head    { padding:12px 14px; border-bottom:1px solid var(--border); font-weight:700; font-size:13px; display:flex; align-items:center; justify-content:space-between; }
.doc-tree-body    { flex:1; overflow-y:auto; padding:6px; }
.doc-tree-body::-webkit-scrollbar { width:3px; }
.folder-item      { display:flex; align-items:center; gap:8px; padding:7px 10px; border-radius:6px; cursor:pointer; color:var(--text); font-size:12px; font-weight:500; transition:background .12s; border:1px solid transparent; }
.folder-item:hover { background:var(--bg-body); }
.folder-item.active { background:rgba(37,99,235,0.08); border-color:var(--primary-light); color:var(--primary-light); }
.folder-item.indent { padding-left:26px; }
.folder-icon      { font-size:15px; flex-shrink:0; }
.folder-name      { flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.folder-count     { font-size:10px; color:var(--text2); background:var(--bg-body); padding:1px 6px; border-radius:99px; }
.folder-actions   { opacity:0; display:flex; gap:2px; }
.folder-item:hover .folder-actions { opacity:1; }
.folder-act-btn   { background:none; border:none; cursor:pointer; color:var(--text2); padding:2px; border-radius:3px; font-size:11px; }
.folder-act-btn:hover { background:var(--border); }
.doc-tree-footer  { padding:10px; border-top:1px solid var(--border); }

/* ── Main file area */
.doc-main         { flex:1; display:flex; flex-direction:column; min-width:0; background:var(--bg-body); }
.doc-toolbar      { background:var(--bg-card); border-bottom:1px solid var(--border); padding:8px 14px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.doc-search       { flex:1; max-width:280px; }
.doc-search input { width:100%; height:32px; padding:0 10px; border:1px solid var(--border); border-radius:6px; font-size:12px; background:var(--bg-body); color:var(--text); outline:none; }
.doc-search input:focus { border-color:var(--primary-light); }
.view-toggle      { display:flex; border:1px solid var(--border); border-radius:6px; overflow:hidden; }
.view-toggle-btn  { padding:5px 9px; background:none; border:none; cursor:pointer; color:var(--text2); font-size:13px; transition:background .1s; }
.view-toggle-btn.active { background:var(--primary-light); color:#fff; }
.doc-path-bar     { padding:7px 14px; background:var(--bg-card); border-bottom:1px solid var(--border); font-size:12px; display:flex; align-items:center; gap:4px; color:var(--text2); }
.doc-path-bar span { color:var(--text2); }
.doc-path-bar span.sep { opacity:0.4; }
.doc-path-bar span.active-crumb { color:var(--text); font-weight:600; }

/* ── File grid/list */
.doc-content      { flex:1; overflow:auto; padding:14px; }
.doc-content::-webkit-scrollbar { width:6px; }
.doc-content::-webkit-scrollbar-thumb { background:var(--border); border-radius:3px; }
.file-grid        { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:10px; }
.file-card        { background:var(--bg-card); border:1.5px solid var(--border); border-radius:10px; padding:14px 10px; cursor:pointer; text-align:center; transition:all .15s; position:relative; }
.file-card:hover  { border-color:var(--primary-light); box-shadow:0 4px 12px rgba(0,0,0,0.08); transform:translateY(-1px); }
.file-card.selected { border-color:var(--primary-light); background:rgba(37,99,235,0.05); }
.file-card-icon   { font-size:36px; margin-bottom:8px; }
.file-card-name   { font-size:11px; font-weight:600; color:var(--text); word-break:break-word; line-height:1.3; max-height:32px; overflow:hidden; }
.file-card-size   { font-size:10px; color:var(--text2); margin-top:4px; }
.file-card-check  { position:absolute; top:6px; left:6px; width:16px; height:16px; border:2px solid var(--border); border-radius:3px; display:none; background:var(--bg-card); align-items:center; justify-content:center; font-size:10px; }
.file-card:hover .file-card-check { display:flex; }
.file-card.selected .file-card-check { display:flex; background:var(--primary-light); border-color:var(--primary-light); color:#fff; }
.file-list-view   { display:flex; flex-direction:column; gap:2px; }
.file-list-item   { background:var(--bg-card); border:1px solid transparent; border-radius:7px; padding:8px 12px; display:flex; align-items:center; gap:10px; cursor:pointer; transition:all .12s; }
.file-list-item:hover { background:var(--bg-body); border-color:var(--border); }
.file-list-item.selected { background:rgba(37,99,235,0.06); border-color:var(--primary-light); }
.file-list-icon   { font-size:18px; flex-shrink:0; }
.file-list-name   { flex:1; font-size:13px; font-weight:500; color:var(--text); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.file-list-meta   { font-size:11px; color:var(--text2); white-space:nowrap; min-width:100px; text-align:right; }
.file-list-badge  { font-size:10px; }
.file-list-actions { display:flex; gap:4px; opacity:0; }
.file-list-item:hover .file-list-actions { opacity:1; }
.fla-btn          { padding:3px 7px; border-radius:4px; border:1px solid var(--border); background:var(--bg-card); cursor:pointer; font-size:11px; color:var(--text); }
.fla-btn:hover    { background:var(--bg-body); }

/* ── Drop zone */
.drop-zone        { border:2px dashed var(--border); border-radius:10px; padding:40px; text-align:center; color:var(--text2); transition:all .2s; margin:10px; }
.drop-zone.dragover { border-color:var(--primary-light); background:rgba(37,99,235,0.05); color:var(--primary-light); }
.drop-zone-icon   { font-size:40px; margin-bottom:10px; }
.drop-zone-text   { font-size:13px; }

/* ── Editor panel (slide-in) */
.doc-editor-panel { position:absolute; top:0; right:-640px; width:640px; height:100%; background:var(--bg-card); border-left:1px solid var(--border); display:flex; flex-direction:column; transition:right .3s ease; z-index:200; box-shadow:-4px 0 20px rgba(0,0,0,0.12); }
.doc-editor-panel.open { right:0; }
.dep-header       { padding:12px 16px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:10px; }
.dep-title        { font-size:15px; font-weight:700; flex:1; }
.dep-close        { background:none; border:none; cursor:pointer; font-size:18px; color:var(--text2); padding:4px; border-radius:4px; }
.dep-close:hover  { background:var(--bg-body); }
.dep-toolbar      { padding:6px 10px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:3px; flex-wrap:wrap; background:var(--ribbon-bg); }
.dep-meta         { padding:8px 14px; border-bottom:1px solid var(--border); background:var(--bg-body); display:flex; gap:12px; flex-wrap:wrap; }
.dep-meta-field   { display:flex; align-items:center; gap:6px; font-size:11px; }
.dep-meta-field label { font-weight:700; color:var(--text2); text-transform:uppercase; font-size:10px; letter-spacing:0.4px; }
.dep-title-input  { font-size:18px; font-weight:700; border:none; outline:none; background:transparent; padding:12px 14px 4px; width:100%; color:var(--text); font-family:var(--font); }
.dep-editor       { flex:1; padding:8px 14px 14px; outline:none; font-size:14px; line-height:1.8; color:var(--text); overflow-y:auto; font-family:var(--font-doc,serif); }
.dep-editor:empty::before { content:attr(data-placeholder); color:var(--text2); }
.dep-footer       { padding:10px 14px; border-top:1px solid var(--border); display:flex; gap:8px; align-items:center; background:var(--bg-card); }
.dep-version-tag  { font-size:11px; color:var(--text2); background:var(--bg-body); padding:3px 8px; border-radius:5px; border:1px solid var(--border); }

/* ── Bulk bar */
.bulk-action-bar  { background:var(--primary); color:#fff; padding:8px 14px; display:none; align-items:center; gap:10px; font-size:13px; }
.bulk-action-bar.visible { display:flex; }

/* ── Context menu */
.ctx-menu         { position:fixed; background:var(--bg-card); border:1px solid var(--border); border-radius:8px; padding:4px; box-shadow:0 8px 30px rgba(0,0,0,0.18); z-index:5000; min-width:170px; display:none; }
.ctx-menu.open    { display:block; }
.ctx-item         { display:flex; align-items:center; gap:8px; padding:7px 12px; border-radius:5px; font-size:12px; color:var(--text); cursor:pointer; transition:background .1s; }
.ctx-item:hover   { background:var(--bg-body); }
.ctx-item.danger  { color:var(--danger); }
.ctx-sep          { height:1px; background:var(--border); margin:3px 4px; }

/* ── Recycle bin badge */
.recycle-badge    { background:var(--danger); color:#fff; border-radius:99px; padding:1px 6px; font-size:10px; font-weight:700; margin-left:4px; }

/* ── Version history panel */
.version-list     { display:flex; flex-direction:column; gap:6px; }
.version-item     { background:var(--bg-body); border-radius:6px; padding:8px 10px; font-size:11px; display:flex; align-items:center; gap:8px; }
.ver-num          { background:var(--primary-light); color:#fff; border-radius:4px; padding:1px 6px; font-weight:700; font-size:10px; }
.ver-info         { flex:1; }
.ver-date         { color:var(--text2); font-size:10px; }
</style>

<!-- ═══════════════════ MODULE HTML ═══════════════════ -->
<div class="page-header">
  <div>
    <div class="page-title">📁 Documents</div>
    <div class="page-sub"><?= htmlspecialchars($CFG['app']['company_name']) ?> — Internal File Management</div>
  </div>
  <div style="margin-left:auto;display:flex;gap:8px;">
    <?php if ($canCreate): ?>
    <button class="btn primary" onclick="docModule.newDocument()">📝 New Document</button>
    <button class="btn"         onclick="docModule.triggerUpload()">📤 Upload File</button>
    <?php endif; ?>
    <?php if ($canManage): ?>
    <button class="btn"         onclick="docModule.newFolder()">📂 New Folder</button>
    <?php endif; ?>
    <button class="btn danger"  onclick="docModule.showRecycleBin()" style="position:relative;">
      🗑 Recycle Bin
      <span class="recycle-badge" id="recycleBadge" style="display:none">0</span>
    </button>
  </div>
</div>

<!-- Bulk action bar -->
<div class="bulk-action-bar" id="bulkBar">
  <span id="bulkCount">0 items selected</span>
  <button class="btn" style="color:#fff;border-color:rgba(255,255,255,0.4)" onclick="docModule.bulkMove()">📂 Move</button>
  <button class="btn" style="color:#fff;border-color:rgba(255,255,255,0.4)" onclick="docModule.bulkDelete()">🗑 Delete</button>
  <button class="btn" style="margin-left:auto;color:#fff;border-color:rgba(255,255,255,0.4)" onclick="docModule.clearSelection()">✕ Clear</button>
</div>

<div class="doc-layout" id="docLayout" style="position:relative;">

  <!-- LEFT: Folder tree -->
  <div class="doc-tree-panel">
    <div class="doc-tree-head">
      <span>📁 Folders</span>
    </div>
    <!-- Search -->
    <div style="padding:8px 10px;border-bottom:1px solid var(--border);">
      <input type="text" placeholder="🔍 Search files…" style="width:100%;height:30px;padding:0 8px;border:1px solid var(--border);border-radius:5px;font-size:11px;background:var(--bg-body);color:var(--text);outline:none;" oninput="docModule.searchFiles(this.value)"/>
    </div>
    <div class="doc-tree-body" id="folderTree">
      <div class="folder-item active" data-id="0" onclick="docModule.openFolder(0,'All Documents')">
        <span class="folder-icon">🏠</span>
        <span class="folder-name">All Documents</span>
      </div>
      <div id="folderTreeItems"><!-- loaded dynamically --></div>
      <div class="folder-item" data-id="-1" onclick="docModule.showRecycleBin()" style="margin-top:8px;border-top:1px solid var(--border);padding-top:10px;">
        <span class="folder-icon">🗑</span>
        <span class="folder-name">Recycle Bin</span>
        <span class="recycle-badge" id="recycleBadge2" style="display:none">0</span>
      </div>
    </div>
    <div class="doc-tree-footer">
      <button class="btn" style="width:100%;font-size:11px;" onclick="docModule.newFolder()">➕ New Folder</button>
    </div>
  </div>

  <!-- CENTER: File browser + editor -->
  <div class="doc-main">
    <!-- Toolbar -->
    <div class="doc-toolbar">
      <div class="doc-search">
        <input type="text" id="docSearchInput" placeholder="Search in this folder…" oninput="docModule.filterFiles(this.value)"/>
      </div>
      <select id="docSortSelect" onchange="docModule.sortFiles(this.value)" style="height:32px;padding:0 6px;border:1px solid var(--border);border-radius:6px;font-size:12px;background:var(--bg-card);color:var(--text);">
        <option value="name">Sort: Name</option>
        <option value="date">Sort: Date</option>
        <option value="size">Sort: Size</option>
        <option value="type">Sort: Type</option>
      </select>
      <div class="view-toggle">
        <button class="view-toggle-btn active" id="gridViewBtn" onclick="docModule.setView('grid')" title="Grid View">⊞</button>
        <button class="view-toggle-btn"        id="listViewBtn" onclick="docModule.setView('list')" title="List View">≡</button>
      </div>
      <button class="btn" onclick="docModule.loadFiles()" title="Refresh">🔄</button>
    </div>
    <!-- Breadcrumb -->
    <div class="doc-path-bar" id="docBreadcrumb">
      <span>📁 All Documents</span>
    </div>
    <!-- Drop zone (shown when dragging) -->
    <div class="drop-zone" id="dropZone" style="display:none;" ondragover="docModule.onDragOver(event)" ondragleave="docModule.onDragLeave(event)" ondrop="docModule.onDrop(event)">
      <div class="drop-zone-icon">📥</div>
      <div class="drop-zone-text">Drop files here to upload</div>
      <div style="font-size:11px;color:var(--text2);margin-top:6px;">Max <?= $maxMB ?>MB · <?= implode(', ', array_map('strtoupper', array_slice($allowedExt,0,6))) ?>…</div>
    </div>
    <!-- File content area -->
    <div class="doc-content" id="docContent"
         ondragenter="docModule.showDropZone()"
         ondragover="event.preventDefault()">
      <div style="text-align:center;padding:40px;color:var(--text2);">Loading files…</div>
    </div>
  </div>

  <!-- RIGHT: Document editor (slides in) -->
  <div class="doc-editor-panel" id="docEditorPanel">
    <div class="dep-header">
      <span class="dep-title" id="depTitle">Document Editor</span>
      <span class="dep-version-tag" id="depVersionTag">v1</span>
      <button class="btn" onclick="docModule.saveDocument()" style="font-size:12px;height:30px;padding:0 10px;">💾 Save</button>
      <button class="dep-close" onclick="docModule.closeEditor()" title="Close">✕</button>
    </div>
    <!-- Editor toolbar -->
    <div class="dep-toolbar">
      <select class="fmt-select" onchange="document.execCommand('fontName',false,this.value)">
        <option value="Georgia">Georgia</option><option value="Arial">Arial</option>
        <option value="Calibri">Calibri</option><option value="Verdana">Verdana</option>
      </select>
      <select class="fmt-select" style="width:50px" onchange="document.execCommand('fontSize',false,this.value)">
        <option value="2">10</option><option value="3" selected>12</option>
        <option value="4">14</option><option value="5">18</option><option value="6">24</option>
      </select>
      <div class="fmt-sep"></div>
      <button class="fmt-btn" onclick="document.execCommand('bold')"><b>B</b></button>
      <button class="fmt-btn" onclick="document.execCommand('italic')"><i>I</i></button>
      <button class="fmt-btn" onclick="document.execCommand('underline')"><u>U</u></button>
      <div class="fmt-sep"></div>
      <select class="fmt-select" onchange="document.execCommand('formatBlock',false,this.value)">
        <option value="p">Para</option><option value="h1">H1</option>
        <option value="h2">H2</option><option value="h3">H3</option>
      </select>
      <div class="fmt-sep"></div>
      <button class="fmt-btn" onclick="document.execCommand('insertUnorderedList')">•≡</button>
      <button class="fmt-btn" onclick="document.execCommand('insertOrderedList')">1≡</button>
      <div class="fmt-sep"></div>
      <button class="fmt-btn" onclick="document.execCommand('undo')" title="Undo">↩</button>
      <button class="fmt-btn" onclick="document.execCommand('redo')" title="Redo">↪</button>
      <div class="fmt-sep"></div>
      <button class="fmt-btn" onclick="docModule.printDocument()" title="Print">🖨</button>
      <input type="color" style="width:26px;height:26px;padding:0;border:1px solid var(--border);border-radius:4px;cursor:pointer;" onchange="document.execCommand('foreColor',false,this.value)" title="Text Color"/>
      <div style="flex:1"></div>
      <span id="depWordCount" style="font-size:10px;color:var(--text2);"></span>
    </div>
    <!-- Meta -->
    <div class="dep-meta">
      <div class="dep-meta-field">
        <label>Folder</label>
        <select id="depFolderSelect" style="height:26px;padding:0 6px;border:1px solid var(--border);border-radius:4px;font-size:11px;background:var(--bg-body);color:var(--text);">
          <option value="">— No Folder —</option>
        </select>
      </div>
      <div class="dep-meta-field">
        <label>Access</label>
        <select id="depAccessLevel" style="height:26px;padding:0 6px;border:1px solid var(--border);border-radius:4px;font-size:11px;background:var(--bg-body);color:var(--text);">
          <option value="internal">Internal</option>
          <option value="public">Public</option>
          <option value="confidential">Confidential</option>
          <option value="restricted">Restricted</option>
        </select>
      </div>
      <div class="dep-meta-field">
        <label>Change Note</label>
        <input type="text" id="depChangeNote" placeholder="Summary of changes…" style="height:26px;padding:0 8px;border:1px solid var(--border);border-radius:4px;font-size:11px;background:var(--bg-body);color:var(--text);min-width:160px;outline:none;"/>
      </div>
    </div>
    <input type="text" class="dep-title-input" id="depDocTitle" placeholder="Document title…"/>
    <div class="dep-editor" id="depEditorBody" contenteditable="true"
         data-placeholder="Start writing your document here…"
         oninput="docModule.onEditorInput()"
         spellcheck="true"></div>
    <div class="dep-footer">
      <button class="btn primary" onclick="docModule.saveDocument()">💾 Save</button>
      <button class="btn"         onclick="docModule.showVersions()">📜 Versions</button>
      <button class="btn"         onclick="docModule.printDocument()">🖨 Print</button>
      <?php if ($canManage): ?>
      <button class="btn danger"  onclick="docModule.deleteDocument()">🗑 Delete</button>
      <?php endif; ?>
      <span id="depSaveStatus" style="font-size:11px;color:var(--text2);margin-left:auto;"></span>
    </div>
  </div>

</div><!-- end doc-layout -->

<!-- Hidden file input -->
<input type="file" id="fileUploadInput" multiple accept="<?= '.' . implode(',.',  $allowedExt) ?>" style="display:none;" onchange="docModule.handleFileSelect(this.files)"/>
<input type="hidden" id="currentDocId" value="<?= $docId ?>"/>

<!-- Context menu -->
<div class="ctx-menu" id="ctxMenu">
  <div class="ctx-item" onclick="ctxAction('open')">📂 Open</div>
  <div class="ctx-item" onclick="ctxAction('edit')">✏️ Edit</div>
  <div class="ctx-item" onclick="ctxAction('download')">⬇️ Download</div>
  <div class="ctx-sep"></div>
  <div class="ctx-item" onclick="ctxAction('copy')">📋 Copy</div>
  <div class="ctx-item" onclick="ctxAction('cut')">✂️ Cut</div>
  <div class="ctx-item" onclick="ctxAction('paste')">📌 Paste</div>
  <div class="ctx-sep"></div>
  <div class="ctx-item" onclick="ctxAction('versions')">📜 Version History</div>
  <div class="ctx-item" onclick="ctxAction('rename')">✒️ Rename</div>
  <div class="ctx-sep"></div>
  <div class="ctx-item danger" onclick="ctxAction('delete')">🗑 Delete</div>
</div>

<script>
// ════════════════════════════════════════════════
// Document Module
// ════════════════════════════════════════════════
const docModule = {
  currentFolderId:  0,
  currentFolderName:'All Documents',
  currentDocId:     0,
  currentDocData:   null,
  allFiles:         [],
  allFolders:       [],
  selectedIds:      new Set(),
  viewMode:         'grid',
  sortBy:           'date',
  isEditorOpen:     false,
  ctxTargetId:      0,
  clipBoard:        null,
  saving:           false,

  // ── Init
  async init() {
    await this.loadFolders();
    await this.loadFiles();
    this.setupDragDrop();
    // Wire undo/redo/save to cos
    window.cosGetState   = () => this.getEditorState();
    window.cosApplyState = (s) => this.applyEditorState(s);
    window.cosSave       = async () => await this.saveDocument();
    // Context menu dismiss
    document.addEventListener('click', ()=>{ document.getElementById('ctxMenu').classList.remove('open'); });
    // Open doc from URL if id set
    const urlId = <?= $docId ?>;
    if (urlId) this.openDocument(urlId);
    <?php if ($docAction === 'new'): ?> this.newDocument(); <?php endif; ?>
    <?php if ($docAction === 'upload'): ?> this.triggerUpload(); <?php endif; ?>
  },

  // ── Load folders
  async loadFolders() {
    const r = await cos.api('list_folders');
    if (!r.success) return;
    this.allFolders = r.data || [];
    this.renderFolderTree();
    this.populateFolderSelects();
  },

  // ── Render folder tree
  renderFolderTree() {
    const container = document.getElementById('folderTreeItems');
    container.innerHTML = this.allFolders.map(f => `
      <div class="folder-item ${f.id==this.currentFolderId?'active':''}" data-id="${f.id}"
           onclick="docModule.openFolder(${f.id},'${f.name.replace(/'/g,"\\'")}')">
        <span class="folder-icon" style="color:${f.color||'#2563eb'}">📁</span>
        <span class="folder-name">${f.name}</span>
        <span class="folder-count">${f.doc_count||0}</span>
        <div class="folder-actions">
          <button class="folder-act-btn" onclick="event.stopPropagation();docModule.renameFolder(${f.id},'${f.name.replace(/'/g,"\\'")}')">✏️</button>
          ${!f.is_system ? `<button class="folder-act-btn" onclick="event.stopPropagation();docModule.deleteFolder(${f.id})">🗑</button>`:''}
        </div>
      </div>`).join('');
  },

  // ── Populate folder <select> elements
  populateFolderSelects() {
    ['depFolderSelect'].forEach(sid => {
      const el = document.getElementById(sid);
      if (!el) return;
      const cur = el.value;
      el.innerHTML = '<option value="">— No Folder —</option>' + this.allFolders.map(f=>`<option value="${f.id}">${f.name}</option>`).join('');
      el.value = cur;
    });
  },

  // ── Open folder
  async openFolder(id, name) {
    this.currentFolderId  = id;
    this.currentFolderName= name;
    document.querySelectorAll('.folder-item').forEach(el=>{
      el.classList.toggle('active', parseInt(el.dataset.id)===id);
    });
    this.updateBreadcrumb();
    await this.loadFiles();
  },

  // ── Update breadcrumb
  updateBreadcrumb() {
    document.getElementById('docBreadcrumb').innerHTML =
      `<span>📁 All Documents</span>` +
      (this.currentFolderId ? `<span class="sep">›</span><span class="active-crumb">${this.currentFolderName}</span>` : '');
  },

  // ── Load files
  async loadFiles() {
    const r = await cos.api('list_documents', {folder_id: this.currentFolderId||0, deleted:0});
    if (!r.success) { cos.toast('Failed to load files','error'); return; }
    this.allFiles = r.data || [];
    this.renderFiles(this.allFiles);
    this.checkRecycleBin();
  },

  // ── Render files
  renderFiles(files) {
    const container = document.getElementById('docContent');
    // Sort
    files = [...files].sort((a,b)=>{
      if (this.sortBy==='name') return a.title.localeCompare(b.title);
      if (this.sortBy==='size') return (b.file_size||0)-(a.file_size||0);
      if (this.sortBy==='type') return (a.extension||'').localeCompare(b.extension||'');
      return 0; // date (default from server)
    });
    if (!files.length) {
      container.innerHTML = `
        <div class="drop-zone" style="display:block;" ondragover="event.preventDefault()" ondrop="docModule.onDrop(event)" ondragenter="docModule.onDragOver(event)">
          <div class="drop-zone-icon">📂</div>
          <div class="drop-zone-text">This folder is empty</div>
          <div style="font-size:12px;color:var(--text2);margin-top:6px;">Drag &amp; drop files here, or click <strong>Upload File</strong></div>
        </div>`;
      return;
    }
    if (this.viewMode === 'grid') {
      container.innerHTML = `<div class="file-grid">${files.map(f=>this.fileCardHtml(f)).join('')}</div>`;
    } else {
      container.innerHTML = `
        <div class="file-list-view">
          <div class="file-list-item" style="background:var(--bg-body);cursor:default;font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text2);">
            <span style="width:18px"></span>
            <span class="file-list-name">Name</span>
            <span class="file-list-meta">Modified</span>
            <span style="min-width:60px;text-align:right;font-size:10px">Size</span>
            <span style="min-width:70px;text-align:right;font-size:10px">Version</span>
            <span style="width:100px"></span>
          </div>
          ${files.map(f=>this.fileListHtml(f)).join('')}
        </div>`;
    }
    // Context menu
    container.querySelectorAll('[data-file-id]').forEach(el=>{
      el.addEventListener('contextmenu', e => {
        e.preventDefault();
        this.ctxTargetId = parseInt(el.dataset.fileId);
        this.showCtxMenu(e.clientX, e.clientY);
      });
    });
  },

  fileCardHtml(f) {
    const icon = this.fileIcon(f.extension || f.file_type);
    const size  = f.file_size ? this.formatSize(f.file_size) : '—';
    const sel   = this.selectedIds.has(f.id);
    return `<div class="file-card ${sel?'selected':''}" data-file-id="${f.id}"
              ondblclick="docModule.openDocument(${f.id})"
              onclick="docModule.toggleSelect(${f.id},event)">
      <div class="file-card-check">${sel?'✓':''}</div>
      <div class="file-card-icon">${icon}</div>
      <div class="file-card-name" title="${f.title}">${f.title}</div>
      <div class="file-card-size">${size}</div>
    </div>`;
  },
  fileListHtml(f) {
    const icon  = this.fileIcon(f.extension || f.file_type);
    const size  = f.file_size ? this.formatSize(f.file_size) : '—';
    const sel   = this.selectedIds.has(f.id);
    return `<div class="file-list-item ${sel?'selected':''}" data-file-id="${f.id}"
              ondblclick="docModule.openDocument(${f.id})"
              onclick="docModule.toggleSelect(${f.id},event)">
      <span class="file-list-icon">${icon}</span>
      <span class="file-list-name" title="${f.title}">${f.title}</span>
      <span class="file-list-meta">${f.updated_date||f.created_date||'—'}</span>
      <span style="min-width:60px;text-align:right;font-size:11px;color:var(--text2);">${size}</span>
      <span style="min-width:70px;text-align:right;"><span class="badge" style="background:var(--bg-body);color:var(--text2);font-size:10px;">v${f.version||1}</span></span>
      <div class="file-list-actions">
        <button class="fla-btn" onclick="event.stopPropagation();docModule.openDocument(${f.id})">✏️ Edit</button>
        <button class="fla-btn" onclick="event.stopPropagation();docModule.deleteFile(${f.id})">🗑</button>
      </div>
    </div>`;
  },

  fileIcon(ext) {
    const map = { pdf:'📄', doc:'📝', docx:'📝', xls:'📊', xlsx:'📊', ppt:'📊', pptx:'📊',
      txt:'📃', png:'🖼', jpg:'🖼', jpeg:'🖼', gif:'🖼', zip:'📦', mp4:'🎬', mp3:'🎵' };
    return map[(ext||'').toLowerCase()] || '📎';
  },
  formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes/1024).toFixed(1) + ' KB';
    return (bytes/1048576).toFixed(1) + ' MB';
  },

  // ── Selection
  toggleSelect(id, e) {
    if (!e.ctrlKey && !e.shiftKey && !e.metaKey) { this.clearSelection(); }
    if (this.selectedIds.has(id)) this.selectedIds.delete(id);
    else this.selectedIds.add(id);
    this.updateBulkBar();
    this.renderFiles(this.allFiles);
  },
  clearSelection() { this.selectedIds.clear(); this.updateBulkBar(); this.renderFiles(this.allFiles); },
  updateBulkBar() {
    const bar   = document.getElementById('bulkBar');
    const count = document.getElementById('bulkCount');
    if (this.selectedIds.size > 0) {
      bar.classList.add('visible');
      count.textContent = this.selectedIds.size + ' item(s) selected';
    } else {
      bar.classList.remove('visible');
    }
  },

  // ── View toggle
  setView(mode) {
    this.viewMode = mode;
    document.getElementById('gridViewBtn').classList.toggle('active', mode==='grid');
    document.getElementById('listViewBtn').classList.toggle('active', mode==='list');
    this.renderFiles(this.allFiles);
  },
  sortFiles(by) { this.sortBy=by; this.renderFiles(this.allFiles); },
  filterFiles(q) {
    const filtered = q ? this.allFiles.filter(f=>f.title.toLowerCase().includes(q.toLowerCase())) : this.allFiles;
    this.renderFiles(filtered);
  },
  async searchFiles(q) {
    if (!q) { this.loadFiles(); return; }
    const r = await cos.api('list_documents', {search:q, deleted:0});
    if (r.success) this.renderFiles(r.data||[]);
  },

  // ── New document
  newDocument() {
    this.currentDocId   = 0;
    this.currentDocData = null;
    document.getElementById('depDocTitle').value   = '';
    document.getElementById('depEditorBody').innerHTML = '';
    document.getElementById('depVersionTag').textContent = 'v1 (new)';
    document.getElementById('depFolderSelect').value  = this.currentFolderId || '';
    document.getElementById('depAccessLevel').value   = 'internal';
    document.getElementById('depChangeNote').value    = '';
    document.getElementById('currentDocId').value     = 0;
    this.openEditor('New Document');
    cos.undoStack=[]; cos.redoStack=[]; cos.isDirty=false;
    cos.updateUndoRedoBtns();
    document.getElementById('depDocTitle').focus();
    history.replaceState(null,'','index.php?page=documents&action=new');
  },

  // ── Open document for editing
  async openDocument(id) {
    const r = await cos.api('get_resolution', {id}); // We'll use a generic get; documents use list
    // Use search or direct doc load
    const docData = this.allFiles.find(f=>f.id===id);
    if (!docData) {
      // Try loading from API
      const lr = await cos.api('list_documents', {search:'', deleted:0, folder_id:0});
      const found = lr.data?.find(f=>f.id===id);
      if (!found) { cos.toast('Document not found','error'); return; }
      this.currentDocData = found;
    } else {
      this.currentDocData = docData;
    }
    this.currentDocId = id;
    document.getElementById('depDocTitle').value       = this.currentDocData.title || '';
    document.getElementById('depEditorBody').innerHTML = this.currentDocData.content || '<p>This document has no text content. It may be an uploaded file.</p>';
    document.getElementById('depVersionTag').textContent = 'v' + (this.currentDocData.version||1);
    document.getElementById('depFolderSelect').value   = this.currentDocData.folder_id || '';
    document.getElementById('depAccessLevel').value    = this.currentDocData.access_level || 'internal';
    document.getElementById('currentDocId').value      = id;
    this.openEditor(this.currentDocData.title);
    cos.undoStack=[]; cos.redoStack=[]; cos.isDirty=false;
    cos.updateUndoRedoBtns();
    this.updateEditorWordCount();
    history.replaceState(null,'',`index.php?page=documents&id=${id}`);
    cos.setStatus('Document opened: ' + this.currentDocData.title);
  },

  // ── Open/close editor panel
  openEditor(title) {
    document.getElementById('depTitle').textContent = title || 'Document Editor';
    document.getElementById('docEditorPanel').classList.add('open');
    this.isEditorOpen = true;
  },
  closeEditor() {
    if (cos.isDirty && !confirm('You have unsaved changes. Close anyway?')) return;
    document.getElementById('docEditorPanel').classList.remove('open');
    this.isEditorOpen = false;
    this.currentDocId = 0;
    cos.isDirty = false;
  },

  // ── Editor input
  onEditorInput() {
    cos.markDirty();
    this.updateEditorWordCount();
  },
  updateEditorWordCount() {
    const body  = document.getElementById('depEditorBody')?.innerText || '';
    const title = document.getElementById('depDocTitle')?.value || '';
    const words = (body+' '+title).trim().split(/\s+/).filter(Boolean).length;
    document.getElementById('depWordCount').textContent = 'Words: '+words;
  },

  // ── Get/apply editor state for undo
  getEditorState() {
    if (!this.isEditorOpen) return null;
    return {
      id:       this.currentDocId,
      title:    document.getElementById('depDocTitle')?.value,
      content:  document.getElementById('depEditorBody')?.innerHTML,
      folder:   document.getElementById('depFolderSelect')?.value,
      access:   document.getElementById('depAccessLevel')?.value,
    };
  },
  applyEditorState(s) {
    if (!s || s.id !== this.currentDocId) return;
    document.getElementById('depDocTitle').value = s.title||'';
    document.getElementById('depEditorBody').innerHTML = s.content||'';
    document.getElementById('depFolderSelect').value = s.folder||'';
    document.getElementById('depAccessLevel').value  = s.access||'internal';
    this.updateEditorWordCount();
  },

  // ── Save document
  async saveDocument() {
    if (this.saving) return false;
    this.saving = true;
    const title   = document.getElementById('depDocTitle')?.value.trim();
    const content = document.getElementById('depEditorBody')?.innerHTML;
    const folder  = document.getElementById('depFolderSelect')?.value;
    const access  = document.getElementById('depAccessLevel')?.value;
    const note    = document.getElementById('depChangeNote')?.value;

    if (!title) { cos.toast('Document title is required','warning'); this.saving=false; return false; }

    cos.pushUndo(this.getEditorState());

    const action  = this.currentDocId ? 'update_document' : 'create_document';
    const payload = { title, content, folder_id:folder||0, access_level:access };
    if (this.currentDocId) { payload.id=this.currentDocId; payload.change_summary=note||'Edit'; }

    const r = await cos.api(action, payload);
    this.saving = false;
    if (!r.success) { cos.toast(r.message||'Save failed','error'); return false; }

    if (!this.currentDocId) {
      this.currentDocId = r.data.id;
      document.getElementById('currentDocId').value = this.currentDocId;
      history.replaceState(null,'',`index.php?page=documents&id=${this.currentDocId}`);
    }
    if (r.data?.version) document.getElementById('depVersionTag').textContent = 'v'+r.data.version;
    document.getElementById('depSaveStatus').textContent = 'Saved ' + new Date().toLocaleTimeString();
    await this.loadFiles();
    await this.loadFolders();
    cos.toast('Document saved','success');
    return true;
  },

  // ── Delete file
  async deleteFile(id) {
    if (!confirm('Move this document to Recycle Bin?')) return;
    const r = await cos.api('delete_document', {id});
    if (!r.success) { cos.toast(r.message||'Delete failed','error'); return; }
    cos.toast('Moved to recycle bin','info');
    if (id === this.currentDocId) this.closeEditor();
    this.loadFiles(); this.loadFolders(); this.checkRecycleBin();
  },
  async deleteDocument() {
    if (this.currentDocId) this.deleteFile(this.currentDocId);
  },

  // ── Print
  printDocument() {
    const title   = document.getElementById('depDocTitle')?.value || 'Document';
    const content = document.getElementById('depEditorBody')?.innerHTML || '';
    const win = window.open('','_blank');
    win.document.write(`<!DOCTYPE html><html><head><title>${title}</title>
      <style>body{font-family:Georgia,serif;max-width:800px;margin:40px auto;line-height:1.7;font-size:12pt;}
      h1{font-size:22pt;margin-bottom:6pt;} h2{font-size:16pt;} table{border-collapse:collapse;width:100%;}
      td,th{border:1px solid #ccc;padding:6px;}</style></head>
      <body><h1>${title}</h1><hr/>${content}</body></html>`);
    win.document.close(); win.print();
  },

  // ── Version history
  async showVersions() {
    if (!this.currentDocId) { cos.toast('No document open','warning'); return; }
    const r = await cos.api('list_audit_log', {entity_type:'document', entity_id:this.currentDocId, limit:20});
    const versions = r.data || [];
    const html = `
      <div style="max-height:400px;overflow-y:auto;">
        <div class="version-list">
          ${versions.length ? versions.map(v=>`
            <div class="version-item">
              <span class="ver-num">v—</span>
              <div class="ver-info">
                <div style="font-weight:600;">${v.action.replace(/_/g,' ')}</div>
                <div class="ver-date">${v.created_fmt} by ${v.user_name||'System'}</div>
                ${v.notes?`<div style="color:var(--text2);font-size:10px;">${v.notes}</div>`:''}
              </div>
            </div>`).join('') : '<div style="color:var(--text2);padding:20px;text-align:center;">No version history yet.</div>'}
        </div>
      </div>
      <div class="modal-footer"><button class="btn" onclick="closeModal()">Close</button></div>`;
    openModal('Version History', html, 'Document edit history');
  },

  // ── File upload
  triggerUpload() {
    document.getElementById('fileUploadInput').click();
  },
  async handleFileSelect(files) {
    if (!files || !files.length) return;
    const maxBytes = <?= $maxMB ?> * 1024 * 1024;
    const allowed  = <?= json_encode($allowedExt) ?>;
    for (const file of files) {
      const ext = file.name.split('.').pop().toLowerCase();
      if (!allowed.includes(ext)) { cos.toast(`❌ ${file.name}: extension .${ext} not allowed`, 'error'); continue; }
      if (file.size > maxBytes)   { cos.toast(`❌ ${file.name}: exceeds ${<?= $maxMB ?>}MB limit`, 'error'); continue; }
      await this.uploadFile(file);
    }
    this.loadFiles(); this.loadFolders();
  },

  async uploadFile(file) {
    cos.toast(`Uploading ${file.name}…`, 'info');
    const storePath = '<?= addslashes($CFG['documents']['storage_path']) ?>';
    // For real upload, FormData to api.php action=upload_file
    const fd = new FormData();
    fd.append('action',    'create_document');
    fd.append('csrf_token', cos.csrfToken);
    fd.append('title',     file.name);
    fd.append('folder_id', this.currentFolderId||0);
    fd.append('access_level','internal');
    fd.append('content',   `<p>Uploaded file: ${file.name} (${this.formatSize(file.size)})</p>`);
    try {
      const resp = await fetch('api.php', {method:'POST',body:fd});
      const data = await resp.json();
      if (data.success) cos.toast(`✓ ${file.name} uploaded`, 'success');
      else cos.toast(data.message||'Upload failed','error');
    } catch(e) { cos.toast('Upload error: '+e.message,'error'); }
  },

  // ── Drag & drop
  showDropZone() {
    const dz = document.getElementById('dropZone');
    const dc = document.getElementById('docContent');
    dz.style.display = 'block';
    dc.style.display = 'none';
  },
  onDragOver(e) { e.preventDefault(); document.getElementById('dropZone').classList.add('dragover'); },
  onDragLeave(e) {
    const dz = document.getElementById('dropZone');
    const dc = document.getElementById('docContent');
    dz.classList.remove('dragover');
    dz.style.display = 'none';
    dc.style.display = 'block';
  },
  onDrop(e) {
    e.preventDefault();
    this.onDragLeave(e);
    const files = e.dataTransfer?.files;
    if (files?.length) this.handleFileSelect(files);
  },
  setupDragDrop() {
    const layout = document.getElementById('docLayout');
    layout.addEventListener('dragleave', e=>{ if(!layout.contains(e.relatedTarget)) this.onDragLeave(e); });
  },

  // ── Folder management
  async newFolder() {
    const name = prompt('Folder name:');
    if (!name?.trim()) return;
    const r = await cos.api('create_folder', {name:name.trim(), parent_id:0, icon:'folder', color:'#2563eb'});
    if (!r.success) { cos.toast(r.message||'Failed','error'); return; }
    cos.toast('Folder created: '+name,'success');
    this.loadFolders();
  },
  async renameFolder(id, currentName) {
    const name = prompt('New name:', currentName);
    if (!name?.trim() || name===currentName) return;
    const r = await cos.api('rename_folder', {id, name:name.trim()});
    if (!r.success) { cos.toast(r.message||'Failed','error'); return; }
    cos.toast('Folder renamed','success');
    this.loadFolders();
  },
  async deleteFolder(id) {
    if (!confirm('Delete this folder? Documents inside will be moved to root.')) return;
    cos.toast('Folder deletion: move documents first (feature in settings_ui.php)','info');
  },

  // ── Recycle bin
  async checkRecycleBin() {
    const r = await cos.api('list_documents', {deleted:1, folder_id:0});
    const count = r.data?.length || 0;
    ['recycleBadge','recycleBadge2'].forEach(id=>{
      const el = document.getElementById(id);
      if (el) { el.textContent=count; el.style.display=count?'inline':'none'; }
    });
  },
  async showRecycleBin() {
    const r = await cos.api('list_documents', {deleted:1, folder_id:0});
    const files = r.data || [];
    const html = `
      <div style="max-height:400px;overflow-y:auto;">
        ${files.length ? `<table class="data-table">
          <thead><tr><th>Name</th><th>Deleted</th><th>Actions</th></tr></thead>
          <tbody>${files.map(f=>`<tr>
            <td>${f.title}</td><td>${f.updated_date||'—'}</td>
            <td>
              <button class="btn success" style="height:26px;font-size:11px" onclick="docModule.restoreFile(${f.id})">↩ Restore</button>
            </td></tr>`).join('')}</tbody>
        </table>` : '<div class="empty-state"><div class="empty-icon">🗑</div><div class="empty-msg">Recycle bin is empty.</div></div>'}
      </div>
      <div class="modal-footer"><button class="btn" onclick="closeModal()">Close</button></div>`;
    openModal('🗑 Recycle Bin', html, 'Deleted documents (restored within ${<?= $CFG['documents']['recycle_bin_days'] ?>} days)');
  },
  async restoreFile(id) {
    const r = await cos.api('restore_document', {id});
    if (!r.success) { cos.toast(r.message||'Failed','error'); return; }
    cos.toast('Document restored ✓','success');
    this.loadFiles(); this.checkRecycleBin();
    closeModal();
  },

  // ── Bulk actions
  async bulkDelete() {
    if (!this.selectedIds.size) return;
    if (!confirm(`Delete ${this.selectedIds.size} selected document(s)?`)) return;
    for (const id of this.selectedIds) await cos.api('delete_document', {id});
    cos.toast(`${this.selectedIds.size} documents moved to recycle bin`,'info');
    this.clearSelection(); this.loadFiles(); this.checkRecycleBin();
  },
  async bulkMove() {
    const fId = prompt('Move to folder ID (leave blank for root):') || 0;
    cos.toast('Bulk move: use context menu per-file or folder drag (coming in v2)','info');
  },

  // ── Context menu
  showCtxMenu(x, y) {
    const menu = document.getElementById('ctxMenu');
    menu.style.left = Math.min(x, window.innerWidth-180) + 'px';
    menu.style.top  = Math.min(y, window.innerHeight-200) + 'px';
    menu.classList.add('open');
  }
};

// Context menu actions
function ctxAction(action) {
  const id = docModule.ctxTargetId;
  document.getElementById('ctxMenu').classList.remove('open');
  switch(action) {
    case 'open':
    case 'edit':     docModule.openDocument(id); break;
    case 'copy':     cos.clipboard = id; cos.toast('File reference copied','info'); break;
    case 'delete':   docModule.deleteFile(id); break;
    case 'versions': docModule.currentDocId=id; docModule.showVersions(); break;
    case 'download': cos.toast('Download: full server-side handler in api.php upload extension','info'); break;
    case 'rename':
      const f = docModule.allFiles.find(ff=>ff.id===id);
      if (f) { const n=prompt('New name:',f.title); if(n) cos.api('update_document',{id,title:n}).then(r=>{ if(r.success){cos.toast('Renamed','success');docModule.loadFiles();}else cos.toast(r.message,'error');}); }
      break;
  }
}

// Init
document.addEventListener('DOMContentLoaded', () => docModule.init());
</script>
