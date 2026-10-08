<style>
:root{
  --brand:#0B6B3A;--brand-dark:#064A28;--brand-soft:#E6F4EC;--gold:#C9A227;
  --bg:#F4F6F5;--surface:#FFFFFF;--surface-2:#F8FAF9;--border:#E2E7E3;--text:#14201A;--muted:#5F6B64;
  --danger:#C62828;--danger-soft:#FDECEC;--warning:#B26A00;--warning-soft:#FFF4E0;--success:#2E7D32;--success-soft:#E8F5E9;
  --info:#1E5AA8;--info-soft:#E7F0FB;--shadow:0 1px 2px rgba(16,24,20,.06),0 1px 3px rgba(16,24,20,.08);
  --radius:12px;--sidebar:#062F1B;--sidebar-text:#CFE3D7;--focus:0 0 0 3px rgba(11,107,58,.3);
}
[data-theme="dark"]{
  --bg:#0E1512;--surface:#151E1A;--surface-2:#1B2621;--border:#26332D;--text:#E6EEE9;--muted:#9AA8A0;
  --brand-soft:#123524;--danger-soft:#3A1717;--warning-soft:#3A2A0E;--success-soft:#14301A;--info-soft:#132A44;
  --sidebar:#08130E;--shadow:0 1px 2px rgba(0,0,0,.4);
}
*{box-sizing:border-box}
html,body{margin:0}
body{font-family:Arial,"Liberation Sans",Helvetica,sans-serif;font-size:14px;line-height:1.5;color:var(--text);background:var(--bg)}
a{color:var(--brand)}
[data-theme="dark"] a{color:#5FC28B}
h1,h2,h3{line-height:1.25;margin:0 0 .4em}
h1{font-size:22px}h2{font-size:17px}h3{font-size:15px}
.muted{color:var(--muted)}
.small{font-size:12px}
.mono{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12.5px}
.nowrap{white-space:nowrap}
.icon{width:18px;height:18px;flex:none;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;vertical-align:-4px}

/* Shell */
.shell{display:flex;min-height:100vh}
.sidebar{width:248px;flex:none;background:var(--sidebar);color:var(--sidebar-text);display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
.brand{display:flex;gap:10px;align-items:center;padding:18px 18px 14px;color:#fff;text-decoration:none}
.brand img{border-radius:8px;background:#fff}
.brand span{display:flex;flex-direction:column;line-height:1.15}
.brand small{color:var(--sidebar-text);font-size:11px;letter-spacing:.06em;text-transform:uppercase}
.sidebar nav{padding:4px 10px 16px;flex:1}
.nav-section{font-size:10.5px;text-transform:uppercase;letter-spacing:.08em;color:#7FA592;margin:16px 10px 6px}
.nav-link{display:flex;gap:10px;align-items:center;padding:8px 10px;border-radius:8px;color:var(--sidebar-text);text-decoration:none;font-size:13.5px}
.nav-link:hover{background:rgba(255,255,255,.06);color:#fff}
.nav-link.active{background:var(--brand);color:#fff}
.sidebar-foot{padding:12px 18px;font-size:11px;color:#7FA592;border-top:1px solid rgba(255,255,255,.06)}
.main{flex:1;min-width:0;display:flex;flex-direction:column}
.topbar{height:60px;display:flex;align-items:center;gap:12px;padding:0 24px;background:var(--surface);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:20}
.topsearch{flex:1;max-width:460px;display:flex;align-items:center;gap:8px;background:var(--surface-2);border:1px solid var(--border);border-radius:10px;padding:0 12px;color:var(--muted)}
.topsearch input{border:0;background:transparent;padding:9px 0;width:100%;color:var(--text);outline:none;font:inherit}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:8px}
.icon-btn{border:0;background:transparent;color:var(--text);padding:8px;border-radius:8px;cursor:pointer;display:inline-flex}
.icon-btn:hover{background:var(--surface-2)}
.menu-btn{display:none}
.usermenu{position:relative}
.usermenu summary{list-style:none;display:flex;gap:10px;align-items:center;cursor:pointer;padding:4px 8px;border-radius:10px}
.usermenu summary::-webkit-details-marker{display:none}
.usermenu summary:hover{background:var(--surface-2)}
.avatar{width:34px;height:34px;border-radius:50%;background:var(--brand);color:#fff;display:grid;place-items:center;font-weight:700;font-size:13px}
.who{display:flex;flex-direction:column;line-height:1.15}
.who small{color:var(--muted);font-size:11.5px}
.usermenu .menu{position:absolute;right:0;top:calc(100% + 6px);background:var(--surface);border:1px solid var(--border);border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,.12);min-width:230px;padding:6px;z-index:30}
.usermenu .menu a,.usermenu .menu button{display:flex;gap:10px;align-items:center;width:100%;padding:9px 10px;border-radius:8px;color:var(--text);text-decoration:none;background:none;border:0;font:inherit;cursor:pointer;text-align:left}
.usermenu .menu a:hover,.usermenu .menu button:hover{background:var(--surface-2)}
.content{padding:24px;max-width:1320px;width:100%}
.backdrop{display:none}

/* Page header */
.page-head{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;justify-content:space-between;margin-bottom:20px}
.page-head p{margin:2px 0 0;color:var(--muted)}
.crumbs{font-size:12.5px;color:var(--muted);margin-bottom:4px}
.crumbs a{color:var(--muted);text-decoration:none}
.crumbs a:hover{text-decoration:underline}
.actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center}

/* Cards & grids */
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow)}
.card-body{padding:18px}
.card-head{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:14px 18px;border-bottom:1px solid var(--border)}
.card-head h2{margin:0;font-size:15px}
.grid{display:grid;gap:16px}
.grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}
.grid-3{grid-template-columns:repeat(3,minmax(0,1fr))}
.grid-sidebar{grid-template-columns:minmax(0,2fr) minmax(280px,1fr)}
.stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:20px}
.stat{padding:16px 18px}
.stat .label{color:var(--muted);font-size:12.5px;display:flex;gap:6px;align-items:center}
.stat .value{font-size:26px;font-weight:700;margin-top:4px}
.stat .hint{font-size:12px;color:var(--muted)}
.stack>*+*{margin-top:16px}
.dl{display:grid;grid-template-columns:minmax(120px,38%) 1fr;gap:8px 12px;margin:0}
.dl dt{color:var(--muted)}
.dl dd{margin:0;word-break:break-word}

/* Tables */
.table-wrap{overflow-x:auto}
table.table{width:100%;border-collapse:collapse}
.table th,.table td{text-align:left;padding:10px 14px;border-bottom:1px solid var(--border);vertical-align:middle}
.table th{font-size:11.5px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);background:var(--surface-2);font-weight:600}
.table tr:last-child td{border-bottom:0}
.table tbody tr:hover{background:var(--surface-2)}
.table .row-actions{display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap}
.cell-title{font-weight:600}
.cell-sub{font-size:12px;color:var(--muted)}

/* Forms */
label{display:block;font-weight:600;font-size:13px;margin-bottom:4px}
.field{margin-bottom:14px}
.field .help{font-weight:400;color:var(--muted);font-size:12px;margin-top:4px}
.field .error{color:var(--danger);font-size:12.5px;margin-top:4px;font-weight:400}
input[type=text],input[type=password],input[type=search],input[type=email],input[type=number],input[type=date],input[type=file],select,textarea{
  width:100%;padding:9px 11px;border:1px solid var(--border);border-radius:9px;background:var(--surface);color:var(--text);font:inherit}
input:focus,select:focus,textarea:focus,.btn:focus-visible,a:focus-visible,summary:focus-visible{outline:none;box-shadow:var(--focus);border-color:var(--brand)}
textarea{resize:vertical}
.check{display:flex;gap:8px;align-items:flex-start;font-weight:400}
.check input{margin-top:3px}
.filters{display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;padding:14px 18px;border-bottom:1px solid var(--border)}
.filters .f{min-width:150px;flex:1;max-width:240px}
.filters .f label{font-size:11.5px;color:var(--muted);font-weight:600}
.form-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px}

/* Buttons */
.btn{display:inline-flex;align-items:center;gap:6px;border:1px solid transparent;border-radius:9px;padding:8px 14px;font:inherit;font-weight:600;font-size:13px;cursor:pointer;text-decoration:none;background:var(--brand);color:#fff;white-space:nowrap}
.btn:hover{filter:brightness(1.07)}
.btn-sm{padding:5px 10px;font-size:12.5px}
.btn-ghost{background:var(--surface);color:var(--text);border-color:var(--border)}
[data-theme="dark"] .btn-ghost{color:var(--text)}
.btn-danger{background:var(--danger)}
.btn-warning{background:var(--warning)}
.btn[disabled]{opacity:.55;cursor:not-allowed}
.link-btn{background:none;border:0;padding:0;color:var(--brand);cursor:pointer;font:inherit}

/* Badges & alerts */
.badge{display:inline-flex;align-items:center;gap:4px;font-size:11.5px;font-weight:600;padding:2px 9px;border-radius:999px;background:var(--surface-2);color:var(--muted);border:1px solid var(--border);white-space:nowrap}
.badge-success{background:var(--success-soft);color:var(--success);border-color:transparent}
.badge-danger{background:var(--danger-soft);color:var(--danger);border-color:transparent}
.badge-warning{background:var(--warning-soft);color:var(--warning);border-color:transparent}
.badge-info{background:var(--info-soft);color:var(--info);border-color:transparent}
.badge-brand{background:var(--brand-soft);color:var(--brand);border-color:transparent}
[data-theme="dark"] .badge-success,[data-theme="dark"] .badge-brand{color:#7BD6A0}
[data-theme="dark"] .badge-danger{color:#F28B8B}
[data-theme="dark"] .badge-warning{color:#F2C46B}
[data-theme="dark"] .badge-info{color:#8CB8F0}
.alert{padding:11px 14px;border-radius:10px;margin-bottom:16px;border:1px solid transparent}
.alert-success{background:var(--success-soft);color:var(--success)}
.alert-danger{background:var(--danger-soft);color:var(--danger)}
.alert-warning{background:var(--warning-soft);color:var(--warning)}
.alert-info{background:var(--info-soft);color:var(--info)}
[data-theme="dark"] .alert{color:var(--text)}
.empty{padding:40px 20px;text-align:center;color:var(--muted)}
.empty .icon{width:36px;height:36px;margin-bottom:8px}

/* Tabs */
.tabs{display:flex;gap:4px;border-bottom:1px solid var(--border);margin-bottom:16px;overflow-x:auto}
.tabs a{padding:10px 14px;color:var(--muted);text-decoration:none;border-bottom:2px solid transparent;white-space:nowrap;font-weight:600}
.tabs a.active{color:var(--brand);border-color:var(--brand)}

/* Charts */
.chart{width:100%;height:auto;display:block}
.chart .bar{fill:var(--brand)}
.chart .bar.alt{fill:var(--gold)}
.chart .axis{stroke:var(--border)}
.chart text{fill:var(--muted);font-size:10px}

/* Pagination */
.pager{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:12px 18px;border-top:1px solid var(--border);flex-wrap:wrap}
.pager .pages{display:flex;gap:4px;flex-wrap:wrap}
.pager a,.pager span.cur,.pager span.gap{min-width:32px;height:32px;display:inline-grid;place-items:center;padding:0 8px;border-radius:8px;border:1px solid var(--border);text-decoration:none;color:var(--text);font-size:13px}
.pager span.cur{background:var(--brand);color:#fff;border-color:var(--brand)}
.pager span.gap{border:0}
.pager .disabled{opacity:.4;pointer-events:none}

/* Dialog */
.dialog{border:0;border-radius:14px;padding:0;max-width:440px;width:calc(100% - 32px);background:var(--surface);color:var(--text);box-shadow:0 20px 60px rgba(0,0,0,.3)}
.dialog::backdrop{background:rgba(6,20,13,.55)}
.dialog form{padding:22px}
.dialog-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:16px}

/* Auth pages */
.auth{min-height:100vh;display:grid;grid-template-columns:1.1fr 1fr}
.auth-hero{background:linear-gradient(160deg,rgba(6,47,27,.92),rgba(11,107,58,.85)),url('{{ asset('admin/hq.jpg') }}') center/cover;color:#fff;padding:48px;display:flex;flex-direction:column;justify-content:space-between}
.auth-hero h1{font-size:30px}
.auth-panel{display:flex;align-items:center;justify-content:center;padding:32px;background:var(--surface)}
.auth-box{width:100%;max-width:380px}

@media (max-width:1100px){.grid-3{grid-template-columns:repeat(2,minmax(0,1fr))}.grid-sidebar{grid-template-columns:1fr}}
@media (max-width:860px){
  .sidebar{position:fixed;left:0;top:0;z-index:50;transform:translateX(-100%);transition:transform .2s}
  .sidebar.open{transform:none}
  .backdrop.open{display:block;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:40}
  .menu-btn{display:inline-flex}
  .who{display:none}
  .content{padding:16px}
  .topbar{padding:0 12px}
  .grid-2,.grid-3{grid-template-columns:1fr}
  .auth{grid-template-columns:1fr}.auth-hero{display:none}
}
@media print{.sidebar,.topbar,.actions,.filters,.pager{display:none!important}.content{padding:0}}
</style>
