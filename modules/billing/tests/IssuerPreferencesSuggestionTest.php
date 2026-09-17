<?php
declare(strict_types=1);

require_once __DIR__.'/../services/IssuerPreferencesService.php';

use Billing\Services\IssuerPreferencesService;
use Billing\Services\IssuerProfileService;
use Billing\Services\SatCfdiCatalog;

$pdo=new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE profiles_doctors (doctor_id TEXT PRIMARY KEY,primary_specialty_credential_id INTEGER,logo_url TEXT);
CREATE TABLE profiles_doctor_credentials (credential_id INTEGER PRIMARY KEY,doctor_id TEXT,credential_type TEXT,professional_area_label TEXT,verification_status TEXT,lifecycle_status TEXT);
CREATE TABLE media_assets (public_url TEXT,owner_type TEXT,owner_id TEXT,purpose TEXT,classification TEXT,status TEXT,deleted_at TEXT);
INSERT INTO profiles_doctors VALUES ('physician',2,NULL),('dental',NULL,NULL),('unverified',NULL,NULL),('ambiguous',NULL,NULL);
INSERT INTO profiles_doctor_credentials VALUES
 (1,'physician','PROFESSIONAL','Médico Cirujano','VERIFIED','ACTIVE'),
 (2,'physician','SPECIALTY','Cardiología','VERIFIED','ACTIVE'),
 (3,'dental','PROFESSIONAL','Cirujano Dentista','VERIFIED','ACTIVE'),
 (4,'unverified','PROFESSIONAL','Médico General','PENDING_REVIEW','ACTIVE'),
 (5,'ambiguous','PROFESSIONAL','Profesional de salud','VERIFIED','ACTIVE');");
$service=new IssuerPreferencesService($pdo,new IssuerProfileService($pdo,new SatCfdiCatalog()));
foreach([
    'physician'=>'Consulta médica de cardiología',
    'dental'=>'Consulta odontológica',
    'unverified'=>'Servicios profesionales',
    'ambiguous'=>'Servicios profesionales',
] as $doctor=>$expected){
    if($service->suggestion($doctor)!==$expected)throw new RuntimeException('wrong suggestion for '.$doctor);
}
if($service->professionalLogoAvailable('physician'))throw new RuntimeException('logo absent mismatch');
$pdo->exec("UPDATE profiles_doctors SET logo_url='/qa-logo.webp' WHERE doctor_id='physician';
INSERT INTO media_assets VALUES ('/qa-logo.webp','PHYSICIAN','physician','PHYSICIAN_PERSONAL_LOGO','PUBLIC','PENDING_REVIEW',NULL);");
if($service->professionalLogoAvailable('physician'))throw new RuntimeException('pending logo exposed');
$pdo->exec("UPDATE media_assets SET status='READY'");
if(!$service->professionalLogoAvailable('physician'))throw new RuntimeException('approved logo unavailable');
echo "PASS verified profession/specialty suggestion, ambiguity fallback, current approved logo only\n";
