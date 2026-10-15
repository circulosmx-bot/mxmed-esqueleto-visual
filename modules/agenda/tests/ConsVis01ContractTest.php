<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);$read=static fn(string $path):string=>(string)file_get_contents($root.'/'.$path);
function consVisContract(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('FAIL '.$label);echo "CONS_VIS01_{$label}=PASS\n";}
$app=$read('private-app.php');$multi=$read('assets/js/perfil/consultorio/multisede.js');$ordering=$read('assets/js/perfil/consultorio/consultorio-ordering.js');
$repo=$read('modules/agenda/repositories/ConsultoriosRepository.php');$controller=$read('modules/agenda/controllers/ConsultoriosController.php');$router=$read('api/agenda/index.php');
$policy=$read('modules/agenda/security/PrivateAgendaRoutePolicy.php');$footer=$read('api/_lib/clinical_professional_presentation.php');
$publicRepo=$read('modules/profiles/repositories/PublicProfileRepository.php');$publicController=$read('modules/profiles/controllers/PublicProfileController.php');
consVisContract(substr_count($app,'Puedes escribir una referencia para que los pacientes ubiquen mejor tu consultorio')===0
  && substr_count($multi,'Puedes escribir una referencia para que los pacientes ubiquen mejor tu consultorio')===1
  && str_contains($multi,'data-map-reference-input-for'),'SINGLE_MAP_REFERENCE');
consVisContract(!str_contains($app,'id="cons-document-primary"')&&!str_contains($app,'id="cons-document-order"')
  && !str_contains($app,'Orden de presentación'),'OLD_ORDER_CONTROLS_REMOVED');
consVisContract(str_contains($ordering,'consultorios/reorder')&&str_contains($ordering,"event.key==='ArrowLeft'")
  && str_contains($ordering,"event.pointerType==='touch'")&&str_contains($ordering,"addEventListener('dragstart'"),'REORDER_INPUTS');
consVisContract(str_contains($repo,'FOR UPDATE')&&str_contains($repo,'stale_consultorio_order')
  && str_contains($repo,'document_is_primary = 0, document_display_order = NULL')
  && str_contains($repo,'document_is_primary = CASE'),'ATOMIC_CANONICAL_WRITER');
consVisContract(str_contains($controller,'presentation_order_fingerprint')&&str_contains($router,'$consultorios->reorder')
  && str_contains($controller,'use canonical consultorio reorder operation')
  && str_contains($policy,"new PrivateAgendaMethodRule('consultorios', 'PUT', \$ownerAdministrator"),'CANONICAL_AUTHORITY');
consVisContract(!str_contains($multi,'document_is_primary:')&&!str_contains($multi,'document_display_order:')
  && str_contains($multi,'document_publication_enabled:'),'PUBLICATION_INDEPENDENT');
$mapPos=strpos($app,'id="cons-map-frame"');$amenitiesPos=strpos($app,'data-cons-location-amenities');$groupPos=strpos($app,'¿Tu consultorio forma parte de un grupo médico?');
consVisContract($mapPos!==false&&$amenitiesPos>$mapPos&&$groupPos>$amenitiesPos
  && str_contains($app,'Cuenta con estacionamiento')&&str_contains($app,'Accesibilidad para personas con discapacidad'),'AMENITIES_NEAR_MAP');
consVisContract(substr_count($app,'<option value="">Sin especificar</option><option value="1">Sí</option><option value="0">No</option>')===2
  && str_contains($multi,'local_parking: triStateValue')&&str_contains($multi,'accessible: triStateValue'),'AMENITIES_TRISTATE_PERSISTENCE');
consVisContract(str_contains($router,"'emergency-contacts'")&&str_contains($controller,'replacePrivateSet')
  && str_contains($multi,'emergency_contacts_fingerprint')&&str_contains($multi,'syncEmergencyFields'),'SHARED_EMERGENCY_CANONICAL_WRITER');
consVisContract(!str_contains($publicRepo,"'urgencias_json',")&&str_contains($publicRepo,'emergency_phone')
  && !str_contains($publicController,"row['urgencias_json']")&&str_contains($publicController,"publicEmergencyPhones"),'PUBLIC_EMERGENCY_CANONICAL_READER');
consVisContract(!str_contains($footer,'urgencias_json')&&!str_contains($footer,'emergency'),'EMERGENCY_FOOTER_PRIVACY');
$proposal=$read('docs/profile/CONS_VIS01_EMERGENCY_CONTACT_SCHEMA_PROPOSAL.sql');
consVisContract(str_contains($proposal,"doctor_contact_points(type='emergency_phone')")&&str_contains($proposal,'Deliberately no backfill')
  && !str_contains($proposal,'CREATE TABLE'),'EMERGENCY_PROPOSAL_RETIRED_NO_BACKFILL');
consVisContract(str_contains($app,'consultorio-ordering.js?v=cons-vis01')&&str_contains($app,'multisede.js?v=cons-imp02b')
  && str_contains($app,'consultorio-visual-normalization.css?v=cons-vis01'),'ASSET_CACHE_VERSIONED');
echo "CONS_VIS01_CONTRACT=PASS\n";
