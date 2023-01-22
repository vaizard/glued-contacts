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
