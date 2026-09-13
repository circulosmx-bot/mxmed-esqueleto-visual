<?php
declare(strict_types=1);
require_once __DIR__ . '/../services/PublicNamePresentation.php';
use Profiles\Services\PublicNamePresentation;
$cases=[
 ['Dra.','Dra. Leticia Muñoz Romo','Dra. Leticia Muñoz Romo'],
 ['Lic.','Dra. Leticia Muñoz Romo','Lic. Leticia Muñoz Romo'],
 ['Lic.','Leticia Muñoz Romo','Lic. Leticia Muñoz Romo'],
 [null,'Leticia Muñoz Romo','Leticia Muñoz Romo'],
 [null,'Dra. Leticia Muñoz Romo','Leticia Muñoz Romo'],
 ['Lic.','dr. Leticia Muñoz Romo','Lic. Leticia Muñoz Romo'],
 ['Dr.','Drake Muñoz Romo','Dr. Drake Muñoz Romo'],
 ['Lic.','Doctor Leticia Muñoz Romo','Lic. Doctor Leticia Muñoz Romo'],
 ['Lic.','Licencia Muñoz Romo','Lic. Licencia Muñoz Romo'],
 ['Lic.','QFB Leticia Muñoz Romo','Lic. Leticia Muñoz Romo'],
 ['Lic.','Psicóloga Leticia Muñoz Romo','Lic. Leticia Muñoz Romo'],
 ['Lic.',null,null],
];
foreach($cases as [$prefix,$name,$expected])if(PublicNamePresentation::compose($prefix,$name)!==$expected)throw new RuntimeException('Composition failed: '.json_encode([$prefix,$name]));
foreach(PublicNamePresentation::KNOWN_PREFIXES as $prefix)if(PublicNamePresentation::compose('Lic.',$prefix.' Leticia Muñoz Romo')!=='Lic. Leticia Muñoz Romo')throw new RuntimeException('Catalog prefix not stripped');
$html=file_get_contents(__DIR__.'/../../../index.html');preg_match('/<select[^>]*id="mxpi-prefix"[^>]*>(.*?)<\/select>/s',$html,$select);preg_match_all('/<option value="([^"]+)"/',$select[1],$options);
if($options[1]!==PublicNamePresentation::KNOWN_PREFIXES)throw new RuntimeException('Presentation catalog differs from Admin');
echo "PUBLIC_NAME_PRESENTATION=PASS: canonical/legacy/cleared prefix, unknown first words preserved, catalog parity, no duplicate known prefix\n";
