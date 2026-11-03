<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/bootstrap.php';
if(one("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.code='admin' AND u.active=1")){fwrite(STDERR,"An active Admin already exists. Use Manage Users.\n");exit(1);}
fwrite(STDOUT,"Admin full name: ");$name=trim((string)fgets(STDIN));
fwrite(STDOUT,"Admin email: ");$email=trim((string)fgets(STDIN));
fwrite(STDOUT,"Password (at least 12 characters; terminal input may be visible): ");$password=trim((string)fgets(STDIN));
if($name==='' || strlen($name)>120 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190 || strlen($password)<12 || strlen($password)>200){fwrite(STDERR,"Invalid name, email or password.\n");exit(1);}
transact(function()use($name,$email,$password){$role=one("SELECT id FROM roles WHERE code='admin' FOR UPDATE");if(one('SELECT id FROM users WHERE role_id=? AND active=1',[$role['id']]))throw new RuntimeException('An Admin already exists.');query('INSERT INTO users(role_id,name,email,password_hash,must_change_password) VALUES(?,?,?,?,0)',[$role['id'],$name,$email,password_hash($password,PASSWORD_DEFAULT)]);$id=(int)db()->lastInsertId();audit('Create','Users','Initial Admin created.',$id);});
fwrite(STDOUT,"Admin created. Sign in through public/index.php.\n");
