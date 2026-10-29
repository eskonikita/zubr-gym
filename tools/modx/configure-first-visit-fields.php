<?php
/** Editable first visit copy and MIGX steps, preserving the current page's design. */
if(PHP_SAPI!=='cli'||!in_array('--apply',$argv,true))exit("Usage: php tools/modx/configure-first-visit-fields.php --apply\n");
$root=dirname(__DIR__,2).'/';define('MODX_API_MODE',true);require $root.'public/index.php';$modx->initialize('mgr');
use MODX\Revolution\{modTemplate,modResource,modTemplateVar,modTemplateVarTemplate,modTemplateVarResource,modCategory,modChunk};
$template=$modx->getObject(modTemplate::class,11);$html=$template->get('content');$seed=[];
if(!str_contains($html,'[[$firstVisitSection]]')){
 $doc=new DOMDocument();libxml_use_internal_errors(true);$doc->loadHTML('<?xml encoding="UTF-8">'.$html);libxml_clear_errors();$xp=new DOMXPath($doc);
 $text=fn($path)=>trim($xp->query($path)->item(0)?->textContent??'');
 $h=$xp->query('//*[@id="visit-title"]')->item(0);$heading='';foreach($h->childNodes as $node)$heading.=$doc->saveHTML($node);
 $seed=['visit_heading'=>$heading,'visit_description'=>$text('//*[@class="visit-intro"]/p[@class="visit-desktop-copy"]'),'visit_mobile_description'=>$text('//*[@class="visit-intro"]/p[@class="visit-mobile-copy"]'),'visit_phone_label'=>$text('//div[@class="visit-actions"]/a[1]'),'visit_instagram_label'=>$text('//div[@class="visit-actions"]/a[2]')];
 $steps=[];foreach($xp->query('//ol[@class="visit-steps"]/li') as $li){$value=fn($path)=>trim($xp->query($path,$li)->item(0)?->textContent??'');$steps[]=['MIGX_id'=>count($steps)+1,'title'=>$value('.//h3/span[@class="visit-desktop-copy"]'),'mobile_title'=>$value('.//h3/span[@class="visit-mobile-copy"]'),'description'=>$value('.//p')];}
 if(!$steps)throw new RuntimeException('No original steps found');$seed['visit_steps']=json_encode($steps,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
$category=$modx->getObject(modCategory::class,['category'=>'Первый визит']);if(!$category){$category=$modx->newObject(modCategory::class);$category->set('category','Первый визит');if(!$category->save())throw new RuntimeException('Category save failed');}
$definitions=[
 'visit_heading'=>['Заголовок блока','text','Красную часть выделяйте тегом <span>.'],
 'visit_description'=>['Описание — компьютер','textarea','Основное описание рядом с шагами.'],
 'visit_mobile_description'=>['Описание — мобильный','textarea','Если оставить пустым, используется основное описание.'],
 'visit_phone_label'=>['Текст кнопки звонка','text','Номер телефона задаётся в ClientConfig.'],
 'visit_instagram_label'=>['Текст кнопки Instagram','text','Адрес Instagram задаётся в ClientConfig.'],
 'visit_steps'=>['Шаги первого визита','migx','Порядок строк — порядок шагов. Нумерация автоматическая. Если мобильный заголовок пустой, берётся основной.'],
];
$file=$root.'cms/template-registry.json';$registry=json_decode(file_get_contents($file),true);
foreach($definitions as $rank=>$definition){[$caption,$type,$description]=$definition;$tv=$modx->getObject(modTemplateVar::class,['name'=>$rank])?:$modx->newObject(modTemplateVar::class);$tv->fromArray(['name'=>$rank,'caption'=>$caption,'type'=>$type,'description'=>$description,'category'=>$category->get('id'),'default_text'=>'','display'=>'default','rank'=>array_search($rank,array_keys($definitions))]);
 if($type==='migx')$tv->set('input_properties',['formtabs'=>json_encode([['caption'=>'Шаг','fields'=>[
  ['field'=>'title','caption'=>'Заголовок','inputTVtype'=>'text','configs'=>json_encode(['allowBlank'=>false])],
  ['field'=>'mobile_title','caption'=>'Заголовок на мобильном','inputTVtype'=>'text','description'=>'Можно оставить пустым — будет использован основной заголовок.'],
  ['field'=>'description','caption'=>'Описание','inputTVtype'=>'textarea'],
 ]]],JSON_UNESCAPED_UNICODE),'columns'=>json_encode([
  ['header'=>'Заголовок','dataIndex'=>'title','width'=>240],['header'=>'На мобильном','dataIndex'=>'mobile_title','width'=>260],['header'=>'Описание','dataIndex'=>'description','width'=>400],
 ],JSON_UNESCAPED_UNICODE),'btntext'=>'Добавить шаг']);
 if(!$tv->save())throw new RuntimeException('TV save failed');$id=(int)$tv->get('id');$registry['fields'][$rank]=$id;$criteria=['templateid'=>11,'tmplvarid'=>$id];if(!$modx->getObject(modTemplateVarTemplate::class,$criteria)){$link=$modx->newObject(modTemplateVarTemplate::class);$link->fromArray($criteria,'',true,true);if(!$link->save())throw new RuntimeException('TV assignment failed');}
 foreach($modx->getCollection(modResource::class,['template'=>11,'deleted'=>0]) as $r)if(!$modx->getObject(modTemplateVarResource::class,['contentid'=>$r->get('id'),'tmplvarid'=>$id])){if(!array_key_exists($rank,$seed))throw new RuntimeException('No original value for '.$rank);if(!$r->setTVValue($rank,$seed[$rank]))throw new RuntimeException('TV value save failed');}
}
$common=$modx->getObject(modCategory::class,['category'=>'Общие'])->get('id');foreach(['firstVisitSection','firstVisitStep'] as $name){$c=$modx->getObject(modChunk::class,['name'=>$name])?:$modx->newObject(modChunk::class);$c->fromArray(['name'=>$name,'category'=>$common,'snippet'=>file_get_contents($root.'cms/chunks/'.$name.'.html')]);if(!$c->save())throw new RuntimeException('Chunk save failed');}
if(!str_contains($html,'[[$firstVisitSection]]')){$html=preg_replace('~<section class="section first-visit".*?</section>~s','[[$firstVisitSection]]',$html,-1,$count);if($count!==1)throw new RuntimeException('Unexpected section');$template->set('content',$html);if(!$template->save())throw new RuntimeException('Template save failed');}
$registry['chunks']=array_values(array_unique(array_merge($registry['chunks'],['firstVisitSection','firstVisitStep'])));file_put_contents($file,json_encode($registry,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));$modx->cacheManager->refresh();echo "First visit fields, MIGX steps and section chunks applied.\n";
