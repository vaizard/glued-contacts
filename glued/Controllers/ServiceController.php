<?php

declare(strict_types=1);

namespace Glued\Controllers;

use Firebase\JWT\ExpiredException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Glued\Classes\Exceptions\AuthTokenException;
use Glued\Classes\Exceptions\AuthJwtException;
use Glued\Classes\Exceptions\AuthOidcException;
use Glued\Classes\Exceptions\DbException;
use Glued\Classes\Exceptions\TransformException;
use Ramsey\Uuid\Uuid;
use JsonPath\JsonObject;


use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

class ServiceController extends AbstractController
{

    public string $schema = '
        {
          "$schema": "http://json-schema.org/draft-2020-12/schema#",
          "type": "object",
          "properties": {
            "name": {
              "type": [ "array", "null" ],
              "minItems": 1,
              "items": {
                "type": "object",
                "properties": {
                  "val": { "type": "string" },
                  "kind": { "type": "string", "default": "fn" }
                },
                "required": ["val"]
              }
            },
            "uuid": { "type": "string" },
            "regid": {
              "type": [ "array", "null" ],
              "minItems": 1,
              "items": {
                "type": "object",
                "properties": {
                  "val": { "type": "string" },
                  "countrycode": { "type": "string" }
                },
                "required": ["val"]
              }
            },
            "vatid": {
              "type": [ "array", "null" ],
              "minItems": 1,
              "items": {
                "type": "object",
                "properties": {
                  "val": { "type": "string" },
                  "countrycode": { "type": "string" }
                },
                "required": ["val"]
              }
            },
            "address": {
              "type": "array",
              "items": {
                "type": "object",
                "properties": {
                  "val": { "type": "string" },
                  "kind": { "type": "string" },
                  "region": { "type": "string" },
                  "street": { "type": "string" },
                  "suburb": { "type": "string" },
                  "district": { "type": "string" },
                  "postcode": { "type": "integer" },
                  "countrycode": { "type": "string" },
                  "municipality": { "type": "string" },
                  "streetnumber": { "type": "integer" },
                  "conscriptionnumber": { "type": "integer" }
                },
                "required": ["val"]
              }
            },
            "domicile": { "type": "string" },
            "registration": {
              "type": "array",
              "items": {
                "type": "object",
                "properties": {
                  "val": { "type": "string" },
                  "date": {
                    "type": "object",
                    "properties": {
                      "iss": { "type": "string", "format": "date", "description": "Issued at" },
                      "eff": { "type": "string", "format": "date", "description": "Effective at" },
                      "upd": { "type": "string", "format": "date", "description": "(Last) updated at" },
                      "vrf": { "type": "string", "format": "date", "description": "(Last) verified at" },
                      "exp": { "type": "string", "format": "date", "description": "Expires at" }
                      },
                    "required": []
                  },
                  "kind": { "type": "string" },
                  "issuer": { "type": "string" },
                  "countrycode": { "type": "string" },
                  "stor": { "type": "string" }
                },
                "required": ["val"]
              }
            },
            "email": {
              "type": [ "array", "null" ],
              "minItems": 1,
              "items": {
                "type": "object",
                "properties": {
                  "val": { "type": "string" },
                  "label": { "type": "string" }
                },
                "required": ["val"]
              }
            },
            "phone": {
              "type": [ "array", "null" ],
              "minItems": 1,
              "items": {
                "type": "object",
                "properties": {
                  "val": { "type": "string" },
                  "label": { "type": "string" }
                },
                "required": ["val"]
              }
            },
            "natid": {
              "type": [ "array", "null" ],
              "minItems": 1,
              "items": {
                "type": "object",
                "properties": {
                  "val": { "type": "string" },
                  "label": { "type": "string" },
                  "countrycode": { "type": "string" }
                },
                "required": ["val"]
              }
            },
            "event": {
              "type": "array",
              "items": {
                "type": "object",
                "properties": {
                  "kind": { "type": "string", "enum": ["dob", "dod", "anniversary", "fiscalyear", "other"], "default": "other" },
                  "dtstart": { "type": "string", "format": "date" },
                  "dtend": { "type": "string", "format": "date" },
                  "duration": { "type": "string", "format": "date" },
                  "summary": { "type": "string" },
                  "rrule": { "type": "string" },
                  "rdate": { "type": "string" },
                  "exdate": { "type": "string" },
                  "cal": { "type": "string", "description": "Glued calendar uri" },
                  "val": { "type": "string" }
                },
                "required": ["kind", "val"]
              }
            },
            "pay": {
              "type": "array",
              "items": {
                "type": "object",
                "properties": {
                  "iban": { "type": "string" },
                  "accountnr": { "type": "string" },
                  "routingnr": { "type": "string" },
                  "bic": { "type": "string" },
                  "currency": { "type": "string" },
                  "bankname": { "type": "string", "description": "Bank name" },
                  "bankaddr": { "type": "string", "description": "Bank address" },
                  "label": { "type": "string", "description": "User defined label such as `main CZK account`" },
                  "uri": { "type": "array", "description": "Uris in the RFC 8905 payto scheme, or special purpose uris such as revolut.me/id" },
                  "val": { "type": "string" }
                },
                "required": ["val"]
              }
            },
            "uri": {
              "type": "array",
              "items": {
                "type": "object",
                "properties": {
                  "kind": { "type": "string" },
                  "val": { "type": "string" }
                },
                "required": ["kind", "value"]
              }
            }
          },
          "anyOf": [
            { "required": ["name"] },
            { "required": ["email"] },
            { "required": ["phone"] }
          ]
        }
        ';
        protected $validator;
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->validator = new Validator();
        $resolver = $this->validator->loader()->resolver();
        $resolver->registerRaw($this->schema, 'https://glued/contacts/object.json');
    }

    /**
     * Returns an exception.
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response Json result set.
     */
    public function stub(Request $request, Response $response, array $args = []): Response
    {
        throw new \Exception('Stub method served where it shouldn\'t. Proxy misconfiguration?');
    }

    public function contacts_d1(Request $request, Response $response, array $args = []): Response
    {
        $headers = '';
        foreach ($request->getHeaders() as $name => $values) {
            $headers .= $name . ": " . implode(", ", $values);
        }
        $r = [
            'qp' => $request->getQueryParams(),
            'pb' => $request->getParsedBody(),
            'fi' => $request->getUploadedFiles(),
            'hd' => $headers
        ];
        return $response->withJson($r);
    }

    private function contacts_patch($json, $objUUID = null)
    {
        if (is_null($objUUID) or !$objUUID) {
            $objUUID = Uuid::uuid4()->toString();
        }

        $j = new JsonObject($json, true);
        $ft = implode(" ", $j->{'$..val'});
        $q = " 
        INSERT INTO `t_contacts_objects` 
            (`c_uuid`, `c_data`, `c_ft`) VALUES (uuid_to_bin(?, 1), ?, ?) 
        ON DUPLICATE KEY UPDATE
            c_data = JSON_MERGE_PATCH(c_data, values(c_data)),
            `c_ft` = VALUES(`c_ft`);
        ";
        return $this->mysqli->execute_query($q, [$objUUID, $json, $ft]);
    }

    public function contacts_p1(Request $request, Response $response, array $args = []): Response
    {
        if (($request->getHeader('Content-Type')[0] ?? '') != 'application/json') { throw new \Exception('Content-Type header missing or not set to `application/json`.', 400); };
        $uuid = $args['uuid'] ?? false;
        $object = json_decode(json_encode($request->getParsedBody()));

        $validation = $this->validator->validate($object, 'https://glued/contacts/object.json');
        if ($validation->isValid()) {
            $res = [
                'valid' => true,
                'data' => $object
            ];
        }

        if ($validation->hasError()) {
            $formatter = new ErrorFormatter();
            $error = $validation->error();
            $res = $formatter->formatOutput($error, "basic");
            return $response->withJson($res);
        }

        $this->contacts_patch(json_encode($object), $uuid);
        return $response->withJson($res);
    }

    public function contacts_r1(Request $request, Response $response, array $args = []): Response
    {
        $wq = "";
        $vals = [];
        $data = [];
        $object = $args['uuid'] ?? false;
        if ($object) {
            // fetch an object by path
            $vals[] = $object;
            $q = "SELECT JSON_INSERT(c_data, '$.uuid', bin_to_uuid(c_uuid, true)) AS data 
              FROM t_contacts_objects
              WHERE c_uuid = uuid_to_bin(?, true)";
        } else {
            // fetch objects array by query params
            foreach ($request->getQueryParams() as $qp) {
                if (is_array($qp)) {
                    foreach ($qp as $item) {
                        $st[] = "c_ft LIKE CONCAT('%',?,'%')";
                        $vals[] = $item;
                    }
                } else {
                    $st[] = "c_ft LIKE CONCAT('%',?,'%')";
                    $vals[] = $qp;
                }
            }
            if (count($request->getQueryParams()) > 0) {
                $wq .= " AND " . implode(" OR ", $st);
            }
            $q = "SELECT JSON_ARRAYAGG(JSON_INSERT(c_data, '$.uuid', bin_to_uuid(c_uuid, true))) AS data 
              FROM t_contacts_objects
              WHERE 1=1 $wq";
        }

        $result = $this->mysqli->execute_query($q, $vals);
        foreach ($result as $row) {
            if ($row['data']) {
                $data = json_decode($row['data']);
            }
            break;
        }
        $data = [
            'timestamp' => microtime(),
            'status' => 'OK',
            'data' => $data,
            'service' => basename(__ROOT__),
        ];
        return $response->withJson($data);
    }

    public function import_r1(Request $request, Response $response, array $args = []): Response
    {
        $action = $args['act'] ?? null;
        $fid = $args['key'] ?? null;

        $sql = "
        INSERT INTO `t_contacts_objects` (`c_uuid`, `c_data`, `c_ft`)
        SELECT
          uuid_to_bin(uuid(), true) AS `c_uuid`,
          c_data AS `c_data`,
          GROUP_CONCAT(extracted_val.val SEPARATOR ' ') AS c_ft
        FROM
          `t_if__objects`,
           JSON_TABLE(
             c_data,
              '$**.val' COLUMNS (
                val VARCHAR(255) PATH '$'
              )
           ) AS extracted_val
        WHERE c_action = uuid_to_bin(?, true) AND c_fid = ?
        GROUP BY c_data 
        ON DUPLICATE KEY UPDATE
          `c_ft` = VALUES(`c_ft`);
        ";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("ss", $action, $fid);
        $stmt->execute();
        $stmt->close();

        $q = "SELECT  bin_to_uuid(co.c_uuid, true) as uuid FROM `t_if__objects` io
              left join t_contacts_objects co ON co.c_hash = io.c_hash
              WHERE io.c_action = uuid_to_bin(?, true) AND io.c_fid = ?
        ";
        $result = $this->mysqli->execute_query($q, [$action, $fid]);
        $obj = null;
        foreach ($result as $row) {
            $obj = $row;
            break;
        }
        if (is_null($obj)) {
            throw new \Exception('Contact not imported.', 500);
        }

        if (array_key_exists('uuid', $obj)) {
            $obj['uri'] = "{$this->settings['glued']['protocol']}{$this->settings['glued']['hostname']}{$this->settings['routes']['be_contacts_v1']['path']}/{$obj['uuid']}";
        }
        $data = [
            'timestamp' => microtime(),
            'status' => 'OK',
            'service' => basename(__ROOT__),
            'data' => $obj
        ];
        return $response->withJson($data);
    }


    /**
     * Returns a health status response.
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response Json result set.
     */
    public function health(Request $request, Response $response, array $args = []): Response
    {
        $params = $request->getQueryParams();
        $data = [
            'timestamp' => microtime(),
            'status' => 'OK',
            'params' => $params,
            'service' => basename(__ROOT__),
            'provided-for' => $_SERVER['X-GLUED-AUTH-UUID'] ?? 'anon',
        ];
        return $response->withJson($data);
    }


    /**
     * Returns a health status response.
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response Json result set.
     */
    public function rels_r1(Request $request, Response $response, array $args = []): Response
    {
        $rp = $request->getQueryParams();
        $qp = null;
        $qs = <<<EOT
        select 
                json_merge_preserve(
                    json_object("uuid", bin_to_uuid( `c_uuid`, true)),
                    `c_data`
                ) as res_rows
        from `t_contacts_rels`
        EOT;
        $qs = (new \Glued\Lib\QueryBuilder())->select($qs);
        $wm = ['tags' => 'json_contains(`c_data`->>"$.tags", ?)'];
        $this->utils->mysqlJsonQueryFromRequest($rp, $qs, $qp, $wm);
        $res = $this->db->rawQuery($qs, $qp);
        return $this->utils->mysqlJsonResponse($response, $res);
    }
}
