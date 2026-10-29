<?php
if(PHP_SAPI!=='cli')exit(1);$root=dirname(__DIR__,2).'/';define('MODX_API_MODE',true);require $root.'public/index.php';$modx->initialize('mgr');
foreach(['articleIntro','svgSprite','head'] as $name){$c=$modx->getObject(MODX\Revolution\modChunk::class,['name'=>$name]);$c->set('snippet',file_get_contents($root.'cms/chunks/'.$name.'.html'));if(!$c->save())throw new RuntimeException('Chunk save failed');}$modx->cacheManager->refresh();echo "Solid article badges and mobile breadcrumb scrolling applied.\n";
