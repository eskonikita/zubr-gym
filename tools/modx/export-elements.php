<?php
/** Export current editable CMS element source for review and version control; never reimport implicitly. */
if(PHP_SAPI!=='cli')exit(1);$root=dirname(__DIR__,2).'/';define('MODX_API_MODE',true);require $root.'public/index.php';$modx->initialize('mgr');require_once $root.'cms/elements/TemplateRegistry.php';
use MODX\Revolution\{modTemplate,modChunk,modTemplateVar};
$file=$root.'cms/template-registry.json';$registry=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
foreach($modx->getCollection(modTemplate::class,Zubr\Site\TemplateRegistry::criteria()) as $t)file_put_contents($root.'cms/templates/structured/'.$t->get('id').'.html',$t->get('content'));
foreach($registry['chunks'] as $name){$c=$modx->getObject(modChunk::class,['name'=>$name]);if($c)file_put_contents($root.'cms/chunks/'.$name.'.html',$c->get('snippet'));}
$definitions=[];foreach($modx->getCollection(modTemplateVar::class) as $tv)$definitions[]=$tv->toArray();file_put_contents($root.'cms/tv-definitions.json',json_encode($definitions,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
foreach(['seo-setup.json','clientconfig-setup.json'] as $name){$path=$root.'cms/'.$name;$data=json_decode(file_get_contents($path),true);$key=$name==='seo-setup.json'?'html_templates':'templates';$data[$key]=count($registry['templates']);file_put_contents($path,json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));}
echo "Exported current templates, chunks and TV definitions.\n";
