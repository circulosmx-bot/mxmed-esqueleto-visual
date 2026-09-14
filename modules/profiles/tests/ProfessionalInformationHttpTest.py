"""Synthetic router only. Seeds synthetic-a; no live profile testing."""
import http.cookiejar
import json
import os
import urllib.error
import urllib.request

base = os.environ['IP01A_TEST_URL']
url = base + '/api/profiles/professional-information.php'

def client(doctor='synthetic-a'):
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def request(method='GET', body=None, csrf='', suffix=''):
        headers = {'Content-Type': 'application/json', 'X-Professional-Information-CSRF': csrf}
        if doctor != 'synthetic-a': headers['Cookie'] = 'qa_doctor=' + doctor
        req = urllib.request.Request(url + suffix, method=method, headers=headers,
                                     data=json.dumps(body).encode() if body is not None else None)
        try:
            with opener.open(req) as response: return response.status, json.load(response)
        except urllib.error.HTTPError as error: return error.code, json.load(error)
    return request

first = client()
status, data = first()
assert status == 200
token = data['data']['csrf_token']
types = ['CERTIFICATION', 'COURSE', 'DIPLOMA', 'MEMBERSHIP', 'SERVICE', 'DISEASE', 'TREATMENT']
draft = {'public_professional_summary': 'Resumen sintético', 'items': {t: [t+' uno', t+' dos', t+' tres'] for t in types}}
assert first('PUT', draft, token)[0] == 200
assert first('PUT', draft, 'invalid')[0] == 403
assert first(suffix='?doctor_id=synthetic-b')[0] == 403
assert first('PUT', {**draft, 'doctor_id': 'synthetic-b'}, token)[0] == 403
assert client('none')()[0] == 401
assert client('synthetic-b')()[1]['data']['professional_information']['items']['SERVICE'] == []
assert client()()[1]['data']['professional_information'] == draft
print('IP01A_HTTP_QA=PASS')
