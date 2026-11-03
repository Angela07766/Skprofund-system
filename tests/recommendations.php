<?php
require __DIR__.'/../app/bootstrap.php';
require ROOT.'/app/recommendations.php';
$count=0;
function expect(bool $condition,string $label): void {global $count;if(!$condition)throw new RuntimeException('FAIL: '.$label);$count++;echo 'PASS '.$label."\n";}
$input=['need'=>'Students need reading tutoring and mathematics practice.','ceiling'=>100000,'beneficiary_count'=>20,'beneficiaries'=>'Students','category_id'=>1,'source_id'=>1];
$category=['id'=>1,'name'=>'Education'];
$result=rankProjectIdeas($input,$category,[],[]);
expect(count($result['ideas'])>0,'cold-start templates generate relevant ideas without fake history');
$idea=$result['ideas'][0];
expect($idea['key']==='template:learning','reading and tutoring match learning template');
expect($idea['estimate']===null && $idea['planning_cents']===100000,'no cost invented; user ceiling is explicit placeholder');
expect($idea['score']<=60,'no history or affordability points without evidence');
$draft=suggestionDraft($idea,$input);
expect($draft['id']===0 && !isset($draft['budget_cents']),'generated draft is unsaved and does not allocate money');
expect(str_contains($draft['description'],'not an estimate') && str_contains($draft['description'],'DRAFT FOR HUMAN REVIEW'),'draft labels planning assumptions');
expect($draft['beneficiaries']==='Students' && $draft['beneficiary_count']===20,'draft preserves supplied participants');
expect(!isset($draft['start_date']) && !isset($draft['location']),'generator does not invent dates or location');
$history=[['id'=>1,'title'=>'Reading tutoring and mathematics','description'=>'Literacy students','category_id'=>1,'status'=>'Completed','published'=>1,'review_status'=>'Approved','spent'=>50000,'beneficiary_count'=>10]];
$result=rankProjectIdeas($input,$category,$history,[]);
expect($result['ideas'][0]['key']==='history:1' && $result['ideas'][0]['estimate']===100000,'historical cost scaled to target participants');
expect($result['ideas'][0]['score']>$idea['score'],'actual history outranks unevidenced template when relevance matches');
$small=$input;$small['ceiling']=99999;
$result=rankProjectIdeas($small,$category,$history,[]);
expect($result['over_budget']===1 && !in_array('history:1',array_column($result['ideas'],'key')),'over-budget historical candidate excluded');
$private=$history;$private[0]['published']=0;
expect(!in_array('history:1',array_column(rankProjectIdeas($input,$category,$private,[])['ideas'],'key')),'private completed projects excluded');
$pending=$history;$pending[0]['status']='Pending';
expect(!in_array('history:1',array_column(rankProjectIdeas($input,$category,$pending,[])['ideas'],'key')),'uncompleted records excluded');
$rejected=$history;$rejected[0]['review_status']='Rejected';
expect(!in_array('history:1',array_column(rankProjectIdeas($input,$category,$rejected,[])['ideas'],'key')),'unapproved historical evidence excluded');
$histories=[];foreach([20000,40000,60000] as $i=>$cost){$p=$history[0];$p['id']=$i+1;$p['spent']=$cost;$histories[]=$p;}
$result=rankProjectIdeas($input,$category,$histories,[]);
$templates=array_values(array_filter($result['ideas'],fn($p)=>$p['key']==='template:learning'));
expect($templates[0]['estimate']===80000,'three-record median benchmark scaled to target participants');
$active=[['title'=>'Youth Learning Support Sessions','status'=>'Ongoing','review_status'=>'Approved']];
$duplicate=rankProjectIdeas($input,$category,[],$active)['ideas'][0];
expect($duplicate['score']===$idea['score']-15,'similar active project reduces recommendation rank');
$unknown=$input;$unknown['need']='astronomy telescope observation';
expect(rankProjectIdeas($unknown,$category,[],[])['ideas']===[],'unrelated need yields no manufactured match');
expect(rankProjectIdeas($input,['id'=>99,'name'=>'Custom Category'],[],[])['ideas']===[],'unknown category needs verified history');
try{$bad=$input;$bad['ceiling']=0;rankProjectIdeas($bad,$category,[],[]);expect(false,'zero ceiling');}catch(DomainException){expect(true,'invalid budget rejected');}
try{$bad=$input;$bad['beneficiary_count']=100001;rankProjectIdeas($bad,$category,[],[]);expect(false,'invalid count');}catch(DomainException){expect(true,'oversized target rejected');}
expect(rankProjectIdeas($input,$category,$histories,[])===rankProjectIdeas($input,$category,$histories,[]),'ranking deterministic for same data');
echo "$count recommendation checks passed\n";
