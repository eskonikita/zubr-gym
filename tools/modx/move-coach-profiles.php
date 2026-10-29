<?php
/** Move profile resources below the coaches listing, retaining their existing public URLs. */
if(PHP_SAPI!=='cli'||!in_array('--apply',$argv,true))exit("Usage: php tools/modx/move-coach-profiles.php --apply\n");
$root=dirname(__DIR__,2).'/';define('MODX_API_MODE',true);require $root.'public/index.php';$modx->initialize('mgr');
use MODX\Revolution\modResource;
$listing=$modx->getObject(modResource::class,10);$container=$modx->getObject(modResource::class,4);
if(!$listing||$listing->get('uri')!=='club/coaches/')throw new RuntimeException('Unexpected resource 10');
if($container&&$container->get('uri')!=='coaches/')throw new RuntimeException('Unexpected resource 4');
$profiles=$modx->getCollection(modResource::class,['context_key'=>'web','uri:LIKE'=>'coaches/%','id:!='=>4]);
$backup=['container'=>$container?$container->toArray():null,'listing'=>$listing->toArray(),'profiles'=>[]];foreach($profiles as $r)$backup['profiles'][]=$r->toArray();
file_put_contents($root.'cms/backups/before-move-coaches-'.date('Ymd-His').'.json',json_encode($backup,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
$moved=[];foreach($profiles as $r){$uri=$r->get('uri');$r->set('parent',10);$r->set('uri_override',1);$r->set('uri',$uri);if(!$r->save())throw new RuntimeException('Move failed');$moved[]=(int)$r->get('id');}
$listing->set('isfolder',1);if(!$listing->save())throw new RuntimeException('Listing save failed');
if($container){if($modx->getCount(modResource::class,['parent'=>4]))throw new RuntimeException('Container still has children');if(!$container->remove())throw new RuntimeException('Container remove failed');}
$report=$root.'cms/import-result.json';if(is_file($report)){$data=json_decode(file_get_contents($report),true);$data['resources']=array_values(array_filter($data['resources'],fn($r)=>$r['id']!==4));foreach($data['resources'] as &$r)if(in_array($r['id'],$moved,true))$r['parent']=10;unset($r);$data['count']=count($data['resources']);file_put_contents($report,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");}
$modx->cacheManager->refresh();echo json_encode(['parent'=>10,'moved'=>$moved,'removed_container'=>4],JSON_PRETTY_PRINT).PHP_EOL;
