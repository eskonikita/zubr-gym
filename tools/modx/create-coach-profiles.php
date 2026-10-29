<?php
/** Replace the three coach redirects with actual resources using the shared trainer template. */
if(PHP_SAPI!=='cli'||!in_array('--apply',$argv,true))exit("Usage: php tools/modx/create-coach-profiles.php --apply\n");
$root=dirname(__DIR__,2).'/';if(is_file($root.'cms/coach-profiles.json'))throw new RuntimeException('Profiles already created; edit the existing resources and TVs instead of reseeding.');define('MODX_API_MODE',true);require $root.'public/index.php';$modx->initialize('mgr');
use MODX\Revolution\{modResource,modDocument,modTemplate,modTemplateVar,modTemplateVarResource,modCategory};
$coaches=[
    ['id'=>14,'slug'=>'andrey-gunda','first'=>'Андрей','last'=>'Гунда','with'=>'Андреем Гундой','years'=>'15+'],
    ['id'=>17,'slug'=>'pavel-apanasenok','first'=>'Павел','last'=>'Апанасенок','with'=>'Павлом Апанасенком','years'=>'8+'],
    ['id'=>16,'slug'=>'artem-dzhabaryan','first'=>'Артём','last'=>'Джабарян','with'=>'Артёмом Джабаряном','years'=>'10+'],
];
$backup=[];foreach([modResource::class,modTemplate::class,modTemplateVarResource::class] as $class)foreach($modx->getCollection($class) as $o)$backup[$class][]=$o->toArray();file_put_contents($root.'cms/backups/before-coach-pages-'.date('Ymd-His').'.json',json_encode($backup,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
$source=[];
foreach($coaches as $index=>$c){
    $r=$modx->getObject(modResource::class,$c['id']);if(!$r||$r->get('parent')!=10)throw new RuntimeException('Unexpected coach resource');
    $name=$c['first'].' '.$c['last'];$title=$name.' — тренер ZUBR GYM в Гродно';
    $description=$name.' — тренер ZUBR GYM в Гродно. Тренерский стаж — '.$c['years'].' лет. Информация о занятиях, подборе группы и первом посещении клуба.';
    $photo='/zubr/images/coaches/'.$c['slug'].'-cutout.webp';if(!is_file(MODX_BASE_PATH.ltrim($photo,'/')))throw new RuntimeException('Photo missing');
    $summary='В команде ZUBR GYM — '.$c['years'].' лет тренерского опыта. Возрастные группы, время занятий и возможность персональной тренировки уточняйте в клубе.';
    $qualifications=[['label'=>'Тренерский опыт','value'=>$c['years'].' лет'],['label'=>'Место тренировок','value'=>'ZUBR GYM, Гродно'],['label'=>'Первое посещение','value'=>'Знакомство с залом и подходящей группой']];
    $content=<<<HTML
<section class="section coach-details" aria-labelledby="qualification-title">
	<div class="container coach-details__layout">
		<div>
			<h2 id="qualification-title">Тренер и <span>занятия в клубе</span></h2>
			<p class="coach-details__summary">[[*coach_qualification_intro]]</p>
		</div>
		<dl class="coach-qualifications">
			[[RepeatItems? &tv=`coach_qualifications` &tpl=`coachQualification`]]
		</dl>
	</div>
</section>
<section class="section coach-training" aria-labelledby="training-title">
	<div class="container">
		<h2 id="training-title">Как начать <span>тренироваться</span></h2>
		<div class="coach-training__grid">
			<div>
				<h3>Выберите подходящую группу</h3>
				<p>В ZUBR GYM занимаются дети, подростки и взрослые. При обращении назовите возраст, расскажите об опыте и сообщите, что хотите заниматься с {$c['with']}. В клубе подскажут доступные группы и время занятий.</p>
			</div>
			<div>
				<h3>Обсудите свою задачу</h3>
				<p>Если вы начинаете с нуля, возвращаетесь после перерыва или хотите уделить внимание отдельным элементам техники, расскажите об этом тренеру. Возможность персонального формата, программу и время согласуйте заранее.</p>
			</div>
		</div>
	</div>
</section>
<section class="section coach-training" aria-labelledby="first-visit-title">
	<div class="container">
		<h2 id="first-visit-title">Перед <span>первым визитом</span></h2>
		<div class="coach-training__grid">
			<div>
				<h3>Уточните время</h3>
				<p>Посмотрите <a href="/info/schedule/">расписание</a> и позвоните в зал, чтобы уточнить занятия с {$c['with']}. Условия группового абонемента и персональных тренировок доступны в разделе <a href="/info/prices/">«Цены»</a>.</p>
			</div>
			<div>
				<h3>Подготовьтесь к тренировке</h3>
				<p>Возьмите удобную спортивную форму, воду и сменную обувь. Требования к экипировке уточните перед посещением. Подробнее о знакомстве с залом — на странице <a href="/club/first-visit/">«Первый визит»</a>.</p>
			</div>
		</div>
	</div>
</section>
HTML;
    $r->fromArray(['class_key'=>modDocument::class,'template'=>13,'pagetitle'=>$name,'longtitle'=>$title,'description'=>$description,'alias'=>$c['slug'],'uri'=>'club/coaches/'.$c['slug'].'/','uri_override'=>0,'parent'=>10,'content'=>$content,'published'=>1,'deleted'=>0,'hidemenu'=>0,'searchable'=>1,'cacheable'=>1,'richtext'=>0,'isfolder'=>0,'menuindex'=>$index+1,'editedon'=>date('Y-m-d H:i:s')]);
    if(!$r->save())throw new RuntimeException('Coach save failed');
    $values=['page_heading'=>$c['first'].' <span>'.$c['last'].'</span>','page_intro'=>'Тренер команды ZUBR GYM. Поможем выбрать группу и уточнить условия занятий с тренером.','coach_role'=>'Тренер ZUBR GYM · Гродно','coach_experience'=>$c['years'],'coach_description'=>$name.' работает в команде ZUBR GYM. Тренировки проходят по адресу [[++zubr_contact_address:htmlent]]. Для подбора группы и знакомства с тренером свяжитесь с клубом.','coach_qualification_intro'=>$summary,'coach_photo'=>$photo,'coach_photo_alt'=>$name.', тренер ZUBR GYM','coach_qualifications'=>json_encode($qualifications,JSON_UNESCAPED_UNICODE),'cta_description'=>'Первое занятие в ZUBR GYM бесплатно. Позвоните в клуб, чтобы уточнить время и возможность тренировки с '.$c['with'].'.','seo_title'=>$title,'seo_description'=>$description,'seo_image'=>'','seo_index'=>'1'];
    foreach($values as $key=>$value)if(!$r->setTVValue($key,$value))throw new RuntimeException('TV save failed: '.$key);
    $source[$c['id']]=['name'=>$name,'slug'=>$c['slug'],'uri'=>'/club/coaches/'.$c['slug'].'/','content'=>$content,'values'=>$values];
}
// Turn the existing anchored cards into profile links on home and coaches listing, retaining IDs.
function linkCards(string $html,array $coaches):string{
    foreach($coaches as $c){
        $html=str_replace('href="/club/coaches/#'.$c['slug'].'"','href="/club/coaches/'.$c['slug'].'/"',$html);
        $html=str_replace($c['first'].' '.$c['last'].' — в списке тренеров',$c['first'].' '.$c['last'].' — страница тренера',$html);
        $opening='<div class="trainer-card" id="'.$c['slug'].'">';$start=strpos($html,$opening);if($start===false)continue;
        $innerStart=$start+strlen($opening);$depth=1;$end=null;$closeStart=null;
        preg_match_all('~</?div\b[^>]*>~',substr($html,$innerStart),$tags,PREG_OFFSET_CAPTURE);
        foreach($tags[0] as [$tag,$offset]){$depth+=str_starts_with($tag,'</')?-1:1;if($depth===0){$closeStart=$innerStart+$offset;$end=$closeStart+strlen($tag);break;}}
        if($end===null)throw new RuntimeException('Card closing tag not found');
        $inner=substr($html,$innerStart,$closeStart-$innerStart);
        $arrow='<span class="trainer-card__link" aria-hidden="true"><svg><use href="#arrow"/></svg></span>';
        $inner=preg_replace('~</div>\s*$~',$arrow.'</div>',$inner,1);
        $name=$c['first'].' '.$c['last'];$card='<a class="trainer-card" id="'.$c['slug'].'" href="/club/coaches/'.$c['slug'].'/" aria-label="'.$name.' — страница тренера">'.$inner.'</a>';
        $html=substr($html,0,$start).$card.substr($html,$end);
    }return $html;
}
foreach($modx->getCollection(modTemplate::class) as $t){$html=linkCards($t->get('content'),$coaches);if($html!==$t->get('content')){$t->set('content',$html);$t->save();}}
$reportFile=$root.'cms/import-result.json';$report=json_decode(file_get_contents($reportFile),true);foreach($report['resources'] as &$entry)if(isset($source[$entry['id']])){$entry['class']=modDocument::class;$entry['template']=13;$entry['parent']=10;}unset($entry);file_put_contents($reportFile,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
file_put_contents($root.'cms/coach-profiles.json',json_encode($source,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
$modx->cacheManager->refresh();echo "Created coach documents 14, 16 and 17 using template 13; profile cards linked.\n";
