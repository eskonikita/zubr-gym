<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('MODX_API_MODE',true);
require dirname(__DIR__,2).'/public/index.php';
$modx->initialize('mgr');
$modx->setLogLevel(MODX\Revolution\modX::LOG_LEVEL_WARN);
$modx->user=$modx->getObject(MODX\Revolution\modUser::class,['username'=>'admin']);
// The core ships a nullable source default which strict MySQL rejects; use the default file source explicitly.
if(!$modx->getObject(MODX\Revolution\modSystemSetting::class,['key'=>'photo_profile_source'])){
$s=$modx->newObject(MODX\Revolution\modSystemSetting::class);$s->fromArray(['key'=>'photo_profile_source','value'=>'1','xtype'=>'modx-combo-source','namespace'=>'core','area'=>'manager'],'',true,true);if(!$s->save())throw new RuntimeException('Cannot set default photo source');}
foreach(['ace-1.9.8-pl','pdotools-3.0.3-pl2','migx-3.0.2-beta1'] as $signature){
 $package=$modx->getObject(MODX\Revolution\Transport\modTransportPackage::class,['signature'=>$signature]);
 if($package&&$package->get('installed')){echo "$signature already installed\n";continue;}
 if(!$package){$package=$modx->newObject(MODX\Revolution\Transport\modTransportPackage::class);$package->fromArray(['signature'=>$signature,'workspace'=>1,'state'=>1,'package_name'=>explode('-',$signature)[0]],'',true,true);$package->setPackageVersionData();if(!$package->save())throw new RuntimeException('Cannot register '.$signature);}
 $package->set('source',$signature.'.transport.zip');$package->save();
 echo 'Installing '.$signature."...\n";
 if(!$package->install(['preserve_keys'=>true]))throw new RuntimeException('Installation failed: '.$signature);
 echo $signature." installed\n";
}
require __DIR__.'/migx-compat.php';
$modx->cacheManager->refresh();
