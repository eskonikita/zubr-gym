<?php
/** Publish organization-specific website documents and normalize internal content URLs. */
if(PHP_SAPI!=='cli'||!in_array('--apply',$argv,true))exit("Usage: php tools/modx/finalize-documents-links.php --apply\n");
$root=dirname(__DIR__,2).'/';define('MODX_API_MODE',true);require $root.'public/index.php';$modx->initialize('mgr');
use MODX\Revolution\{modResource,modTemplate,modChunk,modTemplateVarResource};
foreach([8=>['privacy-policy.html','Политика обработки персональных данных на сайте [[++zubr_contact_domain:htmlent]]. Редакция от 29 октября 2026 г.'],9=>['terms-of-use.html','Правила использования сайта [[++zubr_contact_domain:htmlent]]. Редакция от 29 октября 2026 г.']] as $id=>[$file,$intro]){$r=$modx->getObject(modResource::class,$id);$content=file_get_contents($root.'cms/documents/'.$file);if(!$content)throw new RuntimeException('Legal source missing');$r->set('content',$content);$r->set('description',$id===8?'Политика конфиденциальности ZUBR GYM: обработка персональных данных, контакты оператора, права посетителей и сторонние сервисы.':'Условия использования информационного сайта ZUBR GYM, обращения в клуб, материалы сайта и контакты организации.');if(!$r->save())throw new RuntimeException('Document save failed');$r->setTVValue('page_intro',$intro);$r->setTVValue('seo_description',$r->get('description'));}
foreach([8,9] as $id)$modx->getObject(modResource::class,$id)->setTVValue('seo_index','1');
$routes=['/'=>1];foreach($modx->getCollection(modResource::class,['context_key'=>'web','deleted'=>0]) as $r)$routes['/'.ltrim($r->get('uri'),'/')]=(int)$r->get('id');
function links($text,$routes){return preg_replace_callback('~\bhref=(["\x27])(/(?!/)[^"\x27]*)\1~',function($m)use($routes){$parts=parse_url(html_entity_decode($m[2],ENT_QUOTES,'UTF-8'));$path=$parts['path']??'/';if(!isset($routes[$path]))return $m[0];$tail=isset($parts['query'])?'?'.htmlspecialchars($parts['query'],ENT_QUOTES,'UTF-8'):'';$tail.=isset($parts['fragment'])?'#'.$parts['fragment']:'';return 'href='.$m[1].'[[~'.$routes[$path].'? &scheme=`abs`]]'.$tail.$m[1];},$text);}
function walk($value,$routes){if(is_string($value))return links($value,$routes);if(is_array($value))foreach($value as $key=>$item)$value[$key]=walk($item,$routes);return $value;}
$counts=['templates'=>0,'chunks'=>0,'resources'=>0,'tv_values'=>0];
foreach([modTemplate::class=>['content','templates'],modChunk::class=>['snippet','chunks'],modResource::class=>['content','resources']] as $class=>[$field,$key])foreach($modx->getCollection($class) as $o){$before=(string)$o->get($field);$after=links($before,$routes);if($after!==$before){$o->set($field,$after);if(!$o->save())throw new RuntimeException('Link conversion failed');$counts[$key]++;}}
foreach($modx->getCollection(modTemplateVarResource::class) as $o){$before=(string)$o->get('value');$decoded=json_decode($before,true);$after=is_array($decoded)?json_encode(walk($decoded,$routes),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):links($before,$routes);if($after!==$before&&((is_array($decoded)&&walk($decoded,$routes)!==$decoded)||!is_array($decoded))){$o->set('value',$after);if(!$o->save())throw new RuntimeException('TV link conversion failed');$counts['tv_values']++;}}
// Avoid third-party map requests until the visitor explicitly enables the widget.
$t=$modx->getObject(modTemplate::class,21);$s=$t->get('content');if(!str_contains($s,'data-map-consent')){$s=str_replace('<iframe src=','<iframe hidden data-map-src=',$s);$panel=<<<HTML

							<div class="contacts-map-consent" data-map-consent>
								<svg aria-hidden="true"><use href="#pin"/></svg>
								<p>[[++zubr_contact_address:htmlent]]</p>
								<p>При включении карты браузер обратится к сервису Яндекса. <a href="https://yandex.ru/legal/confidential/" target="_blank" rel="noopener noreferrer">Условия обработки данных</a>.</p>
								<button class="button button-primary" type="button" data-load-map>Показать карту</button>
							</div>
HTML;
	$s=str_replace('<div class="contacts-map__frame">','<div class="contacts-map__frame">'.$panel,$s);$t->set('content',$s);if(!$t->save())throw new RuntimeException('Map gating save failed');}
$modx->cacheManager->refresh();echo 'Documents published; native internal URL conversions: '.json_encode($counts)."\n";
