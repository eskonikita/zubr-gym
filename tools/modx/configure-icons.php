<?php
/** Install icon declarations without changing page content or SEO fields. */
if(PHP_SAPI!=='cli'||!in_array('--apply',$argv,true))exit("Usage: php tools/modx/configure-icons.php --apply\n");
$root=dirname(__DIR__,2).'/';define('MODX_API_MODE',true);require $root.'public/index.php';$modx->initialize('mgr');require_once $root.'cms/elements/TemplateRegistry.php';require_once $root.'cms/elements/Icons.php';
$backup=[];$count=0;foreach($modx->getCollection(MODX\Revolution\modTemplate::class,Zubr\Site\TemplateRegistry::criteria()) as $t){$backup[]=$t->toArray();}
file_put_contents($root.'cms/backups/before-icons-'.date('Ymd-His').'.json',json_encode($backup,JSON_UNESCAPED_UNICODE));
$sharedHead=$modx->getObject(MODX\Revolution\modChunk::class,['name'=>'head']);
if($sharedHead){file_put_contents($root.'cms/backups/before-icons-head-'.date('Ymd-His').'.json',json_encode($sharedHead->toArray(),JSON_UNESCAPED_UNICODE));$sharedHead->set('snippet',Zubr\Site\Icons::prepareTemplate($sharedHead->get('snippet')));if(!$sharedHead->save())throw new RuntimeException('Shared head update failed');}
foreach($backup as $row){$t=$modx->getObject(MODX\Revolution\modTemplate::class,$row['id']);$t->set('content',Zubr\Site\Icons::prepareTemplate($row['content']));if(!$t->save())throw new RuntimeException('Template save failed');$count++;}
$manifest=json_decode(file_get_contents($root.'cms/static-pages.json'),true);$sources=array_column($manifest['pages'],'source');$sources[]='public/zubr/home.html';
foreach(array_unique(array_filter($sources)) as $source){$file=$root.$source;if(is_file($file))file_put_contents($file,Zubr\Site\Icons::prepareTemplate(file_get_contents($file)));}
$modx->cacheManager->refresh();echo "Icons configured in $count MODX templates and static sources.\n";
