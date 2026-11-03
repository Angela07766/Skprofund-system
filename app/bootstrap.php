<?php
declare(strict_types=1);
const ROOT = __DIR__ . '/..';
$config = is_file(ROOT.'/config/local.php') ? require ROOT.'/config/local.php' : require ROOT.'/config/example.php';
$config['dsn'] = getenv('SK_DB_DSN') ?: $config['dsn'];
$config['user'] = getenv('SK_DB_USER') !== false ? getenv('SK_DB_USER') : $config['user'];
$config['password'] = getenv('SK_DB_PASSWORD') !== false ? getenv('SK_DB_PASSWORD') : $config['password'];
$config['storage'] = getenv('SK_STORAGE') ?: $config['storage'];
date_default_timezone_set('Asia/Manila');
if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','samesite'=>'Lax','path'=>'/']);
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    header('Cache-Control: no-store');
    if (!empty($_SESSION['last_seen']) && time()-$_SESSION['last_seen'] > 1800) { $_SESSION=[]; session_regenerate_id(true); }
    $_SESSION['last_seen']=time();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function db(): PDO {
    static $pdo;
    global $config;
    if(!$pdo) { $pdo=new PDO($config['dsn'],$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]); $pdo->exec("SET time_zone = '+08:00'"); } return $pdo;
}
function query(string $sql,array $params=[]): PDOStatement { $s=db()->prepare($sql); $s->execute($params); return $s; }
function rows(string $sql,array $params=[]): array { return query($sql,$params)->fetchAll(); }
function one(string $sql,array $params=[]): ?array { return query($sql,$params)->fetch() ?: null; }
function e(mixed $s): string { return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function money(int|string|null $cents): string { return '₱ '.number_format(((int)$cents)/100,2); }
function cents(string $value): int {
    if(!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D',trim($value))) throw new DomainException('Enter a valid amount, with at most two decimal places.');
    [$a,$b]=array_pad(explode('.',trim($value)),2,''); return (int)$a*100+(int)str_pad($b,2,'0');
}
function input(string $key,int $max=255,bool $required=true): string {
    $v=$_POST[$key] ?? ''; if(!is_string($v)) throw new DomainException('Invalid input.'); $v=trim($v);
    if(($required && $v==='') || strlen($v)>$max) throw new DomainException('Check the '.str_replace('_',' ',$key).' field.'); return $v;
}
function dateInput(string $key): string { $v=input($key,10); $d=DateTimeImmutable::createFromFormat('!Y-m-d',$v); if(!$d || $d->format('Y-m-d')!==$v) throw new DomainException('Enter a valid date.'); return $v; }
function idInput(string $key): int { $v=filter_var($_POST[$key]??null,FILTER_VALIDATE_INT); if(!$v || $v<1) throw new DomainException('Select a valid '.str_replace('_',' ',$key).'.'); return $v; }
function currentUser(): ?array {
    static $loaded=false,$user=null;
    if(!$loaded) { $loaded=true; if(!empty($_SESSION['uid'])) $user=one('SELECT u.*, r.code role, r.name role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.active=1',[$_SESSION['uid']]); }
    if($user && (int)$user['auth_version']!==(int)($_SESSION['auth_version']??0)) $user=null;
    return $user;
}
function allow(array $roles): array { $u=currentUser(); if(!$u || !in_array($u['role'],$roles,true)) throw new DomainException('You do not have permission for this action.'); return $u; }
function audit(string $action,string $module,string $details,?int $uid=null): void { query('INSERT INTO audit_logs(user_id,action,module,details) VALUES(?,?,?,?)',[$uid ?? (currentUser()['id']??null),$action,$module,$details]); }
function redirect(string $page,array $params=[]): never { header('Location: index.php?'.http_build_query(['page'=>$page]+$params)); exit; }
function flash(string $message,string $type='success'): void { $_SESSION['flash']=['message'=>$message,'type'=>$type]; }
function csrf(): string { return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">'; }
function actionFields(string $action): void { echo csrf().'<input type="hidden" name="action" value="'.e($action).'">'; }
function spent(int $id): int { return (int)one('SELECT COALESCE(SUM(amount_cents),0) total FROM expenses WHERE project_id=?',[$id])['total']; }
function project(int $id,bool $lock=false): array { $p=one('SELECT * FROM projects WHERE id=?'.($lock?' FOR UPDATE':''),[$id]); if(!$p) throw new DomainException('Project not found.'); return $p; }
function ownProject(int $id): array { $u=allow(['official']); $p=project($id,true); if((int)$p['owner_id']!==(int)$u['id']) throw new DomainException('This project belongs to another official.'); return $p; }
function transact(callable $f): void { db()->beginTransaction(); try {$f();db()->commit();} catch(Throwable $e) {if(db()->inTransaction()) db()->rollBack();throw $e;} }
function upload(string $field,bool $required=false,bool $pdf=false): ?string {
    global $config;
    $f=$_FILES[$field]??null;
    if(!$f || $f['error']===UPLOAD_ERR_NO_FILE) { if($required) throw new DomainException('Please upload the required file.'); return null; }
    if($f['error']!==UPLOAD_ERR_OK || $f['size']>5*1024*1024 || !is_uploaded_file($f['tmp_name'])) throw new DomainException('Upload failed. Maximum file size is 5 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp']; if($pdf) $types['application/pdf']='pdf';
    if(!isset($types[$mime])) throw new DomainException('Use JPEG, PNG or WebP'.($pdf?' or PDF':'').' files.');
    if(str_starts_with($mime,'image/') && (!($info=getimagesize($f['tmp_name'])) || $info[0]*$info[1]>24000000)) throw new DomainException('Invalid image or image is too large.');
    if(!is_dir($config['storage']) && !mkdir($config['storage'],0700,true)) throw new RuntimeException('Private upload directory is unavailable.');
    $name=bin2hex(random_bytes(24)).'.'.$types[$mime];
    if(!move_uploaded_file($f['tmp_name'],$config['storage'].'/'.$name)) throw new RuntimeException('Cannot store upload.');
    chmod($config['storage'].'/'.$name,0600); $GLOBALS['new_uploads'][]=$name; return $name;
}
function removeNewUploads(): void {global $config; foreach($GLOBALS['new_uploads']??[] as $name) @unlink($config['storage'].'/'.$name);}
function roleMenus(string $role): array {
    return match($role) {
        'admin'=>['dashboard'=>'Dashboard','users'=>'Manage Users','settings'=>'Settings','audit'=>'Audit Logs','reports'=>'Reports','feedback'=>'Community Feedback'],
        'chairperson'=>['dashboard'=>'Dashboard','profile'=>'Edit Profile','proposals'=>'Proposals','monitoring'=>'Monitoring','approvals'=>'Fund Approval','reports'=>'Reports','feedback'=>'Community Feedback'],
        'treasurer'=>['dashboard'=>'Dashboard','profile'=>'Edit Profile','allocation'=>'Allocation','expenses'=>'Expenses','balance'=>'Fund Balance','transactions'=>'Transactions'],
        'official'=>['dashboard'=>'Dashboard','profile'=>'Edit Profile','projects'=>'Projects','summary'=>'Fund Summary'],
        default=>['dashboard'=>'Dashboard','projects'=>'View Projects','summary'=>'Fund Summary','feedback'=>'Suggestions & Comments']
    };
}
function projectList(bool $public=false): array {
    $u=currentUser(); $where=[];$args=[];
    if($public || !$u || $u['role']==='resident') $where[]='p.published=1';
    elseif($u['role']==='official') {$where[]='p.owner_id=?';$args[]=$u['id'];}
    return rows('SELECT p.*, c.name category, f.name source, u.name owner, COALESCE((SELECT SUM(amount_cents) FROM expenses WHERE project_id=p.id),0) spent FROM projects p JOIN categories c ON c.id=p.category_id JOIN fund_sources f ON f.id=p.source_id JOIN users u ON u.id=p.owner_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY p.created_at DESC,p.id DESC',$args);
}
