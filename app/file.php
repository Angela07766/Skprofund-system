<?php
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);$kind=$_GET['kind']??'';$path=null;$mime=null;
if($id && $kind==='project') { $p=project($id);$u=currentUser();if($p['published'] || ($u && in_array($u['role'],['admin','chairperson','treasurer'],true)) || ($u && $u['role']==='official' && (int)$p['owner_id']===(int)$u['id']))$path=$p['photo_path']; }
elseif($id && $kind==='receipt') {allow(['treasurer','chairperson']);$path=one('SELECT receipt_path FROM expenses WHERE id=?',[$id])['receipt_path']??null;}
elseif($id && $kind==='document') {allow(['treasurer','chairperson']);$path=one('SELECT document_path FROM fund_requests WHERE id=?',[$id])['document_path']??null;}
elseif($id && $kind==='avatar' && currentUser() && (int)currentUser()['id']===$id) {$path=currentUser()['photo_path'];}
if(!$path || !preg_match('/^[a-f0-9]{48}\.(jpg|png|webp|pdf)$/D',$path) || !is_file($config['storage'].'/'.$path)){http_response_code(404);exit('File unavailable.');}
$full=$config['storage'].'/'.$path;$mime=(new finfo(FILEINFO_MIME_TYPE))->file($full);
header('Content-Type: '.$mime);header('Content-Disposition: '.($mime==='application/pdf'?'attachment':'inline').'; filename="document.'.pathinfo($path,PATHINFO_EXTENSION).'"');header("Content-Security-Policy: default-src 'none'; sandbox");readfile($full);
