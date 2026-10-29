<?php
/** Narrow compatibility corrections for the official MIGX 3.0.2-beta1 package on strict MySQL 8. */
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__,2).'/public/core/components/migx/';
$p=$root.'processors/mgr/default/getcombo.php';$s=file_get_contents($p);
$changes=[
"\$c->select('id,YEAR(' . \$textfield . ') as combo_id , YEAR(' . \$textfield . ') as combo_name');"=>"\$c->select('MIN(id) as id,YEAR(' . \$textfield . ') as combo_id , YEAR(' . \$textfield . ') as combo_name');",
"\$c->select('id, MONTH(' . \$textfield . ') as combo_id, MONTH(' . \$textfield . ') as combo_name,YEAR(`' . \$classname . '`.`' . \$textfield . '`) as year');"=>"\$c->select('MIN(id) as id, MONTH(' . \$textfield . ') as combo_id, MONTH(' . \$textfield . ') as combo_name,MIN(YEAR(`' . \$classname . '`.`' . \$textfield . '`)) as year');",
"\$c->select(\$classname . '.id, ' . \$idfield . ' as combo_id, ' . \$textfield . ' as combo_name');"=>"\$c->select('MIN(' . \$classname . '.id) as id, MIN(' . \$idfield . ') as combo_id, ' . \$textfield . ' as combo_name');",
];foreach($changes as $from=>$to){if(str_contains($s,$from))$s=str_replace($from,$to,$s);elseif(!str_contains($s,$to))throw new RuntimeException('MIGX package differs from expected 3.0.2-beta1; review patch.');}file_put_contents($p,$s);
$p=$root.'controllers/default/index.class.php';$s=file_get_contents($p);$s=str_replace("\$_REQUEST['configs'];","(\$_REQUEST['configs'] ?? 'migxconfigs||packagemanager');",$s);file_put_contents($p,$s);
$p=$root.'configs/migxconfigs.config.inc.php';$s=file_get_contents($p);
$from="\$c->groupby('category');";
$to="\$c->select('category');\n    \$c->groupby('category');";
if(!str_contains($s,"\$c->select('category');"))$s=str_replace($from,$to,$s);
file_put_contents($p,$s);
echo "MIGX grouped filters corrected without changing the MySQL SQL mode.\n";
