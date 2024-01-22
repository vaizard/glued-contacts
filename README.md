# Glued-contacts

Glued-contacts describes subjects (people, organizations and 'other entities')
with a single json object that is constructed on the fly from its atoms. Every 
contact atom (such as a subject's name, address, email, etc.) is stored as row in
`c_contacts_atoms.c_data` in json format. All other columns in `c_contacts_atoms`
are generated. 

While different classes of contact atoms have their own json schema (an address 
atom has obviously a different structure a e-mail atom), some data are shared
by all atom classes / schemas:

Metadata

- `_s` is schema name (i.e. name, vatid, address, phone, etc.)
- `_v` is schema version
- `_iss` is the object's issuer (authoritative source - i.e. url of a public registry which carries the atom or the uuid of the user who entered the data manually)
- `_sub` is the object's subject (the person)
- `_iat` issued at time (timestamp when the issuer/source first published the info)
- `_exp` expired (timestamp since when the information is no longer valid)
- `_vat` valid at time (timestamp of last validation of the data)

Required data

- `uuid` is the atom's unique identifier
- `kind` is atom kind (technically not required) 
- `value` is the unstructured data that is also used for fulltext search

Schemes and kinds

- address
- date
  - kind:foundation
  - kind:dissolution
  - kind:birth
  - kind:death
  - kind:anniversary
- email
- natid - rodné číslo
- phone
- regid - ičo
- vatid - dič
- name
  - kind:natural
  - kind:legal
- vat_payer - současný stav + historie
- payroll
  - object:nationality (string - countrycode)
  - object:citizenship (array of objects - string countrycode, timestamp - iat, timestamp - exp)
  - object:taxdomicile (array of objects - string countrycode, timestamp - iat, timestamp - exp)
  - health_insurance  (array of objects - string, timestamp - iat, timestamp - exp)
  - can_issue_invoices
  - status <= NOTE that status is to play along with the `stor/annotations` api
    - marital (doloženo formulářem zaměstnance)
    - student (student od do na základě potvrzení o studiu - anotovany dokument s platností od do)
    - taxpayer_declaration (růžový papír - anotoaný dokument s platností období od do)
    - disability (na základě potvrzení - anotoaný dokument s platností od do)
    - offspring (sleva na dítě na základě potvrzení o studiu či jiného dokumentu - anotovany dokument s platností od do)
    - contract (smlouva s platností od, nebo od-do - anotovaný dokument a typem)
- supplier
- client
- uri
  - website
- uuid
- docs - <= NOTE is to play along with the `stor/annotations` api
  - passport - pas 
  - id card - občanka
  - drivers licence
  - hygienický průkaz
  - ISO certifikát 
  - ...

Working with data stored as described above requires a less common querying and
presentation scheme. Both is described below:

## SQL querying by example

List the fulltext

```sql
SELECT bin_to_uuid(c_sub,1) as c_sub, c_scheme, c_fulltext from `t_contacts_atoms`
```

Json merged

```sql
SELECT  
json_merge_preserve(
  json_object(`c_scheme`, `c_data`->>"$.value"), '{}'
) as res_rows
FROM `t_contacts_atoms`
```

For each subject, generate the comma separated concatenation of its elements' 
fulltext strings - the `LIKE` query is case and diacritics insensitive due to 
table collation.

```sql
SELECT
bin_to_uuid(fts.c_sub,1) as uuid
,GROUP_CONCAT(fts.c_fulltext SEPARATOR " ") AS ftss
FROM `t_contacts_atoms` as fts
GROUP BY fts.c_sub
HAVING 
ftss LIKE '%32%'
```


Get subject as an array of all atoms

```sql
SELECT 
    c_sub,
    json_arrayagg(json_object(c_scheme, c_data))
  FROM `t_contacts_atoms` 
GROUP BY c_sub
```
Get all atoms of the same scheme of the same subject as an array (several rows)

```sql
SELECT
    bin_to_uuid(c_sub,1),
    c_scheme,
    json_arrayagg(c_data)
FROM `t_contacts_atoms`
GROUP BY c_scheme,bin_to_uuid(c_sub,1)
```

Get it all

```sql
SELECT 
  any_value(c_sub),
  json_objectagg(c_scheme,data) FROM (
  SELECT 
    bin_to_uuid(c_sub,1) AS c_sub,
    c_scheme,
    json_arrayagg(c_data) AS data
  FROM `t_contacts_atoms` 
  GROUP BY bin_to_uuid(c_sub,1),c_scheme
) AS data
GROUP BY c_sub
```

Return final objects as rows 

```sql
SELECT
    bin_to_uuid(fts.c_sub,1) as uuid,
    GROUP_CONCAT(fts.c_fulltext SEPARATOR " ") AS ftss,
    any_value(obj.data) as data
FROM `t_contacts_atoms` as fts
LEFT JOIN (
    SELECT
        any_value(c_sub) as c_sub,
        json_objectagg(c_scheme,data) as data
    FROM (
             SELECT
                 c_sub AS c_sub,
                 c_scheme,
                 json_arrayagg(c_data) AS data
             FROM `t_contacts_atoms`
             GROUP BY c_sub,c_scheme
         ) AS data
    GROUP BY c_sub
) AS obj
ON obj.c_sub = fts.c_sub
GROUP BY fts.c_sub
HAVING ftss LIKE '%%' 
```

Return a final json array of json objects

```sql
SELECT JSON_ARRAYAGG(data) as data from (
    SELECT
        bin_to_uuid(fts.c_sub,1) as uuid,
        GROUP_CONCAT(fts.c_fulltext SEPARATOR " ") AS ftss,
        any_value(obj.data) as data
    FROM `t_contacts_atoms` as fts
             LEFT JOIN (
        SELECT
            any_value(c_sub) as c_sub,
            json_objectagg(c_scheme,data) as data
        FROM (
                 SELECT
                     c_sub AS c_sub,
                     c_scheme,
                     json_arrayagg(c_data) AS data
                 FROM `t_contacts_atoms`
                 GROUP BY c_sub,c_scheme
             ) AS data
        GROUP BY c_sub
    ) AS obj
    ON obj.c_sub = fts.c_sub
    GROUP BY fts.c_sub
    HAVING ftss LIKE '%%'
) as data
```
Return list of subjects

```sql
SELECT JSON_OBJECTAGG(uuid,data) as data from (
    SELECT
        bin_to_uuid(fts.c_sub,1) as uuid,
        GROUP_CONCAT(fts.c_fulltext SEPARATOR " ") AS ftss,
        any_value(obj.data) as data
    FROM `t_contacts_atoms` as fts
             LEFT JOIN (
        SELECT
            any_value(c_sub) as c_sub,
            json_objectagg(c_scheme,data) as data
        FROM (
                 SELECT
                     c_sub AS c_sub,
                     c_scheme,
                     json_arrayagg(c_data) AS data
                 FROM `t_contacts_atoms`
                 GROUP BY c_sub,c_scheme
             ) AS data
        GROUP BY c_sub
    ) AS obj
    ON obj.c_sub = fts.c_sub
    GROUP BY fts.c_sub
    HAVING ftss LIKE '%%'
) as data
```


## Client-side (PHP) parsing

As of current (MySQL v 8.0.31), the database builtin function `JSON_REMOVE` will
not accept a wildcard in the jsonpath. Hence, removing metadata to lighten the
json response is done PHP side via https://github.com/Galbar/JsonPath-PHP.

## Sample data

```sql
INSERT INTO glued.t_contacts_atoms (c_data) VALUES ('{"_s": "uri", "_v": 1, "_iat": 1673721512, "_iss": "https://id.example.com/auth/realms/t1", "_sub": "7e172087-73e4-48ed-9f27-e51c531e42bb", "kind": "website", "uuid": "17c9eb5c-d9d0-4701-aec5-9c25a5c1d020", "value": "https://vaizard.org"}');
INSERT INTO glued.t_contacts_atoms (c_data) VALUES ('{"_s": "name", "_v": 1, "_iat": 1673721512, "_iss": "https://id.example.com/auth/realms/t1", "_sub": "7e172087-73e4-48ed-9f27-e51c531e42bb", "kind": "natural", "uuid": "9a4d54b3-d7c1-46dd-9ba3-acfb19537bad", "given": "Igor", "label": null, "value": "Mgr. Igor Hnizdo, MSc.", "family": "Hnizdo", "prefix": "Mgr.", "suffix": "MSc."}');
INSERT INTO glued.t_contacts_atoms (c_data) VALUES ('{"_s": "name", "_v": 1, "_iat": 1673721912, "_iss": "https://wwwinfo.mfcr.cz/ares/ares_es.html.cz", "_sub": "bac72087-73e4-48ed-9f27-e51c531e42bb", "kind": "legal", "uuid": "bacd54b3-d7c1-46dd-9ba3-acfb19537bad", "value": "Bauhaus, k.s."}');
INSERT INTO glued.t_contacts_atoms (c_data) VALUES ('{"_s": "email", "_v": 1, "_iat": 1673721512, "_iss": "https://id.example.com/auth/realms/t1", "_sub": "7e172087-73e4-48ed-9f27-e51c531e42bb", "uuid": "fd2f47b6-ae0a-46ec-91f6-8dcbd7294ff4", "label": null, "value": "igor@example.com", "_primary": 1}');
INSERT INTO glued.t_contacts_atoms (c_data) VALUES ('{"_s": "phone", "_v": 1, "_iat": 1673721512, "_iss": "https://example.com/", "_sub": "7e172087-73e4-48ed-9f27-e51c531e42bb", "uuid": "fd2f47b6-ae0a-46ec-91f6-8dcbd7294ff4", "label": null, "value": "+4207123456"}');
INSERT INTO glued.t_contacts_atoms (c_data) VALUES ('{"_s": "service", "_v": 1, "uri": "https://id.example.com/auth/realms/example1", "_iat": 1673721512, "_iss": "https://id.example.com/auth/realms/example1", "_sub": "7e172087-73e4-48ed-9f27-e51c531e42bb", "kind": "oidc", "uuid": "3d2c1354-9931-4af4-a2e4-02257d4f7e1c", "value": "https://id.example.com/auth/realms/example1/igorh", "handle": "igorh"}');
INSERT INTO glued.t_contacts_atoms (c_data) VALUES ('{"_s": "address", "_v": 1, "cc": "CZ", "zip": "61500", "_iat": 1673721512, "_iss": "https://glued.example.com/api/users/v1/pupen", "_sub": "7e172087-73e4-48ed-9f27-e51c531e42bb", "kind": "main", "unit": "Studio 13", "uuid": "432c1aaa-1131-abf4-b2eb-537b7d4f7111", "extra": "Béžová budova naproti výtopně", "floor": "1", "pobox": "123", "value": "Studio 13, 1. podlaží, Budova č. 76 - Industra Labs, Lazaretní 1/7, Brno-Židenice, Brno-Město, Jihomoravský kraj, 61500, CZ", "region": "Jihomoravský kraj", "street": "Lazaretní", "quarter": "Židenice", "district": "Brno-Město", "locacity": "Brno", "conscr_no": "1", "street_no": "7", "building_no": "76", "building_name": "Industra Labs"}');
INSERT INTO glued.t_contacts_atoms (c_data) VALUES ('{"_s": "natid", "_v": 1, "icc": "CZ", "num": "901111/3452", "_iat": 1673721512, "_iss": "https://id.example.com/auth/realms/t1", "_sub": "7e172087-73e4-48ed-9f27-e51c531e42bb", "kind": "CZ", "uuid": "fabd07f8-5005-43d6-9fd2-0a71511478c9", "value": "901111/3452"}');
INSERT INTO glued.t_contacts_atoms (c_data) VALUES ('{"_s": "vatid", "_v": 1, "icc": "CZ", "num": "CZ29228107", "_iat": 1673721512, "_iss": "https://id.example.com/auth/realms/t1", "_sub": "7e172087-73e4-48ed-9f27-e51c531e42bb", "kind": "CZ", "uuid": "e974af5c-afb0-4817-9f33-9a9d25839ab2", "value": "CZ29228107"}');
INSERT INTO glued.t_contacts_atoms (c_data) VALUES ('{"_s": "regid", "_v": 1, "icc": "CZ", "num": "29228107", "_iat": 1673721512, "_iss": "https://id.example.com/auth/realms/t1", "_sub": "7e172087-73e4-48ed-9f27-e51c531e42bb", "kind": "CZ", "uuid": "af5ce974-fbb0-4817-339f-d25839ab29a9", "value": "29228107"}');
```