<?php
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__,2).'/';define('MODX_API_MODE',true);require $root.'public/index.php';$modx->initialize('mgr');
use MODX\Revolution\{modChunk,modTemplate};
$head=$modx->getObject(modChunk::class,['name'=>'head']);$content=$head->get('snippet');if(!str_contains($content,'[[$svgSprite]]')){$head->set('snippet',rtrim($content)."\n\t\t[[$".'svgSprite'."]]\n");if(!$head->save())throw new RuntimeException('Head save failed');}
$r=json_decode(file_get_contents($root.'cms/template-registry.json'),true);$count=0;
foreach($r['templates'] as $row){$t=$modx->getObject(modTemplate::class,$row['id']);$html=preg_replace('/^[\t ]*\[\[\$svgSprite\]\][\t ]*\R?/m','',$t->get('content'),-1,$n);if($n){$t->set('content',$html);if(!$t->save())throw new RuntimeException('Template save failed');$count++;}}
$modx->cacheManager->refresh();echo "Sprite moved to shared head; updated $count templates.\n";
