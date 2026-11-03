<?php
allow(['official']);
$saved=$_SESSION['suggestions']??null;
if($saved && ((int)$saved['uid']!==(int)$user['id'] || time()-$saved['created']>1800))$saved=null;
$criteria=$saved['criteria']??[];
?>
<section class="panel" id="project-generator">
    <h2 tabindex="-1">Project Suggestion &amp; Draft Generator</h2>
    <p class="muted">Describe a specific youth need. The system ranks matching planning ideas and completed project references using transparent rules, then prepares an editable proposal draft.</p>
    <form method="post">
        <?php actionFields('generate_ideas');textarea('Youth need / problem to address','need',$criteria['need']??'',true,2000); ?>
        <div class="grid two">
            <?php selectField('Category','category_id',options('categories'),$criteria['category_id']??'');selectField('Fund Source','source_id',options('fund_sources'),$criteria['source_id']??'');field('Budget Ceiling (PHP)','budget_ceiling',isset($criteria['ceiling'])?number_format($criteria['ceiling']/100,2,'.',''):'','number',true,'min="0.01" step="0.01"');field('Proposed Beneficiary Count','beneficiary_count',$criteria['beneficiary_count']??'','number',true,'min="1" max="100000"'); ?>
        </div>
        <?php field('Target Beneficiaries','beneficiaries',$criteria['beneficiaries']??'','text',true,'maxlength="255"'); ?>
        <button>Generate Project Suggestions</button>
    </form>
    <details><summary>How recommendations work</summary><p class="note">Scores combine need-keyword relevance (up to 60), a historical cost estimate fitting your ceiling (20), and history evidence (up to 20). A similar visible active project reduces the score by 15. Scores are rule-based rankings, not success probabilities. Template ideas can work without history, but their costs remain unverified. Historical estimates are planning references and need updated quotations. This is not AI or machine learning.</p></details>
    <?php if($saved):$result=$saved['generated']; ?>
        <h3>Ranked Suggestions</h3>
        <p class="note">Selected source: <?= e($result['source']) ?>. Unallocated recorded funds at generation: <?= money($result['available']) ?>. This is a snapshot, not a reservation. Generation neither approves projects nor posts financial records.</p>
        <?php if($result['over_budget']): ?><p><?= e($result['over_budget']) ?> matching candidate(s) excluded because their historical estimate exceeds your budget ceiling.</p><?php endif ?>
        <?php foreach($result['ideas'] as $idea): ?>
            <article class="suggestion-card">
                <div class="section-heading"><h3><?= e($idea['title']) ?></h3><?= badge((string)$idea['score'].' / 100') ?></div>
                <p><strong><?= e($idea['kind']) ?></strong> · <?= e($result['category']) ?></p>
                <p><?= e($idea['objective']) ?></p>
                <p><strong><?= $idea['estimate']!==null?'Historical estimate':'Planning ceiling only' ?>:</strong> <?= money($idea['planning_cents']) ?></p>
                <p class="muted"><?= e($idea['cost_basis']) ?></p>
                <?php if($idea['planning_cents']>$result['available']): ?><p class="alert">This amount exceeds current unallocated source funds. Additional funding or a different scope needs review before allocation.</p><?php endif ?>
                <ul><?php foreach($idea['reasons'] as $reason): ?><li><?= e($reason) ?></li><?php endforeach ?></ul>
                <?php if($idea['reference_id']): ?><p class="muted">Historical reference #<?= e($idea['reference_id']) ?>: <?= e($idea['reference_title']) ?></p><?php endif ?>
                <form method="post"><?php actionFields('use_suggestion'); ?><input type="hidden" name="suggestion_key" value="<?= e($idea['key']) ?>"><button class="small">Generate Proposal Draft</button></form>
            </article>
        <?php endforeach ?>
        <?php if(!$result['ideas']): ?><p class="empty">No suitable matches found. Try a more specific need, a relevant category, or a revised budget. Add verified completed history for custom categories; the generator will not invent supporting records.</p><?php endif ?>
    <?php endif ?>
</section>
