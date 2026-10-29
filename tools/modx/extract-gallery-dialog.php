<?php
/** Keep the photo lightbox only on templates containing photo-viewer triggers. */
if(PHP_SAPI!=='cli'||!in_array('--apply',$argv,true))exit("Usage: php tools/modx/extract-gallery-dialog.php --apply\n");
$root=dirname(__DIR__,2).'/';define('MODX_API_MODE',true);require $root.'public/index.php';$modx->initialize('mgr');require_once $root.'cms/elements/TemplateRegistry.php';
use MODX\Revolution\{modTemplate,modChunk,modCategory};
$registryFile=$root.'cms/template-registry.json';$registry=json_decode(file_get_contents($registryFile),true,512,JSON_THROW_ON_ERROR);
if(in_array('galleryDialog',$registry['chunks'],true))throw new RuntimeException('Gallery extraction already applied');
$templates=$modx->getCollection(modTemplate::class,Zubr\Site\TemplateRegistry::criteria());$backup=[];$dialog=null;$uses=[];
foreach($templates as $t){$backup[]=$t->toArray();if(!preg_match('~<dialog class="gallery-dialog".*?</dialog>~s',$t->get('content'),$match))throw new RuntimeException('Dialog missing');if($dialog===null)$dialog=$match[0];}
file_put_contents($root.'cms/backups/before-gallery-dialog-'.date('Ymd-His').'.json',json_encode($backup,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
$category=$modx->getObject(modCategory::class,['category'=>'Общие']);$chunk=$modx->newObject(modChunk::class);$chunk->fromArray(['name'=>'galleryDialog','category'=>$category->get('id'),'description'=>'Просмотр фото. Подключать только при наличии data-gallery. JS подставляет фото и подпись по нажатой кнопке.','snippet'=>$dialog."\n",'static'=>0]);if(!$chunk->save())throw new RuntimeException('Chunk failed');
foreach($templates as $t){$source=$t->get('content');$without=preg_replace('~<dialog class="gallery-dialog".*?</dialog>~s','',$source);$needed=(bool)preg_match('~\bdata-gallery\s*=~',$without);$new=preg_replace('~^[\t ]*<dialog class="gallery-dialog".*?</dialog>\s*~ms',$needed?"\t\t[[$".'galleryDialog'."]]\n":'',$source);$t->set('content',$new);if(!$t->save())throw new RuntimeException('Template failed');if($needed)$uses[]=(int)$t->id;}
// Bump the shared script URL in both CMS and the independent static preview.
$head=$modx->getObject(modChunk::class,['name'=>'head']);$head->set('snippet',str_replace('/zubr/home.js?v=blog-navigation-4','/zubr/home.js?v=optional-gallery-5',$head->get('snippet')));$head->save();
$manifest=json_decode(file_get_contents($root.'cms/static-pages.json'),true);$sources=array_filter(array_column($manifest['pages'],'source'));$sources[]='public/zubr/home.html';foreach(array_unique($sources) as $source){$file=$root.$source;if(is_file($file)){$html=file_get_contents($file);$new=str_replace('/zubr/home.js?v=blog-navigation-4','/zubr/home.js?v=optional-gallery-5',$html);if($new!==$html)file_put_contents($file,$new);}}
$registry['chunks'][]='galleryDialog';$registry['gallery_templates']=$uses;file_put_contents($registryFile,json_encode($registry,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));$modx->cacheManager->refresh();echo json_encode(['gallery_templates'=>$uses,'removed_from_templates'=>count($templates)-count($uses)],JSON_PRETTY_PRINT).PHP_EOL;
