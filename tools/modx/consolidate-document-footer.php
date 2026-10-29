<?php
/** Put document opening/closing concerns in the shared layout chunks. */
if(PHP_SAPI!=='cli'||!in_array('--apply',$argv,true))exit("Usage: php tools/modx/consolidate-document-footer.php --apply\n");
$root=dirname(__DIR__,2).'/';define('MODX_API_MODE',true);require $root.'public/index.php';$modx->initialize('mgr');require_once $root.'cms/elements/TemplateRegistry.php';
use MODX\Revolution\{modTemplate,modChunk};
$file=$root.'cms/template-registry.json';$registry=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);if(!empty($registry['document_footer']))throw new RuntimeException('Already applied');
$templates=$modx->getCollection(modTemplate::class,Zubr\Site\TemplateRegistry::criteria());$header=$modx->getObject(modChunk::class,['name'=>'header']);$footer=$modx->getObject(modChunk::class,['name'=>'footer']);$backup=['header'=>$header->toArray(),'footer'=>$footer->toArray(),'templates'=>[]];$skip=null;
foreach($templates as $t){$backup['templates'][]=$t->toArray();if(!preg_match('~<a class="skip-link" href="#main">.*?</a>~s',$t->get('content'),$m))throw new RuntimeException('Skip link missing');if($skip!==null&&$skip!==$m[0])throw new RuntimeException('Skip links differ');$skip=$m[0];}
file_put_contents($root.'cms/backups/before-document-footer-'.date('Ymd-His').'.json',json_encode($backup,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
$header->set('snippet',$skip."\n".$header->get('snippet'));if(!$header->save())throw new RuntimeException('Header save failed');
$footer->set('snippet',rtrim($footer->get('snippet'))."\n".'[[$mobileMenu]]'."\n\t</body>\n</html>\n");if(!$footer->save())throw new RuntimeException('Footer save failed');
foreach($templates as $t){$html=$t->get('content');$html=preg_replace('~^[\t ]*<a class="skip-link" href="#main">.*?</a>\s*~ms','',$html);$html=preg_replace('~^[\t ]*\[\[\$mobileMenu\]\]\s*~m','',$html);$gallery=str_contains($html,'[[$galleryDialog]]');$html=preg_replace('~^[\t ]*\[\[\$galleryDialog\]\]\s*~m','',$html);$html=preg_replace('~\s*</body>\s*</html>\s*\z~s',"\n",$html,1,$count);if($count!==1)throw new RuntimeException('Closing tags missing');if($gallery)$html=str_replace('[[$footer]]','[[$galleryDialog]]'."\n\t\t".'[[$footer]]',$html);$t->set('content',$html);if(!$t->save())throw new RuntimeException('Template save failed');}
$registry['document_footer']=true;file_put_contents($file,json_encode($registry,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));$modx->cacheManager->refresh();echo "Moved skip link to header, mobileMenu and closing tags to footer on 20 templates.\n";
