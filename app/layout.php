<?php require ROOT.'/app/ui.php'; $entry=in_array($page,['welcome','login'],true); $title=$menu[$page]??($page==='login'?'User Login':'Welcome'); ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($title) ?> · SK ProFund</title><link rel="stylesheet" href="assets/css/app.css"><script src="assets/js/app.js" defer></script></head>
<body class="<?= $entry?'entry':'workspace' ?>"><a class="skip" href="#main">Skip to content</a>
<header class="app-header"><div class="brand"><img src="assets/images/logo.png" alt="SK"><div><strong>SK ProFund</strong><small>SK Project and Fund Monitoring</small></div></div><?php if(!$entry): ?><button class="menu-toggle secondary" aria-label="Toggle navigation" aria-expanded="false">☰</button><?php endif ?><?php if($user): ?><div class="account"><?php if($user['photo_path']): ?><img src="<?= e(url('file',['kind'=>'avatar','id'=>$user['id']])) ?>" alt=""><?php endif ?><span><a href="<?= e(url('profile')) ?>"><?= e($user['role_name']) ?></a><small><?= e($user['name']) ?></small></span></div><?php endif ?></header>
<?php if(!$entry): ?><aside class="sidebar"><nav aria-label="Main navigation"><?php $icons=['dashboard'=>'⌂','users'=>'⊞','settings'=>'⚙','audit'=>'▤','reports'=>'▧','profile'=>'♙','proposals'=>'▧','monitoring'=>'▥','approvals'=>'◉','allocation'=>'▣','expenses'=>'▤','balance'=>'◈','transactions'=>'▦','projects'=>'▧','summary'=>'▥','feedback'=>'▤'];foreach($menu as $key=>$label): ?><a class="<?= $page===$key?'active':'' ?>" <?= $page===$key?'aria-current="page"':'' ?> href="<?= e(url($key)) ?>"><span aria-hidden="true"><?= $icons[$key] ?></span><?= e($label) ?></a><?php endforeach ?></nav><div class="sidebar-bottom"><?php if($user): ?><form method="post"><?php actionFields('logout'); ?><button class="logout">⇥ &nbsp; Log Out</button></form><?php else: ?><a href="<?= e(url('welcome')) ?>">⇥ &nbsp; Exit</a><?php endif ?></div></aside><?php endif ?>
<main id="main" class="<?= $entry?'entry-main':'content' ?>">
<?php if(!empty($_SESSION['flash'])): $f=$_SESSION['flash'];unset($_SESSION['flash']); ?><div role="status" class="alert <?= e($f['type']) ?>"><?= e($f['message']) ?></div><?php endif ?>
<?php if($user && $user['must_change_password']): ?><div class="alert">Change your temporary password to activate access to your account.</div><?php endif ?>
<?php if(!$entry): ?><div class="page-heading"><h1><?= e($title) ?></h1><span class="muted">Barangay Pag-Asa · Mabitac, Laguna</span></div><?php endif ?>
<?php
switch($page) {
case 'welcome':case 'login':require ROOT.'/app/pages/entry.php';break;
case 'dashboard':require ROOT.'/app/pages/dashboard.php';break;
case 'feedback':require ROOT.'/app/pages/feedback.php';break;
case 'profile':require ROOT.'/app/pages/profile.php';break;
case 'users':case 'settings':case 'audit':require ROOT.'/app/pages/admin.php';break;
case 'projects':case 'proposals':case 'monitoring':require ROOT.'/app/pages/projects.php';break;
case 'allocation':case 'approvals':case 'expenses':case 'balance':case 'transactions':require ROOT.'/app/pages/finance.php';break;
case 'summary':case 'reports':require ROOT.'/app/pages/reports.php';break;
default: echo '<section class="panel"><h2>Access restricted</h2><p>This page is not available for your role.</p><a href="index.php?page=dashboard">Return to dashboard</a></section>';
}
?>
<footer>SK ProFund · Project and fund monitoring</footer></main>
<dialog id="confirm-dialog"><h2>Confirm action</h2><p id="confirm-message"></p><div class="actions"><button type="button" class="secondary" id="confirm-cancel">Cancel</button><button type="button" id="confirm-accept">Continue</button></div></dialog>
</body></html>
