<!doctype html>
<html lang="<?= e($lang ?? 'it') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>AlienShop — <?= e(__('Installazione')) ?></title>
<style>
:root{--bg:#0f1220;--card:#fff;--text:#1b1d2a;--muted:#6b7086;--primary:#6c4cf5;--ok:#1e9e5a;--bad:#d6334a;--border:#e3e5ee}
*{box-sizing:border-box}body{margin:0;font:16px/1.55 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:linear-gradient(160deg,#0f1220,#262a52);color:var(--text);min-height:100vh;padding:32px 16px}
.wrap{max-width:760px;margin:0 auto}.brand{color:#fff;font-size:1.6rem;font-weight:800;margin-bottom:20px;display:flex;align-items:center;gap:10px}
.card{background:var(--card);border-radius:14px;padding:32px;box-shadow:0 20px 60px rgba(0,0,0,.35)}
h1{margin:0 0 6px;font-size:1.5rem}h2{margin:0 0 16px;font-size:1.2rem}.sub{color:var(--muted);margin:0 0 24px}
label{display:block;font-weight:600;font-size:.9rem;margin:0 0 4px}.field{margin-bottom:16px}
input[type=text],input[type=email],input[type=password],input[type=number],input[type=url],select{width:100%;padding:11px 12px;border:1px solid var(--border);border-radius:8px;font:inherit}
input:focus,select:focus{outline:2px solid var(--primary);outline-offset:1px}
.row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.btn{display:inline-block;padding:12px 24px;border:0;border-radius:8px;background:var(--primary);color:#fff;font:inherit;font-weight:700;cursor:pointer}.btn.sec{background:#eceefa;color:var(--text)}.btn[disabled]{opacity:.5;cursor:not-allowed}
.nav{display:flex;justify-content:space-between;margin-top:28px;gap:12px}
.steps{display:flex;gap:6px;margin-bottom:24px}.steps i{flex:1;height:5px;border-radius:3px;background:var(--border)}.steps i.on{background:var(--primary)}
.req{list-style:none;padding:0;margin:0 0 18px}.req li{padding:8px 0;border-bottom:1px solid var(--border);display:flex;justify-content:space-between}
.ok{color:var(--ok);font-weight:700}.bad{color:var(--bad);font-weight:700}.warn{color:#c7861b;font-weight:700}
.errors{background:#fdecee;border:1px solid var(--bad);color:#7d1224;padding:12px 16px;border-radius:8px;margin-bottom:20px}.errors ul{margin:0;padding-left:18px}
.choice{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px}.choice label{border:2px solid var(--border);border-radius:10px;padding:14px;cursor:pointer;font-weight:600;margin:0}.choice input{margin-right:8px}.choice label:has(input:checked){border-color:var(--primary);background:#f4f1ff}
.themes{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px}
.theme{border:2px solid var(--border);border-radius:10px;overflow:hidden;cursor:pointer;margin:0;font-weight:400;display:block}.theme:has(input:checked){border-color:var(--primary);box-shadow:0 0 0 3px #6c4cf533}
.theme input{position:absolute;opacity:0}.theme .sw{display:flex;height:54px}.theme .sw span{flex:1}.theme b{display:block;padding:8px 10px 0;font-size:.95rem}.theme small{display:block;padding:0 10px 10px;color:var(--muted);font-size:.8rem;line-height:1.3}
.hint{font-size:.85rem;color:var(--muted);margin-top:4px}
.js .step{display:none}.js .step.active{display:block}
.lang{float:right;font-size:.85rem}.lang a{color:var(--primary);text-decoration:none;font-weight:600}
@media(max-width:600px){.row,.choice{grid-template-columns:1fr}.card{padding:20px}}
</style>
</head>
<body>
<div class="wrap">
  <div class="brand"><svg width="34" height="34" viewBox="0 0 32 32"><circle cx="16" cy="16" r="16" fill="#6c4cf5"/><path d="M16 6c4 0 7 3 7 7 0 5-7 13-7 13S9 18 9 13c0-4 3-7 7-7z" fill="#fff"/><circle cx="16" cy="13" r="2.6" fill="#6c4cf5"/></svg> AlienShop</div>
  <div class="card"><?= $content ?></div>
</div>
</body>
</html>
