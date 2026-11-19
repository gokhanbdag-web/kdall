<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$navigation=require dirname(__DIR__).'/app/config/navigation.php';
$entry=file_get_contents(dirname(__DIR__).'/index.php');
if($entry===false){fwrite(STDERR,"Admin entrypoint missing.\n");exit(1);}
$missing=[];
foreach($navigation as $section){
 foreach($section['items']??[] as $item){
  $page=(string)($item['page']??'');
  if($page===''||$page==='dashboard')continue;
  if(!preg_match('/\\$page\\s*===\\s*[\\x27\\x22]'.preg_quote($page,'/').'[\\x27\\x22]/',$entry))$missing[]=$page;
 }
}
if($missing){fwrite(STDERR,'Navigation pages without routes: '.implode(', ',array_unique($missing))."\n");exit(1);}
if(strpos($entry,'Bu modül henüz etkinleştirilmedi.')!==false){fwrite(STDERR,"Stale inactive-module placeholder found.\n");exit(1);}
echo "KodVadAll admin navigation route coverage passed.\n";
