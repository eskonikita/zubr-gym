<?php
namespace Zubr\Site;
final class TemplateRegistry
{
    public static function criteria(): array
    {
        $file=__DIR__.'/../template-registry.json';
        if(!is_file($file))return ['templatename:LIKE'=>'ZUBR — %'];
        $data=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
        return ['id:IN'=>array_column($data['templates'],'id')];
    }
}
