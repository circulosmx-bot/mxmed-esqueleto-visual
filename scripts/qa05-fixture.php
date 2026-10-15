<?php
declare(strict_types=1);

require __DIR__.'/../modules/identity/http/IdentityHttpComposition.php';
\Identity\Http\IdentityHttpComposition::registerAutoloader();

use Identity\Contracts\PasswordHash;

$required = ['MXMED_DB_HOST','MXMED_DB_PORT','MXMED_DB_NAME','MXMED_DB_USER','MXMED_DB_PASS','MXMED_QA05_CREDENTIALS_JSON'];
foreach ($required as $key) if (trim((string)getenv($key)) === '') throw new RuntimeException('qa05_fixture_configuration_required');
$database = (string)getenv('MXMED_DB_NAME');
if (!str_starts_with($database, 'mxmed_gate4d_preview_adminqa_') || strtolower($database) === 'mxmed') throw new RuntimeException('qa05_database_required');
$credentials = json_decode((string)file_get_contents((string)getenv('MXMED_QA05_CREDENTIALS_JSON')), true, 32, JSON_THROW_ON_ERROR);
if (!is_array($credentials) || array_keys($credentials) !== ['owner','administrator','collaborator']) throw new RuntimeException('qa05_credentials_invalid');

$pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', getenv('MXMED_DB_HOST'), getenv('MXMED_DB_PORT'), $database), (string)getenv('MXMED_DB_USER'), (string)getenv('MXMED_DB_PASS'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
$pdo->beginTransaction();
try {
    $doctor = 'qa05-doctor';
    $profile = $pdo->prepare("INSERT INTO profiles_doctors(doctor_id,display_name,professional_designation,prefix,gender,gender_label,professional_license,specialty_license,specialty_primary,bio_short,profile_status,is_public_candidate) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)");
    $profile->execute([$doctor,'Dra. Aurora Prueba Segura','Médica especialista de entorno QA','Dra.','female','Mujer','QA-SIN-VALIDEZ','QA-ESP-SIN-VALIDEZ','Medicina de entorno de prueba','Perfil totalmente sintético para revisión local aislada.','hidden',0]);

    $account = $pdo->prepare("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at) VALUES(?,?,?,'active',UTC_TIMESTAMP())");
    $credential = $pdo->prepare("INSERT INTO auth_account_credentials(account_id,password_hash,password_changed_at) VALUES(?,?,UTC_TIMESTAMP())");
    $consent = $pdo->prepare("INSERT INTO auth_account_consents(consent_id,account_id,document_type,document_version,accepted_at,metadata_json) VALUES(?,?,?,?,UTC_TIMESTAMP(),?)");
    $membership = $pdo->prepare("INSERT INTO auth_account_memberships(membership_id,account_id,profile_doctor_id,role_code,scope_code,status,assignment_source) VALUES(?,?,?,?,'profile','active','qa05_synthetic_fixture')");
    foreach ($credentials as $role => $entry) {
        $id = 'qa05-'.$role;
        $email = strtolower(trim((string)$entry['email']));
        $password = (string)$entry['password'];
        $account->execute([$id,$email,$email]);
        $credential->execute([$id,PasswordHash::hash($password)]);
        foreach (['terms','privacy_notice'] as $document) $consent->execute(['qa05-'.$role.'-'.$document,$id,$document,'qa05-local-v1',json_encode(['provenance'=>'qa05_synthetic_fixture'], JSON_THROW_ON_ERROR)]);
        $membership->execute(['qa05-membership-'.$role,$id,$doctor,$role]);
    }

    $office = $pdo->prepare("INSERT INTO consultorios(doctor_id,consultorio_id,titulo,grupo_nombre,calle,num_ext,num_int,cp,colonia,municipio,estado,telefonos_json,`local_parking`,`accessible`,document_is_primary,document_display_order,document_publication_enabled,lat,lng,geocode_source) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $offices = [
        ['1','Consultorio Centro QA','Clínica Sintética Centro','Avenida Prueba','101','2','20000','Zona Centro QA','Aguascalientes','Aguascalientes',['4490000101'],1,1,1,1,1,21.8818000,-102.2916000],
        ['2','Consultorio Norte QA','Clínica Sintética Norte','Paseo Simulado','202',null,'20100','Zona Norte QA','Aguascalientes','Aguascalientes',['4490000202'],0,1,0,2,0,21.9250000,-102.2900000],
        ['3','Consultorio Sur QA','Clínica Sintética Sur','Circuito Demostración','303','B','20200','Zona Sur QA','Aguascalientes','Aguascalientes',['4490000303'],null,0,0,3,1,21.8400000,-102.2950000],
    ];
    foreach ($offices as $row) $office->execute([$doctor,$row[0],$row[1],$row[2],$row[3],$row[4],$row[5],$row[6],$row[7],$row[8],$row[9],json_encode($row[10],JSON_THROW_ON_ERROR),$row[11],$row[12],$row[13],$row[14],$row[15],$row[16],$row[17],'qa05_fixture']);

    $contact = $pdo->prepare("INSERT INTO doctor_contact_points(doctor_id,type,value,normalized_value,label,scope,is_public,is_verified,verification_status,use_for_public_profile,status,sort_order,emergency_position,source,metadata_json) VALUES(?,'emergency_phone',?,?,?,?,0,0,'unverified',0,'active',?,?, 'qa05_fixture',?)");
    $contact->execute([$doctor,'4490000901','4490000901','Urgencias QA 1','private',10,1,json_encode(['synthetic'=>true],JSON_THROW_ON_ERROR)]);
    $contact->execute([$doctor,'4490000902','4490000902','Urgencias QA 2','private',20,2,json_encode(['synthetic'=>true],JSON_THROW_ON_ERROR)]);

    $schedule = $pdo->prepare("INSERT INTO consultorio_schedule(doctor_id,consultorio_id,weekday,start_time,end_time,is_active) VALUES(?,?,?,?,?,1)");
    $settings = $pdo->prepare("INSERT INTO agenda_settings(doctor_id,consultorio_id,appointment_duration_min,gap_between_appointments_min,channels_json,cancellation_policy_hours,reminder_template) VALUES(?,?,?,?,?,?,?)");
    foreach ($offices as $index => $row) {
        foreach ([1,2,3,4,5] as $weekday) $schedule->execute([$doctor,$row[0],$weekday,$index === 1 ? '10:00:00' : '09:00:00',$index === 2 ? '14:00:00' : '17:00:00']);
        $settings->execute([$doctor,$row[0],30,10,json_encode(['in_person'],JSON_THROW_ON_ERROR),24,'Recordatorio sintético QA; no se envía.']);
    }

    $appointment = $pdo->prepare("INSERT INTO agenda_appointments(appointment_id,doctor_id,consultorio_id,patient_id,start_at,end_at,reason_code,reason_text,modality,status,channel_origin,created_by_role,created_by_id) VALUES(?,?,?,NULL,?,?,?,?,?,'confirmed','qa05_fixture','owner',?)");
    $event = $pdo->prepare("INSERT INTO agenda_appointment_events(event_id,appointment_id,event_type,timestamp,actor_role,actor_id,channel_origin,notes) VALUES(?,?,'created',UTC_TIMESTAMP(),'owner',?,'qa05_fixture','Cita sintética; no contiene datos de paciente.')");
    $monday = (new DateTimeImmutable('next monday 10:00:00'))->modify('+7 days');
    for ($i=0; $i<5; $i++) {
        $start = $monday->modify('+'.$i.' days')->modify('+'.($i%3).' hours');
        $end = $start->modify('+30 minutes');
        $id = 'qa05-appointment-'.($i+1);
        $appointment->execute([$id,$doctor,$offices[$i%3][0],$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),'qa05-review','Cita sintética '.($i+1),'in_person','qa05-owner']);
        $event->execute(['qa05-event-'.($i+1),$id,'qa05-owner']);
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $error;
}

echo "QA05_FIXTURE=READY\n";
