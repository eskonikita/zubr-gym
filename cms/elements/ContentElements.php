<?php
namespace Zubr\Site;

use MODX\Revolution\{modX,modResource,modDocument};

final class ContentElements
{
    public function __construct(private modX $modx) {}
    private function escape(string $value): string { return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
    public function breadcrumbs(): string
    {
        $current=$this->modx->resource;if(!$current)return '';
        $home=(int)$this->modx->getOption('site_start');$chain=[];$seen=[];$r=$current;
        while($r&&!isset($seen[$r->get('id')])){$seen[$r->get('id')]=true;if((int)$r->get('id')!==$home)$chain[]=$r;$parent=(int)$r->get('parent');$r=$parent?$this->modx->getObject(modResource::class,$parent):null;}
        $out='<li><a href="/">Главная</a></li>';$arrow='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>';
        foreach(array_reverse($chain) as $item){$title=$this->escape((string)$item->get('pagetitle'));$out.='<li>'.$arrow.((int)$item->get('id')===(int)$current->get('id')?'<span aria-current="page">'.$title.'</span>':'<a href="/'.$this->escape(ltrim((string)$item->get('uri'),'/')).'">'.$title.'</a>').'</li>';}
        return $out;
    }
    public function contents(): string
    {
        $content=(string)$this->modx->resource?->get('content');if($content==='')return '';
        $previous=libxml_use_internal_errors(true);$document=new \DOMDocument();$document->loadHTML('<?xml encoding="UTF-8">'.$content,LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($previous);
        $xpath=new \DOMXPath($document);$out='';
        foreach($xpath->query('//section[@id]/h2') as $heading){$id=$heading->parentNode->getAttribute('id');$text=preg_replace('/^\d+\.\s*/u','',trim($heading->textContent));$out.='<li><a href="#'.$this->escape($id).'">'.$this->escape($text).'</a></li>';}
        return $out;
    }
    public function related(): string
    {
        $current=$this->modx->resource;if(!$current)return '';$query=$this->modx->newQuery(modResource::class,['parent'=>$current->get('parent'),'id:!='=>$current->get('id'),'published'=>1,'deleted'=>0,'class_key'=>modDocument::class]);$query->sortby('menuindex','ASC');$out='';
        $relatedTv=$this->modx->getObject(\MODX\Revolution\modTemplateVar::class,['name'=>'article_related']);
        $available=$this->modx->getCollection(modResource::class,$query);
        $relatedValue=$relatedTv?(string)$relatedTv->getValue($current->get('id')):'';
        $order=array_filter(array_map('intval',explode('||',$relatedValue)));$rows=[];
        foreach($available as $r)$rows[(int)$r->get('id')]=$r;
        $selected=$order?array_values(array_intersect_key(array_replace(array_fill_keys($order,null),$rows),array_fill_keys($order,true))):array_values($rows);
        foreach(array_slice(array_filter($selected),0,2) as $r)$out.=$this->modx->getChunk('articleCard',[
            'id'=>$r->get('id'),'pagetitle'=>$r->get('pagetitle'),
            'publishedon'=>$r->get('publishedon'),'createdon'=>$r->get('createdon'),
            'article_image'=>$r->getTVValue('article_image'),'article_image_alt'=>$r->getTVValue('article_image_alt'),
            'article_category'=>$r->getTVValue('article_category'),
            'page_heading'=>$r->getTVValue('page_heading'),'page_intro'=>$r->getTVValue('page_intro'),
        ]);
        return $out;
    }
    public function repeat(array $properties): string
    {
        $tv=(string)($properties['tv']??'');$tpl=(string)($properties['tpl']??'');if($tv===''||$tpl==='')return '';
        $rows=json_decode((string)$this->modx->resource?->getTVValue($tv),true);if(!is_array($rows))return '';$out='';foreach($rows as $i=>$row)if(is_array($row))$out.=$this->modx->getChunk($tpl,$row+['idx'=>$i+1]);return $out;
    }
}
