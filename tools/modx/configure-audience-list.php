<?php
/** Child-owned audience cards and parent-owned training formats. */
if(PHP_SAPI!=='cli'||!in_array('--apply',$argv,true))exit("Usage: php tools/modx/configure-audience-list.php --apply\n");
$root=dirname(__DIR__,2).'/';define('MODX_API_MODE',true);require $root.'public/index.php';$modx->initialize('mgr');
use MODX\Revolution\{modResource,modTemplate,modTemplateVar,modTemplateVarTemplate,modTemplateVarResource,modCategory,modChunk};
$template=$modx->getObject(modTemplate::class,6);$html=$template->get('content');$seed=[];$cards=[];
if(!str_contains($html,'[[$audienceLinks]]')){
	$doc=new DOMDocument();libxml_use_internal_errors(true);$doc->loadHTML('<?xml encoding="UTF-8">'.$html);libxml_clear_errors();$xp=new DOMXPath($doc);
	foreach($xp->query('//a[@class="audience-link"]') as $card){
		$uri=ltrim(parse_url($card->getAttribute('href'),PHP_URL_PATH),'/');$r=$modx->getObject(modResource::class,['uri'=>$uri,'parent'=>6,'context_key'=>'web']);if(!$r)throw new RuntimeException('Unknown audience child');
		$cards[$r->get('id')]=[['MIGX_id'=>1,'title'=>trim($xp->query('.//h3',$card)->item(0)->textContent),'description'=>trim($xp->query('.//p',$card)->item(0)->textContent)]];
	}
	if(count($cards)!==4)throw new RuntimeException('Unexpected original card count');
	$heading=$xp->query('//*[@id="audience-training-title"]')->item(0);$seed['audience_training_heading']='';foreach($heading->childNodes as $node)$seed['audience_training_heading'].=$doc->saveHTML($node);
	$seed['audience_training']=[];foreach($xp->query('//div[@class="audience-training__grid"]/article') as $row){$link=$xp->query('.//a',$row)->item(0);$uri=ltrim(parse_url($link->getAttribute('href'),PHP_URL_PATH),'/');$r=$modx->getObject(modResource::class,['uri'=>$uri,'context_key'=>'web']);if(!$r)throw new RuntimeException('Unknown training link');$seed['audience_training'][]=['MIGX_id'=>count($seed['audience_training'])+1,'title'=>trim($xp->query('.//h3',$row)->item(0)->textContent),'description'=>trim($xp->query('.//p',$row)->item(0)->textContent),'link_label'=>trim($link->textContent),'resource'=>(int)$r->get('id')];}
}
function category($modx,$name){$c=$modx->getObject(modCategory::class,['category'=>$name])?:$modx->newObject(modCategory::class);$c->set('category',$name);if(!$c->save())throw new RuntimeException('Category save failed');return (int)$c->get('id');}
function field($name,$caption,$type='text'){return ['field'=>$name,'caption'=>$caption,'inputTVtype'=>$type];}
$defs=[
	'audience_card'=>['Карточка в разделе «Для кого»','Карточка раздела',16,[field('title','Название карточки'),field('description','Описание карточки','textarea')]],
	'audience_training'=>['Форматы занятий','Для кого — раздел',6,[field('title','Название'),field('description','Описание','textarea'),field('link_label','Текст ссылки'),field('resource','Страница','listbox')+['inputOptionValues'=>"@SELECT pagetitle,id FROM [[+PREFIX]]site_content WHERE published=1 AND deleted=0 AND context_key='web' AND id NOT IN(2,38,39,40) ORDER BY pagetitle",'configs'=>json_encode(['allowBlank'=>false,'forceSelection'=>true])]]],
	'audience_training_heading'=>['Заголовок блока занятий','Для кого — раздел',6,null],
];
$file=$root.'cms/template-registry.json';$reg=json_decode(file_get_contents($file),true);
foreach($defs as $name=>[$caption,$cat,$templateId,$fields]){
	$tv=$modx->getObject(modTemplateVar::class,['name'=>$name])?:$modx->newObject(modTemplateVar::class);$data=['name'=>$name,'caption'=>$caption,'category'=>category($modx,$cat),'type'=>$fields?'migx':'text','display'=>'default','default_text'=>$name==='audience_card'?json_encode([['MIGX_id'=>1,'title'=>'','description'=>'']]):''];
	if($fields){$columns=[];foreach($fields as $f)$columns[]=['header'=>$f['caption'],'dataIndex'=>$f['field'],'width'=>280,'renderer'=>'Ext.util.Format.htmlEncode'];$data['input_properties']=['formtabs'=>json_encode([['caption'=>$caption,'fields'=>$fields]],JSON_UNESCAPED_UNICODE),'columns'=>json_encode($columns,JSON_UNESCAPED_UNICODE),'btntext'=>$name==='audience_card'?'Добавить настройки карточки':'Добавить формат'];}
	$data['description']=$name==='audience_card'?'Одна запись. Пустое название/описание используют заголовок/описание ресурса.':($name==='audience_training_heading'?'Допускается span для цветового акцента.':'Порядок строк задаёт порядок карточек. Нумерация автоматическая.');$tv->fromArray($data);if(!$tv->save())throw new RuntimeException('TV save failed');$id=(int)$tv->get('id');$reg['fields'][$name]=$id;
	$criteria=['tmplvarid'=>$id,'templateid'=>$templateId];if(!$modx->getObject(modTemplateVarTemplate::class,$criteria)){$link=$modx->newObject(modTemplateVarTemplate::class);$link->fromArray($criteria,'',true,true);if(!$link->save())throw new RuntimeException('TV assignment failed');}
	$values=$name==='audience_card'?$cards:[6=>$seed[$name]??null];foreach($values as $resourceId=>$value){if($value===null)continue;if(!$modx->getObject(modTemplateVarResource::class,['tmplvarid'=>$id,'contentid'=>$resourceId])){$r=$modx->getObject(modResource::class,$resourceId);if(!$r->setTVValue($name,is_array($value)?json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):$value))throw new RuntimeException('Seed failed');}}
}
$names=['audienceLinks','audienceCardResource','audienceCard','audienceTraining','audienceTrainingItem'];foreach($names as $name){$c=$modx->getObject(modChunk::class,['name'=>$name])?:$modx->newObject(modChunk::class);$c->fromArray(['name'=>$name,'category'=>category($modx,'Общие'),'snippet'=>file_get_contents($root.'cms/chunks/'.$name.'.html')]);if(!$c->save())throw new RuntimeException('Chunk save failed');}
if(!str_contains($html,'[[$audienceLinks]]')){foreach(array_keys($cards) as $index=>$id){$r=$modx->getObject(modResource::class,$id);$r->set('menuindex',$index);$r->save();}$html=preg_replace('~<section class="section audience-formats".*?</section>~s','[[$audienceLinks]]',$html,1);$html=preg_replace('~<section class="section audience-training".*?</section>~s','[[$audienceTraining]]',$html,1);$template->set('content',$html);if(!$template->save())throw new RuntimeException('Template save failed');}
$reg['chunks']=array_values(array_unique(array_merge($reg['chunks'],$names)));file_put_contents($file,json_encode($reg,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));$modx->cacheManager->refresh();echo "Audience child cards and parent training formats applied.\n";
