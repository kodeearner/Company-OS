<?php
/**
 * CompanyOS — resolutions.php
 * ─────────────────────────────────────────────────────────────
 * Full Resolution Management Module
 * · List view with filters, search, sorting
 * · Rich-text create/edit editor (contenteditable)
 * · Approval workflow: submit → vote → auto-resolve by quorum
 * · Undo/Redo/Save/Cut/Copy/Paste fully wired
 * · Print & export stubs
 * · Comments & version history panel
 * ─────────────────────────────────────────────────────────────
 * Included by index.php — $CFG, $page, $action, session are set.
 */
if (!defined('ROOT')) { header('Location: index.php'); exit; }

$resAction = $_GET['action'] ?? 'list';
$resId     = (int)($_GET['id'] ?? 0);
$userRole  = $_SESSION['user_role'] ?? 'viewer';
$userId    = (int)($_SESSION['user_id'] ?? 0);

// Permission shortcuts
$canManage  = in_array($userRole, ['super_admin','admin']);
$canCreate  = $canManage || in_array($userRole, ['director','staff']);
$canApprove = in_array($userRole, ['super_admin','admin','director','fellow']);

// Resolution type/status config
$resTypes    = $CFG['resolutions']['types']    ?? [];
$resStatuses = $CFG['resolutions']['statuses'] ?? [];
?>

<!-- ═══════════════════ RESOLUTION MODULE CSS ═══════════════════ -->
<style>
.res-layout        { display:flex; gap:0; height:calc(100vh - var(--topbar-h) - var(--ribbon-h) - var(--status-h) - 40px); min-height:500px; }
.res-sidebar       { width:280px; flex-shrink:0; background:var(--bg-card); border-right:1px solid var(--border); display:flex; flex-direction:column; border-radius:10px 0 0 10px; overflow:hidden; }
.res-sidebar-head  { padding:14px 16px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; font-weight:600; font-size:13px; }
.res-search-box    { padding:10px 12px; border-bottom:1px solid var(--border); }
.res-search-box input { width:100%; height:32px; padding:0 10px; border:1px solid var(--border); border-radius:6px; font-size:12px; background:var(--bg-body); color:var(--text); outline:none; }
.res-search-box input:focus { border-color:var(--primary-light); }
.res-filter-bar    { padding:8px 12px; border-bottom:1px solid var(--border); display:flex; gap:6px; flex-wrap:wrap; }
.res-filter-chip   { padding:3px 10px; border-radius:99px; font-size:11px; font-weight:600; cursor:pointer; border:1px solid var(--border); background:var(--bg-body); color:var(--text2); transition:all .15s; }
.res-filter-chip.active { background:var(--primary); color:#fff; border-color:var(--primary); }
.res-list          { flex:1; overflow-y:auto; padding:6px; }
.res-list::-webkit-scrollbar { width:4px; }
.res-list::-webkit-scrollbar-thumb { background:var(--border); border-radius:2px; }
.res-item          { padding:10px 12px; border-radius:7px; cursor:pointer; border:1px solid transparent; margin-bottom:3px; transition:all .15s; }
.res-item:hover    { background:var(--bg-body); }
.res-item.active   { background:rgba(37,99,235,0.08); border-color:var(--primary-light); }
.res-item-number   { font-size:10px; font-weight:700; color:var(--primary-light); letter-spacing:0.5px; }
.res-item-title    { font-size:12px; font-weight:600; color:var(--text); margin:2px 0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.res-item-meta     { display:flex; align-items:center; justify-content:space-between; margin-top:4px; }
.res-item-date     { font-size:10px; color:var(--text2); }
.res-empty         { text-align:center; padding:40px 20px; color:var(--text2); font-size:12px; }

/* ── Editor area */
.res-editor-area   { flex:1; display:flex; flex-direction:column; min-width:0; background:var(--bg-body); border-radius:0 10px 10px 0; }
.res-editor-toolbar { background:var(--bg-card); border-bottom:1px solid var(--border); padding:6px 12px; display:flex; align-items:center; gap:4px; flex-wrap:wrap; }
.fmt-btn           { width:28px; height:28px; border:1px solid transparent; border-radius:4px; background:none; cursor:pointer; font-size:13px; font-weight:700; color:var(--text); display:flex; align-items:center; justify-content:center; transition:background .1s; }
.fmt-btn:hover     { background:var(--bg-body); border-color:var(--border); }
.fmt-btn.active-fmt { background:var(--primary-light)!important; color:#fff!important; }
.fmt-sep           { width:1px; height:20px; background:var(--border); margin:0 3px; }
.fmt-select        { height:28px; padding:0 6px; border:1px solid var(--border); border-radius:4px; font-size:11px; background:var(--bg-card); color:var(--text); cursor:pointer; }
.res-meta-bar      { padding:10px 20px; background:var(--bg-card); border-bottom:1px solid var(--border); display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
.res-meta-field    { display:flex; align-items:center; gap:6px; font-size:12px; }
.res-meta-field label { color:var(--text2); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.4px; white-space:nowrap; }
.res-meta-field input, .res-meta-field select { height:30px; padding:0 8px; border:1px solid var(--border); border-radius:5px; font-size:12px; background:var(--bg-body); color:var(--text); min-width:120px; }
.res-title-input   { font-size:22px; font-weight:700; color:var(--text); border:none; outline:none; background:transparent; width:100%; padding:16px 20px 0; font-family:var(--font-doc, serif); }
.res-title-input::placeholder { color:var(--text2); font-weight:400; }
.res-editor        { flex:1; padding:12px 20px 20px; outline:none; font-size:14px; line-height:1.8; color:var(--text); overflow-y:auto; font-family:var(--font-doc, serif); min-height:200px; }
.res-editor:empty::before { content:attr(data-placeholder); color:var(--text2); pointer-events:none; display:block; }
.res-editor h1     { font-size:20px; margin:12px 0 6px; }
.res-editor h2     { font-size:17px; margin:10px 0 5px; }
.res-editor h3     { font-size:15px; margin:8px 0 4px; }
.res-editor ul, .res-editor ol { margin:6px 0 6px 24px; }
.res-editor table  { border-collapse:collapse; width:100%; margin:10px 0; }
.res-editor table td, .res-editor table th { border:1px solid var(--border); padding:6px 10px; }
.res-editor table th { background:var(--bg-body); font-weight:700; }

/* ── Right panel */
.res-right-panel   { width:300px; flex-shrink:0; background:var(--bg-card); border-left:1px solid var(--border); display:flex; flex-direction:column; overflow:hidden; }
.res-panel-tabs    { display:flex; border-bottom:1px solid var(--border); }
.res-panel-tab     { flex:1; padding:10px; text-align:center; font-size:11px; font-weight:600; cursor:pointer; color:var(--text2); border-bottom:2px solid transparent; transition:all .15s; }
.res-panel-tab.active { color:var(--primary-light); border-bottom-color:var(--primary-light); }
.res-panel-body    { flex:1; overflow-y:auto; padding:12px; }
.res-panel-body::-webkit-scrollbar { width:4px; }

/* ── Approval/Vote panel */
.vote-summary      { background:var(--bg-body); border-radius:8px; padding:12px; margin-bottom:12px; }
.vote-bar-wrap     { margin:8px 0; }
.vote-bar-label    { display:flex; justify-content:space-between; font-size:11px; color:var(--text2); margin-bottom:3px; }
.vote-bar          { height:8px; background:var(--border); border-radius:4px; overflow:hidden; }
.vote-bar-fill     { height:100%; border-radius:4px; transition:width .4s; }
.vote-row          { display:flex; align-items:center; gap:8px; padding:7px 0; border-bottom:1px solid var(--border); font-size:12px; }
.vote-row:last-child { border-bottom:none; }
.vote-name         { flex:1; font-weight:500; }
.vote-status-dot   { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
.vote-badge        { padding:2px 7px; border-radius:99px; font-size:10px; font-weight:700; }
.vote-approve      { background:#dcfce7; color:#15803d; }
.vote-reject       { background:#fee2e2; color:#991b1b; }
.vote-abstain      { background:#fef9c3; color:#854d0e; }
.vote-pending      { background:#f1f5f9; color:#64748b; }

/* ── My vote buttons */
.my-vote-area      { padding:12px; background:var(--bg-body); border-radius:8px; margin-bottom:12px; }
.my-vote-title     { font-size:12px; font-weight:700; color:var(--text2); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px; }
.vote-btn-group    { display:flex; gap:6px; }
.vote-btn          { flex:1; padding:8px 4px; border-radius:6px; border:1.5px solid; cursor:pointer; font-size:12px; font-weight:600; text-align:center; transition:all .15s; background:transparent; }
.vote-btn.approve  { border-color:var(--success); color:var(--success); }
.vote-btn.approve:hover { background:var(--success); color:#fff; }
.vote-btn.reject   { border-color:var(--danger); color:var(--danger); }
.vote-btn.reject:hover { background:var(--danger); color:#fff; }
.vote-btn.abstain  { border-color:var(--text2); color:var(--text2); }
.vote-btn.abstain:hover { background:var(--text2); color:#fff; }

/* ── Comments */
.comment-list      { display:flex; flex-direction:column; gap:8px; }
.comment-item      { background:var(--bg-body); border-radius:7px; padding:10px 12px; font-size:12px; }
.comment-author    { font-weight:700; color:var(--text); font-size:11px; }
.comment-date      { font-size:10px; color:var(--text2); margin-left:6px; }
.comment-text      { margin-top:4px; color:var(--text); line-height:1.5; }
.comment-input-area { padding:10px; border-top:1px solid var(--border); }
.comment-input-area textarea { width:100%; padding:8px; border:1px solid var(--border); border-radius:6px; font-size:12px; resize:none; height:60px; background:var(--bg-body); color:var(--text); outline:none; font-family:var(--font); }
.comment-input-area textarea:focus { border-color:var(--primary-light); }

/* ── History panel */
.history-item      { padding:8px 0; border-bottom:1px solid var(--border); font-size:11px; }
.history-item:last-child { border-bottom:none; }
.history-action    { font-weight:600; color:var(--text); }
.history-who       { color:var(--text2); }
.history-when      { color:var(--text2); float:right; }

/* ── Status indicators */
.status-pill       { display:inline-flex; align-items:center; gap:4px; padding:4px 10px; border-radius:99px; font-size:11px; font-weight:700; }
.quorum-indicator  { font-size:11px; color:var(--text2); background:var(--bg-body); padding:4px 10px; border-radius:6px; border:1px solid var(--border); }

/* ── Watermark for draft/rejected */
.res-editor-wrap   { position:relative; flex:1; display:flex; flex-direction:column; overflow:hidden; }
.watermark         { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%) rotate(-25deg); font-size:80px; font-weight:900; color:rgba(0,0,0,0.04); pointer-events:none; white-space:nowrap; z-index:0; letter-spacing:4px; }

/* ── Print styles */
@media print {
  .res-sidebar, .res-right-panel, .res-editor-toolbar, .res-meta-bar { display:none!important; }
  .res-editor-area { border:none!important; }
  .res-title-input { font-size:24px; padding:0 0 12px; border-bottom:2px solid #000; }
  .res-editor { font-size:12pt; line-height:1.6; }
}
</style>

<!-- ═══════════════════ MODULE HTML ═══════════════════ -->
<div class="page-header">
  <div>
    <div class="page-title">📋 Resolutions</div>
    <div class="page-sub"><?= htmlspecialchars($CFG['app']['company_name']) ?> — Board Resolutions Management</div>
  </div>
  <?php if ($canCreate): ?>
  <button class="btn primary" onclick="resModule.newResolution()" style="margin-left:auto;">
    ➕ New Resolution
  </button>
  <?php endif; ?>
</div>

<div class="res-layout" id="resLayout">

  <!-- LEFT: Resolution list -->
  <div class="res-sidebar">
    <div class="res-sidebar-head">
      <span>All Resolutions</span>
      <span id="resCountBadge" style="font-size:11px;color:var(--text2);">—</span>
    </div>
    <div class="res-search-box">
      <input type="text" id="resSearchInput" placeholder="🔍 Search by title or number…" oninput="resModule.filterList(this.value)"/>
    </div>
    <div class="res-filter-bar">
      <span class="res-filter-chip active" data-status="" onclick="resModule.setFilter(this,'')">All</span>
      <?php foreach ($resStatuses as $key => $st): ?>
      <span class="res-filter-chip" data-status="<?= $key ?>"
            style="--chip-color:<?= htmlspecialchars($st['color']) ?>"
            onclick="resModule.setFilter(this,'<?= $key ?>')">
        <?= htmlspecialchars($st['label']) ?>
      </span>
      <?php endforeach; ?>
    </div>
    <div class="res-list" id="resList">
      <div class="res-empty">Loading resolutions…</div>
    </div>
  </div>

  <!-- CENTER: Editor -->
  <div class="res-editor-area" id="resEditorArea">

    <!-- Formatting toolbar -->
    <div class="res-editor-toolbar" id="resEditorToolbar">
      <!-- Font -->
      <select class="fmt-select" onchange="document.execCommand('fontName',false,this.value)" title="Font">
        <option value="Georgia">Georgia</option>
        <option value="Calibri">Calibri</option>
        <option value="Arial">Arial</option>
        <option value="Times New Roman">Times New Roman</option>
        <option value="Verdana">Verdana</option>
      </select>
      <select class="fmt-select" style="width:52px" onchange="document.execCommand('fontSize',false,this.value)" title="Size">
        <option value="2">10</option>
        <option value="3" selected>12</option>
        <option value="4">14</option>
        <option value="5">18</option>
        <option value="6">24</option>
        <option value="7">36</option>
      </select>
      <div class="fmt-sep"></div>
      <button class="fmt-btn" onclick="document.execCommand('bold')"      title="Bold (Ctrl+B)"><b>B</b></button>
      <button class="fmt-btn" onclick="document.execCommand('italic')"    title="Italic (Ctrl+I)"><i>I</i></button>
      <button class="fmt-btn" onclick="document.execCommand('underline')" title="Underline (Ctrl+U)"><u>U</u></button>
      <button class="fmt-btn" onclick="document.execCommand('strikeThrough')" title="Strikethrough"><s>S</s></button>
      <div class="fmt-sep"></div>
      <select class="fmt-select" onchange="document.execCommand('formatBlock',false,this.value)" title="Heading">
        <option value="p">Paragraph</option>
        <option value="h1">Heading 1</option>
        <option value="h2">Heading 2</option>
        <option value="h3">Heading 3</option>
        <option value="blockquote">Quote</option>
        <option value="pre">Code</option>
      </select>
      <div class="fmt-sep"></div>
      <button class="fmt-btn" onclick="document.execCommand('justifyLeft')"   title="Align Left">⬛</button>
      <button class="fmt-btn" onclick="document.execCommand('justifyCenter')" title="Center">⬜</button>
      <button class="fmt-btn" onclick="document.execCommand('justifyRight')"  title="Align Right">▪</button>
      <button class="fmt-btn" onclick="document.execCommand('justifyFull')"   title="Justify">▦</button>
      <div class="fmt-sep"></div>
      <button class="fmt-btn" onclick="document.execCommand('insertUnorderedList')" title="Bullet List">•≡</button>
      <button class="fmt-btn" onclick="document.execCommand('insertOrderedList')"   title="Numbered List">1≡</button>
      <button class="fmt-btn" onclick="document.execCommand('indent')"    title="Indent">→</button>
      <button class="fmt-btn" onclick="document.execCommand('outdent')"   title="Outdent">←</button>
      <div class="fmt-sep"></div>
      <button class="fmt-btn" onclick="resInsertTable()"     title="Insert Table">⊞</button>
      <button class="fmt-btn" onclick="resInsertHR()"        title="Insert Divider">─</button>
      <button class="fmt-btn" onclick="resInsertDate()"      title="Insert Date">📅</button>
      <div class="fmt-sep"></div>
      <button class="fmt-btn" onclick="document.execCommand('undo')" title="Undo (Ctrl+Z)">↩</button>
      <button class="fmt-btn" onclick="document.execCommand('redo')" title="Redo (Ctrl+Y)">↪</button>
      <div class="fmt-sep"></div>
      <button class="fmt-btn" onclick="resModule.printResolution()" title="Print (Ctrl+P)">🖨</button>
      <input type="color" title="Text color" style="width:28px;height:28px;padding:0;border:1px solid var(--border);border-radius:4px;cursor:pointer;"
             onchange="document.execCommand('foreColor',false,this.value)"/>
      <div style="flex:1"></div>
      <span id="resWordCountDisplay" style="font-size:11px;color:var(--text2);margin-right:8px;"></span>
    </div>

    <!-- Meta bar: type, dates, status -->
    <div class="res-meta-bar" id="resMetaBar">
      <div class="res-meta-field">
        <label>Number</label>
        <span id="resNumberDisplay" style="font-size:13px;font-weight:700;color:var(--primary-light);">—</span>
      </div>
      <div class="res-meta-field">
        <label>Type</label>
        <select id="resTypeSelect" onchange="resModule.onTypeChange(this.value)">
          <?php foreach ($resTypes as $k => $rt): ?>
          <option value="<?= $k ?>"><?= htmlspecialchars($rt['label']) ?> (<?= $rt['quorum_percent'] ?>%)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="res-meta-field">
        <label>Meeting Date</label>
        <input type="date" id="resMeetingDate"/>
      </div>
      <div class="res-meta-field">
        <label>Deadline</label>
        <input type="date" id="resDeadline"/>
      </div>
      <div class="res-meta-field">
        <label>Status</label>
        <span id="resStatusBadge" class="status-pill" style="background:#f1f5f9;color:#64748b;">Draft</span>
      </div>
      <div class="res-meta-field">
        <label>Quorum</label>
        <span id="resQuorumBadge" class="quorum-indicator">51% req.</span>
      </div>
    </div>

    <!-- Title input + editor -->
    <div class="res-editor-wrap" id="resEditorWrap">
      <div class="watermark" id="resWatermark" style="display:none">DRAFT</div>
      <input type="text" class="res-title-input" id="resTitleInput"
             placeholder="Resolution title…"
             oninput="resModule.onEditorChange()"/>
      <div class="res-editor" id="resEditorBody"
           contenteditable="true"
           data-placeholder="Begin drafting the resolution here. Use the formatting toolbar above for headings, lists, tables and more…"
           oninput="resModule.onEditorChange()"
           onkeyup="resModule.updateWordCount()"
           spellcheck="true">
      </div>
    </div>

    <!-- Bottom action bar -->
    <div style="padding:10px 16px;background:var(--bg-card);border-top:1px solid var(--border);display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <?php if ($canCreate): ?>
      <button class="btn primary"  onclick="resModule.save()">💾 Save Draft</button>
      <button class="btn success"  onclick="resModule.submitForApproval()" id="resBtnSubmit">📨 Submit for Approval</button>
      <?php endif; ?>
      <?php if ($canApprove): ?>
      <button class="btn"         onclick="resModule.showVotePanel()">🗳 My Vote</button>
      <?php endif; ?>
      <?php if ($canManage): ?>
      <button class="btn"         onclick="resModule.archive()" id="resBtnArchive">🗂 Archive</button>
      <button class="btn danger"  onclick="resModule.delete()"  id="resBtnDelete" style="margin-left:auto;">🗑 Delete</button>
      <?php endif; ?>
      <button class="btn"         onclick="resModule.printResolution()" style="<?= $canManage ? '' : 'margin-left:auto' ?>">🖨 Print</button>
    </div>
  </div>

  <!-- RIGHT: Info panel (Votes / Comments / History) -->
  <div class="res-right-panel" id="resRightPanel">
    <div class="res-panel-tabs">
      <div class="res-panel-tab active" onclick="resModule.showPanel('votes')">🗳 Votes</div>
      <div class="res-panel-tab"        onclick="resModule.showPanel('comments')">💬 Comments</div>
      <div class="res-panel-tab"        onclick="resModule.showPanel('history')">📜 History</div>
    </div>
    <div class="res-panel-body" id="resPanelVotes">
      <!-- My vote -->
      <?php if ($canApprove): ?>
      <div class="my-vote-area" id="myVoteArea">
        <div class="my-vote-title">My Vote</div>
        <div class="vote-btn-group">
          <button class="vote-btn approve" onclick="resModule.castVote('approve')">✅ Approve</button>
          <button class="vote-btn reject"  onclick="resModule.castVote('reject')">❌ Reject</button>
          <button class="vote-btn abstain" onclick="resModule.castVote('abstain')">⏸ Abstain</button>
        </div>
        <textarea id="voteReasonInput" placeholder="Reason (optional)…" style="width:100%;margin-top:8px;height:50px;padding:6px;border:1px solid var(--border);border-radius:5px;font-size:11px;resize:none;background:var(--bg-body);color:var(--text);outline:none;font-family:var(--font);"></textarea>
      </div>
      <?php endif; ?>
      <!-- Vote summary -->
      <div id="voteSummaryArea">
        <div class="res-empty" style="padding:20px">Select a resolution to view votes.</div>
      </div>
    </div>
    <div class="res-panel-body" id="resPanelComments" style="display:none;padding:0;flex-direction:column;">
      <div style="flex:1;overflow-y:auto;padding:12px;">
        <div class="comment-list" id="commentList">
          <div class="res-empty" style="padding:20px">No comments yet.</div>
        </div>
      </div>
      <div class="comment-input-area">
        <textarea id="commentInput" placeholder="Add a comment…"></textarea>
        <button class="btn primary" style="width:100%;margin-top:6px;height:30px;font-size:12px;" onclick="resModule.addComment()">Post Comment</button>
      </div>
    </div>
    <div class="res-panel-body" id="resPanelHistory" style="display:none;">
      <div id="historyList">
        <div class="res-empty" style="padding:20px">Select a resolution to view history.</div>
      </div>
    </div>
  </div>

</div><!-- end res-layout -->

<!-- Hidden state -->
<input type="hidden" id="currentResId" value="0"/>

<script>
// ════════════════════════════════════════════════
// Resolution Module
// ════════════════════════════════════════════════
const resModule = {
  currentId:     0,
  currentData:   null,
  allResolutions:[],
  filterStatus:  '',
  filterSearch:  '',
  saving:        false,

  // ── Init
  async init() {
    await this.loadList();
    // Open resolution from URL if ?id= is set
    const urlId = <?= $resId ?>;
    if (urlId) this.open(urlId);
    else if (this.allResolutions.length) this.open(this.allResolutions[0].id);
    // Hook undo/redo into cos
    window.cosGetState   = () => this.getEditorState();
    window.cosApplyState = (s) => this.applyEditorState(s);
    window.cosSave       = async () => { return await this.save(); };
    // Open new if action=new
    <?php if ($resAction === 'new'): ?>
    this.newResolution();
    <?php endif; ?>
  },

  // ── Load list
  async loadList() {
    const r = await cos.api('list_resolutions', {status: this.filterStatus, search: this.filterSearch});
    if (!r.success) { cos.toast(r.message || 'Failed to load', 'error'); return; }
    this.allResolutions = r.data?.rows || [];
    document.getElementById('resCountBadge').textContent = this.allResolutions.length + ' records';
    this.renderList();
  },

  // ── Render list
  renderList() {
    const list = document.getElementById('resList');
    const items = this.allResolutions;
    if (!items.length) {
      list.innerHTML = '<div class="res-empty">📋<br>No resolutions found.</div>';
      return;
    }
    const statColors = <?= json_encode(array_map(fn($s) => $s['color'], $resStatuses)) ?>;
    list.innerHTML = items.map(r => `
      <div class="res-item ${r.id == this.currentId ? 'active':''}" onclick="resModule.open(${r.id})">
        <div class="res-item-number">${r.number || 'DRAFT'}</div>
        <div class="res-item-title" title="${r.title}">${r.title}</div>
        <div class="res-item-meta">
          <span class="res-item-date">${r.created_date || ''}</span>
          <span class="badge badge-${r.status}">${r.status}</span>
        </div>
      </div>`).join('');
  },

  // ── Filter
  setFilter(el, status) {
    document.querySelectorAll('.res-filter-chip').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    this.filterStatus = status;
    this.loadList();
  },
  filterList(q) {
    this.filterSearch = q;
    clearTimeout(this._searchTimer);
    this._searchTimer = setTimeout(() => this.loadList(), 300);
  },

  // ── Open resolution
  async open(id) {
    this.currentId = id;
    this.renderList(); // re-render to highlight active
    const r = await cos.api('get_resolution', {id});
    if (!r.success) { cos.toast('Failed to load resolution', 'error'); return; }
    this.currentData = r.data;
    this.populateEditor(r.data);
    this.renderVotes(r.data.votes || []);
    this.renderComments(r.data.comments || []);
    document.getElementById('currentResId').value = id;
    cos.undoStack = []; cos.redoStack = [];
    cos.updateUndoRedoBtns();
    cos.isDirty = false;
    cos.setStatus('Resolution loaded: ' + (r.data.number || 'Draft'));
    // Update URL
    history.replaceState(null, '', `index.php?page=resolutions&id=${id}`);
  },

  // ── Populate editor fields
  populateEditor(d) {
    document.getElementById('resTitleInput').value  = d.title  || '';
    document.getElementById('resEditorBody').innerHTML = d.content || '';
    document.getElementById('resNumberDisplay').textContent = d.number || 'Auto-assign on save';
    document.getElementById('resMeetingDate').value = d.meeting_date || '';
    document.getElementById('resDeadline').value    = d.deadline    || '';
    const typeEl = document.getElementById('resTypeSelect');
    if (typeEl) typeEl.value = d.type || 'ordinary';
    this.onTypeChange(d.type || 'ordinary');
    this.updateStatusBadge(d.status || 'draft');
    this.updateWatermark(d.status || 'draft');
    this.updateWordCount();
    this.updateActionButtons(d.status || 'draft');
  },

  // ── Status badge
  updateStatusBadge(status) {
    const colors = <?= json_encode($resStatuses) ?>;
    const el = document.getElementById('resStatusBadge');
    if (!el) return;
    const c = colors[status] || {label: status, color:'#6b7280'};
    el.textContent = c.label || status;
    el.style.background = c.color + '22';
    el.style.color       = c.color;
  },

  // ── Watermark
  updateWatermark(status) {
    const wm = document.getElementById('resWatermark');
    if (!wm) return;
    if (status === 'draft')    { wm.textContent='DRAFT';    wm.style.display='block'; }
    else if (status==='rejected'){ wm.textContent='REJECTED'; wm.style.display='block'; }
    else wm.style.display='none';
  },

  // ── Type change
  onTypeChange(type) {
    const types = <?= json_encode($resTypes) ?>;
    const t = types[type] || {quorum_percent:51};
    document.getElementById('resQuorumBadge').textContent = t.quorum_percent + '% required';
  },

  // ── Word count
  updateWordCount() {
    const editor = document.getElementById('resEditorBody');
    const title  = document.getElementById('resTitleInput');
    const text   = (title?.value||'') + ' ' + (editor?.innerText||'');
    const words  = text.trim().split(/\s+/).filter(Boolean).length;
    const chars  = text.replace(/\s/g,'').length;
    const display= document.getElementById('resWordCountDisplay');
    if (display) display.textContent = `Words: ${words} · Chars: ${chars}`;
    // Update status bar
    const sb = document.getElementById('wordCount');
    if (sb) sb.textContent = `Words: ${words}`;
  },

  // ── On editor input
  onEditorChange() {
    cos.markDirty();
    this.updateWordCount();
  },

  // ── Get/apply state for undo
  getEditorState() {
    return {
      id:        this.currentId,
      title:     document.getElementById('resTitleInput').value,
      content:   document.getElementById('resEditorBody').innerHTML,
      type:      document.getElementById('resTypeSelect')?.value,
      meeting_date: document.getElementById('resMeetingDate').value,
      deadline:  document.getElementById('resDeadline').value,
    };
  },
  applyEditorState(s) {
    if (s.id !== this.currentId) return;
    document.getElementById('resTitleInput').value = s.title || '';
    document.getElementById('resEditorBody').innerHTML = s.content || '';
    if (s.type) document.getElementById('resTypeSelect').value = s.type;
    if (s.meeting_date) document.getElementById('resMeetingDate').value = s.meeting_date;
    if (s.deadline) document.getElementById('resDeadline').value = s.deadline;
    this.updateWordCount();
  },

  // ── New resolution
  newResolution() {
    this.currentId = 0;
    this.currentData = null;
    document.getElementById('resTitleInput').value = '';
    document.getElementById('resEditorBody').innerHTML = '';
    document.getElementById('resNumberDisplay').textContent = 'Auto-assign on save';
    document.getElementById('resMeetingDate').value = '';
    document.getElementById('resDeadline').value = '';
    document.getElementById('resTypeSelect').value = 'ordinary';
    this.updateStatusBadge('draft');
    this.updateWatermark('draft');
    this.onTypeChange('ordinary');
    this.updateActionButtons('draft');
    document.getElementById('currentResId').value = 0;
    cos.undoStack=[]; cos.redoStack=[]; cos.isDirty=false;
    cos.updateUndoRedoBtns();
    document.getElementById('resTitleInput').focus();
    history.replaceState(null,'','index.php?page=resolutions&action=new');
    cos.setStatus('New resolution — ready to draft');
  },

  // ── Save
  async save() {
    if (this.saving) return false;
    this.saving = true;
    const state = this.getEditorState();
    if (!state.title.trim()) { cos.toast('Title is required','warning'); this.saving=false; return false; }

    cos.pushUndo(state);

    const action  = this.currentId ? 'update_resolution' : 'create_resolution';
    const payload = {
      title:        state.title,
      content:      state.content,
      type:         state.type,
      meeting_date: state.meeting_date,
      deadline:     state.deadline,
    };
    if (this.currentId) payload.id = this.currentId;

    const r = await cos.api(action, payload);
    this.saving = false;
    if (!r.success) { cos.toast(r.message||'Save failed','error'); return false; }

    if (!this.currentId) {
      this.currentId = r.data.id;
      document.getElementById('currentResId').value = this.currentId;
      document.getElementById('resNumberDisplay').textContent = r.data.number;
      history.replaceState(null,'',`index.php?page=resolutions&id=${this.currentId}`);
    }
    await this.loadList();
    cos.toast('Saved ✓', 'success');
    return true;
  },

  // ── Submit for approval
  async submitForApproval() {
    if (!this.currentId) { const ok=await this.save(); if(!ok) return; }
    if (!confirm('Submit this resolution for approval? This will notify all directors and fellows.')) return;
    const r = await cos.api('submit_resolution', {id: this.currentId});
    if (!r.success) { cos.toast(r.message||'Submit failed','error'); return; }
    cos.toast('Resolution submitted for approval ✓','success');
    await this.open(this.currentId);
    this.loadList();
  },

  // ── Cast vote
  async castVote(vote) {
    if (!this.currentId) { cos.toast('No resolution selected','warning'); return; }
    if (this.currentData?.status !== 'pending') { cos.toast('Resolution is not in pending state','warning'); return; }
    const reason = document.getElementById('voteReasonInput').value || '';
    const r = await cos.api('approve_resolution', {id: this.currentId, vote, reason});
    if (!r.success) { cos.toast(r.message||'Vote failed','error'); return; }
    cos.toast('Vote recorded: ' + vote.toUpperCase(),'success');
    await this.open(this.currentId);
  },

  // ── Render votes
  renderVotes(votes) {
    const area = document.getElementById('voteSummaryArea');
    if (!votes || !votes.length) {
      area.innerHTML = '<div class="res-empty" style="padding:20px">No votes recorded yet.</div>';
      return;
    }
    const total    = votes.length;
    const approved = votes.filter(v=>v.vote==='approve').length;
    const rejected = votes.filter(v=>v.vote==='reject').length;
    const abstained= votes.filter(v=>v.vote==='abstain').length;
    const pending  = votes.filter(v=>v.vote==='pending').length;
    const voted    = approved+rejected+abstained;
    const approveP = voted>0 ? Math.round(approved/voted*100) : 0;
    const quorum   = this.currentData?.quorum_required || 51;

    area.innerHTML = `
      <div class="vote-summary">
        <div style="font-size:12px;font-weight:700;margin-bottom:8px;">Vote Tally — ${voted}/${total} voted</div>
        <div class="vote-bar-wrap">
          <div class="vote-bar-label"><span>✅ Approve (${approved})</span><span>${approveP}%</span></div>
          <div class="vote-bar"><div class="vote-bar-fill" style="width:${approveP}%;background:var(--success)"></div></div>
        </div>
        <div class="vote-bar-wrap">
          <div class="vote-bar-label"><span>❌ Reject (${rejected})</span><span>${voted>0?Math.round(rejected/voted*100):0}%</span></div>
          <div class="vote-bar"><div class="vote-bar-fill" style="width:${voted>0?Math.round(rejected/voted*100):0}%;background:var(--danger)"></div></div>
        </div>
        <div style="font-size:11px;color:var(--text2);margin-top:6px;">
          Quorum required: <strong>${quorum}%</strong> approve &nbsp;·&nbsp;
          Pending: <strong>${pending}</strong>
        </div>
      </div>
      <div style="font-size:11px;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">Individual Votes</div>
      ${votes.map(v=>`
        <div class="vote-row">
          <span class="vote-status-dot" style="background:${v.vote==='approve'?'var(--success)':v.vote==='reject'?'var(--danger)':v.vote==='abstain'?'#eab308':'var(--border)'}"></span>
          <span class="vote-name">${v.voter_name||'—'}</span>
          <span class="vote-badge vote-${v.vote}">${v.vote}</span>
        </div>`).join('')}`;
  },

  // ── Render comments
  renderComments(comments) {
    const list = document.getElementById('commentList');
    if (!comments || !comments.length) {
      list.innerHTML = '<div class="res-empty" style="padding:10px 0">No comments yet.</div>';
      return;
    }
    list.innerHTML = comments.map(c=>`
      <div class="comment-item">
        <span class="comment-author">${c.author_name||'Unknown'}</span>
        <span class="comment-date">${c.created_fmt||''}</span>
        <div class="comment-text">${c.content||''}</div>
      </div>`).join('');
  },

  // ── Add comment
  async addComment() {
    if (!this.currentId) { cos.toast('No resolution selected','warning'); return; }
    const txt = document.getElementById('commentInput').value.trim();
    if (!txt) { cos.toast('Comment cannot be empty','warning'); return; }
    const r = await cos.api('add_comment', {entity_type:'resolution', entity_id:this.currentId, content:txt});
    if (!r.success) { cos.toast(r.message||'Failed','error'); return; }
    document.getElementById('commentInput').value = '';
    await this.open(this.currentId);
    cos.toast('Comment posted','success');
  },

  // ── Panel switch
  showPanel(panel) {
    document.querySelectorAll('.res-panel-tab').forEach((t,i)=>{
      t.classList.toggle('active',['votes','comments','history'][i]===panel);
    });
    document.getElementById('resPanelVotes').style.display    = panel==='votes'    ? 'block':'none';
    document.getElementById('resPanelComments').style.display = panel==='comments' ? 'flex':'none';
    document.getElementById('resPanelHistory').style.display  = panel==='history'  ? 'block':'none';
    if (panel==='history' && this.currentId) this.loadHistory();
  },

  // ── History
  async loadHistory() {
    if (!this.currentId) return;
    const r = await cos.api('list_audit_log', {entity_type:'resolution', entity_id:this.currentId, limit:20});
    const list = document.getElementById('historyList');
    if (!r.success || !r.data?.length) {
      list.innerHTML = '<div class="res-empty">No history yet.</div>'; return;
    }
    list.innerHTML = r.data.map(h=>`
      <div class="history-item">
        <span class="history-when">${h.created_fmt}</span>
        <div class="history-action">${h.action.replace(/_/g,' ')}</div>
        <div class="history-who">by ${h.user_name||'System'}</div>
        ${h.notes?`<div style="color:var(--text2);font-size:10px;margin-top:2px;">${h.notes}</div>`:''}
      </div>`).join('');
  },

  // ── Archive
  async archive() {
    if (!this.currentId) return;
    if (!confirm('Archive this resolution?')) return;
    const r = await cos.api('archive_resolution', {id:this.currentId});
    if (!r.success) { cos.toast(r.message||'Failed','error'); return; }
    cos.toast('Resolution archived','success');
    this.loadList();
    this.updateStatusBadge('archived');
  },

  // ── Delete
  async delete() {
    if (!this.currentId) return;
    if (!confirm('Delete this draft resolution? This cannot be undone.')) return;
    const r = await cos.api('delete_resolution', {id:this.currentId});
    if (!r.success) { cos.toast(r.message||'Delete failed','error'); return; }
    cos.toast('Resolution deleted','success');
    this.currentId=0; this.currentData=null;
    this.newResolution();
    this.loadList();
  },

  // ── Update action button states
  updateActionButtons(status) {
    const submitBtn  = document.getElementById('resBtnSubmit');
    const archiveBtn = document.getElementById('resBtnArchive');
    const deleteBtn  = document.getElementById('resBtnDelete');
    if (submitBtn)  submitBtn.disabled  = !['draft'].includes(status);
    if (archiveBtn) archiveBtn.disabled = ['archived'].includes(status);
    if (deleteBtn)  deleteBtn.disabled  = !['draft'].includes(status);
  },

  // ── Print
  printResolution() {
    window.print();
  },

  showVotePanel() { this.showPanel('votes'); }
};

// ── Format helpers
function resInsertTable() {
  const rows = prompt('Number of rows:', '3');
  const cols = prompt('Number of columns:', '3');
  if (!rows || !cols) return;
  let html = '<table><thead><tr>';
  for (let c=0;c<+cols;c++) html+='<th>Header</th>';
  html+='</tr></thead><tbody>';
  for (let r=0;r<+rows-1;r++) {
    html+='<tr>';
    for (let c=0;c<+cols;c++) html+='<td>Cell</td>';
    html+='</tr>';
  }
  html+='</tbody></table>';
  document.execCommand('insertHTML', false, html);
}
function resInsertHR() {
  document.execCommand('insertHTML', false, '<hr style="border:none;border-top:2px solid var(--border);margin:12px 0"/>');
}
function resInsertDate() {
  const d = new Date().toLocaleDateString('en-PK',{day:'2-digit',month:'long',year:'numeric'});
  document.execCommand('insertHTML', false, `<span>${d}</span>`);
}

// Init on DOM ready
document.addEventListener('DOMContentLoaded', () => resModule.init());
</script>
