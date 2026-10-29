<?php
/** Shared coach section with native pdoResources and resource-ID links. */
if(PHP_SAPI!=='cli'||!in_array('--apply',$argv,true))exit("Usage: php tools/modx/configure-pdo-coaches.php --apply\n");
$root=dirname(__DIR__,2).'/';define('MODX_API_MODE',true);require $root.'public/index.php';$modx->initialize('mgr');
use MODX\Revolution\{modCategory,modChunk,modSnippet,modTemplate};
$category=$modx->getObject(modCategory::class,['category'=>'Общие']);
$names=['coachCard','coachDot','coachesSection','coachesHomeHeading','coachesListHeading'];
foreach($names as $name){$c=$modx->getObject(modChunk::class,['name'=>$name])?:$modx->newObject(modChunk::class);$c->fromArray(['name'=>$name,'category'=>$category->get('id'),'snippet'=>file_get_contents($root.'cms/chunks/'.$name.'.html')]);if(!$c->save())throw new RuntimeException('Chunk save failed');}
foreach([2,10] as $id){$t=$modx->getObject(modTemplate::class,$id);$t->set('content',file_get_contents($root.'cms/templates/structured/'.$id.'.html'));if(!$t->save())throw new RuntimeException('Template save failed');}
$s=$modx->getObject(modSnippet::class,['name'=>'Coaches']);if($s)$s->remove();
$file=$root.'cms/template-registry.json';$r=json_decode(file_get_contents($file),true);$r['chunks']=array_values(array_unique(array_merge($r['chunks'],$names)));file_put_contents($file,json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
$modx->cacheManager->refresh();echo "Shared section and pdoResources applied.\n";
