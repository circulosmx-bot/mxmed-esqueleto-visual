<?php
declare(strict_types=1);
require __DIR__.'/../storage/LocalPersistentPrivateMediaStorage.php';
use Media\Storage\LocalPersistentPrivateMediaStorage as Storage;
function storageCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$root=sys_get_temp_dir().'/mr1-private-'.bin2hex(random_bytes(8));
$source=tempnam(sys_get_temp_dir(),'mr1-source-');file_put_contents($source,'synthetic-source');
$key='private/media-review/'.str_repeat('a',64).'/c9a0c96c-2871-4fe8-bb54-1aeb45f0423f/source/c9a0c96c-2871-4fe8-bb54-1aeb45f0423f.png';
$s=new Storage($root);
try{
 $s->storeImmutable($key,$source);$stream=(new Storage($root))->openReadStream($key);
 storageCheck(stream_get_contents($stream['stream'])==='synthetic-source','restart read');fclose($stream['stream']);
 storageCheck((fileperms($root.'/'.$key)&0777)===0600,'private file permissions');
 storageCheck((fileperms($root)&0777)===0700,'root permissions');
 try{$s->storeImmutable($key,$source);throw new RuntimeException('overwrite accepted');}catch(RuntimeException $e){storageCheck($e->getMessage()==='private_media_immutable_store_failed','immutable');}
 $s->delete($key);symlink($source,$root.'/'.$key);
 try{$s->openReadStream($key);throw new RuntimeException('symlink accepted');}catch(RuntimeException $e){storageCheck($e->getMessage()==='private_media_symlink_forbidden','symlink');}
 unlink($root.'/'.$key);
 try{new Storage($root,[$root.'/public']);throw new RuntimeException('overlap accepted');}catch(RuntimeException $e){storageCheck($e->getMessage()==='private_media_root_overlaps_public_root','overlap');}
 echo "PASS: private permissions, persistence, immutable no-clobber, symlink rejection, disjoint roots\n";
}finally{
 if(is_file($source))unlink($source);
 if(is_dir($root)){$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f){if($f->isDir()&&!$f->isLink())rmdir($f->getPathname());else unlink($f->getPathname());}rmdir($root);}
}
