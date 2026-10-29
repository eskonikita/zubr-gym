<?php
/** Editable FAQ rows, retaining answer markup and shared contact tags. */
if(PHP_SAPI!=='cli'||!in_array('--apply',$argv,true))exit("Usage: php tools/modx/configure-faq-fields.php --apply\n");
$root=dirname(__DIR__,2).'/';define('MODX_API_MODE',true);require $root.'public/index.php';$modx->initialize('mgr');
use MODX\Revolution\{modTemplate,modResource,modTemplateVar,modTemplateVarTemplate,modTemplateVarResource,modCategory,modChunk};
$template=$modx->getObject(modTemplate::class,22);$html=$template->get('content');$seed=[];
if(!str_contains($html,'[[$faqSection]]')){
	$doc=new DOMDocument();libxml_use_internal_errors(true);$doc->loadHTML('<?xml encoding="UTF-8">'.$html);libxml_clear_errors();$xp=new DOMXPath($doc);
	foreach($xp->query('//div[@class="faq-list"]/details') as $row){
		$summary=$xp->query('./summary',$row)->item(0);foreach(iterator_to_array($summary->getElementsByTagName('svg')) as $svg)$svg->parentNode->removeChild($svg);
		$answer=$xp->query('./div[@class="faq-answer"]',$row)->item(0);$content='';foreach($answer->childNodes as $node)$content.=$doc->saveHTML($node);
		// libxml URI serialization percent-encodes MODX brackets in href attributes.
		$content=preg_replace_callback('~%5B%5B.*?%5D%5D~i',fn($m)=>rawurldecode($m[0]),$content);
		$content=preg_replace_callback('~href="(/[^"#?]*)"~',function($match)use($modx){$r=$modx->getObject(modResource::class,['uri'=>ltrim($match[1],'/'),'context_key'=>'web']);return $r?'href="[[~'.$r->get('id').'? &scheme=`abs`]]"':$match[0];},$content);
		$seed[]=['MIGX_id'=>count($seed)+1,'question'=>trim($summary->textContent),'answer'=>trim($content)];
	}
	if(count($seed)!==9)throw new RuntimeException('Unexpected original FAQ count');
}
$category=$modx->getObject(modCategory::class,['category'=>'Вопросы и ответы'])?:$modx->newObject(modCategory::class);$category->set('category','Вопросы и ответы');if(!$category->save())throw new RuntimeException('Category save failed');
$fields=[
	['field'=>'question','caption'=>'Вопрос','inputTVtype'=>'text','configs'=>json_encode(['allowBlank'=>false])],
	['field'=>'answer','caption'=>'Ответ (HTML)','inputTVtype'=>'textarea','description'=>'Абзацы оформляйте тегом <p>. Допускаются ссылки, HTML и теги MODX/ClientConfig. Внутренние ссылки задавайте через ID ресурса.','configs'=>json_encode(['allowBlank'=>false])],
];
$tv=$modx->getObject(modTemplateVar::class,['name'=>'faq_items'])?:$modx->newObject(modTemplateVar::class);
$tv->fromArray(['name'=>'faq_items','caption'=>'Вопросы и ответы','description'=>'Добавляйте и редактируйте вопросы. Порядок строк задаёт порядок на странице.','category'=>$category->get('id'),'type'=>'migx','display'=>'default','default_text'=>'','input_properties'=>['formtabs'=>json_encode([['caption'=>'Вопрос и ответ','fields'=>$fields]],JSON_UNESCAPED_UNICODE),'columns'=>json_encode([['header'=>'Вопрос','dataIndex'=>'question','width'=>400,'renderer'=>'Ext.util.Format.htmlEncode'],['header'=>'Ответ','dataIndex'=>'answer','width'=>650,'renderer'=>'Ext.util.Format.htmlEncode']],JSON_UNESCAPED_UNICODE),'btntext'=>'Добавить вопрос']]);
if(!$tv->save())throw new RuntimeException('TV save failed');$id=(int)$tv->get('id');$criteria=['tmplvarid'=>$id,'templateid'=>22];
if(!$modx->getObject(modTemplateVarTemplate::class,$criteria)){$link=$modx->newObject(modTemplateVarTemplate::class);$link->fromArray($criteria,'',true,true);if(!$link->save())throw new RuntimeException('TV assignment failed');}
foreach($modx->getCollection(modResource::class,['template'=>22,'deleted'=>0,'context_key'=>'web']) as $r){if(!$modx->getObject(modTemplateVarResource::class,['tmplvarid'=>$id,'contentid'=>$r->get('id')])){if(!$seed)throw new RuntimeException('Original values missing');if(!$r->setTVValue('faq_items',json_encode($seed,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)))throw new RuntimeException('FAQ seed failed');}}
$category=$modx->getObject(modCategory::class,['category'=>'Общие']);$names=['faqSection','faqItem'];foreach($names as $name){$c=$modx->getObject(modChunk::class,['name'=>$name])?:$modx->newObject(modChunk::class);$c->fromArray(['name'=>$name,'category'=>$category->get('id'),'snippet'=>file_get_contents($root.'cms/chunks/'.$name.'.html')]);if(!$c->save())throw new RuntimeException('Chunk save failed');}
if(!str_contains($html,'[[$faqSection]]')){$html=preg_replace('~<section class="section faq-section".*?</section>~s','[[$faqSection]]',$html,1);$template->set('content',$html);if(!$template->save())throw new RuntimeException('Template save failed');}
$file=$root.'cms/template-registry.json';$reg=json_decode(file_get_contents($file),true);$reg['fields']['faq_items']=$id;$reg['chunks']=array_values(array_unique(array_merge($reg['chunks'],$names)));file_put_contents($file,json_encode($reg,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));$modx->cacheManager->refresh();echo "FAQ MIGX editor applied.\n";
