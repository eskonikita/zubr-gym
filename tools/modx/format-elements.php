<?php
/** Read current database elements or apply a reviewed whitespace-only formatting plan. */
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__,2).'/';define('MODX_API_MODE',true);require $root.'public/index.php';$modx->initialize('mgr');require_once $root.'cms/elements/TemplateRegistry.php';
use MODX\Revolution\{modTemplate,modChunk,modResource,modSystemSetting};
$registry=json_decode(file_get_contents($root.'cms/template-registry.json'),true,512,JSON_THROW_ON_ERROR);
$types=['template'=>[modTemplate::class,'content'],'chunk'=>[modChunk::class,'snippet'],'resource'=>[modResource::class,'content']];
if(in_array('--export',$argv,true)){
    $rows=[];
    foreach($modx->getCollection(modTemplate::class,Zubr\Site\TemplateRegistry::criteria()) as $o)$rows[]=['kind'=>'template','id'=>(int)$o->id,'before'=>$o->get('content')];
    foreach($modx->getCollection(modChunk::class,['name:IN'=>$registry['chunks']]) as $o)$rows[]=['kind'=>'chunk','id'=>(int)$o->id,'before'=>$o->get('snippet')];
    foreach($modx->getCollection(modResource::class,['context_key'=>'web']) as $o)if(preg_match('/<[a-z][^>]*>/i',(string)$o->get('content')))$rows[]=['kind'=>'resource','id'=>(int)$o->id,'before'=>$o->get('content')];
    file_put_contents($root.'.tmp-formatting-input.json',json_encode($rows,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));exit('Exported '.count($rows)." elements.\n");
}
if(!in_array('--apply',$argv,true))exit("Usage: --export, then python tools/modx/format-markup.py, then --apply\n");
$plan=json_decode(file_get_contents($root.'.tmp-formatting-plan.json'),true,512,JSON_THROW_ON_ERROR);
// Check all records before changing any, so concurrent manager edits are never overwritten.
foreach($plan as $row){[$class,$field]=$types[$row['kind']];$o=$modx->getObject($class,$row['id']);if(!$o||$o->get($field)!==$row['before'])throw new RuntimeException('Element changed after export: '.$row['kind'].' '.$row['id']);}
file_put_contents($root.'cms/backups/before-formatting-'.date('Ymd-His').'.json',json_encode($plan,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
foreach($plan as $row){[$class,$field]=$types[$row['kind']];$o=$modx->getObject($class,$row['id']);$o->set($field,$row['after']);if(!$o->save())throw new RuntimeException('Save failed');}
foreach(['ace.soft_tabs'=>'0','ace.tab_size'=>'4'] as $key=>$value){$s=$modx->getObject(modSystemSetting::class,['key'=>$key]);if($s){$s->set('value',$value);$s->save();}}
$modx->cacheManager->refresh();echo 'Formatted '.count($plan)." elements; indentation uses literal tabs.\n";
