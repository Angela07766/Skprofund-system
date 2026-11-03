<?php
function handleAction(): void {
    if(!is_string($_POST['csrf']??null) || !hash_equals($_SESSION['csrf'],$_POST['csrf'])) throw new DomainException('This form expired. Reload the page and try again.');
    $action=input('action',40); $u=currentUser();
    if($action==='login') {
        $email=strtolower(input('email',190)); $password=input('password',200);
        $key=hash('sha256',strtolower($email).'|'.($_SERVER['REMOTE_ADDR']??''));
        transact(function()use($key,$email,$password){
            query('INSERT IGNORE INTO login_attempts(key_hash) VALUES(?)',[$key]);
            $attempt=one('SELECT * FROM login_attempts WHERE key_hash=? FOR UPDATE',[$key]);
            if($attempt['blocked_until'] && strtotime($attempt['blocked_until'])>time()) throw new DomainException('Too many attempts. Try again in 15 minutes.');
            $user=one('SELECT * FROM users WHERE email=? AND active=1',[$email]);
            $dummy='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
            $valid=password_verify($password,$user['password_hash']??$dummy);
            if(!$user || !$valid) {
                query('UPDATE login_attempts SET attempts=IF(blocked_until IS NOT NULL AND blocked_until<=NOW(),1,attempts+1),blocked_until=IF(attempts>=5,DATE_ADD(NOW(),INTERVAL 15 MINUTE),NULL) WHERE key_hash=?',[$key]);
                $GLOBALS['login_failed']=true; return;
            }
            query('DELETE FROM login_attempts WHERE key_hash=?',[$key]);
            session_regenerate_id(true); $_SESSION['uid']=$user['id']; $_SESSION['auth_version']=$user['auth_version']; $_SESSION['csrf']=bin2hex(random_bytes(32));
            audit('Login','System','User logged in.',$user['id']);
        });
        if(!empty($GLOBALS['login_failed'])) throw new DomainException('Invalid email or password.');
        $logged=one('SELECT must_change_password FROM users WHERE id=?',[$_SESSION['uid']]);
        redirect($logged['must_change_password']?'profile':'dashboard');
    }
    if($action==='logout') { if($u) audit('Logout','System','User logged out.'); $_SESSION=[]; session_regenerate_id(true); redirect('welcome'); }
    if($action==='submit_feedback') {
        if($u && $u['role']!=='resident')throw new DomainException('Use the staff inbox to review feedback.');
        if($u && $u['must_change_password'])throw new DomainException('Change your temporary password first.');
        $type=input('feedback_type',20);if(!in_array($type,['Suggestion','Comment'],true))throw new DomainException('Choose a valid message type.');
        $subject=input('subject',150);$message=input('message',2000);if(strlen($message)<10)throw new DomainException('Write a message of at least 10 characters.');
        $name=input('display_name',100,false)?:'Anonymous';
        $projectId=isset($_POST['project_id']) && $_POST['project_id']!==''?idInput('project_id'):null;
        $client=hash('sha256','skprofund-feedback|'.($_SERVER['REMOTE_ADDR']??'unknown'));
        transact(function()use($type,$subject,$message,$name,$projectId,$client,$u){
            query('DELETE FROM feedback_rate_limits WHERE window_started < DATE_SUB(NOW(), INTERVAL 2 DAY)');
            query('INSERT IGNORE INTO feedback_rate_limits(client_hash,window_started) VALUES(?,NOW())',[$client]);
            $limit=one('SELECT *,window_started > DATE_SUB(NOW(),INTERVAL 10 MINUTE) in_window FROM feedback_rate_limits WHERE client_hash=? FOR UPDATE',[$client]);
            if($limit['in_window'] && (int)$limit['attempts']>=5)throw new DomainException('Message limit reached. Please try again after ten minutes.');
            if($projectId && !one('SELECT id FROM projects WHERE id=? AND published=1',[$projectId]))throw new DomainException('Choose a currently public project or a general message.');
            query('INSERT INTO public_feedback(project_id,user_id,type,subject,message,display_name) VALUES(?,?,?,?,?,?)',[$projectId,$u['id']??null,$type,$subject,$message,$name]);
            query('UPDATE feedback_rate_limits SET attempts=IF(window_started > DATE_SUB(NOW(),INTERVAL 10 MINUTE),attempts+1,1),window_started=IF(window_started > DATE_SUB(NOW(),INTERVAL 10 MINUTE),window_started,NOW()) WHERE client_hash=?',[$client]);
        });flash('Your message was sent privately to the SK team. Thank you.');redirect('feedback');
    }
    if(!$u) throw new DomainException('Please log in.');
    if($u['must_change_password'] && $action!=='password') throw new DomainException('Change your temporary password before continuing.');
    switch($action) {
    case 'review_feedback':
        allow(['admin','chairperson']);$id=idInput('id');$status=input('status',20);$notes=input('review_notes',2000,false);
        if(!in_array($status,['New','Reviewed','Archived'],true))throw new DomainException('Choose a valid review status.');
        transact(function()use($id,$status,$notes,$u){if(!one('SELECT id FROM public_feedback WHERE id=? FOR UPDATE',[$id]))throw new DomainException('Message not found.');query('UPDATE public_feedback SET status=?,review_notes=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?',[$status,$notes,$u['id'],$id]);audit('Review','Community Feedback','Message #'.$id.' marked '.$status);});flash('Feedback review saved.');redirect('feedback');
    case 'generate_ideas':
        allow(['official']);require_once ROOT.'/app/recommendations.php';
        $need=input('need',2000);if(strlen($need)<10)throw new DomainException('Describe the need in at least 10 characters.');
        $ceiling=cents(input('budget_ceiling',30));$count=idInput('beneficiary_count');if($count>100000)throw new DomainException('Use at most 100,000 beneficiaries.');
        $criteria=['need'=>$need,'ceiling'=>$ceiling,'beneficiaries'=>input('beneficiaries',255),'beneficiary_count'=>$count,'category_id'=>idInput('category_id'),'source_id'=>idInput('source_id')];
        $generated=generateProjectIdeas($criteria);
        $_SESSION['suggestions']=['uid'=>$u['id'],'created'=>time(),'criteria'=>$criteria,'generated'=>$generated];
        audit('Generate suggestions','Projects','Generated '.count($generated['ideas']).' ranked project ideas.');
        redirect('projects',['suggestions'=>1]);
    case 'use_suggestion':
        allow(['official']);require_once ROOT.'/app/recommendations.php';$key=input('suggestion_key',100);
        $saved=$_SESSION['suggestions']??null;
        if(!$saved || (int)$saved['uid']!==(int)$u['id'] || time()-$saved['created']>1800)throw new DomainException('Suggestions expired. Generate them again.');
        $idea=null;foreach($saved['generated']['ideas'] as $candidate)if($candidate['key']===$key)$idea=$candidate;
        if(!$idea)throw new DomainException('Choose a suggestion from your current results.');
        $_SESSION['proposal_draft']=['uid'=>$u['id'],'created'=>time(),'fields'=>suggestionDraft($idea,$saved['criteria'])];
        audit('Generate draft','Projects','Prepared an unsaved draft from '.$key.'.');
        redirect('projects',['edit'=>'new','generated'=>1]);
    case 'password':
        if(!password_verify(input('current_password',200),$u['password_hash'])) throw new DomainException('Current password is incorrect.');
        $password=input('new_password',200); if(strlen($password)<12 || $password!==input('confirm_password',200)) throw new DomainException('Passwords must match and contain at least 12 characters.');
        if(password_verify($password,$u['password_hash'])) throw new DomainException('Choose a different password.');
        transact(function()use($password,$u){ query('UPDATE users SET password_hash=?,must_change_password=0,auth_version=auth_version+1 WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$u['id']]); audit('Password change','Users','Changed own password.'); });
        $_SESSION['auth_version']=(int)$u['auth_version']+1; session_regenerate_id(true); flash('Password updated.'); redirect('profile');
    case 'profile':
        $email=input('email',190); if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new DomainException('Enter a valid email.');
        $name=input('name',120);$phone=input('phone',40,false);$barangay=input('barangay',120);$photo=upload('photo');
        transact(function()use($email,$name,$phone,$barangay,$photo,$u){query('UPDATE users SET name=?,email=?,phone=?,barangay=?,photo_path=COALESCE(?,photo_path) WHERE id=?',[$name,$email,$phone,$barangay,$photo,$u['id']]);audit('Update','Users','Updated own profile.');});
        flash('Profile updated.'); redirect('profile');
    case 'save_user':
        allow(['admin']); $id=(int)($_POST['id']??0); $role=idInput('role_id');
        if(!one('SELECT id FROM roles WHERE id=?',[$role])) throw new DomainException('Role not found.');
        $name=input('name',120);$email=input('email',190);if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new DomainException('Enter a valid email.');
        $active=isset($_POST['active'])?1:0;
        transact(function()use($id,$role,$name,$email,$active,$u){
            // Serialize admin membership changes to preserve at least one active administrator.
            $admins=rows("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.code='admin' AND u.active=1 FOR UPDATE");
            if($id) {
                $old=one('SELECT * FROM users WHERE id=? FOR UPDATE',[$id]);if(!$old)throw new DomainException('User not found.');
                $oldRole=one('SELECT code FROM roles WHERE id=?',[$old['role_id']])['code'];$newRole=one('SELECT code FROM roles WHERE id=?',[$role])['code'];
                if($oldRole==='admin' && (!$active || $newRole!=='admin') && count($admins)<=1) throw new DomainException('Keep at least one active Admin.');
                if($id===(int)$u['id'] && (!$active || $role!==(int)$u['role_id'])) throw new DomainException('You cannot deactivate or change your own role.');
                query('UPDATE users SET name=?,email=?,role_id=?,active=? WHERE id=?',[$name,$email,$role,$active,$id]);
            } else {
                $password=input('temporary_password',200); if(strlen($password)<12)throw new DomainException('Temporary password needs at least 12 characters.');
                query('INSERT INTO users(role_id,name,email,password_hash,active) VALUES(?,?,?,?,?)',[$role,$name,$email,password_hash($password,PASSWORD_DEFAULT),$active]);$id=(int)db()->lastInsertId();
            }
            audit('Save','Users','Saved account #'.$id);
        }); flash('Account saved.');redirect('users');
    case 'reset_password':
        allow(['admin']);$id=idInput('id');if($id===(int)$u['id'])throw new DomainException('Use your own password form.');
        $password=input('temporary_password',200);if(strlen($password)<12)throw new DomainException('Use at least 12 characters.');
        transact(function()use($id,$password){if(!one('SELECT id FROM users WHERE id=?',[$id]))throw new DomainException('User not found.'); query('UPDATE users SET password_hash=?,must_change_password=1,auth_version=auth_version+1 WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$id]);audit('Password reset','Users','Reset password for account #'.$id);});flash('Temporary password set.');redirect('users');
    case 'save_role':
        allow(['admin']);$id=(int)($_POST['id']??0);$name=input('name',80);$description=input('description',255,false);
        transact(function()use($id,$name,$description){
            if($id){if(!one('SELECT id FROM roles WHERE id=?',[$id]))throw new DomainException('Role not found.');query('UPDATE roles SET name=?,description=? WHERE id=?',[$name,$description,$id]);}
            else{$code=input('code',30);if(!in_array($code,['admin','chairperson','treasurer','official','resident'],true))throw new DomainException('Choose a system permission template.');query('INSERT INTO roles(name,code,description) VALUES(?,?,?)',[$name,$code,$description]);$id=(int)db()->lastInsertId();}
            audit('Save','Roles','Saved role #'.$id);
        });flash('Role saved.');redirect('users',['tab'=>'roles']);
    case 'delete_role':
        allow(['admin']);$id=idInput('id');transact(function()use($id){$r=one('SELECT * FROM roles WHERE id=? FOR UPDATE',[$id]);if(!$r || $r['system_role'])throw new DomainException('System roles must be retained.');query('DELETE FROM roles WHERE id=?',[$id]);audit('Delete','Roles','Deleted unassigned custom role #'.$id);});flash('Role deleted.');redirect('users',['tab'=>'roles']);
    case 'save_setting': case 'delete_setting':
        allow(['admin']);$type=input('type',30); if(!in_array($type,['categories','fund_sources'],true))throw new DomainException('Invalid setting.');
        $id=(int)($_POST['id']??0);
        transact(function()use($type,$id,$action){
            if($action==='delete_setting') {query("DELETE FROM $type WHERE id=?",[$id]);}
            else { $name=input('name',120);$description=input('description',3000,false); if($type==='categories' && strlen($name)>100)throw new DomainException('Category name is too long.');if($id)query("UPDATE $type SET name=?,description=? WHERE id=?",[$name,$description,$id]);else query("INSERT INTO $type(name,description) VALUES(?,?)",[$name,$description]); }
            audit($action==='delete_setting'?'Delete':'Save','Settings',$type.' record #'.$id);
        });flash('Settings updated.');redirect('settings');
    case 'save_project':
        allow(['official']);$id=(int)($_POST['id']??0);
        $values=[idInput('category_id'),idInput('source_id'),input('title',190),input('description',10000),input('location',190),input('committee',190),input('beneficiaries',255)];
        $count=filter_var($_POST['beneficiary_count']??0,FILTER_VALIDATE_INT);if($count===false || $count<0)throw new DomainException('Invalid beneficiary count.');
        $start=dateInput('start_date');$end=dateInput('end_date');if($end<$start)throw new DomainException('End date must be on or after start date.');
        $planned=cents(input('planned_amount',30));$photo=upload('project_photo');
        transact(function()use($id,$u,$values,$count,$start,$end,$planned,$photo){
            if($id) { $p=ownProject($id);query('UPDATE projects SET category_id=?,source_id=?,title=?,description=?,location=?,committee=?,beneficiaries=?,beneficiary_count=?,start_date=?,end_date=?,planned_cents=?,photo_path=COALESCE(?,photo_path),published=0,publication_requested=1 WHERE id=?',array_merge($values,[$count,$start,$end,$planned,$photo,$id]));
                if(($p['budget_cents']>0 || one('SELECT id FROM fund_requests WHERE project_id=? OR from_project_id=? LIMIT 1',[$id,$id])) && ((int)$values[1]!== (int)$p['source_id'] || (int)$values[0]!== (int)$p['category_id'])) throw new DomainException('Category and fund source cannot change after financial requests exist.');
            } else {query('INSERT INTO projects(owner_id,category_id,source_id,title,description,location,committee,beneficiaries,beneficiary_count,start_date,end_date,planned_cents,photo_path,remarks,publication_requested) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,\'\',1)',array_merge([$u['id']],$values,[$count,$start,$end,$planned,$photo]));$id=(int)db()->lastInsertId();}
            audit('Save','Projects','Submitted project #'.$id.' for review.');
        });unset($_SESSION['proposal_draft']);flash('Project saved and submitted for Chairperson review.');redirect('projects');
    case 'delete_project':
        $id=idInput('id');transact(function()use($id){$p=ownProject($id);if($p['review_status']!=='Pending' || $p['published'] || one('SELECT id FROM fund_requests WHERE project_id=? OR from_project_id=? LIMIT 1',[$id,$id]) || one('SELECT id FROM progress_logs WHERE project_id=? LIMIT 1',[$id]))throw new DomainException('Only untouched pending proposals may be deleted. Keep historical records.');query('DELETE FROM projects WHERE id=?',[$id]);audit('Delete','Projects','Deleted pending proposal #'.$id);});flash('Pending proposal deleted.');redirect('projects');
    case 'progress':
        $id=idInput('project_id');$status=input('status',30);$progress=filter_var($_POST['progress']??'',FILTER_VALIDATE_INT);if(!in_array($status,['Pending','Ongoing','Completed','Cancelled'],true) || $progress===false || $progress<0 || $progress>100 || ($status==='Completed' && $progress!==100))throw new DomainException('Choose a valid status and progress. Completed projects must be 100%.');
        $details=input('details',5000);
        transact(function()use($id,$status,$progress,$details,$u){$p=ownProject($id);if($p['review_status']!=='Approved')throw new DomainException('Wait for project approval before updating progress.');query('UPDATE projects SET status=?,progress=?,published=0,publication_requested=1 WHERE id=?',[$status,$progress,$id]);query('INSERT INTO progress_logs(project_id,user_id,status,progress,details) VALUES(?,?,?,?,?)',[$id,$u['id'],$status,$progress,$details]);audit('Progress','Projects','Updated project #'.$id.'; publication requires review.');});flash('Progress recorded and submitted for publication review.');redirect('projects');
    case 'review_project':
        allow(['chairperson']);$id=idInput('id');$decision=input('decision',30);$remarks=input('remarks',5000,false);if(!in_array($decision,['Approve','Reject','Publish','Unpublish'],true))throw new DomainException('Invalid review action.');
        transact(function()use($id,$decision,$remarks,$u){$p=project($id,true);if($decision==='Publish' && $p['review_status']!=='Approved')throw new DomainException('Approve this proposal before publishing.');if($decision==='Reject' && $p['budget_cents']>0)throw new DomainException('A funded project cannot be rejected. Use monitoring and retain its history.');
            if(in_array($decision,['Approve','Reject'],true))query('UPDATE projects SET review_status=?,remarks=?,reviewed_by=?,published=0 WHERE id=?',[$decision==='Approve'?'Approved':'Rejected',$remarks,$u['id'],$id]);
            else query('UPDATE projects SET published=?,publication_requested=0,remarks=?,reviewed_by=? WHERE id=?',[$decision==='Publish'?1:0,$remarks,$u['id'],$id]);
            audit($decision,'Projects','Project #'.$id);
        });flash('Project review saved. Fund approval remains a separate action.');redirect('proposals',['id'=>$id]);
    case 'fund_entry':
        allow(['treasurer']);$source=idInput('source_id');$amount=cents(input('amount',30));if($amount<=0)throw new DomainException('Amount must be positive.');$date=dateInput('entry_date');$reference=input('reference',150);
        transact(function()use($source,$amount,$date,$reference,$u){query('INSERT INTO fund_entries(source_id,amount_cents,entry_date,reference,created_by) VALUES(?,?,?,?,?)',[$source,$amount,$date,$reference,$u['id']]);audit('Receipt of funds','Funds','Recorded source funding '.money($amount));});flash('Funding recorded from your authorized records.');redirect('allocation');
    case 'fund_request':
        allow(['treasurer']);$id=idInput('project_id');$type=input('type',30);if(!in_array($type,['Allocation','Realignment','Disbursement'],true))throw new DomainException('Invalid request type.');$amount=cents(input('amount',30));if($amount<=0)throw new DomainException('Amount must be positive.');$from=$type==='Realignment'?idInput('from_project_id'):null;$reason=input('reason',5000);$document=upload('document',false,true);
        transact(function()use($id,$type,$amount,$from,$reason,$document,$u){$p=project($id,true);if($p['review_status']!=='Approved')throw new DomainException('The project must be approved first.');if($from===$id)throw new DomainException('Choose different source and destination projects.');if($from){$s=project($from,true);if($s['source_id']!==$p['source_id'])throw new DomainException('Realign only within the same fund source.');}query('INSERT INTO fund_requests(project_id,from_project_id,requested_by,type,amount_cents,reason,document_path) VALUES(?,?,?,?,?,?,?)',[$id,$from,$u['id'],$type,$amount,$reason,$document]);audit('Request','Funds',$type.' requested for project #'.$id);});flash('Request sent to the Chairperson.');redirect('allocation');
    case 'decide_request':
        allow(['chairperson']);$id=idInput('id');$decision=input('decision',20);if(!in_array($decision,['Approved','Rejected'],true))throw new DomainException('Invalid decision.');$notes=input('decision_notes',3000,false);
        transact(function()use($id,$decision,$notes,$u){
            $r=one('SELECT * FROM fund_requests WHERE id=? FOR UPDATE',[$id]);if(!$r || $r['status']!=='Pending')throw new DomainException('This request is no longer pending.');
            // Lock all fund sources in stable order before projects, serializing monetary decisions.
            rows('SELECT id FROM fund_sources ORDER BY id FOR UPDATE');
            $ids=array_filter([(int)$r['project_id'],(int)$r['from_project_id']]);sort($ids);foreach($ids as $pid)project($pid,true);
            $p=project((int)$r['project_id']);$amount=(int)$r['amount_cents'];
            if($decision==='Approved') {
                if($p['review_status']!=='Approved')throw new DomainException('Project is not approved.');
                if($r['type']==='Allocation') {
                    $received=(int)one('SELECT COALESCE(SUM(amount_cents),0) n FROM fund_entries WHERE source_id=?',[$p['source_id']])['n'];
                    $reserved=(int)one('SELECT COALESCE(SUM(budget_cents),0) n FROM projects WHERE source_id=?',[$p['source_id']])['n'];
                    if($received-$reserved<$amount)throw new DomainException('Insufficient unallocated funds for this source.');
                    query('UPDATE projects SET budget_cents=budget_cents+? WHERE id=?',[$amount,$p['id']]);
                } elseif($r['type']==='Disbursement') {
                    if((int)$p['budget_cents']-(int)$p['released_cents']<$amount)throw new DomainException('Disbursement exceeds the unreleased approved budget.');
                    query('UPDATE projects SET released_cents=released_cents+? WHERE id=?',[$amount,$p['id']]);
                } else {
                    $from=project((int)$r['from_project_id']);
                    if($from['source_id']!==$p['source_id'] || $from['id']===$p['id'] || (int)$from['budget_cents']-(int)$from['released_cents']<$amount)throw new DomainException('Insufficient unreleased balance or invalid realignment source.');
                    query('UPDATE projects SET budget_cents=budget_cents-? WHERE id=?',[$amount,$from['id']]);query('UPDATE projects SET budget_cents=budget_cents+? WHERE id=?',[$amount,$p['id']]);
                }
            }
            query('UPDATE fund_requests SET status=?,decision_notes=?,decided_by=?,decided_at=NOW() WHERE id=?',[$decision,$notes,$u['id'],$id]);audit($decision,'Funds','Request #'.$id.' '.$r['type'].' '.money($amount));
        });flash('Request '.strtolower($decision).'.');redirect('approvals');
    case 'expense':
        allow(['treasurer']);$projectId=idInput('project_id');$id=(int)($_POST['id']??0);$amount=cents(input('amount',30));if($amount<=0)throw new DomainException('Expense must be positive.');$date=dateInput('expense_date');$description=input('description',5000);$payee=input('payee',190);$receiptDate=dateInput('receipt_date');$reference=input('receipt_reference',120);$receipt=upload('receipt',!$id);
        transact(function()use($projectId,$id,$amount,$date,$description,$payee,$receiptDate,$reference,$receipt,$u){$p=project($projectId,true);$old=$id?one('SELECT * FROM expenses WHERE id=? FOR UPDATE',[$id]):null;if($id && (!$old || (int)$old['project_id']!==$projectId))throw new DomainException('Expense not found for this project.');if($p['review_status']!=='Approved')throw new DomainException('Project is not approved.');if(spent($projectId)-($old?(int)$old['amount_cents']:0)+$amount>(int)$p['released_cents'])throw new DomainException('Expense exceeds available released funds. Request a disbursement first.');
            if($id)query('UPDATE expenses SET amount_cents=?,expense_date=?,description=?,payee=?,receipt_date=?,receipt_reference=?,receipt_path=COALESCE(?,receipt_path) WHERE id=?',[$amount,$date,$description,$payee,$receiptDate,$reference,$receipt,$id]);
            else {query('INSERT INTO expenses(project_id,recorded_by,amount_cents,expense_date,description,payee,receipt_date,receipt_reference,receipt_path) VALUES(?,?,?,?,?,?,?,?,?)',[$projectId,$u['id'],$amount,$date,$description,$payee,$receiptDate,$reference,$receipt]);$id=(int)db()->lastInsertId();}
            audit('Save expense','Expenses','Expense #'.$id.' for project #'.$projectId.' '.money($amount).($old?' (previous '.money($old['amount_cents']).')':''));
        });flash('Expense and receipt saved.');redirect('transactions');
    default:throw new DomainException('Unknown action.');
    }
}
