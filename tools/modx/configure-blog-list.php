<?php
/** Native server-side blog pagination and shared article cards. */
if(PHP_SAPI!=='cli'||!in_array('--apply',$argv,true))exit("Usage: php tools/modx/configure-blog-list.php --apply\n");
$root=dirname(__DIR__,2).'/';define('MODX_API_MODE',true);require $root.'public/index.php';$modx->initialize('mgr');
use MODX\Revolution\{modChunk,modCategory,modTemplate,modSystemSetting};
$s=$modx->getObject(modSystemSetting::class,'zubr_blog_page_size');if(!$s){$s=$modx->newObject(modSystemSetting::class);$s->fromArray(['key'=>'zubr_blog_page_size','value'=>'6','xtype'=>'numberfield','namespace'=>'core','area'=>'site'],'',true,true);if(!$s->save())throw new RuntimeException('Setting failed');}
$names=['articleCard','blogList','blogPage','blogPageActive','blogPagePrev','blogPageNext','blogPageSkip','blogPageWrapper','blogRowsWrapper'];
$category=$modx->getObject(modCategory::class,['category'=>'Общие'])->get('id');
foreach($names as $name){$c=$modx->getObject(modChunk::class,['name'=>$name])?:$modx->newObject(modChunk::class);$c->fromArray(['name'=>$name,'category'=>$category,'snippet'=>file_get_contents($root.'cms/chunks/'.$name.'.html')]);if(!$c->save())throw new RuntimeException('Chunk save failed');}
$t=$modx->getObject(modTemplate::class,20);$t->set('content',file_get_contents($root.'cms/templates/structured/20.html'));if(!$t->save())throw new RuntimeException('Template save failed');
$file=$root.'cms/template-registry.json';$r=json_decode(file_get_contents($file),true);$r['chunks']=array_values(array_unique(array_merge($r['chunks'],$names)));file_put_contents($file,json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));$modx->cacheManager->refresh();echo "Native pdoPage blog applied.\n";
