import hashlib
import json
import os
import subprocess
import urllib.error
import urllib.parse
import urllib.request

BASE = os.environ['PROV03CB_QA_BASE']
DB = os.environ['PROV03CB_QA_DB']
with open(os.environ['PROV03CB_FIXTURE'], encoding='utf-8') as handle:
    F = json.load(handle)
OWNER = 'PHPSESSID=prov03cb-owner'
OTHER = 'PHPSESSID=prov03cb-other'


def check(condition, name):
    assert condition, name
    print(name + '=PASS')


def sql(statement):
    result = subprocess.run(['mysql', '-N', DB, '-e', statement], check=True,
                            capture_output=True, text=True)
    return result.stdout.strip()


def match(order='full', regions=('MX|CP|20000',), mode='ON_SITE', cookie=OWNER,
          doctor='d_match', patient='p_match', version=1, page=1, page_size=20):
    query = urllib.parse.urlencode({
        'document_version': str(version), 'service_mode': mode,
        'region_keys[]': list(regions), 'page': str(page), 'page_size': str(page_size),
    }, doseq=True)
    path = (f'/api/clinical/index.php/doctors/{doctor}/patients/{patient}/study-orders/'
            f'{F["orders"][order]}/provider-matches?{query}')
    request = urllib.request.Request(BASE + path, headers={'Accept': 'application/json',
                                                          'Cookie': cookie} if cookie else {'Accept': 'application/json'})
    try:
        with urllib.request.urlopen(request) as response:
            return response.status, json.load(response)
    except urllib.error.HTTPError as error:
        return error.code, json.load(error)


def candidates(data):
    return {row['location']['location_uuid']: row for row in data['candidates']}


def payload_hash():
    rows = sql('SELECT document_uuid,payload_json FROM clinical_documents ORDER BY id')
    return hashlib.sha256(rows.encode()).hexdigest()


def provider_hash():
    rows = sql('SELECT * FROM healthcare_organization_provider_status ORDER BY group_id;'
               'SELECT * FROM healthcare_organization_locations ORDER BY location_id;'
               'SELECT * FROM healthcare_organization_location_study_offerings ORDER BY offering_id;'
               'SELECT * FROM healthcare_organization_location_study_service_areas ORDER BY service_area_id')
    return hashlib.sha256(rows.encode()).hexdigest()


before = payload_hash()
before_provider = provider_hash()
status, response = match()
check(status == 200 and response['ok'], 'QA_AUTHORIZED')
check(payload_hash() == before and provider_hash() == before_provider, 'QA_ENDPOINT_READ_ONLY')
data = response['data']
rows = candidates(data)
loc = F['locations']
check(rows[loc['full']]['coverage']['classification'] == 'FULL_VERIFIED_COVERAGE'
      and rows[loc['full']]['coverage']['matched_item_count'] == 3
      and rows[loc['full']]['coverage']['total_order_item_count'] == 3,
      'QA_FULL_COVERAGE')
check(rows[loc['ab']]['coverage']['classification'] == 'PARTIAL_VERIFIED_COVERAGE'
      and rows[loc['ab']]['coverage']['matched_item_count'] == 2
      and rows[loc['ab']]['coverage']['unmatched_cataloged_item_count'] == 1
      and rows[loc['ab']]['coverage']['unmatched_cataloged_order_item_ids'] == [F['item_ids'][2]],
      'QA_PARTIAL_COVERAGE')
check(rows[loc['ac']]['coverage']['classification'] == 'PARTIAL_VERIFIED_COVERAGE'
      and loc['full'] != loc['ab'] != loc['ac'], 'QA_MULTIPLE_BRANCHES_SEPARATE')
check(loc['hospital'] not in rows and loc['clinic'] in rows
      and len(rows) == 4, 'QA_ON_SITE_EXACT_REGION')
check(all(loc[key] not in rows for key in ('unverified_org', 'inactive_org', 'rejected_org', 'missing_org')),
      'QA_PROVIDER_STATES_EXCLUDED')
check(all(loc[key] not in rows for key in ('unverified_loc', 'inactive_loc', 'rejected_loc')),
      'QA_LOCATION_STATES_EXCLUDED')
check(all(loc[key] not in rows for key in ('unverified_offering', 'inactive_offering', 'rejected_offering')),
      'QA_OFFERING_STATES_EXCLUDED')
check(data['query']['region_keys'] == ['MX|CP|20000'] and data['query']['service_mode'] == 'ON_SITE',
      'QA_EXPLICIT_REGION_MODE')
check('Paciente Secreto QA' not in json.dumps(data, ensure_ascii=False)
      and 'INDICACION_PRIVADA_QA' not in json.dumps(data, ensure_ascii=False),
      'QA_NO_PATIENT_CONTACT_OR_INDICATION_RETURNED')

status, response = match(regions=('MX|CP|20001', 'MX|CP|20000', 'MX|CP|20000'))
multi = candidates(response['data'])
check(status == 200 and loc['hospital'] in multi
      and response['data']['query']['region_keys'] == ['MX|CP|20000', 'MX|CP|20001'],
      'QA_MULTIPLE_REGIONS_DEDUPLICATED')
check(multi[loc['hospital']]['organization']['organization_type_key'] == 'HOSPITAL'
      and rows[loc['full']]['organization']['organization_type_key'] == 'LABORATORY'
      and rows[loc['clinic']]['organization']['organization_type_key'] == 'CLINIC',
      'QA_ORGANIZATION_TYPE_INDEPENDENCE')

status, response = match(order='custom')
custom = candidates(response['data'])[loc['full']]['coverage']
check(status == 200 and custom['classification'] == 'PARTIAL_VERIFIED_COVERAGE'
      and custom['matched_item_count'] == 2 and custom['unmatchable_item_count'] == 1
      and custom['unmatchable_custom_order_item_ids'] == [F['custom_item_ids'][2]],
      'QA_CUSTOM_PREVENTS_FULL')
status, response = match(order='legacy')
check(status == 200 and response['data']['candidates'] == []
      and response['data']['summary']['unmatchable_item_count'] == 2
      and all(item['reason'] == 'UNMATCHABLE_LEGACY'
              for item in response['data']['summary']['unmatchable_items']), 'QA_V1_NOT_INFERRED')

status, response = match(mode='HOME_SERVICE')
home = candidates(response['data'])
check(status == 200 and list(home) == [loc['home']]
      and home[loc['home']]['coverage']['matched_item_count'] == 1,
      'QA_HOME_VERIFIED_AREA')
status, response = match(mode='HOME_SERVICE', regions=('MX|CP|20001',))
check(status == 200 and response['data']['candidates'] == [], 'QA_HOME_UNVERIFIED_AREA_EXCLUDED')
status, response = match(mode='MOBILE')
mobile = candidates(response['data'])
check(status == 200 and list(mobile) == [loc['mobile']]
      and mobile[loc['mobile']]['coverage']['matched_order_item_ids'] == [F['item_ids'][1]],
      'QA_MOBILE_VERIFIED_AREA')
sql('UPDATE healthcare_organization_location_study_service_areas SET operational_state="INACTIVE" '
    f'WHERE service_area_id={F["home_area_id"]}')
status, response = match(mode='HOME_SERVICE')
check(status == 200 and response['data']['candidates'] == [], 'QA_HOME_INACTIVE_AREA_EXCLUDED')
sql('UPDATE healthcare_organization_location_study_service_areas SET operational_state="ACTIVE" '
    f'WHERE service_area_id={F["home_area_id"]}')

sql(f'UPDATE healthcare_organization_provider_status SET operational_state="INACTIVE" WHERE group_id="org_lab"')
status, response = match()
check(status == 200 and loc['full'] not in candidates(response['data']), 'QA_PROVIDER_INACTIVE')
sql(f'UPDATE healthcare_organization_provider_status SET operational_state="ACTIVE" WHERE group_id="org_lab"')
sql(f'UPDATE healthcare_organization_locations SET operational_state="INACTIVE" '
    f'WHERE location_uuid="{loc["full"]}"')
status, response = match()
check(status == 200 and loc['full'] not in candidates(response['data']), 'QA_LOCATION_INACTIVE')
sql(f'UPDATE healthcare_organization_locations SET operational_state="ACTIVE" '
    f'WHERE location_uuid="{loc["full"]}"')
sql(f'UPDATE healthcare_organization_location_study_offerings o '
    f'JOIN healthcare_organization_locations l ON l.location_id=o.location_id '
    f'SET o.operational_state="INACTIVE" WHERE l.location_uuid="{loc["full"]}" '
    f'AND o.study_type_id={F["studies"][0]}')
status, response = match()
check(status == 200 and candidates(response['data'])[loc['full']]['coverage']['matched_item_count'] == 2,
      'QA_OFFERING_INACTIVE')
sql(f'UPDATE healthcare_organization_location_study_offerings o '
    f'JOIN healthcare_organization_locations l ON l.location_id=o.location_id '
    f'SET o.operational_state="ACTIVE" WHERE l.location_uuid="{loc["full"]}" '
    f'AND o.study_type_id={F["studies"][0]}')
sql(f'UPDATE clinical_study_types SET is_active=0 WHERE study_type_id={F["studies"][2]}')
status, response = match()
check(status == 200 and candidates(response['data'])[loc['full']]['coverage']['classification'] == 'PARTIAL_VERIFIED_COVERAGE'
      and response['data']['summary']['unmatchable_item_count'] == 1,
      'QA_INACTIVE_CATALOG_STUDY_UNMATCHABLE')
sql(f'UPDATE clinical_study_types SET is_active=1 WHERE study_type_id={F["studies"][2]}')

status, page1 = match(page_size=1)
status2, page2 = match(page=2, page_size=1)
check(status == status2 == 200 and page1['data']['query']['has_more']
      and page1['data']['candidates'][0]['location']['location_uuid']
      != page2['data']['candidates'][0]['location']['location_uuid'], 'QA_BOUNDED_PAGINATION')
status, repeated = match(page_size=1)
check(status == 200 and repeated['data']['candidates'] == page1['data']['candidates'],
      'QA_DETERMINISTIC_ORDER')
status, response = match(page_size=101)
check(status == 400, 'QA_PAGE_SIZE_LIMIT')
status, response = match(regions=('MX|CP|99998',))
check(status == 200 and response['data']['candidates'] == [], 'QA_ZERO_PROVIDERS')
status, response = match(regions=('Mexico City',))
check(status == 400, 'QA_FREE_TEXT_REGION_REJECTED')
status, response = match(mode='REMOTE')
check(status == 400, 'QA_UNSUPPORTED_MODE_REJECTED')

baseline = [row['location']['location_uuid'] for row in data['candidates']]
sql("INSERT INTO profile_subscriptions(subscription_id,entity_type,entity_id,plan_code,contracted_plan_code,"
    "effective_plan_code,status) VALUES('00000000-0000-4000-8000-000000000008','laboratory','org_lab',"
    "'professional','professional','professional','active')")
status, response = match()
check(status == 200 and [row['location']['location_uuid'] for row in response['data']['candidates']] == baseline,
      'QA_COMMERCIAL_INDEPENDENCE')

status, response = match(cookie=OTHER, doctor='d_other')
check(status == 404, 'QA_UNRELATED_PHYSICIAN')
status, response = match(patient='p_wrong')
check(status == 404, 'QA_WRONG_PATIENT')
status, response = match(cookie=None)
check(status == 401, 'QA_NO_SESSION')
status, response = match(cookie=OTHER)
check(status == 403, 'QA_ROUTE_DOCTOR_SCOPE')
status, response = match(version=2)
check(status == 404, 'QA_EXACT_VERSION_REQUIRED')
try:
    urllib.request.urlopen(BASE + '/api/clinical/index.php/provider-matches/' + F['orders']['full'])
    uuid_only_status = 200
except urllib.error.HTTPError as error:
    uuid_only_status = error.code
check(uuid_only_status in (401, 404), 'QA_UUID_ONLY_DENIED')

sql(f'UPDATE clinical_documents SET status="voided" WHERE document_uuid="{F["orders"]["full"]}"')
status, response = match()
check(status == 409 and response['error'] == 'order_voided', 'QA_VOIDED_ORDER_DENIED')
sql(f'UPDATE clinical_documents SET status="generated" WHERE document_uuid="{F["orders"]["full"]}"')
sql(f'UPDATE clinical_documents SET payload_json=JSON_SET(payload_json,"$.replaced_by_document_uuid",'
    f'"00000000-0000-4000-8000-000000000009") WHERE document_uuid="{F["orders"]["full"]}"')
status, response = match()
check(status == 409 and response['error'] == 'order_replaced', 'QA_REPLACED_ORDER_DENIED')
sql(f'UPDATE clinical_documents SET payload_json=JSON_REMOVE(payload_json,"$.replaced_by_document_uuid") '
    f'WHERE document_uuid="{F["orders"]["full"]}"')
check(payload_hash() == before, 'QA_ORDER_PAYLOAD_UNCHANGED')
sql('INSERT INTO clinical_documents(document_uuid,document_type,title,version,status,patient_id,payload_json,'
    'event_datetime,created_at,generated_at,created_by_user_id) '
    f'SELECT UUID(),document_type,title,version+1,status,patient_id,payload_json,NOW(),NOW(),NOW(),'
    f'created_by_user_id FROM clinical_documents WHERE document_uuid="{F["orders"]["full"]}"')
sql('INSERT INTO clinical_document_revisions(original_document_id,supersedes_document_id,new_document_id,'
    'reason,author_user_id,created_at) '
    f'SELECT old.id,old.id,new.id,"QA replacement","u_match",NOW() '
    f'FROM clinical_documents old JOIN clinical_documents new ON new.version=old.version+1 '
    f'AND new.patient_id=old.patient_id AND new.document_type=old.document_type '
    f'WHERE old.document_uuid="{F["orders"]["full"]}" ORDER BY new.id DESC LIMIT 1')
status, response = match()
check(status == 409 and response['error'] == 'order_replaced', 'QA_REVISION_SUCCESSOR_DENIED')
sql('DELETE FROM healthcare_organization_location_study_service_areas;'
    'DELETE FROM healthcare_organization_location_study_offerings;'
    'DELETE FROM healthcare_organization_locations;'
    'DELETE FROM healthcare_organization_provider_status;'
    'DELETE FROM medical_groups')
status, response = match(order='custom')
check(status == 200 and response['data']['candidates'] == []
      and int(sql('SELECT COUNT(*) FROM medical_groups')) == 0,
      'QA_NO_PROVIDER_FIXTURES_EMPTY_RESULT')
