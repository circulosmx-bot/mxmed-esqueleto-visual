<?php
declare(strict_types=1);
require_once __DIR__.'/LogoImprovementFixture.php';
require_once __DIR__.'/../services/MediaReplacementService.php';
function mr9Context(array $caps=['media_review_request_replacement']){return mr6Context('request_replacement',$caps);}
function mr9Candidate(string $purpose='logo'):array{return $purpose==='photo'?mr5Candidate(mr5Pdo()):mr8Candidate('photo');}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__&&($argv[1]??'')==='candidate')echo json_encode(mr9Candidate($argv[2]??'logo'));
