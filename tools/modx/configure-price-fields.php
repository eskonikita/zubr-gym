<?php
/** Move editable price cards into MIGX; keep existing TV values on repeat runs. */
if(PHP_SAPI!=='cli'||!in_array('--apply',$argv,true))exit("Usage: php tools/modx/configure-price-fields.php --apply\n");
$root=dirname(__DIR__,2).'/';define('MODX_API_MODE',true);require $root.'public/index.php';$modx->initialize('mgr');
use MODX\Revolution\{modCategory,modChunk,modResource,modTemplate,modTemplateVar,modTemplateVarResource,modTemplateVarTemplate};
function category($modx,$name){$c=$modx->getObject(modCategory::class,['category'=>$name]);if(!$c){$c=$modx->newObject(modCategory::class);$c->set('category',$name);if(!$c->save())throw new RuntimeException('Category failed');}return $c->get('id');}
$category=category($modx,'Цены');$common=category($modx,'Общие');
$cards=[];foreach([['Дети','5–10 лет','90'],['Подростки','11–17 лет','100'],['Взрослые','18+','110']] as $i=>$row)$cards[]=['MIGX_id'=>$i+1,'title'=>$row[0],'age'=>$row[1],'amount'=>$row[2],'currency'=>'BYN','quantity'=>'12 групповых занятий','badge'=>'Абонемент'];
$definitions=[
 'price_cards'=>['Абонементы — карточки','migx',json_encode($cards,JSON_UNESCAPED_UNICODE)],
 'prices_group_heading'=>['Заголовок групповых тренировок','text','Групповые <span>тренировки</span>'],
 'prices_personal_heading'=>['Заголовок персональных тренировок','text','Персональные <span>тренировки</span>'],
 'prices_personal_description'=>['Описание персональных тренировок','textarea','Стоимость обсуждается индивидуально с учётом программы занятий.'],
 'prices_personal_button'=>['Текст кнопки персональных тренировок','text','Обсудить условия'],
];
$registryFile=$root.'cms/template-registry.json';$registry=json_decode(file_get_contents($registryFile),true);
foreach($definitions as $name=>[$caption,$type,$seed]){
 $tv=$modx->getObject(modTemplateVar::class,['name'=>$name])?:$modx->newObject(modTemplateVar::class);
 $tv->fromArray(['name'=>$name,'caption'=>$caption,'type'=>$type,'category'=>$category,'rank'=>array_search($name,array_keys($definitions)),'default_text'=>'','display'=>'default','description'=>$name==='price_cards'?'Добавляйте и редактируйте абонементы. Порядок строк определяет порядок карточек на сайте.':(str_contains($name,'heading')?'Красную часть заголовка можно выделить тегом <span>.':'')]);
 if($type==='migx'){
  $fields=[];$columns=[];foreach(['title'=>'Название','age'=>'Возраст','amount'=>'Стоимость','currency'=>'Валюта','quantity'=>'Количество занятий','badge'=>'Подпись над карточкой'] as $field=>$label){$fields[]=['field'=>$field,'caption'=>$label,'inputTVtype'=>'text'];$columns[]=['header'=>$label,'dataIndex'=>$field,'width'=>$field==='quantity'?190:120];}
  $tv->set('input_properties',['formtabs'=>json_encode([['caption'=>'Абонемент','fields'=>$fields]],JSON_UNESCAPED_UNICODE),'columns'=>json_encode($columns,JSON_UNESCAPED_UNICODE),'btntext'=>'Добавить абонемент']);
 }
 if(!$tv->save())throw new RuntimeException('TV save failed');$id=(int)$tv->get('id');$registry['fields'][$name]=$id;
 $criteria=['templateid'=>23,'tmplvarid'=>$id];if(!$modx->getObject(modTemplateVarTemplate::class,$criteria)){$link=$modx->newObject(modTemplateVarTemplate::class);$link->fromArray($criteria,'',true,true);if(!$link->save())throw new RuntimeException('TV assignment failed');}
 foreach($modx->getCollection(modResource::class,['template'=>23,'deleted'=>0]) as $r){if(!$modx->getObject(modTemplateVarResource::class,['contentid'=>$r->get('id'),'tmplvarid'=>$id]))if(!$r->setTVValue($name,$seed))throw new RuntimeException('Seed failed');}
}
foreach(['priceCard','groupPrices','personalPrices'] as $name){$c=$modx->getObject(modChunk::class,['name'=>$name])?:$modx->newObject(modChunk::class);$c->fromArray(['name'=>$name,'category'=>$common,'snippet'=>file_get_contents($root.'cms/chunks/'.$name.'.html')]);if(!$c->save())throw new RuntimeException('Chunk save failed');$registry['chunks'][]=$name;}
$registry['chunks']=array_values(array_unique($registry['chunks']));file_put_contents($registryFile,json_encode($registry,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
$t=$modx->getObject(modTemplate::class,23);$t->set('content',file_get_contents($root.'cms/templates/structured/23.html'));if(!$t->save())throw new RuntimeException('Template save failed');
$modx->cacheManager->refresh();echo "Price fields and MIGX configured.\n";
