<?php
function url(string $page,array $args=[]): string {return 'index.php?'.http_build_query(['page'=>$page]+$args);}
function badge(string $status): string {return '<span class="badge '.(in_array($status,['Approved','Completed','Active'],true)?'positive':'').'">'.e($status).'</span>';}
function field(string $label,string $name,mixed $value='',string $type='text',bool $required=true,string $extra=''): void {
    echo '<label>'.e($label).'<input type="'.e($type).'" name="'.e($name).'" value="'.e($value).'" '.($required?'required ':'').$extra.'></label>';
}
function textarea(string $label,string $name,mixed $value='',bool $required=true,int $max=5000): void {echo '<label>'.e($label).'<textarea name="'.e($name).'" maxlength="'.$max.'" '.($required?'required':'').'>'.e($value).'</textarea></label>';}
function selectField(string $label,string $name,array $options,mixed $selected='',bool $required=true): void {
    echo '<label>'.e($label).'<select name="'.e($name).'" '.($required?'required':'').'>';if($required)echo '<option value="">Select…</option>';
    foreach($options as $key=>$value)echo '<option value="'.e($key).'" '.((string)$selected===(string)$key?'selected':'').'>'.e($value).'</option>';echo '</select></label>';
}
function options(string $table): array {return array_column(rows('SELECT id,name FROM '.$table.' ORDER BY name'),'name','id');}
function emptyRow(int $columns,string $message='No records yet.'): void {echo '<tr><td colspan="'.$columns.'" class="empty">'.e($message).'</td></tr>';}
function metric(string $label,string|int $value,string $icon='▤'): void {echo '<article class="metric"><span class="metric-icon" aria-hidden="true">'.e($icon).'</span><div><span>'.e($label).'</span><strong>'.e($value).'</strong></div></article>';}
function projectSelect(string $name='project_id',mixed $selected='',bool $approved=false): void { $ps=projectList();$opts=[];foreach($ps as $p)if(!$approved || $p['review_status']==='Approved')$opts[$p['id']]=$p['title'];selectField('Project / Activity',$name,$opts,$selected);}
function chart(array $values,string $title): void {
    $total=array_sum($values);echo '<section class="panel"><h2>'.e($title).'</h2><div class="chart-row"><svg class="donut" viewBox="0 0 120 120" role="img" aria-label="'.e($title).'">';
    echo '<circle class="ring-base" cx="60" cy="60" r="44" pathLength="100"/>';$offset=0;$i=0;
    foreach($values as $value){$pct=$total?$value/$total*100:0;echo '<circle class="segment segment-'.($i++%4).'" cx="60" cy="60" r="44" pathLength="100" stroke-dasharray="'.$pct.' '.(100-$pct).'" stroke-dashoffset="'.(-$offset).'"/>';$offset+=$pct;}
    echo '<text x="60" y="64" text-anchor="middle">'.($total?(string)round(reset($values)/$total*100).'%':'0%').'</text><text class="chart-sub" x="60" y="79" text-anchor="middle">'.e(array_key_first($values)??'Projects').'</text></svg><ul class="legend">';foreach($values as $label=>$value)echo '<li>'.e($label).'<strong>'.e($value).'</strong></li>';echo '</ul></div></section>';
}
function projectCard(array $p,bool $public=false): void {echo '<article class="project-card">';if($p['photo_path'])echo '<img loading="lazy" src="'.e(url('file',['kind'=>'project','id'=>$p['id']])).'" alt="">';else echo '<div class="project-placeholder" aria-hidden="true">▧</div>';echo '<div>'.badge($p['status']).'<h3>'.e($p['title']).'</h3><progress max="100" value="'.e($p['progress']).'">'.e($p['progress']).'%</progress><small>'.e($p['progress']).'% · Due '.e($p['end_date']).'</small><a href="'.e(url('projects',['id'=>$p['id']])).'">View Details</a></div></article>';}
function projectDetail(array $p): void {
    echo '<section class="panel"><div class="section-heading"><h2>Project Details</h2><a class="button secondary" href="'.e(url('projects')).'">Close</a></div>';
    if($p['photo_path'])echo '<img class="detail-photo" src="'.e(url('file',['kind'=>'project','id'=>$p['id']])).'" alt="">';
    echo '<h2>'.e($p['title']).'</h2>'.badge($p['status']).'<div class="grid three"><div><h3>Timeline</h3>'.e($p['start_date']).' – '.e($p['end_date']).'</div><div><h3>Target Beneficiaries</h3>'.e($p['beneficiaries']).'</div><div><h3>Project Location</h3>'.e($p['location']).'</div></div><h3>Project Description</h3><p class="prewrap">'.e($p['description']).'</p><progress max="100" value="'.e($p['progress']).'">'.e($p['progress']).'%</progress><p>'.e($p['progress']).'% complete</p></section>';
}
function barChart(array $values): void {
    arsort($values);$values=array_slice($values,0,8,true);$max=$values?max($values):0;
    echo '<svg class="bar-chart" viewBox="0 0 560 250" role="img" aria-label="Allocated funds by category in pesos">';
    echo '<path class="axis" d="M 55 15 V 205 H 550"/>';
    $count=count($values);$i=0;
    foreach($values as $label=>$value){$slot=480/max(1,$count);$width=min(65,$slot-12);$height=$max?$value/$max*150:0;$x=65+$i*$slot;echo '<rect class="bar" x="'.$x.'" y="'.(205-$height).'" width="'.$width.'" height="'.$height.'"/><text class="bar-value" x="'.($x+$width/2).'" y="'.max(16,195-$height).'" text-anchor="middle">'.e(money((int)$value)).'</text><text class="bar-label" x="'.($x+$width/2).'" y="225" text-anchor="middle">'.e(strlen($label)>12?substr($label,0,12).'…':$label).'</text>';$i++;}
    if(!$count)echo '<text x="280" y="110" text-anchor="middle">No allocations recorded</text>';
    echo '</svg>';
}
