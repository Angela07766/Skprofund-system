<?php
require __DIR__.'/../app/bootstrap.php';
$page=is_string($_GET['page']??null)?$_GET['page']:'welcome';
try {
    if($_SERVER['REQUEST_METHOD']==='POST') {
        require ROOT.'/app/actions.php';
        try { handleAction(); } catch(DomainException $ex) {removeNewUploads();flash($ex->getMessage(),'error');}
        catch(PDOException $ex) {removeNewUploads();error_log('SK ProFund database error: '.$ex->getCode());flash('The record could not be saved. Check for duplicate values, referenced records, or conflicting updates.','error');}
        redirect(in_array($page,['welcome','login','dashboard','profile','users','settings','audit','reports','proposals','monitoring','approvals','allocation','expenses','balance','transactions','projects','summary','feedback'],true)?$page:'dashboard');
    }
    $user=currentUser();$role=$user['role']??'public';
    if($user && $user['must_change_password'] && $page!=='profile')redirect('profile');
    if($page==='file') {require ROOT.'/app/file.php';exit;}
    if($user && in_array($page,['welcome','login'],true))redirect('dashboard');
    $menu=roleMenus($role);if($user && $user['must_change_password'])$menu=['profile'=>'Edit Profile'];
    if(!in_array($page,['welcome','login'],true) && !isset($menu[$page]) && $page!=='profile') {http_response_code(403);$page='forbidden';}
    if(!$user && !in_array($page,['welcome','login','dashboard','projects','summary','feedback'],true)) {http_response_code(403);$page='forbidden';}
    require ROOT.'/app/layout.php';
} catch(DomainException $ex) {
    http_response_code(403); echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Access restricted</title><h1>Access restricted</h1><p>'.e($ex->getMessage()).'</p></html>';
} catch(Throwable $ex) {
    error_log('SK ProFund: '.$ex->getMessage());http_response_code(503);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>SK ProFund setup</title><h1>SK ProFund needs setup</h1><p>Database or private storage is unavailable. Follow README.md to import database/schema.sql and configure config/local.php.</p></html>';
}
