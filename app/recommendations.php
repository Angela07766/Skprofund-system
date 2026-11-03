<?php
declare(strict_types=1);

/** Explainable rule-based decision support; no external API or trained model. */
function suggestionTokens(string $text): array
{
    $stop = ['a','an','and','are','as','at','be','by','for','from','has','have','in','is','it','of','on','or','our','that','the','their','these','this','to','we','with','would','need','needs','want','project','projects','program','programme','community','youth','residents','barangay','help','support','provide','improve','improving','more','lack','lacking','mga','ang','ng','sa','para','at','na'];
    $tokens = preg_split('/[^\p{L}\p{N}]+/u', strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return array_values(array_unique(array_filter($tokens, fn($word) => strlen($word) > 2 && !in_array($word, $stop, true))));
}

function suggestionTemplates(): array
{
    // Planning examples only. These are not financial records or promises of outcomes.
    return [
        ['key'=>'learning','title'=>'Youth Learning Support Sessions','categories'=>['education','learning','edukasyon'],
         'tags'=>'reading literacy tutoring mathematics school students study learning edukasyon pag-aaral',
         'objective'=>'Support participants in practising identified learning skills.',
         'activities'=>['Assess learning topics with participants and facilitators.','Organize supervised reading or tutoring sessions.','Collect attendance and participant feedback.']],
        ['key'=>'digital','title'=>'Digital Skills and Online Safety Workshop','categories'=>['education','digital','technology','learning'],
         'tags'=>'computer digital technology internet online safety cyber cybersecurity skills literacy',
         'objective'=>'Develop practical digital skills and awareness of safer online behaviour.',
         'activities'=>['Confirm access to devices and a suitable venue.','Facilitate practical digital skills and online safety exercises.','Gather participant feedback and practical activity results.']],
        ['key'=>'health','title'=>'Youth Health and Hygiene Awareness Activity','categories'=>['health','wellness','kalusugan'],
         'tags'=>'health hygiene sanitation nutrition wellness kalusugan hygiene handwashing',
         'objective'=>'Build awareness of the health and hygiene topics identified by participants.',
         'activities'=>['Consult qualified local health personnel about appropriate content.','Conduct a health and hygiene awareness session.','Collect participant questions and feedback.']],
        ['key'=>'wellbeing','title'=>'Youth Wellbeing Awareness and Referral Session','categories'=>['health','wellness','kalusugan'],
         'tags'=>'mental wellbeing stress emotional counselling anxiety awareness wellness',
         'objective'=>'Increase awareness of wellbeing resources and appropriate professional referral pathways.',
         'activities'=>['Coordinate with qualified health professionals.','Provide an age-appropriate awareness and resource-sharing session.','Share verified local referral information and collect optional feedback.']],
        ['key'=>'cleanup','title'=>'Youth Community Clean-up and Waste Segregation Drive','categories'=>['environment','citizenship','ecology','kalikasan','others'],
         'tags'=>'cleanup clean waste litter garbage segregation recycling plastic basura kalinisan kalikasan',
         'objective'=>'Engage participants in a supervised clean-up and responsible waste segregation activity.',
         'activities'=>['Coordinate a safe site, permissions and waste collection arrangements.','Brief participants on protective equipment and safe segregation.','Conduct the activity and document collected waste and attendance.']],
        ['key'=>'sports','title'=>'Youth Sports Skills and Teamwork Clinic','categories'=>['sports','sport','recreation','physical'],
         'tags'=>'sports basketball volleyball exercise fitness recreation teamwork training equipment',
         'objective'=>'Provide a supervised opportunity to practise sports skills and teamwork.',
         'activities'=>['Confirm participant needs, coaches and a safe venue.','Run inclusive skills practice and teamwork activities.','Document participation, safety concerns and feedback.']],
        ['key'=>'livelihood','title'=>'Youth Livelihood Skills Exploration Workshop','categories'=>['livelihood','employment','economic','entrepreneur'],
         'tags'=>'livelihood employment entrepreneurship business jobs livelihood income skills vocational',
         'objective'=>'Introduce participants to locally relevant livelihood skills and learning opportunities.',
         'activities'=>['Consult participants and suitable local trainers.','Conduct introductory practical skills exercises.','Share verified next-step training information and gather feedback.']],
        ['key'=>'consultation','title'=>'Youth Consultation and Leadership Workshop','categories'=>['citizenship','governance','leadership','participation','others'],
         'tags'=>'leadership consultation participation governance civic volunteer consultation planning',
         'objective'=>'Gather youth priorities and practise collaborative leadership and planning.',
         'activities'=>['Invite participants and prepare an inclusive consultation agenda.','Facilitate priority-setting and collaborative planning exercises.','Document proposed actions and participant feedback.']],
    ];
}

function scaleHistoricalCost(array $project, int $targetCount): ?int
{
    $cost = (int)($project['spent'] ?? 0);
    if ($cost <= 0) return null; // Never invent a historical cost from an empty record.
    $oldCount = (int)($project['beneficiary_count'] ?? 0);
    if ($oldCount <= 0) return $cost;
    if ($cost > intdiv(PHP_INT_MAX - $oldCount + 1, $targetCount)) return PHP_INT_MAX;
    return intdiv($cost * $targetCount + $oldCount - 1, $oldCount);
}

/** Pure ranking function: testable without a database or web session. */
function rankProjectIdeas(array $input, array $category, array $history, array $activeProjects): array
{
    $needTokens = suggestionTokens($input['need']);
    if (!$needTokens) throw new DomainException('Describe a specific need, such as reading skills or waste segregation.');
    $ceiling = (int)$input['ceiling'];
    $targetCount = (int)$input['beneficiary_count'];
    if ($ceiling <= 0 || $targetCount < 1 || $targetCount > 100000) throw new DomainException('Enter a positive budget and between 1 and 100,000 beneficiaries.');
    $history = array_values(array_filter($history, fn($p) => (int)$p['category_id'] === (int)$category['id'] && $p['status'] === 'Completed' && (bool)$p['published'] && $p['review_status'] === 'Approved'));
    $candidates = [];
    foreach ($history as $p) {
        $candidates[] = [
            'key'=>'history:'.$p['id'], 'title'=>'Follow-up: '.$p['title'],
            'search'=>$p['title'].' '.$p['description'].' '.$category['name'],
            'estimate'=>scaleHistoricalCost($p, $targetCount),
            'cost_basis'=>(int)$p['beneficiary_count'] > 0 ? 'Recorded expense scaled by beneficiary count; obtain current quotations.' : 'Recorded whole-project expense; scope is not adjusted because the old beneficiary count is unavailable.',
            'reference_id'=>(int)$p['id'], 'reference_title'=>$p['title'],
            'objective'=>'Assess whether a revised version of this recorded project addresses the current need.',
            'activities'=>['Review the completed project and confirm its suitability for the current participants.','Prepare updated activities, quantities and quotations with the responsible committee.','Define attendance, outputs and feedback measures before submitting the proposal.'],
            'evidence'=>20, 'kind'=>'Historical reference',
        ];
    }
    $benchmarks = [];
    foreach ($history as $p) if ((int)$p['beneficiary_count'] > 0 && ($cost=scaleHistoricalCost($p, $targetCount)) !== null) $benchmarks[]=$cost;
    sort($benchmarks);
    $benchmark = null;
    if (count($benchmarks)>=3) {
        $middle=intdiv(count($benchmarks),2);
        $benchmark=count($benchmarks)%2 ? $benchmarks[$middle] : $benchmarks[$middle-1]+intdiv($benchmarks[$middle]-$benchmarks[$middle-1],2);
    }
    foreach (suggestionTemplates() as $template) {
        $compatible=false;
        foreach ($template['categories'] as $term) if (str_contains(strtolower($category['name']),$term)) {$compatible=true;break;}
        if (!$compatible) continue;
        $candidates[] = [
            'key'=>'template:'.$template['key'], 'title'=>$template['title'],
            'search'=>$template['title'].' '.$template['tags'],
            'estimate'=>$benchmark,
            'cost_basis'=>$benchmark !== null ? 'Median per-beneficiary expense scaled to your target count, from '.count($benchmarks).' completed public projects in this category. Scope and current prices still need verification.' : 'No reliable historical cost benchmark. Your budget ceiling is a planning placeholder, not a cost estimate.',
            'reference_id'=>null,'reference_title'=>null,'objective'=>$template['objective'],
            'activities'=>$template['activities'],'evidence'=>$benchmark!==null?10:0,'kind'=>'Planning template',
        ];
    }
    $results=[];$excluded=0;
    foreach ($candidates as $candidate) {
        $matched=array_values(array_intersect($needTokens,suggestionTokens($candidate['search'])));
        if (!$matched) continue;
        if ($candidate['estimate'] !== null && $candidate['estimate']>$ceiling) {$excluded++;continue;}
        $needScore=(int)round(60*min(1,count($matched)/min(5,count($needTokens))));
        $budgetScore=$candidate['estimate']!==null?20:0;
        $duplicate=false;
        $candidateTokens=suggestionTokens($candidate['title']);
        foreach ($activeProjects as $p) {
            if (!in_array($p['status'],['Pending','Ongoing'],true) || $p['review_status']==='Rejected') continue;
            $tokens=suggestionTokens($p['title']);
            $overlap=count(array_intersect($candidateTokens,$tokens))/max(1,count(array_unique(array_merge($candidateTokens,$tokens))));
            if ($overlap>=0.65) {$duplicate=true;break;}
        }
        $candidate['score']=max(0,$needScore+$budgetScore+$candidate['evidence']-($duplicate?15:0));
        $candidate['matched']=$matched;
        $candidate['reasons']=[
            'Need match: '.implode(', ',$matched).' ('.$needScore.'/60).',
            $candidate['estimate']!==null ? 'Historical estimate fits your ceiling (20/20).' : 'Affordability is unverified because cost data is unavailable (0/20).',
            'History evidence: '.$candidate['evidence'].'/20.',
        ];
        if ($duplicate) $candidate['reasons'][]='A similar active project exists; check for duplication (-15 points).';
        $candidate['planning_cents']=$candidate['estimate']??$ceiling;
        $results[]=$candidate;
    }
    usort($results,fn($a,$b)=>($b['score']<=>$a['score'])?:strcmp($a['key'],$b['key']));
    return ['ideas'=>array_slice($results,0,5),'over_budget'=>$excluded];
}

function generateProjectIdeas(array $input): array
{
    $u=allow(['official']);
    $category=one('SELECT * FROM categories WHERE id=?',[$input['category_id']]);
    $source=one('SELECT f.*,COALESCE((SELECT SUM(amount_cents) FROM fund_entries WHERE source_id=f.id),0)-COALESCE((SELECT SUM(budget_cents) FROM projects WHERE source_id=f.id),0) available FROM fund_sources f WHERE f.id=?',[$input['source_id']]);
    if (!$category || !$source) throw new DomainException('Choose an existing category and fund source.');
    $history=rows("SELECT p.*,COALESCE((SELECT SUM(amount_cents) FROM expenses WHERE project_id=p.id),0) spent FROM projects p WHERE p.category_id=? AND p.published=1 AND p.review_status='Approved' AND p.status='Completed' ORDER BY p.end_date DESC,p.id DESC LIMIT 200",[$category['id']]);
    // Only own or public active projects are considered; private proposals of other officials stay private.
    $active=rows("SELECT title,status,review_status FROM projects WHERE category_id=? AND (published=1 OR owner_id=?) AND status IN ('Pending','Ongoing')",[$category['id'],$u['id']]);
    return rankProjectIdeas($input,$category,$history,$active)+['category'=>$category['name'],'source'=>$source['name'],'available'=>(int)$source['available']];
}

function suggestionDraft(array $idea, array $input): array
{
    $description="DRAFT FOR HUMAN REVIEW\n\nIdentified need (provided by the official):\n".$input['need']."\n\nProposed objective:\n".$idea['objective']."\n\nSuggested activities:\n";
    foreach ($idea['activities'] as $i=>$activity) $description.=($i+1).'. '.$activity."\n";
    $description.="\nTarget participants (provided by the official):\n".$input['beneficiaries'].'; proposed count: '.$input['beneficiary_count']."\n\nBudget planning:\n";
    $description.=($idea['estimate']!==null?'Historical cost estimate: ':'User-supplied planning ceiling (not an estimate): ').money($idea['planning_cents']).".\n".$idea['cost_basis'];
    if ($idea['reference_id']) $description.="\nHistorical reference: project #".$idea['reference_id'].' — '.$idea['reference_title'];
    $description.="\n\nBefore submission: confirm the scope, quotations, venue, schedule, committee, safeguards and measurable outputs. This draft does not approve or reserve any funds.";
    $title=substr($idea['title'],0,185);
    while($title!=='' && !preg_match('//u',$title)) $title=substr($title,0,-1);
    return ['id'=>0,'title'=>$title,'description'=>$description,'category_id'=>$input['category_id'],'source_id'=>$input['source_id'],'planned_cents'=>$idea['planning_cents'],'beneficiaries'=>$input['beneficiaries'],'beneficiary_count'=>$input['beneficiary_count']];
}
